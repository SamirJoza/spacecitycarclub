<?php

namespace App\Support\Admin\Dashboard;

defined('ABSPATH') || exit;

add_action('wp_dashboard_setup', __NAMESPACE__ . '\\remove_default_dashboard_widgets', 999);

function remove_default_dashboard_widgets(): void
{
  // WordPress core widgets
  remove_meta_box('dashboard_primary', 'dashboard', 'side'); // WordPress Events & News
  remove_meta_box('dashboard_secondary', 'dashboard', 'side'); // Secondary news (older installs)

  remove_meta_box('dashboard_quick_press', 'dashboard', 'side'); // Quick Draft
  //remove_meta_box('dashboard_site_health', 'dashboard', 'normal'); // Site Health
  remove_meta_box('dashboard_right_now', 'dashboard', 'normal'); // At a Glance
  remove_meta_box('dashboard_activity', 'dashboard', 'normal'); // Activity

  // Mage-People (or other plugin) widgets — safe even if plugin is disabled
  remove_meta_box('mage_people_news_widget', 'dashboard', 'normal');
  remove_meta_box('mage_people_news_widget', 'dashboard', 'side');
}
