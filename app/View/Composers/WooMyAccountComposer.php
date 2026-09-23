<?php
/**
 * app/View/Composers/WooMyAccountComposer.php
 * File: app/View/Composers/WooMyAccountComposer.php
 *
 * Update (this revision)
 * -----------------------------------------------------------------------------
 * Sidebar avatar should use the user's *cropped upload* when available.
 *
 * Context
 * -----------------------------------------------------------------------------
 * - Profile uploads are stored as user meta: `sccc_profile_image_id`
 * - That attachment is the "source of truth" for the custom profile image
 * - If no local upload exists (or it can’t be resolved), we fall back to WP avatar
 *
 * Notes
 * -----------------------------------------------------------------------------
 * - Keeps existing structure (no re-architecture).
 * - Only changes `user_avatar_url` to prefer the local uploaded/cropped image.
 */

namespace App\View\Composers;

use Roots\Acorn\View\Composer;
use App\Support\Members\MemberContext;

class WooMyAccountComposer extends Composer
{
    protected static $views = [
        'woocommerce.myaccount.my-account',
        'woocommerce.myaccount.dashboard',
    ];

    public function with(): array
    {
        $user_id = (int) get_current_user_id();
        $user    = $user_id ? get_user_by('id', $user_id) : null;

        $is_true_member = MemberContext::isTrueMember($user_id);

        $endpoint = '';
        if (function_exists('WC') && WC() && WC()->query) {
            $endpoint = (string) WC()->query->get_current_endpoint();
        }
        $endpoint = $endpoint !== '' ? $endpoint : 'dashboard';

        $menu_items = function_exists('wc_get_account_menu_items')
            ? (array) wc_get_account_menu_items()
            : [];

        // Hard denylist for your custom sidebar.
        $deny = ['downloads', 'customer-logout', 'logout', 'log-out'];
        foreach ($deny as $key) {
            if (isset($menu_items[$key])) {
                unset($menu_items[$key]);
            }
        }

        $menu = [];
        foreach ($menu_items as $key => $label) {
            $menu[] = [
                'key'   => (string) $key,
                'label' => (string) $label,
                'url'   => function_exists('wc_get_account_endpoint_url')
                    ? wc_get_account_endpoint_url($key)
                    : '#',
            ];
        }

        // Member dashboard data (kept aligned with your working version)
        $rank = 'Member';
        if (function_exists('pmpro_getMembershipLevelForUser') && $user_id) {
            $lvl = pmpro_getMembershipLevelForUser($user_id);
            if (is_object($lvl) && ! empty($lvl->name)) {
                $rank = (string) $lvl->name;
            }
        }

        $acf_user_key = $user_id ? 'user_' . $user_id : '';

        $membership_number = ($acf_user_key && function_exists('get_field'))
            ? (string) get_field('membership_number', $acf_user_key)
            : '';

        $membership_issued_raw = ($acf_user_key && function_exists('get_field'))
            ? (string) get_field('membership_issued_at', $acf_user_key)
            : '';

        $birth_date_raw = ($acf_user_key && function_exists('get_field'))
            ? (string) get_field('birth_date', $acf_user_key)
            : '';

        $member_since_year = $this->format_date_year($membership_issued_raw);
        $birth_date_fmt    = $this->format_date_mdy($birth_date_raw);

        // ---------------------------------------------------------------------
        // Service / Responder fields (members only)
        // ---------------------------------------------------------------------
        $is_veteran    = false;
        $veteran_type  = '';
        $agency_branch = '';

        if ($is_true_member && $acf_user_key && function_exists('get_field')) {
            $is_veteran    = (bool) get_field('is_veteran_first_responder', $acf_user_key);
            $veteran_type  = (string) (get_field('veteran_type', $acf_user_key) ?: '');
            $agency_branch = (string) (get_field('agency_branch', $acf_user_key) ?: '');

            // Normalize veteran_type in case ACF returns labels instead of values.
            $veteran_type = $this->normalize_veteran_type($veteran_type);
        }

        // Display string for the dashboard.
        $service_responder_display = 'No';

        if ($is_veteran) {
            $type_label = '';
            if ($veteran_type === 'veteran') {
                $type_label = 'Veteran';
            } elseif ($veteran_type === 'first_responder') {
                $type_label = 'First Responder';
            } elseif ($veteran_type === 'both') {
                $type_label = 'Veteran + First Responder';
            }

            $branch_label = trim($agency_branch);

            if ($type_label !== '' && $branch_label !== '') {
                $service_responder_display = $type_label . ' — ' . $branch_label;
            } elseif ($type_label !== '' && $branch_label === '') {
                $service_responder_display = $type_label . ' (details missing)';
            } elseif ($type_label === '' && $branch_label !== '') {
                $service_responder_display = 'Yes — ' . $branch_label;
            } else {
                $service_responder_display = 'Yes (details missing)';
            }
        }

        $vehicles = ($acf_user_key && function_exists('get_field'))
            ? (array) (get_field('vehicles', $acf_user_key) ?: [])
            : [];

        $member_id_display = $membership_number !== '' ? 'SC-' . $membership_number : '—';

        /**
         * Logout URL (nonce-safe) + redirect home
         * -----------------------------------------------------------------------------
         * This avoids the WordPress "confirm logout" screen by including the nonce.
         * Redirect is set to home.
         */
        $logout_url = wp_nonce_url(home_url('/logout/'), 'sccc-logout');

        /**
         * Sidebar avatar URL
         * -----------------------------------------------------------------------------
         * Prefer the uploaded/cropped Media Library attachment stored in:
         * - user meta: sccc_profile_image_id
         *
         * Fall back to WordPress avatar if none exists or URL can't be resolved.
         */
        $user_avatar_url = $this->preferred_avatar_url($user_id);

        return [
            'is_true_member'       => $is_true_member,
            'current_endpoint'     => $endpoint,
            'menu'                 => $menu,
            'user_display_name'    => $user ? (string) $user->display_name : '',
            'user_email'           => $user ? (string) $user->user_email : '',
            'user_avatar_url'      => $user_avatar_url,

            'rank'                 => $rank,
            'member_since_year'    => $member_since_year ?: '—',
            'birth_date'           => $birth_date_fmt ?: '—',
            'member_id_display'    => $member_id_display,

            // Existing flag (kept)
            'is_veteran'           => $is_veteran,

            // Member service/responder details
            'veteran_type'         => $veteran_type,
            'agency_branch'        => $agency_branch,
            'service_responder_display' => $service_responder_display,

            'vehicles'             => $vehicles,
            'events_attended'      => '—',

            'logout_url'           => $logout_url,
        ];
    }

    /**
     * Returns the avatar URL to use in the My Account UI.
     *
     * Rules:
     * 1) If user has an uploaded/cropped profile image (Media Library attachment),
     *    use that.
     * 2) Otherwise, use the normal WordPress avatar URL.
     */
    protected function preferred_avatar_url(int $user_id): string
    {
        if ($user_id <= 0) {
            return '';
        }

        $attachment_id = (int) get_user_meta($user_id, 'sccc_profile_image_id', true);

        if ($attachment_id > 0) {
            // Thumbnail is plenty for a small UI avatar, and keeps payload small.
            $url = (string) wp_get_attachment_image_url($attachment_id, 'thumbnail');

            if ($url !== '') {
                return $url;
            }
        }

        return (string) get_avatar_url($user_id, ['size' => 96]);
    }

    protected function format_date_mdy(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^\d{8}$/', $raw)) {
            $dt = \DateTime::createFromFormat('Ymd', $raw);
            if ($dt instanceof \DateTime) {
                return date_i18n('m/d/Y', $dt->getTimestamp());
            }
        }

        $ts = strtotime($raw);
        if ($ts) {
            return date_i18n('m/d/Y', $ts);
        }

        return '';
    }

    protected function format_date_year(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^\d{8}$/', $raw)) {
            $dt = \DateTime::createFromFormat('Ymd', $raw);
            if ($dt instanceof \DateTime) {
                return date_i18n('Y', $dt->getTimestamp());
            }
        }

        $ts = strtotime($raw);
        if ($ts) {
            return date_i18n('Y', $ts);
        }

        return '';
    }

    /**
     * Normalize veteran_type to stored VALUE (never label).
     * Accepts: veteran|first_responder|both OR labels like "Veteran".
     */
    protected function normalize_veteran_type(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $allowed = ['veteran', 'first_responder', 'both'];
        if (in_array($raw, $allowed, true)) {
            return $raw;
        }

        $map = [
            'Veteran' => 'veteran',
            'veteran' => 'veteran',
            'First Responder' => 'first_responder',
            'first responder' => 'first_responder',
            'first_responder' => 'first_responder',
            'Both' => 'both',
            'both' => 'both',
        ];

        return $map[$raw] ?? '';
    }
}
