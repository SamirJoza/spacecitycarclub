<?php

/**
 * File path + filename: app/Support/Vendors/VendorReturning.php
 *
 * Purpose:
 * - Handle the "Returning Vendor" Gravity Form ("I've been a vendor with you
 *   before"): the vendor enters their email, we look up their record and email
 *   a one-click personal link to the sign-up/payment page. No full form again.
 *
 * Outcomes (shown as the form confirmation):
 * - No record         → "We couldn't find you" + link to the new vendor application
 * - Pending review    → "Your application is still being reviewed"
 * - Rejected/blocked  → "Please contact us"
 * - Approved, but the only open show(s) don't allow food and they are a food
 *   vendor → friendly "no food vendors at that show" message, process ends
 * - Approved, nothing open → closed message
 * - Approved with open shows → link emailed, "Check your inbox"
 *
 * Why the email step: it proves the person owns the address on file before
 * showing a vendor's details or taking a payment, and it matches the invite
 * emails sent from EmailOctopus (which link to this same form).
 */

namespace App\Support\Vendors;

defined('ABSPATH') || exit;

final class VendorReturning
{
    public const F_EMAIL = 'returning_email';

    public const F_BUSINESS = 'returning_business_name';

    /** Minimum seconds between two link emails for the same vendor. */
    private const THROTTLE = 120;

    /** @var array<int, string> Confirmation HTML per entry ID. */
    private static array $results = [];

    public static function register(): void
    {
        add_action('gform_entry_created', [self::class, 'handle'], 10, 2);
        add_filter('gform_confirmation', [self::class, 'confirmation'], 20, 4);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $form
     */
    public static function handle($entry, $form): void
    {
        if (! is_array($entry) || ! Vendors::formHasClass($form, Vendors::RETURNING_FORM_CLASS)) {
            return;
        }

        $email = Vendors::entryValue($form, $entry, self::F_EMAIL);
        $vendorId = Vendors::findByEmail($email);

        self::$results[(int) $entry['id']] = self::outcome($vendorId);

        if (class_exists('GFAPI')) {
            \GFAPI::add_note((int) $entry['id'], 0, 'SCCC Vendors', $vendorId
                ? sprintf(__('Matched vendor #%1$d (%2$s).', 'sccc'), $vendorId, Vendors::statusLabel(Vendors::status($vendorId)))
                : __('No vendor record found for this email.', 'sccc'));
        }
    }

    private static function outcome(int $vendorId): string
    {
        $applicationUrl = Vendors::pageUrl('vendor_application_page');
        $applyButton = $applicationUrl !== ''
            ? '<p><a class="sccc-vendor-button" href="'.esc_url(add_query_arg('vendor_signup', 'new', $applicationUrl).'#vendor-signup').'">'.esc_html__('Fill out the vendor application', 'sccc').'</a></p>'
            : '';

        if (! $vendorId) {
            return self::box(
                '<p><strong>'.esc_html__('We couldn\'t find a vendor record for that email address.', 'sccc').'</strong></p>'
                .'<p>'.esc_html__('If you used a different email before, try that one. Otherwise, please fill out the vendor application — it only takes a few minutes.', 'sccc').'</p>'
                .$applyButton
            );
        }

        $status = Vendors::status($vendorId);

        if ($status === Vendors::STATUS_PENDING) {
            return self::box('<p>'.esc_html__('Thanks! Your vendor application is still being reviewed. We\'ll email you as soon as a decision is made.', 'sccc').'</p>');
        }

        if ($status !== Vendors::STATUS_APPROVED) {
            return self::box('<p>'.sprintf(
                esc_html__('We\'re not able to set up a show sign-up online for this account. Please contact us at %s.', 'sccc'),
                '<a href="mailto:'.esc_attr(Vendors::staffEmail()).'">'.esc_html(Vendors::staffEmail()).'</a>'
            ).'</p>');
        }

        $payable = Vendors::payableShows($vendorId);

        if (! $payable) {
            $foodBlocked = Vendors::foodBlockedShows($vendorId);

            if ($foodBlocked) {
                return self::box('<p>'.esc_html(Vendors::foodMessage((int) $foodBlocked[0])).'</p>');
            }

            $alreadyIn = array_filter(Vendors::openShows(), static fn (int $id): bool => Vendors::showRowStatus($vendorId, $id) !== '');

            return self::box('<p>'.esc_html($alreadyIn
                ? __('You\'re already signed up for our upcoming show — check your email for details. We\'ll let you know when the next one opens.', 'sccc')
                : Vendors::closedMessage()).'</p>');
        }

        $lastSent = (int) get_post_meta($vendorId, '_vendor_link_throttle', true);
        if ($lastSent < time() - self::THROTTLE) {
            update_post_meta($vendorId, '_vendor_link_throttle', time());
            self::sendLink($vendorId, $payable);
        }

        return self::box(
            '<p><strong>'.esc_html__('Welcome back! Check your inbox.', 'sccc').'</strong></p>'
            .'<p>'.esc_html__('We emailed a personal sign-up link to the address we have on file. It takes you straight to the show sign-up and payment — no need to fill out the application again.', 'sccc').'</p>'
            .'<p>'.esc_html__('Didn\'t get it? Check your spam folder or try again in a couple of minutes.', 'sccc').'</p>'
        );
    }

    /**
     * @param  array<int, float>  $payable
     */
    public static function sendLink(int $vendorId, array $payable): bool
    {
        $url = Vendors::signupUrl($vendorId);

        if ($url === '') {
            return false;
        }

        $list = '<ul>';
        foreach ($payable as $eventId => $fee) {
            $list .= '<li>'.esc_html(Vendors::eventLabel((int) $eventId)).' — <strong>$'.esc_html(number_format($fee, 2)).'</strong></li>';
        }
        $list .= '</ul>';

        $sent = Vendors::mail(
            Vendors::contactEmail($vendorId),
            __('Your vendor sign-up link', 'sccc'),
            Vendors::greeting($vendorId)
            .'<p>'.sprintf(esc_html__('Great to have %s back! You can sign up and pay for:', 'sccc'), '<strong>'.esc_html(Vendors::businessName($vendorId)).'</strong>').'</p>'
            .$list
            .'<p style="color:#64748b;font-size:14px;">'.esc_html__('This link is personal to your business and expires in 30 days. Please don\'t share it.', 'sccc').'</p>',
            [['label' => __('Sign up & pay', 'sccc'), 'url' => $url]]
        );

        if ($sent) {
            update_post_meta($vendorId, Vendors::META_LINK_EMAILED_AT, current_time('mysql'));
        }

        return $sent;
    }

    /**
     * @param  string|array<string, mixed>  $confirmation
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $entry
     * @return string|array<string, mixed>
     */
    public static function confirmation($confirmation, $form, $entry, $ajax)
    {
        if (! is_array($entry) || ! Vendors::formHasClass($form, Vendors::RETURNING_FORM_CLASS)) {
            return $confirmation;
        }

        return self::$results[(int) ($entry['id'] ?? 0)] ?? $confirmation;
    }

    private static function box(string $html): string
    {
        return '<div class="gform_confirmation_message sccc-vendor-message">'.$html.'</div>';
    }
}
