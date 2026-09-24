<?php

/**
 * File path + filename: app/Support/Vendors/VendorPostType.php
 *
 * Purpose:
 * - Register the private `vendor` post type (one post = one vendor application
 *   for one show) and the `vendor_type` taxonomy.
 * - Seed the default vendor types.
 * - Make the Vendors admin list easy to work: status / type / show / contact /
 *   fee columns, status + show filters, a food-vendor conflict warning, and a
 *   "pending review" count bubble on the admin menu.
 * - Add an "Application record" side meta box with the Gravity Forms entry,
 *   attribution, fee, payment link and payment details.
 *
 * Why this file exists:
 * - Vendors are internal records: they are never public URLs, so the CPT is
 *   `public => false` with `show_ui => true`.
 * - Editing capability is mapped to `sccc_edit_shared_club_content` by
 *   OperationalRoles, so club officers can review vendors without admin rights.
 */

namespace App\Support\Vendors;

use WP_Post;
use WP_Query;

defined('ABSPATH') || exit;

final class VendorPostType
{
    private const FILTER_STATUS = 'vendor_status_filter';

    private const FILTER_EVENT = 'vendor_event_filter';

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

        add_filter('enter_title_here', [self::class, 'titlePlaceholder'], 10, 2);
        add_action('add_meta_boxes_'.Vendors::POST_TYPE, [self::class, 'addMetaBoxes']);
        add_action('admin_menu', [self::class, 'addPendingBubble'], 999);
        add_action('admin_head', [self::class, 'printAdminStyles']);
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
                'add_new_item' => __('Add Vendor Application', 'sccc'),
                'edit_item' => __('Review Vendor Application', 'sccc'),
                'new_item' => __('New Vendor Application', 'sccc'),
                'search_items' => __('Search Vendors', 'sccc'),
                'not_found' => __('No vendor applications found', 'sccc'),
                'not_found_in_trash' => __('No vendor applications found in Trash', 'sccc'),
                'all_items' => __('All Applications', 'sccc'),
                'menu_name' => __('Vendors', 'sccc'),
            ],
            'public' => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => false,
            'show_in_rest' => false, // Classic edit screen keeps the ACF review workflow simple.
            'menu_icon' => 'dashicons-store',
            'menu_position' => 26,
            'supports' => ['title'],
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
            'meta_box_cb' => false, // Assigned through the ACF taxonomy field instead.
            'rewrite' => false,
        ]);
    }

    public static function ensureDefaultTypes(): void
    {
        if (! taxonomy_exists(Vendors::TAXONOMY_TYPE)) {
            return;
        }

        foreach (Vendors::defaultTypes() as $slug => $name) {
            if (! term_exists($slug, Vendors::TAXONOMY_TYPE)) {
                wp_insert_term($name, Vendors::TAXONOMY_TYPE, ['slug' => $slug]);
            }
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
            'title' => __('Business', 'sccc'),
            'vendor_status' => __('Status', 'sccc'),
            'vendor_type' => __('Type', 'sccc'),
            'vendor_show' => __('Show', 'sccc'),
            'vendor_contact' => __('Contact', 'sccc'),
            'vendor_fee' => __('Fee', 'sccc'),
            'date' => __('Applied', 'sccc'),
        ];
    }

    public static function renderColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'vendor_status':
                echo self::statusPill(Vendors::status($postId)); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in statusPill().
                if (Vendors::hasFoodConflict($postId)) {
                    echo '<br><span class="sccc-vendor-pill sccc-vendor-pill--warning" title="'.esc_attr__('This venue does not allow food vendors.', 'sccc').'">'.esc_html__('Food not allowed', 'sccc').'</span>';
                }
                break;

            case 'vendor_type':
                echo esc_html(Vendors::typeLabel($postId));
                break;

            case 'vendor_show':
                $eventId = Vendors::eventId($postId);
                echo $eventId ? esc_html(Vendors::eventLabel($eventId, false)) : '—';
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

            case 'vendor_fee':
                $status = Vendors::status($postId);
                if ($status === Vendors::STATUS_PAID) {
                    echo esc_html(Vendors::money((float) get_post_meta($postId, Vendors::META_PAID_AMOUNT, true))).' <small>'.esc_html__('paid', 'sccc').'</small>';
                } elseif ($status === Vendors::STATUS_APPROVED) {
                    echo esc_html(Vendors::money(Vendors::feeDue($postId))).' <small>'.esc_html__('due', 'sccc').'</small>';
                } else {
                    echo esc_html(Vendors::money(Vendors::resolveFee($postId)));
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
        $columns['vendor_status'] = 'vendor_status';

        return $columns;
    }

    public static function renderFilters(string $postType): void
    {
        if ($postType !== Vendors::POST_TYPE) {
            return;
        }

        $currentStatus = isset($_GET[self::FILTER_STATUS]) ? sanitize_key(wp_unslash($_GET[self::FILTER_STATUS])) : '';
        $currentEvent = isset($_GET[self::FILTER_EVENT]) ? absint($_GET[self::FILTER_EVENT]) : 0;

        echo '<select name="'.esc_attr(self::FILTER_STATUS).'">';
        echo '<option value="">'.esc_html__('All statuses', 'sccc').'</option>';
        foreach (Vendors::statuses() as $slug => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($slug), selected($currentStatus, $slug, false), esc_html($label));
        }
        echo '</select>';

        global $wpdb;
        $eventIds = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s AND p.post_type = %s AND pm.meta_value <> ''",
            Vendors::FIELD_EVENT,
            Vendors::POST_TYPE
        ));

        echo '<select name="'.esc_attr(self::FILTER_EVENT).'">';
        echo '<option value="">'.esc_html__('All shows', 'sccc').'</option>';
        foreach (array_map('intval', (array) $eventIds) as $eventId) {
            printf('<option value="%d"%s>%s</option>', $eventId, selected($currentEvent, $eventId, false), esc_html(Vendors::eventLabel($eventId, false)));
        }
        echo '</select>';
    }

    public static function applyFiltersAndSorting(WP_Query $query): void
    {
        if (! is_admin() || ! $query->is_main_query() || $query->get('post_type') !== Vendors::POST_TYPE) {
            return;
        }

        $metaQuery = (array) $query->get('meta_query');

        $status = isset($_GET[self::FILTER_STATUS]) ? sanitize_key(wp_unslash($_GET[self::FILTER_STATUS])) : '';
        if ($status !== '' && array_key_exists($status, Vendors::statuses())) {
            $metaQuery[] = ['key' => Vendors::FIELD_STATUS, 'value' => $status];
        }

        $eventId = isset($_GET[self::FILTER_EVENT]) ? absint($_GET[self::FILTER_EVENT]) : 0;
        if ($eventId > 0) {
            $metaQuery[] = ['key' => Vendors::FIELD_EVENT, 'value' => $eventId];
        }

        if ($metaQuery) {
            $query->set('meta_query', $metaQuery);
        }

        if ($query->get('orderby') === 'vendor_status') {
            $query->set('meta_key', Vendors::FIELD_STATUS);
            $query->set('orderby', 'meta_value');
        }
    }

    public static function titlePlaceholder(string $title, WP_Post $post): string
    {
        return $post->post_type === Vendors::POST_TYPE ? __('Business name', 'sccc') : $title;
    }

    /** Show "Vendors (3)" when applications are waiting for review. */
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
     * "Application record" meta box
     * ---------------------------------------------------------------------- */

    public static function addMetaBoxes(): void
    {
        add_meta_box(
            'sccc-vendor-record',
            __('Application record', 'sccc'),
            [self::class, 'renderRecordBox'],
            Vendors::POST_TYPE,
            'side',
            'high'
        );
    }

    public static function renderRecordBox(WP_Post $post): void
    {
        $id = (int) $post->ID;
        $status = Vendors::status($id);
        $eventId = Vendors::eventId($id);

        echo '<p>'.self::statusPill($status).'</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in statusPill().

        if (Vendors::hasFoodConflict($id)) {
            echo '<p class="sccc-vendor-warning">'.esc_html__('Heads up: this is a food vendor and the selected show does not allow food vendors.', 'sccc').'</p>';
        }

        $rows = [];

        if ($eventId) {
            $rows[__('Show', 'sccc')] = '<a href="'.esc_url((string) get_edit_post_link($eventId)).'">'.esc_html(Vendors::eventLabel($eventId)).'</a>';
            $rows[__('Show default fee', 'sccc')] = esc_html(Vendors::money(Vendors::eventDefaultFee($eventId)));
        }

        $entryId = (int) get_post_meta($id, Vendors::META_ENTRY_ID, true);
        $formId = (int) get_post_meta($id, Vendors::META_FORM_ID, true);
        if ($entryId && $formId) {
            $rows[__('Application entry', 'sccc')] = '<a href="'.esc_url(admin_url("admin.php?page=gf_entries&view=entry&id={$formId}&lid={$entryId}")).'">#'.$entryId.'</a>';
        }

        $optin = get_post_meta($id, Vendors::META_MARKETING_OPTIN, true);
        if ($optin !== '') {
            $rows[__('Email updates', 'sccc')] = $optin ? esc_html__('Opted in', 'sccc') : esc_html__('No', 'sccc');
        }

        $source = (string) get_post_meta($id, Vendors::META_REFERRAL_SOURCE, true);
        if ($source !== '') {
            $rows[__('Source', 'sccc')] = esc_html($source);
        }

        foreach ([
            Vendors::META_APPROVED_AT => __('Approved', 'sccc'),
            Vendors::META_PAYMENT_EMAILED_AT => __('Payment email sent', 'sccc'),
            Vendors::META_PAID_AT => __('Paid', 'sccc'),
        ] as $metaKey => $label) {
            $value = (string) get_post_meta($id, $metaKey, true);
            if ($value !== '') {
                $rows[$label] = esc_html(mysql2date('M j, Y g:i a', $value)); // Stored with current_time('mysql') (site-local).
            }
        }

        if ($status === Vendors::STATUS_APPROVED) {
            $rows[__('Amount due', 'sccc')] = '<strong>'.esc_html(Vendors::money(Vendors::feeDue($id))).'</strong>';
        }

        if ($status === Vendors::STATUS_PAID) {
            $rows[__('Amount paid', 'sccc')] = '<strong>'.esc_html(Vendors::money((float) get_post_meta($id, Vendors::META_PAID_AMOUNT, true))).'</strong>';

            $transaction = (string) get_post_meta($id, Vendors::META_TRANSACTION_ID, true);
            if ($transaction !== '') {
                $rows[__('Transaction', 'sccc')] = '<code>'.esc_html($transaction).'</code>';
            }

            $payEntry = (int) get_post_meta($id, Vendors::META_PAYMENT_ENTRY_ID, true);
            $payForm = (int) get_post_meta($id, Vendors::META_PAYMENT_FORM_ID, true);
            if ($payEntry && $payForm) {
                $rows[__('Payment entry', 'sccc')] = '<a href="'.esc_url(admin_url("admin.php?page=gf_entries&view=entry&id={$payForm}&lid={$payEntry}")).'">#'.$payEntry.'</a>';
            }
        }

        if ($rows) {
            echo '<table class="sccc-vendor-record">';
            foreach ($rows as $label => $valueHtml) {
                echo '<tr><th>'.esc_html($label).'</th><td>'.$valueHtml.'</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- values escaped above.
            }
            echo '</table>';
        }

        if ($status === Vendors::STATUS_APPROVED) {
            $url = Vendors::paymentUrl($id);

            if ($url === '') {
                echo '<p class="sccc-vendor-warning">'.esc_html__('No payment page is set. Choose one in Theme Settings → Vendor Settings.', 'sccc').'</p>';
            } else {
                echo '<p><label for="sccc-vendor-payment-link"><strong>'.esc_html__('Private payment link', 'sccc').'</strong></label>';
                echo '<input id="sccc-vendor-payment-link" type="text" class="widefat" readonly value="'.esc_attr($url).'" onclick="this.select();"></p>';

                $resendUrl = wp_nonce_url(
                    admin_url('admin-post.php?action='.VendorApproval::RESEND_ACTION.'&vendor='.$id),
                    VendorApproval::RESEND_ACTION.'_'.$id
                );
                echo '<p><a class="button" href="'.esc_url($resendUrl).'">'.esc_html__('Resend payment email', 'sccc').'</a></p>';
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

    public static function printAdminStyles(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (! $screen || ($screen->post_type ?? '') !== Vendors::POST_TYPE) {
            return;
        }

        echo '<style>
            .sccc-vendor-pill{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600;line-height:1.7;white-space:nowrap}
            .sccc-vendor-pill--pending{background:#fef3c7;color:#92400e}
            .sccc-vendor-pill--approved{background:#dbeafe;color:#1e3a8a}
            .sccc-vendor-pill--paid{background:#dcfce7;color:#14532d}
            .sccc-vendor-pill--declined{background:#fee2e2;color:#7f1d1d}
            .sccc-vendor-pill--cancelled{background:#e2e8f0;color:#334155}
            .sccc-vendor-pill--warning{background:#fde68a;color:#78350f;margin-top:4px}
            .sccc-vendor-warning{background:#fef3c7;border-left:4px solid #f59e0b;padding:8px 10px;margin:8px 0}
            .sccc-vendor-record{width:100%;border-collapse:collapse;margin:8px 0}
            .sccc-vendor-record th{text-align:left;font-weight:600;padding:4px 8px 4px 0;vertical-align:top;width:45%}
            .sccc-vendor-record td{padding:4px 0;word-break:break-word}
            .column-vendor_status{width:190px}.column-vendor_fee{width:110px}
        </style>';
    }
}
