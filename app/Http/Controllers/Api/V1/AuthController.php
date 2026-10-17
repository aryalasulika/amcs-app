<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\OperationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        /** @var User $user */
        $user = User::where('email', $request->input('email'))->firstOrFail();

        if (! $user->active) {
            Auth::guard('web')->logout();

            throw OperationException::make('Akun Anda dinonaktifkan. Hubungi administrator.', 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken($request->input('device_name', 'amcs-client'))->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => new UserResource($user->load('roles')),
            'token' => $token,
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles'));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $user?->currentAccessToken()?->delete();

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logout berhasil.']);
    }
}
