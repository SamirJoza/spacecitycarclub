<?php

/**
 * app/Support/Dev/LocalSslStreamBypass.php
 * File: app/Support/Dev/LocalSslStreamBypass.php
 *
 * Dev-only: prevent local HTTPS/self-signed cert issues from crashing the request when
 * plugins call getimagesize('https://...') and PHP emits warnings that Acorn/Laravel
 * converts into ErrorException (500).
 *
 * This SINGLE file replaces any earlier variations you may have added like:
 * - LocalForceHttpUploads.php (DON'T use; it causes mixed-content + breaks styling)
 * - IgnoreGetImageSizeSslWarnings.php
 *
 * What this file does
 * -----------------------------------------------------------------------------
 * 1) Best-effort: relax PHP SSL stream defaults (may allow streams to work).
 * 2) Relax WP HTTP API SSL verification locally (helps other plugin requests).
 * 3) Guaranteed: swallow ONLY getimagesize() warnings that are clearly stream/SSL/crypto/open failures,
 *    so the page continues to render (getimagesize() will simply return false).
 * 4) Narrow local-only compatibility shim: swallow the known Gravity Forms APC
 *    script dependency notice for gform_apc_theme_script -> gform_apc_shared,
 *    so WordPress debug notices do not become a fatal ErrorException locally.
 *
 * Safety
 * -----------------------------------------------------------------------------
 * - Runs ONLY for wp_get_environment_type() in ['local','development']
 * - Runs ONLY on local-ish hosts (local., .test, .local, localhost, .lndo.site)
 * - Swallows ONLY the known getimagesize() warning family + the specific APC notice
 *
 * IMPORTANT
 * -----------------------------------------------------------------------------
 * Never run this in production.
 */

namespace App\Support\Dev;

class LocalSslStreamBypass
{
    public static function boot(): void
    {
        // Only run in local/dev env
        $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
        if (!in_array($env, ['local', 'development'], true)) {
            return;
        }

        // Only run on local-ish hosts (extra safety)
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isLocalHost =
            str_contains($host, 'local.') ||
            str_contains($host, '.test') ||
            str_contains($host, '.local') ||
            str_contains($host, 'localhost') ||
            str_contains($host, '.lndo.site');

        if (!$isLocalHost) {
            return;
        }

        /**
         * 1) Best-effort: relax PHP SSL stream defaults
         * ---------------------------------------------------------------------
         * getimagesize('https://...') uses PHP stream wrappers. Some local certs
         * fail verification; this can help in certain environments.
         */
        @stream_context_set_default([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
                'SNI_enabled'       => true,
            ],
        ]);

        /**
         * 2) Relax WP HTTP API SSL verification (dev-only)
         * ---------------------------------------------------------------------
         * This does not directly control getimagesize(), but helps other plugin
         * calls that use wp_remote_get()/Requests/cURL.
         */
        add_filter('https_ssl_verify', '__return_false', 999);
        add_filter('https_local_ssl_verify', '__return_false', 999);

        /**
         * 3) Swallow ONLY the known local/dev warnings/notices that Acorn/Laravel
         *    would otherwise promote into ErrorException.
         * ---------------------------------------------------------------------
         * We keep this intentionally narrow.
         */
        $previousHandler = set_error_handler(
            function (int $errno, string $errstr, string $errfile = '', int $errline = 0) use (&$previousHandler, $host) {
                /**
                 * A) Gravity Forms APC / WordPress doing_it_wrong notice
                 * -----------------------------------------------------------------
                 * Local-only compatibility shim for the known APC asset dependency
                 * notice so it doesn't take down the request in dev.
                 */
                $isWpDebugNotice = in_array($errno, [E_NOTICE, E_USER_NOTICE, E_WARNING, E_USER_WARNING, E_DEPRECATED, E_USER_DEPRECATED], true);

                if ($isWpDebugNotice) {
                    $isApcDependencyNotice =
                        str_contains($errstr, 'Function WP_Scripts::add was called') &&
                        str_contains($errstr, 'incorrectly') &&
                        str_contains($errstr, 'gform_apc_theme_script') &&
                        str_contains($errstr, 'gform_apc_shared');

                    if ($isApcDependencyNotice) {
                        return true; // handled -> prevents ErrorException locally
                    }
                }

                /**
                 * B) getimagesize() stream/SSL/crypto/open warnings
                 * -----------------------------------------------------------------
                 * We only intercept warnings that:
                 * - are clearly from getimagesize(...)
                 * - look like stream / crypto / SSL failures
                 * - and reference the current local host (or are obviously URL-based)
                 */
                if ($errno === E_WARNING) {
                    // getimagesize warnings can appear as "getimagesize():" or "getimagesize(https://...):"
                    $isGetImageSize =
                        str_contains($errstr, 'getimagesize()') ||
                        str_contains($errstr, 'getimagesize(');

                    if ($isGetImageSize) {
                        // Keep this scoped to local URLs as much as possible
                        $mentionsLocalHost = ($host !== '') && str_contains($errstr, $host);

                        // Match the known failure families (varies by PHP/OpenSSL builds)
                        $isStreamOrCryptoFailure =
                            // Certificate / SSL verification failures
                            str_contains($errstr, 'certificate verify failed') ||
                            str_contains($errstr, 'SSL operation failed') ||

                            // Crypto handshake failures
                            str_contains($errstr, 'Failed to enable crypto') ||
                            str_contains($errstr, 'Unable to activate crypto') ||
                            str_contains($errstr, 'stream_socket_enable_crypto') ||

                            // General stream failures
                            str_contains($errstr, 'Failed to open stream') ||
                            str_contains($errstr, 'operation failed') ||
                            str_contains($errstr, 'Connection refused') ||
                            str_contains($errstr, 'timed out') ||
                            str_contains($errstr, 'No route to host') ||
                            str_contains($errstr, 'Temporary failure') ||
                            str_contains($errstr, 'Name or service not known') ||
                            str_contains($errstr, 'getaddrinfo failed');

                        // Only swallow when it’s clearly the known problem surface
                        if ($isStreamOrCryptoFailure && ($mentionsLocalHost || str_contains($errstr, 'https://') || str_contains($errstr, 'http://'))) {
                            return true; // handled -> prevents ErrorException
                        }
                    }
                }

                // Anything else behaves normally
                if (is_callable($previousHandler)) {
                    return (bool) $previousHandler($errno, $errstr, $errfile, $errline);
                }

                return false;
            }
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LocalSslStreamBypass loaded (dev-only): swallowing getimagesize stream/SSL warnings and known APC dependency notice on local.');
        }
    }
}

LocalSslStreamBypass::boot();