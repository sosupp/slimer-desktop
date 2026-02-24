<?php

namespace Sosupp\SlimerDesktop\Http\Middleware;

use Closure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyRemoteSyncToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            abort(401, 'Missing token');
        }

        try {
            $payload = JWT::decode($token, new Key(config('services.desktop.api.secret'), 'HS256'));

            // Additional checks, if needed
            if ($payload->iss !== config('slimerdesktop.jwt.iss')) {
                abort(401, 'Invalid issuer');
            }

        } catch (Exception $e) {
            abort(401, 'Invalid or expired token');
        }

        return $next($request);
    }
}
