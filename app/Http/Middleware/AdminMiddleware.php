<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the authenticated user is an admin
        if (auth()->check() && auth()->user()->role === 'Admin') {
            return $next($request);
        }

        // If not an admin, return an unauthorized response
        return response()->json(['message' => 'Unauthorized'], 403);
    }
}
