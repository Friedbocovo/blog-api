<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new visitor account.
     *
     * POST /api/auth/register
     * throttle:5,1
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        // Role is always 'visitor' — admin accounts are created only via seeder
        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'], // hashed by the 'hashed' cast on User
            'role'     => 'visitor',
        ]);

        $token = $user->createToken('auth-token', ['*'], now()->addDays(7))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user,
        ], 201);
    }

    /**
     * Authenticate an existing user and return a token.
     *
     * POST /api/auth/login
     * throttle:5,1
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Identifiants invalides.',
            ], 401);
        }

        /** @var User $user */
        $user  = Auth::user();
        $token = $user->createToken('auth-token', ['*'], now()->addDays(7))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user,
        ]);
    }

    /**
     * Revoke the current access token (logout).
     *
     * POST /api/auth/logout
     * auth:sanctum
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }

    /**
     * Return the currently authenticated user with relations.
     *
     * GET /api/auth/me
     * auth:sanctum
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['likedPosts', 'favoritePosts']);

        return response()->json($user);
    }
}
