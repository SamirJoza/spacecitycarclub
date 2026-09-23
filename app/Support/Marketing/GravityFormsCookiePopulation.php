<?php

declare(strict_types=1);

/**
 * Gravity Forms: populate dynamic-parameter fields from URL, then cookie, else field default.
 *
 * In GF: enable “Allow field to be populated dynamically” and set **Parameter Name** to a
 * key in the map (e.g. `referral_source`). Order: GF’s URL value for that name → optional
 * extra `query_keys` → `cookie` → empty string (GF keeps the field’s default).
 *
 * Add more keys in one of two ways:
 *
 * 1) Theme code — extend {@see defaultMap()}:
 *
 *     'campaign_id' => [
 *         'cookie'     => 'sccc_campaign',
 *         'query_keys' => ['utm_campaign'], // try if primary GET param is empty
 *     ],
 *
 * 2) Any plugin or small mu-plugin — filter `sccc_gf_cookie_population_map` and merge/append.
 */

namespace App\Support\Marketing;

if (! defined('ABSPATH')) {
    exit;
}

final class GravityFormsCookiePopulation
{
    private const MAX_LEN = 200;

    public static function register(): void
    {
        add_action('init', static function (): void {
            if (! class_exists('GFForms')) {
                return;
            }

            add_filter('gform_field_value', [self::class, 'filterGformFieldValue'], 10, 3);
        }, 20);
    }

    /**
     * Default GF parameter name => cookie + optional extra GET keys.
     *
     * The GF “Parameter Name” is the primary GET key Gravity Forms reads first; you only
     * need query_keys when the URL uses a different name than the GF parameter.
     *
     * @return array<string, array{cookie?: string, query_keys?: string[]}>
     */
    public static function defaultMap(): array
    {
        return [
            'referral_source' => [
                'cookie' => 'sccc_referral_source',
                'query_keys' => [],
            ],
        ];
    }

    /**
     * @return array<string, array{cookie?: string, query_keys?: string[]}>
     */
    public static function map(): array
    {
        $map = self::defaultMap();

        if (! is_array($map)) {
            $map = [];
        }

        /**
         * Filters which GF dynamic-population parameter names get URL → cookie → default resolution.
         *
         * Keys must match the field’s Parameter Name in Gravity Forms. Each value may include:
         * - cookie: (string) $_COOKIE name
         * - query_keys: (string[]) extra GET parameter names to try after GF’s primary $value is empty
         *
         * @param array<string, array<string, mixed>> $map
         */
        $filtered = apply_filters('sccc_gf_cookie_population_map', $map);

        return is_array($filtered) ? $filtered : $map;
    }

    /**
     * @param mixed $value
     * @param mixed $field
     * @param mixed $name
     * @return mixed
     */
    public static function filterGformFieldValue($value, $field, $name)
    {
        if (! is_string($name) || $name === '') {
            return $value;
        }

        if (is_array($value)) {
            return $value;
        }

        $map = self::map();
        if (! isset($map[$name]) || ! is_array($map[$name])) {
            return $value;
        }

        $cfg = $map[$name];
        $cookie = isset($cfg['cookie']) && is_string($cfg['cookie']) ? trim($cfg['cookie']) : '';
        $extraKeys = [];
        if (isset($cfg['query_keys']) && is_array($cfg['query_keys'])) {
            foreach ($cfg['query_keys'] as $k) {
                if (is_string($k) && trim($k) !== '') {
                    $extraKeys[] = trim($k);
                }
            }
        }

        $primary = self::cleanScalar(is_scalar($value) ? (string) $value : '');

        $resolved = self::resolveFromQueryAndCookie($extraKeys, $cookie, $primary);

        return $resolved === '' ? '' : $resolved;
    }

    /**
     * @param string[] $extraQueryKeys  Tried only when $primaryValue is empty (GF already applied main param name).
     * @param string   $cookieName
     * @param string   $primaryValue    Value GF already read for the field’s parameter name (usually from URL).
     */
    private static function resolveFromQueryAndCookie(array $extraQueryKeys, string $cookieName, string $primaryValue): string
    {
        $v = self::cleanScalar($primaryValue);
        if ($v !== '') {
            return $v;
        }

        foreach ($extraQueryKeys as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            $q = self::readGet($key);
            if ($q !== '') {
                return $q;
            }
        }

        if ($cookieName !== '') {
            $c = self::readCookie($cookieName);
            if ($c !== '') {
                return $c;
            }
        }

        return '';
    }

    private static function readGet(string $key): string
    {
        $key = sanitize_key($key);
        if ($key === '' || ! isset($_GET[$key])) {
            return '';
        }

        return self::cleanScalar((string) wp_unslash($_GET[$key]));
    }

    private static function readCookie(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $name) ?? '';
        if ($name === '' || ! isset($_COOKIE[$name])) {
            return '';
        }

        return self::cleanScalar((string) wp_unslash($_COOKIE[$name]));
    }

    private static function cleanScalar(string $value): string
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
}

GravityFormsCookiePopulation::register();
