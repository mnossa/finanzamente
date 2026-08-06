<?php

namespace App\Http\Responses;

use App\Http\Controllers\HouseholdInvitationController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $user = Auth::user();

        if ($user !== null) {
            HouseholdInvitationController::processPendingInvitation($user);
        }

        $redirectUrl = redirect()->intended(config('passkeys.redirect', '/dashboard'))->getTargetUrl();

        if ($request->wantsJson()) {
            return new JsonResponse([
                'redirect' => $redirectUrl,
            ], 200);
        }

        return redirect()->to($redirectUrl);
    }
}
