<?php
/**
 * app/Support/Integrations/StripePaymentElementAppearance.php
 * File: app/Support/Integrations/StripePaymentElementAppearance.php
 *
 * Purpose
 * -----------------------------------------------------------------------------
 * Style the WooCommerce Stripe Payment Element (card number, expiry, CVC) so
 * it matches the SCCC checkout fields.
 *
 * Why PHP and not CSS
 * -----------------------------------------------------------------------------
 * The card fields live inside an iframe served by js.stripe.com. Page CSS
 * cannot reach inside it. Stripe only accepts styling through its Appearance
 * API, which the WooCommerce Stripe Gateway exposes via the
 * `wc_stripe_upe_params` filter:
 *   - `blocksAppearance` → Checkout Block (what the site uses)
 *   - `appearance`       → classic / shortcode checkout
 * Docs: https://woocommerce.com/document/stripe/customization/style-payment-form/
 *
 * The wrapper around the iframe (radio row, "Save payment information"
 * checkbox) is regular page markup and is styled in
 * resources/css/components/woocommerce-stripe-payment.css.
 *
 * Light / dark
 * -----------------------------------------------------------------------------
 * The site defaults to dark. The visitor's choice lives in localStorage, which
 * PHP cannot read, so resources/js/components/theme.js mirrors it into the
 * `scc-theme` cookie. Light values are used only when that cookie says
 * "light". Toggling the theme on the checkout page restyles the card fields on
 * the next page load (Stripe sets appearance when the element is created).
 *
 * Colour values
 * -----------------------------------------------------------------------------
 * Stripe cannot read CSS custom properties, so the values below are copies of
 * the --sccc-wc-field-* tokens in resources/css/layouts/woo-checkout.css.
 * Keep the two in sync when re-skinning.
 */

namespace App\Support\Integrations;

final class StripePaymentElementAppearance
{
    /** Bump when the values below change, so stale cached appearances are cleared. */
    private const VERSION = '1';

    private const OPTION = 'sccc_stripe_appearance_version';

    public static function register(): void
    {
        add_filter('wc_stripe_upe_params', [self::class, 'filterParams'], 20);
        add_action('admin_init', [self::class, 'clearCachedAppearance']);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function filterParams($params)
    {
        if (! is_array($params)) {
            return $params;
        }

        $appearance = self::appearance(self::mode());

        $params['blocksAppearance'] = $appearance;
        $params['appearance']       = $appearance;

        return $params;
    }

    /**
     * WooCommerce Stripe caches its own computed appearance in transients.
     * Clear them once per VERSION so nothing stale lingers.
     */
    public static function clearCachedAppearance(): void
    {
        if (get_option(self::OPTION) === self::VERSION) {
            return;
        }

        delete_transient('wc_stripe_appearance');
        delete_transient('wc_stripe_blocks_appearance');
        update_option(self::OPTION, self::VERSION, false);
    }

    private static function mode(): string
    {
        $cookie = isset($_COOKIE['scc-theme']) ? sanitize_key(wp_unslash($_COOKIE['scc-theme'])) : '';

        return $cookie === 'light' ? 'light' : 'dark';
    }

    /**
     * Stripe Appearance API object.
     * https://docs.stripe.com/elements/appearance-api
     */
    private static function appearance(string $mode): object
    {
        $c = $mode === 'light' ? self::lightColors() : self::darkColors();

        $font = 'Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';

        return (object) [
            'theme'     => $mode === 'light' ? 'stripe' : 'night',
            'labels'    => 'floating', // matches the Woo block fields (label inside, floats up)
            'variables' => (object) [
                'colorPrimary'         => $c['primary'],
                'colorBackground'      => $c['field'],
                'colorText'            => $c['text'],
                'colorTextSecondary'   => $c['label'],
                'colorTextPlaceholder' => $c['placeholder'],
                'colorIcon'            => $c['icon'],
                'colorDanger'          => '#ef4444',
                'fontFamily'           => $font,
                'fontSizeBase'         => '15px',
                'borderRadius'         => '12px',
                'spacingUnit'          => '4px',
                'gridRowSpacing'       => '14px',
                'gridColumnSpacing'    => '14px',
            ],
            'rules' => (object) [
                // Outer card that wraps the "Card" tab. Stripe paints it white
                // by default; blend it into the checkout panel instead.
                '.AccordionItem' => (object) [
                    'backgroundColor' => $c['panel'],
                    'border'          => '1px solid ' . $c['border'],
                    'borderRadius'    => '14px',
                    'boxShadow'       => 'none',
                    'padding'         => '18px',
                ],
                '.AccordionItem--selected' => (object) [
                    'color' => $c['text'],
                ],
                '.Label' => (object) [
                    'color' => $c['label'],
                ],
                '.Input' => (object) [
                    'backgroundColor' => $c['field'],
                    'border'          => '1px solid ' . $c['border'],
                    'borderRadius'    => '12px',
                    'boxShadow'       => 'inset 0 1px 0 ' . $c['shine'],
                    'color'           => $c['text'],
                ],
                '.Input:hover' => (object) [
                    'border' => '1px solid ' . $c['hoverBorder'],
                ],
                '.Input:focus' => (object) [
                    'border'    => '1px solid ' . $c['focusBorder'],
                    'boxShadow' => '0 0 0 4px ' . $c['focusRing'],
                    'outline'   => 'none',
                ],
                '.Input--invalid' => (object) [
                    'border'    => '1px solid #ef4444',
                    'boxShadow' => '0 0 0 4px rgba(239, 68, 68, 0.14)',
                ],
                '.Input::placeholder' => (object) [
                    'color' => $c['placeholder'],
                ],
                '.Error' => (object) [
                    'color'    => '#f87171',
                    'fontSize' => '13px',
                ],
            ],
        ];
    }

    /** Mirrors --sccc-wc-field-* (dark) in woo-checkout.css. */
    private static function darkColors(): array
    {
        return [
            'primary'     => '#65d2ff',
            'panel'       => '#0f1625',
            'field'       => '#111827',
            'text'        => '#eef3ff',
            'label'       => 'rgba(221, 229, 243, 0.62)',
            'placeholder' => 'rgba(221, 229, 243, 0.46)',
            'icon'        => 'rgba(221, 229, 243, 0.78)',
            'border'      => 'rgba(255, 255, 255, 0.18)',
            'hoverBorder' => 'rgba(101, 210, 255, 0.28)',
            'focusBorder' => 'rgba(101, 210, 255, 0.58)',
            'focusRing'   => 'rgba(101, 210, 255, 0.14)',
            'shine'       => 'rgba(255, 255, 255, 0.035)',
        ];
    }

    /** Mirrors --sccc-wc-field-* (light) in woo-checkout.css. */
    private static function lightColors(): array
    {
        return [
            'primary'     => '#135bec',
            'panel'       => '#f6f8fc',
            'field'       => '#ffffff',
            'text'        => '#132033',
            'label'       => 'rgba(19, 32, 51, 0.58)',
            'placeholder' => 'rgba(19, 32, 51, 0.42)',
            'icon'        => 'rgba(19, 32, 51, 0.68)',
            'border'      => 'rgba(19, 32, 51, 0.18)',
            'hoverBorder' => 'rgba(41, 121, 255, 0.32)',
            'focusBorder' => 'rgba(41, 121, 255, 0.56)',
            'focusRing'   => 'rgba(41, 121, 255, 0.14)',
            'shine'       => 'rgba(255, 255, 255, 0.72)',
        ];
    }
}

StripePaymentElementAppearance::register();
