<?php

/**
 * File path + filename: app/Support/Vendors/VendorApproval.php
 *
 * Purpose:
 * - Manual review workflow, triggered when staff change "Vendor status" and
 *   click Update on a Vendor:
 *     → Approved : pending show rows become "awaiting payment" with the fee
 *                  locked in; the vendor is emailed a private sign-up/payment
 *                  link. From now on they can sign up for future club shows
 *                  themselves (returning vendor flow).
 *     → Rejected : a reason is required; pending show rows are declined and
 *                  the vendor is emailed WHY. The record can be deleted later.
 *     → Do not re-invite : pending rows declined, link disabled, no email.
 * - Show rows set to "Active" by hand (cash / check) get their paid fields
 *   filled in.
 * - "Email sign-up / payment link" button in the Vendor record box.
 *
 * Why: payment is never requested before a person approves the vendor.
 */

namespace App\Support\Vendors;

defined('ABSPATH') || exit;

final class VendorApproval
{
    public const SEND_LINK_ACTION = 'sccc_vendor_send_link';

    private const NOTICE_TRANSIENT = 'sccc_vendor_notice_';

    /** @var array<int, string> */
    private static array $previous = [];

    public static function register(): void
    {
        add_action('acf/save_post', [self::class, 'rememberPreviousStatus'], 5);
        add_action('acf/save_post', [self::class, 'handleSave'], 20);
        add_filter('acf/validate_value/key=field_'.Vendors::FIELD_REJECT_REASON, [self::class, 'requireRejectReason'], 10, 4);
        add_action('admin_post_'.self::SEND_LINK_ACTION, [self::class, 'handleSendLink']);
        add_action('admin_notices', [self::class, 'renderNotice']);
    }

    /** @param int|string $postId */
    public static function rememberPreviousStatus($postId): void
    {
        if (is_numeric($postId) && Vendors::isVendor((int) $postId)) {
            self::$previous[(int) $postId] = Vendors::status((int) $postId);
        }
    }

    /**
     * A rejection must say why — the reason is emailed to the vendor.
     *
     * @param  bool|string  $valid
     * @return bool|string
     */
    public static function requireRejectReason($valid, $value, $field, $input)
    {
        $status = $_POST['acf']['field_'.Vendors::FIELD_STATUS] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification -- ACF verifies its own nonce.

        if ($valid === true && $status === Vendors::STATUS_REJECTED && trim((string) $value) === '') {
            return __('Please enter the reason for the rejection — it is included in the email to the vendor.', 'sccc');
        }

        return $valid;
    }

    /** @param int|string $postId */
    public static function handleSave($postId): void
    {
        if (! is_numeric($postId) || ! Vendors::isVendor((int) $postId) || wp_is_post_revision((int) $postId)) {
            return;
        }

        $id = (int) $postId;
        $new = Vendors::status($id);
        $old = self::$previous[$id] ?? $new;
        $sendEmail = (bool) get_post_meta($id, Vendors::FIELD_SEND_EMAIL, true);

        self::fillManualPayments($id);

        if ($new === $old) {
            return;
        }

        if ($new === Vendors::STATUS_APPROVED) {
            self::approve($id, $old, $sendEmail);
        } elseif ($new === Vendors::STATUS_REJECTED) {
            self::closePendingRows($id);
            Vendors::clearToken($id);
            if ($sendEmail) {
                self::notice(self::sendRejectionEmail($id)
                    ? __('Vendor rejected. The vendor has been emailed the reason.', 'sccc')
                    : __('Vendor rejected, but the email could not be sent. Check the contact email address.', 'sccc'), 'warning');
            }
        } elseif ($new === Vendors::STATUS_BLOCKED) {
            self::closePendingRows($id);
            Vendors::clearToken($id);
            self::notice(__('Vendor marked "Do not re-invite". They can no longer sign up online.', 'sccc'), 'info');
        }
    }

    private static function approve(int $id, string $old, bool $sendEmail): void
    {
        if (Vendors::pageUrl('vendor_payment_page') === '') {
            update_post_meta($id, Vendors::FIELD_STATUS, $old);
            self::notice(__('Approval not saved: no vendor sign-up page is set. Choose it in Theme Settings → Vendor Settings, then approve again.', 'sccc'), 'error');

            return;
        }

        $rows = Vendors::history($id);
        $booth = (string) get_post_meta($id, Vendors::FIELD_BOOTH_TYPE, true);
        $type = Vendors::typeSlug($id);
        $warnings = [];

        foreach ($rows as $index => $row) {
            if (($row[Vendors::ROW_STATUS] ?? '') !== Vendors::SHOW_PENDING) {
                continue;
            }

            $eventId = (int) ($row[Vendors::ROW_EVENT] ?? 0);

            if (! Vendors::showAllowsType($eventId, $type)) {
                $rows[$index][Vendors::ROW_STATUS] = Vendors::SHOW_DECLINED;
                $rows[$index][Vendors::ROW_NOTES] = __('No food vendors at this show', 'sccc');
                continue;
            }

            $fee = is_numeric($row[Vendors::ROW_FEE] ?? null) && (float) $row[Vendors::ROW_FEE] > 0
                ? round((float) $row[Vendors::ROW_FEE], 2)
                : Vendors::showFee($eventId, $booth);

            if ($fee <= 0) {
                $warnings[] = sprintf(__('%s has no vendor fee set — that show stays pending until a fee is set.', 'sccc'), Vendors::eventTitle($eventId));
                continue;
            }

            $rows[$index][Vendors::ROW_STATUS] = Vendors::SHOW_AWAITING;
            $rows[$index][Vendors::ROW_FEE] = $fee;
        }

        Vendors::saveHistory($id, $rows);
        update_post_meta($id, Vendors::META_APPROVED_AT, current_time('mysql'));

        $payable = Vendors::payableShows($id);
        $message = __('Vendor approved.', 'sccc');

        if ($sendEmail) {
            $sent = self::sendApprovalEmail($id, $payable);
            $message .= ' '.($sent ? __('The vendor has been emailed.', 'sccc') : __('The email could not be sent — use "Email sign-up / payment link".', 'sccc'));
        }

        if ($warnings) {
            $message .= ' '.implode(' ', $warnings);
        }

        self::notice($message, $warnings ? 'warning' : 'success');
    }

    private static function closePendingRows(int $id): void
    {
        $rows = Vendors::history($id);
        $changed = false;

        foreach ($rows as $index => $row) {
            if (in_array($row[Vendors::ROW_STATUS] ?? '', [Vendors::SHOW_PENDING, Vendors::SHOW_AWAITING], true)) {
                $rows[$index][Vendors::ROW_STATUS] = Vendors::SHOW_DECLINED;
                $changed = true;
            }
        }

        if ($changed) {
            Vendors::saveHistory($id, $rows);
        }
    }

    /** Rows switched to Active by staff (cash / check) get paid details filled in. */
    private static function fillManualPayments(int $id): void
    {
        $rows = Vendors::history($id);
        $changed = false;

        foreach ($rows as $index => $row) {
            if (($row[Vendors::ROW_STATUS] ?? '') === Vendors::SHOW_ACTIVE && trim((string) ($row[Vendors::ROW_PAID_AT] ?? '')) === '') {
                $rows[$index][Vendors::ROW_PAID_AT] = current_time('Y-m-d H:i');
                $rows[$index][Vendors::ROW_PAID_AMOUNT] = ($row[Vendors::ROW_PAID_AMOUNT] ?? '') ?: ($row[Vendors::ROW_FEE] ?? '');
                $rows[$index][Vendors::ROW_TRANSACTION] = ($row[Vendors::ROW_TRANSACTION] ?? '') ?: __('Marked paid manually', 'sccc');
                $changed = true;
            }
        }

        if ($changed) {
            Vendors::saveHistory($id, $rows);
        }
    }

    /* -------------------------------------------------------------------------
     * Emails
     * ---------------------------------------------------------------------- */

    /**
     * @param  array<int, float>  $payable
     */
    public static function sendApprovalEmail(int $id, array $payable): bool
    {
        $extra = Vendors::option('vendor_approval_email_note');
        $body = Vendors::greeting($id)
            .'<p>'.sprintf(esc_html__('Great news — %s has been approved as a Space City Car Club vendor!', 'sccc'), '<strong>'.esc_html(Vendors::businessName($id)).'</strong>').'</p>';
        $buttons = [];

        if ($payable) {
            $url = Vendors::signupUrl($id);
            $body .= '<p>'.esc_html__('To confirm your spot, please pay the vendor fee:', 'sccc').'</p><ul>';
            foreach ($payable as $eventId => $fee) {
                $body .= '<li>'.esc_html(Vendors::eventLabel((int) $eventId)).' — <strong>$'.esc_html(number_format($fee, 2)).'</strong></li>';
            }
            $body .= '</ul><p>'.esc_html__('Your spot is confirmed once payment is received.', 'sccc').'</p>';
            $buttons[] = ['label' => __('Pay vendor fee', 'sccc'), 'url' => $url];
            update_post_meta($id, Vendors::META_LINK_EMAILED_AT, current_time('mysql'));
        } else {
            $body .= '<p>'.esc_html__('We\'ll email you when vendor sign-up opens for our next show — signing up again only takes a minute.', 'sccc').'</p>';
        }

        if ($extra !== '') {
            $body .= '<p>'.wp_kses($extra, ['br' => [], 'strong' => [], 'em' => [], 'a' => ['href' => []]]).'</p>';
        }

        if ($buttons) {
            $body .= '<p style="color:#64748b;font-size:14px;">'.esc_html__('This payment link is personal to your business and expires in 30 days. Please don\'t share it.', 'sccc').'</p>';
        }

        return Vendors::mail(Vendors::contactEmail($id), __('You\'re approved as a vendor!', 'sccc'), $body, $buttons);
    }

    public static function sendRejectionEmail(int $id): bool
    {
        $reason = trim((string) get_post_meta($id, Vendors::FIELD_REJECT_REASON, true));

        return Vendors::mail(
            Vendors::contactEmail($id),
            __('Your vendor application', 'sccc'),
            Vendors::greeting($id)
            .'<p>'.esc_html__('Thank you for applying to be a vendor with Space City Car Club. Unfortunately we\'re not able to accept your application at this time.', 'sccc').'</p>'
            .($reason !== '' ? '<p><strong>'.esc_html__('Reason:', 'sccc').'</strong><br>'.nl2br(esc_html($reason)).'</p>' : '')
            .'<p>'.esc_html__('We appreciate your interest and wish you all the best.', 'sccc').'</p>'
        );
    }

    /* -------------------------------------------------------------------------
     * "Email sign-up / payment link" button
     * ---------------------------------------------------------------------- */

    public static function handleSendLink(): void
    {
        $id = isset($_GET['vendor']) ? absint($_GET['vendor']) : 0;

        check_admin_referer(self::SEND_LINK_ACTION.'_'.$id);

        if (! $id || ! Vendors::isVendor($id) || ! current_user_can('edit_post', $id)) {
            wp_die(esc_html__('You are not allowed to do that.', 'sccc'), 403);
        }

        $payable = Vendors::payableShows($id);

        if (! $payable) {
            self::notice(__('There is nothing this vendor can sign up or pay for right now.', 'sccc'), 'error');
        } else {
            self::notice(VendorReturning::sendLink($id, $payable)
                ? __('Sign-up / payment link emailed.', 'sccc')
                : __('The email could not be sent.', 'sccc'), 'success');
        }

        wp_safe_redirect((string) get_edit_post_link($id, 'raw'));
        exit;
    }

    /* -------------------------------------------------------------------------
     * Admin notices
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
