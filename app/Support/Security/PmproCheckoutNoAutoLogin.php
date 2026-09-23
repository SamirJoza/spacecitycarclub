<?php

namespace App\Support\Security;

use WP_User;

/**
 * File: app/Support/Security/PmproCheckoutNoAutoLogin.php
 *
 * Prevents abandoned PMPro Stripe Checkout signups from leaving logged-in
 * subscriber/customer accounts behind.
 *
 * Why this file exists:
 * - PMPro creates the WordPress user before Stripe Checkout is completed.
 * - PMPro authenticates the user during checkout.
 * - If the user backs out of Stripe, cancels, or payment fails, the visitor can
 *   otherwise end up logged in as a plain subscriber/customer.
 *
 * What this file does:
 * - Marks newly created PMPro checkout users as temporary checkout users.
 * - Clears the browser auth cookie before sending the user to Stripe.
 * - Routes PMPro confirmation through a signed SCCC return URL.
 * - Routes Stripe success through a signed SCCC return URL when the Stripe filter is honored.
 * - Logs the user back in only after Stripe/payment success returns through the signed flow.
 * - Sends successful users to the PMPro confirmation page.
 * - Sends Stripe cancellation to a signed cleanup URL.
 * - Deletes the temporary user immediately when Stripe cancellation/back-out is detected.
 * - Deletes the temporary user immediately when PMPro marks the order as error.
 * - Clears all temporary markers after successful PMPro checkout.
 *
 * Important limitation:
 * - If a visitor closes the Stripe tab/browser and never returns to the site,
 *   WordPress receives no request, so immediate deletion cannot run at that exact
 *   moment. This file intentionally does not schedule delayed cleanup because the
 *   requested behavior is no grace-period cleanup.
 */

defined('ABSPATH') || exit;

final class PmproCheckoutNoAutoLogin
{
    /**
     * User meta keys used to identify temporary checkout users.
     */
    private const META_STARTED_AT = 'sccc_pmpro_temp_checkout_started_at';
    private const META_LEVEL_ID = 'sccc_pmpro_temp_checkout_level_id';
    private const META_ORDER_CODE = 'sccc_pmpro_temp_checkout_order_code';
    private const META_GATEWAY = 'sccc_pmpro_temp_checkout_gateway';
    private const META_BROWSER_DETACHED_AT = 'sccc_pmpro_temp_checkout_browser_detached_at';

    /**
     * Signed browser cookie used to identify the temporary user when returning
     * from Stripe cancellation, success, login fallback, or browser-back behavior.
     */
    private const COOKIE_NAME = 'sccc_pmpro_temp_checkout';

    /**
     * Query args used by the custom Stripe/confirmation return URLs.
     */
    private const SUCCESS_QUERY_FLAG = 'sccc_pmpro_success';
    private const CANCEL_QUERY_FLAG = 'sccc_pmpro_cancel';
    private const RETURN_QUERY_TOKEN = 'sccc_pmpro_token';

    /**
     * Register PMPro and WordPress hooks.
     */
    public static function boot(): void
    {
        /**
         * Mark a newly created PMPro checkout user before PMPro authenticates them.
         */
        add_action('pmpro_checkout_before_user_auth', [self::class, 'markTemporaryCheckoutUser'], 10, 1);

        /**
         * Store order context as soon as PMPro builds the checkout order object.
         */
        add_filter('pmpro_checkout_order', [self::class, 'trackCheckoutOrder'], 20, 1);
        add_filter('pmpro_checkout_order_free', [self::class, 'trackCheckoutOrder'], 20, 1);

        /**
         * Before PMPro sends the user to payment processing, detach the browser
         * from the newly created account by clearing auth cookies.
         */
        add_action('pmpro_checkout_before_processing', [self::class, 'detachBrowserSessionBeforePayment'], 20);

        /**
         * Route PMPro's own confirmation URL through our signed return handler.
         *
         * This is the important fallback for the URL you reported:
         * /log-in/?redirect_to=/membership-confirmation/?pmpro_level=3
         *
         * If PMPro/Stripe ignores the Stripe success_url override, this filter
         * still catches the confirmation redirect and sends it through SCCC first.
         */
        add_filter('pmpro_confirmation_url', [self::class, 'routeConfirmationThroughSignedReturn'], 5, 3);

        /**
         * For Stripe Checkout, replace success and cancel URLs with signed SCCC
         * return handlers when the Stripe session filter is honored.
         */
        add_filter('pmpro_stripe_checkout_session_parameters', [self::class, 'addStripeCheckoutReturnUrls'], 20, 3);

        /**
         * Handle signed success/cancel returns very early.
         *
         * This also contains fallbacks for cases where the browser lands on
         * PMPro confirmation or login before the custom success URL is honored.
         */
        add_action('init', [self::class, 'handleEarlyReturnOrLoginFallback'], 0);

        /**
         * Browser-back cleanup needs page context, so this stays on template_redirect.
         */
        add_action('template_redirect', [self::class, 'handleBrowserBackToCheckout'], 1);

        /**
         * Delete the temporary user immediately when failure is detected.
         */
        add_action('pmpro_checkout_processing_failed', [self::class, 'deleteTemporaryUserAfterProcessingFailure'], 20, 1);
        add_action('pmpro_order_status_error', [self::class, 'deleteTemporaryUserAfterOrderError'], 20, 2);

        /**
         * If checkout succeeds, remove temporary flags. For Stripe Checkout this
         * may happen from the Stripe webhook, not the browser return.
         */
        add_action('pmpro_after_checkout', [self::class, 'clearTemporaryCheckoutAfterSuccess'], 20, 2);
    }

    /**
     * Mark the newly created PMPro checkout user as temporary.
     *
     * This fires before PMPro authenticates the new WordPress user.
     */
    public static function markTemporaryCheckoutUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        update_user_meta($userId, self::META_STARTED_AT, time());

        if (isset($_REQUEST['level'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            update_user_meta($userId, self::META_LEVEL_ID, absint($_REQUEST['level'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
    }

    /**
     * Store PMPro order context on the temporary user.
     *
     * The order object is returned unchanged so PMPro checkout behavior is not altered.
     */
    public static function trackCheckoutOrder(mixed $order): mixed
    {
        if (! is_object($order)) {
            return $order;
        }

        $userId = self::getOrderUserId($order);

        if ($userId <= 0 || ! self::isTemporaryCheckoutUser($userId)) {
            return $order;
        }

        $levelId = self::getOrderLevelId($order);
        $code    = self::getOrderCode($order);
        $gateway = self::getOrderGateway($order);

        if ($levelId > 0) {
            update_user_meta($userId, self::META_LEVEL_ID, $levelId);
        }

        if ($code !== '') {
            update_user_meta($userId, self::META_ORDER_CODE, $code);
        }

        if ($gateway !== '') {
            update_user_meta($userId, self::META_GATEWAY, $gateway);
        }

        return $order;
    }

    /**
     * Clear browser auth cookies before the gateway redirect.
     *
     * This prevents the visitor from returning as a logged-in subscriber/customer
     * if they back out of Stripe.
     *
     * We use wp_clear_auth_cookie() instead of wp_logout() here so PMPro can
     * continue the current server-side checkout request with its current user
     * object intact.
     */
    public static function detachBrowserSessionBeforePayment(): void
    {
        $userId = get_current_user_id();

        if ($userId <= 0 || ! self::isTemporaryCheckoutUser($userId)) {
            return;
        }

        $orderCode = (string) get_user_meta($userId, self::META_ORDER_CODE, true);
        $token     = self::createSignedToken($userId, $orderCode);

        update_user_meta($userId, self::META_BROWSER_DETACHED_AT, time());

        self::setTemporaryCheckoutCookie($token);

        wp_clear_auth_cookie();
    }

    /**
     * Route PMPro's confirmation redirect through our signed return handler.
     *
     * This catches the case where PMPro builds the normal confirmation URL:
     * /membership-confirmation/?pmpro_level=3
     *
     * Returning home_url('/') with signed args gives our init handler the first
     * chance to restore the login session before PMPro redirects to the login page.
     */
    public static function routeConfirmationThroughSignedReturn(string $url, int $userId, mixed $pmproLevel): string
    {
        if ($userId <= 0 || ! self::isTemporaryCheckoutUser($userId)) {
            return $url;
        }

        $levelId = self::levelIdFromMixedLevel($pmproLevel);

        if ($levelId > 0) {
            update_user_meta($userId, self::META_LEVEL_ID, $levelId);
        }

        $orderCode = (string) get_user_meta($userId, self::META_ORDER_CODE, true);
        $token     = self::createSignedToken($userId, $orderCode);

        return add_query_arg(
            [
                self::SUCCESS_QUERY_FLAG => '1',
                self::RETURN_QUERY_TOKEN => $token,
            ],
            home_url('/')
        );
    }

    /**
     * Replace Stripe Checkout return URLs with safe SCCC handlers.
     *
     * Success goes to a simple SCCC return URL first, where we restore the auth
     * cookie and then redirect to confirmation.
     */
    public static function addStripeCheckoutReturnUrls(array $checkoutSessionParams, mixed $order, mixed $customer): array
    {
        if (! is_object($order)) {
            return $checkoutSessionParams;
        }

        $userId = self::getOrderUserId($order);

        if ($userId <= 0 || ! self::isTemporaryCheckoutUser($userId)) {
            return $checkoutSessionParams;
        }

        $levelId   = self::getOrderLevelId($order);
        $orderCode = self::getOrderCode($order);
        $token     = self::createSignedToken($userId, $orderCode);

        if ($levelId > 0) {
            update_user_meta($userId, self::META_LEVEL_ID, $levelId);
        }

        if ($orderCode !== '') {
            update_user_meta($userId, self::META_ORDER_CODE, $orderCode);
        }

        /**
         * Let add_query_arg handle URL encoding. Do not pre-encode the token.
         */
        $checkoutSessionParams['success_url'] = add_query_arg(
            [
                self::SUCCESS_QUERY_FLAG => '1',
                self::RETURN_QUERY_TOKEN => $token,
            ],
            home_url('/')
        );

        $checkoutSessionParams['cancel_url'] = add_query_arg(
            [
                self::CANCEL_QUERY_FLAG => '1',
                self::RETURN_QUERY_TOKEN => $token,
            ],
            self::checkoutRestartUrl($userId, $levelId)
        );

        return $checkoutSessionParams;
    }

    /**
     * Handle signed Stripe success/cancel returns and PMPro login fallback.
     *
     * This runs on init at priority 0 so it can restore login before PMPro's
     * confirmation/login redirects take over.
     */
    public static function handleEarlyReturnOrLoginFallback(): void
    {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        /**
         * Signed success URL case.
         */
        if (isset($_GET[self::SUCCESS_QUERY_FLAG], $_GET[self::RETURN_QUERY_TOKEN])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $token  = self::queryValue(self::RETURN_QUERY_TOKEN);
            $userId = self::validateSignedToken($token);

            self::loginCheckoutUserAndRedirect($userId, self::confirmationUrl($userId));
        }

        /**
         * Signed cancel URL case.
         */
        if (isset($_GET[self::CANCEL_QUERY_FLAG], $_GET[self::RETURN_QUERY_TOKEN])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $token  = self::queryValue(self::RETURN_QUERY_TOKEN);
            $userId = self::validateSignedToken($token);

            self::deleteTemporaryUserAndRedirect($userId, 'cancelled');
        }

        /**
         * Fallback:
         * If Stripe/PMPro still lands on confirmation while logged out, use the
         * signed temporary cookie to restore the browser session first.
         */
        if (! is_user_logged_in() && self::isCurrentRequestLikelyPmproConfirmation()) {
            $token  = self::readTemporaryCheckoutCookie();
            $userId = self::validateSignedToken($token);

            if ($userId > 0) {
                self::loginCheckoutUserAndRedirect($userId, self::currentRequestUrl());
            }
        }

        /**
         * Fallback:
         * If PMPro already redirected the logged-out user to the login page with
         * a redirect_to value pointing back to confirmation, restore login and
         * send the user directly to that target URL.
         */
        if (! is_user_logged_in() && self::loginRedirectTargetLooksLikeConfirmation()) {
            $token  = self::readTemporaryCheckoutCookie();
            $userId = self::validateSignedToken($token);

            if ($userId > 0) {
                $redirectTarget = self::queryValue('redirect_to');
                $redirectTarget = $redirectTarget !== ''
                    ? wp_validate_redirect($redirectTarget, self::confirmationUrl($userId))
                    : self::confirmationUrl($userId);

                self::loginCheckoutUserAndRedirect($userId, $redirectTarget);
            }
        }
    }

    /**
     * Handle browser-back behavior after Stripe opens.
     *
     * This is separate from the signed success/cancel handler because checkout
     * page detection is more reliable later in the WordPress request.
     */
    public static function handleBrowserBackToCheckout(): void
    {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        if (! function_exists('pmpro_is_checkout') || ! pmpro_is_checkout()) {
            return;
        }

        $token  = self::readTemporaryCheckoutCookie();
        $userId = self::validateSignedToken($token);

        if ($userId > 0 && self::isTemporaryCheckoutUser($userId)) {
            self::deleteTemporaryUserAndRedirect($userId, 'cancelled');
        }
    }

    /**
     * Delete the temporary user after a PMPro processing failure.
     */
    public static function deleteTemporaryUserAfterProcessingFailure(?object $order = null): void
    {
        $userId = is_object($order) ? self::getOrderUserId($order) : 0;

        if ($userId <= 0) {
            $userId = get_current_user_id();
        }

        if ($userId <= 0) {
            return;
        }

        self::deleteTemporaryUser($userId);
        self::clearTemporaryCheckoutCookie();
    }

    /**
     * Delete the temporary user when PMPro updates an order status to error.
     */
    public static function deleteTemporaryUserAfterOrderError(?object $order = null, string $originalStatus = ''): void
    {
        if (! is_object($order)) {
            return;
        }

        $userId = self::getOrderUserId($order);

        if ($userId <= 0) {
            return;
        }

        self::deleteTemporaryUser($userId);
    }

    /**
     * Clear temporary markers after successful checkout.
     *
     * For Stripe Checkout, PMPro usually triggers this from the successful webhook.
     */
    public static function clearTemporaryCheckoutAfterSuccess(int $userId, mixed $order = null): void
    {
        self::clearTemporaryMeta($userId);
    }

    /**
     * Log the checkout user back in and redirect to the target URL.
     */
    private static function loginCheckoutUserAndRedirect(int $userId, string $targetUrl): void
    {
        if ($userId <= 0) {
            wp_safe_redirect(self::checkoutRestartUrl());
            exit;
        }

        $user = get_userdata($userId);

        if (! $user instanceof WP_User) {
            self::clearTemporaryCheckoutCookie();

            wp_safe_redirect(self::checkoutRestartUrl());
            exit;
        }

        wp_set_current_user($userId);
        wp_set_auth_cookie($userId, true, is_ssl());

        /**
         * Once the success/login fallback path is reached, the temporary checkout
         * cookie should no longer trigger browser-back cleanup.
         */
        self::clearTemporaryCheckoutCookie();

        /**
         * If the webhook already finalized membership, clean temporary meta.
         * If webhook is still catching up, keep meta so failure hooks can still
         * delete the temporary user if needed.
         */
        if (self::isActivePmproMember($userId)) {
            self::clearTemporaryMeta($userId);
        }

        nocache_headers();

        wp_safe_redirect($targetUrl ?: self::confirmationUrl($userId));
        exit;
    }

    /**
     * Delete and redirect after frontend cancellation/backout.
     */
    private static function deleteTemporaryUserAndRedirect(int $userId, string $reason): void
    {
        $levelId     = $userId > 0 ? absint(get_user_meta($userId, self::META_LEVEL_ID, true)) : 0;
        $redirectUrl = self::checkoutRestartUrl($userId, $levelId);

        self::deleteTemporaryUser($userId);
        self::clearTemporaryCheckoutCookie();

        $redirectUrl = add_query_arg(
            [
                'sccc_checkout' => $reason,
            ],
            $redirectUrl
        );

        wp_safe_redirect($redirectUrl);
        exit;
    }

    /**
     * Delete a temporary user if they are still not an active PMPro member.
     */
    private static function deleteTemporaryUser(int $userId): bool
    {
        if ($userId <= 0 || ! self::isTemporaryCheckoutUser($userId)) {
            return false;
        }

        if (self::isActivePmproMember($userId)) {
            self::clearTemporaryMeta($userId);
            return false;
        }

        $user = get_userdata($userId);

        if (! $user instanceof WP_User) {
            return false;
        }

        if (! self::hasOnlyDisposableRoles($user)) {
            return false;
        }

        if (get_current_user_id() === $userId) {
            wp_logout();
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';

        return (bool) wp_delete_user($userId);
    }

    /**
     * Build the restart checkout URL.
     */
    private static function checkoutRestartUrl(int $userId = 0, int $levelId = 0): string
    {
        if ($levelId <= 0 && $userId > 0) {
            $levelId = absint(get_user_meta($userId, self::META_LEVEL_ID, true));
        }

        if (function_exists('pmpro_url')) {
            $url = $levelId > 0
                ? pmpro_url('checkout', '?level=' . $levelId)
                : pmpro_url('levels');
        } else {
            $url = home_url('/');
        }

        return (string) apply_filters('sccc_pmpro_checkout_restart_url', $url, $userId, $levelId);
    }

    /**
     * Build the PMPro confirmation URL.
     *
     * The user-reported URL used pmpro_level=3, so this helper uses that same
     * argument name to stay aligned with the confirmation page currently in use.
     */
    private static function confirmationUrl(int $userId = 0): string
    {
        $levelId = $userId > 0
            ? absint(get_user_meta($userId, self::META_LEVEL_ID, true))
            : 0;

        if (function_exists('pmpro_url')) {
            $url = $levelId > 0
                ? pmpro_url('confirmation', '?pmpro_level=' . $levelId)
                : pmpro_url('confirmation');
        } else {
            $url = home_url('/');
        }

        return (string) apply_filters('sccc_pmpro_checkout_confirmation_url', $url, $userId, $levelId);
    }

    /**
     * Check whether the current request appears to be the PMPro confirmation URL.
     *
     * This is intentionally path-based because it runs very early on init,
     * before normal WordPress conditional tags are reliable.
     */
    private static function isCurrentRequestLikelyPmproConfirmation(): bool
    {
        if (! function_exists('pmpro_url')) {
            return false;
        }

        return self::urlLooksLikePmproConfirmation(self::currentRequestUrl());
    }

    /**
     * Check whether the current login request is redirecting back to confirmation.
     */
    private static function loginRedirectTargetLooksLikeConfirmation(): bool
    {
        $redirectTo = self::queryValue('redirect_to');

        if ($redirectTo === '') {
            return false;
        }

        return self::urlLooksLikePmproConfirmation($redirectTo);
    }

    /**
     * Check whether a URL has the same path as the PMPro confirmation page.
     */
    private static function urlLooksLikePmproConfirmation(string $url): bool
    {
        if (! function_exists('pmpro_url')) {
            return false;
        }

        $confirmationUrl = pmpro_url('confirmation');

        $targetPath       = (string) wp_parse_url($url, PHP_URL_PATH);
        $confirmationPath = (string) wp_parse_url($confirmationUrl, PHP_URL_PATH);

        $targetPath       = untrailingslashit($targetPath);
        $confirmationPath = untrailingslashit($confirmationPath);

        return $targetPath !== '' && $confirmationPath !== '' && $targetPath === $confirmationPath;
    }

    /**
     * Build the current request URL.
     */
    private static function currentRequestUrl(): string
    {
        $requestUri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
            ? wp_unslash($_SERVER['REQUEST_URI'])
            : '/';

        return home_url($requestUri);
    }

    /**
     * Read a query value safely.
     */
    private static function queryValue(string $key): string
    {
        if (! isset($_GET[$key]) || ! is_string($_GET[$key])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return '';
        }

        return sanitize_text_field(wp_unslash((string) $_GET[$key])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    /**
     * Create a signed token for browser return/cancellation cleanup.
     */
    private static function createSignedToken(int $userId, string $orderCode = ''): string
    {
        $issuedAt  = time();
        $payload   = $userId . '|' . $issuedAt . '|' . $orderCode;
        $signature = hash_hmac('sha256', $payload, wp_salt('auth'));

        return $payload . '|' . $signature;
    }

    /**
     * Validate a signed cleanup/login token and return the user ID.
     */
    private static function validateSignedToken(string $token): int
    {
        if ($token === '') {
            return 0;
        }

        $parts = explode('|', $token);

        if (count($parts) !== 4) {
            return 0;
        }

        [$userIdRaw, $issuedAtRaw, $orderCode, $signature] = $parts;

        $userId   = absint($userIdRaw);
        $issuedAt = absint($issuedAtRaw);

        if ($userId <= 0 || $issuedAt <= 0 || $signature === '') {
            return 0;
        }

        /**
         * This is not a cleanup grace period.
         * It only prevents very old browser tokens from being reused later.
         */
        if ($issuedAt < (time() - 6 * HOUR_IN_SECONDS)) {
            return 0;
        }

        $payload  = $userId . '|' . $issuedAt . '|' . $orderCode;
        $expected = hash_hmac('sha256', $payload, wp_salt('auth'));

        if (! hash_equals($expected, $signature)) {
            return 0;
        }

        $storedOrderCode = (string) get_user_meta($userId, self::META_ORDER_CODE, true);

        if ($storedOrderCode !== '' && $orderCode !== '' && ! hash_equals($storedOrderCode, $orderCode)) {
            return 0;
        }

        return $userId;
    }

    /**
     * Store the signed temporary checkout token in the browser.
     */
    private static function setTemporaryCheckoutCookie(string $token): void
    {
        if ($token === '' || headers_sent()) {
            return;
        }

        setcookie(
            self::COOKIE_NAME,
            $token,
            self::cookieOptions(time() + 6 * HOUR_IN_SECONDS)
        );

        $_COOKIE[self::COOKIE_NAME] = $token;
    }

    /**
     * Read the temporary checkout cookie.
     */
    private static function readTemporaryCheckoutCookie(): string
    {
        if (empty($_COOKIE[self::COOKIE_NAME]) || ! is_string($_COOKIE[self::COOKIE_NAME])) {
            return '';
        }

        return sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME]));
    }

    /**
     * Clear the temporary checkout cookie.
     */
    private static function clearTemporaryCheckoutCookie(): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(
            self::COOKIE_NAME,
            '',
            self::cookieOptions(time() - HOUR_IN_SECONDS)
        );

        unset($_COOKIE[self::COOKIE_NAME]);
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

    /**
     * Determine if the user is one of our temporary checkout users.
     */
    private static function isTemporaryCheckoutUser(int $userId): bool
    {
        return (bool) get_user_meta($userId, self::META_STARTED_AT, true);
    }

    /**
     * Check whether the user already has an active PMPro membership.
     */
    private static function isActivePmproMember(int $userId): bool
    {
        if ($userId <= 0 || ! function_exists('pmpro_hasMembershipLevel')) {
            return false;
        }

        return (bool) pmpro_hasMembershipLevel(null, $userId);
    }

    /**
     * Only delete low-access users created during checkout.
     */
    private static function hasOnlyDisposableRoles(WP_User $user): bool
    {
        $roles = array_values((array) $user->roles);

        if (empty($roles)) {
            return true;
        }

        $allowedRoles = (array) apply_filters(
            'sccc_pmpro_temp_checkout_disposable_roles',
            [
                'subscriber',
                'customer',
            ]
        );

        return empty(array_diff($roles, $allowedRoles));
    }

    /**
     * Remove all temporary checkout metadata from a successful user.
     */
    private static function clearTemporaryMeta(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        delete_user_meta($userId, self::META_STARTED_AT);
        delete_user_meta($userId, self::META_LEVEL_ID);
        delete_user_meta($userId, self::META_ORDER_CODE);
        delete_user_meta($userId, self::META_GATEWAY);
        delete_user_meta($userId, self::META_BROWSER_DETACHED_AT);
    }

    /**
     * Get the membership level ID from a PMPro level-like value.
     */
    private static function levelIdFromMixedLevel(mixed $level): int
    {
        if (is_object($level) && isset($level->id)) {
            return absint($level->id);
        }

        if (is_array($level) && isset($level['id'])) {
            return absint($level['id']);
        }

        if (is_numeric($level)) {
            return absint($level);
        }

        return 0;
    }

    /**
     * Get the user ID from a PMPro order object.
     */
    private static function getOrderUserId(object $order): int
    {
        return isset($order->user_id) ? absint($order->user_id) : 0;
    }

    /**
     * Get the membership level ID from a PMPro order object.
     */
    private static function getOrderLevelId(object $order): int
    {
        return isset($order->membership_id) ? absint($order->membership_id) : 0;
    }

    /**
     * Get the PMPro order code.
     */
    private static function getOrderCode(object $order): string
    {
        return ! empty($order->code) ? sanitize_text_field((string) $order->code) : '';
    }

    /**
     * Get the PMPro gateway slug.
     */
    private static function getOrderGateway(object $order): string
    {
        return ! empty($order->gateway) ? sanitize_text_field((string) $order->gateway) : '';
    }
}

PmproCheckoutNoAutoLogin::boot();