<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsCustomer {
    public function handle(Request $request, Closure $next): Response {
        if (!auth('api')->check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (auth('api')->user()->role !== 'customer') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
