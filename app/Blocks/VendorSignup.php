<?php

/**
 * File path + filename: app/Blocks/VendorSignup.php
 *
 * Purpose:
 * - "Vendor Sign-up" block for the public vendor page. It asks one question —
 *   "Have you been a vendor with us before?" — and shows the matching form:
 *     • Yes → the short Returning Vendor form (email → personal link)
 *     • No  → the full 4-page Vendor Application
 * - Lists the open club shows (date, fee, "no food vendors" note) above.
 *
 * Why this file exists:
 * - Returning vendors should never have to fill out the whole application
 *   again. Links can preselect a path: ?vendor_signup=returning or
 *   ?vendor_signup=new (used in emails and EmailOctopus invites), and the
 *   anchor #vendor-signup scrolls to the block.
 *
 * Notes:
 * - The forms are created in code by App\Support\Vendors\VendorForms; this block
 *   finds them by the stored form IDs, so nothing has to be selected here.
 */

declare(strict_types=1);

namespace App\Blocks;

use App\Support\Vendors\VendorForms;
use App\Support\Vendors\Vendors;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class VendorSignup extends Block
{
    public $name = 'Vendor Sign-up';

    public $slug = 'vendor-signup';

    public $view = 'blocks.vendor-signup';

    public $description = 'Asks "Have you been a vendor with us before?" and shows the returning-vendor or new-vendor application form.';

    public $category = 'theme-blocks';

    public $icon = 'store';

    public $keywords = ['vendor', 'application', 'signup', 'show', 'form'];

    public $mode = 'preview';

    public $apiVersion = 3;

    public $supports = [
        'align' => false,
        'anchor' => true,
        'mode' => false,
        'multiple' => false,
        'jsx' => true,
    ];

    public function with(): array
    {
        $applicationId = (int) get_option(VendorForms::OPTION_APPLICATION_ID, 0);
        $returningId = (int) get_option(VendorForms::OPTION_RETURNING_ID, 0);

        // Keep the right panel open after a submission / validation error.
        $submitted = isset($_POST['gform_submit']) ? absint($_POST['gform_submit']) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        $requested = isset($_GET['vendor_signup']) ? sanitize_key(wp_unslash($_GET['vendor_signup'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        $open = match (true) {
            $submitted && $submitted === $returningId => 'returning',
            $submitted && $submitted === $applicationId => 'new',
            in_array($requested, ['new', 'returning'], true) => $requested,
            default => (string) (get_field('default_panel') ?: ''),
        };

        $shows = [];
        foreach (Vendors::openShows() as $eventId) {
            $isBooth = (string) get_post_meta($eventId, Vendors::EVENT_PRICING_MODE, true) === 'booth';
            $fee = Vendors::showFee($eventId);

            $shows[] = [
                'title' => Vendors::eventTitle($eventId),
                'date' => ($ts = Vendors::eventTimestamp($eventId)) ? wp_date('l, F j, Y', $ts) : '',
                'venue' => trim((string) get_post_meta($eventId, 'mep_location_venue', true)),
                'fee' => $isBooth ? __('Fee depends on booth type', 'sccc') : ($fee > 0 ? '$'.number_format($fee, 2) : ''),
                'noFood' => ! Vendors::showAllowsFood($eventId),
                'url' => (string) get_permalink($eventId),
            ];
        }

        return [
            'eyebrow' => (string) (get_field('eyebrow') ?: 'Vendors'),
            'heading' => (string) (get_field('heading') ?: 'Become a vendor at our next show'),
            'intro' => (string) (get_field('intro') ?: ''),
            'question' => (string) (get_field('question') ?: 'Have you been a vendor with us before?'),
            'returningLabel' => (string) (get_field('returning_label') ?: 'Yes, I\'ve been a vendor before'),
            'returningHint' => (string) (get_field('returning_hint') ?: 'Just your email — we\'ll send your personal sign-up link.'),
            'newLabel' => (string) (get_field('new_label') ?: 'No, I\'m new'),
            'newHint' => (string) (get_field('new_hint') ?: 'Fill out the vendor application (about 5 minutes).'),
            'open' => $open,
            'shows' => $shows,
            'closedMessage' => Vendors::closedMessage(),
            'applicationForm' => self::renderForm($applicationId),
            'returningForm' => self::renderForm($returningId),
            'gfActive' => function_exists('gravity_form'),
        ];
    }

    private static function renderForm(int $formId): string
    {
        if (! $formId || ! function_exists('gravity_form')) {
            return '';
        }

        // title, description off; AJAX on; no tabindex; return instead of echo.
        return (string) gravity_form($formId, false, false, false, null, true, 0, false);
    }

    public function fields(): array
    {
        $fields = Builder::make('vendor_signup');

        $fields
            ->addText('eyebrow', ['label' => 'Eyebrow', 'default_value' => 'Vendors', 'wrapper' => ['width' => '30']])
            ->addText('heading', ['label' => 'Heading', 'default_value' => 'Become a vendor at our next show', 'wrapper' => ['width' => '70']])
            ->addTextarea('intro', ['label' => 'Intro', 'rows' => 3, 'new_lines' => 'br'])
            ->addText('question', ['label' => 'Question', 'default_value' => 'Have you been a vendor with us before?'])
            ->addText('returning_label', ['label' => '"Yes" button', 'default_value' => 'Yes, I\'ve been a vendor before', 'wrapper' => ['width' => '50']])
            ->addText('returning_hint', ['label' => '"Yes" hint', 'default_value' => 'Just your email — we\'ll send your personal sign-up link.', 'wrapper' => ['width' => '50']])
            ->addText('new_label', ['label' => '"No" button', 'default_value' => 'No, I\'m new', 'wrapper' => ['width' => '50']])
            ->addText('new_hint', ['label' => '"No" hint', 'default_value' => 'Fill out the vendor application (about 5 minutes).', 'wrapper' => ['width' => '50']])
            ->addSelect('default_panel', [
                'label' => 'Open by default',
                'choices' => ['' => 'Nothing — let the visitor choose', 'returning' => 'Returning vendor', 'new' => 'New vendor application'],
                'default_value' => '',
                'return_format' => 'value',
            ]);

        return $fields->build();
    }
}
