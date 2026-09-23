<?php

/**
 * Minutes module:
 * - CPT: minutes
 * - Taxonomy: meeting_type (Board / Committee / Membership)
 * - Editor UI: ACF radio in sidebar (single-select), synced to taxonomy
 * - Hide native taxonomy panel in Gutenberg (so no checkbox/tag UI)
 */

namespace App;

defined('ABSPATH') || exit;

const MINUTES_CPT      = 'minutes';
const MINUTES_TAX_TYPE = 'meeting_type';
const MINUTES_ACF_MEETING_TYPE_FIELD = 'meeting_type_select';

/**
 * Bootstrap.
 */
add_action('init', __NAMESPACE__ . '\\register_minutes_cpt', 5);
add_action('init', __NAMESPACE__ . '\\register_meeting_type_taxonomy', 5);
add_action('init', __NAMESPACE__ . '\\ensure_default_meeting_types', 15);

// Hide the native metabox in classic editor (ACF replaces it).
add_action('add_meta_boxes', __NAMESPACE__ . '\\remove_native_meeting_type_metabox', 20);

// Sync: ACF radio -> taxonomy on save.
add_action('acf/save_post', __NAMESPACE__ . '\\sync_acf_meeting_type_to_taxonomy', 20);

// Sync: taxonomy -> ACF value when loading editor (keeps things consistent after quick-edit/bulk-edit).
add_filter('acf/load_value/name=' . MINUTES_ACF_MEETING_TYPE_FIELD, __NAMESPACE__ . '\\load_meeting_type_from_taxonomy', 10, 3);

// Admin UX tweaks.
add_filter('enter_title_here', __NAMESPACE__ . '\\minutes_title_placeholder', 10, 2);

/**
 * CPT registration.
 */
function register_minutes_cpt(): void
{
  $labels = [
    'name'               => __('Minutes', 'sccc'),
    'singular_name'      => __('Minutes', 'sccc'),
    'add_new'            => __('Add Minutes', 'sccc'),
    'add_new_item'       => __('Add Minutes', 'sccc'),
    'edit_item'          => __('Edit Minutes', 'sccc'),
    'new_item'           => __('New Minutes', 'sccc'),
    'view_item'          => __('View Minutes', 'sccc'),
    'search_items'       => __('Search Minutes', 'sccc'),
    'not_found'          => __('No minutes found', 'sccc'),
    'not_found_in_trash' => __('No minutes found in Trash', 'sccc'),
    'menu_name'          => __('Minutes', 'sccc'),
  ];

  register_post_type(MINUTES_CPT, [
    'labels'            => $labels,
    'public'            => true,
    'show_in_rest'      => true,
    'menu_icon'         => 'dashicons-media-text',
    'supports'          => ['title', 'editor', 'excerpt', 'author', 'revisions'],
    'has_archive'       => true,
    'rewrite'           => ['slug' => 'minutes'],
    'show_in_nav_menus' => false,
  ]);
}

/**
 * Meeting Type taxonomy.
 *
 * Key point:
 * - show_in_rest => false hides the taxonomy panel in Gutenberg (the thing that keeps showing tags/checkboxes)
 * - show_ui => true keeps the taxonomy usable in admin (terms page, columns, quick edit, etc.)
 */
function register_meeting_type_taxonomy(): void
{
  register_taxonomy(MINUTES_TAX_TYPE, [MINUTES_CPT], [
    'labels' => [
      'name'          => __('Meeting Type', 'sccc'),
      'singular_name' => __('Meeting Type', 'sccc'),
    ],
    'public'            => false,
    'show_ui'           => true,
    'show_admin_column' => true,

    // IMPORTANT: hide WP's Gutenberg taxonomy selector UI for this taxonomy.
    'show_in_rest'      => false,

    // This taxonomy is effectively single-select, but we store as taxonomy terms.
    'hierarchical'      => false,
    'rewrite'           => false,

    // Lock term management to admins (you can still add/remove types by code or as admin on the taxonomy screen).
    'capabilities' => [
      'manage_terms' => 'manage_options',
      'edit_terms'   => 'manage_options',
      'delete_terms' => 'manage_options',
      'assign_terms' => 'edit_posts',
    ],
  ]);
}

/**
 * Hardcode / seed the default meeting types.
 */
function ensure_default_meeting_types(): void
{
  if (!taxonomy_exists(MINUTES_TAX_TYPE)) return;

  $terms = [
    'board'      => 'Board Meeting',
    'committee'  => 'Committee Meeting',
    'membership' => 'Membership Meeting',
  ];

  foreach ($terms as $slug => $name) {
    if (!term_exists($slug, MINUTES_TAX_TYPE)) {
      wp_insert_term($name, MINUTES_TAX_TYPE, ['slug' => $slug]);
    }
  }
}

/**
 * Remove the native taxonomy meta box in classic editor.
 * (Gutenberg panel is already hidden via show_in_rest => false.)
 */
function remove_native_meeting_type_metabox(): void
{
  remove_meta_box(MINUTES_TAX_TYPE . 'div', MINUTES_CPT, 'side');
}

/**
 * ACF radio -> taxonomy assignment (single term).
 */
function sync_acf_meeting_type_to_taxonomy($post_id): void
{
  if (get_post_type($post_id) !== MINUTES_CPT) return;
  if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;

  if (!function_exists('get_field')) return;

  $slug = (string) get_field(MINUTES_ACF_MEETING_TYPE_FIELD, $post_id);
  $slug = sanitize_title($slug);

  $allowed = ['board', 'committee', 'membership'];
  if (!in_array($slug, $allowed, true)) return;

  // Make sure the term exists.
  $exists = term_exists($slug, MINUTES_TAX_TYPE);
  if (!$exists) return;

  // Store exactly one selected term.
  wp_set_object_terms($post_id, [$slug], MINUTES_TAX_TYPE, false);
}

/**
 * Taxonomy -> ACF value on load.
 * If ACF value is empty, we populate it from the assigned meeting_type term.
 */
function load_meeting_type_from_taxonomy($value, $post_id, $field)
{
  // Only for real posts in editor context.
  if (!is_numeric($post_id)) return $value;

  if (!empty($value)) return $value;

  $slugs = wp_get_object_terms((int) $post_id, MINUTES_TAX_TYPE, ['fields' => 'slugs']);
  if (is_wp_error($slugs) || empty($slugs)) return $value;

  return (string) $slugs[0];
}

/**
 * Admin UX.
 */
function minutes_title_placeholder(string $title, \WP_Post $post): string
{
  if ($post->post_type !== MINUTES_CPT) return $title;
  return __('Example: Board Meeting — January 2026', 'sccc');
}
