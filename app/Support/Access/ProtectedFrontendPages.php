<?php

namespace App\Support\Access;

/**
 * File path + filename: app/Support/Access/ProtectedFrontendPages.php
 * -----------------------------------------------------------------------------
 * Purpose:
 * - Enforce frontend access rules for protected paths configured in Theme
 *   Settings.
 *
 * Why this file exists:
 * - The user wants a reusable, centralized way to protect multiple frontend
 *   pages/slugs without hard-coding one-off checks for each page.
 * - Theme Settings becomes the source of truth for which paths are protected,
 *   which role slugs are allowed, and where blocked visitors should be sent.
 *
 * How this works:
 * - Runs on `template_redirect`, before the page template loads.
 * - Reads the protected path rules from ACF options.
 * - If the current request path matches a protected rule, only users with at
 *   least one allowed role slug may continue.
 * - Everyone else is redirected to the configured URL or the homepage.
 *
 * Important implementation notes:
 * - Paths are normalized so editors can enter slugs with or without slashes.
 * - Query strings are ignored.
 * - Matching is prefix-based on normalized path boundaries, not strict exact
 *   equality only. This allows one protected base path like
 *   `/business-directory/` to also protect:
 *     - /business-directory/page/2/
 *     - /business-directory/page/3/
 *     - filtered variants with query strings
 * - Logged-out visitors are always blocked for protected paths.
 *
 * Usage examples for Theme Settings:
 * - business-directory
 * - /business-directory/
 * - /members/private-area/
 */
final class ProtectedFrontendPages
{
    /**
     * Boot the protection hook immediately when this support file is loaded.
     */
    public static function boot(): void
    {
        add_action('template_redirect', [self::class, 'maybeProtectCurrentRequest']);
    }

    /**
     * Apply protection rules to the current frontend request.
     */
    public static function maybeProtectCurrentRequest(): void
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        if (!function_exists('get_field')) {
            return;
        }

        $enabled = (bool) get_field('protected_pages_enabled', 'option');

        if (!$enabled) {
            return;
        }

        $rules = get_field('protected_pages_rules', 'option');

        if (!is_array($rules) || empty($rules)) {
            return;
        }

        $currentPath = self::currentRequestPath();

        if ($currentPath === '') {
            return;
        }

        $defaultRedirect = trim((string) (get_field('protected_pages_default_redirect', 'option') ?: ''));

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $configuredPath = self::normalizeConfiguredPath(
                (string) ($rule['protected_path'] ?? '')
            );

            if ($configuredPath === '') {
                continue;
            }

            if (!self::pathsMatch($currentPath, $configuredPath)) {
                continue;
            }

            $allowedRoles = self::parseRoleList(
                (string) ($rule['protected_allowed_roles'] ?? '')
            );

            if (self::currentUserHasAnyAllowedRole($allowedRoles)) {
                return;
            }

            $redirectValue = trim((string) ($rule['protected_redirect'] ?? ''));

            if ($redirectValue === '') {
                $redirectValue = $defaultRedirect;
            }

            $redirectUrl = self::resolveRedirectUrl($redirectValue);

            if (wp_safe_redirect($redirectUrl, 302, 'SCCC Protected Frontend Pages')) {
                exit;
            }

            exit;
        }
    }

    /**
     * Get the current request path without query-string noise.
     *
     * Examples:
     * - /business-directory/
     * - /business-directory/page/2/
     * - /members/private-area/
     */
    private static function currentRequestPath(): string
    {
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

        if ($requestUri === '') {
            return '';
        }

        $path = (string) wp_parse_url($requestUri, PHP_URL_PATH);

        return self::normalizePath($path);
    }

    /**
     * Normalize a configured path from Theme Settings.
     *
     * Supports:
     * - bare slugs: business-directory
     * - paths: /business-directory/
     * - full URLs: https://example.com/business-directory/
     */
    private static function normalizeConfiguredPath(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $value = (string) wp_parse_url($value, PHP_URL_PATH);
        }

        return self::normalizePath($value);
    }

    /**
     * Normalize a path for stable matching.
     *
     * Rules:
     * - always leading slash
     * - remove trailing slash except for homepage
     * - collapse duplicate slashes
     * - lowercase for stable slug comparisons
     */
    private static function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        $path = preg_replace('#/+#', '/', $path) ?: $path;
        $path = '/' . ltrim($path, '/');

        if ($path !== '/') {
            $path = untrailingslashit($path);
        }

        return strtolower($path);
    }

    /**
     * Check whether the current request path matches a configured protected path.
     *
     * Matching behavior:
     * - Exact path match is allowed.
     * - Prefix match is also allowed, but only on a path boundary.
     *
     * Examples:
     * - configured: /business-directory
     *   current:    /business-directory                => true
     *   current:    /business-directory/page/2         => true
     *   current:    /business-directory-archive        => false
     */
    private static function pathsMatch(string $currentPath, string $configuredPath): bool
    {
        if ($currentPath === $configuredPath) {
            return true;
        }

        return str_starts_with($currentPath . '/', $configuredPath . '/');
    }

    /**
     * Convert a comma-separated role list into normalized role slugs.
     *
     * Examples:
     * - "sccc_member"
     * - "sccc_member,administrator"
     */
    private static function parseRoleList(string $roles): array
    {
        if (trim($roles) === '') {
            return [];
        }

        $parts = preg_split('/[\s,|]+/', $roles) ?: [];

        $normalized = array_map(
            static fn ($role) => sanitize_key((string) $role),
            $parts
        );

        $normalized = array_filter(
            $normalized,
            static fn ($role) => $role !== ''
        );

        return array_values(array_unique($normalized));
    }

    /**
     * Check whether the current logged-in user has at least one allowed role.
     */
    private static function currentUserHasAnyAllowedRole(array $allowedRoles): bool
    {
        if (empty($allowedRoles) || !is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();

        if (!$user || empty($user->roles) || !is_array($user->roles)) {
            return false;
        }

        $currentRoles = array_map(
            static fn ($role) => sanitize_key((string) $role),
            $user->roles
        );

        return !empty(array_intersect($allowedRoles, $currentRoles));
    }

    /**
     * Resolve a redirect value from Theme Settings into a safe frontend URL.
     *
     * Accepted input:
     * - full URL: https://example.com/
     * - relative path: /
     * - relative path: /members/login/
     * - blank: homepage
     */
    private static function resolveRedirectUrl(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return home_url('/');
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return home_url('/' . ltrim($value, '/'));
    }
}

// Boot immediately when this support file is required by the theme.
ProtectedFrontendPages::boot();