<?php

/**
 * app/Support/GravityForms/GravityFormsKLoggerBootstrap.php
 *
 * Gravity Forms’ {@see GFLogging::include_logger()} uses:
 *   `require_once 'includes/KLogger.php';`
 * That path is resolved relative to PHP’s **current working directory**, not the logging
 * class file, so `KLogger.php` is often “missing” locally. Acorn/Laravel then turns the
 * resulting warning into an ErrorException.
 *
 * Preload the real file once `GF_PLUGIN_DIR_PATH` exists so GF’s `class_exists('KLogger')`
 * check skips the broken require.
 */

namespace App\Support\GravityForms;

defined('ABSPATH') || exit;

final class GravityFormsKLoggerBootstrap
{
    public static function register(): void
    {
        add_action('after_setup_theme', [self::class, 'maybeLoadKLogger'], 0);
    }

    public static function maybeLoadKLogger(): void
    {
        if (!defined('GF_PLUGIN_DIR_PATH')) {
            return;
        }

        if (class_exists('KLogger', false)) {
            return;
        }

        $path = GF_PLUGIN_DIR_PATH . 'includes/logging/includes/KLogger.php';
        if (is_readable($path)) {
            require_once $path;
        }
    }
}

GravityFormsKLoggerBootstrap::register();
