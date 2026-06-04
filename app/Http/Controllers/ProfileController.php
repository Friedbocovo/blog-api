<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

/**
 * ProfileController — user profile management.
 *
 * Validates: Requirements 8
 */
class ProfileController extends Controller
{
    /**
     * GET /api/profile
     *
     * Return the authenticated user's profile data.
     *
     * Validates: Requirement 8.1
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * PUT /api/profile
     *
     * Update profile fields for the authenticated user.
     *
     * Validates: Requirement 8.2
     */
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

    /**
     * POST /api/profile/avatar
     *
     * Upload and store an avatar image for the authenticated user.
     * Uses Cloudinary when CLOUDINARY_URL is configured, otherwise local storage.
     *
     * Validates: Requirements 8.3, 8.4, 8.5, 8.6
     */
    public function avatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,gif,webp',
                'max:5120', // 5 MB in kilobytes
            ],
        ]);

        $user = $request->user();

        $url = $this->storeAvatar($request->file('avatar'));

        $user->update(['avatar' => $url]);

        return response()->json($user->fresh());
    }

    /**
     * Store the uploaded avatar using Cloudinary (if configured) or local disk.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return string  The public URL of the stored image.
     */
    private function storeAvatar(\Illuminate\Http\UploadedFile $file): string
    {
        // If Cloudinary is configured, upload there
        if ($this->cloudinaryConfigured()) {
            return $this->uploadToCloudinary($file);
        }

        // Otherwise store locally on the public disk
        $path = $file->store('avatars', 'public');

        return Storage::disk('public')->url($path);
    }

    /**
     * Determine whether Cloudinary is configured via environment variables.
     */
    private function cloudinaryConfigured(): bool
    {
        return !empty(config('services.cloudinary.url'))
            || !empty(env('CLOUDINARY_URL'));
    }

    /**
     * Upload the file to Cloudinary and return the secure URL.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return string
     */
    private function uploadToCloudinary(\Illuminate\Http\UploadedFile $file): string
    {
        $cloudinaryUrl = config('services.cloudinary.url') ?: env('CLOUDINARY_URL');

        // Parse the Cloudinary URL: cloudinary://{api_key}:{api_secret}@{cloud_name}
        $parsed = parse_url($cloudinaryUrl);
        $cloudName = $parsed['host'] ?? '';
        $apiKey    = $parsed['user'] ?? '';
        $apiSecret = $parsed['pass'] ?? '';

        $endpoint = "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload";

        $response = \Illuminate\Support\Facades\Http::asMultipart()
            ->post($endpoint, [
                [
                    'name'     => 'file',
                    'contents' => fopen($file->getRealPath(), 'r'),
                    'filename' => $file->getClientOriginalName(),
                ],
                ['name' => 'api_key',   'contents' => $apiKey],
                ['name' => 'timestamp', 'contents' => (string) time()],
            ]);

        return $response->json('secure_url');
    }
}
