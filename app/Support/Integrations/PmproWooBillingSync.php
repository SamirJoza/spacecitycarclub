<?php
/**
 * app/Support/Integrations/PmproWooAddressSync.php
 *
 * What this does
 * -----------------------------------------------------------------------------
 * On PMPro checkout completion, copy PMPro billing address fields into:
 * - WooCommerce billing_* user meta
 * - WooCommerce shipping_* user meta (mirrors billing; individuals use-case)
 *
 * Why this version works in Sage/theme-loaded files
 * -----------------------------------------------------------------------------
 * We register the PMPro action immediately (NOT inside plugins_loaded).
 * WordPress can store this callback even if PMPro fires the action later.
 *
 * Debugging
 * -----------------------------------------------------------------------------
 * Enable via:
 * add_filter('sccc_pmpro_to_woo_debug', '__return_true');
 *
 * Overwrite strategy
 * -----------------------------------------------------------------------------
 * Default: overwrite = true (you asked to ensure usermeta is correctly populated)
 * Change via:
 * add_filter('sccc_pmpro_to_woo_overwrite', '__return_false');
 */

namespace App\Support\Integrations;

class PmproWooAddressSync
{
    /**
     * Register WordPress hooks.
     * Safe to call when the theme loads (even if plugins loaded earlier).
     */
    public function register(): void
    {
        // Register immediately. If PMPro is not active, this action will never fire.
        add_action('pmpro_after_checkout', [$this, 'sync'], 10, 2);
    }

    /**
     * Sync PMPro billing address -> Woo billing & shipping user meta.
     *
     * @param int   $user_id
     * @param mixed $morder  PMPro MemberOrder object (may contain billing data)
     */
    public function sync(int $user_id, $morder = null): void
    {
        if ($user_id <= 0) {
            return;
        }

        // Only do work if WooCommerce is available at runtime.
        if (! class_exists('\WooCommerce') || ! class_exists('\WC_Customer')) {
            $this->debug('WooCommerce not available at runtime; aborting sync.', [
                'user_id' => $user_id,
                'has_woo' => class_exists('\WooCommerce'),
                'has_customer' => class_exists('\WC_Customer'),
            ]);
            return;
        }

        $overwrite = (bool) apply_filters('sccc_pmpro_to_woo_overwrite', true, $user_id, $morder);

        // Pull billing payload from the order (best for "what user just entered"), then fallback to pmpro_b* meta.
        $pmpro = $this->getPmproBillingPayload($user_id, $morder);

        $this->debug('PMPro payload detected', [
            'user_id' => $user_id,
            'overwrite' => $overwrite,
            'pmpro_payload' => $pmpro,
        ]);

        // If we still don't have address data, don't write blanks into Woo.
        if (empty($pmpro['address_1']) && empty($pmpro['city']) && empty($pmpro['postcode'])) {
            $this->debug('No usable PMPro address values found; nothing written.', [
                'user_id' => $user_id,
            ]);
            return;
        }

        $this->writeWooCustomerMeta($user_id, $pmpro, $overwrite);

        $this->debug('PMPro -> Woo sync completed', [
            'user_id' => $user_id,
            'overwrite' => $overwrite,
        ]);
    }

    /**
     * Build a normalized billing payload from PMPro.
     *
     * Priority:
     * 1) $morder->billing object (if present)
     * 2) pmpro_b* user meta keys
     */
    protected function getPmproBillingPayload(int $user_id, $morder = null): array
    {
        $payload = [
            'first_name' => '',
            'last_name'  => '',
            'address_1'  => '',
            'address_2'  => '',
            'city'       => '',
            'state'      => '',
            'postcode'   => '',
            'country'    => '',
            'phone'      => '',
            'email'      => '',
        ];

        // Use WP account email for Woo billing_email.
        $user = get_user_by('id', $user_id);
        if ($user && ! empty($user->user_email)) {
            $payload['email'] = (string) $user->user_email;
        }

        // Try to pull from the order billing object (varies by gateway/config).
        if (is_object($morder) && isset($morder->billing) && is_object($morder->billing)) {
            $billing = $morder->billing;

            $payload['address_1'] = isset($billing->street)  ? trim((string) $billing->street)  : '';
            $payload['address_2'] = isset($billing->street2) ? trim((string) $billing->street2) : '';
            $payload['city']      = isset($billing->city)    ? trim((string) $billing->city)    : '';
            $payload['state']     = isset($billing->state)   ? trim((string) $billing->state)   : '';
            $payload['postcode']  = isset($billing->zip)     ? trim((string) $billing->zip)     : '';
            $payload['country']   = isset($billing->country) ? trim((string) $billing->country) : '';
            $payload['phone']     = isset($billing->phone)   ? trim((string) $billing->phone)   : '';

            // Name may exist as a single string; we'll still prefer pmpro_bfirstname/blastname if present.
            if (isset($billing->name) && is_string($billing->name) && $billing->name !== '') {
                $parts = preg_split('/\s+/', trim($billing->name), 2);
                $payload['first_name'] = $parts[0] ?? '';
                $payload['last_name']  = $parts[1] ?? '';
            }
        }

        // Fallback: pmpro_b* user meta (these are often the real source of truth).
        $pmpro_meta_map = [
            'pmpro_bfirstname' => 'first_name',
            'pmpro_blastname'  => 'last_name',
            'pmpro_baddress1'  => 'address_1',
            'pmpro_baddress2'  => 'address_2',
            'pmpro_bcity'      => 'city',
            'pmpro_bstate'     => 'state',
            'pmpro_bzipcode'   => 'postcode',
            'pmpro_bcountry'   => 'country',
            'pmpro_bphone'     => 'phone',
        ];

        foreach ($pmpro_meta_map as $pmpro_key => $field) {
            $val = get_user_meta($user_id, $pmpro_key, true);

            // If order billing didn't provide it, or it's empty, fill from meta.
            if (($payload[$field] === '' || $payload[$field] === null) && $val !== '' && $val !== null) {
                $payload[$field] = trim((string) $val);
            }
        }

        return $payload;
    }

    /**
     * Write Woo billing_* and shipping_* data using WC_Customer + user meta fallback.
     */
    protected function writeWooCustomerMeta(int $user_id, array $pmpro, bool $overwrite): void
    {
        $billing = [
            'billing_first_name' => $pmpro['first_name'],
            'billing_last_name'  => $pmpro['last_name'],
            'billing_address_1'  => $pmpro['address_1'],
            'billing_address_2'  => $pmpro['address_2'],
            'billing_city'       => $pmpro['city'],
            'billing_state'      => $pmpro['state'],
            'billing_postcode'   => $pmpro['postcode'],
            'billing_country'    => $pmpro['country'],
            'billing_phone'      => $pmpro['phone'],
            'billing_email'      => $pmpro['email'],
        ];

        $shipping = [
            'shipping_first_name' => $pmpro['first_name'],
            'shipping_last_name'  => $pmpro['last_name'],
            'shipping_address_1'  => $pmpro['address_1'],
            'shipping_address_2'  => $pmpro['address_2'],
            'shipping_city'       => $pmpro['city'],
            'shipping_state'      => $pmpro['state'],
            'shipping_postcode'   => $pmpro['postcode'],
            'shipping_country'    => $pmpro['country'],
        ];

        // 1) Preferred: WC_Customer API.
        try {
            $customer = new \WC_Customer($user_id);

            $this->setCustomerField($customer, 'set_billing_first_name', $billing['billing_first_name'], $overwrite);
            $this->setCustomerField($customer, 'set_billing_last_name',  $billing['billing_last_name'],  $overwrite);
            $this->setCustomerField($customer, 'set_billing_address_1',  $billing['billing_address_1'],  $overwrite);
            $this->setCustomerField($customer, 'set_billing_address_2',  $billing['billing_address_2'],  $overwrite);
            $this->setCustomerField($customer, 'set_billing_city',       $billing['billing_city'],       $overwrite);
            $this->setCustomerField($customer, 'set_billing_state',      $billing['billing_state'],      $overwrite);
            $this->setCustomerField($customer, 'set_billing_postcode',   $billing['billing_postcode'],   $overwrite);
            $this->setCustomerField($customer, 'set_billing_country',    $billing['billing_country'],    $overwrite);
            $this->setCustomerField($customer, 'set_billing_phone',      $billing['billing_phone'],      $overwrite);
            $this->setCustomerField($customer, 'set_billing_email',      $billing['billing_email'],      $overwrite);

            $this->setCustomerField($customer, 'set_shipping_first_name', $shipping['shipping_first_name'], $overwrite);
            $this->setCustomerField($customer, 'set_shipping_last_name',  $shipping['shipping_last_name'],  $overwrite);
            $this->setCustomerField($customer, 'set_shipping_address_1',  $shipping['shipping_address_1'],  $overwrite);
            $this->setCustomerField($customer, 'set_shipping_address_2',  $shipping['shipping_address_2'],  $overwrite);
            $this->setCustomerField($customer, 'set_shipping_city',       $shipping['shipping_city'],       $overwrite);
            $this->setCustomerField($customer, 'set_shipping_state',      $shipping['shipping_state'],      $overwrite);
            $this->setCustomerField($customer, 'set_shipping_postcode',   $shipping['shipping_postcode'],   $overwrite);
            $this->setCustomerField($customer, 'set_shipping_country',    $shipping['shipping_country'],    $overwrite);

            $customer->save();
        } catch (\Throwable $e) {
            $this->debug('WC_Customer save failed; will still attempt user_meta writes.', [
                'user_id' => $user_id,
                'error' => $e->getMessage(),
            ]);
        }

        // 2) Fallback/guarantee: write user meta directly (Woo reads these for checkout prefill).
        $this->writeMetaArray($user_id, $billing, $overwrite);
        $this->writeMetaArray($user_id, $shipping, $overwrite);
    }

    protected function setCustomerField(\WC_Customer $customer, string $setter, string $value, bool $overwrite): void
    {
        if ($value === '' || ! method_exists($customer, $setter)) {
            return;
        }

        if (! $overwrite) {
            $getter = str_replace('set_', 'get_', $setter);
            if (method_exists($customer, $getter)) {
                $existing = $customer->{$getter}();
                if (! empty($existing)) {
                    return;
                }
            }
        }

        $customer->{$setter}($value);
    }

    protected function writeMetaArray(int $user_id, array $meta, bool $overwrite): void
    {
        foreach ($meta as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if (! $overwrite) {
                $existing = get_user_meta($user_id, $key, true);
                if ($existing !== '' && $existing !== null) {
                    continue;
                }
            }

            update_user_meta($user_id, $key, $value);
        }
    }

    protected function debug(string $message, array $context = []): void
    {
        $enabled = (bool) apply_filters('sccc_pmpro_to_woo_debug', false);

        if (! defined('WP_DEBUG') || ! WP_DEBUG || ! $enabled) {
            return;
        }

        error_log('[SCCC PMPro->Woo] ' . $message . ' ' . wp_json_encode($context));
    }
}

// Auto-boot when included (matches your require_once pattern).
(new \App\Support\Integrations\PmproWooAddressSync())->register();
