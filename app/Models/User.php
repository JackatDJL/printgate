<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function connectSites(): HasMany
    {
        return $this->hasMany(ConnectSite::class, 'owner_id');
    }

    public function connectInvitations(): HasMany
    {
        return $this->hasMany(ConnectInvitation::class, 'owner_id');
    }

    public function connectPairingClaims(): HasMany
    {
        return $this->hasMany(ConnectPairingClaim::class, 'owner_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
