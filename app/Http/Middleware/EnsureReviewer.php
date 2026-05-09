<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates reviewer-only endpoints (verification queue, approval, etc.).
 * Must be applied AFTER `auth.api` so $request->user() is populated.
 */
class EnsureReviewer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! ($user->is_reviewer ?? false)) {
            return response()->json([
                'message' => 'Reviewer access required.',
            ], 403);
        }

        return $next($request);
    }
}
