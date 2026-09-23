<?php
/**
 * app/Support/Integrations/WooMyAccountPmproMembershipEndpoint.php
 * File: app/Support/Integrations/WooMyAccountPmproMembershipEndpoint.php
 *
 * What this does
 * -----------------------------------------------------------------------------
 * 1) Adds a "Membership" endpoint/tab in Woo My Account:
 *      /my-account/membership/
 *
 * 2) Renders PMPro membership status + invoices inside that endpoint.
 *
 * 3) Global My Account housekeeping:
 *    - Removes "Downloads" (you don't offer downloads).
 *    - Removes Woo "Logout" menu item (we render a single Sign Out link).
 *
 * 4) Logout behavior (your request):
 *    - No WP "Are you sure you want to log out?" confirmation screen.
 *    - Always redirect to Home after logout.
 *
 * Why the confirmation screen can still happen even with _wpnonce visible
 * -----------------------------------------------------------------------------
 * WordPress shows the confirmation screen when the nonce is missing/invalid.
 * Some setups can cause a mismatch (plugins/security/caching/rewrite oddities).
 *
 * The fix here is to intercept the wp-login.php logout request:
 * - If the nonce is missing/invalid, redirect to a fresh wp_logout_url(home).
 * - If the nonce is valid, let WordPress complete logout normally.
 */

namespace App\Support\Integrations;

class WooMyAccountPmproMembershipEndpoint
{
    protected string $endpoint = 'membership';
    protected string $label = 'Membership';

    public function register(): void
    {
        // Endpoint plumbing
        add_action('init', [$this, 'addEndpoint']);
        add_filter('query_vars', [$this, 'addQueryVars']);

        // Menu housekeeping + injection (run as late as possible)
        add_filter('woocommerce_account_menu_items', [$this, 'filterMenuItems'], PHP_INT_MAX, 1);

        // Render the Membership endpoint
        add_action('woocommerce_account_' . $this->endpoint . '_endpoint', [$this, 'renderEndpoint']);

        // Force redirects to home on logout
        add_filter('woocommerce_logout_default_redirect_url', [$this, 'logoutRedirectUrl'], PHP_INT_MAX, 1);
        add_filter('logout_redirect', [$this, 'logoutRedirect'], PHP_INT_MAX, 3);

        /**
         * HARD FIX: eliminate WP logout confirmation screen
         * -----------------------------------------------------------------------------
         * Runs on wp-login.php. If a user hits action=logout with a missing/invalid
         * nonce, we bounce them to a fresh nonce URL (wp_logout_url) with home redirect.
         */
        add_action('login_init', [$this, 'forceNonceSafeLogoutFlow'], PHP_INT_MAX);
    }

    public function addEndpoint(): void
    {
        add_rewrite_endpoint($this->endpoint, EP_ROOT | EP_PAGES);
    }

    public function addQueryVars(array $vars): array
    {
        $vars[] = $this->endpoint;
        return $vars;
    }

    public function filterMenuItems(array $items): array
    {
        if (empty($items) || ! is_array($items)) {
            return $items;
        }

        // Global removals
        $deny = ['downloads', 'customer-logout', 'logout', 'log-out'];
        foreach ($deny as $key) {
            if (isset($items[$key])) {
                unset($items[$key]);
            }
        }

        // Insert Membership after Dashboard
        $new = [];
        $inserted = false;

        foreach ($items as $key => $label) {
            $new[$key] = $label;

            if ($key === 'dashboard') {
                $new[$this->endpoint] = $this->label;
                $inserted = true;
            }
        }

        if (! $inserted) {
            $new[$this->endpoint] = $this->label;
        }

        return $new;
    }

    /**
     * If wp-login.php?action=logout is hit without a valid nonce,
     * redirect to a fresh nonce-safe wp_logout_url(home).
     *
     * This prevents the WordPress confirmation page entirely.
     */
    public function forceNonceSafeLogoutFlow(): void
    {
        // Only on the logout action
        $action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
        if ($action !== 'logout') {
            return;
        }

        // If not logged in, just bounce home.
        if (! is_user_logged_in()) {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        // If nonce is missing, bounce to a fresh nonce logout URL.
        if (empty($_REQUEST['_wpnonce'])) {
            wp_safe_redirect(wp_logout_url(home_url('/')));
            exit;
        }

        // If nonce is present but invalid, bounce to a fresh nonce logout URL.
        $nonce = (string) $_REQUEST['_wpnonce'];

        /**
         * WordPress uses the 'log-out' nonce action for logout.
         * If this doesn't validate, WordPress will show the confirmation screen.
         */
        if (! wp_verify_nonce($nonce, 'log-out')) {
            wp_safe_redirect(wp_logout_url(home_url('/')));
            exit;
        }

        // If nonce is valid, do nothing — WordPress will proceed with logout normally.
    }

    public function logoutRedirectUrl(string $default): string
    {
        return home_url('/');
    }

    public function logoutRedirect(string $redirect_to, string $requested_redirect_to, $user): string
    {
        return home_url('/');
    }

    public function renderEndpoint(): void
    {
        echo '<div class="woocommerce-account-membership">';

        if (! shortcode_exists('pmpro_account') && ! shortcode_exists('pmpro_invoice')) {
            echo '<p>Your membership dashboard is currently unavailable.</p>';
            echo '</div>';
            return;
        }

        // Membership status/actions
        if (shortcode_exists('pmpro_account')) {
            echo '<div class="sccc-woo-account__membership-status">';
            echo do_shortcode('[pmpro_account section="membership" title=""]');
            echo '</div>';
        }

        // Invoices
        echo '<div class="sccc-woo-account__membership-invoices">';
        echo '<h3 class="sccc-woo-account__heading">Invoices</h3>';

        $invoice_html = '';

        if (shortcode_exists('pmpro_invoice')) {
            $invoice_html = $this->renderPmproInvoiceWithForcedContext();
        }

        if ($this->hasMeaningfulHtml($invoice_html)) {
            echo $invoice_html;
        } else {
            echo $this->renderInvoicesFallbackFromOrders();
        }

        echo '</div>'; // invoices
        echo '</div>'; // wrapper
    }

    protected function renderPmproInvoiceWithForcedContext(): string
    {
        if (! shortcode_exists('pmpro_invoice')) {
            return '';
        }

        global $pmpro_page_name;

        $original = $pmpro_page_name ?? null;

        $pmpro_page_name = 'invoice';
        $html = (string) do_shortcode('[pmpro_invoice]');

        if ($original === null) {
            unset($pmpro_page_name);
        } else {
            $pmpro_page_name = $original;
        }

        return trim($html);
    }

    protected function renderInvoicesFallbackFromOrders(): string
    {
        $user_id = (int) get_current_user_id();
        if ($user_id <= 0) {
            return '<p>Please log in to view your invoices.</p>';
        }

        if (! class_exists('\MemberOrder') || ! method_exists('\MemberOrder', 'get_orders')) {
            return '<p>Invoices are currently unavailable.</p>';
        }

        $orders = \MemberOrder::get_orders(['user_id' => $user_id]);

        if (empty($orders) || ! is_array($orders)) {
            return '<p>No invoices found for your account.</p>';
        }

        $invoices = array_filter($orders, function ($o) {
            return is_object($o) && ! empty($o->code);
        });

        if (empty($invoices)) {
            return '<p>No invoices found for your account.</p>';
        }

        usort($invoices, function ($a, $b) {
            $at = isset($a->timestamp) ? (int) $a->timestamp : 0;
            $bt = isset($b->timestamp) ? (int) $b->timestamp : 0;
            return $bt <=> $at;
        });

        $invoices = array_slice($invoices, 0, 10);

        $date_format = (string) get_option('date_format');

        $rows = '';
        foreach ($invoices as $order) {
            $code = (string) $order->code;

            $date = '—';
            if (! empty($order->timestamp)) {
                $date = date_i18n($date_format, (int) $order->timestamp);
            } elseif (method_exists($order, 'getTimestamp')) {
                $ts = (int) $order->getTimestamp();
                if ($ts > 0) {
                    $date = date_i18n($date_format, $ts);
                }
            }

            $total = '—';
            if (isset($order->total) && $order->total !== '') {
                $total = function_exists('pmpro_formatPrice')
                    ? (string) pmpro_formatPrice($order->total)
                    : (string) $order->total;
            }

            $status = '—';
            if (! empty($order->status)) {
                $status = ucfirst((string) $order->status);
            } elseif (! empty($order->gateway) && ! empty($order->payment_transaction_id)) {
                $status = 'Paid';
            }

            $rows .= '<tr>';
            $rows .= '<td>' . esc_html($code) . '</td>';
            $rows .= '<td>' . esc_html($date) . '</td>';
            $rows .= '<td>' . esc_html($status) . '</td>';
            $rows .= '<td>' . esc_html($total) . '</td>';
            $rows .= '</tr>';
        }

        $out  = '<table class="shop_table shop_table_responsive my_account_orders">';
        $out .= '<thead><tr>';
        $out .= '<th>Invoice</th><th>Date</th><th>Status</th><th>Total</th>';
        $out .= '</tr></thead>';
        $out .= '<tbody>' . $rows . '</tbody>';
        $out .= '</table>';

        return $out;
    }

    protected function hasMeaningfulHtml(string $html): bool
    {
        $html = trim($html);
        if ($html === '') {
            return false;
        }

        $stripped = trim(wp_strip_all_tags($html));
        return strlen($stripped) > 40;
    }
}
