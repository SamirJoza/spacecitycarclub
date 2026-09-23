<?php

/**
 * File: app/Support/Membership/AbandonedSubscriberCleanup.php
 *
 * Purpose:
 * Automatically clean up abandoned PMPro signup accounts for Space City Car Club.
 *
 * Project rule:
 * - WooCommerce checkout-created users become `customer`.
 * - Legit PMPro members receive `subscriber` + `sccc_member`.
 * - Therefore, users whose ONLY role is exactly `subscriber` are abandoned/failed
 *   PMPro signup accounts and may be removed.
 */

namespace App\Support\Membership;

use DateTimeImmutable;
use DateTimeZone;
use WP_User;

class AbandonedSubscriberCleanup
{
    private const ACTION_HOOK = 'sccc_cleanup_abandoned_subscriber_users';

    private const ACTION_GROUP = 'sccc_membership';

    private const RUN_INTERVAL_SECONDS = 15 * MINUTE_IN_SECONDS;

    /**
     * Minimum account age before deletion.
     *
     * Set to 0 for immediate cleanup.
     * Current value: 15 minutes.
     */
    private const MIN_ACCOUNT_AGE_SECONDS = 15 * MINUTE_IN_SECONDS;

    /**
     * Safety switch.
     *
     * true  = log only, do not delete
     * false = delete matched users
     */
    private const DRY_RUN = false;

    public static function boot(): void
    {
        add_action('init', [self::class, 'scheduleCleanup']);
        add_action(self::ACTION_HOOK, [self::class, 'cleanup']);

        add_filter('cron_schedules', [self::class, 'registerCronSchedule']);
    }

    public static function scheduleCleanup(): void
    {
        if (function_exists('as_next_scheduled_action') && function_exists('as_schedule_recurring_action')) {
            if (! as_next_scheduled_action(self::ACTION_HOOK, [], self::ACTION_GROUP)) {
                as_schedule_recurring_action(
                    time() + self::RUN_INTERVAL_SECONDS,
                    self::RUN_INTERVAL_SECONDS,
                    self::ACTION_HOOK,
                    [],
                    self::ACTION_GROUP
                );
            }

            return;
        }

        if (! wp_next_scheduled(self::ACTION_HOOK)) {
            wp_schedule_event(
                time() + self::RUN_INTERVAL_SECONDS,
                'sccc_every_15_minutes',
                self::ACTION_HOOK
            );
        }
    }

    public static function registerCronSchedule(array $schedules): array
    {
        $schedules['sccc_every_15_minutes'] = [
            'interval' => self::RUN_INTERVAL_SECONDS,
            'display'  => __('Every 15 minutes - SCCC cleanup', 'sccc'),
        ];

        return $schedules;
    }

    /**
     * Delete every abandoned subscriber-only user found.
     */
    public static function cleanup(): void
    {
        $users = get_users([
            'role'        => 'subscriber',
            'orderby'     => 'registered',
            'order'       => 'ASC',
            'fields'      => 'all',
            'count_total' => false,
            'number'      => -1,
        ]);

        if (empty($users)) {
            self::log('No subscriber users found.');
            return;
        }

        foreach ($users as $user) {
            if (! $user instanceof WP_User) {
                continue;
            }

            if (! self::isDeletableSubscriberOnlyUser($user)) {
                continue;
            }

            self::deleteUser($user);
        }
    }

    private static function isDeletableSubscriberOnlyUser(WP_User $user): bool
    {
        $roles = array_values((array) $user->roles);

        if (count($roles) !== 1 || ! in_array('subscriber', $roles, true)) {
            return false;
        }

        if ((int) $user->ID === get_current_user_id()) {
            return false;
        }

        if (! self::isOldEnough($user)) {
            return false;
        }

        if (count_user_posts((int) $user->ID) > 0) {
            self::log("Skipped user {$user->ID}; user has authored posts.");
            return false;
        }

        return true;
    }

    private static function isOldEnough(WP_User $user): bool
    {
        if (empty($user->user_registered)) {
            return false;
        }

        $registered = new DateTimeImmutable($user->user_registered, new DateTimeZone('UTC'));
        $age        = time() - $registered->getTimestamp();

        return $age >= self::MIN_ACCOUNT_AGE_SECONDS;
    }

    private static function deleteUser(WP_User $user): void
    {
        self::log("Matched abandoned subscriber-only user {$user->ID} ({$user->user_email}).");

        if (self::DRY_RUN) {
            self::log("Dry run enabled; user {$user->ID} was not deleted.");
            return;
        }

        if (! function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $deleted = wp_delete_user((int) $user->ID);

        if ($deleted) {
            self::log("Deleted abandoned subscriber-only user {$user->ID} ({$user->user_email}).");
        } else {
            self::log("Failed to delete abandoned subscriber-only user {$user->ID} ({$user->user_email}).");
        }
    }

    private static function log(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[SCCC Abandoned Subscriber Cleanup] ' . $message);
        }
    }
}

AbandonedSubscriberCleanup::boot();