<?php

namespace App\Http\Middleware;

use App\Models\ConnectSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateConnectSite
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->isSecure(), 400, 'HTTPS is required.');

        $authorization = $request->header('Authorization', '');
        if (! preg_match('/^Bearer ([a-f0-9]{64})$/', $authorization, $matches)) {
            abort(401);
        }

        $digest = hash('sha256', $matches[1]);
        $site = ConnectSite::query()->with('owner')
            ->where('credential_digest', $digest)
            ->whereNull('revoked_at')
            ->first();
        abort_unless($site, 401);

        $request->attributes->set('connect.site', $site);
        $request->attributes->set('connect.owner_id', $site->owner_id);
        $request->attributes->set('connect.user_id', $site->owner_id);

        return $next($request);
    }
}
