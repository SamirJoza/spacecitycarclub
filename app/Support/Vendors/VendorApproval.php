<?php

/**
 * File path + filename: app/Support/Vendors/VendorApproval.php
 *
 * Purpose:
 * - Run the manual approval workflow when staff change "Application status" on
 *   a Vendor application and click Update:
 *     → Approved : lock in the fee, create the private payment link and email it
 *     → Declined : optionally email the vendor (with the reason, if given)
 *     → Paid     : manual override (e.g. paid cash at the gate) — records the date
 *     → Pending / Declined / Cancelled after Approved : the payment link stops working
 * - "Resend payment email" button (admin-post) from the Application record box.
 *
 * Why this file exists:
 * - Payment must never be requested before a human approves the vendor
 *   (some venues do not allow food vendors, booth space is limited, etc.).
 *
 * Guard rails:
 * - Approval is refused (status reverts) when the fee would be $0 or no payment
 *   page is configured, with an admin notice explaining what to fix.
 */

namespace App\Support\Vendors;

defined('ABSPATH') || exit;

final class VendorApproval
{
    public const RESEND_ACTION = 'sccc_vendor_resend_payment_email';

    private const NOTICE_TRANSIENT = 'sccc_vendor_notice_';

    /** @var array<int, string> Status before ACF saved the post. */
    private static array $previous = [];

    public static function register(): void
    {
        add_action('acf/save_post', [self::class, 'rememberPreviousStatus'], 5);
        add_action('acf/save_post', [self::class, 'handleStatusChange'], 20);
        add_action('admin_post_'.self::RESEND_ACTION, [self::class, 'handleResend']);
        add_action('admin_notices', [self::class, 'renderNotice']);
    }

    /** @param int|string $postId */
    public static function rememberPreviousStatus($postId): void
    {
        if (is_numeric($postId) && Vendors::isVendor((int) $postId)) {
            self::$previous[(int) $postId] = Vendors::status((int) $postId);
        }
    }

    /** @param int|string $postId */
    public static function handleStatusChange($postId): void
    {
        if (! is_numeric($postId) || ! Vendors::isVendor((int) $postId) || wp_is_post_revision((int) $postId)) {
            return;
        }

        $id = (int) $postId;
        $new = Vendors::status($id);
        $old = self::$previous[$id] ?? $new;
        $sendEmail = (bool) get_post_meta($id, Vendors::FIELD_SEND_EMAIL, true);

        if ($new === Vendors::STATUS_APPROVED) {
            self::handleApproved($id, $old, $sendEmail);

            return;
        }

        // Leaving "approved" for anything but "paid" invalidates the payment link.
        if ($old === Vendors::STATUS_APPROVED && $new !== Vendors::STATUS_PAID) {
            delete_post_meta($id, Vendors::META_TOKEN);
        }

        if ($new === Vendors::STATUS_DECLINED && $old !== Vendors::STATUS_DECLINED && $sendEmail) {
            self::notice(self::sendDeclineEmail($id)
                ? __('Vendor declined. The vendor has been emailed.', 'sccc')
                : __('Vendor declined, but the email could not be sent. Check the contact email address.', 'sccc'), 'success');
        }

        if ($new === Vendors::STATUS_PAID && $old !== Vendors::STATUS_PAID) {
            // Manual "paid" (e.g. cash / check). Online payments are recorded by VendorPayment.
            if ((string) get_post_meta($id, Vendors::META_PAID_AT, true) === '') {
                update_post_meta($id, Vendors::META_PAID_AT, current_time('mysql'));
            }
            if ((string) get_post_meta($id, Vendors::META_PAID_AMOUNT, true) === '') {
                update_post_meta($id, Vendors::META_PAID_AMOUNT, Vendors::feeDue($id) ?: Vendors::resolveFee($id));
            }
            if ((string) get_post_meta($id, Vendors::META_TRANSACTION_ID, true) === '') {
                update_post_meta($id, Vendors::META_TRANSACTION_ID, __('Marked paid manually', 'sccc'));
            }
            delete_post_meta($id, Vendors::META_TOKEN);
        }
    }

    private static function handleApproved(int $id, string $old, bool $sendEmail): void
    {
        $fee = Vendors::resolveFee($id);
        $problem = '';

        if ($fee <= 0) {
            $problem = __('Approval not saved: the fee is $0. Set a vendor fee on the show, or enter a fee override, then approve again.', 'sccc');
        } elseif (Vendors::paymentPageUrl() === '') {
            $problem = __('Approval not saved: no vendor payment page is set. Choose one in Theme Settings → Vendor Settings, then approve again.', 'sccc');
        } elseif (! is_email(Vendors::contactEmail($id))) {
            $problem = __('Approval not saved: the vendor has no valid email address for the payment link.', 'sccc');
        }

        if ($problem !== '') {
            update_post_meta($id, Vendors::FIELD_STATUS, $old === Vendors::STATUS_APPROVED ? Vendors::STATUS_PENDING : $old);
            self::notice($problem, 'error');

            return;
        }

        $previousFee = Vendors::feeDue($id);
        update_post_meta($id, Vendors::META_FEE_DUE, $fee);
        Vendors::ensureToken($id);

        if ($old !== Vendors::STATUS_APPROVED) {
            update_post_meta($id, Vendors::META_APPROVED_AT, current_time('mysql'));

            if (! $sendEmail) {
                self::notice(__('Vendor approved. No email was sent — copy the payment link from the Application record box.', 'sccc'), 'success');

                return;
            }

            self::notice(self::sendPaymentEmail($id)
                ? sprintf(__('Vendor approved and emailed a payment link for %s.', 'sccc'), Vendors::money($fee))
                : __('Vendor approved, but the email could not be sent. Copy the payment link from the Application record box.', 'sccc'), 'success');

            return;
        }

        if (abs($previousFee - $fee) > 0.001) {
            self::notice(sprintf(__('Fee updated to %s. Use "Resend payment email" to let the vendor know.', 'sccc'), Vendors::money($fee)), 'warning');
        }
    }

    /* -------------------------------------------------------------------------
     * Emails
     * ---------------------------------------------------------------------- */

    public static function sendPaymentEmail(int $id): bool
    {
        $url = Vendors::paymentUrl($id);

        if ($url === '') {
            return false;
        }

        $first = (string) get_post_meta($id, Vendors::FIELD_FIRST_NAME, true);
        $show = Vendors::eventLabel(Vendors::eventId($id));
        $fee = Vendors::money(Vendors::feeDue($id));
        $extra = function_exists('get_field') ? (string) get_field('vendor_approval_email_note', 'option') : '';

        $body = '<p>'.esc_html(sprintf(__('Hi %s,', 'sccc'), $first ?: Vendors::businessName($id))).'</p>'
            .'<p>'.sprintf(
                /* translators: 1: business name, 2: show */
                esc_html__('Great news — %1$s has been approved as a vendor for %2$s.', 'sccc'),
                '<strong>'.esc_html(Vendors::businessName($id)).'</strong>',
                '<strong>'.esc_html($show).'</strong>'
            ).'</p>'
            .'<p>'.sprintf(esc_html__('The vendor fee is %s. Your spot is confirmed once payment is received.', 'sccc'), '<strong>'.esc_html($fee).'</strong>').'</p>'
            .($extra !== '' ? '<p>'.wp_kses($extra, ['br' => [], 'strong' => [], 'em' => [], 'a' => ['href' => []]]).'</p>' : '')
            .'<p style="color:#64748b;font-size:14px;">'.esc_html__('This payment link is private to your application. Please don\'t share it.', 'sccc').'</p>';

        $sent = Vendors::mail(
            Vendors::contactEmail($id),
            sprintf(__('You\'re approved: %s — complete your vendor payment', 'sccc'), html_entity_decode(get_the_title(Vendors::eventId($id)), ENT_QUOTES, 'UTF-8')),
            $body,
            [['label' => sprintf(__('Pay %s now', 'sccc'), $fee), 'url' => $url]]
        );

        if ($sent) {
            update_post_meta($id, Vendors::META_PAYMENT_EMAILED_AT, current_time('mysql'));
        }

        return $sent;
    }

    public static function sendDeclineEmail(int $id): bool
    {
        $first = (string) get_post_meta($id, Vendors::FIELD_FIRST_NAME, true);
        $reason = trim((string) get_post_meta($id, Vendors::FIELD_DECLINE_REASON, true));
        $show = Vendors::eventLabel(Vendors::eventId($id));

        $body = '<p>'.esc_html(sprintf(__('Hi %s,', 'sccc'), $first ?: Vendors::businessName($id))).'</p>'
            .'<p>'.sprintf(
                esc_html__('Thank you for applying to be a vendor at %s. Unfortunately we\'re not able to accept your application for this show.', 'sccc'),
                '<strong>'.esc_html($show).'</strong>'
            ).'</p>'
            .($reason !== '' ? '<p>'.nl2br(esc_html($reason)).'</p>' : '')
            .'<p>'.esc_html__('We appreciate your interest and hope to work with you at a future event.', 'sccc').'</p>';

        return Vendors::mail(
            Vendors::contactEmail($id),
            sprintf(__('Your vendor application for %s', 'sccc'), html_entity_decode(get_the_title(Vendors::eventId($id)), ENT_QUOTES, 'UTF-8')),
            $body
        );
    }

    /* -------------------------------------------------------------------------
     * Resend button
     * ---------------------------------------------------------------------- */

    public static function handleResend(): void
    {
        $id = isset($_GET['vendor']) ? absint($_GET['vendor']) : 0;

        check_admin_referer(self::RESEND_ACTION.'_'.$id);

        if (! $id || ! Vendors::isVendor($id) || ! current_user_can('edit_post', $id)) {
            wp_die(esc_html__('You are not allowed to do that.', 'sccc'), 403);
        }

        if (Vendors::status($id) !== Vendors::STATUS_APPROVED) {
            self::notice(__('Only approved applications have a payment link.', 'sccc'), 'error');
        } else {
            self::notice(self::sendPaymentEmail($id)
                ? __('Payment email sent again.', 'sccc')
                : __('The payment email could not be sent.', 'sccc'), 'success');
        }

        wp_safe_redirect((string) get_edit_post_link($id, 'raw'));
        exit;
    }

    /* -------------------------------------------------------------------------
     * Admin notices (stored per user, shown after the redirect)
     * ---------------------------------------------------------------------- */

    private static function notice(string $message, string $type = 'success'): void
    {
        set_transient(self::NOTICE_TRANSIENT.get_current_user_id(), ['message' => $message, 'type' => $type], 60);
    }

    public static function renderNotice(): void
    {
        $key = self::NOTICE_TRANSIENT.get_current_user_id();
        $notice = get_transient($key);

        if (! is_array($notice) || empty($notice['message'])) {
            return;
        }

        delete_transient($key);

        $type = in_array($notice['type'] ?? '', ['success', 'warning', 'error', 'info'], true) ? $notice['type'] : 'info';

        printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($type), esc_html((string) $notice['message']));
    }
}
