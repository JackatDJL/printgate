<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['owner_id', 'invitation_id', 'claim_digest', 'site_name', 'comparison_code', 'expires_at', 'confirmed_at', 'delivered_at', 'credential', 'site_id'])]
class ConnectPairingClaim extends Model
{
    use HasUlids;

    protected $table = 'connect_pairing_claims';

    protected $hidden = ['claim_digest', 'credential'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(ConnectSite::class, 'site_id');
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(ConnectInvitation::class, 'invitation_id');
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'credential' => 'encrypted',
        ];
    }
}
