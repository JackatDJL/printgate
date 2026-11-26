<?php

namespace App\Connect;

use App\Models\ConnectInvitation;
use App\Models\ConnectPairingClaim;
use App\Models\ConnectSite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PairingService
{
    /** @return array{invitation: ConnectInvitation, token: string} */
    public function createInvitation(User $owner): array
    {
        $token = bin2hex(random_bytes(32));
        $invitation = $owner->connectInvitations()->create([
            'token_digest' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(10),
        ]);

        return ['invitation' => $invitation, 'token' => $token];
    }

    /** @return array{claim: ConnectPairingClaim, claim_token: string} */
    public function begin(string $invitationToken, string $siteName, string $comparisonCode): array
    {
        return DB::transaction(function () use ($invitationToken, $siteName, $comparisonCode): array {
            $invitation = ConnectInvitation::query()
                ->where('token_digest', hash('sha256', $invitationToken))
                ->lockForUpdate()
                ->first();

            if (! $invitation || $invitation->used_at || $invitation->expires_at->isPast()) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is invalid or expired.']);
            }

            $claimToken = bin2hex(random_bytes(32));
            $claim = $invitation->owner->connectPairingClaims()->create([
                'invitation_id' => $invitation->id,
                'claim_digest' => hash('sha256', $claimToken),
                'site_name' => $siteName,
                'comparison_code' => $comparisonCode,
                'expires_at' => now()->addMinutes(10),
            ]);

            $invitation->update(['used_at' => now()]);

            return ['claim' => $claim, 'claim_token' => $claimToken];
        });
    }

    public function confirm(User $owner, ConnectPairingClaim $claim, string $comparisonCode): void
    {
        DB::transaction(function () use ($owner, $claim, $comparisonCode): void {
            $claim = ConnectPairingClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            abort_unless($claim->owner_id === $owner->id, 404);
            abort_if($claim->confirmed_at || $claim->expires_at->isPast(), 410);

            if (! hash_equals($claim->comparison_code, $comparisonCode)) {
                throw ValidationException::withMessages(['comparison_code' => 'The comparison code does not match.']);
            }

            $credential = bin2hex(random_bytes(32));
            $site = $owner->connectSites()->create([
                'name' => $claim->site_name,
                'credential_digest' => hash('sha256', $credential),
                'credential_issued_at' => now(),
            ]);
            $claim->update(['site_id' => $site->id, 'credential' => $credential, 'confirmed_at' => now()]);
        });
    }

    /** @return array{status: string, credential?: string, site_id?: string} */
    public function delivery(string $claimId, string $claimToken): array
    {
        return DB::transaction(function () use ($claimId, $claimToken): array {
            $claim = ConnectPairingClaim::query()->whereKey($claimId)->lockForUpdate()->first();
            abort_unless($claim && hash_equals($claim->claim_digest, hash('sha256', $claimToken)), 401);
            abort_if($claim->expires_at->isPast(), 410);

            if (! $claim->confirmed_at) {
                return ['status' => 'pending'];
            }
            if ($claim->delivered_at || ! $claim->credential || ! $claim->site || $claim->site->revoked_at) {
                abort(410);
            }

            $result = ['status' => 'paired', 'credential' => $claim->credential, 'site_id' => $claim->site_id];
            $claim->update(['credential' => null, 'delivered_at' => now()]);

            return $result;
        });
    }

    public function revoke(ConnectSite $site): void
    {
        $site->update(['revoked_at' => now(), 'credential_digest' => null]);
    }
}
