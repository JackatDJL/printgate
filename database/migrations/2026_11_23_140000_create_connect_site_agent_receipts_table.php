<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Connect\SiteAgent\ReceiptDatabase;

return new class extends Migration
{
    public function up(): void
    {
        ReceiptDatabase::configure();
        Schema::connection(ReceiptDatabase::CONNECTION)->create('connect_site_agent_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('site_id', 64);
            $table->string('remote_job_id', 128);
            $table->string('state', 32);
            $table->string('backend_job_id')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'remote_job_id']);
        });
    }

    public function down(): void
    {
        ReceiptDatabase::configure();
        Schema::connection(ReceiptDatabase::CONNECTION)->dropIfExists('connect_site_agent_receipts');
    }
};
