<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Role is a plain string rather than an enum so a future role can be added
        // without a database migration. 'client' is the default for every account.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('client');
            $table->timestamp('suspended_at')->nullable();
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'suspended_at']);
        });
    }
};
