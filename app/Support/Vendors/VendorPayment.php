<?php

/**
 * File path + filename: app/Support/Vendors/VendorPayment.php
 *
 * Purpose:
 * - Wire the Gravity Forms "Vendor Sign-up & Payment" form (Stripe) to Vendors.
 *   The form only works through a personal link
 *   (?vendor=ID&vendor_token=TOKEN) emailed after approval or to a returning
 *   vendor. It shows the vendor's business and the shows they may pay for:
 *     • shows they were approved for (fee locked at approval), and
 *     • for approved vendors, every open club show they aren't in yet —
 *       filtered by the food rule.
 * - The price is always set server-side from Vendors::payableShows().
 * - When Stripe completes the payment, the show row becomes "Active", paid
 *   details are stored, and the vendor + staff get confirmation emails.
 *
 * How the form is recognised:
 * - CSS class `sccc-vendor-payment`; fields by Admin Field Label (F_* below).
 */

namespace App\Support\Vendors;

defined('ABSPATH') || exit;

final class VendorPayment
{
    public const F_SUMMARY = 'vendor_payment_summary';

    public const F_VENDOR = 'vendor_id';

    public const F_TOKEN = 'vendor_token';

    public const F_PRODUCT = 'vendor_fee_product';

    public const F_EMAIL = 'vendor_payment_email';

    public static function register(): void
    {
        foreach (['gform_pre_render', 'gform_pre_validation', 'gform_pre_submission_filter'] as $hook) {
            add_filter($hook, [self::class, 'prepareForm']);
        }

        add_filter('gform_get_form_filter', [self::class, 'guardFormOutput'], 10, 2);
        add_filter('gform_validation', [self::class, 'validate']);
        add_filter('gform_product_info', [self::class, 'enforcePrice'], 10, 3);

        add_action('gform_post_payment_completed', [self::class, 'onPaymentCompleted'], 10, 2);
        add_action('gform_after_submission', [self::class, 'onAfterSubmission'], 20, 2);
    }

    /* -------------------------------------------------------------------------
     * Context
     * ---------------------------------------------------------------------- */

    /**
     * Vendor ID + token from the link (GET) or the posted hidden fields.
     *
     * @return array{0:int,1:string}
     */
    private static function context(?array $form = null): array
    {
        $id = isset($_GET[Vendors::QUERY_VENDOR]) ? absint($_GET[Vendors::QUERY_VENDOR]) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        $token = isset($_GET[Vendors::QUERY_TOKEN]) ? sanitize_text_field(wp_unslash($_GET[Vendors::QUERY_TOKEN])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        if ($form) {
            $posted = absint(Vendors::postedValue($form, self::F_VENDOR));
            $postedToken = sanitize_text_field(Vendors::postedValue($form, self::F_TOKEN));
            $id = $posted ?: $id;
            $token = $postedToken !== '' ? $postedToken : $token;
        }

        return [$id, $token];
    }

    /** Why the link can't be used right now ('' when it can). */
    private static function problemFor(int $id, string $token): string
    {
        if (! $id || ! Vendors::isVendor($id) || ! Vendors::tokenIsValid($id, $token)) {
            return __('This link is not valid or has expired. Use "I\'ve been a vendor with you before" on our vendor page to get a new one.', 'sccc');
        }

        if (in_array(Vendors::status($id), [Vendors::STATUS_REJECTED, Vendors::STATUS_BLOCKED], true)) {
            return __('This vendor account can\'t sign up online. Please contact us.', 'sccc');
        }

        if (Vendors::payableShows($id)) {
            return '';
        }

        if (Vendors::status($id) === Vendors::STATUS_PENDING) {
            return __('Your application is still being reviewed. We\'ll email you once it\'s approved.', 'sccc');
        }

        $foodBlocked = Vendors::foodBlockedShows($id);
        if ($foodBlocked) {
            return Vendors::foodMessage((int) $foodBlocked[0]);
        }

        foreach (Vendors::openShows() as $eventId) {
            if (Vendors::showRowStatus($id, $eventId) === Vendors::SHOW_ACTIVE) {
                return __('You\'re all set — you\'re already confirmed for our upcoming show. See you there!', 'sccc');
            }
        }

        return Vendors::closedMessage();
    }

    /* -------------------------------------------------------------------------
     * Rendering
     * ---------------------------------------------------------------------- */

    /**
     * @param  array<string, mixed>|mixed  $form
     * @return array<string, mixed>|mixed
     */
    public static function prepareForm($form)
    {
        if (! Vendors::formHasClass($form, Vendors::PAYMENT_FORM_CLASS)) {
            return $form;
        }

        [$id, $token] = self::context($form);

        if (self::problemFor($id, $token) !== '') {
            return $form;
        }

        $payable = Vendors::payableShows($id);

        foreach ((array) $form['fields'] as $field) {
            switch ((string) ($field->adminLabel ?? '')) {
                case self::F_VENDOR:
                    $field->defaultValue = (string) $id;
                    break;

                case self::F_TOKEN:
                    $field->defaultValue = $token;
                    break;

                case self::F_EMAIL:
                    $field->defaultValue = Vendors::contactEmail($id);
                    break;

                case self::F_SUMMARY:
                    $field->content = self::summaryHtml($id);
                    break;

                case self::F_PRODUCT:
                    $choices = [];
                    foreach ($payable as $eventId => $fee) {
                        $choices[] = [
                            'text' => Vendors::eventLabel((int) $eventId),
                            'value' => (string) $eventId,
                            'price' => self::gfMoney($fee),
                            'isSelected' => count($payable) === 1,
                        ];
                    }
                    $field->choices = $choices;
                    $field->enablePrice = true;
                    break;
            }
        }

        return $form;
    }

    /**
     * @param  array<string, mixed>|mixed  $form
     */
    public static function guardFormOutput(string $formString, $form): string
    {
        if (is_admin() || ! Vendors::formHasClass($form, Vendors::PAYMENT_FORM_CLASS) || ! empty($GLOBALS['sccc_vendor_payment_submitted'])) {
            return $formString;
        }

        [$id, $token] = self::context(is_array($form) ? $form : null);
        $problem = self::problemFor($id, $token);

        return $problem === ''
            ? $formString
            : '<div class="sccc-vendor-message gform_confirmation_message">'.esc_html($problem).'</div>';
    }

    private static function summaryHtml(int $id): string
    {
        $logo = Vendors::logoId($id);

        return '<div class="sccc-vendor-summary">'
            .($logo ? wp_get_attachment_image($logo, 'thumbnail', false, ['class' => 'sccc-vendor-summary__logo', 'alt' => '']) : '')
            .'<div><p class="sccc-vendor-summary__name"><strong>'.esc_html(Vendors::businessName($id)).'</strong></p>'
            .'<p class="sccc-vendor-summary__contact">'.esc_html(Vendors::contactName($id)).' · '.esc_html(Vendors::contactEmail($id)).'</p></div>'
            .'</div>';
    }

    /* -------------------------------------------------------------------------
     * Server-side checks
     * ---------------------------------------------------------------------- */

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public static function validate(array $result): array
    {
        $form = $result['form'] ?? null;

        if (! Vendors::formHasClass($form, Vendors::PAYMENT_FORM_CLASS)) {
            return $result;
        }

        [$id, $token] = self::context($form);
        $problem = self::problemFor($id, $token);
        $eventId = self::eventFromValue(Vendors::postedValue($form, self::F_PRODUCT));

        if ($problem === '' && ! isset(Vendors::payableShows($id)[$eventId])) {
            $problem = $eventId && ! Vendors::showAllowsType($eventId, Vendors::typeSlug($id))
                ? Vendors::foodMessage($eventId)
                : __('Please choose a show from the list.', 'sccc');
        }

        if ($problem !== '') {
            $result['is_valid'] = false;
            $target = Vendors::field($form, self::F_PRODUCT) ?: Vendors::field($form, self::F_EMAIL);
            if ($target) {
                $target->failed_validation = true;
                $target->validation_message = $problem;
            }
        } else {
            $GLOBALS['sccc_vendor_payment_submitted'] = true;
        }

        $result['form'] = $form;

        return $result;
    }

    /**
     * Force the charged price to the vendor's fee for the chosen show.
     *
     * @param  array<string, mixed>  $productInfo
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    public static function enforcePrice($productInfo, $form, $entry)
    {
        if (! is_array($productInfo) || ! is_array($form) || ! Vendors::formHasClass($form, Vendors::PAYMENT_FORM_CLASS)) {
            return $productInfo;
        }

        $field = Vendors::field($form, self::F_PRODUCT);
        $id = absint(Vendors::entryValue($form, (array) $entry, self::F_VENDOR)) ?: self::context($form)[0];
        $eventId = self::eventFromValue(Vendors::entryValue($form, (array) $entry, self::F_PRODUCT) ?: Vendors::postedValue($form, self::F_PRODUCT));

        if (! $field || ! $id || ! $eventId || ! isset($productInfo['products'][$field->id])) {
            return $productInfo;
        }

        $fee = Vendors::payableShows($id)[$eventId] ?? self::expectedFee($id, $eventId);

        if ($fee > 0) {
            $productInfo['products'][$field->id]['price'] = self::gfMoney($fee);
            $productInfo['products'][$field->id]['quantity'] = 1;
            $productInfo['products'][$field->id]['name'] = sprintf(__('Vendor fee — %s', 'sccc'), Vendors::eventTitle($eventId));
        }

        return $productInfo;
    }

    /* -------------------------------------------------------------------------
     * Payment completion
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

        if (is_array($form) && Vendors::formHasClass($form, Vendors::PAYMENT_FORM_CLASS)) {
            self::markPaid(
                $form,
                $entry,
                (float) rgar((array) $action, 'amount', rgar($entry, 'payment_amount')),
                (string) rgar((array) $action, 'transaction_id', rgar($entry, 'transaction_id'))
            );
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $form
     */
    public static function onAfterSubmission($entry, $form): void
    {
        if (is_array($entry) && is_array($form) && Vendors::formHasClass($form, Vendors::PAYMENT_FORM_CLASS)
            && strtolower((string) rgar($entry, 'payment_status')) === 'paid') {
            self::markPaid($form, $entry, (float) rgar($entry, 'payment_amount'), (string) rgar($entry, 'transaction_id'));
        }
    }

    /**
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $entry
     */
    private static function markPaid(array $form, array $entry, float $amount, string $transactionId): void
    {
        $id = absint(Vendors::entryValue($form, $entry, self::F_VENDOR));
        $eventId = self::eventFromValue(Vendors::entryValue($form, $entry, self::F_PRODUCT));
        $entryId = (int) rgar($entry, 'id');

        if (! $id || ! Vendors::isVendor($id) || ! $eventId) {
            self::note($entryId, __('Payment received, but the vendor or show could not be matched. Please reconcile manually.', 'sccc'));

            return;
        }

        $rows = Vendors::history($id);
        $index = Vendors::historyIndex($rows, $eventId);

        // Idempotent: both payment hooks can fire for one payment.
        if ($index >= 0 && ($rows[$index][Vendors::ROW_STATUS] ?? '') === Vendors::SHOW_ACTIVE) {
            return;
        }

        $expected = self::expectedFee($id, $eventId);

        if ($expected > 0 && $amount + 0.001 < $expected) {
            $message = sprintf(
                __('Payment of $%1$s is less than the $%2$s due for %3$s (vendor #%4$d). The show was NOT marked active.', 'sccc'),
                number_format($amount, 2),
                number_format($expected, 2),
                Vendors::eventTitle($eventId),
                $id
            );
            self::note($entryId, $message);
            Vendors::mail(Vendors::staffEmail(), __('Vendor payment needs review', 'sccc'), '<p>'.esc_html($message).'</p>', [
                ['label' => __('Open vendor', 'sccc'), 'url' => admin_url('post.php?action=edit&post='.$id)],
            ]);

            return;
        }

        $row = [
            Vendors::ROW_EVENT => $eventId,
            Vendors::ROW_STATUS => Vendors::SHOW_ACTIVE,
            Vendors::ROW_FEE => $expected ?: $amount,
            Vendors::ROW_PAID_AMOUNT => round($amount, 2),
            Vendors::ROW_PAID_AT => current_time('Y-m-d H:i'),
            Vendors::ROW_TRANSACTION => sanitize_text_field($transactionId),
            Vendors::ROW_ENTRY => $entryId,
        ];

        if ($index >= 0) {
            $rows[$index] = array_merge($rows[$index], $row);
        } else {
            $rows[] = $row;
        }

        Vendors::saveHistory($id, $rows);

        // Keep the link only while there is still something left to pay for.
        if (! Vendors::payableShows($id)) {
            Vendors::clearToken($id);
        }

        self::note($entryId, sprintf(__('Vendor #%1$d is now active for %2$s.', 'sccc'), $id, Vendors::eventTitle($eventId)));
        self::sendConfirmations($id, $eventId, $amount);
    }

    /** Fee locked on the history row, else the show's current fee. */
    private static function expectedFee(int $id, int $eventId): float
    {
        $rows = Vendors::history($id);
        $index = Vendors::historyIndex($rows, $eventId);

        if ($index >= 0 && is_numeric($rows[$index][Vendors::ROW_FEE] ?? null) && (float) $rows[$index][Vendors::ROW_FEE] > 0) {
            return round((float) $rows[$index][Vendors::ROW_FEE], 2);
        }

        return Vendors::showFee($eventId, (string) get_post_meta($id, Vendors::FIELD_BOOTH_TYPE, true));
    }

    private static function sendConfirmations(int $id, int $eventId, float $amount): void
    {
        $show = Vendors::eventLabel($eventId);

        Vendors::mail(
            Vendors::contactEmail($id),
            sprintf(__('You\'re confirmed as a vendor for %s', 'sccc'), Vendors::eventTitle($eventId)),
            Vendors::greeting($id)
            .'<p>'.sprintf(
                esc_html__('We received your payment of %1$s. %2$s is confirmed as a vendor for %3$s.', 'sccc'),
                '<strong>$'.esc_html(number_format($amount, 2)).'</strong>',
                '<strong>'.esc_html(Vendors::businessName($id)).'</strong>',
                '<strong>'.esc_html($show).'</strong>'
            ).'</p>'
            .'<p>'.esc_html__('We\'ll be in touch with load-in details before the show. See you there!', 'sccc').'</p>'
        );

        Vendors::mail(
            Vendors::staffEmail(),
            sprintf(__('Vendor paid: %1$s — %2$s', 'sccc'), Vendors::businessName($id), Vendors::eventTitle($eventId)),
            '<p>'.sprintf(
                esc_html__('%1$s paid %2$s and is active for %3$s.', 'sccc'),
                '<strong>'.esc_html(Vendors::businessName($id)).'</strong>',
                '<strong>$'.esc_html(number_format($amount, 2)).'</strong>',
                esc_html($show)
            ).'</p>',
            [['label' => __('Open vendor', 'sccc'), 'url' => admin_url('post.php?action=edit&post='.$id)]],
            Vendors::contactEmail($id)
        );
    }

    /* -------------------------------------------------------------------------
     * Helpers
     * ---------------------------------------------------------------------- */

    /** Product select values are stored as "eventId|price". */
    private static function eventFromValue(string $value): int
    {
        return absint(explode('|', $value)[0] ?? '');
    }

    private static function gfMoney(float $amount): string
    {
        return class_exists('GFCommon') ? (string) \GFCommon::to_money($amount) : '$'.number_format($amount, 2);
    }

    private static function note(int $entryId, string $note): void
    {
        if ($entryId && class_exists('GFAPI')) {
            \GFAPI::add_note($entryId, 0, 'SCCC Vendors', $note);
        }
    }
}
