<?php

namespace App\Http\Controllers;

use App\Models\AboutPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AboutController — public "About" page management.
 *
 * Validates: Requirements 9
 */
class AboutController extends Controller
{
    /**
     * GET /api/about
     *
     * Return the singleton About page content (public, no auth required).
     *
     * Validates: Requirement 9.1
     */
    public function show(): JsonResponse
    {
        $about = AboutPage::first();

        if (!$about) {
            // Return empty structure when no content has been saved yet
            return response()->json([
                'bio'            => null,
                'links'          => null,
                'extra_sections' => null,
                'profile_photo'  => null,
                'updated_at'     => null,
            ]);
        }

        return response()->json($about);
    }

    /**
     * PUT /api/about  [admin only]
     *
     * Create or update the singleton About page entry.
     * Uses updateOrCreate([], $data) to guarantee exactly one row.
     *
     * Validates: Requirements 9.2, 9.3, Property 4
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bio'            => ['sometimes', 'nullable', 'string'],
            'links'          => ['sometimes', 'nullable', 'array'],
            'extra_sections' => ['sometimes', 'nullable', 'array'],
            'profile_photo'  => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $validated['updated_at'] = now();

        $about = AboutPage::updateOrCreate([], $validated);

        return response()->json($about);
    }
}
