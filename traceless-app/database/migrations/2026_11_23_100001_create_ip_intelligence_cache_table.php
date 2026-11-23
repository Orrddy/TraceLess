<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_intelligence_cache', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->unsignedTinyInteger('ip_version');
            $table->json('data')->nullable();
            $table->json('provider_results')->nullable();
            $table->json('provider_status')->nullable();
            $table->unsignedTinyInteger('risk_score')->nullable();
            $table->string('risk_grade', 1)->nullable();
            $table->string('risk_classification', 16)->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->unsignedInteger('analysis_ms')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->string('lock_token', 64)->nullable();
            $table->timestamp('first_scanned_at')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
            $table->index('locked_until');
            $table->index('lock_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_intelligence_cache');
    }
};
