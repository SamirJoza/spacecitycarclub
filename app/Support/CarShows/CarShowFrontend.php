<?php

/**
 * File path + filename: app/Support/CarShows/CarShowFrontend.php
 *
 * Purpose:
 * - What a car-show event page shows instead of MagePeople's ticket box and
 *   seat counts. Called from the theme's MagePeople template overrides:
 *     mage-event/layout/registration.php  → renderRegistration()
 *     mage-event/layout/seat_status.php   → renderSeatStatus()
 *   Events that are not car shows fall through to MagePeople's own templates.
 *
 * Registration box:
 * - "Register your car" with the Car Registration form (open shows), or a
 *   friendly closed/full message.
 */

namespace App\Support\CarShows;

use App\Support\Vendors\Vendors;


defined('ABSPATH') || exit;

final class CarShowFrontend
{
    private static bool $assetsPrinted = false;

    public static function renderRegistration(int $eventId): void
    {
        self::printAssets();

        echo '<div class="sccc-carshow" id="car-registration">';

        if (CarShows::isOpen($eventId)) {
            self::renderForm($eventId);
        } else {
            echo '<div class="sccc-carshow__card sccc-carshow__card--closed"><h3 class="sccc-carshow__title">'.esc_html__('Car registration', 'sccc').'</h3>'
                .'<p>'.esc_html(CarShows::closedMessage($eventId)).'</p>';

            if (current_user_can('edit_post', $eventId) && CarShows::closedReason($eventId) === 'not_set_up') {
                echo '<p class="sccc-carshow__admin">'.esc_html__('Admin: add at least one ticket type (e.g. "Early Bird", "Regular") in this event\'s Ticket & Pricing step to open car registration.', 'sccc').'</p>';
            }

            echo '</div>';
        }

        echo '</div>';
    }

    private static function renderForm(int $eventId): void
    {
        $formId = CarRegistrationForm::formId();
        $tickets = CarShows::onSaleTickets($eventId);
        $count = CarShows::registeredCount($eventId);
        $left = CarShows::spotsLeft($eventId);

        echo '<div class="sccc-carshow__card">';
        echo '<h3 class="sccc-carshow__title">'.esc_html__('Register your car', 'sccc').'</h3>';

        $facts = [];
        foreach ($tickets as $ticket) {
            $fact = $ticket['name'].': '.($ticket['price'] > 0 ? sprintf(__('%s per car', 'sccc'), CarShows::money($ticket['price'])) : __('free', 'sccc'));
            if ($ticket['ends']) {
                $fact .= ' · '.sprintf(__('until %s', 'sccc'), wp_date('M j', $ticket['ends']));
            }
            $facts[] = $fact;
        }
        $facts[] = sprintf(_n('%d car registered', '%d cars registered', $count, 'sccc'), $count);
        if ($left !== PHP_INT_MAX && $left <= 25) {
            $facts[] = sprintf(_n('%d spot left', '%d spots left', $left, 'sccc'), $left);
        }

        echo '<ul class="sccc-carshow__facts">';
        foreach ($facts as $fact) {
            echo '<li>'.esc_html($fact).'</li>';
        }
        echo '</ul>';

        if (! $formId || ! function_exists('gravity_form')) {
            echo '<p>'.esc_html__('Online registration is being set up. Please check back soon.', 'sccc').'</p>';
        } else {
            gravity_form($formId, false, false, false, [CarRegistrationForm::QUERY_SHOW => $eventId], true, 0, true);
        }

        echo '</div>';
    }

    /**
     * "Vendors wanted" note for club shows that accept vendor applications.
     * Printed just above the registration box, i.e. below the event description.
     * Hidden when no Vendor page is set in Theme Settings → Vendor Settings.
     */
    public static function renderVendorCard(int $eventId): void
    {
        if (! class_exists(Vendors::class)) {
            return;
        }

        if (! Vendors::showIsOpen($eventId)) {
            // Admins: say why the box is hidden, but only when vendors were meant to be on.
            $reasons = Vendors::showClosedReasons($eventId);
            if (current_user_can('manage_options') && $reasons && (get_post_meta($eventId, Vendors::EVENT_ACCEPTING, true) !== '' || CarShows::isCarShow($eventId))) {
                echo '<p class="sccc-carshow__admin" style="margin:1rem 0;">'
                    .esc_html(sprintf(__('Only admins see this: the "Vendors wanted" box is hidden because %s.', 'sccc'), implode('; ', $reasons)))
                    .'</p>';
            }

            return;
        }

        $page = Vendors::pageUrl('vendor_application_page');

        if ($page === '') {
            // Visitors see nothing; admins get a one-line reminder in the same spot.
            if (current_user_can('manage_options')) {
                echo '<p class="sccc-carshow__admin" style="margin:1rem 0;">'
                    .esc_html__('Only admins see this: this show accepts vendors, but there is no published page with the Vendor Sign-up block (or chosen in Theme Settings → Vendor Settings), so the "Vendors wanted" box is hidden.', 'sccc')
                    .'</p>';
            }

            return;
        }

        self::printAssets();

        $new = add_query_arg(['vendor_signup' => 'new', 'vendor_show' => $eventId], $page).'#vendor-signup';
        $returning = add_query_arg(['vendor_signup' => 'returning'], $page).'#vendor-signup';

        echo '<div class="sccc-carshow sccc-carshow--vendors"><div class="sccc-carshow__card sccc-carshow__card--vendors">'
            .'<h3 class="sccc-carshow__title">'.esc_html__('Vendors wanted', 'sccc').'</h3>'
            .'<p>'.esc_html__('Want to sell at this show? Apply as a vendor — no car registration needed.', 'sccc').'</p>'
            .'<p class="sccc-carshow__actions">'
            .'<a class="sccc-carshow__button" href="'.esc_url($new).'">'.esc_html__('Apply as a vendor', 'sccc').'</a> '
            .'<a class="sccc-carshow__link" href="'.esc_url($returning).'">'.esc_html__('Been a vendor with us before?', 'sccc').'</a>'
            .'</p></div></div>';
    }

    public static function renderSeatStatus(int $eventId): void
    {
        self::printAssets();

        echo '<div class="mep-default-sidrbar-price-seat sccc-carshow-count"><div class="setas-info"><div class="total-seats">'
            .'<div>'.esc_html__('Cars registered', 'sccc').'</div>'
            .'<strong>'.esc_html((string) CarShows::registeredCount($eventId)).'</strong>'
            .'</div></div></div>';
    }

    /** Styles + the "Add another car" behaviour, printed once per page. */
    private static function printAssets(): void
    {
        if (self::$assetsPrinted) {
            return;
        }

        self::$assetsPrinted = true;
        \App\Support\Forms\GlassForms::print();
        ?>
        <style>
            .sccc-carshow { display: grid; gap: 1.25rem; margin-block: 1.5rem; }
            .sccc-carshow__card { border: 1px solid var(--color-card-border, rgba(148,163,184,.3)); border-radius: 1rem; padding: 1.25rem 1.25rem 1.5rem; background: var(--color-card-bg, transparent); }
            .sccc-carshow__title { margin: 0 0 .5rem; font-size: 1.25rem; font-weight: 700; }
            .sccc-carshow__facts { display: flex; flex-wrap: wrap; gap: .5rem; list-style: none; margin: 0 0 1rem; padding: 0; }
            .sccc-carshow__facts li { font-size: .85rem; font-weight: 600; padding: .25rem .75rem; border-radius: 999px; border: 1px solid var(--color-card-border, rgba(148,163,184,.4)); }
            .sccc-carshow__admin { font-size: .85rem; opacity: .8; font-style: italic; }
            .sccc-carshow__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; margin: .75rem 0 0; }
            .sccc-car-registration .sccc-car-product--single .gfield_radio { display: none; }
            .sccc-carshow__link { text-decoration: underline; }
            .sccc-car-registration .sccc-car-count.sccc-enhanced { position: absolute !important; left: -9999px !important; }
            .sccc-car-confirmation ul { margin: .5rem 0 1rem 1.25rem; }
        </style>
        <script>
            (function () {
                // Total = price of the chosen registration option × number of cars.
                var filterAdded = false;
                function addTotalFilter() {
                    if (filterAdded || !window.gform || typeof window.gform.addFilter !== 'function') { return; }
                    filterAdded = true;
                    window.gform.addFilter('gform_product_total', function (total, formId) {
                        var form = document.getElementById('gform_' + formId);
                        if (!form || !form.classList.contains('sccc-car-registration')) { return total; }
                        var select = form.querySelector('.sccc-car-count select');
                        var choice = form.querySelector('.sccc-car-product input[type="radio"]:checked');
                        if (!select || !choice) { return total; }
                        var raw = String(choice.value || '').split('|')[1] || '0';
                        var price = typeof window.gformToNumber === 'function' ? window.gformToNumber(raw) : parseFloat(raw.replace(/[^0-9.]/g, ''));
                        var cars = parseInt(select.value || '1', 10) || 1;
                        return (isNaN(price) ? 0 : price) * cars;
                    }, 1);
                }

                function enhance(formId) {
                    addTotalFilter();
                    var form = document.getElementById('gform_' + formId);
                    if (!form || !form.classList.contains('sccc-car-registration')) { return; }

                    var countField = form.querySelector('.sccc-car-count');
                    var select = countField ? countField.querySelector('select') : null;
                    if (!select) { return; }

                    var max = select.options.length;
                    var bar = form.querySelector('.sccc-car-actions');

                    if (!bar) {
                        bar = document.createElement('div');
                        bar.className = 'sccc-car-actions';
                        bar.innerHTML = '<button type="button" class="sccc-car-actions__add">+ <?php echo esc_js(__('Add another car', 'sccc')); ?></button>'
                            + '<button type="button" class="sccc-car-actions__remove"><?php echo esc_js(__('Remove last car', 'sccc')); ?></button>'
                            + '<span class="sccc-car-actions__count" aria-live="polite"></span>';
                        var payment = form.querySelector('.gsection.sccc-car-payment, [id^="field_' + formId + '_200"]');
                        (payment && payment.parentNode ? payment.parentNode : form.querySelector('.gform_fields')).insertBefore(bar, payment || null);
                        countField.classList.add('sccc-enhanced');

                        bar.addEventListener('click', function (event) {
                            var button = event.target.closest('button');
                            if (!button) { return; }
                            var current = parseInt(select.value || '1', 10);
                            var next = button.classList.contains('sccc-car-actions__add') ? Math.min(max, current + 1) : Math.max(1, current - 1);
                            setCount(next, next > current);
                        });
                    }

                    function setCount(n, focusNew) {
                        select.value = String(n);
                        if (window.jQuery) { window.jQuery(select).trigger('change'); } else { select.dispatchEvent(new Event('change', { bubbles: true })); }
                        update();
                        if (typeof window.gformCalculateTotalPrice === 'function') { window.gformCalculateTotalPrice(formId); }
                        if (focusNew) {
                            var section = form.querySelector('[id^="field_' + formId + '_' + (100 + (n - 1) * 10) + '"]');
                            window.setTimeout(function () {
                                var input = section ? section.parentNode.querySelector('#input_' + formId + '_' + (101 + (n - 1) * 10)) : null;
                                if (input) { input.focus(); }
                            }, 150);
                        }
                    }

                    function update() {
                        var n = parseInt(select.value || '1', 10);
                        bar.querySelector('.sccc-car-actions__add').disabled = n >= max;
                        bar.querySelector('.sccc-car-actions__remove').disabled = n <= 1;
                        bar.querySelector('.sccc-car-actions__count').textContent = n === 1 ? '<?php echo esc_js(__('1 car', 'sccc')); ?>' : n + ' <?php echo esc_js(__('cars', 'sccc')); ?>';
                    }

                    // Keep the quantity in step after validation reloads.
                    setCount(parseInt(select.value || '1', 10), false);
                }

                if (window.jQuery) {
                    window.jQuery(document).on('gform_post_render', function (event, formId) { enhance(formId); });
                }
                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('form.sccc-car-registration').forEach(function (form) {
                        enhance(form.id.replace('gform_', ''));
                    });
                });
            })();
        </script>
        <?php
    }
}
