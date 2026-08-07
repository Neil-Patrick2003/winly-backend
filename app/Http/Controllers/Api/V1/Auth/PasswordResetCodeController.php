<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\SendPasswordResetCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PasswordResetCodeController extends Controller
{
    /**
     * Email a one-time code for setting a new password.
     *
     * Answers the same way whether or not the address has an account, and
     * whether or not a code was actually sent — a caller who could tell the
     * difference could read the user list out of this endpoint one address at
     * a time. The client shows the "check your email" screen regardless, which
     * is also the honest thing to show: if there is no account there, no email
     * is what should arrive.
     *
     * Soft-deleted users are excluded by the model's global scope, so a closed
     * account cannot be reopened by resetting its password.
     */
    public function store(ForgotPasswordRequest $request, SendPasswordResetCode $send): JsonResponse
    {
        $user = User::firstWhere('email', $request->string('email')->value());

        if ($user !== null) {
            $send->handle($user);
        }

        return response()->json([
            'message' => 'If that address has an account, a reset code is on its way.',
        ]);
    }
}
