<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Identity Profiles
        Schema::create('identity_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->text('previous_names')->nullable();
            $table->text('aliases')->nullable();
            $table->string('primary_email')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('region')->nullable();
            $table->boolean('consent_acknowledged')->default(false);
            $table->timestamp('consent_given_at')->nullable();
            $table->integer('privacy_score')->default(100);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Public Identifiers
        Schema::create('public_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('identity_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // email, username, phone, url, domain
            $table->string('value');
            $table->boolean('is_monitored')->default(true);
            $table->timestamps();
        });

        // 3. Scans
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('identity_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('snapshot'); // snapshot, deep_scan
            $table->string('status')->default('queued'); // queued, checking_sources, normalizing_results, calculating_risk, completed, failed, cancelled
            $table->integer('progress_percentage')->default(0);
            $table->string('current_stage')->default('Initializing scan engine...');
            $table->integer('sources_checked')->default(0);
            $table->integer('findings_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 4. Scan Sources
        Schema::create('scan_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category');
            $table->string('status')->default('pending'); // pending, checking, completed, failed
            $table->integer('findings_found')->default(0);
            $table->timestamps();
        });

        // 5. Findings
        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('identity_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->string('category'); // social_profile, developer_profile, data_broker, public_paste, public_avatar
            $table->string('identifier_type');
            $table->string('identifier_value');
            $table->text('url')->nullable();
            $table->float('confidence')->default(0.8);
            $table->string('risk')->default('moderate'); // low, moderate, high, critical
            $table->string('removal_method')->default('manual_instructions'); // automated_request, manual_instructions
            $table->string('status')->default('needs_user_action'); // not_started, ready_to_remove, in_progress, waiting_for_source, user_action_required, removed, could_not_verify
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('action_guidance')->nullable();
            $table->timestamp('discovered_at')->useCurrent();
            $table->timestamp('last_checked_at')->useCurrent();
            $table->timestamps();
        });

        // 6. Credential Exposures (Never passwords, only verified public breach metadata)
        Schema::create('credential_exposures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('identity_profile_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('service_name');
            $table->string('breach_date')->nullable();
            $table->text('exposed_data_classes')->nullable(); // e.g. Usernames, Emails, IP addresses
            $table->string('risk_level')->default('high');
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // 7. Removal Tasks
        Schema::create('removal_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('identity_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->string('source_name');
            $table->string('status')->default('ready_to_remove'); // ready_to_remove, in_progress, waiting_for_source, user_action_required, removed
            $table->string('workflow_type')->default('manual'); // automated, manual
            $table->string('estimated_turnaround')->default('24-48 hours');
            $table->text('opt_out_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 8. Removal Instructions
        Schema::create('removal_instructions', function (Blueprint $table) {
            $table->id();
            $table->string('source_identifier')->unique(); // e.g. fastpeoplesearch, twitter, pastebin
            $table->string('title');
            $table->text('step_1');
            $table->text('step_2')->nullable();
            $table->text('step_3')->nullable();
            $table->text('step_4')->nullable();
            $table->string('direct_opt_out_link')->nullable();
            $table->string('typical_turnaround')->default('1-3 days');
            $table->timestamps();
        });

        // 9. Monitoring Subscriptions
        Schema::create('monitoring_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('identity_profile_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('cadence')->default('weekly'); // daily, weekly, monthly
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamp('next_scan_at')->nullable();
            $table->timestamps();
        });

        // 10. Alerts
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('identity_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // new_exposure, reappeared, credential_leak
            $table->string('severity')->default('moderate'); // low, moderate, high, critical
            $table->string('title');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        // 11. Consent Records (Audit proof)
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('identity_profile_id')->constrained()->cascadeOnDelete();
            $table->text('consent_text');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('agreed_at')->useCurrent();
            $table->timestamps();
        });

        // 12. Audit Logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('details')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });

        // 13. Reports
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('identity_profile_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('report_type')->default('snapshot');
            $table->integer('privacy_score');
            $table->integer('total_findings');
            $table->text('summary')->nullable();
            $table->string('download_token')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('consent_records');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('monitoring_subscriptions');
        Schema::dropIfExists('removal_instructions');
        Schema::dropIfExists('removal_tasks');
        Schema::dropIfExists('credential_exposures');
        Schema::dropIfExists('findings');
        Schema::dropIfExists('scan_sources');
        Schema::dropIfExists('scans');
        Schema::dropIfExists('public_identifiers');
        Schema::dropIfExists('identity_profiles');
    }
};
