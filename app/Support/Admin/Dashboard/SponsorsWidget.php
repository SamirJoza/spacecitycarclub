<?php

namespace App\Support\Admin\Dashboard;

use App\Support\Admin\UserRoles\OperationalRoles;
use WP_Query;

defined('ABSPATH') || exit;

/**
 * Sponsors dashboard widget
 * - Shows total ACTIVE sponsors + ACTIVE per tier
 *
 * Depends on sponsor CPT/tax constants being available in App\ namespace.
 */
add_action('wp_dashboard_setup', __NAMESPACE__ . '\\register_sponsors_widget');

function register_sponsors_widget(): void
{
  if (!current_user_can(OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
    return;
  }

  wp_add_dashboard_widget(
    'sccc_sponsors_widget',
    __('Active Sponsors', 'sccc'),
    __NAMESPACE__ . '\\render_sponsors_widget'
  );
}

function render_sponsors_widget(): void
{
  if (!current_user_can(OperationalRoles::CAP_USE_DASHBOARD_WIDGETS)) {
    echo '<p>' . esc_html__('You do not have permission to view this widget.', 'sccc') . '</p>';
    return;
  }

  // Cache for snappy dashboard loads
  $cache_key = 'sccc_active_sponsor_counts_v1';
  $counts = get_transient($cache_key);

  if (!is_array($counts)) {
    $counts = get_active_sponsor_counts_by_tier();
    set_transient($cache_key, $counts, 15 * MINUTE_IN_SECONDS);
  }

  echo '<div style="display:grid;gap:.65rem;">';
  echo '<div style="font-size:16px;font-weight:800;">Total Active: ' . esc_html((string) ($counts['total'] ?? 0)) . '</div>';
  echo '<div style="display:grid;gap:.35rem;">';

  foreach (['platinum','gold','silver','bronze','supporter'] as $slug) {
    $label = ucfirst($slug);
    echo '<div style="display:flex;justify-content:space-between;align-items:center;">';
    echo '<span>' . esc_html($label) . '</span>';
    echo '<strong>' . esc_html((string) ($counts[$slug] ?? 0)) . '</strong>';
    echo '</div>';
  }

  echo '</div></div>';
}

/**
 * Counts ONLY active sponsors, grouped by tier slug.
 */
function get_active_sponsor_counts_by_tier(): array
{
  // Pull constants from App namespace
  $cpt = defined('\\App\\SPONSOR_CPT') ? \App\SPONSOR_CPT : 'sponsor';
  $tax = defined('\\App\\SPONSOR_TAX') ? \App\SPONSOR_TAX : 'sponsor_tier';

  $base = [
    'total'     => 0,
    'platinum'  => 0,
    'gold'      => 0,
    'silver'    => 0,
    'bronze'    => 0,
    'supporter' => 0,
  ];

  $q = new WP_Query([
    'post_type'      => $cpt,
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [
      [
        'key'     => 'sponsor_status',
        'value'   => 'active',
        'compare' => '=',
      ],
    ],
  ]);

  foreach ($q->posts as $post_id) {
    $base['total']++;

    $terms = get_the_terms($post_id, $tax);
    $slug = 'supporter';
    if (is_array($terms) && !empty($terms)) {
      $slug = $terms[0]->slug ?: 'supporter';
    }
    if (!isset($base[$slug])) $base[$slug] = 0;
    $base[$slug]++;
  }

  return $base;
}
