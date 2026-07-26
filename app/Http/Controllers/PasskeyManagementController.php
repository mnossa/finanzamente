<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passkeys\Passkey;

class PasskeyManagementController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $passkeys = $user->passkeys()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Passkey $passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'last_used_at' => $passkey->last_used_at?->format('d/m/Y H:i'),
                'created_at' => $passkey->created_at?->format('d/m/Y'),
            ])
            ->values()
            ->all();

        return Inertia::render('Profile/PasskeyManage', [
            'passkeys' => $passkeys,
            'successMessage' => session('success'),
            'errorMessage' => session('error'),
        ]);
    }
}
