<?php
/**
 * File: app/Fields/EventSignupSettings.php
 *
 * Purpose:
 * -----------------------------------------------------------------------------
 * Registers an ACF-powered admin settings page for the onsite/event signup flow.
 *
 * Why this exists:
 * - The event signup page needs one admin-controlled event source value.
 * - That value should not be accepted blindly from a public URL.
 * - The same page can be reused for different events by changing these settings.
 * - The event name/logo can be updated per event without touching template code.
 *
 * Admin location:
 * -----------------------------------------------------------------------------
 * WP Admin → Settings → Event Signup
 *
 * Settings registered:
 * -----------------------------------------------------------------------------
 * - Enable Event Signup Flow
 * - Active Event Name
 * - Event Label
 * - Event Logo
 * - Signup Source Value
 * - Optional Event Access Key
 * - Completion Message
 * - Return Button Label
 * - Auto Return Seconds
 *
 * Important:
 * -----------------------------------------------------------------------------
 * This file uses the official ACF PHP APIs directly because this is a small,
 * global options page. It is self-booting when required from app/setup.php.
 */

namespace App\Fields;

defined('ABSPATH') || exit;

final class EventSignupSettings
{
    /**
     * ACF options page slug.
     */
    private const PAGE_SLUG = 'sccc-event-signup-settings';

    /**
     * Register ACF hooks.
     */
    public static function boot(): void
    {
        add_action('acf/init', [self::class, 'registerOptionsPage']);
        add_action('acf/init', [self::class, 'registerFieldGroup']);
    }

    /**
     * Add the admin options page.
     */
    public static function registerOptionsPage(): void
    {
        if (! function_exists('acf_add_options_sub_page')) {
            return;
        }

        acf_add_options_sub_page([
            'page_title'  => __('Event Signup Settings', 'sccc'),
            'menu_title'  => __('Event Signup', 'sccc'),
            'menu_slug'   => self::PAGE_SLUG,
            'parent_slug' => 'options-general.php',
            'capability'  => 'manage_options',
            'redirect'    => false,
            'post_id'     => 'option',
        ]);
    }

    /**
     * Register fields for the options page.
     */
    public static function registerFieldGroup(): void
    {
        if (! function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key'                   => 'group_sccc_event_signup_settings',
            'title'                 => __('Event Signup Settings', 'sccc'),
            'fields'                => [
                [
                    'key'           => 'field_sccc_event_signup_enabled',
                    'label'         => __('Enable Event Signup Flow', 'sccc'),
                    'name'          => 'sccc_event_signup_enabled',
                    'type'          => 'true_false',
                    'instructions'  => __('Turn this off when the event signup terminal flow should not be available.', 'sccc'),
                    'required'      => 0,
                    'ui'            => 1,
                    'default_value' => 1,
                ],
                [
                    'key'           => 'field_sccc_event_signup_event_name',
                    'label'         => __('Active Event Name', 'sccc'),
                    'name'          => 'sccc_event_signup_event_name',
                    'type'          => 'text',
                    'instructions'  => __('Human-readable event name shown on the event signup and completion screens.', 'sccc'),
                    'required'      => 0,
                    'default_value' => 'Space City Car Club Event Signup',
                    'placeholder'   => 'Lone Star Motor Mania 2026',
                ],
                [
                    'key'           => 'field_sccc_event_signup_event_label',
                    'label'         => __('Event Label', 'sccc'),
                    'name'          => 'sccc_event_signup_event_label',
                    'type'          => 'text',
                    'instructions'  => __('Small uppercase label shown above the active event name in the event spotlight area.', 'sccc'),
                    'required'      => 0,
                    'default_value' => 'Current Event',
                    'placeholder'   => 'Featured Event',
                ],
                [
                    'key'           => 'field_sccc_event_signup_event_logo',
                    'label'         => __('Event Logo', 'sccc'),
                    'name'          => 'sccc_event_signup_event_logo',
                    'type'          => 'image',
                    'instructions'  => __('Optional. Upload an event-specific logo for the event signup spotlight. If empty, the page will show a simple event icon instead.', 'sccc'),
                    'required'      => 0,
                    'return_format' => 'array',
                    'preview_size'  => 'medium',
                    'library'       => 'all',
                    'mime_types'    => 'jpg,jpeg,png,webp,svg',
                ],
                [
                    'key'           => 'field_sccc_event_signup_source',
                    'label'         => __('Signup Source Value', 'sccc'),
                    'name'          => 'sccc_event_signup_source',
                    'type'          => 'text',
                    'instructions'  => __('Saved into the existing Referral Source system for members who join through the event signup flow.', 'sccc'),
                    'required'      => 0,
                    'default_value' => 'event_terminal_signup',
                    'placeholder'   => 'lsmm_2026_tablet_signup',
                ],
                [
                    'key'           => 'field_sccc_event_signup_access_key',
                    'label'         => __('Optional Event Access Key', 'sccc'),
                    'name'          => 'sccc_event_signup_access_key',
                    'type'          => 'text',
                    'instructions'  => __('Optional. If set, the event signup URL must include ?event_key=YOUR_KEY. Leave blank to allow /event-signup/ without a key.', 'sccc'),
                    'required'      => 0,
                    'default_value' => '',
                    'placeholder'   => '7x9k-lsmm-2026',
                ],
                [
                    'key'           => 'field_sccc_event_signup_completion_message',
                    'label'         => __('Completion Message', 'sccc'),
                    'name'          => 'sccc_event_signup_completion_message',
                    'type'          => 'textarea',
                    'instructions'  => __('Shown after successful signup/payment. Keep this short and clear for tablet use.', 'sccc'),
                    'required'      => 0,
                    'rows'          => 5,
                    'new_lines'     => 'br',
                    'default_value' => 'Thank you for joining Space City Car Club. Your signup was completed successfully. Please check your email for login instructions and next steps. For privacy, this terminal has been reset and you are not logged in on this device.',
                ],
                [
                    'key'           => 'field_sccc_event_signup_return_button_label',
                    'label'         => __('Return Button Label', 'sccc'),
                    'name'          => 'sccc_event_signup_return_button_label',
                    'type'          => 'text',
                    'instructions'  => __('Button text shown on the completion screen.', 'sccc'),
                    'required'      => 0,
                    'default_value' => 'Start Next Signup',
                ],
                [
                    'key'           => 'field_sccc_event_signup_auto_return_seconds',
                    'label'         => __('Auto Return Seconds', 'sccc'),
                    'name'          => 'sccc_event_signup_auto_return_seconds',
                    'type'          => 'number',
                    'instructions'  => __('How many seconds the completion page waits before returning to the signup screen. Use 0 to disable auto-return.', 'sccc'),
                    'required'      => 0,
                    'default_value' => 12,
                    'min'           => 0,
                    'max'           => 60,
                    'step'          => 1,
                ],
            ],
            'location'              => [
                [
                    [
                        'param'    => 'options_page',
                        'operator' => '==',
                        'value'    => self::PAGE_SLUG,
                    ],
                ],
            ],
            'menu_order'            => 0,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen'        => '',
            'active'                => true,
            'description'           => __('Settings for the onsite/event signup terminal flow.', 'sccc'),
            'show_in_rest'          => 0,
        ]);
    }
}

EventSignupSettings::boot();