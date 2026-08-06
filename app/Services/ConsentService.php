<?php

namespace App\Services;

use App\Models\Consent;
use App\Models\ConsentEvent;
use App\Models\User;
use Illuminate\Support\Arr;

class ConsentService
{
    public function setConsent(
        User $user,
        string $purpose,
        string $status,
        array $context = []
    ): Consent {
        $normalizedStatus = strtolower(trim($status));
        if (! in_array($normalizedStatus, ['granted', 'revoked', 'pending'], true)) {
            throw new \InvalidArgumentException('Stato consenso non valido.');
        }

        $source = Arr::get($context, 'source', 'system');
        $legalBasis = Arr::get($context, 'legal_basis', 'consent');
        $policyVersion = Arr::get($context, 'policy_version', 'unknown');
        $metadata = Arr::get($context, 'metadata', []);

        $consent = Consent::query()->firstOrNew([
            'user_id' => $user->id,
            'purpose' => $purpose,
        ]);

        $oldStatus = $consent->exists ? $consent->status : null;
        $now = now();

        $consent->fill([
            'status' => $normalizedStatus,
            'source' => $source,
            'legal_basis' => $legalBasis,
            'policy_version' => $policyVersion,
            'metadata' => $metadata,
        ]);

        if ($normalizedStatus === 'granted') {
            $consent->granted_at = $now;
            $consent->revoked_at = null;
        } elseif ($normalizedStatus === 'revoked') {
            $consent->revoked_at = $now;
        }

        $consent->save();

        ConsentEvent::query()->create([
            'consent_id' => $consent->id,
            'user_id' => $user->id,
            'event_type' => $this->resolveEventType($oldStatus, $normalizedStatus),
            'old_status' => $oldStatus,
            'new_status' => $normalizedStatus,
            'source' => $source,
            'ip_hash' => $this->hashSensitiveValue(Arr::get($context, 'ip')),
            'user_agent_hash' => $this->hashSensitiveValue(Arr::get($context, 'user_agent')),
            'policy_version' => $policyVersion,
            'occurred_at' => $now,
            'metadata' => $metadata,
            'created_at' => $now,
        ]);

        return $consent->fresh();
    }

    private function resolveEventType(?string $oldStatus, string $newStatus): string
    {
        if ($oldStatus === null) {
            return $newStatus === 'granted' ? 'granted' : 'updated';
        }

        if ($oldStatus !== $newStatus && $newStatus === 'revoked') {
            return 'revoked';
        }

        if ($oldStatus !== $newStatus && $newStatus === 'granted') {
            return 'granted';
        }

        return 'updated';
    }

    private function hashSensitiveValue(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $salt = (string) env('ADV_THROTTLE_SALT', 'default_salt');

        return hash('sha256', $raw.$salt);
    }
}
