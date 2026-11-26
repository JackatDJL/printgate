<?php

namespace App\Models;

use App\Connect\ConnectPrintJobState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['owner_id', 'site_id', 'printer_name', 'original_filename', 'document_path', 'sha256', 'size', 'state', 'backend_job_id', 'expires_at'])]
class ConnectPrintJob extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'state' => ConnectPrintJobState::class,
            'expires_at' => 'datetime',
            'size' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(ConnectSite::class, 'site_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
