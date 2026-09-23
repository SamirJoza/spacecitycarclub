<?php

/**
 * app/Support/CPT/thank_you.php
 *
 * “Thank you” landing pages: page-like (hierarchical, block editor), not blog posts.
 */

namespace App;

defined('ABSPATH') || exit;

const THANK_YOU_CPT = 'thank_you';

add_action('init', __NAMESPACE__ . '\\register_thank_you_cpt', 5);
add_filter('enter_title_here', __NAMESPACE__ . '\\thank_you_title_placeholder', 10, 2);
add_filter('document_title_parts', __NAMESPACE__ . '\\thank_you_document_title_parts', 20);
add_filter('wpseo_title', __NAMESPACE__ . '\\thank_you_wpseo_title', 20);
add_filter('rank_math/frontend/title', __NAMESPACE__ . '\\thank_you_rank_math_title', 20);

/**
 * Register Thank You custom post type (Gutenberg: show_in_rest + editor support).
 */
function register_thank_you_cpt(): void
{
    $labels = [
        'name'                  => __('Thank You', 'sccc'),
        'singular_name'         => __('Thank You', 'sccc'),
        'add_new'               => __('Add Thank You', 'sccc'),
        'add_new_item'          => __('Add Thank You Page', 'sccc'),
        'edit_item'             => __('Edit Thank You Page', 'sccc'),
        'new_item'              => __('New Thank You Page', 'sccc'),
        'view_item'             => __('View Thank You Page', 'sccc'),
        'view_items'            => __('View Thank You Pages', 'sccc'),
        'search_items'          => __('Search Thank You Pages', 'sccc'),
        'not_found'             => __('No thank you pages found.', 'sccc'),
        'not_found_in_trash'    => __('No thank you pages found in Trash.', 'sccc'),
        'parent_item_colon'     => __('Parent Thank You Page:', 'sccc'),
        'all_items'             => __('All Thank You Pages', 'sccc'),
        'archives'              => __('Thank You Archives', 'sccc'),
        'attributes'            => __('Thank You Page Attributes', 'sccc'),
        'insert_into_item'      => __('Insert into thank you page', 'sccc'),
        'uploaded_to_this_item' => __('Uploaded to this thank you page', 'sccc'),
        'menu_name'             => __('Thank You', 'sccc'),
    ];

    register_post_type(THANK_YOU_CPT, [
        'labels'              => $labels,
        'description'         => __('Thank you / confirmation style pages with the block editor.', 'sccc'),
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => true,
        'show_in_admin_bar'   => true,
        'show_in_rest'        => true,
        'rest_base'           => 'thank-you',
        'menu_icon'           => 'dashicons-yes-alt',
        'hierarchical'        => true,
        'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'revisions'],
        'has_archive'         => false,
        'rewrite'             => ['slug' => 'thank-you', 'with_front' => false],
    ]);
}

/**
 * @param string   $title
 * @param \WP_Post $post
 */
function thank_you_title_placeholder(string $title, \WP_Post $post): string
{
    if ($post->post_type === THANK_YOU_CPT) {
        return __('Admin label only (not shown on the site)', 'sccc');
    }

    return $title;
}

/**
 * Browser tab / SEO title: always “Thank you” — the post title is for the editor list only.
 *
 * @param  array<string, string>  $title
 * @return array<string, string>
 */
function thank_you_document_title_parts(array $title): array
{
    if (! is_singular(THANK_YOU_CPT)) {
        return $title;
    }

    $postId = (int) get_queried_object_id();
    $title['title'] = apply_filters('sccc_thank_you_document_title', __('Thank you', 'sccc'), $postId);

    return $title;
}

/**
 * Yoast SEO `<title>` when the plugin replaces the document title.
 */
function thank_you_wpseo_title(string $title): string
{
    if (! is_singular(THANK_YOU_CPT)) {
        return $title;
    }

    $postId = (int) get_queried_object_id();
    $main = apply_filters('sccc_thank_you_document_title', __('Thank you', 'sccc'), $postId);

    return $main.' – '.get_bloginfo('name', 'display');
}

/**
 * Rank Math `<title>` override (same intent as `thank_you_wpseo_title`).
 */
function thank_you_rank_math_title(string $title): string
{
    if (! is_singular(THANK_YOU_CPT)) {
        return $title;
    }

    $postId = (int) get_queried_object_id();
    $main = apply_filters('sccc_thank_you_document_title', __('Thank you', 'sccc'), $postId);

    return $main.' – '.get_bloginfo('name', 'display');
}
