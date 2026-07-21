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
        $user = DB::transaction(fn (): User => User::create($request->safe()->only([
            'name',
            'username',
            'email',
            'password',
        ])));

        event(new Registered($user));

        $token = $user->createToken($request->string('device_name')->value());

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
        ], 201);
    }
}
