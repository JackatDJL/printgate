<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

#[Fillable(['owner_id', 'name', 'credential_digest', 'credential_issued_at', 'revoked_at'])]
class ConnectSite extends Model
{
    use HasUlids;

    protected $table = 'connect_sites';

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function pairingClaims(): HasMany
    {
        return $this->hasMany(ConnectPairingClaim::class, 'site_id');
    }

    protected function casts(): array
    {
        return [
            'credential_issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
