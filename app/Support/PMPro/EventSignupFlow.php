<?php
/**
 * File: app/Support/PMPro/EventSignupFlow.php
 *
 * Purpose:
 * -----------------------------------------------------------------------------
 * Adds an onsite/event signup flow for Space City Car Club.
 *
 * This flow is designed for shared tablets / personal iPads at shows:
 * - Visitors start at /event-signup/
 * - They choose from the allowed PMPro membership levels
 * - PMPro checkout/payment still handles the real signup/payment process
 * - After successful checkout, the new member is NOT left logged in
 * - The browser is redirected to /event-signup-complete/
 * - The completion screen returns to a fresh /event-signup/ page
 *
 * What this file intentionally does NOT do:
 * -----------------------------------------------------------------------------
 * - It does not replace PMPro checkout.
 * - It does not replace the existing ReferralSourceTracker.
 * - It does not assign the sccc_member role. MemberRoleSync already does that.
 * - It does not duplicate Founding Member cap logic. FoundingCap remains the
 *   source of truth.
 *
 * Existing systems reused:
 * -----------------------------------------------------------------------------
 * - App\Support\Memberships\FoundingCap
 *   Handles Founding Member availability, remaining seats, sold-out protection.
 *
 * - App\Support\Marketing\ReferralSourceTracker
 *   Handles final referral source storage in user meta:
 *   sccc_referral_source
 *
 * This file only sets the pending referral source during the event flow:
 *   sccc_referral_source_pending
 *
 * Page templates expected:
 * -----------------------------------------------------------------------------
 * - resources/views/template-event-signup.blade.php
 * - resources/views/template-event-signup-complete.blade.php
 *
 * Recommended page slugs:
 * -----------------------------------------------------------------------------
 * - /event-signup/
 * - /event-signup-complete/
 */

namespace App\Support\PMPro;

use App\Support\Memberships\FoundingCap;

defined('ABSPATH') || exit;

final class EventSignupFlow
{
    /**
     * Event signup URL markers.
     *
     * EVENT_QUERY_FLAG:
     * - Added to PMPro checkout links from the event signup page.
     * - Lets this class recognize the checkout as part of the event flow.
     *
     * EVENT_KEY_QUERY:
     * - Optional access key query parameter.
     * - If an Event Access Key is set in admin, the entry URL must include it.
     */
    private const EVENT_QUERY_FLAG = 'sccc_event_signup';
    private const EVENT_KEY_QUERY  = 'event_key';

    /**
     * Signed cookie that keeps the event signup context alive while the visitor
     * moves from the event signup page into PMPro checkout/payment.
     *
     * This cookie does NOT store personal information.
     */
    private const COOKIE_NAME = 'sccc_event_signup';

    /**
     * Event signup user meta.
     *
     * These are internal tracking fields that let the successful checkout remain
     * identifiable as an event signup even if checkout goes through an offsite
     * payment gateway flow.
     */
    private const META_CONTEXT      = 'sccc_signup_context';
    private const META_EVENT_NAME   = 'sccc_signup_event';
    private const META_EVENT_SOURCE = 'sccc_signup_source';
    private const META_COMPLETED_AT = 'sccc_signup_completed_at';

    /**
     * Existing referral source meta used by ReferralSourceTracker.
     *
     * Important:
     * - We only set the pending value.
     * - ReferralSourceTracker commits this to sccc_referral_source after checkout.
     */
    private const REFERRAL_PENDING_META = 'sccc_referral_source_pending';

    /**
     * ACF option field names registered by EventSignupSettings.
     */
    private const OPTION_ENABLED             = 'sccc_event_signup_enabled';
    private const OPTION_EVENT_NAME          = 'sccc_event_signup_event_name';
    private const OPTION_SOURCE              = 'sccc_event_signup_source';
    private const OPTION_ACCESS_KEY          = 'sccc_event_signup_access_key';
    private const OPTION_COMPLETION_MESSAGE  = 'sccc_event_signup_completion_message';
    private const OPTION_RETURN_BUTTON_LABEL = 'sccc_event_signup_return_button_label';
    private const OPTION_AUTO_RETURN_SECONDS = 'sccc_event_signup_auto_return_seconds';

    /**
     * Event signup context value.
     */
    private const CONTEXT_VALUE = 'event_terminal';

    /**
     * Signed cookie lifespan.
     *
     * Long enough for an onsite signup to complete without forcing volunteers to
     * restart, but short enough that it is not a long-lived visitor identifier.
     */
    private const COOKIE_TTL_SECONDS = 12 * HOUR_IN_SECONDS;

    /**
     * Register WordPress + PMPro hooks.
     */
    public static function boot(): void
    {
        /**
         * Start event mode early when the visitor lands on the event signup page
         * or clicks an event checkout link.
         */
        add_action('init', [self::class, 'maybeStartEventSignupMode'], 2);

        /**
         * Keep terminal pages and event checkout requests out of browser/proxy cache.
         */
        add_action('template_redirect', [self::class, 'prepareFrontendRequest'], 0);

        /**
         * If PMPro creates the user during checkout, stamp the event context and
         * pending referral source before the normal referral tracker runs.
         */
        add_action('user_register', [self::class, 'stashEventSourceOnUserRegister'], 8, 1);

        /**
         * PMPro checkout page entry.
         *
         * Priority 1 is intentionally earlier than ReferralSourceTracker's
         * pmpro_checkout_preheader priority, so the event source wins.
         */
        add_action('pmpro_checkout_preheader', [self::class, 'stashEventSourceOnCheckout'], 1);

        /**
         * PMPro successful checkout.
         *
         * Priority 5 is intentionally earlier than ReferralSourceTracker's
         * pmpro_after_checkout priority 10, so it can commit the event source
         * from our pending meta.
         */
        add_action('pmpro_after_checkout', [self::class, 'markEventSignupAfterCheckout'], 5, 2);

        /**
         * Redirect successful event signups to the terminal confirmation page,
         * not the normal PMPro confirmation flow.
         */
        add_filter('pmpro_confirmation_url', [self::class, 'filterPmproConfirmationUrl'], 20, 3);

        /**
         * Compatibility with the inactive/optional PmproCheckoutNoAutoLogin helper.
         *
         * If that helper is re-enabled later, these filters keep event signup
         * routing aligned with the terminal completion page.
         */
        add_filter('sccc_pmpro_checkout_confirmation_url', [self::class, 'filterCustomConfirmationUrl'], 20, 3);
        add_filter('sccc_pmpro_checkout_restart_url', [self::class, 'filterCustomRestartUrl'], 20, 3);
    }

    /* ==========================================================================
     * PUBLIC TEMPLATE HELPERS
     * ========================================================================== */

    /**
     * Whether the event signup flow is enabled.
     *
     * Default:
     * - Enabled unless the setting is explicitly saved as false/0.
     * - This makes the feature usable immediately after deployment.
     */
    public static function isEnabled(): bool
    {
        $value = self::option(self::OPTION_ENABLED, null);

        if ($value === null || $value === '') {
            return true;
        }

        return (bool) $value;
    }

    /**
     * Event name displayed on the event signup and completion screens.
     */
    public static function eventName(): string
    {
        $value = self::clean((string) self::option(
            self::OPTION_EVENT_NAME,
            'Space City Car Club Event Signup'
        ));

        return $value !== '' ? $value : 'Space City Car Club Event Signup';
    }

    /**
     * Referral/source value used for event signups.
     *
     * This becomes the pending referral source and is committed by the existing
     * ReferralSourceTracker after successful PMPro checkout.
     */
    public static function sourceValue(): string
    {
        $value = self::clean((string) self::option(
            self::OPTION_SOURCE,
            'event_terminal_signup'
        ));

        return $value !== '' ? $value : 'event_terminal_signup';
    }

    /**
     * Optional event access key.
     *
     * If blank, /event-signup/ is usable without a key.
     * If set, the entry URL should include:
     * /event-signup/?event_key=YOUR_KEY
     */
    public static function accessKey(): string
    {
        return self::clean((string) self::option(self::OPTION_ACCESS_KEY, ''));
    }

    /**
     * Completion message shown after successful event signup.
     */
    public static function completionMessage(): string
    {
        $default = 'Thank you for joining Space City Car Club. Your signup was completed successfully. Please check your email for login instructions and next steps. For privacy, this terminal has been reset and you are not logged in on this device.';

        $value = trim((string) self::option(self::OPTION_COMPLETION_MESSAGE, $default));

        return $value !== '' ? $value : $default;
    }

    /**
     * Button label for returning to the event signup page.
     */
    public static function returnButtonLabel(): string
    {
        $value = self::clean((string) self::option(
            self::OPTION_RETURN_BUTTON_LABEL,
            'Start Next Signup'
        ));

        return $value !== '' ? $value : 'Start Next Signup';
    }

    /**
     * Seconds before the completion page automatically returns to the entry page.
     */
    public static function autoReturnSeconds(): int
    {
        $value = absint(self::option(self::OPTION_AUTO_RETURN_SECONDS, 12));

        if ($value < 0) {
            return 0;
        }

        if ($value > 60) {
            return 60;
        }

        return $value;
    }

    /**
     * Entry URL for the event signup page.
     *
     * If an event key is configured, include it so the completion page can return
     * to a fresh valid event signup screen.
     */
    public static function entryUrl(): string
    {
        $url = home_url(self::entryPath());

        $key = self::accessKey();
        if ($key !== '') {
            $url = add_query_arg(self::EVENT_KEY_QUERY, rawurlencode($key), $url);
        }

        return (string) $url;
    }

    /**
     * Completion URL for successful event signups.
     */
    public static function completionUrl(): string
    {
        return home_url(self::completePath());
    }

    /**
     * Whether the current entry request is allowed to start the event flow.
     *
     * Templates use this to avoid displaying level choices if the optional event
     * access key is configured but missing/incorrect.
     */
    public static function canStartFromCurrentRequest(): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        return self::currentRequestHasValidAccessKey();
    }

    /**
     * Build the membership level card data for /event-signup/.
     *
     * This intentionally mirrors the shape used by MembershipsTiersGrid, but it
     * stays local to this flow to avoid refactoring existing working code.
     *
     * Levels:
     * - Defaults to [2, 3, 4]
     * - Can be adjusted with the sccc_event_signup_level_ids filter.
     */
    public static function eventSignupPlans(): array
    {
        $levelIds = (array) apply_filters('sccc_event_signup_level_ids', [2, 3, 4]);

        $levelIds = array_values(array_unique(array_filter(array_map(static function ($id): int {
            return absint($id);
        }, $levelIds))));

        if (empty($levelIds) || ! function_exists('pmpro_getLevel')) {
            return [];
        }

        $mostPopularId = self::autoPickMostPopular($levelIds);
        $plans = [];

        foreach ($levelIds as $levelId) {
            $level = pmpro_getLevel($levelId);

            if (empty($level) || ! is_object($level)) {
                continue;
            }

            $isFounding = class_exists(FoundingCap::class)
                && $levelId === (int) FoundingCap::FOUNDING_LEVEL_ID;

            $isSoldOut = $isFounding
                && class_exists(FoundingCap::class)
                && ! FoundingCap::allowsCheckout();

            $isFeatured = $levelId === $mostPopularId;

            $name = ! empty($level->name) ? (string) $level->name : 'Membership';
            $desc = ! empty($level->description) ? (string) $level->description : '';

            $pricing = self::priceAndSuffixFromLevel($level);

            $badge = null;
            $status = 'available';

            if ($isFounding && ! $isSoldOut && class_exists(FoundingCap::class)) {
                $badge = FoundingCap::badgeText();
                $status = 'limited';
            }

            if ($isSoldOut) {
                $badge = 'Sold Out';
                $status = 'sold_out';
            } elseif ($isFeatured) {
                $badge = $badge ?: 'Most Popular';
            }

            $plans[] = [
                'id'          => $levelId,
                'name'        => $name,
                'tagline'     => self::cleanTagline($desc),
                'price'       => $pricing['price'],
                'suffix'      => $pricing['suffix'],
                'cta_label'   => $isSoldOut
                    ? 'Sold Out'
                    : ($isFounding ? 'Secure Your Spot' : 'Choose This Level'),
                'cta_url'     => $isSoldOut ? '' : self::checkoutUrlForLevel($levelId),
                'is_featured' => $isFeatured,
                'is_founding' => $isFounding,
                'badge'       => $badge,
                'status'      => $status,
            ];
        }

        return $plans;
    }

    /* ==========================================================================
     * REQUEST LIFECYCLE
     * ========================================================================== */

    /**
     * Start event signup mode when the visitor is on the event signup entry page
     * or when they click from that page into PMPro checkout.
     */
    public static function maybeStartEventSignupMode(): void
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        if (! self::requestShouldStartEventMode()) {
            return;
        }

        if (! self::canStartFromCurrentRequest()) {
            return;
        }

        self::setEventCookie(self::payloadFromSettings());
    }

    /**
     * Prepare frontend terminal pages.
     *
     * This is intentionally separate from init because page conditionals and
     * template rendering are more reliable at template_redirect.
     */
    public static function prepareFrontendRequest(): void
    {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        $isEntry      = self::isEntryRequest();
        $isComplete   = self::isCompleteRequest();
        $isEventPmpro = self::isPmproCheckoutWithEventMode();

        if ($isEntry || $isComplete || $isEventPmpro || self::hasValidEventCookie()) {
            self::sendNoStoreHeaders();
        }

        /**
         * Starting the event page on a shared/personal iPad must detach any
         * previously logged-in user before the visitor starts entering data.
         */
        if ($isEntry) {
            if (self::canStartFromCurrentRequest()) {
                self::setEventCookie(self::payloadFromSettings());
            } else {
                self::clearEventCookie();
            }

            self::logoutBrowserSession(true);
        }

        /**
         * The completion page must never leave the new member logged in.
         * It also clears the event cookie so the "Start Next Signup" button
         * begins a clean flow.
         */
        if ($isComplete) {
            self::logoutBrowserSession(true);
            self::clearEventCookie();
        }
    }

    /**
     * When PMPro creates a user during checkout, stamp the event context early.
     */
    public static function stashEventSourceOnUserRegister(int $userId): void
    {
        if ($userId <= 0 || ! self::isActiveEventSignupRequest()) {
            return;
        }

        self::applyEventMetaToUser($userId);
    }

    /**
     * When PMPro checkout loads with a logged-in temporary/new user, stamp the
     * event context before the normal referral tracker runs.
     */
    public static function stashEventSourceOnCheckout(): void
    {
        if (! self::isActiveEventSignupRequest()) {
            return;
        }

        $userId = (int) get_current_user_id();

        if ($userId <= 0) {
            return;
        }

        self::applyEventMetaToUser($userId);
    }

    /**
     * Successful PMPro checkout.
     *
     * This marks the user as an event signup before ReferralSourceTracker commits
     * the final referral source.
     */
    public static function markEventSignupAfterCheckout(int $userId, mixed $morder = null): void
    {
        if ($userId <= 0) {
            return;
        }

        if (! self::isActiveEventSignupRequest() && ! self::isEventSignupUser($userId)) {
            return;
        }

        self::applyEventMetaToUser($userId);
        update_user_meta($userId, self::META_COMPLETED_AT, current_time('mysql'));
    }

    /**
     * PMPro confirmation redirect.
     *
     * For event signups only:
     * - clear the browser auth cookie
     * - send the visitor to the terminal completion page
     */
    public static function filterPmproConfirmationUrl(string $url, int $userId, mixed $pmproLevel): string
    {
        if ($userId <= 0) {
            return $url;
        }

        if (! self::isActiveEventSignupRequest() && ! self::isEventSignupUser($userId)) {
            return $url;
        }

        self::applyEventMetaToUser($userId);
        self::logoutBrowserSession(false);
        self::clearEventCookie();

        return self::completionUrl();
    }

    /**
     * Compatibility filter for PmproCheckoutNoAutoLogin if that helper is enabled.
     */
    public static function filterCustomConfirmationUrl(string $url, int $userId = 0, int $levelId = 0): string
    {
        if ($userId <= 0) {
            return $url;
        }

        if (! self::isEventSignupUser($userId) && ! self::isActiveEventSignupRequest()) {
            return $url;
        }

        self::logoutBrowserSession(false);
        self::clearEventCookie();

        return self::completionUrl();
    }

    /**
     * Compatibility filter for PmproCheckoutNoAutoLogin restart/cancel URLs.
     */
    public static function filterCustomRestartUrl(string $url, int $userId = 0, int $levelId = 0): string
    {
        if (($userId > 0 && self::isEventSignupUser($userId)) || self::isActiveEventSignupRequest()) {
            return self::entryUrl();
        }

        return $url;
    }

    /* ==========================================================================
     * EVENT META / SOURCE HELPERS
     * ========================================================================== */

    /**
     * Apply event signup source/context to the user.
     *
     * Why this matters:
     * - ReferralSourceTracker commits sccc_referral_source from pending meta.
     * - We write the pending event source before the tracker commits it.
     */
    private static function applyEventMetaToUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $payload = self::activePayload();

        if (empty($payload) && self::isEventSignupUser($userId)) {
            $payload = [
                'event'  => (string) get_user_meta($userId, self::META_EVENT_NAME, true),
                'source' => (string) get_user_meta($userId, self::META_EVENT_SOURCE, true),
            ];
        }

        if (empty($payload)) {
            $payload = self::payloadFromSettings();
        }

        $eventName = self::clean((string) ($payload['event'] ?? ''));
        $source    = self::clean((string) ($payload['source'] ?? ''));

        if ($eventName === '') {
            $eventName = self::eventName();
        }

        if ($source === '') {
            $source = self::sourceValue();
        }

        update_user_meta($userId, self::META_CONTEXT, self::CONTEXT_VALUE);
        update_user_meta($userId, self::META_EVENT_NAME, $eventName);
        update_user_meta($userId, self::META_EVENT_SOURCE, $source);
        update_user_meta($userId, self::REFERRAL_PENDING_META, $source);
    }

    /**
     * Whether the user belongs to the event terminal signup flow.
     */
    private static function isEventSignupUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return (string) get_user_meta($userId, self::META_CONTEXT, true) === self::CONTEXT_VALUE;
    }

    /**
     * Payload built from current event settings.
     */
    private static function payloadFromSettings(): array
    {
        return [
            'event'  => self::eventName(),
            'source' => self::sourceValue(),
            'issued' => time(),
        ];
    }

    /**
     * Active request payload from cookie or current valid entry/checkout query.
     */
    private static function activePayload(): array
    {
        $payload = self::payloadFromCookie();

        if (! empty($payload)) {
            return $payload;
        }

        if (self::requestShouldStartEventMode() && self::canStartFromCurrentRequest()) {
            return self::payloadFromSettings();
        }

        return [];
    }

    /**
     * Whether the current request is part of event signup.
     */
    private static function isActiveEventSignupRequest(): bool
    {
        if (self::hasValidEventCookie()) {
            return true;
        }

        return self::requestShouldStartEventMode() && self::canStartFromCurrentRequest();
    }

    /**
     * Should this request start event mode?
     */
    private static function requestShouldStartEventMode(): bool
    {
        if (self::isEntryRequest()) {
            return true;
        }

        return isset($_GET[self::EVENT_QUERY_FLAG]); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    /**
     * Optional event access key validation.
     */
    private static function currentRequestHasValidAccessKey(): bool
    {
        $expected = self::accessKey();

        if ($expected === '') {
            return true;
        }

        if (! isset($_GET[self::EVENT_KEY_QUERY])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return false;
        }

        $provided = self::clean((string) wp_unslash($_GET[self::EVENT_KEY_QUERY])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        return $provided !== '' && hash_equals($expected, $provided);
    }

    /* ==========================================================================
     * URL / PATH HELPERS
     * ========================================================================== */

    /**
     * Event signup entry path.
     */
    private static function entryPath(): string
    {
        $path = (string) apply_filters('sccc_event_signup_entry_path', '/event-signup/');

        return self::normalizePath($path);
    }

    /**
     * Event signup completion path.
     */
    private static function completePath(): string
    {
        $path = (string) apply_filters('sccc_event_signup_complete_path', '/event-signup-complete/');

        return self::normalizePath($path);
    }

    /**
     * Event signup checkout path.
     *
     * This keeps the shared iPad/tablet flow on the selectable kiosk checkout
     * template instead of sending visitors to the normal PMPro checkout page.
     *
     * Default page setup:
     * - Create a WordPress page at /event-membership-checkout/
     * - Assign the "Event Membership Checkout" template
     * - Add the PMPro Membership Checkout block or [pmpro_checkout] shortcode
     */
    private static function checkoutPath(): string
    {
        $path = (string) apply_filters('sccc_event_signup_checkout_path', '/event-membership-checkout/');

        return self::normalizePath($path);
    }

    /**
     * Is the current request the event signup entry page?
     */
    private static function isEntryRequest(): bool
    {
        return self::currentRequestPath() === self::entryPath();
    }

    /**
     * Is the current request the event signup completion page?
     */
    private static function isCompleteRequest(): bool
    {
        return self::currentRequestPath() === self::completePath();
    }

    /**
     * Is the current request a PMPro checkout request related to event signup?
     */
    private static function isPmproCheckoutWithEventMode(): bool
    {
        if (! self::isActiveEventSignupRequest()) {
            return false;
        }

        if (function_exists('pmpro_is_checkout') && pmpro_is_checkout()) {
            return true;
        }

        return isset($_GET[self::EVENT_QUERY_FLAG]); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    /**
     * Current request path normalized against the site root.
     */
    private static function currentRequestPath(): string
    {
        $requestUri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
            ? (string) wp_unslash($_SERVER['REQUEST_URI'])
            : '/';

        $path = (string) wp_parse_url($requestUri, PHP_URL_PATH);

        if ($path === '') {
            $path = '/';
        }

        $homePath = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
        $homePath = self::normalizePath($homePath ?: '/');

        $path = self::normalizePath($path);

        /**
         * If WordPress is installed in a subdirectory, strip the home path so
         * /subdir/event-signup/ still matches /event-signup/.
         */
        if ($homePath !== '/' && str_starts_with($path . '/', $homePath . '/')) {
            $path = substr($path, strlen($homePath));
            $path = self::normalizePath($path ?: '/');
        }

        return $path;
    }

    /**
     * Normalize a path to:
     * - leading slash
     * - no trailing slash, except root
     */
    private static function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '/';
        }

        $path = '/' . ltrim($path, '/');
        $path = untrailingslashit($path);

        return $path === '' ? '/' : $path;
    }

    /* ==========================================================================
     * COOKIE HELPERS
     * ========================================================================== */

    /**
     * Create and store a signed event cookie.
     */
    private static function setEventCookie(array $payload): void
    {
        if (headers_sent()) {
            return;
        }

        $token = self::signPayload($payload);

        if ($token === '') {
            return;
        }

        setcookie(
            self::COOKIE_NAME,
            $token,
            self::cookieOptions(time() + self::COOKIE_TTL_SECONDS)
        );

        $_COOKIE[self::COOKIE_NAME] = $token;
    }

    /**
     * Clear event cookie.
     */
    private static function clearEventCookie(): void
    {
        if (! headers_sent()) {
            setcookie(
                self::COOKIE_NAME,
                '',
                self::cookieOptions(time() - HOUR_IN_SECONDS)
            );
        }

        unset($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * Whether a valid event cookie exists.
     */
    private static function hasValidEventCookie(): bool
    {
        return ! empty(self::payloadFromCookie());
    }

    /**
     * Decode and validate event cookie payload.
     */
    private static function payloadFromCookie(): array
    {
        if (empty($_COOKIE[self::COOKIE_NAME]) || ! is_string($_COOKIE[self::COOKIE_NAME])) {
            return [];
        }

        $token = sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME]));

        return self::validateSignedPayload($token);
    }

    /**
     * Sign a small JSON payload.
     */
    private static function signPayload(array $payload): string
    {
        $json = wp_json_encode($payload);

        if (! is_string($json) || $json === '') {
            return '';
        }

        $encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $sig     = hash_hmac('sha256', $encoded, wp_salt('auth'));

        return $encoded . '.' . $sig;
    }

    /**
     * Validate signed payload.
     */
    private static function validateSignedPayload(string $token): array
    {
        if ($token === '' || ! str_contains($token, '.')) {
            return [];
        }

        [$encoded, $sig] = array_pad(explode('.', $token, 2), 2, '');

        if ($encoded === '' || $sig === '') {
            return [];
        }

        $expected = hash_hmac('sha256', $encoded, wp_salt('auth'));

        if (! hash_equals($expected, $sig)) {
            return [];
        }

        $json = base64_decode(strtr($encoded, '-_', '+/'), true);

        if (! is_string($json) || $json === '') {
            return [];
        }

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            return [];
        }

        $issued = isset($payload['issued']) ? absint($payload['issued']) : 0;

        if ($issued <= 0 || $issued < (time() - self::COOKIE_TTL_SECONDS)) {
            return [];
        }

        return [
            'event'  => self::clean((string) ($payload['event'] ?? '')),
            'source' => self::clean((string) ($payload['source'] ?? '')),
            'issued' => $issued,
        ];
    }

    /**
     * Shared cookie options.
     */
    private static function cookieOptions(int $expires): array
    {
        $options = [
            'expires'  => $expires,
            'path'     => defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        if (defined('COOKIE_DOMAIN') && COOKIE_DOMAIN) {
            $options['domain'] = COOKIE_DOMAIN;
        }

        return $options;
    }

    /* ==========================================================================
     * CHECKOUT CARD HELPERS
     * ========================================================================== */

    /**
     * Checkout URL for a PMPro level.
     *
     * Event/tablet signups must not use PMPro's normal checkout page URL because
     * the normal checkout should keep the full website header/footer. Instead,
     * this sends the visitor to the dedicated event checkout page that has the
     * selectable "Event Membership Checkout" kiosk template assigned.
     */
    private static function checkoutUrlForLevel(int $levelId): string
    {
        $url = home_url(self::checkoutPath());

        $args = [
            'level'                => $levelId,
            self::EVENT_QUERY_FLAG => '1',
        ];

        $key = self::accessKey();
        if ($key !== '') {
            $args[self::EVENT_KEY_QUERY] = $key;
        }

        return (string) add_query_arg($args, $url);
    }

    /**
     * Pick the featured level.
     *
     * Priority:
     * - Level named "Member"
     * - First non-Founding level
     * - First level
     */
    private static function autoPickMostPopular(array $levelIds): int
    {
        if (empty($levelIds) || ! function_exists('pmpro_getLevel')) {
            return 0;
        }

        foreach ($levelIds as $id) {
            $lvl = pmpro_getLevel((int) $id);

            if (! empty($lvl) && is_object($lvl) && ! empty($lvl->name)) {
                if (mb_strtolower(trim((string) $lvl->name)) === 'member') {
                    return (int) $id;
                }
            }
        }

        foreach ($levelIds as $id) {
            if (
                class_exists(FoundingCap::class)
                && (int) $id === (int) FoundingCap::FOUNDING_LEVEL_ID
            ) {
                continue;
            }

            return (int) $id;
        }

        return (int) $levelIds[0];
    }

    /**
     * Build price + suffix from PMPro level billing configuration.
     *
     * This mirrors the existing MembershipsTiersGrid behavior.
     */
    private static function priceAndSuffixFromLevel(object $level): array
    {
        $billingAmount  = isset($level->billing_amount) ? (float) $level->billing_amount : 0.0;
        $initialPayment = isset($level->initial_payment) ? (float) $level->initial_payment : 0.0;

        $cycleNumber = isset($level->cycle_number) ? (int) $level->cycle_number : 0;
        $cyclePeriod = isset($level->cycle_period) ? (string) $level->cycle_period : '';

        $expNumber = isset($level->expiration_number) ? (int) $level->expiration_number : 0;
        $expPeriod = isset($level->expiration_period) ? (string) $level->expiration_period : '';

        $hasCycle = ($cycleNumber > 0 && trim($cyclePeriod) !== '');
        $hasExp   = ($expNumber > 0 && trim($expPeriod) !== '');

        if ($hasCycle && $billingAmount > 0) {
            return [
                'price'  => self::formatPmproMoney($billingAmount),
                'suffix' => self::periodSuffix($cycleNumber, $cyclePeriod),
            ];
        }

        if ($hasCycle && $billingAmount <= 0 && $initialPayment > 0) {
            return [
                'price'  => self::formatPmproMoney($initialPayment),
                'suffix' => self::periodSuffix($cycleNumber, $cyclePeriod),
            ];
        }

        if ($hasExp && $initialPayment > 0) {
            return [
                'price'  => self::formatPmproMoney($initialPayment),
                'suffix' => self::periodSuffix($expNumber, $expPeriod),
            ];
        }

        if ($initialPayment <= 0 && $billingAmount <= 0) {
            return [
                'price'  => 'Free',
                'suffix' => $hasCycle ? self::periodSuffix($cycleNumber, $cyclePeriod)
                    : ($hasExp ? self::periodSuffix($expNumber, $expPeriod) : ''),
            ];
        }

        $amount = $initialPayment > 0 ? $initialPayment : $billingAmount;

        return [
            'price'  => self::formatPmproMoney($amount),
            'suffix' => '/ one-time',
        ];
    }

    /**
     * Period suffix like "/ month", "/ year", "/ 3 months".
     */
    private static function periodSuffix(int $n, string $period): string
    {
        $p = mb_strtolower(trim($period));
        $p = rtrim($p, 's');

        if ($p === '') {
            return '';
        }

        if ($n > 1) {
            return '/ ' . $n . ' ' . $p . 's';
        }

        return '/ ' . $p;
    }

    /**
     * Format money with PMPro when available.
     */
    private static function formatPmproMoney(float $amount): string
    {
        $price = function_exists('pmpro_formatPrice')
            ? (string) pmpro_formatPrice($amount)
            : ('$' . number_format($amount, 0));

        return html_entity_decode($price, ENT_QUOTES | ENT_HTML5);
    }

    /**
     * Preserve sanitized PMPro WYSIWYG descriptions.
     */
    private static function cleanTagline(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $hasHtml = (bool) preg_match('/<[^>]+>/', $html);

        if ($hasHtml) {
            return function_exists('wp_kses_post')
                ? (string) wp_kses_post($html)
                : $html;
        }

        $text = function_exists('wp_strip_all_tags')
            ? wp_strip_all_tags($html, true)
            : strip_tags($html);

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        $max = 160;
        if (mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max - 1) . '…';
        }

        return $text;
    }

    /* ==========================================================================
     * BROWSER / HEADER HELPERS
     * ========================================================================== */

    /**
     * Clear the browser session.
     *
     * $fullLogout = true:
     * - Used on event entry/complete pages.
     * - Runs wp_logout() so the current browser is fully reset.
     *
     * $fullLogout = false:
     * - Used inside PMPro redirect filters.
     * - Clears cookies/current user without firing a full logout stack during
     *   PMPro's redirect decision.
     */
    private static function logoutBrowserSession(bool $fullLogout): void
    {
        if ($fullLogout && get_current_user_id() > 0) {
            wp_logout();
            return;
        }

        wp_clear_auth_cookie();
        wp_set_current_user(0);
    }

    /**
     * Send no-cache/no-index headers for terminal pages and event checkout.
     */
    private static function sendNoStoreHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        nocache_headers();

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private', true);
        header('Pragma: no-cache', true);
        header('Expires: 0', true);
        header('X-Robots-Tag: noindex, nofollow', true);
    }

    /* ==========================================================================
     * OPTIONS / SANITIZATION HELPERS
     * ========================================================================== */

    /**
     * Read an ACF option value, with a normal WP option fallback.
     */
    private static function option(string $key, mixed $default = ''): mixed
    {
        $value = null;

        if (function_exists('get_field')) {
            $value = get_field($key, 'option');
        }

        if ($value === null || $value === false || $value === '') {
            $stored = get_option($key, null);
            if ($stored !== null && $stored !== false && $stored !== '') {
                $value = $stored;
            }
        }

        if ($value === null || $value === false || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * Clean simple text values used in cookies/meta.
     */
    private static function clean(string $value): string
    {
        $value = sanitize_text_field($value);
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            $value = (string) mb_substr($value, 0, 200);
        } else {
            $value = (string) substr($value, 0, 200);
        }

        return trim($value);
    }
}

EventSignupFlow::boot();