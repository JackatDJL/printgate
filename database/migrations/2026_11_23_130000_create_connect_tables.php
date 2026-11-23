<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connect_sites', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->char('credential_digest', 64)->nullable()->unique();
            $table->timestamp('credential_issued_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'revoked_at']);
        });

        Schema::create('connect_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->char('token_digest', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'expires_at', 'used_at']);
        });

        Schema::create('connect_pairing_claims', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('invitation_id')->constrained('connect_invitations')->cascadeOnDelete();
            $table->char('claim_digest', 64)->unique();
            $table->string('site_name');
            $table->string('comparison_code', 12);
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('credential')->nullable();
            $table->foreignUlid('site_id')->nullable()->constrained('connect_sites')->nullOnDelete();
            $table->timestamps();
            $table->index(['owner_id', 'expires_at', 'confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connect_pairing_claims');
        Schema::dropIfExists('connect_invitations');
        Schema::dropIfExists('connect_sites');
    }
};
