<?php

namespace App\Models;

use App\Printing\PrintJobState;
use Database\Factories\PrintJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['state', 'printer_name', 'original_filename', 'document_path', 'backend_job_id', 'failure_message'])]
class PrintJob extends Model
{
    /** @use HasFactory<PrintJobFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => PrintJobState::class,
        ];
    }
}
