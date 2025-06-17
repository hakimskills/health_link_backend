<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        return $next($request);
    }
}
