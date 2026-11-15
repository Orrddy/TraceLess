<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->unsignedInteger('sources_total')->default(0)->after('sources_checked');
            $table->boolean('demo_mode')->default(false)->after('current_stage');
            $table->text('error_message')->nullable()->after('demo_mode');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->timestamp('generated_at')->nullable()->after('summary');
            $table->unsignedInteger('download_count')->default(0)->after('generated_at');
            $table->string('signature')->nullable()->after('download_count');
            $table->boolean('demo_mode')->default(false)->after('signature');
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->string('action_url')->nullable()->after('message');
            $table->timestamp('read_at')->nullable()->after('is_read');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan')->default('snapshot'); // snapshot, deep_scan
            $table->string('status')->default('active'); // active, past_due, cancelled, trialing
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('paid'); // paid, pending, refunded, failed
            $table->string('description')->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_records');
        Schema::dropIfExists('subscriptions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn(['action_url', 'read_at']);
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['generated_at', 'download_count', 'signature', 'demo_mode']);
        });

        Schema::table('scans', function (Blueprint $table) {
            $table->dropColumn(['sources_total', 'demo_mode', 'error_message', 'cancelled_at']);
        });
    }
};
