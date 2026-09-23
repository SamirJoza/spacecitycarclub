<?php

/**
 * --------------------------------------------------------------------------
 * File path + filename: app/Support/CPT/LeadershipMetaBoxes.php
 * --------------------------------------------------------------------------
 * Purpose:
 * - Remove the default WordPress taxonomy meta box for Leadership.
 *
 * Why this file exists:
 * - The Leadership taxonomy assignment is handled through the ACF field group.
 * - We do not want the default WordPress taxonomy UI to appear alongside it,
 *   because that creates duplicate controls and invites confusion.
 * - Editors should assign an existing leadership category, but term management
 *   itself should happen through the taxonomy admin screen.
 */

namespace App;

defined('ABSPATH') || exit;

add_action('add_meta_boxes', __NAMESPACE__ . '\\leadership_remove_taxonomy_metaboxes', 100);

/**
 * Remove the default taxonomy meta box for the Leadership category taxonomy.
 */
function leadership_remove_taxonomy_metaboxes(): void
{
    $post_type = LEADERSHIP_CPT ?? 'leadership';
    $group_tax = LEADERSHIP_GROUP_TAX ?? 'leadership_group';

    /**
     * WordPress uses different IDs depending on taxonomy style:
     * - `tagsdiv-{taxonomy}` for non-hierarchical taxonomies
     * - `{taxonomy}div` for hierarchical taxonomies
     *
     * We remove both so this file remains safe even if the taxonomy style ever
     * changes later.
     */
    remove_meta_box('tagsdiv-' . $group_tax, $post_type, 'side');
    remove_meta_box($group_tax . 'div', $post_type, 'side');
}