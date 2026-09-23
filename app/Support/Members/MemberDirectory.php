<?php

namespace App\Support\Members;

/**
 * MemberDirectory
 * File: app/Support/Members/MemberDirectory.php
 * =================================================
 *
 * Purpose
 * -------
 * Provides the data/service layer for the Space City Car Club Member Directory.
 *
 * This class intentionally keeps the directory logic out of the Blade template:
 * - Members-only access checks
 * - User querying
 * - PMPro active membership verification
 * - Directory opt-out handling
 * - Search/filter/sort handling
 * - Pagination
 * - Member card data normalization
 * - Garage mosaic data
 * - Social link normalization
 *
 * Source of truth rules
 * ---------------------
 * - WordPress users are the member records.
 * - The WordPress role "sccc_member" is the first query filter.
 * - PMPro is still the final authority for active membership.
 * - Members can opt out through:
 *      sccc_hide_in_member_directory = 1
 *
 * Privacy rules
 * -------------
 * The directory does NOT expose:
 * - Email address
 * - Phone number
 * - Birthdate
 * - Membership number
 * - Admin roles
 *
 * The directory MAY expose:
 * - Display name
 * - Profile photo
 * - Bio
 * - Member since year
 * - Website
 * - Social profile links
 * - Garage vehicle images/details
 *
 * Garage tooltip data
 * -------------------
 * Vehicle color is intentionally ignored because the current vehicle data model
 * does not store color.
 */

class MemberDirectory
{
    /**
     * Directory query string keys.
     *
     * These keys are kept namespaced so they do not conflict with WordPress core
     * query variables or other plugin filters.
     */
    public const QUERY_SEARCH = 'member_search';
    public const QUERY_MAKE   = 'vehicle_make';
    public const QUERY_SORT   = 'member_sort';
    public const QUERY_PAGE   = 'member_page';

    /**
     * Main member role used by the club.
     */
    protected const MEMBER_ROLE = 'sccc_member';

    /**
     * Default number of member cards per page.
     *
     * 12 gives:
     * - 4 rows on desktop at 3 cards per row
     * - 6 rows on tablet at 2 cards per row
     * - manageable scrolling on mobile
     */
    protected const DEFAULT_PER_PAGE = 12;

    /**
     * Hard safety limit for the first version.
     *
     * The club directory should remain small/medium in practice. This prevents
     * a future accidental runaway user query if a site has thousands of users.
     */
    protected const MAX_CANDIDATE_USERS = 1000;

    /**
     * Check whether the current visitor can view the Member Directory.
     *
     * This is intentionally stricter than "can manage options" because the user
     * specifically wants non-members blocked from the page.
     */
    public static function currentUserCanView(): bool
    {
        if (! is_user_logged_in()) {
            return false;
        }

        return static::userHasActiveMembership((int) get_current_user_id());
    }

    /**
     * Return a friendly locked-state payload for the Blade template.
     *
     * The template can use this to show a polished members-only message instead
     * of leaking any directory data.
     */
    public static function lockedState(): array
    {
        return [
            'title' => __('Members Only', 'sccc'),
            'message' => __('The Member Directory is available to active Space City Car Club members only.', 'sccc'),
            'login_url' => function_exists('wc_get_page_permalink')
                ? wc_get_page_permalink('myaccount')
                : wp_login_url(get_permalink()),
            'join_url' => home_url('/join/'),
        ];
    }

    /**
     * Build the full directory state for the template.
     *
     * Returned structure:
     * - filters
     * - make_options
     * - members
     * - total
     * - page
     * - per_page
     * - total_pages
     * - pagination_links
     */
    public static function query(array $request = []): array
    {
        $filters = static::normalizeFilters($request);

        $candidate_users = static::queryCandidateUsers();
        $members = [];

        foreach ($candidate_users as $user) {
            if (! $user instanceof \WP_User) {
                continue;
            }

            if (! static::isDirectoryVisibleMember($user)) {
                continue;
            }

            $members[] = static::buildMemberCardData($user);
        }

        /**
         * Build make options before applying the selected make filter.
         *
         * This lets the filter dropdown keep showing all available vehicle makes
         * from eligible visible members, not just the makes left after filtering.
         */
        $make_options = static::collectMakeOptions($members);

        if ($filters['search'] !== '') {
            $members = array_values(array_filter(
                $members,
                fn (array $member): bool => static::memberMatchesSearch($member, $filters['search'])
            ));
        }

        if ($filters['vehicle_make'] !== '') {
            $members = array_values(array_filter(
                $members,
                fn (array $member): bool => static::memberMatchesMake($member, $filters['vehicle_make'])
            ));
        }

        $members = static::sortMembers($members, $filters['sort']);

        $total = count($members);
        $per_page = static::DEFAULT_PER_PAGE;
        $total_pages = max(1, (int) ceil($total / $per_page));
        $page = max(1, min((int) $filters['page'], $total_pages));

        $offset = ($page - 1) * $per_page;
        $paged_members = array_slice($members, $offset, $per_page);

        return [
            'filters' => [
                'search' => $filters['search'],
                'vehicle_make' => $filters['vehicle_make'],
                'sort' => $filters['sort'],
            ],
            'make_options' => $make_options,
            'members' => $paged_members,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
            'pagination_links' => static::paginationLinks($page, $total_pages, $filters),
        ];
    }

    /**
     * Normalize request filters from $_GET or a passed request array.
     */
    protected static function normalizeFilters(array $request): array
    {
        $search = isset($request[static::QUERY_SEARCH])
            ? sanitize_text_field(wp_unslash($request[static::QUERY_SEARCH]))
            : '';

        $vehicle_make = isset($request[static::QUERY_MAKE])
            ? sanitize_title(wp_unslash($request[static::QUERY_MAKE]))
            : '';

        $sort = isset($request[static::QUERY_SORT])
            ? sanitize_key(wp_unslash($request[static::QUERY_SORT]))
            : 'name_asc';

        $page = isset($request[static::QUERY_PAGE])
            ? absint($request[static::QUERY_PAGE])
            : 1;

        $allowed_sorts = [
            'name_asc',
            'newest',
            'oldest',
        ];

        if (! in_array($sort, $allowed_sorts, true)) {
            $sort = 'name_asc';
        }

        return [
            'search' => trim($search),
            'vehicle_make' => $vehicle_make,
            'sort' => $sort,
            'page' => max(1, $page),
        ];
    }

    /**
     * Query possible directory users.
     *
     * This is only the first pass:
     * - Must have role sccc_member
     * - Must not have the directory opt-out meta set to 1
     *
     * We still verify active PMPro membership after this query.
     */
    protected static function queryCandidateUsers(): array
    {
        $query = new \WP_User_Query([
            'role' => static::MEMBER_ROLE,
            'fields' => 'all',
            'number' => static::MAX_CANDIDATE_USERS,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'sccc_hide_in_member_directory',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => 'sccc_hide_in_member_directory',
                    'value' => '1',
                    'compare' => '!=',
                ],
            ],
        ]);

        $results = $query->get_results();

        return is_array($results) ? $results : [];
    }

    /**
     * Final visibility check for a member card.
     *
     * The role/meta query is only the first pass. This method makes the final
     * decision using:
     * - active PMPro membership
     * - opt-out meta
     */
    protected static function isDirectoryVisibleMember(\WP_User $user): bool
    {
        $user_id = (int) $user->ID;

        if ($user_id <= 0) {
            return false;
        }

        if (static::isHiddenInDirectory($user_id)) {
            return false;
        }

        if (! static::userHasActiveMembership($user_id)) {
            return false;
        }

        return true;
    }

    /**
     * PMPro active-membership check.
     *
     * PMPro is the source of truth for whether a user is an active member.
     * The role is helpful for querying, but this check prevents stale roles from
     * appearing in the directory.
     */
    protected static function userHasActiveMembership(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        if (! function_exists('pmpro_getMembershipLevelsForUser')) {
            return false;
        }

        $levels = pmpro_getMembershipLevelsForUser($user_id);

        return is_array($levels) && ! empty($levels);
    }

    /**
     * Check the reversed directory opt-out setting.
     *
     * Field:
     * - sccc_hide_in_member_directory
     *
     * Meaning:
     * - 1 = hide this member
     * - 0/empty = eligible to show
     */
    protected static function isHiddenInDirectory(int $user_id): bool
    {
        $value = static::getUserField($user_id, 'sccc_hide_in_member_directory', 0);

        return (int) ((bool) $value) === 1;
    }

    /**
     * Build normalized card/modal data for one directory member.
     */
    protected static function buildMemberCardData(\WP_User $user): array
    {
        $user_id = (int) $user->ID;

        $display_name = trim((string) $user->display_name);
        if ($display_name === '') {
            $display_name = trim((string) $user->user_nicename);
        }

        $bio = (string) get_the_author_meta('description', $user_id);
        $bio = static::normalizePlainText($bio);

        $issued_at = (string) static::getUserField($user_id, 'membership_issued_at', '');
        $issued_timestamp = static::timestampFromValue($issued_at);

        $vehicles = static::getVehicles($user_id);
        $vehicle_index = (string) get_user_meta($user_id, 'vehicle_index', true);

        if ($vehicle_index === '') {
            $vehicle_index = static::buildVehicleIndex($vehicles);
        }

        $website = (string) static::getUserField($user_id, 'sccc_member_website', '');
        $website = esc_url_raw($website);

        $social_raw = static::getUserField($user_id, 'sccc_member_social_links', []);
        $social_links = static::normalizeSocialLinks(is_array($social_raw) ? $social_raw : []);

        $profile_image_id = (int) get_user_meta($user_id, 'sccc_profile_image_id', true);
        $profile_image_url = $profile_image_id > 0
            ? (string) wp_get_attachment_image_url($profile_image_id, 'thumbnail')
            : '';

        if ($profile_image_url === '') {
            $profile_image_url = (string) get_avatar_url($user_id, ['size' => 180]);
        }

        $garage_summary = static::garageSummary($vehicles);
        $service_badge = static::serviceBadge($user_id);

        return [
            'id' => $user_id,
            'display_name' => $display_name,
            'profile_image_url' => $profile_image_url,
            'initial' => static::initial($display_name),
            'bio' => $bio,
            'bio_excerpt' => $bio !== ''
                ? wp_trim_words($bio, 24, '…')
                : '',
            'member_since_year' => $issued_timestamp > 0
                ? date_i18n('Y', $issued_timestamp)
                : '',
            'member_since_label' => $issued_timestamp > 0
                ? sprintf(__('Member since %s', 'sccc'), date_i18n('Y', $issued_timestamp))
                : __('Active member', 'sccc'),
            'issued_timestamp' => $issued_timestamp,
            'website' => $website,
            'social_links' => $social_links,
            'vehicles' => $vehicles,
            'vehicle_count' => count($vehicles),
            'vehicle_index' => $vehicle_index,
            'garage_summary' => $garage_summary,
            'service_badge' => $service_badge,
        ];
    }

    /**
     * Read a user field through ACF when available, then fall back to user meta.
     *
     * ACF user fields are stored in user meta, but get_field() is preferred when
     * active because it handles repeater fields like vehicles/social links.
     */
    protected static function getUserField(int $user_id, string $field_name, $default = '')
    {
        $acf_key = 'user_' . $user_id;

        if (function_exists('get_field')) {
            $value = get_field($field_name, $acf_key);

            if ($value !== null && $value !== false && $value !== '') {
                return $value;
            }
        }

        $value = get_user_meta($user_id, $field_name, true);

        if ($value === '' || $value === null) {
            return $default;
        }

        return $value;
    }

    /**
     * Normalize a plain-text field for display/search.
     */
    protected static function normalizePlainText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset'));
        $value = preg_replace("/\r\n?/", "\n", $value) ?? $value;
        $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? $value;

        return trim(wp_strip_all_tags($value));
    }

    /**
     * Convert a stored date/datetime value into a timestamp.
     */
    protected static function timestampFromValue(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $timestamp = strtotime($value);

        return $timestamp ? (int) $timestamp : 0;
    }

    /**
     * Get normalized vehicle rows for the member garage mosaic.
     */
    protected static function getVehicles(int $user_id): array
    {
        $raw = static::getUserField($user_id, 'vehicles', []);

        if (! is_array($raw)) {
            return [];
        }

        $vehicles = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $year = trim((string) ($row['year'] ?? ''));

            $make = trim((string) ($row['make_raw'] ?? ''));
            if ($make === '') {
                $make_select = trim((string) ($row['make_select'] ?? ''));
                $make_other = trim((string) ($row['make_other'] ?? ''));

                $make = ($make_select === 'Other' && $make_other !== '')
                    ? $make_other
                    : $make_select;
            }

            $model = trim((string) ($row['model'] ?? ''));
            $nickname = trim((string) ($row['nickname'] ?? ''));

            $image_id = static::imageIdFromAcfValue($row['image'] ?? 0);

            $image_url_large = $image_id > 0
                ? (string) wp_get_attachment_image_url($image_id, 'large')
                : '';

            $image_url_medium = $image_id > 0
                ? (string) wp_get_attachment_image_url($image_id, 'medium')
                : '';

            $label_parts = array_filter([$year, $make, $model]);
            $label = trim(implode(' ', $label_parts));

            if ($label === '' && $nickname !== '') {
                $label = $nickname;
            }

            if ($label === '') {
                $label = __('Vehicle', 'sccc');
            }

            $tooltip = $label;
            if ($nickname !== '') {
                $tooltip .= ' — ' . $nickname;
            }

            $vehicles[] = [
                'vehicle_id' => trim((string) ($row['vehicle_id'] ?? '')),
                'year' => $year,
                'make' => $make,
                'make_slug' => sanitize_title($make),
                'model' => $model,
                'nickname' => $nickname,
                'label' => $label,
                'tooltip' => $tooltip,
                'image_id' => $image_id,
                'image_url_large' => $image_url_large,
                'image_url_medium' => $image_url_medium,
            ];
        }

        return $vehicles;
    }

    /**
     * Normalize an ACF image value into an attachment ID.
     *
     * Supports both:
     * - return_format = id
     * - return_format = array
     */
    protected static function imageIdFromAcfValue($value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_array($value) && isset($value['ID'])) {
            return (int) $value['ID'];
        }

        if (is_array($value) && isset($value['id'])) {
            return (int) $value['id'];
        }

        return 0;
    }

    /**
     * Build a searchable vehicle index from normalized vehicles.
     */
    protected static function buildVehicleIndex(array $vehicles): string
    {
        $parts = [];

        foreach ($vehicles as $vehicle) {
            $parts[] = strtolower(trim(implode(' ', array_filter([
                $vehicle['year'] ?? '',
                $vehicle['make'] ?? '',
                $vehicle['model'] ?? '',
                $vehicle['nickname'] ?? '',
            ]))));
        }

        $parts = array_filter(array_unique($parts));

        return implode(' | ', $parts);
    }

    /**
     * Build the compact garage summary for the card.
     */
    protected static function garageSummary(array $vehicles): string
    {
        $count = count($vehicles);

        if ($count === 0) {
            return __('No vehicles listed yet', 'sccc');
        }

        $first = $vehicles[0]['label'] ?? __('Vehicle', 'sccc');

        if ($count === 1) {
            return $first;
        }

        return sprintf(
            _n('%1$s + %2$d more vehicle', '%1$s + %2$d more vehicles', $count - 1, 'sccc'),
            $first,
            $count - 1
        );
    }

    /**
     * Optional service/responder badge.
     *
     * This does not expose branch/agency details. It only shows a simple public
     * badge if the member has marked themselves as a veteran/first responder.
     */
    protected static function serviceBadge(int $user_id): array
    {
        $is_service = (int) ((bool) static::getUserField($user_id, 'is_veteran_first_responder', 0));

        if ($is_service !== 1) {
            return [];
        }

        $type = (string) static::getUserField($user_id, 'veteran_type', '');

        $label = match ($type) {
            'veteran' => __('Veteran', 'sccc'),
            'first_responder' => __('First Responder', 'sccc'),
            'both' => __('Veteran + First Responder', 'sccc'),
            default => __('Service / Responder', 'sccc'),
        };

        return [
            'label' => $label,
        ];
    }

    /**
     * Normalize and validate member social links for display.
     */
    protected static function normalizeSocialLinks(array $rows): array
    {
        $platforms = static::socialPlatforms();
        $clean = [];
        $seen = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $platform = isset($row['platform']) ? sanitize_key((string) $row['platform']) : '';
            $username = isset($row['username']) ? sanitize_text_field((string) $row['username']) : '';
            $username = ltrim(trim($username), '@');
            $url = isset($row['url']) ? esc_url_raw((string) $row['url']) : '';

            if ($platform === '' && $url !== '') {
                $platform = static::platformFromSocialUrl($url);
            }

            if ($platform === '' || ! isset($platforms[$platform])) {
                continue;
            }

            if ($username === '' && $url !== '') {
                $username = static::usernameFromSocialUrl($url);
            }

            if ($url === '' && $username !== '') {
                $url = static::profileUrlFromPlatformUsername($platform, $username);
            }

            if ($url === '') {
                continue;
            }

            $fingerprint = $platform . '|' . strtolower($username) . '|' . strtolower($url);

            if (isset($seen[$fingerprint])) {
                continue;
            }

            $clean[] = [
                'platform' => $platform,
                'label' => $platforms[$platform]['label'],
                'icon' => $platforms[$platform]['icon'],
                'username' => $username,
                'url' => $url,
            ];

            $seen[$fingerprint] = true;

            if (count($clean) >= 15) {
                break;
            }
        }

        return $clean;
    }

    /**
     * Approved social platform definitions.
     *
     * Icons are inline SVG strings so the directory does not need another
     * frontend dependency.
     */
    protected static function socialPlatforms(): array
    {
        return [
            'facebook' => [
                'label' => 'Facebook',
                'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14.2 8.4V6.7c0-.8.6-1 1-1h2.6V2.1L14.4 2c-3.4 0-4.9 2-4.9 4.8v1.6H6.4v3.8h3.1V22h4.1v-9.8h3.3l.5-3.8h-3.2Z"/></svg>',
            ],
            'instagram' => [
                'label' => 'Instagram',
                'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9a5.5 5.5 0 0 1-5.5 5.5h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2Zm0 2A3.5 3.5 0 0 0 4 7.5v9A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5v-9A3.5 3.5 0 0 0 16.5 4h-9Zm4.5 3.4A4.6 4.6 0 1 1 12 16.6a4.6 4.6 0 0 1 0-9.2Zm0 2A2.6 2.6 0 1 0 12 14.6a2.6 2.6 0 0 0 0-5.2Zm5-2.45a1.05 1.05 0 1 1 0 2.1 1.05 1.05 0 0 1 0-2.1Z"/></svg>',
            ],
            'x' => [
                'label' => 'X',
                'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M17.7 3h3.1l-6.8 7.8L22 21h-6.4l-5-6.5L4.9 21H1.8l7.3-8.4L1.4 3H8l4.5 5.9L17.7 3Zm-1.1 16.2h1.7L7.1 4.7H5.3l11.3 14.5Z"/></svg>',
            ],
            'tiktok' => [
                'label' => 'TikTok',
                'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M16.2 2c.3 2.4 1.7 4 4 4.2v3.5a7.4 7.4 0 0 1-4-1.2v6.7a6.3 6.3 0 1 1-6.3-6.3c.4 0 .8 0 1.2.1v3.7a2.6 2.6 0 1 0 1.8 2.5V2h3.3Z"/></svg>',
            ],
            'youtube' => [
                'label' => 'YouTube',
                'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21.6 7.2s-.2-1.6-.8-2.3c-.8-.9-1.7-.9-2.1-1C15.8 3.7 12 3.7 12 3.7h0s-3.8 0-6.7.2c-.4.1-1.3.1-2.1 1-.6.7-.8 2.3-.8 2.3S2.2 9.1 2.2 11v1.8c0 1.9.2 3.8.2 3.8s.2 1.6.8 2.3c.8.9 1.9.9 2.4 1 1.7.2 6.4.2 6.4.2s3.8 0 6.7-.2c.4-.1 1.3-.1 2.1-1 .6-.7.8-2.3.8-2.3s.2-1.9.2-3.8V11c0-1.9-.2-3.8-.2-3.8ZM10.1 14.9V8.4l6.1 3.2-6.1 3.3Z"/></svg>',
            ],
        ];
    }

    /**
     * Guess platform from a profile URL.
     */
    protected static function platformFromSocialUrl(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?: $host;

        if (str_contains($host, 'facebook.com') || str_contains($host, 'fb.com')) {
            return 'facebook';
        }

        if (str_contains($host, 'instagram.com')) {
            return 'instagram';
        }

        if ($host === 'x.com' || str_contains($host, 'twitter.com')) {
            return 'x';
        }

        if (str_contains($host, 'tiktok.com')) {
            return 'tiktok';
        }

        if (str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be')) {
            return 'youtube';
        }

        return '';
    }

    /**
     * Best-effort username extraction from a profile URL.
     */
    protected static function usernameFromSocialUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $path = trim($path, "/ \t\n\r\0\x0B");

        if ($path === '') {
            return '';
        }

        $parts = array_values(array_filter(explode('/', $path)));
        $candidate = end($parts);

        if (! is_string($candidate) || $candidate === '') {
            return '';
        }

        return ltrim(sanitize_text_field($candidate), '@');
    }

    /**
     * Build common profile URLs from platform + username.
     */
    protected static function profileUrlFromPlatformUsername(string $platform, string $username): string
    {
        $username = ltrim(trim($username), '@');

        if ($username === '') {
            return '';
        }

        $encoded = rawurlencode($username);

        return match ($platform) {
            'facebook' => 'https://www.facebook.com/' . $encoded,
            'instagram' => 'https://www.instagram.com/' . $encoded,
            'x' => 'https://x.com/' . $encoded,
            'tiktok' => 'https://www.tiktok.com/@' . $encoded,
            'youtube' => 'https://www.youtube.com/@' . $encoded,
            default => '',
        };
    }

    /**
     * Collect available vehicle make options from eligible members.
     */
    protected static function collectMakeOptions(array $members): array
    {
        $makes = [];

        foreach ($members as $member) {
            foreach (($member['vehicles'] ?? []) as $vehicle) {
                $make = trim((string) ($vehicle['make'] ?? ''));
                $slug = sanitize_title($make);

                if ($make === '' || $slug === '') {
                    continue;
                }

                if (! isset($makes[$slug])) {
                    $makes[$slug] = $make;
                }
            }
        }

        natcasesort($makes);

        return $makes;
    }

    /**
     * Search across name, bio, and vehicle information.
     */
    protected static function memberMatchesSearch(array $member, string $search): bool
    {
        $needle = static::lower($search);

        if ($needle === '') {
            return true;
        }

        $haystack = implode(' ', array_filter([
            $member['display_name'] ?? '',
            $member['bio'] ?? '',
            $member['vehicle_index'] ?? '',
            $member['garage_summary'] ?? '',
            static::vehiclesSearchText($member['vehicles'] ?? []),
        ]));

        return str_contains(static::lower($haystack), $needle);
    }

    /**
     * Build searchable text from vehicle rows.
     */
    protected static function vehiclesSearchText(array $vehicles): string
    {
        $parts = [];

        foreach ($vehicles as $vehicle) {
            $parts[] = implode(' ', array_filter([
                $vehicle['year'] ?? '',
                $vehicle['make'] ?? '',
                $vehicle['model'] ?? '',
                $vehicle['nickname'] ?? '',
            ]));
        }

        return implode(' ', $parts);
    }

    /**
     * Filter member by selected vehicle make slug.
     */
    protected static function memberMatchesMake(array $member, string $make_slug): bool
    {
        if ($make_slug === '') {
            return true;
        }

        foreach (($member['vehicles'] ?? []) as $vehicle) {
            if (($vehicle['make_slug'] ?? '') === $make_slug) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sort member card data.
     */
    protected static function sortMembers(array $members, string $sort): array
    {
        usort($members, function (array $a, array $b) use ($sort): int {
            return match ($sort) {
                'newest' => ((int) ($b['issued_timestamp'] ?? 0)) <=> ((int) ($a['issued_timestamp'] ?? 0)),
                'oldest' => ((int) ($a['issued_timestamp'] ?? 0)) <=> ((int) ($b['issued_timestamp'] ?? 0)),
                default => strcasecmp((string) ($a['display_name'] ?? ''), (string) ($b['display_name'] ?? '')),
            };
        });

        return $members;
    }

    /**
     * Build pagination links while preserving filter query args.
     */
    protected static function paginationLinks(int $page, int $total_pages, array $filters): array
    {
        if ($total_pages <= 1) {
            return [];
        }

        $base_url = remove_query_arg(static::QUERY_PAGE);

        $query_args = [];

        if (! empty($filters['search'])) {
            $query_args[static::QUERY_SEARCH] = $filters['search'];
        }

        if (! empty($filters['vehicle_make'])) {
            $query_args[static::QUERY_MAKE] = $filters['vehicle_make'];
        }

        if (! empty($filters['sort']) && $filters['sort'] !== 'name_asc') {
            $query_args[static::QUERY_SORT] = $filters['sort'];
        }

        $base_url = add_query_arg($query_args, $base_url);

        $links = paginate_links([
            'base' => esc_url_raw(add_query_arg(static::QUERY_PAGE, '%#%', $base_url)),
            'format' => '',
            'current' => $page,
            'total' => $total_pages,
            'type' => 'array',
            'prev_text' => __('Previous', 'sccc'),
            'next_text' => __('Next', 'sccc'),
        ]);

        return is_array($links) ? $links : [];
    }

    /**
     * Safe lowercase helper.
     */
    protected static function lower(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value)
            : strtolower($value);
    }

    /**
     * First character for avatar fallback.
     */
    protected static function initial(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return 'M';
        }

        return function_exists('mb_substr')
            ? mb_substr($name, 0, 1)
            : substr($name, 0, 1);
    }
}