<?php
/**
 * app/Support/Memberships/FoundingCap.php
 * File: app/Support/Memberships/FoundingCap.php
 *
 * What this file does
 * -----------------------------------------------------------------------------
 * Caps “Founding” signup at a fixed number of seats by counting **active** PMPro
 * memberships for the founding level (no custom option rows). Remaining seats =
 * CAP − that count. Checkout is blocked and the level can be hidden once full.
 *
 * Behavior note (different from an order ledger)
 * -----------------------------------------------------------------------------
 * Seats are tied to **currently active memberships**. If someone cancels or
 * expires, a seat becomes available again. For “lifetime / never refill” caps,
 * you would count DISTINCT successful orders instead—see countActiveSoldSeats().
 *
 * Badge copy must ONLY be: "Only XX spots available"
 *
 * Theme-contained loading
 * -----------------------------------------------------------------------------
 * Self-booting: `require_once get_theme_file_path('app/Support/Memberships/FoundingCap.php');`
 *
 * Separation of concerns
 * -----------------------------------------------------------------------------
 * - Business logic + PMPro enforcement live here.
 * - Blocks / Blade call FoundingCap::badgeText() for UI.
 */

namespace App\Support\Memberships;

use wpdb;

class FoundingCap
{
    public const FOUNDING_LEVEL_ID = 2;
    public const CAP = 25;

    /**
     * Ensures we only attach hooks once per request.
     */
    protected static bool $booted = false;

    /**
     * Boot PMPro enforcement hooks.
     * Runs on WP `init` so PMPro is loaded.
     */
    public static function init(): void
    {
        if (static::$booted) {
            return;
        }

        static::$booted = true;

        add_action('pmpro_checkout_preheader_after_get_level_at_checkout', [static::class, 'maybeRedirectSoldOutCheckout'], 5, 1);
        add_filter('pmpro_registration_checks', [static::class, 'blockCheckoutIfSoldOut'], 10, 1);
        add_filter('pmpro_levels_array', [static::class, 'hideSoldOutFromLevelsPage'], 20, 1);
    }

    /**
     * Whether the founding tier still has open seats (same gate as checkout CTA).
     */
    public static function allowsCheckout(): bool
    {
        return static::remaining() > 0;
    }

    /**
     * Badge string for UI.
     * MUST be ONLY: "Only XX spots available"
     * Returns empty string when 0 so UI can hide it.
     */
    public static function badgeText(): string
    {
        $remaining = static::remaining();

        if ($remaining <= 0) {
            return '';
        }

        return sprintf('Only %d spots available', $remaining);
    }

    /**
     * Seats currently counted against the cap (active founding members).
     */
    public static function sold(): int
    {
        return static::countActiveSoldSeats();
    }

    public static function remaining(): int
    {
        return max(0, static::CAP - static::sold());
    }

    /**
     * Deep-linked checkout → levels when founding is full (still allow in-flight token orders).
     */
    public static function maybeRedirectSoldOutCheckout($pmpro_level): void
    {
        global $pmpro_review;

        if (! empty($pmpro_review)) {
            return;
        }

        if (empty($pmpro_level) || ! is_object($pmpro_level)) {
            return;
        }

        $levelId = (int) ($pmpro_level->id ?? 0);
        if ($levelId !== static::FOUNDING_LEVEL_ID) {
            return;
        }

        if (static::remaining() <= 0) {
            wp_redirect(function_exists('pmpro_url') ? pmpro_url('levels') : home_url('/'));
            exit;
        }
    }

    /**
     * PMPro: block checkout submission when cap reached.
     */
    public static function blockCheckoutIfSoldOut(bool $continue): bool
    {
        if (! function_exists('pmpro_setMessage')) {
            return $continue;
        }

        $levelId = static::getCheckoutLevelId();

        if ($levelId !== static::FOUNDING_LEVEL_ID) {
            return $continue;
        }

        if (static::sold() >= static::CAP) {
            pmpro_setMessage('Founding Member Status is sold out.', 'pmpro_error');

            return false;
        }

        return $continue;
    }

    /**
     * Remove founding level row from PMPro membership levels archive when sold out.
     *
     * @param iterable<int|string, mixed> $levels
     *
     * @return array<int, mixed>
     */
    public static function hideSoldOutFromLevelsPage($levels): array
    {
        if (! is_iterable($levels)) {
            return [];
        }

        $list = is_array($levels) ? $levels : iterator_to_array($levels, false);

        if (static::remaining() > 0) {
            return $list;
        }

        $id = static::FOUNDING_LEVEL_ID;

        return array_values(array_filter($list, static function ($level) use ($id): bool {
            return ! is_object($level) || (int) ($level->id ?? 0) !== $id;
        }));
    }

    protected static function getCheckoutLevelId(): int
    {
        global $pmpro_level;

        if (! empty($pmpro_level) && is_object($pmpro_level) && ! empty($pmpro_level->id)) {
            return (int) $pmpro_level->id;
        }

        if (isset($_REQUEST['level'])) {
            return (int) sanitize_text_field(wp_unslash($_REQUEST['level']));
        }

        return 0;
    }

    protected static function countActiveSoldSeats(): int
    {
        global $wpdb;

        if (! $wpdb instanceof wpdb) {
            return 0;
        }

        $table = $wpdb->prefix . 'pmpro_memberships_users';

        $sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM `{$table}` WHERE membership_id = %d AND status = %s",
            static::FOUNDING_LEVEL_ID,
            'active'
        );

        $count = $wpdb->get_var($sql);

        return max(0, min(static::CAP, (int) $count));
    }
}

/**
 * Self-boot
 * -----------------------------------------------------------------------------
 * When this file is required, hook into WP `init` and initialize our logic.
 * This keeps plugin dependencies safe (PMPro loads before init callbacks run).
 */
add_action('init', [\App\Support\Memberships\FoundingCap::class, 'init']);
