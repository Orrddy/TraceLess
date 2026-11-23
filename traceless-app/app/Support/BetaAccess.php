<?php

namespace App\Support;

use App\Models\User;

/**
 * Single authoritative plan gate for the free beta (v1.0).
 *
 * While `traceless.beta` is on, every active account gets deep scans, deep
 * reports and recurring monitoring without a subscription, and no billing path
 * can run. Turning the flag off restores the subscription plan checks for v2.0.
 *
 * The UI must read the flag from config only; it is never accepted from a
 * request or a Livewire property.
 */
class BetaAccess
{
    public static function enabled(): bool
    {
        return filter_var(config('traceless.beta', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Deep scans, deep reports and recurring monitoring share this one gate.
     */
    public static function grantsDeepScan(?User $user = null): bool
    {
        if ($user === null || $user->isSuspended()) {
            return false;
        }

        if (self::enabled()) {
            return true;
        }

        return (bool) $user->activeSubscription()?->isDeepScan();
    }

    public static function grantsRecurringMonitoring(?User $user = null): bool
    {
        return self::grantsDeepScan($user);
    }

    /**
     * Billing is deferred to v2.0; the free beta must never charge or bill.
     */
    public static function billingEnabled(): bool
    {
        return ! self::enabled();
    }
}
