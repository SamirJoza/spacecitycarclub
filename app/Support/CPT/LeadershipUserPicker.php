<?php

/**
 * --------------------------------------------------------------------------
 * File path + filename: app/Support/CPT/LeadershipUserPicker.php
 * --------------------------------------------------------------------------
 * Purpose:
 * - Make the ACF user selector easier to scan on Leadership edit screens.
 *
 * Why this file exists:
 * - ACF/Select2 can visually group users by role, which adds noise on this
 *   screen when the only job is selecting the linked member.
 * - Hiding the group headings keeps the selector flatter and easier to search
 *   without changing the underlying query behavior.
 */

namespace App;

defined('ABSPATH') || exit;

add_action('admin_head', __NAMESPACE__ . '\\leadership_user_picker_hide_role_groups');

/**
 * Hide visual role group labels in the linked member user selector.
 */
function leadership_user_picker_hide_role_groups(): void
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if (!$screen || ($screen->post_type ?? '') !== (LEADERSHIP_CPT ?? 'leadership')) {
        return;
    }

    echo '<style>
        .acf-field[data-name="leadership_user"] .select2-results__group {
            display:none !important;
        }

        .acf-field[data-name="leadership_user"] .select2-results__options--nested {
            padding-left:0 !important;
        }
    </style>';
}