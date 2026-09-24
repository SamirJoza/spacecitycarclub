<?php

/**
 * File path + filename: app/Support/Vendors/VendorPayment.php
 *
 * Purpose:
 * - Wire the Gravity Forms "Vendor Payment" form (Stripe) to Vendor applications.
 *   1) Only render the form for a valid private link
 *      (?vendor_application=ID&vendor_token=TOKEN) on an APPROVED, unpaid
 *      application. Otherwise show a friendly message instead of the form.
 *   2) Pre-fill the hidden application/token fields, the payer email and a
 *      summary, and force the product price to the approved fee.
 *   3) Re-check everything server-side on submit (token, status, amount).
 *   4) When Stripe reports the payment as completed, mark the application Paid,
 *      store the amount / transaction / entry, kill the link and send the
 *      vendor confirmation + staff notice.
 *
 * Why this file exists:
 * - The fee is decided by staff at approval time. The browser must never be
 *   able to change what gets charged, so the price is set from the Vendor
 *   record on every Gravity Forms pass and verified again after payment.
 *
 * How the form is recognised (no hard-coded form IDs):
 * - Form Settings → CSS Class Name contains `sccc-vendor-payment`.
 * - Fields are matched by Admin Field Label (see resources/gravity-forms/).
 */

namespace App\Support\Vendors;

defined('ABSPATH') || exit;

final class VendorPayment
{
    /** Admin Field Labels used by the payment form. */
    public const F_APPLICATION = 'vendor_application_id';

    public const F_TOKEN = 'vendor_token';

    public const F_SUMMARY = 'vendor_payment_summary';

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
     * Request context
     * ---------------------------------------------------------------------- */

    /**
     * Vendor ID + token from the private link (GET) or the posted hidden fields.
     *
     * @param  array<string, mixed>|null  $form
     * @return array{0:int,1:string}
     */
    private static function requestContext(?array $form = null): array
    {
        $id = isset($_GET[Vendors::QUERY_APPLICATION]) ? absint($_GET[Vendors::QUERY_APPLICATION]) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        $token = isset($_GET[Vendors::QUERY_TOKEN]) ? sanitize_text_field(wp_unslash($_GET[Vendors::QUERY_TOKEN])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        if ($form) {
            $idField = Vendors::field($form, self::F_APPLICATION);
            $tokenField = Vendors::field($form, self::F_TOKEN);

            if ($idField && isset($_POST['input_'.$idField->id])) { // phpcs:ignore WordPress.Security.NonceVerification
                $id = absint(wp_unslash($_POST['input_'.$idField->id]));
            }
            if ($tokenField && isset($_POST['input_'.$tokenField->id])) { // phpcs:ignore WordPress.Security.NonceVerification
                $token = sanitize_text_field(wp_unslash($_POST['input_'.$tokenField->id]));
            }
        }

        return [$id, $token];
    }

    /**
     * Why a link cannot be used, or '' when it can.
     */
    private static function problemFor(int $id, string $token): string
    {
        if (! $id || ! Vendors::isVendor($id) || ! Vendors::tokenIsValid($id, $token)) {
            return Vendors::isVendor($id) && Vendors::status($id) === Vendors::STATUS_PAID
                ? __('This vendor fee has already been paid — you\'re all set. Check your email for the confirmation.', 'sccc')
                : __('This payment link is not valid or has expired. Please use the link from your approval email, or contact us.', 'sccc');
        }

        $status = Vendors::status($id);

        if ($status === Vendors::STATUS_PAID) {
            return __('This vendor fee has already been paid — you\'re all set.', 'sccc');
        }

        if ($status !== Vendors::STATUS_APPROVED) {
            return __('This application is not awaiting payment. Please contact us if you think this is a mistake.', 'sccc');
        }

        if (Vendors::feeDue($id) <= 0) {
            return __('There is no fee due for this application. Please contact us.', 'sccc');
        }

        return '';
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

        [$id, $token] = self::requestContext($form);

        if (self::problemFor($id, $token) !== '') {
            return $form;
        }

        $fee = Vendors::feeDue($id);

        foreach ((array) $form['fields'] as $field) {
            $label = (string) ($field->adminLabel ?? '');

            if ($label === self::F_APPLICATION) {
                $field->defaultValue = (string) $id;
            } elseif ($label === self::F_TOKEN) {
                $field->defaultValue = $token;
            } elseif ($label === self::F_EMAIL) {
                $field->defaultValue = Vendors::contactEmail($id);
            } elseif ($label === self::F_PRODUCT) {
                $field->basePrice = self::gfMoney($fee);
                $field->disableQuantity = true;
                $field->label = sprintf(__('Vendor fee — %s', 'sccc'), html_entity_decode(get_the_title(Vendors::eventId($id)), ENT_QUOTES, 'UTF-8'));
            } elseif ($label === self::F_SUMMARY) {
                $field->content = self::summaryHtml($id);
            }
        }

        return $form;
    }

    /**
     * Replace the form with a message when the link is not usable.
     *
     * @param  array<string, mixed>|mixed  $form
     */
    public static function guardFormOutput(string $formString, $form): string
    {
        if (is_admin() || ! Vendors::formHasClass($form, Vendors::PAYMENT_FORM_CLASS)) {
            return $formString;
        }

        // After a successful submission Gravity Forms returns the confirmation; leave it alone.
        if (! empty($GLOBALS['sccc_vendor_payment_submitted'])) {
            return $formString;
        }

        [$id, $token] = self::requestContext(is_array($form) ? $form : null);
        $problem = self::problemFor($id, $token);

        if ($problem === '') {
            return $formString;
        }

        return '<div class="sccc-vendor-payment-message gform_confirmation_message">'.esc_html($problem).'</div>';
    }

    private static function summaryHtml(int $id): string
    {
        return '<div class="sccc-vendor-payment-summary">'
            .'<p><strong>'.esc_html(Vendors::businessName($id)).'</strong></p>'
            .'<p>'.esc_html(Vendors::eventLabel(Vendors::eventId($id))).'</p>'
            .'<p>'.esc_html__('Amount due:', 'sccc').' <strong>'.esc_html(Vendors::money(Vendors::feeDue($id))).'</strong></p>'
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

        [$id, $token] = self::requestContext($form);
        $problem = self::problemFor($id, $token);

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
     * Force the charged price to the approved fee, whatever the browser posted.
     *
     * @param  array<string, mixed>  $productInfo
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    public static function enforcePrice($productInfo, $form, $entry)
    {
        if (! is_array($productInfo) || ! Vendors::formHasClass($form, Vendors::PAYMENT_FORM_CLASS)) {
            return $productInfo;
        }

        $id = absint(Vendors::entryValue($form, (array) $entry, self::F_APPLICATION));
        if (! $id) {
            [$id] = self::requestContext($form);
        }

        $productField = Vendors::field($form, self::F_PRODUCT);

        if (! $id || ! Vendors::isVendor($id) || ! $productField) {
            return $productInfo;
        }

        $fee = Vendors::feeDue($id);

        if ($fee > 0 && isset($productInfo['products'][$productField->id])) {
            $productInfo['products'][$productField->id]['price'] = self::gfMoney($fee);
            $productInfo['products'][$productField->id]['quantity'] = 1;
        }

        return $productInfo;
    }

    /* -------------------------------------------------------------------------
     * Payment completion
     * ---------------------------------------------------------------------- */

    /**
     * Fires when Gravity Forms Stripe marks the payment complete.
     *
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $action
     */
    public static function onPaymentCompleted($entry, $action): void
    {
        if (! is_array($entry) || ! class_exists('GFAPI')) {
            return;
        }

        $form = \GFAPI::get_form((int) rgar($entry, 'form_id'));

        if (! Vendors::formHasClass($form, Vendors::PAYMENT_FORM_CLASS)) {
            return;
        }

        self::markPaid(
            $form,
            $entry,
            (float) rgar((array) $action, 'amount', rgar($entry, 'payment_amount')),
            (string) rgar((array) $action, 'transaction_id', rgar($entry, 'transaction_id'))
        );
    }

    /**
     * Fallback for gateways that finish during submission (entry already "Paid").
     *
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $form
     */
    public static function onAfterSubmission($entry, $form): void
    {
        if (! is_array($entry) || ! Vendors::formHasClass($form, Vendors::PAYMENT_FORM_CLASS)) {
            return;
        }

        if (strtolower((string) rgar($entry, 'payment_status')) !== 'paid') {
            return;
        }

        self::markPaid($form, $entry, (float) rgar($entry, 'payment_amount'), (string) rgar($entry, 'transaction_id'));
    }

    /**
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $entry
     */
    private static function markPaid(array $form, array $entry, float $amount, string $transactionId): void
    {
        $id = absint(Vendors::entryValue($form, $entry, self::F_APPLICATION));
        $entryId = (int) rgar($entry, 'id');

        if (! $id || ! Vendors::isVendor($id)) {
            self::entryNote($entryId, __('Payment received, but no matching vendor application was found. Please reconcile manually.', 'sccc'));

            return;
        }

        // Idempotent: both hooks may fire for the same payment.
        if (Vendors::status($id) === Vendors::STATUS_PAID) {
            return;
        }

        $due = Vendors::feeDue($id);

        if ($due > 0 && $amount + 0.001 < $due) {
            $message = sprintf(
                __('Payment of %1$s is less than the %2$s due for vendor application #%3$d. The application was NOT marked paid.', 'sccc'),
                Vendors::money($amount),
                Vendors::money($due),
                $id
            );
            self::entryNote($entryId, $message);
            Vendors::mail(Vendors::staffEmail(), __('Vendor payment needs review', 'sccc'), '<p>'.esc_html($message).'</p>', [
                ['label' => __('Open application', 'sccc'), 'url' => admin_url('post.php?action=edit&post='.$id)],
            ]);

            return;
        }

        update_post_meta($id, Vendors::FIELD_STATUS, Vendors::STATUS_PAID);
        update_post_meta($id, Vendors::META_PAID_AT, current_time('mysql'));
        update_post_meta($id, Vendors::META_PAID_AMOUNT, round($amount, 2));
        update_post_meta($id, Vendors::META_TRANSACTION_ID, sanitize_text_field($transactionId));
        update_post_meta($id, Vendors::META_PAYMENT_ENTRY_ID, $entryId);
        update_post_meta($id, Vendors::META_PAYMENT_FORM_ID, (int) rgar($form, 'id'));
        delete_post_meta($id, Vendors::META_TOKEN);

        self::entryNote($entryId, sprintf(__('Vendor application #%d marked as paid.', 'sccc'), $id));
        self::sendConfirmations($id, $amount);
    }

    private static function sendConfirmations(int $id, float $amount): void
    {
        $first = (string) get_post_meta($id, Vendors::FIELD_FIRST_NAME, true);
        $show = Vendors::eventLabel(Vendors::eventId($id));
        $showTitle = html_entity_decode(get_the_title(Vendors::eventId($id)), ENT_QUOTES, 'UTF-8');

        Vendors::mail(
            Vendors::contactEmail($id),
            sprintf(__('You\'re confirmed as a vendor for %s', 'sccc'), $showTitle),
            '<p>'.esc_html(sprintf(__('Hi %s,', 'sccc'), $first ?: Vendors::businessName($id))).'</p>'
            .'<p>'.sprintf(
                esc_html__('We received your payment of %1$s. %2$s is confirmed as a vendor for %3$s.', 'sccc'),
                '<strong>'.esc_html(Vendors::money($amount)).'</strong>',
                '<strong>'.esc_html(Vendors::businessName($id)).'</strong>',
                '<strong>'.esc_html($show).'</strong>'
            ).'</p>'
            .'<p>'.esc_html__('We\'ll be in touch with load-in details before the show. See you there!', 'sccc').'</p>'
        );

        Vendors::mail(
            Vendors::staffEmail(),
            sprintf(__('Vendor paid: %1$s — %2$s', 'sccc'), Vendors::businessName($id), $showTitle),
            '<p>'.sprintf(
                esc_html__('%1$s paid %2$s for %3$s.', 'sccc'),
                '<strong>'.esc_html(Vendors::businessName($id)).'</strong>',
                '<strong>'.esc_html(Vendors::money($amount)).'</strong>',
                esc_html($show)
            ).'</p>',
            [['label' => __('Open application', 'sccc'), 'url' => admin_url('post.php?action=edit&post='.$id)]],
            Vendors::contactEmail($id)
        );
    }

    /* -------------------------------------------------------------------------
     * Helpers
     * ---------------------------------------------------------------------- */

    private static function gfMoney(float $amount): string
    {
        return class_exists('GFCommon') ? (string) \GFCommon::to_money($amount) : '$'.number_format($amount, 2);
    }

    private static function entryNote(int $entryId, string $note): void
    {
        if ($entryId && class_exists('GFAPI')) {
            \GFAPI::add_note($entryId, 0, 'SCCC Vendors', $note);
        }
    }
}
