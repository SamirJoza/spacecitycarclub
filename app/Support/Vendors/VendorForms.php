<?php

/**
 * File path + filename: app/Support/Vendors/VendorForms.php
 *
 * Purpose:
 * - Create and maintain the three vendor Gravity Forms in code, so nothing has
 *   to be imported or clicked together by hand:
 *     • "Vendor Application"         (public, 4 pages) — new vendors
 *     • "Returning Vendor"           (public)          — email → personal link
 *     • "Vendor Sign-up & Payment"   (private link)    — show choice + Stripe
 * - Create their add-on feeds:
 *     • Stripe feed on the payment form (when Gravity Forms Stripe is active)
 *     • EmailOctopus feed on the application form (when a list is chosen in
 *       Theme Settings → Vendor Settings)
 *
 * When it runs:
 * - On admin page loads by an administrator (cheap checks, cached in options).
 * - Missing forms/feeds are created automatically.
 * - Existing forms are only overwritten from code when SCHEMA_VERSION goes up
 *   or when "Sync vendor forms" is clicked (Theme Settings → Vendor Settings).
 *   Code is the source of truth: edits made in the form editor are replaced on
 *   the next sync, so change wording here instead.
 *
 * How the rest of the feature finds the forms:
 * - By CSS class (`sccc-vendor-application`, `sccc-vendor-payment`) and field
 *   Admin Field Labels — see Vendors, VendorApplicationForm and VendorPayment.
 */

namespace App\Support\Vendors;

defined('ABSPATH') || exit;

final class VendorForms
{
    /** Bump when the form definitions below change to push them to existing forms. */
    public const SCHEMA_VERSION = 3;

    public const OPTION_APPLICATION_ID = 'sccc_vendor_application_form_id';

    public const OPTION_PAYMENT_ID = 'sccc_vendor_payment_form_id';

    public const OPTION_RETURNING_ID = 'sccc_vendor_returning_form_id';

    public const OPTION_SCHEMA = 'sccc_vendor_forms_schema';

    public const SYNC_ACTION = 'sccc_vendor_forms_sync';

    private const STRIPE_SLUG = 'gravityformsstripe';

    private const EMAILOCTOPUS_SLUG = 'gravityformsemailoctopus';

    public static function register(): void
    {
        add_action('admin_init', [self::class, 'maybeInstall'], 20);
        add_action('admin_post_'.self::SYNC_ACTION, [self::class, 'handleSync']);
        add_action('admin_notices', [self::class, 'renderStatus']);
        add_filter('acf/load_field/name=vendor_emailoctopus_list', [self::class, 'loadEmailOctopusLists']);
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

    /**
     * Ensure all vendor forms and their feeds exist. With $force, rewrite the forms
     * from the definitions in this file (form IDs, entries and feeds are kept).
     */
    public static function sync(bool $force = false): void
    {
        $applicationId = self::ensureForm(self::OPTION_APPLICATION_ID, Vendors::APPLICATION_FORM_CLASS, self::applicationForm(), $force);
        $paymentId = self::ensureForm(self::OPTION_PAYMENT_ID, Vendors::PAYMENT_FORM_CLASS, self::paymentForm(), $force);
        self::ensureForm(self::OPTION_RETURNING_ID, Vendors::RETURNING_FORM_CLASS, self::returningForm(), $force);

        if ($paymentId) {
            self::ensureStripeField($paymentId);
            self::ensureStripeFeed($paymentId);
        }

        if ($applicationId) {
            self::ensureEmailOctopusFeed($applicationId);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private static function ensureForm(string $option, string $cssClass, array $definition, bool $force): int
    {
        $formId = self::findFormId($option, $cssClass);

        if (! $formId) {
            $result = \GFAPI::add_form($definition);

            if (is_wp_error($result) || ! $result) {
                return 0;
            }

            $formId = (int) $result;
            update_option($option, $formId, false);

            return $formId;
        }

        if ($force) {
            $existing = \GFAPI::get_form($formId);
            $definition['id'] = $formId;
            // Keep whether the form is active; everything else comes from code.
            $definition['is_active'] = is_array($existing) ? ($existing['is_active'] ?? '1') : '1';
            \GFAPI::update_form($definition, $formId);
        }

        return $formId;
    }

    private static function findFormId(string $option, string $cssClass): int
    {
        $stored = (int) get_option($option, 0);

        if ($stored) {
            $form = \GFAPI::get_form($stored);
            if (is_array($form) && empty($form['is_trash']) && Vendors::formHasClass($form, $cssClass)) {
                return $stored;
            }
        }

        foreach ([true, false] as $active) {
            foreach ((array) \GFAPI::get_forms($active, false) as $form) {
                if (Vendors::formHasClass($form, $cssClass)) {
                    update_option($option, (int) $form['id'], false);

                    return (int) $form['id'];
                }
            }
        }

        return 0;
    }

    /** Add the Stripe card field once Gravity Forms Stripe is active. */
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

        $nextId = 1;
        foreach ((array) $form['fields'] as $field) {
            $nextId = max($nextId, (int) $field->id + 1);
        }

        $form['fields'][] = \GF_Fields::create([
            'type' => 'stripe_creditcard',
            'id' => $nextId,
            'formId' => $formId,
            'label' => 'Card details',
            'isRequired' => true,
            'inputs' => [
                ['id' => $nextId.'.1', 'label' => 'Card Details', 'name' => ''],
                ['id' => $nextId.'.4', 'label' => 'Card Type', 'name' => ''],
                ['id' => $nextId.'.5', 'label' => 'Cardholder Name', 'name' => ''],
            ],
        ]);

        \GFAPI::update_form($form, $formId);
    }

    private static function ensureStripeFeed(int $formId): void
    {
        if (! class_exists('GFStripe') || self::feeds($formId, self::STRIPE_SLUG)) {
            return;
        }

        $form = \GFAPI::get_form($formId);
        $email = is_array($form) ? Vendors::field($form, VendorPayment::F_EMAIL) : null;

        \GFAPI::add_feed($formId, [
            'feedName' => 'Vendor fee',
            'transactionType' => 'product',
            'paymentAmount' => 'form_total',
            'billingInformation_email' => $email ? (string) $email->id : '',
            'feed_condition_conditional_logic' => '0',
        ], self::STRIPE_SLUG);
    }

    private static function ensureEmailOctopusFeed(int $formId): void
    {
        if (! class_exists('GF_EmailOctopus') || ! function_exists('get_field')) {
            return;
        }

        $listId = trim((string) get_field('vendor_emailoctopus_list', 'option'));
        if ($listId === '') {
            return;
        }

        $form = \GFAPI::get_form($formId);
        if (! is_array($form)) {
            return;
        }

        $email = Vendors::field($form, VendorApplicationForm::F_EMAIL);
        $first = Vendors::field($form, VendorApplicationForm::F_FIRST_NAME);
        $last = Vendors::field($form, VendorApplicationForm::F_LAST_NAME);
        $optin = Vendors::field($form, VendorApplicationForm::F_MARKETING);

        if (! $email) {
            return;
        }

        $meta = [
            'feedName' => 'Vendors',
            'emailoctopuslist' => $listId,
            'mappedFields_EmailAddress' => (string) $email->id,
            'mappedFields_FirstName' => $first ? (string) $first->id : '',
            'mappedFields_LastName' => $last ? (string) $last->id : '',
            'feed_condition_conditional_logic' => $optin ? '1' : '0',
            'feed_condition_conditional_logic_object' => $optin ? [
                'conditionalLogic' => [
                    'actionType' => 'show',
                    'logicType' => 'all',
                    'rules' => [['fieldId' => (string) $optin->id, 'operator' => 'is', 'value' => 'yes']],
                ],
            ] : [],
        ];

        $existing = self::feeds($formId, self::EMAILOCTOPUS_SLUG);

        if (! $existing) {
            \GFAPI::add_feed($formId, $meta, self::EMAILOCTOPUS_SLUG);

            return;
        }

        // Follow the list chosen in Theme Settings.
        $feed = $existing[0];
        if ((string) rgars($feed, 'meta/emailoctopuslist') !== $listId) {
            \GFAPI::update_feed((int) $feed['id'], array_merge((array) $feed['meta'], ['emailoctopuslist' => $listId]), $formId);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function feeds(int $formId, string $slug): array
    {
        $feeds = \GFAPI::get_feeds(null, $formId, $slug, null);

        return is_array($feeds) ? $feeds : [];
    }

    /* -------------------------------------------------------------------------
     * Admin: sync button + status notice
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

        wp_safe_redirect(add_query_arg('sccc_vendor_forms_synced', '1', wp_get_referer() ?: admin_url('admin.php?page=theme-settings-vendor-settings')));
        exit;
    }

    /** Status box on Theme Settings → Vendor Settings. */
    public static function renderStatus(): void
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

        if ($page !== 'theme-settings-vendor-settings' || ! current_user_can('manage_options')) {
            return;
        }

        if (! class_exists('GFAPI')) {
            echo '<div class="notice notice-error"><p>'.esc_html__('Gravity Forms is not active, so the vendor forms cannot be created.', 'sccc').'</p></div>';

            return;
        }

        $applicationId = (int) get_option(self::OPTION_APPLICATION_ID, 0);
        $paymentId = (int) get_option(self::OPTION_PAYMENT_ID, 0);
        $returningId = (int) get_option(self::OPTION_RETURNING_ID, 0);

        $items = [];
        $items[] = self::statusLine(__('Vendor Application form', 'sccc'), $applicationId > 0, $applicationId ? sprintf('#%d', $applicationId) : '', $applicationId ? admin_url('admin.php?page=gf_edit_forms&id='.$applicationId) : '');
        $items[] = self::statusLine(__('Returning Vendor form', 'sccc'), $returningId > 0, $returningId ? sprintf('#%d', $returningId) : '', $returningId ? admin_url('admin.php?page=gf_edit_forms&id='.$returningId) : '');
        $items[] = self::statusLine(__('Vendor Sign-up & Payment form', 'sccc'), $paymentId > 0, $paymentId ? sprintf('#%d', $paymentId) : '', $paymentId ? admin_url('admin.php?page=gf_edit_forms&id='.$paymentId) : '');

        $stripeFeed = $paymentId && class_exists('GFStripe') && self::feeds($paymentId, self::STRIPE_SLUG);
        $items[] = self::statusLine(
            __('Stripe payment feed', 'sccc'),
            (bool) $stripeFeed,
            class_exists('GFStripe') ? '' : __('install/activate Gravity Forms Stripe', 'sccc'),
            $paymentId ? admin_url('admin.php?page=gf_edit_forms&view=settings&subview='.self::STRIPE_SLUG.'&id='.$paymentId) : ''
        );

        $eoFeed = $applicationId && class_exists('GF_EmailOctopus') && self::feeds($applicationId, self::EMAILOCTOPUS_SLUG);
        $items[] = self::statusLine(
            __('EmailOctopus feed', 'sccc'),
            (bool) $eoFeed,
            $eoFeed ? '' : __('choose a list below and save', 'sccc'),
            $applicationId ? admin_url('admin.php?page=gf_edit_forms&view=settings&subview='.self::EMAILOCTOPUS_SLUG.'&id='.$applicationId) : ''
        );

        foreach (['vendor_application_page' => __('Vendor page (sign-up block)', 'sccc'), 'vendor_payment_page' => __('Sign-up & payment page', 'sccc')] as $option => $label) {
            $url = Vendors::pageUrl($option);
            $items[] = self::statusLine($label, $url !== '', $url !== '' ? '' : __('choose it below', 'sccc'), $url);
        }

        $synced = ! empty($_GET['sccc_vendor_forms_synced']); // phpcs:ignore WordPress.Security.NonceVerification

        echo '<div class="notice notice-info"><p><strong>'.esc_html__('Vendor forms', 'sccc').'</strong>'
            .($synced ? ' — '.esc_html__('synced from code.', 'sccc') : '').'</p><ul style="margin-left:1em;list-style:none;">'
            .implode('', $items) // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
            .'</ul><p><a class="button" href="'.esc_url(self::syncUrl()).'" onclick="return confirm(\''.esc_js(__('Rebuild all vendor forms from the theme code? Edits made in the form editor will be replaced. Entries and feeds are kept.', 'sccc')).'\');">'
            .esc_html__('Sync vendor forms from code', 'sccc').'</a></p></div>';
    }

    private static function statusLine(string $label, bool $ok, string $detail, string $url): string
    {
        $icon = $ok
            ? '<span class="dashicons dashicons-yes-alt" style="color:#16a34a;"></span>'
            : '<span class="dashicons dashicons-warning" style="color:#d97706;"></span>';
        $text = esc_html($label).($detail !== '' ? ' — '.esc_html($detail) : '');

        if ($url !== '') {
            $text = '<a href="'.esc_url($url).'">'.$text.'</a>';
        }

        return '<li>'.$icon.' '.$text.'</li>';
    }

    /**
     * Fill the EmailOctopus list dropdown in Vendor Settings from the add-on.
     *
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    public static function loadEmailOctopusLists(array $field): array
    {
        $choices = ['' => __('— Don\'t add vendors to EmailOctopus —', 'sccc')];

        if (is_admin() && class_exists('GF_EmailOctopus')) {
            $addon = \GF_EmailOctopus::get_instance();

            if ($addon->initialize_api()) {
                foreach ((array) $addon->get_emailoctopus_lists() as $option) {
                    if (($option['value'] ?? '') !== '') {
                        $choices[(string) $option['value']] = (string) $option['label'];
                    }
                }
            } else {
                $field['instructions'] = __('Connect EmailOctopus first: Forms → Settings → EmailOctopus (API key).', 'sccc');
            }
        }

        $field['choices'] = $choices;

        return $field;
    }

    /* -------------------------------------------------------------------------
     * Form definitions (source of truth)
     * ---------------------------------------------------------------------- */

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
     * @param  array<string, string>  $pairs  value => text
     * @return array<int, array<string, mixed>>
     */
    private static function choices(array $pairs, string $selected = ''): array
    {
        $choices = [];
        foreach ($pairs as $value => $text) {
            $choices[] = ['text' => $text, 'value' => (string) $value, 'isSelected' => (string) $value === $selected, 'price' => ''];
        }

        return $choices;
    }

    /**
     * Single-choice checkbox (field ID $id, input $id.1).
     *
     * @return array<string, mixed>
     */
    private static function singleCheckbox(int $id, string $label, string $adminLabel, string $text, string $value, bool $checked, bool $required): array
    {
        return self::field($id, 'checkbox', $label, $adminLabel, [
            'isRequired' => $required,
            'choices' => [['text' => $text, 'value' => $value, 'isSelected' => $checked, 'price' => '']],
            'inputs' => [['id' => $id.'.1', 'label' => $text, 'name' => '']],
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function page(int $id, string $next = 'Next'): array
    {
        return [
            'id' => $id,
            'type' => 'page',
            'label' => '',
            'adminLabel' => '',
            'nextButton' => ['type' => 'text', 'text' => $next, 'imageUrl' => ''],
            'previousButton' => ['type' => 'text', 'text' => 'Back', 'imageUrl' => ''],
        ];
    }

    /**
     * @param  array<string, string>  $pairs
     * @return array<string, mixed>
     */
    private static function checkboxes(int $id, string $label, string $adminLabel, array $pairs, array $extra = []): array
    {
        $inputs = [];
        $index = 0;
        foreach ($pairs as $text) {
            $index++;
            $inputs[] = ['id' => $id.'.'.$index, 'label' => $text, 'name' => ''];
        }

        return self::field($id, 'checkbox', $label, $adminLabel, array_merge([
            'choices' => self::choices($pairs),
            'inputs' => $inputs,
        ], $extra));
    }

    /** @return array<string, string> */
    private static function yesNo(): array
    {
        return ['no' => 'No', 'yes' => 'Yes'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function baseForm(string $title, string $description, string $cssClass, string $button): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'cssClass' => $cssClass,
            'labelPlacement' => 'top_label',
            'descriptionPlacement' => 'below',
            'subLabelPlacement' => 'below',
            'requiredIndicator' => 'asterisk',
            'markupVersion' => 2,
            'enableHoneypot' => true,
            'enableAnimation' => false,
            'is_active' => '1',
            'button' => ['type' => 'text', 'text' => $button, 'imageUrl' => ''],
            'notifications' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function message(string $id, string $html): array
    {
        return [
            $id => [
                'id' => $id,
                'name' => 'Default Confirmation',
                'isDefault' => true,
                'type' => 'message',
                'message' => $html,
                'url' => '',
                'pageId' => '',
                'queryString' => '',
                'disableAutoformat' => false,
                'conditionalLogic' => [],
            ],
        ];
    }

    /**
     * New vendor application — 4 pages.
     *
     * @return array<string, mixed>
     */
    public static function applicationForm(): array
    {
        $site = get_bloginfo('name') ?: 'Space City Car Club';
        $A = VendorApplicationForm::class;
        $types = array_map(static fn (array $type): string => $type[0], Vendors::defaultTypes());

        $form = self::baseForm(
            'Vendor Application',
            'Apply to be a vendor at a Space City Car Club show. We review every application and email you once a decision is made. Payment is only requested after approval.',
            Vendors::APPLICATION_FORM_CLASS,
            'Submit application'
        );

        $form['pagination'] = [
            'type' => 'steps',
            'pages' => ['Your business', 'Contact', 'Show & booth', 'Promotion & agreement'],
            'style' => 'blue',
            'display_progressbar_on_confirmation' => false,
        ];
        $form['firstPageCssClass'] = '';
        $form['lastPageButton'] = ['type' => 'text', 'text' => 'Back', 'imageUrl' => ''];

        $form['fields'] = [
            // Page 1 — Your business
            self::field(2, 'text', 'Business name', $A::F_BUSINESS, ['isRequired' => true, 'layoutGridColumnSpan' => 6]),
            self::field(3, 'text', 'Display name (if different)', $A::F_DISPLAY_NAME, ['layoutGridColumnSpan' => 6, 'description' => 'The name you\'d like us to use in promotions.']),
            self::field(4, 'select', 'Type of business', $A::F_TYPE, ['isRequired' => true, 'placeholder' => 'Choose one', 'choices' => self::choices($types)]),
            self::field(5, 'textarea', 'Short description', $A::F_BLURB, ['isRequired' => true, 'maxLength' => 300, 'size' => 'small', 'description' => 'One or two sentences we can use in promotions (300 characters max).']),
            self::field(6, 'textarea', 'What do you sell or offer?', $A::F_OFFERINGS, ['isRequired' => true, 'size' => 'small']),
            self::field(7, 'website', 'Website', $A::F_WEBSITE, ['placeholder' => 'https://', 'layoutGridColumnSpan' => 6]),
            self::field(8, 'text', 'Instagram', $A::F_INSTAGRAM, ['placeholder' => '@yourbusiness', 'layoutGridColumnSpan' => 6]),
            self::field(9, 'text', 'Facebook', $A::F_FACEBOOK, ['placeholder' => 'Page name or URL', 'layoutGridColumnSpan' => 6]),
            self::field(10, 'text', 'TikTok', $A::F_TIKTOK, ['placeholder' => '@yourbusiness', 'layoutGridColumnSpan' => 6]),
            self::field(11, 'fileupload', 'Logo', $A::F_LOGO, [
                'allowedExtensions' => 'jpg,jpeg,png,webp',
                'maxFileSize' => 5,
                'description' => 'PNG with a transparent background works best (at least 1000px wide, max 5 MB). We may feature it in show promotions.',
            ]),
            self::field(12, 'fileupload', 'Product photos (up to 3)', $A::F_PHOTOS, [
                'allowedExtensions' => 'jpg,jpeg,png,webp',
                'maxFileSize' => 5,
                'multipleFiles' => true,
                'maxFiles' => 3,
            ]),
            self::page(13),

            // Page 2 — Contact
            self::field(14, 'text', 'First name', $A::F_FIRST_NAME, ['isRequired' => true, 'layoutGridColumnSpan' => 4]),
            self::field(15, 'text', 'Last name', $A::F_LAST_NAME, ['isRequired' => true, 'layoutGridColumnSpan' => 4]),
            self::field(16, 'text', 'Your role', $A::F_ROLE, ['placeholder' => 'Owner, manager…', 'layoutGridColumnSpan' => 4]),
            self::field(17, 'email', 'Email', $A::F_EMAIL, ['isRequired' => true, 'layoutGridColumnSpan' => 6, 'description' => 'We\'ll use this to recognise you next time.']),
            self::field(18, 'phone', 'Mobile phone', $A::F_PHONE, ['isRequired' => true, 'phoneFormat' => 'standard', 'layoutGridColumnSpan' => 6]),
            self::singleCheckbox(19, 'Texting', $A::F_TEXT_OK, 'It\'s OK to text me about the show', 'yes', true, false),
            self::field(20, 'address', 'Business address', $A::F_ADDRESS, [
                'isRequired' => true,
                'addressType' => 'us',
                'defaultState' => 'Texas',
                'defaultCountry' => 'United States',
                'inputs' => [
                    ['id' => '20.1', 'label' => 'Street Address', 'name' => ''],
                    ['id' => '20.2', 'label' => 'Address Line 2', 'name' => ''],
                    ['id' => '20.3', 'label' => 'City', 'name' => ''],
                    ['id' => '20.4', 'label' => 'State', 'name' => ''],
                    ['id' => '20.5', 'label' => 'ZIP Code', 'name' => ''],
                    ['id' => '20.6', 'label' => 'Country', 'name' => '', 'isHidden' => true],
                ],
            ]),
            self::field(21, 'text', 'Show-day contact (if different)', $A::F_DAYOF_NAME, ['layoutGridColumnSpan' => 6]),
            self::field(22, 'phone', 'Show-day phone', $A::F_DAYOF_PHONE, ['phoneFormat' => 'standard', 'layoutGridColumnSpan' => 6]),
            self::page(23),

            // Page 3 — Show & booth
            self::field(24, 'radio', 'Which show?', $A::F_SHOW, [
                'isRequired' => true,
                'description' => 'Open shows are listed automatically.',
                'choices' => [['text' => 'Upcoming shows load automatically', 'value' => '0', 'isSelected' => false, 'price' => '']],
            ]),
            self::field(25, 'radio', 'Booth type', $A::F_BOOTH, ['isRequired' => true, 'choices' => self::choices(Vendors::boothTypes(), '10x10')]),
            self::field(26, 'text', 'Truck / trailer length', $A::F_TRAILER, [
                'placeholder' => 'e.g. 24 ft',
                'conditionalLogic' => [
                    'actionType' => 'show',
                    'logicType' => 'all',
                    'rules' => [['fieldId' => '25', 'operator' => 'is', 'value' => 'food_truck']],
                ],
            ]),
            self::checkboxes(27, 'We bring our own', $A::F_EQUIPMENT, ['tent' => 'Tent / canopy', 'tables' => 'Tables', 'chairs' => 'Chairs']),
            self::field(28, 'radio', 'Do you need electricity?', $A::F_POWER, ['isRequired' => true, 'choices' => self::choices(self::yesNo(), 'no'), 'layoutGridColumnSpan' => 4]),
            self::field(29, 'radio', 'Bringing a generator?', $A::F_GENERATOR, ['isRequired' => true, 'choices' => self::choices(self::yesNo(), 'no'), 'layoutGridColumnSpan' => 4, 'description' => 'Quiet inverter generators only.']),
            self::field(30, 'radio', 'Open flame or propane?', $A::F_OPEN_FLAME, ['isRequired' => true, 'choices' => self::choices(self::yesNo(), 'no'), 'layoutGridColumnSpan' => 4]),
            self::field(31, 'number', 'How many staff on site?', $A::F_STAFF, ['numberFormat' => 'decimal_dot', 'rangeMin' => 0, 'rangeMax' => 50, 'layoutGridColumnSpan' => 6, 'size' => 'small']),
            self::field(32, 'textarea', 'Anything else we should know?', $A::F_NOTES, ['size' => 'small']),
            self::page(33),

            // Page 4 — Promotion & agreement
            self::field(34, 'radio', 'May we feature your business name and logo in show promotions?', $A::F_FEATURE_OK, ['isRequired' => true, 'choices' => self::choices(['yes' => 'Yes, please feature us', 'no' => 'No thanks'], 'yes')]),
            self::field(35, 'text', 'Special offer for club members or attendees (optional)', $A::F_OFFER, ['placeholder' => 'e.g. 10% off for SCCC members']),
            self::field(36, 'select', 'How did you hear about us?', $A::F_HEARD, ['placeholder' => 'Choose one', 'choices' => self::choices([
                'Instagram' => 'Instagram', 'Facebook' => 'Facebook', 'TikTok' => 'TikTok', 'Word of mouth' => 'Friend / word of mouth',
                'At a show' => 'At one of our shows', 'Google' => 'Google', 'Other' => 'Other',
            ])]),
            self::singleCheckbox(37, 'Stay in the loop', $A::F_MARKETING, 'Yes, email me when vendor sign-up opens for future shows.', 'yes', true, false),
            self::singleCheckbox(38, 'Vendor agreement', $A::F_AGREEMENT, 'I agree to follow the show rules, setup and teardown times, and staff instructions. I understand my application is reviewed before approval and payment is only requested after approval.', 'agreed', false, true),
            self::field(39, 'text', 'Type your full name to sign', $A::F_SIGNATURE, ['isRequired' => true]),
            self::field(40, 'hidden', 'Referral source', $A::F_REFERRAL, ['allowsPrepopulate' => true, 'inputName' => 'referral_source', 'size' => 'medium']),
        ];

        $form['confirmations'] = self::message('sccc0vendorconfirm', '<strong>Thanks, {First name:14}!</strong> We received your vendor application. We review every application and will email you once a decision is made. If you\'re approved, we\'ll send a secure link to pay the vendor fee.');

        $form['notifications'] = [
            'sccc0vendoradmin' => [
                'id' => 'sccc0vendoradmin', 'name' => 'Staff: new vendor application', 'service' => 'wordpress', 'event' => 'form_submission',
                'toType' => 'email', 'to' => '{admin_email}', 'subject' => 'New vendor application: {Business name:2}',
                'message' => "A new vendor application is waiting for review in wp-admin → Vendors.\n\n{all_fields}",
                'from' => '{admin_email}', 'fromName' => $site, 'replyTo' => '{Email:17}', 'bcc' => '', 'disableAutoformat' => false, 'isActive' => true,
            ],
            'sccc0vendorreceipt' => [
                'id' => 'sccc0vendorreceipt', 'name' => 'Vendor: application received', 'service' => 'wordpress', 'event' => 'form_submission',
                'toType' => 'field', 'to' => '17', 'subject' => 'We received your vendor application',
                'message' => "Hi {First name:14},\n\nThanks for applying to be a vendor with {$site}! We review every application and will email you once a decision is made.\n\nIf you're approved, you'll receive a secure link to pay the vendor fee — no payment is needed until then. Next time, just choose \"I've been a vendor with you before\" — no need to fill out the whole application again.\n\n{$site}",
                'from' => '{admin_email}', 'fromName' => $site, 'replyTo' => '{admin_email}', 'bcc' => '', 'disableAutoformat' => false, 'isActive' => true,
            ],
        ];

        return $form;
    }

    /**
     * Returning vendor: email → personal sign-up link (VendorReturning).
     *
     * @return array<string, mixed>
     */
    public static function returningForm(): array
    {
        $form = self::baseForm(
            'Returning Vendor',
            'Welcome back! Enter the email address you used before and we\'ll email you a personal link to sign up for the next show.',
            Vendors::RETURNING_FORM_CLASS,
            'Email my sign-up link'
        );

        $form['fields'] = [
            self::field(1, 'email', 'Email address', VendorReturning::F_EMAIL, ['isRequired' => true]),
            self::field(2, 'text', 'Business name (optional)', VendorReturning::F_BUSINESS),
        ];

        // Replaced at submission by VendorReturning::confirmation().
        $form['confirmations'] = self::message('sccc0vendorreturning', 'Thanks! Check your inbox.');

        return $form;
    }

    /**
     * Private sign-up & payment form. The Stripe card field is added by
     * ensureStripeField() once Gravity Forms Stripe is active.
     *
     * @return array<string, mixed>
     */
    public static function paymentForm(): array
    {
        $P = VendorPayment::class;

        $form = self::baseForm(
            'Vendor Sign-up & Payment',
            'Choose your show and pay the vendor fee to confirm your spot.',
            Vendors::PAYMENT_FORM_CLASS,
            'Pay vendor fee'
        );

        $form['fields'] = [
            self::field(1, 'html', 'Vendor', $P::F_SUMMARY, ['content' => '<p>Loading your vendor details…</p>']),
            self::field(2, 'hidden', 'Vendor', $P::F_VENDOR, ['allowsPrepopulate' => true, 'inputName' => Vendors::QUERY_VENDOR, 'size' => 'medium']),
            self::field(3, 'hidden', 'Token', $P::F_TOKEN, ['allowsPrepopulate' => true, 'inputName' => Vendors::QUERY_TOKEN, 'size' => 'medium']),
            self::field(4, 'product', 'Show', $P::F_PRODUCT, [
                'inputType' => 'select',
                'enablePrice' => true,
                'isRequired' => true,
                'choices' => [['text' => 'Open shows load automatically', 'value' => '0', 'price' => '$0.00', 'isSelected' => false]],
            ]),
            self::field(5, 'email', 'Email for your receipt', $P::F_EMAIL, ['isRequired' => true]),
            self::field(6, 'total', 'Total', 'vendor_payment_total'),
        ];

        if (class_exists('GFStripe')) {
            $form['fields'][] = self::field(7, 'stripe_creditcard', 'Card details', 'vendor_payment_card', [
                'isRequired' => true,
                'inputs' => [
                    ['id' => '7.1', 'label' => 'Card Details', 'name' => ''],
                    ['id' => '7.4', 'label' => 'Card Type', 'name' => ''],
                    ['id' => '7.5', 'label' => 'Cardholder Name', 'name' => ''],
                ],
            ]);
        }

        $form['confirmations'] = self::message('sccc0vendorpaid', '<strong>Payment received — thank you!</strong> Your spot is confirmed and a confirmation email is on its way.');

        return $form;
    }
}
