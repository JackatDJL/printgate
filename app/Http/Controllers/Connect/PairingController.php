<?php

namespace App\Http\Controllers\Connect;

use App\Connect\PairingService;
use App\Http\Controllers\Controller;
use App\Models\ConnectPairingClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PairingController extends Controller
{
    public function index(Request $request): View
    {
        return view('connect.index', [
            'sites' => $request->user()->connectSites()->latest()->get(),
            'claims' => $request->user()->connectPairingClaims()->whereNull('confirmed_at')->where('expires_at', '>', now())->latest()->get(),
            'invitationToken' => $request->session()->get('connect_invitation_token'),
        ]);
    }

    public function invite(Request $request, PairingService $pairing): RedirectResponse
    {
        $result = $pairing->createInvitation($request->user());

        return redirect()->route('connect.index')->with('connect_invitation_token', $result['token']);
    }

    public function confirm(Request $request, ConnectPairingClaim $claim, PairingService $pairing): RedirectResponse
    {
        $validated = $request->validate(['comparison_code' => ['required', 'string', 'size:6']]);
        $pairing->confirm($request->user(), $claim, $validated['comparison_code']);

        return redirect()->route('connect.index')->with('status', 'Site pairing confirmed.');
    }

    public function begin(Request $request, PairingService $pairing): JsonResponse
    {
        abort_unless($request->isSecure(), 400, 'HTTPS is required.');
        $validated = $request->validate([
            'invitation' => ['required', 'string', 'size:64'],
            'site_name' => ['required', 'string', 'max:120'],
            'comparison_code' => ['required', 'regex:/^[0-9]{6}$/'],
        ]);
        $result = $pairing->begin($validated['invitation'], $validated['site_name'], $validated['comparison_code']);

        return response()->json([
            'claim_id' => $result['claim']->id,
            'claim_token' => $result['claim_token'],
            'comparison_code' => $result['claim']->comparison_code,
            'expires_at' => $result['claim']->expires_at->toIso8601String(),
        ], 201)->header('Cache-Control', 'no-store');
    }

    public function delivery(Request $request, string $claim, PairingService $pairing): JsonResponse
    {
        abort_unless($request->isSecure(), 400, 'HTTPS is required.');
        $validated = $request->validate(['claim_token' => ['required', 'string', 'size:64']]);

        return response()->json($pairing->delivery($claim, $validated['claim_token']))
            ->header('Cache-Control', 'no-store');
    }
}
