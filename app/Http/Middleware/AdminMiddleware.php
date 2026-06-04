<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AdminMiddleware — protège toutes les routes /api/admin/*.
 *
 * Vérifie que l'utilisateur authentifié possède le rôle 'admin'.
 * Retourne HTTP 403 si l'utilisateur n'est pas admin ou n'est pas authentifié.
 *
 * Validates: Requirements 20.1
 */
class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
