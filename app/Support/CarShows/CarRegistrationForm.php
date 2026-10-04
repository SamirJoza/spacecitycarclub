<?php

/**
 * File path + filename: app/Support/CarShows/CarRegistrationForm.php
 *
 * Purpose:
 * - The "Car Registration" Gravity Form, built and maintained in code, plus
 *   everything that happens around it:
 *     • owner details once (pre-filled for logged-in users)
 *     • "How many cars" (1–10) with a Car block per car; the page script turns
 *       this into "Add another car" / "Remove" buttons
 *     • registration option = one of the event's MagePeople ticket types on sale
 *       (e.g. Early Bird / Regular); total = cars × that ticket's price, enforced
 *       server-side
 *     • Stripe card payment (Gravity Forms Stripe)
 *     • on payment: one Registered Car record per car, numbered per show in
 *       payment order, owner confirmation email + staff copy
 *
 * How the form finds its show:
 * - The event page renders it with field value `sccc_car_show` = event ID
 *   (see mage-event/layout/registration.php). Every step re-checks that the
 *   event is an open car show; nothing about price comes from the browser.
 *
 * How the form is recognised:
 * - CSS class `sccc-car-registration`; fields by Admin Field Label (F_* below).
 * - Code is the source of truth: bump SCHEMA_VERSION after changing the
 *   definition, or click "Sync car registration form" on Car Show Settings.
 */

namespace App\Support\CarShows;

use App\Support\Vendors\Vendors;

defined('ABSPATH') || exit;

final class CarRegistrationForm
{
    public const SCHEMA_VERSION = 4;

    public const OPTION_FORM_ID = 'sccc_car_registration_form_id';

    public const OPTION_SCHEMA = 'sccc_car_registration_schema';

    public const SYNC_ACTION = 'sccc_car_registration_sync';

    public const QUERY_SHOW = 'sccc_car_show';

    public const SETTINGS_PAGE = 'theme-settings-car-show-settings';

    private const STRIPE_SLUG = 'gravityformsstripe';

    private const META_CREATED = 'sccc_cars_created';

    /* Admin Field Labels. */
    public const F_SHOW = 'car_show_id';

    public const F_FIRST = 'car_owner_first_name';

    public const F_LAST = 'car_owner_last_name';

    public const F_EMAIL = 'car_owner_email';

    public const F_PHONE = 'car_owner_phone';

    public const F_CITY = 'car_owner_city';

    public const F_COUNT = 'car_count';

    public const F_PRODUCT = 'car_fee_product';

    public const F_TOTAL = 'car_total';

    public const F_QTY = 'car_quantity';

    public const F_CARD = 'car_card';

    /** Per-car fields: car_{n}_{part}. */
    private const CAR_PARTS = ['year', 'make', 'model', 'color', 'notes'];

    public static function register(): void
    {
        add_action('admin_init', [self::class, 'maybeInstall'], 20);
        add_action('admin_post_'.self::SYNC_ACTION, [self::class, 'handleSync']);
        add_action('admin_notices', [self::class, 'renderStatus']);

        foreach (['gform_pre_render', 'gform_pre_validation', 'gform_pre_submission_filter'] as $hook) {
            add_filter($hook, [self::class, 'prepareForm']);
        }

        foreach (['first', 'last', 'email', 'phone', 'city'] as $part) {
            add_filter('gform_field_value_sccc_owner_'.$part, static fn ($value) => self::prefill($part, $value));
        }

        add_filter('gform_validation', [self::class, 'validate']);
        add_filter('gform_product_info', [self::class, 'enforcePrice'], 10, 3);
        add_filter('gform_confirmation', [self::class, 'confirmation'], 10, 4);

        // Readable Stripe card field on our dark theme (car + vendor payment forms).
        add_filter('gform_stripe_object', [self::class, 'stripeCardStyle'], 10, 2);

        // Work with either Stripe "Payment Collection Method": with Stripe Checkout
        // (hosted payment page) the on-form card field must not be shown.
        foreach (['gform_pre_render', 'gform_pre_validation', 'gform_pre_submission_filter'] as $hook) {
            add_filter($hook, [self::class, 'matchStripeMode'], 30);
        }

        add_action('gform_post_payment_completed', [self::class, 'onPaymentCompleted'], 10, 2);
        add_action('gform_after_submission', [self::class, 'onAfterSubmission'], 20, 2);
    }

    public static function formId(): int
    {
        return class_exists('GFAPI') ? self::findFormId() : 0;
    }

    /* -------------------------------------------------------------------------
     * Rendering
     * ---------------------------------------------------------------------- */

    /** Event ID from the posted hidden field, or the event page being viewed. */
    private static function eventId(array $form): int
    {
        $posted = absint(Vendors::postedValue($form, self::F_SHOW));

        if ($posted) {
            return $posted;
        }

        $queried = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;

        return $queried && get_post_type($queried) === CarShows::EVENT_POST_TYPE ? $queried : 0;
    }

    /**
     * @param  array<string, mixed>|mixed  $form
     * @return array<string, mixed>|mixed
     */
    public static function prepareForm($form)
    {
        if (! Vendors::formHasClass($form, CarShows::FORM_CLASS) || (is_admin() && ! wp_doing_ajax())) {
            return $form;
        }

        $eventId = self::eventId($form);
        $tickets = $eventId ? CarShows::onSaleTickets($eventId) : [];
        $allFree = $tickets && max(array_column($tickets, 'price')) <= 0;
        $maxCars = max(1, min(CarShows::MAX_CARS_PER_REGISTRATION, $eventId ? CarShows::spotsLeft($eventId) : CarShows::MAX_CARS_PER_REGISTRATION));

        $fields = [];
        foreach ((array) $form['fields'] as $field) {
            $label = (string) ($field->adminLabel ?? '');

            if ($label === self::F_PRODUCT) {
                $choices = [];
                foreach (array_values($tickets) as $i => $ticket) {
                    $text = $ticket['name'];
                    if ($ticket['ends']) {
                        $text .= ' — '.sprintf(__('until %s', 'sccc'), wp_date('M j', $ticket['ends']));
                    }
                    if ($ticket['left'] !== PHP_INT_MAX && $ticket['left'] <= 20) {
                        $text .= ' — '.sprintf(_n('%d spot left', '%d spots left', $ticket['left'], 'sccc'), $ticket['left']);
                    }
                    $choices[] = ['text' => $text, 'value' => $ticket['key'], 'price' => self::gfMoney($ticket['price']), 'isSelected' => $i === 0];
                }
                if ($choices) {
                    $field->choices = $choices;
                }
                if (count($choices) === 1) {
                    // One option: show it as a line of text instead of a lone radio button.
                    $only = array_values($tickets)[0];
                    $field->label = sprintf(__('%1$s — %2$s per car', 'sccc'), $only['name'], CarShows::money($only['price']));
                    $field->cssClass = trim((string) $field->cssClass.' sccc-car-product--single');
                } else {
                    $field->label = __('Registration type (price per car)', 'sccc');
                }
            }

            if ($label === self::F_COUNT) {
                $choices = [];
                foreach (range(1, $maxCars) as $n) {
                    $choices[] = ['text' => (string) $n, 'value' => (string) $n, 'isSelected' => $n === 1, 'price' => ''];
                }
                $field->choices = $choices;
            }

            // Free show: no card needed.
            if ($allFree && (($field->type ?? '') === 'stripe_creditcard' || $label === self::F_TOTAL)) {
                continue;
            }

            $fields[] = $field;
        }

        $form['fields'] = $fields;

        return $form;
    }

    /**
     * Owner details for logged-in users: account → WooCommerce billing → PMPro billing.
     *
     * @param  mixed  $value
     * @return mixed
     */
    private static function prefill(string $part, $value)
    {
        if ((is_string($value) && $value !== '') || ! is_user_logged_in()) {
            return $value;
        }

        $user = wp_get_current_user();
        $id = (int) $user->ID;

        $pick = static function (array $keys) use ($id): string {
            foreach ($keys as $key) {
                $found = trim((string) get_user_meta($id, $key, true));
                if ($found !== '') {
                    return $found;
                }
            }

            return '';
        };

        switch ($part) {
            case 'first':
                return $pick(['first_name', 'billing_first_name', 'pmpro_bfirstname']);
            case 'last':
                return $pick(['last_name', 'billing_last_name', 'pmpro_blastname']);
            case 'email':
                return (string) $user->user_email;
            case 'phone':
                return $pick(['billing_phone', 'pmpro_bphone', 'phone']);
            case 'city':
                return $pick(['billing_city', 'pmpro_bcity', 'city']);
        }

        return $value;
    }

    /* -------------------------------------------------------------------------
     * Validation and price
     * ---------------------------------------------------------------------- */

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public static function validate($result)
    {
        $form = $result['form'] ?? null;

        if (! is_array($form) || ! Vendors::formHasClass($form, CarShows::FORM_CLASS)) {
            return $result;
        }

        $eventId = self::eventId($form);
        $count = (int) Vendors::postedValue($form, self::F_COUNT);
        $message = '';

        if (! $eventId || ! CarShows::isOpen($eventId)) {
            $message = $eventId ? CarShows::closedMessage($eventId) : __('Please register from the show\'s event page.', 'sccc');
        } elseif ($count < 1 || $count > CarShows::MAX_CARS_PER_REGISTRATION) {
            $message = __('Please choose how many cars you are registering.', 'sccc');
        } else {
            $ticket = self::chosenTicket($form, [], $eventId);

            if (! $ticket) {
                $message = __('That registration option is no longer available. Please choose another.', 'sccc');
            } elseif ($count > $ticket['left']) {
                $left = (int) $ticket['left'];
                $message = sprintf(_n('Only %1$d spot is left for %2$s.', 'Only %1$d spots are left for %2$s.', $left, 'sccc'), $left, $ticket['name']);
            }
        }

        // Car years: four digits, not in the future.
        $maxYear = (int) wp_date('Y') + 1;
        foreach ($form['fields'] as $field) {
            if (! preg_match('/^car_(\d+)_year$/', (string) ($field->adminLabel ?? ''), $m) || (int) $m[1] > $count) {
                continue;
            }
            $year = trim((string) rgpost('input_'.$field->id));
            if ($year !== '' && (! preg_match('/^\d{4}$/', $year) || (int) $year < 1886 || (int) $year > $maxYear)) {
                $field->failed_validation = true;
                $field->validation_message = sprintf(__('Enter a 4-digit year between 1886 and %d.', 'sccc'), $maxYear);
                $result['is_valid'] = false;
            }
        }

        if ($message === '') {
            $result['form'] = $form;

            return $result;
        }

        $result['is_valid'] = false;

        foreach ($form['fields'] as $field) {
            if (($field->adminLabel ?? '') === self::F_COUNT) {
                $field->failed_validation = true;
                $field->validation_message = $message;
            }
        }

        $result['form'] = $form;

        return $result;
    }

    /**
     * @param  array<string, mixed>|mixed  $productInfo
     * @param  array<string, mixed>|mixed  $form
     * @param  array<string, mixed>|mixed  $entry
     * @return array<string, mixed>|mixed
     */
    public static function enforcePrice($productInfo, $form, $entry)
    {
        if (! is_array($productInfo) || ! is_array($form) || ! Vendors::formHasClass($form, CarShows::FORM_CLASS)) {
            return $productInfo;
        }

        $field = Vendors::field($form, self::F_PRODUCT);
        $eventId = absint(Vendors::entryValue($form, (array) $entry, self::F_SHOW)) ?: self::eventId($form);
        $count = (int) (Vendors::entryValue($form, (array) $entry, self::F_COUNT) ?: Vendors::postedValue($form, self::F_COUNT));
        $count = max(1, min(CarShows::MAX_CARS_PER_REGISTRATION, $count));

        if (! $field || ! $eventId) {
            return $productInfo;
        }

        $ticket = self::chosenTicket($form, (array) $entry, $eventId);

        if (! $ticket) {
            return $productInfo;
        }

        $productInfo['products'] = [
            $field->id => [
                'name' => sprintf(__('Car registration (%1$s) — %2$s', 'sccc'), $ticket['name'], Vendors::eventTitle($eventId)),
                'price' => self::gfMoney($ticket['price']),
                'quantity' => $count,
                'options' => [],
            ],
        ];

        return $productInfo;
    }

    /**
     * The ticket type picked on the form, if it's on sale right now.
     * Product radio values are posted as "key|price"; only the key is trusted.
     *
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private static function chosenTicket(array $form, array $entry, int $eventId): ?array
    {
        $raw = $entry ? Vendors::entryValue($form, $entry, self::F_PRODUCT) : '';
        $raw = $raw !== '' ? $raw : Vendors::postedValue($form, self::F_PRODUCT);
        $key = sanitize_title(explode('|', $raw)[0]);
        $tickets = CarShows::onSaleTickets($eventId);

        return $tickets[$key] ?? null;
    }

    /**
     * The ticket type stored on an entry, whether or not it's still on sale
     * (payment can complete after an early-bird window closes).
     *
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private static function entryTicket(array $form, array $entry, int $eventId): ?array
    {
        $key = sanitize_title(explode('|', Vendors::entryValue($form, $entry, self::F_PRODUCT))[0]);
        $tickets = CarShows::tickets($eventId);

        return $tickets[$key] ?? null;
    }

    /* -------------------------------------------------------------------------
     * Stripe card field styling (car registration + vendor payment forms)
     * ---------------------------------------------------------------------- */

    /** Our forms that take card payments. */
    private static function isPaymentForm($form): bool
    {
        return Vendors::formHasClass($form, CarShows::FORM_CLASS) || Vendors::formHasClass($form, Vendors::PAYMENT_FORM_CLASS);
    }

    /** True when Gravity Forms Stripe is set to "Stripe Payment Form (Stripe Checkout)". */
    public static function stripeCheckoutMode(): bool
    {
        return function_exists('gf_stripe') && method_exists(gf_stripe(), 'is_stripe_checkout_enabled') && gf_stripe()->is_stripe_checkout_enabled();
    }

    /**
     * Drop the card field when payments go through Stripe Checkout; visitors are
     * then sent to Stripe's secure payment page after "Register & Pay".
     *
     * @param  array<string, mixed>|mixed  $form
     * @return array<string, mixed>|mixed
     */
    public static function matchStripeMode($form)
    {
        if (! self::isPaymentForm($form) || ! self::stripeCheckoutMode()) {
            return $form;
        }

        $form['fields'] = array_values(array_filter(
            (array) $form['fields'],
            static fn ($field): bool => ($field->type ?? '') !== 'stripe_creditcard'
        ));

        return $form;
    }

    /**
     * Colors inside Stripe's card iframe (the page CSS can't reach into it).
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public static function stripeCardStyle($args, $formId)
    {
        if (! is_array($args) || ! class_exists('GFAPI') || ! self::isPaymentForm(\GFAPI::get_form((int) $formId))) {
            return $args;
        }

        $args['cardStyle'] = [
            'base' => [
                'color' => '#e2e8f0',
                'iconColor' => '#cbd5e1',
                'fontSize' => '16px',
                'fontFamily' => 'Inter, system-ui, -apple-system, "Segoe UI", sans-serif',
                '::placeholder' => ['color' => '#94a3b8'],
            ],
            'invalid' => [
                'color' => '#fca5a5',
                'iconColor' => '#fca5a5',
            ],
        ];

        return $args;
    }

    /* -------------------------------------------------------------------------
     * After payment
     * ---------------------------------------------------------------------- */

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $action
     */
    public static function onPaymentCompleted($entry, $action): void
    {
        if (! is_array($entry) || ! class_exists('GFAPI')) {
            return;
        }

        $form = \GFAPI::get_form((int) rgar($entry, 'form_id'));

        if (is_array($form) && Vendors::formHasClass($form, CarShows::FORM_CLASS)) {
            self::createCars(
                $form,
                $entry,
                (float) rgar((array) $action, 'amount', rgar($entry, 'payment_amount')),
                (string) rgar((array) $action, 'transaction_id', rgar($entry, 'transaction_id'))
            );
        }
    }

    /**
     * Paid during submission, or a free show.
     *
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $form
     */
    public static function onAfterSubmission($entry, $form): void
    {
        if (! is_array($entry) || ! is_array($form) || ! Vendors::formHasClass($form, CarShows::FORM_CLASS)) {
            return;
        }

        $eventId = absint(Vendors::entryValue($form, $entry, self::F_SHOW));
        $ticket = $eventId ? self::entryTicket($form, $entry, $eventId) : null;
        $free = $ticket && $ticket['price'] <= 0;

        if ($free || strtolower((string) rgar($entry, 'payment_status')) === 'paid') {
            self::createCars($form, $entry, (float) rgar($entry, 'payment_amount'), (string) rgar($entry, 'transaction_id'));
        }
    }

    /**
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $entry
     */
    private static function createCars(array $form, array $entry, float $amount, string $transactionId): void
    {
        $entryId = (int) rgar($entry, 'id');

        // Idempotent: both payment hooks can fire for one registration.
        if ($entryId && gform_get_meta($entryId, self::META_CREATED)) {
            return;
        }

        $eventId = absint(Vendors::entryValue($form, $entry, self::F_SHOW));
        $count = max(1, min(CarShows::MAX_CARS_PER_REGISTRATION, (int) Vendors::entryValue($form, $entry, self::F_COUNT)));

        if (! $eventId || ! CarShows::isCarShow($eventId)) {
            self::note($entryId, __('Registration received, but the show could not be matched. Please add the cars by hand.', 'sccc'));

            return;
        }

        $ticket = self::entryTicket($form, $entry, $eventId);
        // The order total uses the price enforced at submission (cached product info),
        // so a registration paid just after an early-bird window closes is still valid.
        $expected = class_exists('GFCommon') ? round((float) \GFCommon::get_order_total($form, $entry), 2) : round(($ticket['price'] ?? 0.0) * $count, 2);

        if ($expected > 0 && $amount + 0.001 < $expected) {
            $message = sprintf(
                __('Payment of %1$s is less than the %2$s due for %3$d car(s). The cars were NOT registered — please check this entry.', 'sccc'),
                CarShows::money($amount),
                CarShows::money($expected),
                $count
            );
            self::note($entryId, $message);
            Vendors::mail(CarShows::staffEmail(), __('Car registration needs attention', 'sccc'), '<p>'.esc_html($message).'</p><p>'.esc_html(sprintf(__('Show: %s · Entry #%d', 'sccc'), Vendors::eventTitle($eventId), $entryId)).'</p>');

            return;
        }

        if ($entryId) {
            gform_update_meta($entryId, self::META_CREATED, 'pending');
        }

        $owner = [
            CarShows::META_FIRST => sanitize_text_field(Vendors::entryValue($form, $entry, self::F_FIRST)),
            CarShows::META_LAST => sanitize_text_field(Vendors::entryValue($form, $entry, self::F_LAST)),
            CarShows::META_EMAIL => sanitize_email(Vendors::entryValue($form, $entry, self::F_EMAIL)),
            CarShows::META_PHONE => sanitize_text_field(Vendors::entryValue($form, $entry, self::F_PHONE)),
            CarShows::META_CITY => sanitize_text_field(Vendors::entryValue($form, $entry, self::F_CITY)),
        ];

        $numbers = CarShows::reserveNumbers($eventId, $count);
        $perCar = $count > 0 ? round($amount / $count, 2) : 0.0;
        $userId = (int) rgar($entry, 'created_by');
        $carIds = [];

        foreach (range(1, $count) as $i) {
            $meta = $owner + [
                CarShows::META_SHOW => (string) $eventId,
                CarShows::META_NUMBER => (string) $numbers[$i - 1],
                CarShows::META_YEAR => sanitize_text_field(Vendors::entryValue($form, $entry, self::carLabel($i, 'year'))),
                CarShows::META_MAKE => sanitize_text_field(Vendors::entryValue($form, $entry, self::carLabel($i, 'make'))),
                CarShows::META_MODEL => sanitize_text_field(Vendors::entryValue($form, $entry, self::carLabel($i, 'model'))),
                CarShows::META_COLOR => sanitize_text_field(Vendors::entryValue($form, $entry, self::carLabel($i, 'color'))),
                CarShows::META_NOTES => sanitize_textarea_field(Vendors::entryValue($form, $entry, self::carLabel($i, 'notes'))),
                CarShows::META_USER => (string) $userId,
                CarShows::META_ENTRY => (string) $entryId,
                CarShows::META_AMOUNT => number_format($perCar, 2, '.', ''),
                CarShows::META_TRANSACTION => $transactionId,
                CarShows::META_SOURCE => 'online',
                CarShows::META_TICKET => (string) ($ticket['key'] ?? sanitize_title(explode('|', Vendors::entryValue($form, $entry, self::F_PRODUCT))[0])),
                CarShows::META_TICKET_NAME => (string) ($ticket['name'] ?? ''),
            ];

            $carId = wp_insert_post([
                'post_type' => CarShows::POST_TYPE,
                'post_status' => 'publish',
                'post_title' => '#'.$numbers[$i - 1],
                'post_author' => $userId,
                'meta_input' => $meta,
            ], true);

            if (is_wp_error($carId) || ! $carId) {
                continue;
            }

            wp_update_post(['ID' => $carId, 'post_title' => CarShows::buildTitle((int) $carId)]);
            $carIds[] = (int) $carId;
        }

        if ($entryId) {
            gform_update_meta($entryId, self::META_CREATED, implode(',', $carIds));
            self::note($entryId, sprintf(
                __('Registered %1$d car(s) for %2$s: %3$s', 'sccc'),
                count($carIds),
                Vendors::eventTitle($eventId),
                implode(', ', array_map(static fn (int $id): string => CarShows::buildTitle($id), $carIds))
            ));
        }

        if ($carIds) {
            self::sendEmails($eventId, $carIds, $owner, $amount);
        }
    }

    /**
     * @param  array<int, int>  $carIds
     * @param  array<string, string>  $owner
     */
    private static function sendEmails(int $eventId, array $carIds, array $owner, float $amount): void
    {
        $rows = '';
        foreach ($carIds as $carId) {
            $color = (string) get_post_meta($carId, CarShows::META_COLOR, true);
            $rows .= '<tr>'
                .'<td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:700;font-size:20px;">#'.esc_html((string) get_post_meta($carId, CarShows::META_NUMBER, true)).'</td>'
                .'<td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;">'.esc_html(CarShows::carLabel($carId))
                .($color !== '' ? ' <span style="color:#64748b;">('.esc_html($color).')</span>' : '')
                .'</td></tr>';
        }

        $table = '<table style="width:100%;border-collapse:collapse;margin:16px 0;">'
            .'<tr><th style="text-align:left;padding:8px 12px;background:#f1f5f9;">'.esc_html__('Car #', 'sccc').'</th><th style="text-align:left;padding:8px 12px;background:#f1f5f9;">'.esc_html__('Car', 'sccc').'</th></tr>'
            .$rows.'</table>';

        $show = Vendors::eventLabel($eventId);
        $note = CarShows::option('car_show_email_note');
        $first = $owner[CarShows::META_FIRST] ?? '';

        $body = '<p>'.esc_html(sprintf(__('Hi %s,', 'sccc'), $first !== '' ? $first : __('there', 'sccc'))).'</p>'
            .'<p>'.esc_html(sprintf(_n('You\'re registered for %s! Here is your car number:', 'You\'re registered for %s! Here are your car numbers:', count($carIds), 'sccc'), $show)).'</p>'
            .$table
            .($amount > 0 ? '<p>'.esc_html(sprintf(__('Paid: %s', 'sccc'), CarShows::money($amount))).'</p>' : '')
            .($note !== '' ? '<p>'.wp_kses_post(nl2br($note)).'</p>' : '')
            .'<p>'.esc_html__('Keep this email — your number is how we find your car on show day.', 'sccc').'</p>';

        $subject = sprintf(__('You\'re registered: %s', 'sccc'), Vendors::eventTitle($eventId));
        $link = [['label' => __('View show details', 'sccc'), 'url' => (string) get_permalink($eventId)]];

        Vendors::mail((string) ($owner[CarShows::META_EMAIL] ?? ''), $subject, $body, $link, CarShows::staffEmail());

        $staffBody = '<p>'.esc_html(sprintf(
            __('%1$s registered %2$d car(s) for %3$s.', 'sccc'),
            trim(($owner[CarShows::META_FIRST] ?? '').' '.($owner[CarShows::META_LAST] ?? '')),
            count($carIds),
            $show
        )).'</p>'
            .$table
            .'<p>'.esc_html(sprintf(__('Email: %1$s · Phone: %2$s · City: %3$s', 'sccc'), $owner[CarShows::META_EMAIL] ?? '', $owner[CarShows::META_PHONE] ?? '', $owner[CarShows::META_CITY] ?? '')).'</p>'
            .($amount > 0 ? '<p>'.esc_html(sprintf(__('Paid: %s', 'sccc'), CarShows::money($amount))).'</p>' : '')
            .'<p>'.esc_html(sprintf(__('Cars registered for this show so far: %d', 'sccc'), CarShows::registeredCount($eventId))).'</p>';

        Vendors::mail(
            CarShows::staffEmail(),
            sprintf(__('New car registration: %s', 'sccc'), Vendors::eventTitle($eventId)),
            $staffBody,
            [['label' => __('Open registered cars', 'sccc'), 'url' => admin_url('edit.php?post_type='.CarShows::POST_TYPE.'&car_show='.$eventId)]],
            (string) ($owner[CarShows::META_EMAIL] ?? '')
        );
    }

    /**
     * Show the car numbers right away when they're already assigned.
     *
     * @param  string|array<string, mixed>  $confirmation
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $entry
     * @return string|array<string, mixed>
     */
    public static function confirmation($confirmation, $form, $entry, $ajax)
    {
        if (! is_array($form) || ! Vendors::formHasClass($form, CarShows::FORM_CLASS) || ! is_string($confirmation)) {
            return $confirmation;
        }

        $created = (string) gform_get_meta((int) rgar((array) $entry, 'id'), self::META_CREATED);
        $ids = array_filter(array_map('intval', explode(',', $created)));

        if (! $ids) {
            return $confirmation;
        }

        $items = '';
        foreach ($ids as $id) {
            $items .= '<li><strong>#'.esc_html((string) get_post_meta($id, CarShows::META_NUMBER, true)).'</strong> — '.esc_html(CarShows::carLabel($id)).'</li>';
        }

        return '<div class="sccc-car-confirmation"><p><strong>'.esc_html__('You\'re registered!', 'sccc').'</strong> '
            .esc_html(_n('Your car number:', 'Your car numbers:', count($ids), 'sccc')).'</p><ul>'.$items.'</ul><p>'
            .esc_html__('A confirmation email is on its way.', 'sccc').'</p></div>';
    }

    private static function note(int $entryId, string $message): void
    {
        if ($entryId && class_exists('GFAPI')) {
            \GFAPI::add_note($entryId, 0, 'Car Registration', $message);
        }
    }

    private static function gfMoney(float $amount): string
    {
        return '$'.number_format($amount, 2, '.', ',');
    }

    public static function carLabel(int $n, string $part): string
    {
        return 'car_'.$n.'_'.$part;
    }

    /* -------------------------------------------------------------------------
     * Install / sync
     * ---------------------------------------------------------------------- */

    public static function maybeInstall(): void
    {
        if (! class_exists('GFAPI') || wp_doing_ajax() || ! current_user_can('manage_options')) {
            return;
        }

        $force = (int) get_option(self::OPTION_SCHEMA, 0) < self::SCHEMA_VERSION;
        self::sync($force);

        if ($force) {
            update_option(self::OPTION_SCHEMA, self::SCHEMA_VERSION, false);
        }
    }

    public static function sync(bool $force = false): int
    {
        $formId = self::findFormId();
        $definition = self::definition();

        if (! $formId) {
            $result = \GFAPI::add_form($definition);
            if (is_wp_error($result) || ! $result) {
                return 0;
            }
            $formId = (int) $result;
            update_option(self::OPTION_FORM_ID, $formId, false);
        } elseif ($force) {
            $existing = \GFAPI::get_form($formId);
            $definition['id'] = $formId;
            $definition['is_active'] = is_array($existing) ? ($existing['is_active'] ?? '1') : '1';
            \GFAPI::update_form($definition, $formId);
        }

        self::ensureStripeField($formId);
        self::ensureStripeFeed($formId);

        return $formId;
    }

    private static function findFormId(): int
    {
        $stored = (int) get_option(self::OPTION_FORM_ID, 0);

        if ($stored) {
            $form = \GFAPI::get_form($stored);
            if (is_array($form) && empty($form['is_trash']) && Vendors::formHasClass($form, CarShows::FORM_CLASS)) {
                return $stored;
            }
        }

        foreach ([true, false] as $active) {
            foreach ((array) \GFAPI::get_forms($active, false) as $form) {
                if (Vendors::formHasClass($form, CarShows::FORM_CLASS)) {
                    update_option(self::OPTION_FORM_ID, (int) $form['id'], false);

                    return (int) $form['id'];
                }
            }
        }

        return 0;
    }

    private static function ensureStripeField(int $formId): void
    {
        if (! class_exists('GFStripe')) {
            return;
        }

        $form = \GFAPI::get_form($formId);
        if (! is_array($form)) {
            return;
        }

        foreach ((array) $form['fields'] as $field) {
            if (($field->type ?? '') === 'stripe_creditcard') {
                return;
            }
        }

        $form['fields'][] = \GF_Fields::create(self::field(203, 'stripe_creditcard', 'Card details', self::F_CARD, [
            'formId' => $formId,
            'isRequired' => true,
            'inputs' => [
                ['id' => '203.1', 'label' => 'Card Details', 'name' => ''],
                ['id' => '203.4', 'label' => 'Card Type', 'name' => ''],
                ['id' => '203.5', 'label' => 'Cardholder Name', 'name' => ''],
            ],
        ]));

        \GFAPI::update_form($form, $formId);
    }

    private static function ensureStripeFeed(int $formId): void
    {
        if (! class_exists('GFStripe')) {
            return;
        }

        $feeds = \GFAPI::get_feeds(null, $formId, self::STRIPE_SLUG, null);
        if (is_array($feeds) && $feeds) {
            return;
        }

        \GFAPI::add_feed($formId, [
            'feedName' => 'Car registration',
            'transactionType' => 'product',
            'paymentAmount' => 'form_total',
            'billingInformation_email' => '6',
            'feed_condition_conditional_logic' => '0',
        ], self::STRIPE_SLUG);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function field(int $id, string $type, string $label, string $adminLabel, array $extra = []): array
    {
        return array_merge([
            'id' => $id,
            'type' => $type,
            'label' => $label,
            'adminLabel' => $adminLabel,
            'isRequired' => false,
            'size' => 'large',
            'visibility' => 'visible',
            'description' => '',
            'cssClass' => '',
            'layoutGridColumnSpan' => 12,
        ], $extra);
    }

    /**
     * The form definition. Field IDs are fixed so entries stay readable after a sync.
     *
     * @return array<string, mixed>
     */
    public static function definition(): array
    {
        $fields = [
            self::field(2, 'hidden', 'Show', self::F_SHOW, ['allowsPrepopulate' => true, 'inputName' => self::QUERY_SHOW, 'size' => 'medium']),
            self::field(3, 'section', 'Owner', 'car_owner_section', ['description' => 'We\'ll use this to send your confirmation and car numbers.']),
            self::field(4, 'text', 'First name', self::F_FIRST, ['isRequired' => true, 'allowsPrepopulate' => true, 'inputName' => 'sccc_owner_first', 'layoutGridColumnSpan' => 6, 'autocompleteAttribute' => 'given-name']),
            self::field(5, 'text', 'Last name', self::F_LAST, ['isRequired' => true, 'allowsPrepopulate' => true, 'inputName' => 'sccc_owner_last', 'layoutGridColumnSpan' => 6, 'autocompleteAttribute' => 'family-name']),
            self::field(6, 'email', 'Email', self::F_EMAIL, ['isRequired' => true, 'allowsPrepopulate' => true, 'inputName' => 'sccc_owner_email', 'layoutGridColumnSpan' => 6]),
            self::field(7, 'phone', 'Phone', self::F_PHONE, ['isRequired' => true, 'phoneFormat' => 'standard', 'allowsPrepopulate' => true, 'inputName' => 'sccc_owner_phone', 'layoutGridColumnSpan' => 6]),
            self::field(8, 'text', 'City', self::F_CITY, ['isRequired' => true, 'allowsPrepopulate' => true, 'inputName' => 'sccc_owner_city', 'layoutGridColumnSpan' => 6, 'autocompleteAttribute' => 'address-level2']),
            self::field(9, 'select', 'How many cars are you registering?', self::F_COUNT, [
                'isRequired' => true,
                'cssClass' => 'sccc-car-count',
                'layoutGridColumnSpan' => 6,
                'choices' => array_map(static fn (int $n): array => ['text' => (string) $n, 'value' => (string) $n, 'isSelected' => $n === 1, 'price' => ''], range(1, CarShows::MAX_CARS_PER_REGISTRATION)),
            ]),
        ];

        foreach (range(1, CarShows::MAX_CARS_PER_REGISTRATION) as $n) {
            $base = 100 + ($n - 1) * 10;

            $fields[] = self::field($base, 'section', 'Car '.$n, 'car_'.$n.'_section', [
                'cssClass' => 'sccc-car-section',
                'conditionalLogic' => $n === 1 ? '' : [
                    'actionType' => 'show',
                    'logicType' => 'all',
                    'rules' => [['fieldId' => '9', 'operator' => '>', 'value' => (string) ($n - 1)]],
                ],
            ]);
            $fields[] = self::field($base + 1, 'text', 'Year', self::carLabel($n, 'year'), ['isRequired' => true, 'layoutGridColumnSpan' => 3, 'placeholder' => 'e.g. 1969', 'autocompleteAttribute' => 'off']);
            $fields[] = self::field($base + 2, 'text', 'Make', self::carLabel($n, 'make'), ['isRequired' => true, 'layoutGridColumnSpan' => 4, 'placeholder' => 'e.g. Chevrolet']);
            $fields[] = self::field($base + 3, 'text', 'Model', self::carLabel($n, 'model'), ['isRequired' => true, 'layoutGridColumnSpan' => 5, 'placeholder' => 'e.g. Camaro SS']);
            $fields[] = self::field($base + 4, 'text', 'Color', self::carLabel($n, 'color'), ['layoutGridColumnSpan' => 4]);
            $fields[] = self::field($base + 5, 'textarea', 'Anything we should know? (optional)', self::carLabel($n, 'notes'), ['layoutGridColumnSpan' => 8, 'size' => 'small']);
        }

        $fields[] = self::field(200, 'section', 'Payment', 'car_payment_section');
        $fields[] = self::field(201, 'product', 'Registration (price per car)', self::F_PRODUCT, [
            'inputType' => 'radio',
            'enablePrice' => true,
            'isRequired' => true,
            'cssClass' => 'sccc-car-product',
            'choices' => [['text' => 'Ticket types load from the event', 'value' => 'none', 'price' => '$0.00', 'isSelected' => true]],
        ]);
        $fields[] = self::field(202, 'total', 'Total', self::F_TOTAL, ['cssClass' => 'sccc-car-total']);

        if (class_exists('GFStripe')) {
            $fields[] = self::field(203, 'stripe_creditcard', 'Card details', self::F_CARD, [
                'isRequired' => true,
                'inputs' => [
                    ['id' => '203.1', 'label' => 'Card Details', 'name' => ''],
                    ['id' => '203.4', 'label' => 'Card Type', 'name' => ''],
                    ['id' => '203.5', 'label' => 'Cardholder Name', 'name' => ''],
                ],
            ]);
        }

        return [
            'title' => 'Car Registration',
            'description' => '',
            'cssClass' => CarShows::FORM_CLASS,
            'labelPlacement' => 'top_label',
            'descriptionPlacement' => 'below',
            'subLabelPlacement' => 'below',
            'requiredIndicator' => 'asterisk',
            'markupVersion' => 2,
            'enableHoneypot' => true,
            'enableAnimation' => false,
            'is_active' => '1',
            'button' => ['type' => 'text', 'text' => 'Register & pay', 'imageUrl' => ''],
            'notifications' => [],
            'fields' => $fields,
            'confirmations' => [
                'sccc0carreg' => [
                    'id' => 'sccc0carreg',
                    'name' => 'Default Confirmation',
                    'isDefault' => true,
                    'type' => 'message',
                    'message' => '<strong>You\'re registered — thank you!</strong> Your confirmation email with your car number(s) is on its way.',
                    'url' => '',
                    'pageId' => '',
                    'queryString' => '',
                    'disableAutoformat' => false,
                    'conditionalLogic' => [],
                ],
            ],
        ];
    }

    /* -------------------------------------------------------------------------
     * Admin: status + sync button (Theme Settings → Car Show Settings)
     * ---------------------------------------------------------------------- */

    public static function syncUrl(): string
    {
        return wp_nonce_url(admin_url('admin-post.php?action='.self::SYNC_ACTION), self::SYNC_ACTION);
    }

    public static function handleSync(): void
    {
        check_admin_referer(self::SYNC_ACTION);

        if (! current_user_can('manage_options') || ! class_exists('GFAPI')) {
            wp_die(esc_html__('You are not allowed to do that.', 'sccc'), 403);
        }

        self::sync(true);
        update_option(self::OPTION_SCHEMA, self::SCHEMA_VERSION, false);

        wp_safe_redirect(add_query_arg('sccc_car_form_synced', '1', wp_get_referer() ?: admin_url('admin.php?page='.self::SETTINGS_PAGE)));
        exit;
    }

    public static function renderStatus(): void
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        if ($page !== self::SETTINGS_PAGE || ! current_user_can('manage_options')) {
            return;
        }

        if (! class_exists('GFAPI')) {
            echo '<div class="notice notice-error"><p>'.esc_html__('Gravity Forms is not active, so car registration cannot run.', 'sccc').'</p></div>';

            return;
        }

        $formId = self::findFormId();
        $feeds = $formId && class_exists('GFStripe') ? \GFAPI::get_feeds(null, $formId, self::STRIPE_SLUG, null) : [];
        $shows = CarShows::carShowEventIds();

        $line = static function (string $label, bool $ok, string $detail = '', string $url = ''): string {
            $icon = $ok ? '<span style="color:#15803d;">✔</span>' : '<span style="color:#b91c1c;">✖</span>';
            $text = esc_html($label).($detail !== '' ? ' — '.esc_html($detail) : '');

            return '<li>'.$icon.' '.($url !== '' ? '<a href="'.esc_url($url).'">'.$text.'</a>' : $text).'</li>';
        };

        $items = $line(__('Car Registration form', 'sccc'), $formId > 0, $formId ? '#'.$formId : '', $formId ? admin_url('admin.php?page=gf_edit_forms&id='.$formId) : '');
        $items .= $line(
            __('Stripe payment feed', 'sccc'),
            is_array($feeds) && (bool) $feeds,
            class_exists('GFStripe') ? '' : __('install/activate Gravity Forms Stripe', 'sccc'),
            $formId ? admin_url('admin.php?page=gf_edit_forms&view=settings&subview='.self::STRIPE_SLUG.'&id='.$formId) : ''
        );
        if (class_exists('GFStripe')) {
            $items .= $line(
                __('Stripe payment method', 'sccc'),
                true,
                self::stripeCheckoutMode()
                    ? __('Stripe Checkout — visitors pay on Stripe\'s secure page after "Register & Pay"', 'sccc')
                    : __('Stripe Field — card entered on the form', 'sccc'),
                admin_url('admin.php?page=gf_settings&subview=gravityformsstripe')
            );
        }
        $items .= $line(
            __('Car shows found', 'sccc'),
            (bool) $shows,
            $shows ? implode(', ', array_map([Vendors::class, 'eventTitle'], array_slice($shows, 0, 3))) : sprintf(__('set Category "%1$s" and Organizer "%2$s" on the event', 'sccc'), CarShows::categoryNames()[0] ?? 'Car Show', CarShows::organizerNames()[0] ?? 'Space City Car Club')
        );

        $synced = isset($_GET['sccc_car_form_synced']) ? '<p><strong>'.esc_html__('Form synced from code.', 'sccc').'</strong></p>' : ''; // phpcs:ignore WordPress.Security.NonceVerification

        echo '<div class="notice notice-info"><p><strong>'.esc_html__('Car registration status', 'sccc').'</strong></p>'.$synced
            .'<ul style="margin-left:1em;">'.$items.'</ul>'
            .'<p><a class="button" href="'.esc_url(self::syncUrl()).'">'.esc_html__('Sync car registration form from code', 'sccc').'</a></p></div>';
    }
}
