<?php
/**
 * app/Support/Marketing/ReferralSourceTracker.php
 * File: app/Support/Marketing/ReferralSourceTracker.php
 *
 * Referral Source tracking:
 * ------------------------------------------------------------------------------
 * 1) If URL has ?referral_source=VALUE -> store VALUE in a long-lived cookie.
 * 2) If cookie is missing -> infer from HTTP referrer (google/bing/facebook/etc) or "Direct".
 * 3) On PMPro membership signup:
 *    - stash the resolved source early (survives offsite gateways)
 *    - write the final value into the user's "Referral Source" meta field:
 *        sccc_referral_source
 *
 * Cookie TTL:
 * ------------------------------------------------------------------------------
 * Browsers don't truly support "infinite" cookies, so we set 10 years.
 *
 * PMPro hooks used:
 * ------------------------------------------------------------------------------
 * - pmpro_checkout_preheader (runs on checkout before headers are sent)
 * - pmpro_after_checkout (fires after successful checkout, passes user_id + order)
 */

namespace App\Support\Marketing;

final class ReferralSourceTracker
{
    // URL param name: https://yoursite.com/?referral_source=Facebook
    private const QUERY_PARAM = 'referral_source';

    // Cookie where we remember the first source we saw.
    private const COOKIE_NAME = 'sccc_referral_source';

    // Final destination (your admin display/filter field reads this meta).
    private const META_FINAL  = 'sccc_referral_source';

    // Temporary stash (important for offsite gateways / webhook completions).
    private const META_PENDING = 'sccc_referral_source_pending';

    // "Indefinite" TTL (10 years).
    private const COOKIE_TTL_SECONDS = 315360000;

    // Keep values small and safe for cookies/meta.
    private const MAX_LEN = 200;

    public static function boot(): void
    {
        // Capture param/referrer and set the cookie early on frontend requests.
        add_action('init', [__CLASS__, 'captureCookieFromRequest'], 1);

        // PMPro checkout: stash a pending value early so cookies/referrer aren't required later.
        add_action('pmpro_checkout_preheader', [__CLASS__, 'stashPendingOnCheckout'], 5);

        // If a user is created during checkout, also stash pending right after registration.
        add_action('user_register', [__CLASS__, 'stashPendingOnUserRegister'], 10, 1);

        // Checkout completed: commit into final user meta.
        add_action('pmpro_after_checkout', [__CLASS__, 'commitOnAfterCheckout'], 10, 2);
    }

    /**
     * STEP 1: Cookie capture (URL param wins; otherwise infer once).
     */
    public static function captureCookieFromRequest(): void
    {
        // Don’t set cookies in admin/ajax/cron.
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        // If URL has referral_source=..., that always wins and overwrites cookie.
        $param = isset($_GET[self::QUERY_PARAM]) ? (string) wp_unslash($_GET[self::QUERY_PARAM]) : '';
        $param = self::clean($param);

        if ($param !== '') {
            self::setCookie($param);
            return;
        }

        // If cookie already exists, we’re done.
        if (self::getCookie() !== '') {
            return;
        }

        // Otherwise infer from referrer (or Direct) and set cookie once.
        $inferred = self::inferSourceFromReferrer();
        if ($inferred === '') {
            $inferred = 'Direct';
        }

        self::setCookie($inferred);
    }

    /**
     * STEP 2: PMPro checkout start -> stash pending meta (survives offsite/webhook).
     */
    public static function stashPendingOnCheckout(): void
    {
        $userId = (int) get_current_user_id();
        if ($userId <= 0) {
            return; // not logged in yet
        }

        // If final already exists, don't touch.
        $final = (string) get_user_meta($userId, self::META_FINAL, true);
        if (trim($final) !== '') {
            return;
        }

        // If pending already exists, don't touch.
        $pending = (string) get_user_meta($userId, self::META_PENDING, true);
        if (trim($pending) !== '') {
            return;
        }

        // Ensure cookie exists; if not, infer now (checkout is a great “decision point”).
        self::ensureCookieExists();

        $source = self::getCookie();
        if ($source === '') {
            $source = 'Direct';
        }

        update_user_meta($userId, self::META_PENDING, $source);
    }

    /**
     * STEP 2b: If user is created during signup, stash pending at registration time too.
     */
    public static function stashPendingOnUserRegister(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $final = (string) get_user_meta($userId, self::META_FINAL, true);
        if (trim($final) !== '') {
            return;
        }

        $pending = (string) get_user_meta($userId, self::META_PENDING, true);
        if (trim($pending) !== '') {
            return;
        }

        self::ensureCookieExists();

        $source = self::getCookie();
        if ($source === '') {
            $source = 'Direct';
        }

        update_user_meta($userId, self::META_PENDING, $source);
    }

    /**
     * STEP 3: PMPro checkout completed -> write the final Referral Source user meta.
     */
    public static function commitOnAfterCheckout(int $userId, $morder): void
    {
        if ($userId <= 0) {
            return;
        }

        // Don’t overwrite once set (first-touch wins).
        $final = (string) get_user_meta($userId, self::META_FINAL, true);
        if (trim($final) !== '') {
            delete_user_meta($userId, self::META_PENDING);
            return;
        }

        // Prefer pending (best for offsite/webhook), then cookie, then referrer, then Direct.
        $source = (string) get_user_meta($userId, self::META_PENDING, true);
        $source = self::clean($source);

        if ($source === '') {
            $source = self::getCookie();
        }

        if ($source === '') {
            $source = self::inferSourceFromReferrer();
        }

        if ($source === '') {
            $source = 'Direct';
        }

        update_user_meta($userId, self::META_FINAL, $source);
        delete_user_meta($userId, self::META_PENDING);

        // Optional: keep cookie consistent for that browser session.
        if (self::getCookie() === '') {
            self::setCookie($source);
        }
    }

    /**
     * If cookie is missing, set it from referrer or Direct.
     */
    private static function ensureCookieExists(): void
    {
        if (self::getCookie() !== '') {
            return;
        }

        $inferred = self::inferSourceFromReferrer();
        if ($inferred === '') {
            $inferred = 'Direct';
        }

        self::setCookie($inferred);
    }

    /**
     * Read cookie value.
     */
    private static function getCookie(): string
    {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return '';
        }

        return self::clean((string) $_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * Write cookie value (10 years).
     */
    private static function setCookie(string $value): void
    {
        $value = self::clean($value);
        if ($value === '') {
            return;
        }

        // If headers already sent, we can't set the cookie reliably.
        if (headers_sent()) {
            $_COOKIE[self::COOKIE_NAME] = $value;
            return;
        }

        $expires = time() + self::COOKIE_TTL_SECONDS;
        $secure  = self::isHttps();

        // PHP 7.3+ supports options array (lets us set SameSite cleanly).
        if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
            setcookie(self::COOKIE_NAME, $value, [
                'expires'  => $expires,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            // Fallback for older PHP: no SameSite support here.
            setcookie(self::COOKIE_NAME, $value, $expires, '/', '', $secure, true);
        }

        // Make it available immediately in the current request.
        $_COOKIE[self::COOKIE_NAME] = $value;
    }

    /**
     * Infer source from HTTP_REFERER.
     *
     * - Returns: google, bing, facebook, instagram, etc
     * - If unknown external domain, returns host without "www"
     * - Returns '' if no referrer or internal referrer
     */
    private static function inferSourceFromReferrer(): string
    {
        $ref = isset($_SERVER['HTTP_REFERER']) ? trim((string) $_SERVER['HTTP_REFERER']) : '';
        if ($ref === '') {
            return '';
        }

        $host = wp_parse_url($ref, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return '';
        }

        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        // Ignore internal referrers (your own site).
        $siteHost = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $siteHost = strtolower(preg_replace('/^www\./', '', $siteHost) ?? $siteHost);

        if ($siteHost !== '' && ($host === $siteHost || self::endsWith($host, '.' . $siteHost))) {
            return '';
        }

        // Common search engines
        if (strpos($host, 'google.') !== false)     return 'google';
        if (strpos($host, 'bing.') !== false)       return 'bing';
        if (strpos($host, 'yahoo.') !== false)      return 'yahoo';
        if (strpos($host, 'duckduckgo.') !== false) return 'duckduckgo';

        // Common social
        if ($host === 'fb.com' || strpos($host, 'facebook.') !== false || strpos($host, 'l.facebook.com') !== false) return 'facebook';
        if (strpos($host, 'instagram.') !== false)  return 'instagram';
        if (strpos($host, 'tiktok.') !== false)     return 'tiktok';
        if ($host === 't.co' || $host === 'x.com' || strpos($host, 'twitter.') !== false) return 'x';
        if (strpos($host, 'youtube.') !== false)    return 'youtube';
        if (strpos($host, 'linkedin.') !== false)   return 'linkedin';

        // Fallback: store the host (example.com)
        return self::clean($host);
    }

    /**
     * Clean and cap values for cookie + meta.
     * Plain text only, trimmed, max length enforced.
     */
    private static function clean(string $value): string
    {
        $value = sanitize_text_field($value);
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            $value = (string) mb_substr($value, 0, self::MAX_LEN);
        } else {
            $value = (string) substr($value, 0, self::MAX_LEN);
        }

        return trim($value);
    }

    /**
     * Are we effectively on HTTPS?
     */
    private static function isHttps(): bool
    {
        if (function_exists('wp_is_using_https')) {
            return (bool) wp_is_using_https();
        }

        $homeScheme = wp_parse_url(home_url(), PHP_URL_SCHEME);
        $siteScheme = wp_parse_url(site_url(), PHP_URL_SCHEME);

        return is_ssl() || $homeScheme === 'https' || $siteScheme === 'https';
    }

    /**
     * Tiny helper for PHP < 8 (no str_ends_with).
     */
    private static function endsWith(string $haystack, string $needle): bool
    {
        $len = strlen($needle);
        if ($len === 0) return true;
        return substr($haystack, -$len) === $needle;
    }
}

ReferralSourceTracker::boot();