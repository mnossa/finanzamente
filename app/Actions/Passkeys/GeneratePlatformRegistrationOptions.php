<?php

namespace App\Actions\Passkeys;

use Cose\Algorithms;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkeys;
use ParagonIE\ConstantTime\Base64UrlSafe;
use RuntimeException;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class GeneratePlatformRegistrationOptions extends GenerateRegistrationOptions
{
    /**
     * Generate platform-oriented registration options for biometric PWA unlock.
     */
    public function __invoke(Authenticatable $user): PublicKeyCredentialCreationOptions
    {
        if (! $user instanceof PasskeyUser) {
            throw new RuntimeException('User model must implement the PasskeyUser contract.');
        }

        return PublicKeyCredentialCreationOptions::create(
            rp: $this->relyingParty(),
            user: $this->userEntity($user),
            challenge: random_bytes(32),
            pubKeyCredParams: $this->supportedAlgorithms(),
            authenticatorSelection: $this->authenticatorSelection(),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $this->excludedCredentials($user),
            timeout: Passkeys::timeout(),
            hints: ['client-device'],
        );
    }

    /**
     * Prefer platform authenticators (impronta / Face ID / Windows Hello).
     *
     * All four authenticatorSelection fields are set explicitly: Google Password
     * Manager on Android is known to fail create() when the object is incomplete.
     */
    public function authenticatorSelection(): AuthenticatorSelectionCriteria
    {
        return AuthenticatorSelectionCriteria::create(
            authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
        );
    }

    /**
     * Use the product name in authenticator UI (not the bare hostname).
     */
    protected function relyingParty(): PublicKeyCredentialRpEntity
    {
        return PublicKeyCredentialRpEntity::create(
            name: (string) config('app.name', 'Finanzamente'),
            id: Passkeys::relyingPartyId(),
        );
    }

    /**
     * Ensure WebAuthn user.name / displayName are never blank (breaks Android CM).
     */
    protected function userEntity(PasskeyUser $user): PublicKeyCredentialUserEntity
    {
        $username = trim($user->getPasskeyUsername());
        $displayName = trim($user->getPasskeyDisplayName());

        if ($username === '') {
            $username = 'user-'.$user->getAuthIdentifier();
        }

        if ($displayName === '') {
            $displayName = $username;
        }

        return PublicKeyCredentialUserEntity::create(
            name: $username,
            id: $user->getPasskeyUserHandle(),
            displayName: $displayName,
        );
    }

    /**
     * @return array<PublicKeyCredentialDescriptor>
     */
    public function excludedCredentials(PasskeyUser $user): array
    {
        $type = PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY;

        return $user->passkeys()->get()->map(
            function ($passkey) use ($type): ?PublicKeyCredentialDescriptor {
                $credentialId = (string) $passkey->credential_id;

                if ($credentialId === '') {
                    return null;
                }

                try {
                    return PublicKeyCredentialDescriptor::create(
                        $type,
                        Base64UrlSafe::decodeNoPadding($credentialId)
                    );
                } catch (\Throwable) {
                    // Skip malformed ids so one bad row cannot break registration.
                    return null;
                }
            }
        )->filter()->values()->all();
    }

    /**
     * @return array<PublicKeyCredentialParameters>
     */
    public function supportedAlgorithms(): array
    {
        $type = PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY;

        return [
            PublicKeyCredentialParameters::create($type, Algorithms::COSE_ALGORITHM_ES256),
            PublicKeyCredentialParameters::create($type, Algorithms::COSE_ALGORITHM_RS256),
        ];
    }
}
