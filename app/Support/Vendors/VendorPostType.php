<?php

/**
 * File path + filename: app/Support/Vendors/VendorPostType.php
 *
 * Purpose:
 * - Register the private `vendor` post type (one record per business) and the
 *   color-coded `vendor_type` taxonomy.
 * - Vendors admin list: logo, business, type (color pill + row accent),
 *   status, shows (colored by date), last show date, contact, vendor since.
 *   Sortable by type, status, last show date and vendor since; filterable by
 *   status, type, show and "OK to feature".
 * - "Vendor record" side box: sign-up link / resend, attribution, agreement.
 * - Keep flat, sortable meta in sync (Vendors::syncIndexes) whenever a vendor
 *   or one of its shows is saved.
 *
 * Why this file exists:
 * - Vendors are internal records, never public URLs (`public => false`).
 * - Editing is mapped to `sccc_edit_shared_club_content` by OperationalRoles.
 */

namespace App\Support\Vendors;

use WP_Post;
use WP_Query;

defined('ABSPATH') || exit;

final class VendorPostType
{
    private const FILTER_STATUS = 'vendor_status_filter';

    private const FILTER_TYPE = 'vendor_type_filter';

    private const FILTER_SHOW = 'vendor_show_filter';

    private const FILTER_FEATURE = 'vendor_feature_filter';

    public static function register(): void
    {
        add_action('init', [self::class, 'registerPostType'], 5);
        add_action('init', [self::class, 'registerTaxonomy'], 5);
        add_action('init', [self::class, 'ensureDefaultTypes'], 15);

        add_filter('manage_'.Vendors::POST_TYPE.'_posts_columns', [self::class, 'columns']);
        add_action('manage_'.Vendors::POST_TYPE.'_posts_custom_column', [self::class, 'renderColumn'], 10, 2);
        add_filter('manage_edit-'.Vendors::POST_TYPE.'_sortable_columns', [self::class, 'sortableColumns']);
        add_action('restrict_manage_posts', [self::class, 'renderFilters']);
        add_action('pre_get_posts', [self::class, 'applyFiltersAndSorting']);
        add_filter('post_class', [self::class, 'rowClasses'], 10, 3);

        add_filter('enter_title_here', [self::class, 'titlePlaceholder'], 10, 2);
        add_action('add_meta_boxes_'.Vendors::POST_TYPE, [self::class, 'addMetaBoxes']);
        add_action('admin_menu', [self::class, 'addPendingBubble'], 999);
        add_action('admin_head', [self::class, 'printAdminStyles']);

        // Keep derived data in sync after ACF has saved the vendor.
        add_action('acf/save_post', [self::class, 'afterVendorSave'], 30);
        // Show date / title changes flow into the vendors' sortable meta.
        add_action('save_post_'.Vendors::EVENT_POST_TYPE, [self::class, 'afterEventSave'], 30);
    }

    /* -------------------------------------------------------------------------
     * Registration
     * ---------------------------------------------------------------------- */

    public static function registerPostType(): void
    {
        register_post_type(Vendors::POST_TYPE, [
            'labels' => [
                'name' => __('Vendors', 'sccc'),
                'singular_name' => __('Vendor', 'sccc'),
                'add_new' => __('Add Vendor', 'sccc'),
                'add_new_item' => __('Add Vendor', 'sccc'),
                'edit_item' => __('Edit Vendor', 'sccc'),
                'new_item' => __('New Vendor', 'sccc'),
                'search_items' => __('Search Vendors', 'sccc'),
                'not_found' => __('No vendors found', 'sccc'),
                'not_found_in_trash' => __('No vendors found in Trash', 'sccc'),
                'all_items' => __('All Vendors', 'sccc'),
                'menu_name' => __('Vendors', 'sccc'),
                'featured_image' => __('Logo', 'sccc'),
            ],
            'public' => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => false,
            'show_in_rest' => false,
            'menu_icon' => 'dashicons-store',
            'menu_position' => 26,
            'supports' => ['title', 'thumbnail'],
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
        ]);
    }

    public static function registerTaxonomy(): void
    {
        register_taxonomy(Vendors::TAXONOMY_TYPE, [Vendors::POST_TYPE], [
            'labels' => [
                'name' => __('Vendor Types', 'sccc'),
                'singular_name' => __('Vendor Type', 'sccc'),
                'menu_name' => __('Vendor Types', 'sccc'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => false,
            'show_in_rest' => false,
            'hierarchical' => false,
            'meta_box_cb' => false,
            'rewrite' => false,
        ]);
    }

    public static function ensureDefaultTypes(): void
    {
        if (! taxonomy_exists(Vendors::TAXONOMY_TYPE)) {
            return;
        }

        foreach (Vendors::defaultTypes() as $slug => [$name, $color]) {
            if (term_exists($slug, Vendors::TAXONOMY_TYPE)) {
                continue;
            }

            $term = wp_insert_term($name, Vendors::TAXONOMY_TYPE, ['slug' => $slug]);
            if (! is_wp_error($term)) {
                update_term_meta((int) $term['term_id'], 'vendor_type_color', $color);
            }
        }
    }

    /* -------------------------------------------------------------------------
     * Sync
     * ---------------------------------------------------------------------- */

    /** @param int|string $postId */
    public static function afterVendorSave($postId): void
    {
        if (! is_numeric($postId) || ! Vendors::isVendor((int) $postId)) {
            return;
        }

        $id = (int) $postId;
        $logo = (int) get_post_meta($id, Vendors::FIELD_LOGO, true);

        if ($logo && (int) get_post_thumbnail_id($id) !== $logo) {
            set_post_thumbnail($id, $logo);
        }

        Vendors::syncIndexes($id);
    }

    public static function afterEventSave(int $eventId): void
    {
        if (wp_is_post_revision($eventId)) {
            return;
        }

        $vendorIds = get_posts([
            'post_type' => Vendors::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [['key' => Vendors::META_SHOW_ID, 'value' => $eventId]],
        ]);

        foreach ($vendorIds as $vendorId) {
            Vendors::syncIndexes((int) $vendorId);
        }
    }

    /* -------------------------------------------------------------------------
     * Admin list
     * ---------------------------------------------------------------------- */

    /**
     * @param  array<string, string>  $columns
     * @return array<string, string>
     */
    public static function columns(array $columns): array
    {
        return [
            'cb' => $columns['cb'] ?? '<input type="checkbox" />',
            'vendor_logo' => '<span class="screen-reader-text">'.esc_html__('Logo', 'sccc').'</span>',
            'title' => __('Business', 'sccc'),
            'vendor_type' => __('Type', 'sccc'),
            'vendor_status' => __('Status', 'sccc'),
            'vendor_shows' => __('Shows', 'sccc'),
            'vendor_last_show' => __('Last show', 'sccc'),
            'vendor_contact' => __('Contact', 'sccc'),
            'date' => __('Vendor since', 'sccc'),
        ];
    }

    public static function renderColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'vendor_logo':
                $logo = Vendors::logoId($postId);
                echo $logo ? wp_get_attachment_image($logo, [48, 48], false, ['class' => 'sccc-vendor-logo']) : '<span class="sccc-vendor-logo sccc-vendor-logo--empty"></span>';
                break;

            case 'vendor_type':
                $term = Vendors::typeTerm($postId);
                echo $term ? self::typePill($term) : '—';
                break;

            case 'vendor_status':
                echo self::statusPill(Vendors::status($postId)); // phpcs:ignore WordPress.Security.EscapeOutput
                if ((bool) get_post_meta($postId, Vendors::FIELD_FEATURE_OK, true)) {
                    echo '<br><span class="sccc-vendor-pill sccc-vendor-pill--feature">'.esc_html__('OK to feature', 'sccc').'</span>';
                }
                break;

            case 'vendor_shows':
                $rows = Vendors::history($postId);
                if (! $rows) {
                    echo '—';
                    break;
                }
                echo '<ul class="sccc-vendor-shows">';
                foreach (array_reverse($rows) as $row) {
                    $eventId = (int) ($row[Vendors::ROW_EVENT] ?? 0);
                    $status = (string) ($row[Vendors::ROW_STATUS] ?? '');
                    printf(
                        '<li class="sccc-vendor-show sccc-vendor-show--%s"><span class="sccc-vendor-show__title">%s</span> <span class="sccc-vendor-pill sccc-vendor-pill--show-%s">%s</span></li>',
                        esc_attr(self::timing($eventId)),
                        esc_html(Vendors::eventLabel($eventId, false)),
                        esc_attr($status),
                        esc_html(Vendors::showStatusLabel($status))
                    );
                }
                echo '</ul>';
                break;

            case 'vendor_last_show':
                $date = (string) get_post_meta($postId, Vendors::META_LAST_SHOW_DATE, true);
                echo $date !== '' ? esc_html(mysql2date('M j, Y', $date)) : '—';
                break;

            case 'vendor_contact':
                $email = Vendors::contactEmail($postId);
                $phone = (string) get_post_meta($postId, Vendors::FIELD_PHONE, true);
                echo esc_html(Vendors::contactName($postId) ?: '—');
                if ($email !== '') {
                    echo '<br><a href="mailto:'.esc_attr($email).'">'.esc_html($email).'</a>';
                }
                if ($phone !== '') {
                    echo '<br>'.esc_html($phone);
                }
                break;
        }
    }

    /**
     * @param  array<string, string>  $columns
     * @return array<string, string>
     */
    public static function sortableColumns(array $columns): array
    {
        $columns['vendor_type'] = 'vendor_type';
        $columns['vendor_status'] = 'vendor_status';
        $columns['vendor_last_show'] = 'vendor_last_show';
        $columns['date'] = 'date';

        return $columns;
    }

    public static function renderFilters(string $postType): void
    {
        if ($postType !== Vendors::POST_TYPE) {
            return;
        }

        $status = self::query(self::FILTER_STATUS);
        $type = self::query(self::FILTER_TYPE);
        $show = absint(self::query(self::FILTER_SHOW));
        $feature = self::query(self::FILTER_FEATURE);

        echo '<select name="'.esc_attr(self::FILTER_STATUS).'"><option value="">'.esc_html__('All statuses', 'sccc').'</option>';
        foreach (Vendors::statuses() as $slug => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($slug), selected($status, $slug, false), esc_html($label));
        }
        echo '</select>';

        echo '<select name="'.esc_attr(self::FILTER_TYPE).'"><option value="">'.esc_html__('All types', 'sccc').'</option>';
        foreach ((array) get_terms(['taxonomy' => Vendors::TAXONOMY_TYPE, 'hide_empty' => false]) as $term) {
            if ($term instanceof \WP_Term) {
                printf('<option value="%s"%s>%s</option>', esc_attr($term->slug), selected($type, $term->slug, false), esc_html($term->name));
            }
        }
        echo '</select>';

        global $wpdb;
        $showIds = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
            Vendors::META_SHOW_ID
        ));
        usort($showIds, static fn ($a, $b): int => Vendors::eventTimestamp((int) $b) <=> Vendors::eventTimestamp((int) $a));

        echo '<select name="'.esc_attr(self::FILTER_SHOW).'"><option value="">'.esc_html__('All shows', 'sccc').'</option>';
        foreach ($showIds as $eventId) {
            printf('<option value="%d"%s>%s</option>', (int) $eventId, selected($show, (int) $eventId, false), esc_html(Vendors::eventLabel((int) $eventId, false)));
        }
        echo '</select>';

        printf(
            '<select name="%s"><option value="">%s</option><option value="1"%s>%s</option></select>',
            esc_attr(self::FILTER_FEATURE),
            esc_html__('Any feature permission', 'sccc'),
            selected($feature, '1', false),
            esc_html__('OK to feature', 'sccc')
        );
    }

    public static function applyFiltersAndSorting(WP_Query $query): void
    {
        if (! is_admin() || ! $query->is_main_query() || $query->get('post_type') !== Vendors::POST_TYPE) {
            return;
        }

        $metaQuery = (array) $query->get('meta_query');

        $status = self::query(self::FILTER_STATUS);
        if ($status !== '' && array_key_exists($status, Vendors::statuses())) {
            $metaQuery[] = ['key' => Vendors::FIELD_STATUS, 'value' => $status];
        }

        $show = absint(self::query(self::FILTER_SHOW));
        if ($show > 0) {
            $metaQuery[] = ['key' => Vendors::META_SHOW_ID, 'value' => $show];
        }

        if (self::query(self::FILTER_FEATURE) === '1') {
            $metaQuery[] = ['key' => Vendors::FIELD_FEATURE_OK, 'value' => '1'];
        }

        if ($metaQuery) {
            $query->set('meta_query', $metaQuery);
        }

        $type = self::query(self::FILTER_TYPE);
        if ($type !== '') {
            $query->set('tax_query', [['taxonomy' => Vendors::TAXONOMY_TYPE, 'field' => 'slug', 'terms' => $type]]);
        }

        $sortMeta = [
            'vendor_type' => Vendors::META_TYPE_NAME,
            'vendor_status' => Vendors::FIELD_STATUS,
            'vendor_last_show' => Vendors::META_LAST_SHOW_DATE,
        ];

        $orderby = (string) $query->get('orderby');
        if (isset($sortMeta[$orderby])) {
            $query->set('meta_key', $sortMeta[$orderby]);
            $query->set('orderby', 'meta_value');
        }
    }

    /**
     * Type color class on each list row (colored left edge).
     *
     * @param  array<int, string>  $classes
     * @return array<int, string>
     */
    public static function rowClasses(array $classes, $class, int $postId): array
    {
        if (is_admin() && get_post_type($postId) === Vendors::POST_TYPE) {
            $slug = Vendors::typeSlug($postId);
            if ($slug !== '') {
                $classes[] = 'sccc-vendor-row--'.sanitize_html_class($slug);
            }
        }

        return $classes;
    }

    public static function titlePlaceholder(string $title, WP_Post $post): string
    {
        return $post->post_type === Vendors::POST_TYPE ? __('Business name', 'sccc') : $title;
    }

    public static function addPendingBubble(): void
    {
        global $menu;

        $count = (int) (new WP_Query([
            'post_type' => Vendors::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [['key' => Vendors::FIELD_STATUS, 'value' => Vendors::STATUS_PENDING]],
        ]))->found_posts;

        if ($count < 1 || ! is_array($menu)) {
            return;
        }

        foreach ($menu as $index => $item) {
            if (($item[2] ?? '') === 'edit.php?post_type='.Vendors::POST_TYPE) {
                $menu[$index][0] .= sprintf(' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>', $count);
                break;
            }
        }
    }

    /* -------------------------------------------------------------------------
     * "Vendor record" meta box
     * ---------------------------------------------------------------------- */

    public static function addMetaBoxes(): void
    {
        add_meta_box('sccc-vendor-record', __('Vendor record', 'sccc'), [self::class, 'renderRecordBox'], Vendors::POST_TYPE, 'side', 'high');
    }

    public static function renderRecordBox(WP_Post $post): void
    {
        $id = (int) $post->ID;
        $status = Vendors::status($id);

        echo '<p>'.self::statusPill($status); // phpcs:ignore WordPress.Security.EscapeOutput
        $term = Vendors::typeTerm($id);
        if ($term) {
            echo ' '.self::typePill($term); // phpcs:ignore WordPress.Security.EscapeOutput
        }
        echo '</p>';

        $rows = [
            __('Vendor since', 'sccc') => esc_html(get_the_date('M j, Y', $post)),
        ];

        $entryId = (int) get_post_meta($id, Vendors::META_ENTRY_ID, true);
        $formId = (int) get_post_meta($id, Vendors::META_FORM_ID, true);
        if ($entryId && $formId) {
            $rows[__('Application', 'sccc')] = '<a href="'.esc_url(admin_url("admin.php?page=gf_entries&view=entry&id={$formId}&lid={$entryId}")).'">#'.$entryId.'</a>';
        }

        $approved = (string) get_post_meta($id, Vendors::META_APPROVED_AT, true);
        if ($approved !== '') {
            $rows[__('Approved', 'sccc')] = esc_html(mysql2date('M j, Y', $approved));
        }

        $emailed = (string) get_post_meta($id, Vendors::META_LINK_EMAILED_AT, true);
        if ($emailed !== '') {
            $rows[__('Link emailed', 'sccc')] = esc_html(mysql2date('M j, Y g:i a', $emailed));
        }

        foreach ([
            Vendors::META_HEARD_ABOUT => __('Heard about us', 'sccc'),
            Vendors::META_REFERRAL_SOURCE => __('Source', 'sccc'),
            Vendors::META_SIGNATURE => __('Agreement signed by', 'sccc'),
        ] as $metaKey => $label) {
            $value = trim((string) get_post_meta($id, $metaKey, true));
            if ($value !== '') {
                $rows[$label] = esc_html($value);
            }
        }

        $optin = get_post_meta($id, Vendors::META_MARKETING_OPTIN, true);
        if ($optin !== '') {
            $rows[__('Email updates', 'sccc')] = $optin ? esc_html__('Opted in', 'sccc') : esc_html__('No', 'sccc');
        }

        echo '<table class="sccc-vendor-record">';
        foreach ($rows as $label => $valueHtml) {
            echo '<tr><th>'.esc_html($label).'</th><td>'.$valueHtml.'</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- values escaped above.
        }
        echo '</table>';

        $blocked = Vendors::foodBlockedShows($id);
        foreach ($blocked as $eventId) {
            echo '<p class="sccc-vendor-warning">'.esc_html(sprintf(__('Food vendor — %s does not allow food vendors.', 'sccc'), Vendors::eventTitle($eventId))).'</p>';
        }

        $payable = Vendors::payableShows($id);

        if ($payable) {
            if (Vendors::pageUrl('vendor_payment_page') === '') {
                echo '<p class="sccc-vendor-warning">'.esc_html__('No vendor sign-up page is set (Theme Settings → Vendor Settings).', 'sccc').'</p>';
            } else {
                echo '<p><strong>'.esc_html__('Can sign up / pay for:', 'sccc').'</strong></p><ul class="sccc-vendor-payable">';
                foreach ($payable as $eventId => $fee) {
                    echo '<li>'.esc_html(Vendors::eventTitle((int) $eventId).' — '.self::money($fee)).'</li>';
                }
                echo '</ul>';

                $sendUrl = wp_nonce_url(
                    admin_url('admin-post.php?action='.VendorApproval::SEND_LINK_ACTION.'&vendor='.$id),
                    VendorApproval::SEND_LINK_ACTION.'_'.$id
                );
                echo '<p><a class="button" href="'.esc_url($sendUrl).'">'.esc_html__('Email sign-up / payment link', 'sccc').'</a></p>';
            }
        }
    }

    /* -------------------------------------------------------------------------
     * Presentation helpers
     * ---------------------------------------------------------------------- */

    public static function statusPill(string $status): string
    {
        return '<span class="sccc-vendor-pill sccc-vendor-pill--'.esc_attr($status).'">'.esc_html(Vendors::statusLabel($status)).'</span>';
    }

    public static function typePill(\WP_Term $term): string
    {
        $color = Vendors::typeColor($term);

        return '<span class="sccc-vendor-pill sccc-vendor-pill--type" style="--sccc-type:'.esc_attr($color).'">'.esc_html($term->name).'</span>';
    }

    /** past | soon (≤ 14 days) | upcoming */
    private static function timing(int $eventId): string
    {
        $ts = Vendors::eventTimestamp($eventId);

        if (! $ts || $ts < time() - DAY_IN_SECONDS) {
            return 'past';
        }

        return $ts <= time() + 14 * DAY_IN_SECONDS ? 'soon' : 'upcoming';
    }

    private static function money(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    private static function query(string $key): string
    {
        return isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    }

    public static function printAdminStyles(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (! $screen || ($screen->post_type ?? '') !== Vendors::POST_TYPE) {
            return;
        }

        $rowColors = '';
        foreach ((array) get_terms(['taxonomy' => Vendors::TAXONOMY_TYPE, 'hide_empty' => false]) as $term) {
            if ($term instanceof \WP_Term) {
                $rowColors .= '.wp-list-table tr.sccc-vendor-row--'.sanitize_html_class($term->slug).' th.check-column{box-shadow:inset 4px 0 0 '.esc_attr(Vendors::typeColor($term)).'}';
            }
        }

        echo '<style>
            .sccc-vendor-pill{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600;line-height:1.7;white-space:nowrap}
            .sccc-vendor-pill--pending{background:#fef3c7;color:#92400e}
            .sccc-vendor-pill--approved{background:#dcfce7;color:#14532d}
            .sccc-vendor-pill--rejected{background:#fee2e2;color:#7f1d1d}
            .sccc-vendor-pill--blocked{background:#e2e8f0;color:#334155}
            .sccc-vendor-pill--feature{background:#ede9fe;color:#5b21b6;margin-top:4px}
            .sccc-vendor-pill--type{background:color-mix(in srgb,var(--sccc-type) 16%,#fff);color:color-mix(in srgb,var(--sccc-type) 80%,#000);border:1px solid color-mix(in srgb,var(--sccc-type) 45%,#fff)}
            .sccc-vendor-pill--show-pending{background:#fef3c7;color:#92400e}
            .sccc-vendor-pill--show-awaiting_payment{background:#dbeafe;color:#1e3a8a}
            .sccc-vendor-pill--show-active{background:#dcfce7;color:#14532d}
            .sccc-vendor-pill--show-declined,.sccc-vendor-pill--show-cancelled{background:#f1f5f9;color:#475569}
            .sccc-vendor-shows{margin:0}.sccc-vendor-shows li{margin:0 0 4px}
            .sccc-vendor-show--past .sccc-vendor-show__title{color:#94a3b8}
            .sccc-vendor-show--soon .sccc-vendor-show__title{color:#b45309;font-weight:600}
            .sccc-vendor-show--upcoming .sccc-vendor-show__title{color:#1d4ed8;font-weight:600}
            .sccc-vendor-logo{width:48px;height:48px;object-fit:contain;border-radius:6px;background:#f8fafc;display:inline-block}
            .sccc-vendor-logo--empty{border:1px dashed #cbd5e1}
            .column-vendor_logo{width:56px}.column-vendor_status{width:150px}.column-vendor_type{width:190px}.column-vendor_last_show{width:110px}
            .sccc-vendor-warning{background:#fef3c7;border-left:4px solid #f59e0b;padding:8px 10px;margin:8px 0}
            .sccc-vendor-record{width:100%;border-collapse:collapse;margin:8px 0}
            .sccc-vendor-record th{text-align:left;font-weight:600;padding:4px 8px 4px 0;vertical-align:top;width:45%}
            .sccc-vendor-record td{padding:4px 0;word-break:break-word}
            .sccc-vendor-payable{margin:0 0 8px 1em;list-style:disc}
            '.$rowColors.'
        </style>';
    }
}
