<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\ConsumePasswordResetCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NewPasswordController extends Controller
{
    /**
     * Set a new password from an emailed code and sign the device back in.
     *
     * Every existing token is revoked first. Someone resetting a password has
     * usually either forgotten it or lost control of the account, and in the
     * second case leaving the old sessions alive would hand the new password
     * to a stranger who is already inside. The device doing the reset then gets
     * a fresh token, so the app can go straight into the feed rather than
     * bouncing the user to a sign-in screen to retype what they just chose.
     */
    public function store(ResetPasswordRequest $request, ConsumePasswordResetCode $consume): JsonResponse
    {
        $user = $request->resolveUser($consume);

        DB::transaction(function () use ($user, $request): void {
            // `password_hash` is cast to `hashed`, so the plain value is what
            // gets assigned and the cast does the work — the same way register
            // and the web reset flow write it.
            $user->forceFill([
                'password_hash' => $request->string('password')->value(),
            ])->save();

            $user->tokens()->delete();
        });

        event(new PasswordReset($user));

        $token = $user->createToken($request->string('device_name')->value());

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
        ]);
    }
}
