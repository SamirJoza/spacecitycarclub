<?php

/**
 * File path + filename: app/Support/Vendors/EventVendorModernEditor.php
 *
 * Purpose:
 * - Adds a "Club Show" step (car registration + vendors) to MagePeople's Modern event editor
 *   (edit.php?post_type=mep_events&page=mpwem_event_edit).
 *
 * Why this file exists:
 * - Since Event Manager 5.7, events open in a custom admin page instead of
 *   post.php. That page doesn't render meta boxes, so the ACF "Vendors" box
 *   (app/Fields/EventVendorSettings.php) only appears in the Classic editor.
 * - This class renders the same settings as a wizard step, using the plugin's
 *   own hooks:
 *     mpwem_event_edit_steps       → adds the "Vendors" step button
 *     mp_event_all_in_tab_item     → outputs the step panel inside the form
 *     mpwem_after_event_edit_save  → saves the values
 * - Values are written with update_field() and the same field keys, so the
 *   Classic editor's ACF box, Vendors.php and the admin tools all read the same
 *   data regardless of which editor was used.
 */

namespace App\Support\Vendors;

use App\Support\CarShows\CarShows;

defined('ABSPATH') || exit;

class EventVendorModernEditor
{
    private const PAGE_SLUG = 'mpwem_event_edit';

    private const STEP_KEY = 'vendors';

    private const PANEL_ID = 'sccc_vendor_settings';

    private const NONCE = 'sccc_event_vendor_modern';

    private const INPUT = 'sccc_event_vendor';

    public static function register(): void
    {
        add_filter('mpwem_event_edit_steps', [self::class, 'addStep'], 20, 2);
        add_action('mp_event_all_in_tab_item', [self::class, 'renderPanel'], 20);
        add_action('mpwem_after_event_edit_save', [self::class, 'save'], 20);
    }

    /** Only the Modern editor page; the Classic screen already has the ACF box. */
    private static function isModernEditor(): bool
    {
        return is_admin()
            && isset($_GET['page'])
            && sanitize_key(wp_unslash($_GET['page'])) === self::PAGE_SLUG;
    }

    /**
     * @param  array<int, array<string, string>>  $steps
     * @return array<int, array<string, string>>
     */
    public static function addStep($steps, $postId = 0): array
    {
        $steps = is_array($steps) ? $steps : [];

        if (! function_exists('update_field') || ! current_user_can('edit_post', (int) $postId)) {
            return $steps;
        }

        foreach ($steps as $step) {
            if (($step['key'] ?? '') === self::STEP_KEY) {
                return $steps;
            }
        }

        $steps[] = [
            'key' => self::STEP_KEY,
            'label' => __('Club Show', 'sccc'),
            'panel' => '#'.self::PANEL_ID,
        ];

        return $steps;
    }

    public static function renderPanel($postId = 0): void
    {
        $postId = (int) $postId;

        if (! self::isModernEditor() || $postId <= 0 || ! function_exists('update_field')) {
            return;
        }

        $isCarShow = CarShows::isCarShow($postId);
        $carCount = CarShows::registeredCount($postId);

        $accepting = (bool) get_post_meta($postId, Vendors::EVENT_ACCEPTING, true);
        $fee = (string) get_post_meta($postId, Vendors::EVENT_FEE, true);
        $foodRaw = get_post_meta($postId, Vendors::EVENT_FOOD_ALLOWED, true);
        $food = $foodRaw === '' ? true : (bool) $foodRaw; // ACF default is "allowed".
        $mode = (string) get_post_meta($postId, Vendors::EVENT_PRICING_MODE, true) === 'booth' ? 'booth' : 'flat';

        $deadline = preg_replace('/\D/', '', (string) get_post_meta($postId, Vendors::EVENT_DEADLINE, true));
        $deadline = strlen($deadline) === 8
            ? substr($deadline, 0, 4).'-'.substr($deadline, 4, 2).'-'.substr($deadline, 6, 2)
            : '';

        $boothPrices = [];
        $boothCount = (int) get_post_meta($postId, Vendors::EVENT_BOOTH_PRICES, true);
        for ($i = 0; $i < $boothCount; $i++) {
            $type = (string) get_post_meta($postId, Vendors::EVENT_BOOTH_PRICES.'_'.$i.'_booth_type', true);
            if ($type !== '') {
                $boothPrices[$type] = (string) get_post_meta($postId, Vendors::EVENT_BOOTH_PRICES.'_'.$i.'_price', true);
            }
        }

        $name = static fn (string $key): string => self::INPUT.'['.$key.']';
        $detailsId = self::PANEL_ID.'_details';
        $boothId = self::PANEL_ID.'_booths';
        ?>
        <section class="mpwem-wizard-panel mp_tab_item" data-tab-item="#<?php echo esc_attr(self::PANEL_ID); ?>" id="<?php echo esc_attr(self::PANEL_ID); ?>" style="display:none;">
            <?php wp_nonce_field(self::NONCE, self::NONCE); ?>
            <input type="hidden" name="<?php echo esc_attr($name('present')); ?>" value="1" />

            <div class="mpwem-event-wizard__grid">
                <div class="mpwem-event-wizard__main">
                    <div class="mpwem-card">
                        <div class="mpwem-card__head">
                            <h2><?php esc_html_e('Car registration', 'sccc'); ?></h2>
                            <p>
                                <?php if ($isCarShow) : ?>
                                    <?php echo esc_html(sprintf(_n('This is a car show. %d car registered so far.', 'This is a car show. %d cars registered so far.', $carCount, 'sccc'), $carCount)); ?>
                                <?php else : ?>
                                    <?php esc_html_e('Not a car show yet: set Category to "Car Show" and Organizer to "Space City Car Club" (Basic Info), then save. These settings are ignored until then.', 'sccc'); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="mpwem-card__body">
                            <?php $tickets = CarShows::tickets($postId); ?>
                            <p class="description"><?php esc_html_e('Prices come from this event\'s Ticket & Pricing step: each ticket type is a car registration option (e.g. "Early Bird" with a sale end date, and "Regular"). Its quantity is the number of cars it allows. Registration closes when the show starts.', 'sccc'); ?></p>
                            <?php if ($tickets) : ?>
                                <ul style="margin:.75rem 0 0 1.1rem;list-style:disc;">
                                    <?php foreach ($tickets as $ticket) : ?>
                                        <li>
                                            <?php
                                            echo esc_html($ticket['name'].' — '.CarShows::money($ticket['price']));
                                            echo esc_html(' · '.sprintf(__('%d registered', 'sccc'), $ticket['sold']));
                                            if ($ticket['capacity'] > 0) {
                                                echo esc_html(' / '.$ticket['capacity']);
                                            }
                                            if ($ticket['ends']) {
                                                echo esc_html(' · '.sprintf(__('on sale until %s', 'sccc'), wp_date('M j, Y g:i a', $ticket['ends'])));
                                            }
                                            if (! $ticket['on_sale']) {
                                                echo ' <strong>'.esc_html__('(not on sale now)', 'sccc').'</strong>';
                                            }
                                            ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <p><strong><?php esc_html_e('No ticket types yet — add at least one in Ticket & Pricing to open car registration.', 'sccc'); ?></strong></p>
                            <?php endif; ?>
                            <?php if ($carCount > 0) : ?>
                                <p class="description"><a href="<?php echo esc_url(admin_url('edit.php?post_type='.CarShows::POST_TYPE.'&car_show='.$postId)); ?>"><?php esc_html_e('View registered cars', 'sccc'); ?></a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mpwem-card">
                        <div class="mpwem-card__head">
                            <h2><?php esc_html_e('Vendors', 'sccc'); ?></h2>
                            <p><?php esc_html_e('Only for our own club shows. Other organizations\' events stay off and never appear in the vendor forms or blocks.', 'sccc'); ?></p>
                        </div>
                        <div class="mpwem-card__body">
                            <label class="mpwem-field">
                                <span class="mpwem-field__label"><?php esc_html_e('Club show: accept vendor applications', 'sccc'); ?></span>
                                <input type="checkbox" name="<?php echo esc_attr($name('accepting')); ?>" value="1" data-collapse-target="#<?php echo esc_attr($detailsId); ?>" <?php checked($accepting); ?> />
                            </label>

                            <div id="<?php echo esc_attr($detailsId); ?>" <?php echo $accepting ? '' : 'style="display:none;"'; ?>>
                                <label class="mpwem-field">
                                    <span class="mpwem-field__label"><?php esc_html_e('Vendor fee ($)', 'sccc'); ?></span>
                                    <input type="number" class="mpwem-input" name="<?php echo esc_attr($name('fee')); ?>" value="<?php echo esc_attr($fee); ?>" min="0" step="0.01" />
                                    <small class="mpwem-tooltip-skip"><?php esc_html_e('Price per vendor for this show.', 'sccc'); ?></small>
                                </label>

                                <label class="mpwem-field">
                                    <span class="mpwem-field__label"><?php esc_html_e('Food vendors allowed', 'sccc'); ?></span>
                                    <input type="checkbox" name="<?php echo esc_attr($name('food')); ?>" value="1" <?php checked($food); ?> />
                                </label>
                                <p class="description"><?php esc_html_e('When off, food vendors cannot apply or sign up for this show and are told why.', 'sccc'); ?></p>

                                <label class="mpwem-field">
                                    <span class="mpwem-field__label"><?php esc_html_e('Application deadline', 'sccc'); ?></span>
                                    <input type="date" class="mpwem-input" name="<?php echo esc_attr($name('deadline')); ?>" value="<?php echo esc_attr($deadline); ?>" />
                                    <small class="mpwem-tooltip-skip"><?php esc_html_e('Optional. The show closes to vendors after this day, and automatically once the show date has passed.', 'sccc'); ?></small>
                                </label>

                                <label class="mpwem-field">
                                    <span class="mpwem-field__label"><?php esc_html_e('Pricing', 'sccc'); ?></span>
                                    <select name="<?php echo esc_attr($name('pricing_mode')); ?>" class="sccc-vendor-pricing-mode" data-booth-target="#<?php echo esc_attr($boothId); ?>">
                                        <option value="flat" <?php selected($mode, 'flat'); ?>><?php esc_html_e('Flat fee (vendor fee above)', 'sccc'); ?></option>
                                        <option value="booth" <?php selected($mode, 'booth'); ?>><?php esc_html_e('By booth type', 'sccc'); ?></option>
                                    </select>
                                </label>

                                <div id="<?php echo esc_attr($boothId); ?>" <?php echo $mode === 'booth' ? '' : 'style="display:none;"'; ?>>
                                    <p class="description"><?php esc_html_e('Leave a booth type empty to use the vendor fee above.', 'sccc'); ?></p>
                                    <?php foreach (Vendors::boothTypes() as $type => $label) : ?>
                                        <label class="mpwem-field">
                                            <span class="mpwem-field__label"><?php echo esc_html($label); ?> ($)</span>
                                            <input type="number" class="mpwem-input" name="<?php echo esc_attr(self::INPUT.'[booth]['.$type.']'); ?>" value="<?php echo esc_attr($boothPrices[$type] ?? ''); ?>" min="0" step="0.01" />
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <aside class="mpwem-event-wizard__sidebar">
                    <div class="mpwem-card mpwem-card--help">
                        <div class="mpwem-card__head">
                            <h2><?php esc_html_e('How vendors work', 'sccc'); ?></h2>
                        </div>
                        <div class="mpwem-card__body">
                            <p class="description"><?php esc_html_e('Once this show is published and accepting vendors, it appears in the Vendor Sign-up block. New vendors apply and are reviewed; approved and returning vendors pay the fee here to become Active for this show.', 'sccc'); ?></p>
                            <p class="description"><?php esc_html_e('Vendor pages, emails and messages live under Theme Settings → Vendor Settings.', 'sccc'); ?></p>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
        <script>
            (function () {
                document.addEventListener('change', function (event) {
                    var select = event.target;
                    if (!select.classList || !select.classList.contains('sccc-vendor-pricing-mode')) {
                        return;
                    }
                    var target = document.querySelector(select.getAttribute('data-booth-target'));
                    if (target) {
                        target.style.display = select.value === 'booth' ? '' : 'none';
                    }
                });
            })();
        </script>
        <?php
    }

    public static function save($postId = 0): void
    {
        $postId = (int) $postId;

        if (
            $postId <= 0
            || empty($_POST[self::INPUT]['present'])
            || ! isset($_POST[self::NONCE])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE])), self::NONCE)
            || ! current_user_can('edit_post', $postId)
        ) {
            return;
        }

        $input = wp_unslash($_POST[self::INPUT]);
        $input = is_array($input) ? $input : [];

        $accepting = ! empty($input['accepting']);
        self::write($postId, Vendors::EVENT_ACCEPTING, 'field_event_vendors_accepting', $accepting ? '1' : '0');

        // Details are saved whether or not the switch is on, so turning it off and
        // on again keeps the fee and rules.
        $fee = $input['fee'] ?? '';
        self::write($postId, Vendors::EVENT_FEE, 'field_event_vendor_fee_default', is_numeric($fee) ? (string) round(max(0, (float) $fee), 2) : '');

        self::write($postId, Vendors::EVENT_FOOD_ALLOWED, 'field_event_vendors_food_allowed', ! empty($input['food']) ? '1' : '0');

        $deadline = (string) ($input['deadline'] ?? '');
        self::write(
            $postId,
            Vendors::EVENT_DEADLINE,
            'field_event_vendor_application_deadline',
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline) ? str_replace('-', '', $deadline) : ''
        );

        $mode = ($input['pricing_mode'] ?? '') === 'booth' ? 'booth' : 'flat';
        self::write($postId, Vendors::EVENT_PRICING_MODE, 'field_event_vendor_pricing_mode', $mode);

        // Booth price repeater, stored the way ACF stores a repeater.
        $booth = is_array($input['booth'] ?? null) ? $input['booth'] : [];
        $oldCount = (int) get_post_meta($postId, Vendors::EVENT_BOOTH_PRICES, true);
        $i = 0;
        foreach (array_keys(Vendors::boothTypes()) as $type) {
            $price = $booth[$type] ?? '';
            if (! is_numeric($price)) {
                continue;
            }
            $prefix = Vendors::EVENT_BOOTH_PRICES.'_'.$i.'_';
            self::write($postId, $prefix.'booth_type', 'field_event_vendor_booth_type', $type);
            self::write($postId, $prefix.'price', 'field_event_vendor_booth_price', (string) round(max(0, (float) $price), 2));
            $i++;
        }
        for ($j = $i; $j < $oldCount; $j++) {
            foreach (['booth_type', 'price'] as $sub) {
                delete_post_meta($postId, Vendors::EVENT_BOOTH_PRICES.'_'.$j.'_'.$sub);
                delete_post_meta($postId, '_'.Vendors::EVENT_BOOTH_PRICES.'_'.$j.'_'.$sub);
            }
        }
        self::write($postId, Vendors::EVENT_BOOTH_PRICES, 'field_event_vendor_booth_prices', (string) $i);

        // Keep vendor indexes (last show date etc.) in step with this show.
        if (class_exists(VendorPostType::class)) {
            VendorPostType::afterEventSave($postId);
        }
    }

    /**
     * Save a value exactly the way ACF stores it (value under the field name, field
     * key under "_name"), without depending on ACF having the field group loaded
     * in this request. The Classic editor's ACF box reads the same meta.
     */
    private static function write(int $postId, string $name, string $key, string $value): void
    {
        update_post_meta($postId, $name, $value);
        update_post_meta($postId, '_'.$name, $key);
    }
}
