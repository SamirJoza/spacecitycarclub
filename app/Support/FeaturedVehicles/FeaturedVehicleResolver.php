<?php

namespace App\Support\FeaturedVehicles;

use DateTimeImmutable;
use WP_Error;
use WP_Post;
use WP_User;

/**
 * File: app/Support/FeaturedVehicles/FeaturedVehicleResolver.php
 *
 * Monthly Featured Vehicle Resolver
 * =================================
 *
 * PURPOSE
 * -------
 * This service resolves one featured member + one featured vehicle for a given month,
 * writes the result into the Featured Vehicle archive CPT, and returns that saved record.
 *
 * CORE BUSINESS RULES
 * -------------------
 * 1. One member is featured for the month.
 * 2. That member must be eligible:
 *    - active
 *    - in the "sccc_member" group/role (default assumption)
 *    - has at least one vehicle with an image
 * 3. Members rotate fairly:
 *    - no repeat within the current cycle when possible
 *    - if the pool is exhausted, the cycle resets
 *    - avoid back-to-back months for the same member when possible
 * 4. One vehicle is chosen from the selected member's eligible vehicles:
 *    - prefer a vehicle that has not been used for that member yet
 *    - if all that member's vehicles have already been used, allow reuse
 * 5. Once chosen, the result is SAVED into the archive CPT for that month.
 *    Every visitor then sees the same saved result for the entire month.
 *
 * IMPORTANT ASSUMPTION
 * --------------------
 * This first version assumes "belonging to the sccc_member group" means the user
 * has a WordPress role of "sccc_member".
 *
 * WHY THIS IS SAFE FOR NOW
 * ------------------------
 * - It matches the user-facing requirement as closely as possible without guessing
 *   an unknown custom group implementation.
 * - It is filterable in one place if your project later needs to swap this to a
 *   custom membership flag, PMPro rule, taxonomy, or other membership source.
 *
 * MEMBER TENURE SNAPSHOT
 * ----------------------
 * This version snapshots member tenure from the user meta key:
 * - membership_issued_at
 *
 * Based on your MemberProfile field registration, that value is stored as a mysql datetime
 * and is auto-generated when a membership number is issued.
 *
 * We store both:
 * - member_since_raw  (the original datetime string)
 * - member_since_year (derived YYYY value for modal display)
 *
 * SOURCE OF TRUTH
 * ---------------
 * The archive CPT is the source of truth for each month.
 *
 * DISPLAY FLOW
 * ------------
 * - If a record for the current month already exists, use it.
 * - If not, build the pool, choose the winner, create the archive record, and use that.
 *
 * PROJECT NOTE
 * ------------
 * This class is intentionally theme-contained and stateless.
 * It does not register hooks on its own.
 * The block/template layer can call it directly.
 */
final class FeaturedVehicleResolver
{
    /**
     * Bump this if the selection rules materially change.
     *
     * WHY:
     * Stored with each archive record so later you can see which logic version
     * created a given month's winner.
     */
    public const ALGORITHM_VERSION = '1.2.0';

    /**
     * Default role/group assumption for "eligible members".
     *
     * IMPORTANT:
     * This can be overridden via the eligible user query args filter if your
     * implementation uses something other than a WordPress role.
     */
    public const DEFAULT_MEMBER_ROLE = 'sccc_member';

    /**
     * Month/record meta keys.
     *
     * WHY:
     * Centralized constants reduce typo risk and keep the CPT columns + resolver aligned.
     */
    public const META_FEATURED_MONTH_KEY            = 'featured_month_key';
    public const META_FEATURED_MONTH_LABEL          = 'featured_month_label';
    public const META_FEATURED_YEAR                 = 'featured_year';
    public const META_SOURCE_USER_ID                = 'source_user_id';
    public const META_SOURCE_VEHICLE_ID             = 'source_vehicle_id';
    public const META_SOURCE_VEHICLE_KEY            = 'source_vehicle_key';
    public const META_OWNER_DISPLAY_NAME            = 'owner_display_name_snapshot';
    public const META_OWNER_USERNAME                = 'owner_username_snapshot';
    public const META_OWNER_BIO                     = 'owner_bio_snapshot';
    public const META_OWNER_PROFILE_IMAGE_ID        = 'owner_profile_image_id_snapshot';
    public const META_OWNER_PROFILE_IMAGE_URL       = 'owner_profile_image_url_snapshot';
    public const META_MEMBER_NUMBER                 = 'member_number_snapshot';
    public const META_MEMBER_SINCE_RAW              = 'member_since_raw';
    public const META_MEMBER_SINCE_YEAR             = 'member_since_year';
    public const META_VEHICLE_YEAR                  = 'vehicle_year_snapshot';
    public const META_VEHICLE_MAKE                  = 'vehicle_make_snapshot';
    public const META_VEHICLE_MODEL                 = 'vehicle_model_snapshot';
    public const META_VEHICLE_NICKNAME              = 'vehicle_nickname_snapshot';
    public const META_VEHICLE_NOTES                 = 'vehicle_notes_snapshot';
    public const META_VEHICLE_IMAGE_ID              = 'vehicle_image_id_snapshot';
    public const META_SELECTION_MEMBER_POOL_COUNT   = 'selection_member_pool_count';
    public const META_SELECTION_VEHICLE_POOL_COUNT  = 'selection_vehicle_pool_count';
    public const META_SELECTION_ALGO_VERSION        = 'selection_algorithm_version';
    public const META_GENERATED_AT                  = 'generated_at';

    /**
     * Resolve or create the archive record for the CURRENT site month.
     *
     * RETURN
     * ------
     * - WP_Post when a record exists or was created successfully
     * - null when no eligible winner could be resolved
     */
    public static function getOrCreateCurrentMonthArchive(): ?WP_Post
    {
        return self::getOrCreateArchiveForMonth(self::getCurrentMonthKey());
    }

    /**
     * Force reset the current month's archive record and regenerate it.
     *
     * ADMIN-ONLY INTENT
     * -----------------
     * This method does not perform capability or nonce checks because it is a
     * plain service method. The wp-admin controller that calls this method must
     * verify both authorization and intent before invoking it.
     *
     * RESET STRATEGY
     * --------------
     * 1. Find the current month's saved archive record.
     * 2. Snapshot the previous member and vehicle IDs before removing it.
     * 3. Move the old record to Trash so the monthly slot is free again.
     * 4. Clear cached post/data transients for the current month.
     * 5. Regenerate the month while trying to avoid the same member.
     * 6. If the same member is unavoidable, try to avoid the same vehicle.
     *
     * SAFETY FALLBACK
     * ---------------
     * If an existing archive record was trashed but a replacement cannot be
     * generated, the original record is restored from Trash. This prevents an
     * admin reset click from accidentally leaving the current month empty.
     *
     * RETURN SHAPE
     * ------------
     * [
     *   'success'           => bool,
     *   'message'           => string,
     *   'month_key'         => string,
     *   'month_label'       => string,
     *   'previous_post_id'  => int,
     *   'new_post_id'       => int,
     *   'previous_user_id'  => int,
     *   'new_user_id'       => int,
     *   'previous_vehicle'  => string,
     *   'new_vehicle'       => string,
     *   'same_member'       => bool,
     *   'same_vehicle'      => bool,
     * ]
     */
    public static function forceResetCurrentMonthArchive(): array
    {
        $monthKey = self::getCurrentMonthKey();

        if ($monthKey === '') {
            return self::buildResetResult(false, 'Unable to determine the current month key.');
        }

        $monthLabel = self::getMonthLabelFromMonthKey($monthKey);
        $existing   = self::findArchiveByMonth($monthKey);

        $previousPostId = $existing instanceof WP_Post ? (int) $existing->ID : 0;
        $previousUserId = $previousPostId > 0
            ? (int) get_post_meta($previousPostId, self::META_SOURCE_USER_ID, true)
            : 0;
        $previousVehicleId = $previousPostId > 0
            ? strtoupper(trim((string) get_post_meta($previousPostId, self::META_SOURCE_VEHICLE_ID, true)))
            : '';

        /**
         * No existing record means there is nothing to reset.
         * We still create the current month so the admin action is useful.
         */
        if (!$existing instanceof WP_Post) {
            self::clearArchiveCacheForMonth($monthKey);

            $created = self::getOrCreateArchiveForMonth($monthKey);

            if (!$created instanceof WP_Post) {
                return self::buildResetResult(
                    false,
                    'No current featured vehicle record existed, and no eligible member vehicle could be generated.',
                    $monthKey,
                    $monthLabel
                );
            }

            self::clearArchiveCacheForMonth($monthKey);

            return self::buildResetResult(
                true,
                'No current featured vehicle record existed, so a new one was generated for ' . $monthLabel . '.',
                $monthKey,
                $monthLabel,
                0,
                (int) $created->ID,
                0,
                (int) get_post_meta((int) $created->ID, self::META_SOURCE_USER_ID, true),
                '',
                strtoupper(trim((string) get_post_meta((int) $created->ID, self::META_SOURCE_VEHICLE_ID, true)))
            );
        }

        /**
         * Trash first so findArchiveByMonth() no longer sees the old published
         * monthly record and the replacement can be created cleanly.
         */
        $trashed = wp_trash_post($previousPostId);

        if (!$trashed instanceof WP_Post) {
            return self::buildResetResult(
                false,
                'The current featured vehicle record could not be moved to Trash, so no replacement was generated.',
                $monthKey,
                $monthLabel,
                $previousPostId,
                0,
                $previousUserId,
                0,
                $previousVehicleId,
                ''
            );
        }

        self::clearArchiveCacheForMonth($monthKey);

        $excludedMemberIds  = $previousUserId > 0 ? [$previousUserId] : [];
        $excludedVehicleIds = $previousVehicleId !== '' ? [$previousVehicleId] : [];

        $replacement = self::resolveAndCreateArchiveForMonthWithExclusions(
            $monthKey,
            $excludedMemberIds,
            $excludedVehicleIds
        );

        /**
         * Restore the previous record if regeneration fails.
         * This keeps the front end from losing a current month feature.
         */
        if (!$replacement instanceof WP_Post) {
            wp_untrash_post($previousPostId);
            self::clearArchiveCacheForMonth($monthKey);

            return self::buildResetResult(
                false,
                'The reset could not generate a replacement, so the original featured vehicle record was restored.',
                $monthKey,
                $monthLabel,
                $previousPostId,
                0,
                $previousUserId,
                0,
                $previousVehicleId,
                ''
            );
        }

        self::clearArchiveCacheForMonth($monthKey);

        $newPostId    = (int) $replacement->ID;
        $newUserId    = (int) get_post_meta($newPostId, self::META_SOURCE_USER_ID, true);
        $newVehicleId = strtoupper(trim((string) get_post_meta($newPostId, self::META_SOURCE_VEHICLE_ID, true)));

        $sameMember  = $previousUserId > 0 && $newUserId > 0 && $previousUserId === $newUserId;
        $sameVehicle = $previousVehicleId !== '' && $newVehicleId !== '' && $previousVehicleId === $newVehicleId;

        $message = 'Featured vehicle force reset complete for ' . $monthLabel . '.';

        if ($sameMember && $sameVehicle) {
            $message .= ' The same member and vehicle were reused because no eligible alternative was available.';
        } elseif ($sameMember) {
            $message .= ' The same member was reused because no eligible alternate member was available, but the vehicle was re-rolled when possible.';
        }

        return self::buildResetResult(
            true,
            $message,
            $monthKey,
            $monthLabel,
            $previousPostId,
            $newPostId,
            $previousUserId,
            $newUserId,
            $previousVehicleId,
            $newVehicleId,
            $sameMember,
            $sameVehicle
        );
    }

    /**
     * Resolve or create the archive record for a specific month key.
     *
     * EXPECTED MONTH KEY FORMAT
     * -------------------------
     * YYYY-MM
     * Example: 2026-03
     *
     * WHY THIS EXISTS
     * ---------------
     * Allows future admin/debug tools to regenerate or inspect historical months
     * in a controlled way.
     */
    public static function getOrCreateArchiveForMonth(string $monthKey): ?WP_Post
    {
        $monthKey = self::normalizeMonthKey($monthKey);

        if ($monthKey === '') {
            return null;
        }

        $existing = self::findArchiveByMonth($monthKey);

        if ($existing instanceof WP_Post) {
            return $existing;
        }

        return self::resolveAndCreateArchiveForMonth($monthKey);
    }

    /**
     * Find an existing archive record by month key.
     *
     * WHY:
     * The monthly winner should be saved once and re-used, not recalculated on every request.
     */
    public static function findArchiveByMonth(string $monthKey): ?WP_Post
    {
        $monthKey = self::normalizeMonthKey($monthKey);

        if ($monthKey === '') {
            return null;
        }

        $posts = get_posts([
            'post_type'        => RegisterFeaturedVehiclePostType::POST_TYPE,
            'post_status'      => 'publish',
            'numberposts'      => 1,
            'meta_key'         => self::META_FEATURED_MONTH_KEY,
            'meta_value'       => $monthKey,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'suppress_filters' => false,
        ]);

        return !empty($posts[0]) && $posts[0] instanceof WP_Post ? $posts[0] : null;
    }

    /**
     * Resolve the winner for a month and persist it to the archive CPT.
     *
     * HIGH-LEVEL STEPS
     * ----------------
     * 1. Build the eligible member pool.
     * 2. Determine which members have already been used in the current cycle.
     * 3. Choose one member deterministically for the month.
     * 4. Choose one eligible vehicle for that member.
     * 5. Save a monthly archive record containing source IDs + snapshot data.
     */
    public static function resolveAndCreateArchiveForMonth(string $monthKey): ?WP_Post
    {
        $monthKey = self::normalizeMonthKey($monthKey);

        if ($monthKey === '') {
            return null;
        }

        /**
         * Safety check:
         * If another process created the record between the first lookup and now,
         * prefer the already-saved archive post.
         */
        $existing = self::findArchiveByMonth($monthKey);

        if ($existing instanceof WP_Post) {
            return $existing;
        }

        return self::resolveAndCreateArchiveForMonthWithExclusions($monthKey);
    }

    /**
     * Resolve and create a monthly archive while optionally avoiding previous picks.
     *
     * WHY THIS METHOD EXISTS
     * ----------------------
     * The normal monthly resolver is intentionally deterministic. That is good for
     * scheduled/front-end use because the same month and pool should not keep
     * changing. A manual admin reset is different: when an admin says "force reset,"
     * they usually expect a re-roll. These exclusions give the admin reset a safe
     * way to avoid the previous winner without changing the public resolver rules.
     *
     * EXCLUSION RULES
     * ---------------
     * - Excluded members are skipped only when at least one other eligible member exists.
     * - Excluded vehicles are skipped only when at least one other eligible vehicle exists.
     * - If no alternative exists, the previous value may be reused instead of failing.
     */
    protected static function resolveAndCreateArchiveForMonthWithExclusions(
        string $monthKey,
        array $excludedMemberIds = [],
        array $excludedVehicleIds = []
    ): ?WP_Post {
        $monthKey = self::normalizeMonthKey($monthKey);

        if ($monthKey === '') {
            return null;
        }

        $pool = self::buildEligibleMemberPool();

        if (empty($pool)) {
            return null;
        }

        $excludedMemberIds = array_values(array_unique(array_filter(
            array_map('intval', $excludedMemberIds),
            static fn (int $userId): bool => $userId > 0
        )));

        if (!empty($excludedMemberIds) && count($pool) > 1) {
            $filteredPool = $pool;

            foreach ($excludedMemberIds as $excludedMemberId) {
                unset($filteredPool[$excludedMemberId]);
            }

            if (!empty($filteredPool)) {
                $pool = $filteredPool;
            }
        }

        $selectedMember = self::selectMemberForMonth($pool, $monthKey);

        if (empty($selectedMember)) {
            return null;
        }

        $selectedVehicle = self::selectVehicleForMemberForMonth($selectedMember, $monthKey, $excludedVehicleIds);

        if (empty($selectedVehicle)) {
            return null;
        }

        $postId = self::createArchiveRecord($monthKey, $selectedMember, $selectedVehicle);

        if ($postId instanceof WP_Error || $postId < 1) {
            return null;
        }

        $post = get_post($postId);

        return $post instanceof WP_Post ? $post : null;
    }

    /**
     * Build the current pool of ELIGIBLE MEMBERS.
     *
     * FAIRNESS MODEL
     * --------------
     * We build the pool from members first, NOT from vehicles.
     *
     * WHY:
     * If we built the pool from vehicles first, a member with 4 cars would have
     * 4 chances while a member with 1 car would only have 1 chance.
     *
     * So each eligible member gets ONE chance in the monthly member selection step.
     *
     * RETURN SHAPE
     * ------------
     * [
     *   user_id => [
     *     'user' => WP_User,
     *     'vehicles' => [ ...eligible vehicles with images... ],
     *   ],
     * ]
     */
    protected static function buildEligibleMemberPool(): array
    {
        /**
         * DEFAULT ASSUMPTION
         * ------------------
         * "sccc_member group" is treated as the WordPress role "sccc_member".
         *
         * This is filterable if your project uses a different membership source.
         */
        $queryArgs = [
            'role__in' => [self::DEFAULT_MEMBER_ROLE],
            'orderby'  => 'ID',
            'order'    => 'ASC',
            'fields'   => 'all',
        ];

        $queryArgs = apply_filters('sccc/featured_vehicles/eligible_user_query_args', $queryArgs);

        $users = get_users($queryArgs);

        if (empty($users)) {
            return [];
        }

        $pool = [];

        foreach ($users as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }

            if (!self::userIsEligible($user)) {
                continue;
            }

            $vehicles = self::getEligibleVehiclesForUser((int) $user->ID);

            if (empty($vehicles)) {
                continue;
            }

            $pool[(int) $user->ID] = [
                'user'     => $user,
                'vehicles' => $vehicles,
            ];
        }

        ksort($pool, SORT_NUMERIC);

        return $pool;
    }

    /**
     * Determine whether a user is eligible for the monthly rotation.
     *
     * DEFAULT RULES
     * -------------
     * - valid user ID
     * - not spam/deleted when those properties are present
     * - user_status must be 0 when present
     *
     * FILTERS
     * -------
     * This method is deliberately filterable because "active member" may later
     * need to map to a custom membership source instead of a core user property.
     */
    protected static function userIsEligible(WP_User $user): bool
    {
        if ((int) $user->ID < 1) {
            return false;
        }

        $isActive = true;

        /**
         * Core-ish sanity checks:
         * These are safe defaults and help us avoid obviously inactive/deleted accounts.
         */
        if (isset($user->spam) && (bool) $user->spam) {
            $isActive = false;
        }

        if (isset($user->deleted) && (bool) $user->deleted) {
            $isActive = false;
        }

        if (isset($user->user_status) && (int) $user->user_status !== 0) {
            $isActive = false;
        }

        /**
         * Project override point:
         * If your final implementation uses PMPro, another membership plugin,
         * a custom group, or user meta, you can swap the active check here
         * without changing the resolver architecture.
         */
        return (bool) apply_filters('sccc/featured_vehicles/user_is_active', $isActive, $user);
    }

    /**
     * Get all eligible vehicles for one user.
     *
     * VEHICLE ELIGIBILITY RULES
     * -------------------------
     * - has non-empty vehicle_id
     * - has a valid image attachment ID
     *
     * WHY:
     * The user specifically wanted only members whose cars have images.
     */
    protected static function getEligibleVehiclesForUser(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }

        $rows = function_exists('get_field') ? get_field('vehicles', 'user_' . $userId) : null;

        if (!is_array($rows) || empty($rows)) {
            return [];
        }

        $vehicles = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $vehicleId = strtoupper(trim((string) ($row['vehicle_id'] ?? '')));

            if ($vehicleId === '') {
                continue;
            }

            $imageId = self::normalizeAttachmentId($row['image'] ?? 0);

            if ($imageId < 1) {
                continue;
            }

            $vehicle = [
                'vehicle_id' => $vehicleId,
                'year'       => trim((string) ($row['year'] ?? '')),
                'make_raw'   => trim((string) ($row['make_raw'] ?? '')),
                'model'      => trim((string) ($row['model'] ?? '')),
                'nickname'   => trim((string) ($row['nickname'] ?? '')),
                'notes'      => trim((string) ($row['notes'] ?? '')),
                'image_id'   => $imageId,
            ];

            /**
             * Project override point:
             * lets you exclude a row for custom reasons later, such as:
             * - hidden from features
             * - damaged vehicle
             * - opt-out flag
             */
            $isEligible = (bool) apply_filters(
                'sccc/featured_vehicles/vehicle_row_is_eligible',
                true,
                $vehicle,
                $row,
                $userId
            );

            if (!$isEligible) {
                continue;
            }

            $vehicles[$vehicleId] = $vehicle;
        }

        ksort($vehicles, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($vehicles);
    }

    /**
     * Select the winning member for a month.
     *
     * CYCLE LOGIC
     * -----------
     * 1. Start with all currently eligible members.
     * 2. Exclude members already used in the CURRENT cycle.
     * 3. If nobody remains, reset the cycle and use the full pool again.
     * 4. Avoid the same member in consecutive months when possible.
     * 5. Pick deterministically based on the month key.
     *
     * WHY "CURRENT CYCLE" IS DERIVED FROM ARCHIVE HISTORY
     * ---------------------------------------------------
     * We do not store a separate cycle table.
     * Instead, we derive the current cycle from the existing archive CPT history.
     *
     * This keeps the system automatic and avoids extra moving parts.
     */
    protected static function selectMemberForMonth(array $pool, string $monthKey): ?array
    {
        if (empty($pool)) {
            return null;
        }

        $eligibleMemberIds = array_map('intval', array_keys($pool));
        sort($eligibleMemberIds, SORT_NUMERIC);

        $usedThisCycle = self::getUsedMemberIdsInCurrentCycle($eligibleMemberIds);

        $candidateIds = array_values(array_diff($eligibleMemberIds, $usedThisCycle));

        /**
         * If the cycle is exhausted, reset to the full current pool.
         */
        if (empty($candidateIds)) {
            $candidateIds = $eligibleMemberIds;
        }

        /**
         * Avoid immediate back-to-back repeats when possible.
         */
        $lastMemberId = self::getMostRecentlyFeaturedMemberId();

        if ($lastMemberId > 0 && count($candidateIds) > 1) {
            $withoutLast = array_values(array_filter(
                $candidateIds,
                static fn (int $id): bool => $id !== $lastMemberId
            ));

            if (!empty($withoutLast)) {
                $candidateIds = $withoutLast;
            }
        }

        if (empty($candidateIds)) {
            return null;
        }

        sort($candidateIds, SORT_NUMERIC);

        $winnerId = self::pickDeterministicValue(
            $candidateIds,
            $monthKey . '|member|' . implode(',', $candidateIds)
        );

        if ($winnerId === null || empty($pool[$winnerId])) {
            return null;
        }

        /**
         * Store the effective member pool size with the selected entry so it can
         * be written into the archive for auditing/debugging.
         */
        $selected = $pool[$winnerId];
        $selected['selection_member_pool_count'] = count($candidateIds);

        return $selected;
    }

    /**
     * Determine which eligible members have already been used in the current cycle.
     *
     * HOW THIS WORKS
     * --------------
     * We scan archive history from newest to oldest and collect eligible member IDs.
     *
     * We stop when:
     * - we have seen all currently eligible members, OR
     * - we encounter a duplicate eligible member, which implies we crossed
     *   into the previous cycle.
     *
     * WHY THIS APPROACH
     * -----------------
     * It works well even when the club grows over time.
     * Example:
     * - if a new member becomes eligible, they will still get a turn before reset
     *   if they are not already in the current cycle's used set.
     */
    protected static function getUsedMemberIdsInCurrentCycle(array $eligibleMemberIds): array
    {
        if (empty($eligibleMemberIds)) {
            return [];
        }

        $eligibleMap = array_fill_keys(array_map('intval', $eligibleMemberIds), true);
        $seen = [];

        foreach (self::getArchivePostIdsNewestFirst() as $postId) {
            $memberId = (int) get_post_meta($postId, self::META_SOURCE_USER_ID, true);

            if ($memberId < 1 || !isset($eligibleMap[$memberId])) {
                continue;
            }

            if (isset($seen[$memberId])) {
                break;
            }

            $seen[$memberId] = true;

            if (count($seen) >= count($eligibleMap)) {
                break;
            }

            return array_map('intval', array_keys($seen));
        }

        return array_map('intval', array_keys($seen));
    }

    /**
     * Select one eligible vehicle for the selected member.
     *
     * VEHICLE ROTATION LOGIC
     * ----------------------
     * - Prefer vehicles for this member that have not already been featured
     * - If all have been used, allow reuse within that member's garage
     * - Pick deterministically for the month
     */
    protected static function selectVehicleForMemberForMonth(
        array $selectedMember,
        string $monthKey,
        array $excludedVehicleIds = []
    ): ?array
    {
        /** @var WP_User|null $user */
        $user = $selectedMember['user'] ?? null;

        if (!$user instanceof WP_User) {
            return null;
        }

        $userId = (int) $user->ID;
        $vehicles = $selectedMember['vehicles'] ?? [];

        if ($userId < 1 || empty($vehicles) || !is_array($vehicles)) {
            return null;
        }

        $usedVehicleIds = self::getPreviouslyFeaturedVehicleIdsForMember($userId);

        $candidateVehicles = array_values(array_filter(
            $vehicles,
            static function (array $vehicle) use ($usedVehicleIds): bool {
                $vehicleId = strtoupper(trim((string) ($vehicle['vehicle_id'] ?? '')));

                return $vehicleId !== '' && !in_array($vehicleId, $usedVehicleIds, true);
            }
        ));

        $excludedVehicleIds = array_values(array_unique(array_filter(array_map(
            static fn ($vehicleId): string => strtoupper(trim((string) $vehicleId)),
            $excludedVehicleIds
        ))));

        if (!empty($excludedVehicleIds) && count($candidateVehicles) > 1) {
            $withoutExcludedVehicles = array_values(array_filter(
                $candidateVehicles,
                static function (array $vehicle) use ($excludedVehicleIds): bool {
                    $vehicleId = strtoupper(trim((string) ($vehicle['vehicle_id'] ?? '')));

                    return $vehicleId !== '' && !in_array($vehicleId, $excludedVehicleIds, true);
                }
            ));

            if (!empty($withoutExcludedVehicles)) {
                $candidateVehicles = $withoutExcludedVehicles;
            }
        }

        if (empty($candidateVehicles)) {
            $candidateVehicles = array_values($vehicles);

            if (!empty($excludedVehicleIds) && count($candidateVehicles) > 1) {
                $withoutExcludedVehicles = array_values(array_filter(
                    $candidateVehicles,
                    static function (array $vehicle) use ($excludedVehicleIds): bool {
                        $vehicleId = strtoupper(trim((string) ($vehicle['vehicle_id'] ?? '')));

                        return $vehicleId !== '' && !in_array($vehicleId, $excludedVehicleIds, true);
                    }
                ));

                if (!empty($withoutExcludedVehicles)) {
                    $candidateVehicles = $withoutExcludedVehicles;
                }
            }
        }

        if (empty($candidateVehicles)) {
            return null;
        }

        usort($candidateVehicles, static function (array $a, array $b): int {
            return strnatcasecmp(
                (string) ($a['vehicle_id'] ?? ''),
                (string) ($b['vehicle_id'] ?? '')
            );
        });

        $vehicleIds = array_map(
            static fn (array $vehicle): string => (string) ($vehicle['vehicle_id'] ?? ''),
            $candidateVehicles
        );

        $winnerVehicleId = self::pickDeterministicValue(
            $vehicleIds,
            $monthKey . '|vehicle|' . $userId . '|' . implode(',', $vehicleIds)
        );

        if ($winnerVehicleId === null) {
            return null;
        }

        foreach ($candidateVehicles as $vehicle) {
            if (strcasecmp((string) ($vehicle['vehicle_id'] ?? ''), (string) $winnerVehicleId) === 0) {
                $vehicle['selection_vehicle_pool_count'] = count($candidateVehicles);

                return $vehicle;
            }
        }

        return null;
    }

    /**
     * Get all previously featured vehicle IDs for a specific member.
     *
     * WHY:
     * Used to rotate a member's cars when that member appears again in a later cycle.
     */
    protected static function getPreviouslyFeaturedVehicleIdsForMember(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }

        $archiveIds = get_posts([
            'post_type'        => RegisterFeaturedVehiclePostType::POST_TYPE,
            'post_status'      => 'publish',
            'numberposts'      => -1,
            'fields'           => 'ids',
            'meta_key'         => self::META_SOURCE_USER_ID,
            'meta_value'       => $userId,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'suppress_filters' => false,
        ]);

        if (empty($archiveIds)) {
            return [];
        }

        $vehicleIds = [];

        foreach ($archiveIds as $postId) {
            $vehicleId = strtoupper(trim((string) get_post_meta((int) $postId, self::META_SOURCE_VEHICLE_ID, true)));

            if ($vehicleId !== '') {
                $vehicleIds[$vehicleId] = true;
            }
        }

        return array_keys($vehicleIds);
    }

    /**
     * Get the most recently featured member ID.
     *
     * WHY:
     * Used to avoid immediate back-to-back repeats when the pool allows it.
     */
    protected static function getMostRecentlyFeaturedMemberId(): int
    {
        $archiveIds = self::getArchivePostIdsNewestFirst(1);

        if (empty($archiveIds[0])) {
            return 0;
        }

        return (int) get_post_meta((int) $archiveIds[0], self::META_SOURCE_USER_ID, true);
    }

    /**
     * Get archive post IDs in reverse chronological order.
     *
     * WHY:
     * Keeping this in one helper avoids duplicating the archive query logic.
     */
    protected static function getArchivePostIdsNewestFirst(int $limit = -1): array
    {
        $args = [
            'post_type'        => RegisterFeaturedVehiclePostType::POST_TYPE,
            'post_status'      => 'publish',
            'numberposts'      => $limit,
            'fields'           => 'ids',
            'orderby'          => 'date',
            'order'            => 'DESC',
            'suppress_filters' => false,
        ];

        return array_map('intval', get_posts($args));
    }

    /**
     * Create the saved archive record for the month.
     *
     * WHAT GETS SAVED
     * ---------------
     * - source IDs
     * - snapshot data for member + vehicle
     * - featured image thumbnail
     * - audit metadata
     *
     * WHY SNAPSHOT DATA MATTERS
     * -------------------------
     * Member/vehicle profile data may change later.
     * The archive should preserve what was featured at the time.
     */
    protected static function createArchiveRecord(string $monthKey, array $selectedMember, array $selectedVehicle)
    {
        /** @var WP_User|null $user */
        $user = $selectedMember['user'] ?? null;

        if (!$user instanceof WP_User) {
            return new WP_Error('sccc_feature_missing_user', 'Selected member record is missing a valid user.');
        }

        $userId    = (int) $user->ID;
        $vehicleId = strtoupper(trim((string) ($selectedVehicle['vehicle_id'] ?? '')));

        if ($userId < 1 || $vehicleId === '') {
            return new WP_Error('sccc_feature_missing_vehicle', 'Selected feature is missing a valid user or vehicle ID.');
        }

        $monthLabel = self::getMonthLabelFromMonthKey($monthKey);
        $year       = (int) substr($monthKey, 0, 4);

        $postId = wp_insert_post([
            'post_type'   => RegisterFeaturedVehiclePostType::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => $monthLabel,
            'post_name'   => 'featured-vehicle-' . $monthKey,
        ], true);

        if ($postId instanceof WP_Error || (int) $postId < 1) {
            return $postId;
        }

        $postId = (int) $postId;

        /**
         * Member snapshot
         */
        $displayName  = trim((string) ($user->display_name ?: $user->user_login));
        $username     = trim((string) $user->user_login);
        $bio          = trim((string) get_user_meta($userId, 'description', true));
        $memberNumber = trim((string) get_user_meta($userId, 'membership_number', true));

        /**
         * Member tenure snapshot
         *
         * SOURCE
         * ------
         * membership_issued_at user meta from MemberProfile.
         *
         * STORED FORMAT
         * -------------
         * mysql datetime string, example: 2026-03-20 14:33:21
         */
        $memberSinceRaw  = trim((string) get_user_meta($userId, 'membership_issued_at', true));
        $memberSinceYear = self::extractYearFromDatetime($memberSinceRaw);

        /**
         * Profile image snapshot
         * ----------------------
         * Prefer the custom uploaded profile image used elsewhere in the project.
         * Fall back to WordPress avatar URL if there is no uploaded attachment ID.
         */
        $profileImageId  = (int) get_user_meta($userId, 'sccc_profile_image_id', true);
        $profileImageUrl = '';

        if ($profileImageId > 0) {
            $profileImageUrl = (string) (wp_get_attachment_image_url($profileImageId, 'medium_large') ?: '');
        }

        if ($profileImageUrl === '') {
            $profileImageUrl = (string) (get_avatar_url($userId, ['size' => 320]) ?: '');
        }

        /**
         * Vehicle snapshot
         */
        $vehicleImageId = (int) ($selectedVehicle['image_id'] ?? 0);
        $vehicleYear    = trim((string) ($selectedVehicle['year'] ?? ''));
        $vehicleMake    = trim((string) ($selectedVehicle['make_raw'] ?? ''));
        $vehicleModel   = trim((string) ($selectedVehicle['model'] ?? ''));
        $vehicleNick    = trim((string) ($selectedVehicle['nickname'] ?? ''));
        $vehicleNotes   = trim((string) ($selectedVehicle['notes'] ?? ''));

        /**
         * Save core archive metadata.
         */
        update_post_meta($postId, self::META_FEATURED_MONTH_KEY, $monthKey);
        update_post_meta($postId, self::META_FEATURED_MONTH_LABEL, $monthLabel);
        update_post_meta($postId, self::META_FEATURED_YEAR, $year);
        update_post_meta($postId, self::META_SOURCE_USER_ID, $userId);
        update_post_meta($postId, self::META_SOURCE_VEHICLE_ID, $vehicleId);
        update_post_meta($postId, self::META_SOURCE_VEHICLE_KEY, $userId . '|' . $vehicleId);

        update_post_meta($postId, self::META_OWNER_DISPLAY_NAME, $displayName);
        update_post_meta($postId, self::META_OWNER_USERNAME, $username);
        update_post_meta($postId, self::META_OWNER_BIO, $bio);
        update_post_meta($postId, self::META_OWNER_PROFILE_IMAGE_ID, $profileImageId);
        update_post_meta($postId, self::META_OWNER_PROFILE_IMAGE_URL, $profileImageUrl);
        update_post_meta($postId, self::META_MEMBER_NUMBER, $memberNumber);
        update_post_meta($postId, self::META_MEMBER_SINCE_RAW, $memberSinceRaw);
        update_post_meta($postId, self::META_MEMBER_SINCE_YEAR, $memberSinceYear);

        update_post_meta($postId, self::META_VEHICLE_YEAR, $vehicleYear);
        update_post_meta($postId, self::META_VEHICLE_MAKE, $vehicleMake);
        update_post_meta($postId, self::META_VEHICLE_MODEL, $vehicleModel);
        update_post_meta($postId, self::META_VEHICLE_NICKNAME, $vehicleNick);
        update_post_meta($postId, self::META_VEHICLE_NOTES, $vehicleNotes);
        update_post_meta($postId, self::META_VEHICLE_IMAGE_ID, $vehicleImageId);

        update_post_meta(
            $postId,
            self::META_SELECTION_MEMBER_POOL_COUNT,
            (int) ($selectedMember['selection_member_pool_count'] ?? 0)
        );

        update_post_meta(
            $postId,
            self::META_SELECTION_VEHICLE_POOL_COUNT,
            (int) ($selectedVehicle['selection_vehicle_pool_count'] ?? 0)
        );

        update_post_meta($postId, self::META_SELECTION_ALGO_VERSION, self::ALGORITHM_VERSION);
        update_post_meta($postId, self::META_GENERATED_AT, current_time('mysql'));

        /**
         * Mirror the chosen vehicle image into the post thumbnail.
         *
         * WHY:
         * Makes the archive list scannable in wp-admin and useful later for
         * reports/calendar assembly.
         */
        if ($vehicleImageId > 0) {
            set_post_thumbnail($postId, $vehicleImageId);
        }

        return $postId;
    }

    /**
     * Clear archive repository cache for a given month when the repository exists.
     *
     * WHY:
     * The resolver should not require the repository to be loaded first, but the
     * admin force reset must clear the same cached post/data transients used by
     * front-end rendering. This small guard keeps the service safe in either load
     * order while still clearing cache in the normal theme boot path.
     */
    protected static function clearArchiveCacheForMonth(string $monthKey): void
    {
        if (class_exists(FeaturedVehicleArchiveRepository::class)) {
            FeaturedVehicleArchiveRepository::clearCacheForMonth($monthKey);
        }
    }

    /**
     * Build a consistent force-reset result payload.
     *
     * WHY:
     * Keeping the shape centralized makes the wp-admin controller simple and
     * avoids scattered array-key checks when rendering admin notices.
     */
    protected static function buildResetResult(
        bool $success,
        string $message,
        string $monthKey = '',
        string $monthLabel = '',
        int $previousPostId = 0,
        int $newPostId = 0,
        int $previousUserId = 0,
        int $newUserId = 0,
        string $previousVehicleId = '',
        string $newVehicleId = '',
        bool $sameMember = false,
        bool $sameVehicle = false
    ): array {
        return [
            'success'          => $success,
            'message'          => $message,
            'month_key'        => $monthKey,
            'month_label'      => $monthLabel,
            'previous_post_id' => $previousPostId,
            'new_post_id'      => $newPostId,
            'previous_user_id' => $previousUserId,
            'new_user_id'      => $newUserId,
            'previous_vehicle' => strtoupper(trim($previousVehicleId)),
            'new_vehicle'      => strtoupper(trim($newVehicleId)),
            'same_member'      => $sameMember,
            'same_vehicle'     => $sameVehicle,
        ];
    }
    /**
     * Get the current site month key in YYYY-MM format.
     *
     * WHY:
     * current_time() uses the site's configured timezone when GMT is false,
     * which is exactly what we want for a calendar-month feature.
     */
    protected static function getCurrentMonthKey(): string
    {
        return (string) current_time('Y-m');
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
     * Convert a YYYY-MM month key into a human label like "March 2026".
     */
    protected static function getMonthLabelFromMonthKey(string $monthKey): string
    {
        $monthKey = self::normalizeMonthKey($monthKey);

        if ($monthKey === '') {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m', $monthKey);

        if (!$date) {
            return $monthKey;
        }

        return $date->format('F Y');
    }

    /**
     * Normalize a possible ACF image value into an attachment ID.
     *
     * SUPPORTED INPUTS
     * ----------------
     * - integer ID
     * - numeric string ID
     * - ACF image array with ID or id key
     */
    protected static function normalizeAttachmentId($value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_array($value)) {
            return (int) ($value['ID'] ?? $value['id'] ?? 0);
        }

        return 0;
    }

    /**
     * Extract a four-digit year from a mysql-ish datetime string.
     *
     * EXAMPLES
     * --------
     * 2026-03-20 14:33:21 -> 2026
     * 2026-03-20          -> 2026
     *
     * RETURN
     * ------
     * - integer year when parsing succeeds
     * - 0 when the value is empty or invalid
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

    /**
     * Deterministically choose one value from a sorted list.
     *
     * WHY THIS EXISTS
     * ---------------
     * The user wanted automation without favoritism.
     * This keeps the pick stable for the same month + pool input.
     *
     * HOW IT WORKS
     * ------------
     * - We hash a predictable seed string.
     * - We mod the result by the pool size.
     * - We return the value at that index.
     *
     * RESULT
     * ------
     * Same month key + same candidate pool = same winner.
     */
    protected static function pickDeterministicValue(array $values, string $seed)
    {
        if (empty($values)) {
            return null;
        }

        $values = array_values($values);

        $hash = crc32($seed);

        /**
         * crc32 can return signed ints depending on platform;
         * sprintf('%u', ...) normalizes to an unsigned decimal string.
         */
        $unsigned = (int) sprintf('%u', $hash);
        $index = $unsigned % count($values);

        return $values[$index] ?? null;
    }
}