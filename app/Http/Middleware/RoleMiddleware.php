<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user() || ! in_array($request->user()->role, $roles)) {
            return response()->json([
                'message' => 'Unauthorized. You do not have access to this resource.'
            ], 403);
        }

        // Students and companies must be approved by admin before role endpoints.
        if ($request->user()->role === 'student' && ! $request->user()->is_active) {
            return response()->json([
                'message' => 'Your student account is pending admin approval.'
            ], 403);
        }

        if ($request->user()->role === 'company' && ! $request->user()->is_active) {
            return response()->json([
                'message' => 'Your company account is pending admin approval.'
            ], 403);
        }

        return $next($request);
    }
}
