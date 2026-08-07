<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RegisteredUserController extends Controller
{
    /**
     * Register a new user and issue an API token for their device.
     */
    public function store(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(fn (): User => User::create([
            ...$request->safe()->only(['full_name', 'username', 'email']),
            'password_hash' => $request->string('password')->value(),
            /*
             * Stamped here rather than taken from the request: the client says
             * whether the box was ticked, and the server says when. A date the
             * client could set is not evidence of anything.
             */
            'terms_accepted_at' => now(),
        ]));

        event(new Registered($user));

        $token = $user->createToken($request->string('device_name')->value());

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
        ], 201);
    }
}
