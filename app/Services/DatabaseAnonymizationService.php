<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Support\LocalEnvironmentGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseAnonymizationService
{
    public const DEFAULT_PASSWORD = 'password';

    /**
     * @return array<string, int>
     */
    public function run(): array
    {
        $this->assertSafeEnvironment();

        $counts = [];

        DB::transaction(function () use (&$counts) {
            $counts['users'] = $this->anonymizeUsers();
            $counts['households'] = $this->anonymizeHouseholds();
            $counts['accounts'] = $this->anonymizeAccounts();
            $counts['transactions'] = $this->anonymizeTransactions();
            $counts['investments'] = $this->anonymizeInvestments();
            $counts['debts_credits'] = $this->anonymizeDebtsCredits();
            $counts['subscriptions'] = $this->anonymizeSubscriptions();
            $counts['invitations'] = $this->anonymizeHouseholdInvitations();
            $counts['inbox_items'] = $this->anonymizeInboxItems();
            $counts['sessions'] = $this->truncateTable('sessions');
            $counts['password_reset_tokens'] = $this->truncateTable('password_reset_tokens');
            $counts['telegram_link_tokens'] = $this->truncateTable('telegram_link_tokens');
        });

        return $counts;
    }

    public function assertSafeEnvironment(): void
    {
        LocalEnvironmentGuard::assertLocalDevelopment('db:anonymize');
    }

    private function anonymizeUsers(): int
    {
        $password = Hash::make(self::DEFAULT_PASSWORD);
        $count = 0;

        User::withTrashed()->orderBy('id')->each(function (User $user) use ($password, &$count) {
            $user->forceFill([
                'name' => 'Utente '.$user->id,
                'email' => 'user'.$user->id.'@anon.finanzamente.local',
                'email_verified_at' => now(),
                'password' => $password,
                'remember_token' => null,
                'telegram_chat_id' => null,
                'fiscal_code' => null,
                'vat_number' => null,
            ])->saveQuietly();
            $count++;
        });

        return $count;
    }

    private function anonymizeHouseholds(): int
    {
        $count = 0;

        Household::query()->orderBy('id')->each(function (Household $household) use (&$count) {
            $household->forceFill([
                'name' => 'Household '.$household->id,
            ])->saveQuietly();
            $count++;
        });

        return $count;
    }

    private function anonymizeAccounts(): int
    {
        $count = 0;

        Account::query()->orderBy('id')->each(function (Account $account) use (&$count) {
            $typeLabel = Account::TYPES[$account->type] ?? 'Conto';
            $account->forceFill([
                'name' => $typeLabel.' '.$account->id,
            ])->saveQuietly();
            $count++;
        });

        return $count;
    }

    private function anonymizeTransactions(): int
    {
        $count = 0;

        Transaction::withTrashed()->orderBy('id')->chunkById(200, function ($transactions) use (&$count) {
            foreach ($transactions as $transaction) {
                $transaction->forceFill([
                    'description' => 'Movimento #'.$transaction->id,
                ])->saveQuietly();
                $count++;
            }
        });

        return $count;
    }

    private function anonymizeInvestments(): int
    {
        if (! Schema::hasTable('investments')) {
            return 0;
        }

        $count = 0;
        if (Schema::hasColumn('investments', 'notes')) {
            $count += DB::table('investments')->whereNotNull('notes')->update(['notes' => null]);
        }

        if (Schema::hasTable('investment_pacs') && Schema::hasColumn('investment_pacs', 'notes')) {
            $count += DB::table('investment_pacs')->whereNotNull('notes')->update(['notes' => null]);
        }

        return $count;
    }

    private function anonymizeDebtsCredits(): int
    {
        if (! Schema::hasTable('debts_credits')) {
            return 0;
        }

        $updated = 0;
        DB::table('debts_credits')->orderBy('id')->chunkById(200, function ($rows) use (&$updated) {
            foreach ($rows as $row) {
                DB::table('debts_credits')->where('id', $row->id)->update([
                    'counterparty' => 'Controparte '.$row->id,
                    'description' => $row->description !== null ? 'Debito/credito anonimizzato' : null,
                ]);
                $updated++;
            }
        });

        return $updated;
    }

    private function anonymizeSubscriptions(): int
    {
        if (! Schema::hasTable('subscriptions')) {
            return 0;
        }

        $payload = [];
        foreach ([
            'billing_name',
            'billing_email',
            'billing_address',
            'billing_city',
            'billing_zip',
            'billing_country',
            'billing_vat',
            'billing_company',
        ] as $column) {
            if (Schema::hasColumn('subscriptions', $column)) {
                $payload[$column] = null;
            }
        }

        if ($payload === []) {
            return 0;
        }

        return DB::table('subscriptions')->update($payload);
    }

    private function anonymizeHouseholdInvitations(): int
    {
        if (! Schema::hasTable('household_invitations')) {
            return 0;
        }

        $count = 0;
        DB::table('household_invitations')->orderBy('id')->chunkById(200, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                DB::table('household_invitations')->where('id', $row->id)->update([
                    'email' => 'invite+'.$row->id.'@anon.finanzamente.local',
                ]);
                $count++;
            }
        });

        return $count;
    }

    private function anonymizeInboxItems(): int
    {
        if (! Schema::hasTable('inbox_items')) {
            return 0;
        }

        $payload = [];
        foreach (['raw_text', 'image_path', 'ai_payload', 'description'] as $column) {
            if (Schema::hasColumn('inbox_items', $column)) {
                $payload[$column] = null;
            }
        }

        if ($payload === []) {
            return 0;
        }

        return DB::table('inbox_items')->update($payload);
    }

    private function truncateTable(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $count = (int) DB::table($table)->count();
        DB::table($table)->delete();

        return $count;
    }
}
