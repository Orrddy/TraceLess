<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_scan_records', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 255);
            $table->string('username', 64);
            $table->json('sources');
            $table->json('green_sources');
            $table->unsignedSmallInteger('checked_count')->default(10);
            $table->unsignedSmallInteger('public_matches')->default(0);
            $table->unsignedSmallInteger('username_matches')->default(0);
            $table->unsignedSmallInteger('email_matches')->default(0);
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_scan_records');
    }
};
