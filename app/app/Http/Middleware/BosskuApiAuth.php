<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional API token auth for public deployments. Disabled by default for OSS local setup.
 */
class BosskuApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('bossku.api_auth_enabled', false)) {
            return $next($request);
        }

        $expected = (string) config('bossku.api_token', '');
        if ($expected === '') {
            return response()->json([
                'message' => 'API auth is enabled but BOSSKU_API_TOKEN is not configured.',
            ], 503);
        }

        $provided = $this->extractToken($request);
        if ($provided === null || ! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Unauthorized. Provide Authorization: Bearer <token> or X-Bossku-Token header.',
            ], 401);
        }

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('X-Bossku-Token');
        if (is_string($header) && $header !== '') {
            return $header;
        }

        $auth = $request->header('Authorization', '');
        if (is_string($auth) && str_starts_with(strtolower($auth), 'bearer ')) {
            return trim(substr($auth, 7));
        }

        return null;
    }
}
