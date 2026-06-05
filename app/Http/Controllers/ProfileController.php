<?php

namespace App\Http\Controllers;

use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * ProfileController — user profile management.
 */
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'         => ['sometimes', 'string', 'max:255'],
            'bio'          => ['sometimes', 'nullable', 'string'],
            'website'      => ['sometimes', 'nullable', 'url', 'max:255'],
            'social_links' => ['sometimes', 'nullable', 'array'],
            'password'     => ['sometimes', 'confirmed', Password::min(8)],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json($user->fresh());
    }

    public function avatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ]);

        $user = $request->user();

        $cloudinary = new CloudinaryService();
        $url = $cloudinary->upload($request->file('avatar'), 'avatars');

        $user->update(['avatar' => $url]);

        return response()->json($user->fresh());
    }
}
