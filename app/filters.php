<?php

/**
 * Theme filters.
 */

namespace App;

/**
 * Add "… Continued" to the excerpt.
 *
 * @return string
 */
add_filter('excerpt_more', function () {
    return sprintf(' &hellip; <a href="%s">%s</a>', get_permalink(), __('Continued', 'sage'));
});

add_filter('wp_resource_hints', function ($hints, $relation) {
  if ($relation === 'preconnect') {
    $hints[] = 'https://fonts.googleapis.com';
    $hints[] = 'https://fonts.gstatic.com';
  }
  return $hints;
}, 10, 2);

/**
 * Hide ACF admin UI globally.
 */
add_filter('acf/settings/show_admin', '__return_false');

add_filter('block_categories_all', function ($categories, $post) {
            return array_merge(
                $categories,
                [[
                    'slug'  => 'theme-blocks',
                    'title' => __('Theme Blocks', 'sage'), // Name shown in editor
                ]]
            );
        }, 10, 2);


add_filter('sccc_pmpro_club_level_ids', function () {
  return [2, 3, 4, 5]; // replace with your real level IDs
});
