<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connect_print_jobs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('site_id')->constrained('connect_sites')->cascadeOnDelete();
            $table->string('printer_name');
            $table->string('original_filename');
            $table->string('document_path');
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->string('state')->default('queued');
            $table->string('backend_job_id')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['site_id', 'state', 'expires_at']);
            $table->index(['owner_id', 'state', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connect_print_jobs');
    }
};
