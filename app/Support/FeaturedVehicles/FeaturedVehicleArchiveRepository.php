<?php

namespace App\Support\FeaturedVehicles;

use DateTimeImmutable;
use DateTimeZone;
use WP_Post;

/**
 * File: app/Support/FeaturedVehicles/FeaturedVehicleArchiveRepository.php
 *
 * Featured Vehicle Archive Repository
 * ==================================
 *
 * PURPOSE
 * -------
 * This class reads the saved monthly Featured Vehicle archive records and maps
 * them into a clean, block-friendly data shape.
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * The resolver's job is to:
 * - determine the monthly winner
 * - create the archive CPT record
 * - save source IDs + snapshot metadata
 *
 * The block's job is to:
 * - render a vehicle feature card
 * - show the owner modal
 * - stay focused on presentation
 *
 * This repository sits between those two layers so the block does NOT need to:
 * - know the archive CPT slug
 * - know the meta key names
 * - read raw post meta directly
 * - understand archive storage details
 *
 * PERFORMANCE STRATEGY
 * --------------------
 * To keep block/page renders light, this repository uses a read-first approach:
 *
 * 1. Try a transient cache for the current month.
 * 2. If missing, try the saved archive CPT record for that month.
 * 3. If still missing, ask the resolver to create the month once.
 * 4. Cache the result until the start of the next month.
 *
 * This means:
 * - normal page loads are usually just one transient read
 * - archive lookup only happens on cache miss
 * - the heavier resolver logic only runs when a new month does not yet exist
 *
 * MEMBER TENURE
 * -------------
 * The resolver now snapshots:
 * - member_since_raw
 * - member_since_year
 *
 * This repository passes those values through to the block/modal data so the
 * Blade template can simply render "Member since: YYYY" without doing data logic.
 *
 * IMPORTANT
 * ---------
 * This file does NOT register WordPress hooks.
 * It is a plain service/repository class and does not need to boot itself.
 *
 * USAGE EXAMPLES
 * --------------
 * Get the current month's saved archive post:
 *   FeaturedVehicleArchiveRepository::getCurrentMonthArchivePost()
 *
 * Get the current month's block-ready data:
 *   FeaturedVehicleArchiveRepository::getCurrentMonthFeatureData()
 *
 * Get block-ready data from a known archive post:
 *   FeaturedVehicleArchiveRepository::mapArchivePostToFeatureData($post)
 */
final class FeaturedVehicleArchiveRepository
{
    /**
     * Transient key version.
     *
     * WHY:
     * Bumping this invalidates old cached payloads after structure changes,
     * such as adding member tenure to the modal data.
     */
    protected const TRANSIENT_VERSION = 'v2';

    /**
     * Transient prefixes.
     *
     * WHY:
     * Keeps keys short, predictable, and safely under transient name limits.
     */
    protected const TRANSIENT_POST_PREFIX = 'sccc_fv_post_';
    protected const TRANSIENT_DATA_PREFIX = 'sccc_fv_data_';

    /**
     * Minimum cache lifetime in seconds.
     *
     * WHY:
     * Protect against any edge case where date math would otherwise produce
     * a zero or negative duration around a month boundary.
     */
    protected const MIN_CACHE_TTL = 300;

    /**
     * Get the current month's saved archive post, creating it if needed.
     *
     * PERFORMANCE
     * -----------
     * This method first tries a transient containing the archive post ID for
     * the current month. If that fails, it looks up the archive record and only
     * falls back to resolver creation when that month does not exist yet.
     */
    public static function getCurrentMonthArchivePost(): ?WP_Post
    {
        $monthKey = self::getCurrentMonthKey();

        if ($monthKey === '') {
            return null;
        }

        $transientKey = self::getPostTransientKey($monthKey);
        $cachedPostId = get_transient($transientKey);

        if (is_numeric($cachedPostId) && (int) $cachedPostId > 0) {
            $post = get_post((int) $cachedPostId);

            if ($post instanceof WP_Post && $post->post_type === RegisterFeaturedVehiclePostType::POST_TYPE) {
                return $post;
            }

            /**
             * The cached post ID is stale or invalid.
             * Clear it so the repository can rebuild cleanly.
             */
            delete_transient($transientKey);
        }

        /**
         * Archive-first lookup:
         * prefer an existing saved monthly record before invoking the resolver.
         */
        $post = FeaturedVehicleResolver::findArchiveByMonth($monthKey);

        if (!$post instanceof WP_Post) {
            $post = FeaturedVehicleResolver::getOrCreateArchiveForMonth($monthKey);
        }

        if ($post instanceof WP_Post) {
            set_transient(
                $transientKey,
                (int) $post->ID,
                self::getSecondsUntilNextMonth()
            );
        }

        return $post instanceof WP_Post ? $post : null;
    }

    /**
     * Get block-ready feature data for the current month.
     *
     * RETURN
     * ------
     * - array when a current monthly archive exists or can be created
     * - null when no eligible monthly feature can be resolved
     *
     * PERFORMANCE
     * -----------
     * This is the preferred block-facing entry point.
     * It caches the final mapped data array, which is cheaper for repeated block
     * renders than re-reading and re-mapping all archive fields each time.
     */
    public static function getCurrentMonthFeatureData(): ?array
    {
        $monthKey = self::getCurrentMonthKey();

        if ($monthKey === '') {
            return null;
        }

        $transientKey = self::getDataTransientKey($monthKey);
        $cachedData   = get_transient($transientKey);

        /**
         * IMPORTANT:
         * get_transient() returns false when the transient is missing/expired.
         * Valid cached payloads are stored as arrays.
         */
        if (is_array($cachedData) && !empty($cachedData)) {
            return $cachedData;
        }

        $post = self::getCurrentMonthArchivePost();

        if (!$post instanceof WP_Post) {
            return null;
        }

        $data = self::mapArchivePostToFeatureData($post);

        if (is_array($data) && !empty($data)) {
            set_transient(
                $transientKey,
                $data,
                self::getSecondsUntilNextMonth()
            );
        }

        return $data;
    }

    /**
     * Get a specific archive post by its post ID and map it to block-ready data.
     *
     * WHY:
     * Useful for future admin previews, historical archive pages, or manual
     * "show this month's feature record" tools.
     *
     * NOTE:
     * This method does not use transients because it is not the hot path for
     * normal front-end rendering.
     */
    public static function getFeatureDataByArchivePostId(int $postId): ?array
    {
        if ($postId < 1) {
            return null;
        }

        $post = get_post($postId);

        if (!$post instanceof WP_Post) {
            return null;
        }

        if ($post->post_type !== RegisterFeaturedVehiclePostType::POST_TYPE) {
            return null;
        }

        return self::mapArchivePostToFeatureData($post);
    }

    /**
     * Clear the current month's cache.
     *
     * WHY:
     * Useful for future admin tools, manual regeneration, or debug workflows.
     */
    public static function clearCurrentMonthCache(): void
    {
        $monthKey = self::getCurrentMonthKey();

        if ($monthKey === '') {
            return;
        }

        self::clearCacheForMonth($monthKey);
    }

    /**
     * Clear the cached post ID + mapped data for a specific month.
     *
     * EXPECTED MONTH KEY FORMAT
     * -------------------------
     * YYYY-MM
     */
    public static function clearCacheForMonth(string $monthKey): void
    {
        $monthKey = self::normalizeMonthKey($monthKey);

        if ($monthKey === '') {
            return;
        }

        delete_transient(self::getPostTransientKey($monthKey));
        delete_transient(self::getDataTransientKey($monthKey));
    }

    /**
     * Map a saved archive post into the shape expected by the current feature block.
     *
     * RETURN SHAPE
     * ------------
     * [
     *   'archive' => [...],
     *   'monthYear' => 'March 2026',
     *   'imageId' => 123,
     *   'member' => [...],
     *   'ownerModal' => [...],
     *   'vehicleData' => [...],
     * ]
     *
     * WHY THIS SHAPE
     * --------------
     * It mirrors the current vehicle feature block's needs as closely as possible,
     * so the eventual block update can stay small and focused.
     */
    public static function mapArchivePostToFeatureData(WP_Post $post): ?array
    {
        if ($post->post_type !== RegisterFeaturedVehiclePostType::POST_TYPE) {
            return null;
        }

        $archivePostId = (int) $post->ID;

        /**
         * Archive identity / time snapshot
         */
        $monthKey   = trim((string) get_post_meta($archivePostId, FeaturedVehicleResolver::META_FEATURED_MONTH_KEY, true));
        $monthLabel = trim((string) get_post_meta($archivePostId, FeaturedVehicleResolver::META_FEATURED_MONTH_LABEL, true));
        $year       = (int) get_post_meta($archivePostId, FeaturedVehicleResolver::META_FEATURED_YEAR, true);

        /**
         * Source references
         */
        $sourceUserId    = (int) get_post_meta($archivePostId, FeaturedVehicleResolver::META_SOURCE_USER_ID, true);
        $sourceVehicleId = strtoupper(trim((string) get_post_meta($archivePostId, FeaturedVehicleResolver::META_SOURCE_VEHICLE_ID, true)));

        /**
         * Member snapshot
         */
        $displayName     = trim((string) get_post_meta($archivePostId, FeaturedVehicleResolver::META_OWNER_DISPLAY_NAME, true));
        $username        = trim((string) get_post_meta($archivePostId, FeaturedVehicleResolver::META_OWNER_USERNAME, true));
        $bio             = trim((string) get_post_meta($archivePostId, FeaturedVehicleResolver::META_OWNER_BIO, true));
        $profileImageId  = (int) get_post_meta($archivePostId, FeaturedVehicleResolver::META_OWNER_PROFILE_IMAGE_ID, true);
        $profileImageUrl = trim((string) get_post_meta($archivePostId, FeaturedVehicleResolver::META_OWNER_PROFILE_IMAGE_URL, true));
        $memberNumber    = trim((string) get_post_meta($archivePostId, FeaturedVehicleResolver::META_MEMBER_NUMBER, true));

        /**
         * Member tenure snapshot
         *
         * FALLBACK STRATEGY
         * -----------------
         * Newer archive records should have both:
         * - member_since_raw
         * - member_since_year
         *
         * If an older archive record only has the raw value, derive the year here.
         */
        $memberSinceRaw  = trim((string) get_post_meta($archivePostId, FeaturedVehicleResolver::META_MEMBER_SINCE_RAW, true));
        $memberSinceYear = (int) get_post_meta($archivePostId, FeaturedVehicleResolver::META_MEMBER_SINCE_YEAR, true);

        if ($memberSinceYear < 1 && $memberSinceRaw !== '') {
            $memberSinceYear = self::extractYearFromDatetime($memberSinceRaw);
        }

        /**
         * Vehicle snapshot
         */
        $vehicleYear     = trim((string) get_post_meta($archivePostId, FeaturedVehicleResolver::META_VEHICLE_YEAR, true));
        $vehicleMake     = trim((string) get_post_meta($archivePostId, FeaturedVehicleResolver::META_VEHICLE_MAKE, true));
        $vehicleModel    = trim((string) get_post_meta($archivePostId, FeaturedVehicleResolver::META_VEHICLE_MODEL, true));
        $vehicleNickname = trim((string) get_post_meta($archivePostId, FeaturedVehicleResolver::META_VEHICLE_NICKNAME, true));
        $vehicleNotes    = trim((string) get_post_meta($archivePostId, FeaturedVehicleResolver::META_VEHICLE_NOTES, true));
        $vehicleImageId  = (int) get_post_meta($archivePostId, FeaturedVehicleResolver::META_VEHICLE_IMAGE_ID, true);

        /**
         * Prefer the archive post thumbnail when present.
         *
         * WHY:
         * The resolver mirrors the chosen vehicle image into the archive post thumbnail,
         * so this gives us one canonical visual for the archive record.
         *
         * FALLBACK:
         * If the post thumbnail is missing for any reason, fall back to the saved
         * vehicle image snapshot ID.
         */
        $postThumbnailId = (int) get_post_thumbnail_id($archivePostId);
        $finalImageId    = $postThumbnailId > 0 ? $postThumbnailId : $vehicleImageId;

        /**
         * Build the block-friendly member object.
         *
         * WHY:
         * The current feature block expects a simplified member array.
         */
        $member = [
            'id'                => $sourceUserId,
            'name'              => $displayName,
            'display_name'      => $displayName,
            'user_login'        => $username,
            'member_number'     => $memberNumber,
            'member_since_raw'  => $memberSinceRaw,
            'member_since_year' => $memberSinceYear,
        ];

        /**
         * Build the owner modal object.
         *
         * WHY:
         * This mirrors the modal structure already used by the current block.
         */
        $ownerModal = [
            'user_id'           => $sourceUserId,
            'display_name'      => $displayName,
            'username'          => $username,
            'bio'               => $bio,
            'profile_image'     => $profileImageUrl,
            'profile_image_id'  => $profileImageId,
            'member_number'     => $memberNumber,
            'member_since_raw'  => $memberSinceRaw,
            'member_since_year' => $memberSinceYear,
        ];

        /**
         * Build the vehicleData object expected by the current feature card.
         *
         * IMPORTANT:
         * We keep "make_raw" here to match the current block naming convention.
         */
        $vehicleData = [
            'vehicle_id'  => $sourceVehicleId,
            'year'        => $vehicleYear,
            'make_raw'    => $vehicleMake,
            'model'       => $vehicleModel,
            'nickname'    => $vehicleNickname,
            'notes'       => $vehicleNotes,
            'image_id'    => $vehicleImageId,
            'member_id'   => $sourceUserId,
            'member_name' => $displayName,
        ];

        /**
         * Archive/debug info
         *
         * WHY:
         * This gives the calling layer access to the underlying monthly archive record
         * without forcing the block to understand every meta key itself.
         */
        $archive = [
            'post_id'                       => $archivePostId,
            'post_title'                    => (string) $post->post_title,
            'month_key'                     => $monthKey,
            'month_label'                   => $monthLabel,
            'year'                          => $year,
            'source_user_id'                => $sourceUserId,
            'source_vehicle_id'             => $sourceVehicleId,
            'member_since_raw'              => $memberSinceRaw,
            'member_since_year'             => $memberSinceYear,
            'selection_member_pool_count'   => (int) get_post_meta($archivePostId, FeaturedVehicleResolver::META_SELECTION_MEMBER_POOL_COUNT, true),
            'selection_vehicle_pool_count'  => (int) get_post_meta($archivePostId, FeaturedVehicleResolver::META_SELECTION_VEHICLE_POOL_COUNT, true),
            'selection_algorithm_version'   => trim((string) get_post_meta($archivePostId, FeaturedVehicleResolver::META_SELECTION_ALGO_VERSION, true)),
            'generated_at'                  => trim((string) get_post_meta($archivePostId, FeaturedVehicleResolver::META_GENERATED_AT, true)),
        ];

        return [
            'archive'     => $archive,
            'monthYear'   => $monthLabel !== '' ? $monthLabel : (string) $post->post_title,
            'imageId'     => $finalImageId,
            'member'      => $member,
            'ownerModal'  => $ownerModal,
            'vehicleData' => $vehicleData,
        ];
    }

    /**
     * Build the transient key for the current month's cached archive post ID.
     */
    protected static function getPostTransientKey(string $monthKey): string
    {
        return self::TRANSIENT_POST_PREFIX . self::TRANSIENT_VERSION . '_' . str_replace('-', '_', $monthKey);
    }

    /**
     * Build the transient key for the current month's cached mapped feature data.
     */
    protected static function getDataTransientKey(string $monthKey): string
    {
        return self::TRANSIENT_DATA_PREFIX . self::TRANSIENT_VERSION . '_' . str_replace('-', '_', $monthKey);
    }

    /**
     * Get the current site month key in YYYY-MM format.
     *
     * WHY:
     * This uses WordPress site time so month rollover follows the site's timezone,
     * not the server's raw timezone.
     */
    protected static function getCurrentMonthKey(): string
    {
        return self::normalizeMonthKey((string) current_time('Y-m'));
    }

    /**
     * Normalize and validate a month key.
     *
     * ACCEPTS
     * -------
     * YYYY-MM only.
     */
    protected static function normalizeMonthKey(string $monthKey): string
    {
        $monthKey = trim($monthKey);

        return preg_match('/^\d{4}-\d{2}$/', $monthKey) ? $monthKey : '';
    }

    /**
     * Get the number of seconds from "now" until the start of next month,
     * using the site's timezone.
     *
     * WHY:
     * This makes the transient naturally roll over when the month changes,
     * so April cache entries do not survive into May.
     */
    protected static function getSecondsUntilNextMonth(): int
    {
        $timezone = function_exists('wp_timezone')
            ? wp_timezone()
            : new DateTimeZone('UTC');

        $nowString = (string) current_time('mysql');
        $now = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $nowString, $timezone);

        if (!$now instanceof DateTimeImmutable) {
            return MONTH_IN_SECONDS;
        }

        $nextMonth = $now
            ->modify('first day of next month')
            ->setTime(0, 0, 0);

        $seconds = $nextMonth->getTimestamp() - $now->getTimestamp();

        return max(self::MIN_CACHE_TTL, $seconds);
    }

    /**
     * Extract a four-digit year from a mysql-ish datetime string.
     *
     * RETURN
     * ------
     * - integer year when parsing succeeds
     * - 0 when the value is empty or invalid
     *
     * WHY:
     * Older archive records may only have member_since_raw and not the already
     * derived member_since_year snapshot.
     */
    protected static function extractYearFromDatetime(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $timestamp = strtotime($value);

        if (!$timestamp) {
            return 0;
        }

        return (int) date('Y', $timestamp);
    }
}