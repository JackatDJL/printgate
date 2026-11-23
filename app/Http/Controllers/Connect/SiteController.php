<?php

namespace App\Http\Controllers\Connect;

use App\Connect\PairingService;
use App\Http\Controllers\Controller;
use App\Models\ConnectSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function revoke(Request $request, ConnectSite $site, PairingService $pairing): RedirectResponse
    {
        abort_unless($request->user()->connectSites()->whereKey($site->id)->exists(), 404);
        $pairing->revoke($site);

        return redirect()->route('connect.index')->with('status', 'Site credential revoked.');
    }
}
