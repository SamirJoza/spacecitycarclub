<?php

/**
 * File: app/Support/Woo/EventBookingsBladeOverride.php
 *
 * Purpose
 * -----------------------------------------------------------------------------
 * Replace the Event Booking Manager for WooCommerce (mage-eventpress) output for
 * the "event-bookings" My Account endpoint with a Blade-rendered wrapper.
 *
 * Why this exists
 * -----------------------------------------------------------------------------
 * The plugin registers its My Account output via:
 *   add_action('woocommerce_account_event-bookings_endpoint', [$this, 'event_bookings_content']);
 *
 * That means WooCommerce is NOT loading a theme template for this endpoint.
 * Therefore, creating Blade templates alone has no effect.
 *
 * Our approach (safe, minimal, theme-contained)
 * -----------------------------------------------------------------------------
 * 1) Locate the plugin callback inside $wp_filter for the endpoint hook.
 * 2) Remove ONLY that callback.
 * 3) Call the original callback ourselves (capturing HTML via output buffer).
 * 4) Render a Blade view that wraps the captured HTML and applies scoped CSS.
 *
 * Self-registration (your request)
 * -----------------------------------------------------------------------------
 * This file self-registers when required.
 * That means in setup.php you only need:
 *   require_once get_theme_file_path('app/Support/Woo/EventBookingsBladeOverride.php');
 *
 * Rules
 * -----------------------------------------------------------------------------
 * - No behavior changes: we still run the plugin’s original output.
 * - Styling-only: we only wrap + style.
 * - Theme-contained: loaded via require_once get_theme_file_path(...).
 */

namespace App\Support\Woo;

class EventBookingsBladeOverride
{
    /**
     * Hook this once during theme boot.
     */
    public static function register(): void
    {
        // wp_loaded ensures plugins have registered their endpoint callbacks.
        add_action('wp_loaded', [self::class, 'swapEndpointRenderer'], 20);
    }

    /**
     * Replace the plugin endpoint callback with our Blade wrapper.
     */
    public static function swapEndpointRenderer(): void
    {
        $hook = 'woocommerce_account_event-bookings_endpoint';

        if (!function_exists('has_action') || !has_action($hook)) {
            return;
        }

        global $wp_filter;

        if (empty($wp_filter[$hook])) {
            return;
        }

        $wpHook = $wp_filter[$hook];

        // WP stores callbacks differently across versions; WP_Hook is typical.
        $callbacks = is_object($wpHook) && property_exists($wpHook, 'callbacks')
            ? $wpHook->callbacks
            : (is_array($wpHook) ? $wpHook : []);

        if (empty($callbacks) || !is_array($callbacks)) {
            return;
        }

        $originalCallback = null;

        // Find and remove ONLY: MPWEM_My_Account_Dashboard::event_bookings_content
        foreach ($callbacks as $priority => $items) {
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $data) {
                $fn = $data['function'] ?? null;

                // Looking for: [object, 'event_bookings_content'] where object is MPWEM_My_Account_Dashboard
                if (
                    is_array($fn)
                    && isset($fn[0], $fn[1])
                    && is_object($fn[0])
                    && is_string($fn[1])
                    && $fn[1] === 'event_bookings_content'
                    && ($fn[0] instanceof \MPWEM_My_Account_Dashboard)
                ) {
                    $originalCallback = $fn;

                    // Remove the plugin callback at its exact priority using the same callable.
                    remove_action($hook, $fn, (int) $priority);
                }
            }
        }

        // If we didn't find the plugin callback, bail (we don't guess).
        if (!$originalCallback) {
            return;
        }

        /**
         * Add our renderer.
         *
         * We:
         * - run the original plugin callback to preserve behavior
         * - capture its output HTML
         * - render it inside a Blade view for styling control
         */
        add_action($hook, static function () use ($originalCallback) {
            ob_start();

            try {
                call_user_func($originalCallback);
            } catch (\Throwable $e) {
                // Fail-safe: don't white-screen the account page.
                echo '<div class="woocommerce-error" role="alert">Sorry, the Event Bookings section could not be displayed.</div>';
            }

            $pluginHtml = (string) ob_get_clean();

            // Render Blade wrapper. Sage/Acorn provides the view() helper.
            if (function_exists('view')) {
                echo view('woocommerce.myaccount.event-bookings', [
                    'plugin_html' => $pluginHtml,
                ])->render();

                return;
            }

            // Ultra-safe fallback: output raw plugin HTML if Blade isn't available.
            echo $pluginHtml;
        }, 10);
    }
}

/**
 * Bootstrap (self-register)
 * -----------------------------------------------------------------------------
 * This runs immediately when the file is required, so setup.php only needs the
 * require_once line (per your preference).
 */
EventBookingsBladeOverride::register();
