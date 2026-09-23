<?php

namespace App\View\Composers;

use Roots\Acorn\View\Composer;
use WP_User_Query;

/**
 * File path + filename: app/View/Composers/BusinessDirectory.php
 * -----------------------------------------------------------------------------
 * Purpose:
 * - Prepare all directory data for the member business directory page template.
 * - Keep filter parsing, user queries, result normalization, and pagination out
 *   of Blade so the views stay focused on markup.
 *
 * Why this file exists:
 * - The business directory is driven by user meta, not posts.
 * - The page still needs editor-managed page content at the top, but the listing
 *   itself behaves like an application view with search, filtering, and paging.
 * - Sage view composers are the cleanest way to scope this data to one template
 *   and the partials it includes.
 *
 * Important notes:
 * - Category and service keys must stay aligned with the keys used in
 *   `app/Fields/MemberProfile.php`.
 * - Search uses the computed `sccc_business_index` meta field for business
 *   keywords instead of relying on WordPress user-column search.
 * - Pagination is query-string based (`?paged=2`) so it works cleanly on a
 *   custom page template without depending on archive rewrites.
 */
class BusinessDirectory extends Composer
{
    /**
     * Bind explicitly to the custom business directory page template.
     *
     * We bind to the template view instead of individual partials because the
     * partials inherit the data automatically once the template includes them.
     * This keeps the wiring simple and localized to one page template.
     *
     * @var array<int, string>
     */
    protected static $views = [
        'template-business-directory',
    ];

    /**
     * Make the prepared data available to the template and all included partials.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $filters    = $this->requestFilters();
        $queryData  = $this->directoryQuery($filters);
        $cardData   = $this->mapCards($queryData['users']);
        $pagination = $this->pagination($filters, $queryData['total_pages']);

        return [
            'directoryRootClass'   => 'sccc-business-directory-template',
            'directorySearchTerm'  => $filters['q'],
            'directoryCategory'    => $filters['category'],
            'directoryService'     => $filters['service'],
            'directoryCurrentPage' => $filters['paged'],
            'directoryPerPage'     => $filters['per_page'],

            'directoryCategories'  => $this->businessCategoryOptions(),
            'directoryServices'    => $this->businessServiceOptions(),

            'directoryCards'       => $cardData,
            'directoryResultCount' => count($cardData),
            'directoryTotalResults'=> $queryData['total_results'],
            'directoryTotalPages'  => $queryData['total_pages'],
            'directoryHasResults'  => !empty($cardData),

            'directoryResetUrl'    => $this->currentBaseUrl(),
            'directoryPagination'  => $pagination,
            'directorySummary'     => $this->summary($filters, $queryData['total_results']),
        ];
    }

    /**
     * Read and sanitize supported GET filters.
     *
     * Supported params:
     * - q
     * - category
     * - service
     * - paged
     *
     * @return array<string, mixed>
     */
    protected function requestFilters(): array
    {
        $categories = $this->businessCategoryOptions();
        $services   = $this->businessServiceOptions();

        $q = isset($_GET['q'])
            ? sanitize_text_field(wp_unslash((string) $_GET['q']))
            : '';

        $category = isset($_GET['category'])
            ? sanitize_key(wp_unslash((string) $_GET['category']))
            : '';

        $service = isset($_GET['service'])
            ? sanitize_key(wp_unslash((string) $_GET['service']))
            : '';

        $pagedFromQueryVar = (int) get_query_var('paged', 0);
        $pagedFromGet      = isset($_GET['paged']) ? absint($_GET['paged']) : 0;
        $paged             = max(1, $pagedFromQueryVar, $pagedFromGet);

        if ($category !== '' && !array_key_exists($category, $categories)) {
            $category = '';
        }

        if ($service !== '' && !array_key_exists($service, $services)) {
            $service = '';
        }

        return [
            'q'        => $q,
            'category' => $category,
            'service'  => $service,
            'paged'    => $paged,
            'per_page' => 9,
        ];
    }

    /**
     * Query opted-in members with business data using WP_User_Query.
     *
     * Why this query is structured this way:
     * - Users are the source of truth for the directory.
     * - ACF multi-select values are stored in serialized user meta, so category
     *   and service filters use LIKE against the serialized key.
     * - Search targets the precomputed `sccc_business_index` field so business
     *   keywords are searchable without depending on user login/email columns.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function directoryQuery(array $filters): array
    {
        $metaQuery = [
            'relation' => 'AND',
            [
                'key'     => 'sccc_business_opt_in',
                'value'   => '1',
                'compare' => '=',
            ],
            [
                'key'     => 'sccc_business_name',
                'value'   => '',
                'compare' => '!=',
            ],
        ];

        if ($filters['q'] !== '') {
            $metaQuery[] = [
                'key'     => 'sccc_business_index',
                'value'   => strtolower($filters['q']),
                'compare' => 'LIKE',
            ];
        }

        if ($filters['category'] !== '') {
            $metaQuery[] = [
                'key'     => 'sccc_business_categories',
                'value'   => '"' . $filters['category'] . '"',
                'compare' => 'LIKE',
            ];
        }

        if ($filters['service'] !== '') {
            $metaQuery[] = [
                'key'     => 'sccc_business_services',
                'value'   => '"' . $filters['service'] . '"',
                'compare' => 'LIKE',
            ];
        }

        $perPage = (int) $filters['per_page'];
        $paged   = (int) $filters['paged'];
        $offset  = ($paged - 1) * $perPage;

        $query = new WP_User_Query([
            'number'      => $perPage,
            'offset'      => $offset,
            'count_total' => true,
            'fields'      => 'all',
            'meta_key'    => 'sccc_business_name',
            'orderby'     => 'meta_value',
            'order'       => 'ASC',
            'meta_query'  => $metaQuery,
        ]);

        $users        = $query->get_results();
        $totalResults = (int) $query->get_total();
        $totalPages   = max(1, (int) ceil($totalResults / $perPage));

        if ($paged > $totalPages && $totalResults > 0) {
            $filters['paged'] = $totalPages;

            return $this->directoryQuery($filters);
        }

        return [
            'users'         => is_array($users) ? $users : [],
            'total_results' => $totalResults,
            'total_pages'   => $totalPages,
        ];
    }

    /**
     * Normalize WP_User objects into card-ready arrays.
     *
     * The views should not know where a value lives or how it was assembled.
     * This method translates raw user/meta data into a stable payload for the
     * business card partial.
     *
     * @param  array<int, mixed>  $users
     * @return array<int, array<string, mixed>>
     */
    protected function mapCards(array $users): array
    {
        $cards      = [];
        $categories = $this->businessCategoryOptions();

        foreach ($users as $user) {
            if (!$user instanceof \WP_User) {
                continue;
            }

            $userId = (int) $user->ID;

            $businessName        = trim((string) get_user_meta($userId, 'sccc_business_name', true));
            $businessDescription = trim((string) get_user_meta($userId, 'sccc_business_description', true));
            $businessWebsite     = trim((string) get_user_meta($userId, 'sccc_business_website', true));
            $businessPhone       = $this->digitsOnlyPhone(
                (string) get_user_meta($userId, 'sccc_business_phone', true)
            );
            $businessEmail       = trim((string) get_user_meta($userId, 'sccc_business_email', true));
            $businessLogoId      = (int) get_user_meta($userId, 'sccc_business_logo_id', true);
            $categoryValues      = get_user_meta($userId, 'sccc_business_categories', true);

            if ($businessName === '') {
                continue;
            }

            $categoryValues = is_array($categoryValues) ? $categoryValues : [];
            $primaryKey     = isset($categoryValues[0]) ? (string) $categoryValues[0] : '';
            $primaryLabel   = $primaryKey !== '' && isset($categories[$primaryKey])
                ? $categories[$primaryKey]
                : '';

            $logoUrl = $businessLogoId > 0
                ? wp_get_attachment_image_url($businessLogoId, 'medium')
                : '';

            $ownerName      = $this->ownerDisplayName($user);
            $ownerAvatarUrl = get_avatar_url($userId, ['size' => 112]);

            $cards[] = [
                'business_id'             => $userId,
                'business_name'           => $businessName,
                'business_description'    => $businessDescription,
                'business_website'        => $businessWebsite,
                'business_phone'          => $businessPhone,
                'business_email'          => $businessEmail,
                'business_logo_url'       => $logoUrl ?: '',
                'business_logo_alt'       => $businessName . ' logo',
                'primary_category_key'    => $primaryKey,
                'primary_category_label'  => $primaryLabel,
                'owner_name'              => $ownerName,
                'owner_avatar_url'        => is_string($ownerAvatarUrl) ? $ownerAvatarUrl : '',
                'owner_avatar_alt'        => $ownerName,
                'owner_avatar'            => get_avatar($userId, 56, '', $ownerName, [
                    'class'   => 'sccc-business-directory__owner-avatar-image',
                    'loading' => 'lazy',
                ]),
            ];
        }

        return $cards;
    }

    /**
     * Remove every non-digit character from a stored business phone number.
     *
     * Why:
     * Members may enter parentheses, spaces, periods, dashes, or a leading plus
     * sign. The directory keeps one predictable digits-only value for display
     * formatting and the clickable `tel:` link.
     */
    protected function digitsOnlyPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return is_string($digits) ? $digits : '';
    }

    /**
     * Build pagination links for the custom page template.
     *
     * Query-string pagination is used deliberately so the directory can live on
     * a normal WordPress page without depending on special rewrite handling.
     *
     * @param  array<string, mixed>  $filters
     * @param  int  $totalPages
     * @return array<int, string>
     */
    protected function pagination(array $filters, int $totalPages): array
    {
        if ($totalPages <= 1) {
            return [];
        }

        $baseUrl = $this->currentBaseUrl();
        $baseUrl = add_query_arg([
            'q'        => $filters['q'] !== '' ? $filters['q'] : null,
            'category' => $filters['category'] !== '' ? $filters['category'] : null,
            'service'  => $filters['service'] !== '' ? $filters['service'] : null,
            'paged'    => '%#%',
        ], $baseUrl);

        $links = paginate_links([
            'base'      => $baseUrl,
            'format'    => '',
            'current'   => max(1, (int) $filters['paged']),
            'total'     => $totalPages,
            'type'      => 'array',
            'prev_text' => __('Previous', 'sage'),
            'next_text' => __('Next', 'sage'),
            'mid_size'  => 1,
            'end_size'  => 1,
        ]);

        return is_array($links) ? $links : [];
    }

    /**
     * Build a friendly summary line for the current result set.
     *
     * @param  array<string, mixed>  $filters
     * @param  int  $totalResults
     * @return string
     */
    protected function summary(array $filters, int $totalResults): string
    {
        $parts = [];

        if ($filters['q'] !== '') {
            $parts[] = sprintf(__('matching “%s”', 'sage'), $filters['q']);
        }

        if ($filters['category'] !== '' && isset($this->businessCategoryOptions()[$filters['category']])) {
            $parts[] = sprintf(
                __('in %s', 'sage'),
                $this->businessCategoryOptions()[$filters['category']]
            );
        }

        if ($filters['service'] !== '' && isset($this->businessServiceOptions()[$filters['service']])) {
            $parts[] = sprintf(
                __('offering %s', 'sage'),
                $this->businessServiceOptions()[$filters['service']]
            );
        }

        if (empty($parts)) {
            return sprintf(
                _n('%s member business found', '%s member businesses found', $totalResults, 'sage'),
                number_format_i18n($totalResults)
            );
        }

        return sprintf(
            _n('%1$s member business found %2$s', '%1$s member businesses found %2$s', $totalResults, 'sage'),
            number_format_i18n($totalResults),
            implode(' ', $parts)
        );
    }

    /**
     * Provide a stable owner display name.
     *
     * Preference order:
     * - display_name
     * - first + last name
     * - user_login
     */
    protected function ownerDisplayName(\WP_User $user): string
    {
        $displayName = trim((string) $user->display_name);

        if ($displayName !== '') {
            return $displayName;
        }

        $first = trim((string) get_user_meta((int) $user->ID, 'first_name', true));
        $last  = trim((string) get_user_meta((int) $user->ID, 'last_name', true));
        $full  = trim($first . ' ' . $last);

        return $full !== '' ? $full : (string) $user->user_login;
    }

    /**
     * Return the canonical URL for the current page without directory params.
     *
     * We intentionally strip only the params managed by this feature so the page
     * can continue to live within the normal site routing.
     */
    protected function currentBaseUrl(): string
    {
        $url = get_permalink();

        return remove_query_arg([
            'q',
            'category',
            'service',
            'paged',
        ], $url ?: home_url('/'));
    }

    /**
     * Business category labels.
     *
     * IMPORTANT:
     * These keys must stay aligned with MemberProfile.php.
     * This duplication is intentional for now so the directory UI has a stable,
     * theme-side source of labels without reaching into ACF field objects.
     *
     * @return array<string, string>
     */
    protected function businessCategoryOptions(): array
    {
        return [
            'auto'              => 'Automotive',
            'auto_dealer'       => 'Auto Dealer',
            'auto_services'     => 'Auto Services / Repair',
            'auto_detailing'    => 'Auto Detailing',
            'auto_wrap_paint'   => 'Wrap / Paint / PPF',
            'auto_wheels_tires' => 'Wheels & Tires',
            'auto_audio'        => 'Car Audio / Electronics',
            'auto_performance'  => 'Performance / Tuning',
            'auto_body'         => 'Body Shop / Collision',
            'it_tech'           => 'IT / Tech Services',
            'marketing'         => 'Marketing / Advertising',
            'photography_media' => 'Photography / Video / Media',
            'real_estate'       => 'Real Estate',
            'finance_insurance' => 'Finance / Insurance',
            'legal'             => 'Legal Services',
            'medical'           => 'Medical / Dental / Wellness',
            'beauty'            => 'Beauty / Spa / Salon',
            'fitness'           => 'Fitness / Training',
            'home_services'     => 'Home Services',
            'construction'      => 'Construction / Trades',
            'hospitality'       => 'Restaurants / Bars / Hospitality',
            'retail'            => 'Retail / Shopping',
            'events_venues'     => 'Events / Venues',
            'travel'            => 'Travel / Transportation',
            'education'         => 'Education / Lessons',
            'nonprofit'         => 'Non-Profit / Community',
            'professional'      => 'Professional Services (Other)',
            'other'             => 'Other',
        ];
    }

    /**
     * Business service labels.
     *
     * IMPORTANT:
     * These keys must stay aligned with MemberProfile.php.
     *
     * @return array<string, string>
     */
    protected function businessServiceOptions(): array
    {
        return [
            'web_development'    => 'Web Development',
            'wordpress'          => 'WordPress Development',
            'web_maintenance'    => 'Website Maintenance',
            'hosting_domains'    => 'Hosting / Domains',
            'seo'                => 'SEO',
            'ppc_ads'            => 'PPC / Paid Ads',
            'social_media'       => 'Social Media Management',
            'graphic_design'     => 'Graphic Design',
            'it_support'         => 'IT Support',
            'managed_it'         => 'Managed IT Services',
            'networking_wifi'    => 'Networking / Wi-Fi',
            'cybersecurity'      => 'Cybersecurity',
            'app_development'    => 'App Development',
            'automation_ai'      => 'Automation / AI Workflows',
            'oil_change'         => 'Oil Change',
            'brakes'             => 'Brakes',
            'alignment'          => 'Alignment',
            'diagnostics'        => 'Diagnostics',
            'repair_maintenance' => 'Repair / Maintenance',
            'detailing_interior' => 'Interior Detailing',
            'detailing_exterior' => 'Exterior Detailing',
            'ceramic_coating'    => 'Ceramic Coating',
            'ppf'                => 'Paint Protection Film (PPF)',
            'vinyl_wrap'         => 'Vinyl Wrap',
            'paint_bodywork'     => 'Paint / Bodywork',
            'wheels'             => 'Wheels',
            'tires'              => 'Tires',
            'audio_install'      => 'Audio Install',
            'dyno_tune'          => 'Dyno / Tuning',
            'performance_parts'  => 'Performance Parts',
            'tint'               => 'Window Tint',
            'plumbing'           => 'Plumbing',
            'electrical'         => 'Electrical',
            'hvac'               => 'HVAC',
            'landscaping'        => 'Landscaping',
            'cleaning'           => 'Cleaning Services',
            'remodeling'         => 'Remodeling',
            'roofing'            => 'Roofing',
            'accounting'         => 'Accounting / Bookkeeping',
            'insurance'          => 'Insurance',
            'legal_services'     => 'Legal Services',
            'real_estate_agent'  => 'Real Estate Agent',
            'mortgage'           => 'Mortgage / Lending',
            'photo_video'        => 'Photo / Video',
            'dj_music'           => 'DJ / Music',
            'event_space'        => 'Event Space',
            'catering'           => 'Catering',
            'delivery'           => 'Delivery',
            'consulting'         => 'Consulting',
            'training'           => 'Training / Coaching',
            'other'              => 'Other',
        ];
    }
}