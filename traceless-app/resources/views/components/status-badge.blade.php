@props(['status' => 'needs_user_action'])

@php
    $status = strtolower($status);
    $map = [
        'removed' => ['badge-emerald', 'check_circle', 'Removed'],
        'in_progress' => ['badge-low', 'autorenew', 'In progress'],
        'ready_to_remove' => ['badge-low', 'bolt', 'Ready to remove'],
        'waiting_for_source' => ['badge-moderate', 'hourglass', 'Waiting on source'],
        'user_action_required' => ['badge-moderate', 'touch_app', 'User action'],
        'could_not_verify' => ['badge-neutral', 'help', 'Could not verify'],
        'reappeared' => ['badge-high', 'restart_alt', 'Reappeared'],
        'not_started' => ['badge-neutral', 'radio_button_unchecked', 'Not started'],
        'needs_user_action' => ['badge-moderate', 'touch_app', 'Needs action'],
        'completed' => ['badge-emerald', 'check_circle', 'Completed'],
        'failed' => ['badge-high', 'error', 'Failed'],
        'cancelled' => ['badge-neutral', 'block', 'Cancelled'],
        'queued' => ['badge-neutral', 'schedule', 'Queued'],
        'checking_sources' => ['badge-low', 'radar', 'Checking sources'],
        'normalizing_results' => ['badge-low', 'filter_alt', 'Normalizing'],
        'calculating_risk' => ['badge-low', 'analytics', 'Calculating risk'],
        'generating_report' => ['badge-low', 'description', 'Generating report'],
        'initializing' => ['badge-low', 'settings_suggest', 'Initializing'],
        'active' => ['badge-emerald', 'sensors', 'Active'],
        'paused' => ['badge-neutral', 'pause', 'Paused'],
    ];
    [$class, $icon, $label] = $map[$status] ?? ['badge-neutral', 'circle', str($status)->replace('_', ' ')->title()];
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$class]) }}>
    <span class="material-symbols-outlined text-[12px]" aria-hidden="true">{{ $icon }}</span>
    {{ $label }}
</span>
