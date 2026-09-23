<?php
/**
 * app/Support/Auth/PrettyLogout.php
 * File: app/Support/Auth/PrettyLogout.php
 *
 * Purpose
 * -----------------------------------------------------------------------------
 * Provides a "pretty" logout endpoint at:
 *   /logout/
 *
 * Self-registration
 * -----------------------------------------------------------------------------
 * This file self-registers when it is required (like your other support files).
 * In setup.php you only need:
 *   require_once get_theme_file_path('app/Support/Auth/PrettyLogout.php');
 *
 * Permalinks
 * -----------------------------------------------------------------------------
 * Because this adds a rewrite rule, you must flush permalinks once:
 *   WP Admin → Settings → Permalinks → Save Changes
 */

namespace App\Support\Auth;

class PrettyLogout
{
    /**
     * Query var used by the rewrite rule.
     */
    protected string $qv = 'sccc_logout';

    /**
     * Nonce action used to protect the logout route.
     */
    protected string $nonce_action = 'sccc-logout';

    public function register(): void
    {
        add_action('init', [$this, 'redirectWpLoginLoggedOut'], 0);
        add_action('init', [$this, 'addRewriteRule']);
        add_filter('query_vars', [$this, 'addQueryVar']);

        /**
         * template_redirect runs after WP parsed the request.
         * Safe place to intercept and perform logout + redirect.
         */
        add_action('template_redirect', [$this, 'handle'], 0);
    }

    /**
     * Core logout sends users to wp-login.php?loggedout=true. Send them home instead.
     *
     * Runs on {@see 'init'} priority 0 so it fires before wp-login.php prints headers.
     */
    public function redirectWpLoginLoggedOut(): void
    {
        global $pagenow;

        if (! isset($pagenow) || $pagenow !== 'wp-login.php') {
            return;
        }

        if (! isset($_GET['loggedout'])) {
            return;
        }

        wp_safe_redirect(home_url('/'));
        exit;
    }

    /**
     * Add /logout/ rewrite → index.php?sccc_logout=1
     */
    public function addRewriteRule(): void
    {
        add_rewrite_rule(
            '^logout/?$',
            'index.php?' . $this->qv . '=1',
            'top'
        );
    }

    public function addQueryVar(array $vars): array
    {
        $vars[] = $this->qv;
        return $vars;
    }

    /**
     * Handle /logout/
     *
     * Flow:
     * - If not on /logout/, do nothing.
     * - If not logged in, redirect home.
     * - If nonce missing/invalid, redirect to /logout/?_wpnonce=... (fresh).
     * - If nonce valid, wp_logout() then redirect home.
     */
    public function handle(): void
    {
        $flag = (int) get_query_var($this->qv, 0);
        if ($flag !== 1) {
            return;
        }

        // Already logged out? Just go home.
        if (! is_user_logged_in()) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        $nonce = isset($_GET['_wpnonce']) ? (string) $_GET['_wpnonce'] : '';

        // Missing/invalid nonce → bounce to a fresh nonce-safe /logout/ URL.
        if ($nonce === '' || ! wp_verify_nonce($nonce, $this->nonce_action)) {
            wp_safe_redirect($this->getNonceSafeLogoutUrl());
            exit;
        }

        // Nonce ok → log out immediately.
        wp_logout();

        // Always go home after logout.
        wp_safe_redirect(home_url('/'));
        exit;
    }

    /**
     * Generates a nonce-safe /logout/ URL.
     * Keeps the visible URL short and avoids wp-login.php entirely.
     */
    public function getNonceSafeLogoutUrl(): string
    {
        return wp_nonce_url(home_url('/logout/'), $this->nonce_action);
    }
}

/**
 * -----------------------------------------------------------------------------
 * Self-registration (runs on include)
 * -----------------------------------------------------------------------------
 * Use a closure so we don't run into namespace/global-function callback issues.
 */
add_action('after_setup_theme', function (): void {
    static $booted = false;

    if ($booted) {
        return;
    }

    $booted = true;

    (new \App\Support\Auth\PrettyLogout())->register();
}, 20);
