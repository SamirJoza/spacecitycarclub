<?php
/**
 * app/Support/Media/ForceHttpsMediaUrls.php
 * File: app/Support/Media/ForceHttpsMediaUrls.php
 *
 * Purpose
 * ------------------------------------------------------------------------------
 * Fix mixed-content issues where media/avatar URLs are generated as http:// while
 * the site is served over https://.
 *
 * What this does
 * ------------------------------------------------------------------------------
 * When the site is effectively using HTTPS, this forces HTTPS for:
 * - Upload base URLs (upload_dir)
 * - Attachment URLs (wp_get_attachment_url)
 * - Attachment image src arrays (wp_get_attachment_image_src)
 * - Image srcset sources (wp_calculate_image_srcset)
 * - Avatar URLs (get_avatar_url)
 *
 * IMPORTANT (PHP 8.4 compatibility)
 * ------------------------------------------------------------------------------
 * WordPress filter callbacks may pass numeric values as strings (e.g. attachment ID).
 * Do NOT strict-type those filter parameters as int, or PHP will throw TypeError.
 *
 * Docs (hooks/functions)
 * ------------------------------------------------------------------------------
 * - upload_dir: https://developer.wordpress.org/reference/hooks/upload_dir/
 * - wp_get_attachment_url: https://developer.wordpress.org/reference/hooks/wp_get_attachment_url/
 * - wp_get_attachment_image_src: https://developer.wordpress.org/reference/hooks/wp_get_attachment_image_src/
 * - wp_calculate_image_srcset: https://developer.wordpress.org/reference/hooks/wp_calculate_image_srcset/
 * - get_avatar_url: https://developer.wordpress.org/reference/hooks/get_avatar_url/
 * - set_url_scheme(): https://developer.wordpress.org/reference/functions/set_url_scheme/
 */

namespace App\Support\Media;

final class ForceHttpsMediaUrls
{
    public static function boot(): void
    {
        add_filter('upload_dir', [__CLASS__, 'filterUploadDir'], 10, 1);
        add_filter('wp_get_attachment_url', [__CLASS__, 'filterAttachmentUrl'], 10, 2);
        add_filter('wp_get_attachment_image_src', [__CLASS__, 'filterAttachmentImageSrc'], 10, 4);
        add_filter('wp_calculate_image_srcset', [__CLASS__, 'filterSrcsetSources'], 10, 5);
        add_filter('get_avatar_url', [__CLASS__, 'filterAvatarUrl'], 10, 3);
    }

    /**
     * upload_dir filter: force https on baseurl/url.
     */
    public static function filterUploadDir(array $uploads): array
    {
        if (!self::shouldForceHttps()) {
            return $uploads;
        }

        if (!empty($uploads['url'])) {
            $uploads['url'] = self::forceHttps((string) $uploads['url']);
        }

        if (!empty($uploads['baseurl'])) {
            $uploads['baseurl'] = self::forceHttps((string) $uploads['baseurl']);
        }

        return $uploads;
    }

    /**
     * wp_get_attachment_url filter: force https on individual attachment URLs.
     *
     * NOTE: $postId may be passed as string by core.
     *
     * @param mixed $postId
     */
    public static function filterAttachmentUrl(string $url, $postId): string
    {
        if (!self::shouldForceHttps()) {
            return $url;
        }

        return self::forceHttps($url);
    }

    /**
     * wp_get_attachment_image_src filter: force https on the returned src array.
     *
     * NOTE: $attachmentId may be passed as string by core.
     *
     * @param array|false $image
     * @param mixed       $attachmentId
     * @param mixed       $size
     */
    public static function filterAttachmentImageSrc($image, $attachmentId, $size, bool $icon)
    {
        if (!self::shouldForceHttps()) {
            return $image;
        }

        if (!is_array($image) || empty($image[0])) {
            return $image;
        }

        $image[0] = self::forceHttps((string) $image[0]);

        return $image;
    }

    /**
     * wp_calculate_image_srcset filter: force https on all srcset source URLs.
     *
     * NOTE: $attachmentId may be passed as string by core.
     *
     * @param mixed $attachmentId
     */
    public static function filterSrcsetSources(array $sources, array $sizeArray, string $imageSrc, array $imageMeta, $attachmentId): array
    {
        if (!self::shouldForceHttps()) {
            return $sources;
        }

        foreach ($sources as $key => $source) {
            if (!is_array($source) || empty($source['url'])) {
                continue;
            }

            $sources[$key]['url'] = self::forceHttps((string) $source['url']);
        }

        return $sources;
    }

    /**
     * get_avatar_url filter: force https on avatar URLs (covers local avatars + gravatar).
     *
     * @param mixed $idOrEmail
     */
    public static function filterAvatarUrl(string $url, $idOrEmail, array $args): string
    {
        if (!self::shouldForceHttps()) {
            return $url;
        }

        return self::forceHttps($url);
    }

    /**
     * Decide whether we should force HTTPS output.
     *
     * We prefer wp_is_using_https() when available, otherwise we fall back to
     * checking is_ssl() and the configured home/site URL schemes.
     */
    private static function shouldForceHttps(): bool
    {
        if (function_exists('wp_is_using_https')) {
            return (bool) wp_is_using_https();
        }

        $homeScheme = wp_parse_url(home_url(), PHP_URL_SCHEME);
        $siteScheme = wp_parse_url(site_url(), PHP_URL_SCHEME);

        return is_ssl() || $homeScheme === 'https' || $siteScheme === 'https';
    }

    /**
     * Convert absolute URLs to https:// when needed.
     * Leaves relative URLs untouched.
     */
    private static function forceHttps(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return $url;
        }

        // Relative URL ("/wp-content/...") — do not modify.
        if (str_starts_with($url, '/')) {
            return $url;
        }

        // Protocol-relative ("//example.com/...") — make it explicit.
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        // Only adjust http(s) URLs — leave other schemes alone (data:, mailto:, etc).
        $scheme = wp_parse_url($url, PHP_URL_SCHEME);
        if ($scheme === 'http' || $scheme === 'https') {
            return set_url_scheme($url, 'https');
        }

        return $url;
    }
}

ForceHttpsMediaUrls::boot();