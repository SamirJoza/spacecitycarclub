<?php

/**
 * app/Sponsors.php
 * File: app/Sponsors.php
 *
 * Sponsors module:
 * - CPT + Taxonomy + Default tiers
 * - Expiration automation (daily cron)
 * - Admin list columns + pills
 *
 * Dashboard widget moved to: app/Support/Admin/Dashboard/SponsorsWidget.php
 */

namespace App;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

defined('ABSPATH') || exit;

const SPONSOR_CPT = 'sponsor';
const SPONSOR_TAX = 'sponsor_tier';
const SPONSOR_CRON_HOOK = 'sccc_sponsor_expiration_daily';

/** Post meta + ACF field name for lead / referral attribution (GF hidden field or “Direct”). */
const SPONSOR_SOURCE_META = 'sponsor_source';

/**
 * Bootstrap.
 */
add_action('init', __NAMESPACE__ . '\\register_sponsor_cpt', 5);
add_action('init', __NAMESPACE__ . '\\register_sponsor_tier_taxonomy', 5);
add_action('init', __NAMESPACE__ . '\\ensure_default_sponsor_tiers', 15);
add_action('init', __NAMESPACE__ . '\\schedule_sponsor_expiration_cron', 20);

add_action(SPONSOR_CRON_HOOK, __NAMESPACE__ . '\\run_sponsor_expiration_pass');

// Keep dates/status consistent on save (ACF save hook).
add_action('acf/save_post', __NAMESPACE__ . '\\sponsor_preserve_readonly_source_in_acf_post', 5);
add_action('acf/save_post', __NAMESPACE__ . '\\sync_sponsor_dates_and_status_on_save', 20);
add_action('acf/save_post', __NAMESPACE__ . '\\sponsor_default_source_if_empty', 25);

// Admin list columns + styling.
add_filter('manage_' . SPONSOR_CPT . '_posts_columns', __NAMESPACE__ . '\\sponsor_admin_columns');
add_action('manage_' . SPONSOR_CPT . '_posts_custom_column', __NAMESPACE__ . '\\sponsor_admin_column_render', 10, 2);
add_filter('manage_edit-' . SPONSOR_CPT . '_sortable_columns', __NAMESPACE__ . '\\sponsor_admin_sortable_columns');
add_action('pre_get_posts', __NAMESPACE__ . '\\sponsor_admin_query_tweaks');
add_action('restrict_manage_posts', __NAMESPACE__ . '\\sponsor_admin_source_dropdown', 10, 2);

// Admin UX tweaks.
add_filter('enter_title_here', __NAMESPACE__ . '\\sponsor_title_placeholder', 10, 2);
add_filter('admin_post_thumbnail_html', __NAMESPACE__ . '\\sponsor_featured_image_label', 10, 2);
add_action('admin_head', __NAMESPACE__ . '\\sponsor_admin_pill_styles');

/**
 * CPT registration.
 */
function register_sponsor_cpt(): void
{
  $labels = [
    'name'                  => __('Sponsors', 'sccc'),
    'singular_name'         => __('Sponsor', 'sccc'),
    'add_new'               => __('Add Sponsor', 'sccc'),
    'add_new_item'          => __('Add Sponsor', 'sccc'),
    'edit_item'             => __('Edit Sponsor', 'sccc'),
    'new_item'              => __('New Sponsor', 'sccc'),
    'view_item'             => __('View Sponsor', 'sccc'),
    'search_items'          => __('Search Sponsors', 'sccc'),
    'not_found'             => __('No sponsors found', 'sccc'),
    'not_found_in_trash'    => __('No sponsors found in Trash', 'sccc'),
    'featured_image'        => __('Sponsor Logo', 'sccc'),
    'set_featured_image'    => __('Set Sponsor Logo', 'sccc'),
    'remove_featured_image' => __('Remove Sponsor Logo', 'sccc'),
    'use_featured_image'    => __('Use as Sponsor Logo', 'sccc'),
    'menu_name'             => __('Sponsors', 'sccc'),
  ];

  register_post_type(SPONSOR_CPT, [
    'labels' => $labels,
    'public' => true,
    'show_in_rest' => true,
    'menu_icon' => 'dashicons-awards',
    'supports' => ['title', 'thumbnail', 'editor'],
    'has_archive' => false,
    'rewrite' => ['slug' => 'sponsors'],
    'show_in_nav_menus' => false,
  ]);
}

/**
 * Tier taxonomy.
 */
function register_sponsor_tier_taxonomy(): void
{
  register_taxonomy(SPONSOR_TAX, [SPONSOR_CPT], [
    'labels' => [
      'name'          => __('Sponsor Tiers', 'sccc'),
      'singular_name' => __('Sponsor Tier', 'sccc'),
    ],
    'public' => false,
    'show_ui' => true,
    'show_admin_column' => false,
    'show_in_rest' => true,
    'hierarchical' => false,
    'rewrite' => false,
  ]);
}

function ensure_default_sponsor_tiers(): void
{
  if (!taxonomy_exists(SPONSOR_TAX)) return;

  $terms = [
    'platinum'  => 'Platinum',
    'gold'      => 'Gold',
    'silver'    => 'Silver',
    'bronze'    => 'Bronze',
    'supporter' => 'Supporter',
  ];

  foreach ($terms as $slug => $name) {
    if (!term_exists($slug, SPONSOR_TAX)) {
      wp_insert_term($name, SPONSOR_TAX, ['slug' => $slug]);
    }
  }
}

/**
 * Cron scheduling (daily).
 */
function schedule_sponsor_expiration_cron(): void
{
  if (!wp_next_scheduled(SPONSOR_CRON_HOOK)) {
    $tz = wp_timezone();
    $now = new DateTimeImmutable('now', $tz);
    $next = $now->setTime(0, 10, 0)->add(new DateInterval('P1D')); // tomorrow 00:10
    wp_schedule_event($next->getTimestamp(), 'daily', SPONSOR_CRON_HOOK);
  }
}

/**
 * Expiration pass:
 * - If end_date is in the past, set status to inactive.
 * - Otherwise, preserve the current status (active / inactive / pending).
 */
function run_sponsor_expiration_pass(): void
{
  $tz = wp_timezone();
  $today = (new DateTimeImmutable('now', $tz))->setTime(0, 0, 0);

  $q = new \WP_Query([
    'post_type'      => SPONSOR_CPT,
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [
      [
        'key'     => 'sponsor_end_date',
        'compare' => 'EXISTS',
      ],
    ],
  ]);

  foreach ($q->posts as $post_id) {
    $end_raw = (string) get_post_meta($post_id, 'sponsor_end_date', true);
    if (!$end_raw) continue;

    $end = date_from_ymd($end_raw, $tz);
    if (!$end) continue;

    if ($end < $today) {
      update_post_meta($post_id, 'sponsor_status', 'inactive');
    }
  }
}

/**
 * On save:
 * - Ensure end_date = start_date + 1 year (if start exists and end missing)
 * - If expired, force inactive
 * - Otherwise preserve valid status values, including pending
 * - If status is empty/invalid, default to pending
 */
function sync_sponsor_dates_and_status_on_save($post_id): void
{
  if (get_post_type($post_id) !== SPONSOR_CPT) return;

  if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;

  $tz = wp_timezone();
  $today = (new DateTimeImmutable('now', $tz))->setTime(0, 0, 0);

  $start_raw = (string) get_post_meta($post_id, 'sponsor_start_date', true);
  $end_raw   = (string) get_post_meta($post_id, 'sponsor_end_date', true);
  $status    = normalize_sponsor_status((string) get_post_meta($post_id, 'sponsor_status', true));

  $start = $start_raw ? date_from_ymd($start_raw, $tz) : null;
  $end   = $end_raw ? date_from_ymd($end_raw, $tz) : null;

  if ($start && !$end) {
    $computed_end = $start->add(new DateInterval('P1Y'));
    update_post_meta($post_id, 'sponsor_end_date', $computed_end->format('Y-m-d'));
    $end = $computed_end;
  }

  if ($end && $end < $today) {
    update_post_meta($post_id, 'sponsor_status', 'inactive');
    return;
  }

  if ($status === '') {
    update_post_meta($post_id, 'sponsor_status', 'pending');
    return;
  }

  update_post_meta($post_id, 'sponsor_status', $status);
}

/**
 * Read-only Source is often absent from $_POST; ACF would otherwise clear meta on save and
 * {@see sponsor_default_source_if_empty} would set “Direct”. Runs before ACF’s internal save.
 */
function sponsor_preserve_readonly_source_in_acf_post($post_id): void
{
  if (!isset($_POST['acf']) || !is_array($_POST['acf'])) {
    return;
  }

  if (function_exists('acf_get_valid_post_id')) {
    $post_id = acf_get_valid_post_id($post_id);
  }

  $post_id = (int) $post_id;
  if ($post_id <= 0 || get_post_type($post_id) !== SPONSOR_CPT) {
    return;
  }

  if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
    return;
  }

  $key = 'field_sponsor_source';
  $posted = isset($_POST['acf'][$key]) ? trim((string) wp_unslash($_POST['acf'][$key])) : '';
  if ($posted !== '') {
    return;
  }

  $existing = trim((string) get_post_meta($post_id, SPONSOR_SOURCE_META, true));
  if ($existing === '') {
    return;
  }

  $_POST['acf'][$key] = $existing;
}

/**
 * Manual (and other) saves: empty Source becomes “Direct”. Read-only in the editor;
 * Gravity Forms sync sets the real value when present.
 */
function sponsor_default_source_if_empty($post_id): void
{
  if (function_exists('acf_get_valid_post_id')) {
    $post_id = acf_get_valid_post_id($post_id);
  }

  $post_id = (int) $post_id;
  if ($post_id <= 0 || get_post_type($post_id) !== SPONSOR_CPT) {
    return;
  }

  if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
    return;
  }

  $src = trim((string) get_post_meta($post_id, SPONSOR_SOURCE_META, true));
  if ($src !== '') {
    return;
  }

  if (function_exists('update_field')) {
    update_field('field_sponsor_source', 'Direct', $post_id);
  } else {
    update_post_meta($post_id, SPONSOR_SOURCE_META, 'Direct');
  }
}

/**
 * Helpers: parse Y-m-d into DateTimeImmutable in WP timezone.
 */
function date_from_ymd(string $ymd, DateTimeZone $tz): ?DateTimeImmutable
{
  $ymd = trim($ymd);
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return null;

  try {
    return new DateTimeImmutable($ymd, $tz);
  } catch (\Throwable $e) {
    return null;
  }
}

/**
 * Normalize sponsor status.
 */
function normalize_sponsor_status(string $status): string
{
  $status = strtolower(trim($status));

  return in_array($status, ['active', 'inactive', 'pending'], true)
    ? $status
    : '';
}

/**
 * Human label for sponsor status.
 */
function sponsor_status_label(string $status): string
{
  return match ($status) {
    'active'   => 'Active',
    'inactive' => 'Inactive',
    'pending'  => 'Pending',
    default    => 'Pending',
  };
}

/**
 * CSS class for sponsor status pill.
 */
function sponsor_status_pill_class(string $status): string
{
  return match ($status) {
    'active'   => 'is-active',
    'inactive' => 'is-inactive',
    'pending'  => 'is-pending',
    default    => 'is-pending',
  };
}

/* ============================================================
 * Admin columns
 * ============================================================ */

function sponsor_admin_columns(array $cols): array
{
  $new = [];
  $new['cb'] = $cols['cb'] ?? '';

  $new['logo'] = __('Logo', 'sccc');
  $new['title'] = __('Company Name', 'sccc');
  $new['website'] = __('Website', 'sccc');
  $new['status'] = __('Status', 'sccc');
  $new['tier'] = __('Tier', 'sccc');
  $new['sponsor_source'] = __('Source', 'sccc');

  $new['date'] = $cols['date'] ?? __('Date', 'sccc');

  return $new;
}

function sponsor_admin_column_render(string $col, int $post_id): void
{
  if ($col === 'logo') {
    $thumb = get_the_post_thumbnail($post_id, [48, 48], [
      'style' => 'width:48px;height:48px;object-fit:contain;border-radius:8px;',
    ]);
    echo $thumb ?: '<span class="dashicons dashicons-format-image" style="font-size:22px;opacity:.55;"></span>';
    return;
  }

  if ($col === 'website') {
    $url = (string) get_post_meta($post_id, 'sponsor_company_website', true);
    if ($url) {
      $disp = preg_replace('#^https?://#', '', $url);
      echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($disp) . '</a>';
    } else {
      echo '<span style="opacity:.6">—</span>';
    }
    return;
  }

  if ($col === 'status') {
    $status = normalize_sponsor_status((string) get_post_meta($post_id, 'sponsor_status', true));
    $status = $status ?: 'pending';

    $label = sponsor_status_label($status);
    $class = sponsor_status_pill_class($status);

    echo '<span class="sccc-pill ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    return;
  }

  if ($col === 'tier') {
    $terms = get_the_terms($post_id, SPONSOR_TAX);
    if (is_array($terms) && !empty($terms)) {
      $t = $terms[0];
      $slug = $t->slug ?: 'supporter';
      $name = $t->name ?: 'Supporter';
      echo '<span class="sccc-pill tier-' . esc_attr($slug) . '">' . esc_html($name) . '</span>';
    } else {
      echo '<span class="sccc-pill tier-supporter">Supporter</span>';
    }
    return;
  }

  if ($col === 'sponsor_source') {
    $src = trim((string) get_post_meta($post_id, SPONSOR_SOURCE_META, true));
    echo $src !== '' ? esc_html($src) : '<span style="opacity:.6">' . esc_html__('Direct', 'sccc') . '</span>';
    return;
  }
}

function sponsor_admin_sortable_columns(array $cols): array
{
  $cols['status'] = 'sponsor_status';
  $cols['sponsor_source'] = 'sponsor_source';

  return $cols;
}

/**
 * Admin list: sort by status / source, filter by source (dropdown).
 */
function sponsor_admin_query_tweaks(\WP_Query $q): void
{
  if (! is_admin() || ! $q->is_main_query()) {
    return;
  }

  if (($q->get('post_type') ?: '') !== SPONSOR_CPT) {
    return;
  }

  if (! empty($_GET['sponsor_source_filter'])) {
    $val = sanitize_text_field(wp_unslash((string) $_GET['sponsor_source_filter']));
    if ($val !== '') {
      $mq = $q->get('meta_query');
      if (! is_array($mq)) {
        $mq = [];
      }
      $mq[] = [
        'key' => SPONSOR_SOURCE_META,
        'value' => $val,
        'compare' => '=',
      ];
      $q->set('meta_query', $mq);
    }
  }

  $orderby = $q->get('orderby');
  if ($orderby === 'sponsor_status') {
    $q->set('meta_key', 'sponsor_status');
    $q->set('orderby', 'meta_value');
  } elseif ($orderby === 'sponsor_source') {
    $q->set('meta_key', SPONSOR_SOURCE_META);
    $q->set('orderby', 'meta_value');
  }
}

/**
 * Distinct Source values for the admin filter dropdown.
 *
 * @return string[]
 */
function sponsor_admin_distinct_sources(): array
{
  global $wpdb;

  $sql = $wpdb->prepare(
    "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
     INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
     WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status IN ('publish','draft','pending','private')
     AND pm.meta_value <> ''
     ORDER BY pm.meta_value ASC",
    SPONSOR_SOURCE_META,
    SPONSOR_CPT
  );

  $rows = $wpdb->get_col($sql);
  if (! is_array($rows)) {
    return [];
  }

  $out = [];
  foreach ($rows as $row) {
    $row = trim((string) $row);
    if ($row !== '' && ! in_array($row, $out, true)) {
      $out[] = $row;
    }
  }

  return $out;
}

function sponsor_admin_source_dropdown(string $post_type, string $which = ''): void
{
  if ($post_type !== SPONSOR_CPT) {
    return;
  }

  if ($which === 'bottom') {
    return;
  }

  $selected = isset($_GET['sponsor_source_filter'])
    ? sanitize_text_field(wp_unslash((string) $_GET['sponsor_source_filter']))
    : '';

  $choices = sponsor_admin_distinct_sources();
  $choices = array_values(array_unique(array_merge(['Direct'], $choices)));
  sort($choices, SORT_STRING);

  echo '<label class="screen-reader-text" for="sponsor_source_filter">' . esc_html__('Filter by source', 'sccc') . '</label>';
  echo '<select name="sponsor_source_filter" id="sponsor_source_filter" style="max-width:14rem;">';
  echo '<option value="">' . esc_html__('All sources', 'sccc') . '</option>';

  foreach ($choices as $choice) {
    printf(
      '<option value="%s"%s>%s</option>',
      esc_attr($choice),
      selected($selected, $choice, false),
      esc_html($choice)
    );
  }

  echo '</select>';
}

/* ============================================================
 * Admin UX tweaks
 * ============================================================ */

function sponsor_title_placeholder(string $title, \WP_Post $post): string
{
  if ($post->post_type === SPONSOR_CPT) {
    return __('Company Name', 'sccc');
  }
  return $title;
}

function sponsor_featured_image_label(string $content, int $post_id): string
{
  if (get_post_type($post_id) !== SPONSOR_CPT) return $content;

  $content = str_replace(__('Set featured image'), __('Set Sponsor Logo', 'sccc'), $content);
  $content = str_replace(__('Remove featured image'), __('Remove Sponsor Logo', 'sccc'), $content);
  $content = str_replace(__('Featured image'), __('Sponsor Logo', 'sccc'), $content);

  return $content;
}

function sponsor_admin_pill_styles(): void
{
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen) return;

  $is_sponsor = ($screen->post_type ?? '') === SPONSOR_CPT;
  if (!$is_sponsor) return;

  echo '<style>
  .customize-support #editor .edit-post-layout__metaboxes .edit-post-meta-boxes-area {margin-bottom: 150px;}
    .sccc-pill{
      display:inline-flex;
      align-items:center;
      gap:.35rem;
      padding:.18rem .55rem;
      border-radius:999px;
      font-weight:700;
      font-size:12px;
      line-height:1.5;
      border:1px solid rgba(0,0,0,.08);
      white-space:nowrap;
    }
    .sccc-pill.is-active{
      background: rgba(34,197,94,.14);
      border-color: rgba(34,197,94,.28);
      color: #166534;
    }
    .sccc-pill.is-inactive{
      background: rgba(239,68,68,.14);
      border-color: rgba(239,68,68,.30);
      color: #991b1b;
    }
    .sccc-pill.is-pending{
      background: rgba(168,85,247,.16);
      border-color: rgba(168,85,247,.34);
      color: #6b21a8;
      box-shadow: inset 0 0 0 1px rgba(255,255,255,.15);
    }
    .sccc-pill.tier-platinum{
      background: rgba(148,163,184,.18);
      border-color: rgba(148,163,184,.34);
      color:#334155;
    }
    .sccc-pill.tier-gold{
      background: rgba(234,179,8,.18);
      border-color: rgba(234,179,8,.34);
      color:#854d0e;
    }
    .sccc-pill.tier-silver{
      background: rgba(203,213,225,.22);
      border-color: rgba(203,213,225,.42);
      color:#334155;
    }
    .sccc-pill.tier-bronze{
      background: rgba(180,83,9,.18);
      border-color: rgba(180,83,9,.34);
      color:#7c2d12;
    }
    .sccc-pill.tier-supporter{
      background: rgba(59,130,246,.14);
      border-color: rgba(59,130,246,.28);
      color:#1e3a8a;
    }
  </style>';
}