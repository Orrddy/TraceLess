<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Monotonic counters are updated from both web requests and queue
        // workers, so they live in the shared database rather than in a cache.
        Schema::create('ip_intelligence_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric', 120)->unique();
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_intelligence_metrics');
    }
};
