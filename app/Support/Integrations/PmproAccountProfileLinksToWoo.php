<?php
/**
 * app/Support/Integrations/PmproAccountProfileLinksToWoo.php
 * File: app/Support/Integrations/PmproAccountProfileLinksToWoo.php
 *
 * Purpose
 * -----------------------------------------------------------------------------
 * Members are Subscribers and cannot access wp-admin profile pages.
 * PMPro “Profile” links often point to wp-admin/profile.php.
 *
 * Member-only scope
 * -----------------------------------------------------------------------------
 * Only rewrite these links for “true members” (role + active PMPro).
 * Everyone else: leave PMPro output untouched.
 */

namespace App\Support\Integrations;

use App\Support\Members\MemberContext;

class PmproAccountProfileLinksToWoo
{
    public function register(): void
    {
        add_filter('pmpro_account_profile_action_links', [$this, 'rewriteLinks'], 10, 1);
    }

    public function rewriteLinks(array $links): array
    {
        if (! MemberContext::isTrueMember()) {
            return $links;
        }

        if (! function_exists('wc_get_account_endpoint_url')) {
            return $links;
        }

        $edit_account_url  = wc_get_account_endpoint_url('edit-account');
        $lost_password_url = wc_get_account_endpoint_url('lost-password');

        $rewritten = [];

        foreach ($links as $key => $html) {
            if (! is_string($html) || $html === '') {
                $rewritten[$key] = $html;
                continue;
            }

            // Replace wp-admin profile links.
            $html = str_replace('wp-admin/profile.php', $edit_account_url, $html);
            $html = str_replace(admin_url('profile.php'), $edit_account_url, $html);

            // Replace WP lost password link (if present).
            $html = str_replace('wp-login.php?action=lostpassword', $lost_password_url, $html);

            $rewritten[$key] = $html;
        }

        return $rewritten;
    }
}

(new \App\Support\Integrations\PmproAccountProfileLinksToWoo())->register();
