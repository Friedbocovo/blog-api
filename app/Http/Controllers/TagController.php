<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;

/**
 * TagController — tag listing.
 *
 * Validates: Requirements 10
 */
class TagController extends Controller
{
    /**
     * GET /api/tags
     *
     * Return all available tags.
     */
    public function index(): JsonResponse
    {
        return response()->json(Tag::all());
    }
}
