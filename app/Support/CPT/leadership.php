<?php

/**
 * --------------------------------------------------------------------------
 * File path + filename: app/Support/CPT/leadership.php
 * --------------------------------------------------------------------------
 * Purpose:
 * - Register the Leadership custom post type.
 * - Register the single leadership classification taxonomy used by this CPT.
 * - Seed the default classification terms for the club.
 * - Keep the Leadership admin list easy to scan with category/position/order
 *   pills.
 * - Provide a simple taxonomy filter in the admin list table.
 *
 * Why this file exists:
 * - The Leadership area has been intentionally simplified.
 * - We now keep one taxonomy for leadership classification and use plain custom
 *   fields for position names and display ordering.
 *
 * Important implementation note:
 * - The taxonomy slug remains `leadership_group` for backward compatibility with
 *   existing data and any older template references.
 * - In the WordPress UI, however, it is presented as “Leadership Categories”.
 *
 * Current category terms:
 * - Leadership Team
 * - Officer
 * - Board Member
 * - Committee
 */

namespace App;

defined('ABSPATH') || exit;

/**
 * CPT + taxonomy constants.
 */
const LEADERSHIP_CPT       = 'leadership';
const LEADERSHIP_GROUP_TAX = 'leadership_group';

/**
 * Bootstrap registrations.
 */
add_action('init', __NAMESPACE__ . '\\register_leadership_cpt', 5);
add_action('init', __NAMESPACE__ . '\\register_leadership_group_taxonomy', 5);
add_action('init', __NAMESPACE__ . '\\ensure_default_leadership_terms', 15);

/**
 * Admin list table enhancements.
 */
add_filter('manage_' . LEADERSHIP_CPT . '_posts_columns', __NAMESPACE__ . '\\leadership_admin_columns');
add_action('manage_' . LEADERSHIP_CPT . '_posts_custom_column', __NAMESPACE__ . '\\leadership_admin_column_render', 10, 2);
add_action('restrict_manage_posts', __NAMESPACE__ . '\\leadership_admin_taxonomy_filters');
add_filter('parse_query', __NAMESPACE__ . '\\leadership_admin_apply_taxonomy_filters');
add_filter('enter_title_here', __NAMESPACE__ . '\\leadership_title_placeholder', 10, 2);
add_action('admin_head', __NAMESPACE__ . '\\leadership_admin_pill_styles');

/* ========================================================================== */
/* CPT registration                                                            */
/* ========================================================================== */

/**
 * Register the Leadership custom post type.
 */
function register_leadership_cpt(): void
{
    $labels = [
        'name'               => __('Leadership', 'sccc'),
        'singular_name'      => __('Leader', 'sccc'),
        'add_new'            => __('Add Leader', 'sccc'),
        'add_new_item'       => __('Add Leader', 'sccc'),
        'edit_item'          => __('Edit Leader', 'sccc'),
        'new_item'           => __('New Leader', 'sccc'),
        'view_item'          => __('View Leader', 'sccc'),
        'search_items'       => __('Search Leadership', 'sccc'),
        'not_found'          => __('No leadership entries found', 'sccc'),
        'not_found_in_trash' => __('No leadership entries found in Trash', 'sccc'),
        'menu_name'          => __('Leadership', 'sccc'),
    ];

    register_post_type(LEADERSHIP_CPT, [
        'labels'             => $labels,
        'public'             => true,
        'show_in_rest'       => true,
        'menu_icon'          => 'dashicons-businessperson',
        'supports'           => ['title', 'revisions'],
        'taxonomies'         => [LEADERSHIP_GROUP_TAX],
        'has_archive'        => false,
        'rewrite'            => ['slug' => 'leadership', 'with_front' => false],
        'show_in_nav_menus'  => false,
    ]);
}

/* ========================================================================== */
/* Taxonomy registration                                                       */
/* ========================================================================== */

/**
 * Register the single leadership classification taxonomy.
 *
 * Why this exists:
 * - The Leadership CPT needs one curated classification layer.
 * - Terms are managed through the taxonomy admin screen.
 * - The editor assigns existing terms through the ACF field.
 */
function register_leadership_group_taxonomy(): void
{
    register_taxonomy(LEADERSHIP_GROUP_TAX, [LEADERSHIP_CPT], [
        'labels' => [
            'name'              => __('Leadership Categories', 'sccc'),
            'singular_name'     => __('Leadership Category', 'sccc'),
            'search_items'      => __('Search Leadership Categories', 'sccc'),
            'all_items'         => __('All Leadership Categories', 'sccc'),
            'edit_item'         => __('Edit Leadership Category', 'sccc'),
            'update_item'       => __('Update Leadership Category', 'sccc'),
            'add_new_item'      => __('Add New Leadership Category', 'sccc'),
            'new_item_name'     => __('New Leadership Category Name', 'sccc'),
            'menu_name'         => __('Leadership Categories', 'sccc'),
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_admin_column'   => false,
        'show_in_rest'        => true,
        'show_in_quick_edit'  => false,
        'hierarchical'        => false,
        'rewrite'             => false,
    ]);
}

/**
 * Seed and gently normalize the default taxonomy terms.
 *
 * Why this is written carefully:
 * - Older installs may already have the legacy term “Leadership”.
 * - Updating the existing term in place preserves assignments because the term
 *   ID stays the same.
 */
function ensure_default_leadership_terms(): void
{
    if (!taxonomy_exists(LEADERSHIP_GROUP_TAX)) {
        return;
    }

    $legacy = get_term_by('slug', 'leadership', LEADERSHIP_GROUP_TAX);

    if ($legacy && !is_wp_error($legacy)) {
        $existing_target = get_term_by('slug', 'leadership-team', LEADERSHIP_GROUP_TAX);

        if (!$existing_target) {
            wp_update_term((int) $legacy->term_id, LEADERSHIP_GROUP_TAX, [
                'name' => 'Leadership Team',
                'slug' => 'leadership-team',
            ]);
        } else {
            wp_update_term((int) $legacy->term_id, LEADERSHIP_GROUP_TAX, [
                'name' => 'Leadership Team',
            ]);
        }
    }

    $terms = [
        'leadership-team' => 'Leadership Team',
        'officer'         => 'Officer',
        'board-member'    => 'Board Member',
        'committee'       => 'Committee',
    ];

    foreach ($terms as $slug => $name) {
        if (!term_exists($slug, LEADERSHIP_GROUP_TAX)) {
            wp_insert_term($name, LEADERSHIP_GROUP_TAX, ['slug' => $slug]);
        }
    }
}

/* ========================================================================== */
/* Admin columns                                                               */
/* ========================================================================== */

/**
 * Customize the Leadership admin columns.
 */
function leadership_admin_columns(array $columns): array
{
    $new = [];

    $new['cb']                       = $columns['cb'] ?? '';
    $new['title']                    = __('Leader', 'sccc');
    $new['leadership_category']      = __('Category', 'sccc');
    $new['leadership_display_order'] = __('Order', 'sccc');
    $new['leadership_positions']     = __('Position(s)', 'sccc');
    $new['date']                     = $columns['date'] ?? __('Date', 'sccc');

    return $new;
}

/**
 * Render custom Leadership admin columns.
 */
function leadership_admin_column_render(string $column, int $post_id): void
{
    if ($column === 'leadership_category') {
        echo leadership_terms_as_pills($post_id, LEADERSHIP_GROUP_TAX, 'category');
        return;
    }

    if ($column === 'leadership_display_order') {
        echo leadership_display_order_as_pill($post_id);
        return;
    }

    if ($column === 'leadership_positions') {
        echo leadership_positions_as_pills($post_id);
        return;
    }
}

/**
 * Render taxonomy terms as pills for quick scanning.
 */
function leadership_terms_as_pills(int $post_id, string $taxonomy, string $kind): string
{
    $terms = get_the_terms($post_id, $taxonomy);

    if (!is_array($terms) || empty($terms)) {
        return '<span style="opacity:.6">—</span>';
    }

    $output = [];

    foreach ($terms as $term) {
        $output[] = sprintf(
            '<span class="sccc-pill is-%1$s term-%2$s">%3$s</span>',
            esc_attr($kind),
            esc_attr($term->slug ?: 'term'),
            esc_html($term->name ?: $term->slug)
        );
    }

    return implode(' ', $output);
}

/**
 * Render the display order as a pill.
 */
function leadership_display_order_as_pill(int $post_id): string
{
    $value = function_exists('get_field')
        ? get_field('leadership_display_order', $post_id, false)
        : get_post_meta($post_id, 'leadership_display_order', true);

    if ($value === '' || $value === null) {
        return '<span style="opacity:.6">—</span>';
    }

    return sprintf(
        '<span class="sccc-pill is-order">%s</span>',
        esc_html((string) (int) $value)
    );
}

/**
 * Render the custom positions field as pills.
 *
 * Supported input shapes:
 * - newline-delimited string
 * - comma-delimited string
 * - array fallback for legacy data
 */
function leadership_positions_as_pills(int $post_id): string
{
    $positions = leadership_normalize_positions_value(
        function_exists('get_field')
            ? get_field('leadership_position_titles', $post_id, false)
            : get_post_meta($post_id, 'leadership_position_titles', true)
    );

    if (empty($positions)) {
        return '<span style="opacity:.6">—</span>';
    }

    $output = [];

    foreach ($positions as $position) {
        $output[] = sprintf(
            '<span class="sccc-pill is-position">%s</span>',
            esc_html($position)
        );
    }

    return implode(' ', $output);
}

/**
 * Normalize the positions field into a clean array of labels.
 */
function leadership_normalize_positions_value($value): array
{
    if (is_array($value)) {
        $items = $value;
    } else {
        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        $value = str_replace(['<br />', '<br/>', '<br>'], "\n", $value);

        if (str_contains($value, "\n")) {
            $items = preg_split('/\r\n|\r|\n/', $value) ?: [];
        } else {
            $items = preg_split('/\s*,\s*/', $value) ?: [];
        }
    }

    $items = array_map(static fn($item) => trim(wp_strip_all_tags((string) $item)), $items);
    $items = array_filter($items, static fn($item) => $item !== '');

    return array_values(array_unique($items));
}

/* ========================================================================== */
/* Admin list filters                                                          */
/* ========================================================================== */

/**
 * Add the category filter dropdown to the Leadership list table.
 */
function leadership_admin_taxonomy_filters(): void
{
    global $typenow;

    if ($typenow !== LEADERSHIP_CPT) {
        return;
    }

    leadership_render_taxonomy_filter_dropdown(LEADERSHIP_GROUP_TAX, __('All Leadership Categories', 'sccc'));
}

/**
 * Render a taxonomy dropdown filter by slug.
 */
function leadership_render_taxonomy_filter_dropdown(string $taxonomy, string $label): void
{
    if (!taxonomy_exists($taxonomy)) {
        return;
    }

    $selected = isset($_GET[$taxonomy]) ? (string) $_GET[$taxonomy] : '';

    wp_dropdown_categories([
        'show_option_all' => $label,
        'taxonomy'        => $taxonomy,
        'name'            => $taxonomy,
        'orderby'         => 'name',
        'selected'        => $selected,
        'hierarchical'    => false,
        'show_count'      => false,
        'hide_empty'      => false,
        'value_field'     => 'slug',
    ]);
}

/**
 * Apply the selected taxonomy filter to the query.
 */
function leadership_admin_apply_taxonomy_filters(\WP_Query $query): void
{
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    if (($query->get('post_type') ?: '') !== LEADERSHIP_CPT) {
        return;
    }

    if (empty($_GET[LEADERSHIP_GROUP_TAX])) {
        return;
    }

    $slug = sanitize_title((string) $_GET[LEADERSHIP_GROUP_TAX]);

    if ($slug === '' || $slug === '0') {
        return;
    }

    $query->set('tax_query', [[
        'taxonomy' => LEADERSHIP_GROUP_TAX,
        'field'    => 'slug',
        'terms'    => [$slug],
    ]]);
}

/* ========================================================================== */
/* Admin UX                                                                    */
/* ========================================================================== */

/**
 * Keep the title prompt helpful.
 */
function leadership_title_placeholder(string $title, \WP_Post $post): string
{
    if ($post->post_type === LEADERSHIP_CPT) {
        return __('Leader Name (usually the linked user name)', 'sccc');
    }

    return $title;
}

/**
 * Scoped admin pill styles for the Leadership edit/list screens.
 */
function leadership_admin_pill_styles(): void
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if (!$screen || ($screen->post_type ?? '') !== LEADERSHIP_CPT) {
        return;
    }

    echo '<style>
        .sccc-pill{
            display:inline-flex;
            align-items:center;
            padding:.18rem .55rem;
            border-radius:999px;
            font-weight:700;
            font-size:12px;
            line-height:1.5;
            border:1px solid rgba(0,0,0,.10);
            white-space:nowrap;
            margin-right:.35rem;
            margin-bottom:.2rem;
        }
        .sccc-pill.is-category{
            background:rgba(34,197,94,.10);
            border-color:rgba(34,197,94,.22);
            color:#14532d;
        }
        .sccc-pill.is-position{
            background:rgba(59,130,246,.10);
            border-color:rgba(59,130,246,.22);
            color:#1e3a8a;
        }
        .sccc-pill.is-order{
            background:rgba(168,85,247,.12);
            border-color:rgba(168,85,247,.26);
            color:#581c87;
        }
    </style>';
}