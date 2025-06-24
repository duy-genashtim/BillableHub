<?php
namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;

class AuthenticateWithJWT
{
    private const EXCLUDED_ROUTES = [
        'api/timedoctor/auth',
        'api/timedoctor/callback',
        'api/timedoctor/stream-worklog-sync',
        'api/timedoctor-v2/stream-worklog-sync',
    ];
    public function handle(Request $request, Closure $next)
    {
        // ✅ Skip JWT auth if current route is in the excluded list
        foreach (self::EXCLUDED_ROUTES as $excluded) {
            if ($request->is($excluded)) {
                return $next($request);
            }
        }

        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'Token not provided'], 401);
        }

        try {
            $decoded = JWT::decode($token, new Key(config('app.key'), 'HS256'));
            $user    = User::find($decoded->sub);

            if (! $user) {
                return response()->json(['error' => 'User not found'], 401);
            }

            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            return $next($request);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
    }
}