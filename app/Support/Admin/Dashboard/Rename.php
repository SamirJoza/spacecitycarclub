<?php

namespace App\Support\Admin\Dashboard;

defined('ABSPATH') || exit;

/**
 * Single source of truth for the Dashboard label.
 * Change this ONE value only.
 */
const SCCC_DASHBOARD_LABEL = 'Cockpit';

/**
 * Rename Dashboard in the left admin menu.
 */
add_action('admin_menu', function () {
  global $menu;

  foreach ($menu as $i => $item) {
    if (!empty($item[2]) && $item[2] === 'index.php') {
      $menu[$i][0] = __(SCCC_DASHBOARD_LABEL, 'sccc');
      break;
    }
  }
}, 999);

/**
 * Rename the visible H1 page title on wp-admin/index.php (Dashboard screen).
 */
add_filter('gettext', function ($translated, $text, $domain) {
  if (!is_admin()) return $translated;

  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->base !== 'dashboard') return $translated;

  if ($domain === 'default' && $text === 'Dashboard') {
    return __(SCCC_DASHBOARD_LABEL, 'sccc');
  }

  return $translated;
}, 10, 3);
