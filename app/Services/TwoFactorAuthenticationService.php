<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFactorAuthenticationService
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQrCodeSvg(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeInline(
            config('app.name', 'Finanzamente'),
            $user->email,
            $secret
        );
    }

    public function verifyCode(User $user, string $code): bool
    {
        if (! $user->two_factor_secret) {
            return false;
        }

        return $this->google2fa->verifyKey(
            $user->two_factor_secret,
            preg_replace('/\s+/', '', $code) ?? ''
        );
    }

    public function verifyPendingSecret(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey(
            $secret,
            preg_replace('/\s+/', '', $code) ?? ''
        );
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        return Collection::times(self::RECOVERY_CODE_COUNT, fn () => Str::upper(Str::random(4).'-'.Str::random(4)))
            ->all();
    }

    /**
     * @param  list<string>  $plainCodes
     * @return list<string>
     */
    public function hashRecoveryCodes(array $plainCodes): array
    {
        return array_map(fn (string $code) => Hash::make($code), $plainCodes);
    }

    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $normalized = Str::upper(str_replace(' ', '', $code));
        $hashedCodes = $user->two_factor_recovery_codes ?? [];

        foreach ($hashedCodes as $index => $hashed) {
            if (Hash::check($normalized, $hashed)) {
                unset($hashedCodes[$index]);
                $user->forceFill([
                    'two_factor_recovery_codes' => array_values($hashedCodes),
                ])->save();

                return true;
            }
        }

        return false;
    }

    public function hasConfirmedTwoFactor(User $user): bool
    {
        return $user->two_factor_secret !== null
            && $user->two_factor_confirmed_at !== null;
    }
}
