<?php

/**
 * File path + filename: app/Support/Forms/GlassForms.php
 *
 * Purpose:
 * - One shared "neon glass" look for every form and button the vendor and car
 *   show features print: Car Registration, Vendor Application, Returning Vendor,
 *   Vendor Sign-up & Payment, the car "Add another car" buttons, the vendor
 *   card and the Vendor Sign-up block choices.
 * - Matches the theme's button system (resources/css/components/buttons.css):
 *   glass fill + iridescent gradient border (cyan → blue → violet) via the
 *   padding-box / border-box background technique, bloom on hover, brighter
 *   border + ring on focus. Uses the same --btn-* tokens, so light/dark mode
 *   follow the theme automatically.
 *
 * How it is loaded:
 * - Printed once per page, in front of the first of our Gravity Forms
 *   (gform_get_form_filter), or by our own markup via GlassForms::print().
 * - Inline on purpose: no asset rebuild needed, and it only loads on pages that
 *   show one of these forms.
 */

namespace App\Support\Forms;

defined('ABSPATH') || exit;

final class GlassForms
{
    /** Gravity Form CSS classes that get the glass look. */
    public const FORM_CLASSES = [
        'sccc-car-registration',
        'sccc-vendor-application',
        'sccc-vendor-returning',
        'sccc-vendor-payment',
    ];

    private static bool $printed = false;

    public static function register(): void
    {
        add_filter('gform_get_form_filter', [self::class, 'prependCss'], 5, 2);
    }

    /**
     * @param  array<string, mixed>|mixed  $form
     */
    public static function prependCss(string $html, $form): string
    {
        if (self::$printed || ! is_array($form)) {
            return $html;
        }

        $classes = preg_split('/\s+/', (string) ($form['cssClass'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (! array_intersect($classes, self::FORM_CLASSES)) {
            return $html;
        }

        return self::css().$html;
    }

    /** Print the stylesheet once (for our own markup outside a form). */
    public static function print(): void
    {
        echo self::css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function css(): string
    {
        if (self::$printed) {
            return '';
        }

        self::$printed = true;

        $wrappers = implode(', ', array_map(static fn (string $c): string => '.gform_wrapper.'.$c.'_wrapper', self::FORM_CLASSES));
        $W = ':is('.$wrappers.')';

        $fields = $W.' :is(input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="file"]):not([type="image"]), select, textarea)';
        $buttons = $W.' :is(.gform_button, .gform_next_button, .gform_previous_button, .gform_save_link, button[type="submit"], input[type="submit"], input[type="button"])';
        $ours = ':is(.sccc-car-actions button, .sccc-carshow__button, .sccc-vendor-button, .sccc-vendor-signup__choice)';

        $chevron = "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%239da6b9'%3E%3Cpath d='M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4z'/%3E%3C/svg%3E\")";

        $css = <<<CSS
/* ---------- Glass forms (vendor + car show) ---------- */
{$W} {
  --gf-glass-fill: var(--btn-glass-fill, rgba(10, 18, 30, .68));
  --gf-glass-fill-focus: var(--btn-glass-fill-hover, rgba(10, 18, 30, .82));
  --gf-neon: linear-gradient(135deg, var(--btn-neon-from, rgba(0,210,255,.5)), var(--btn-neon-mid, rgba(19,100,236,.45)), var(--btn-neon-to, rgba(160,92,255,.5)));
  --gf-neon-hover: linear-gradient(135deg, var(--btn-neon-from-hover, rgba(0,210,255,.85)), var(--btn-neon-mid-hover, rgba(19,100,236,.8)), var(--btn-neon-to-hover, rgba(160,92,255,.85)));
  --gf-neon-active: linear-gradient(135deg, var(--btn-neon-from-active, #00d2ff), var(--btn-neon-mid-active, #1364ec), var(--btn-neon-to-active, #a05cff));
  --gf-text: var(--color-input-text, var(--color-text, #fff));
  --gf-muted: var(--color-muted, #9da6b9);
  color: var(--color-text, #fff);
}

/* Labels, descriptions, sections */
{$W} :is(.gfield_label, legend.gfield_label, .gform-field-label:not(.gform-field-label--type-inline):not(.gform-field-label--type-sub)) {
  color: var(--color-text, #fff) !important; font-weight: 700 !important; font-size: .95rem; margin-bottom: .45rem;
}
{$W} :is(.gfield_description, .gform-field-label--type-sub, .ginput_counter, .gsection_description, .gform_required_legend) {
  color: var(--gf-muted) !important; font-size: .85rem;
}
{$W} .gfield_required { color: #f87171 !important; }
{$W} .gsection { border-bottom: 0 !important; padding-bottom: .6rem !important; margin-top: .75rem; position: relative; }
{$W} .gsection::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 1px; background: var(--gf-neon); opacity: .7; }
{$W} .gsection_title { color: var(--color-text, #fff) !important; font-size: 1.2rem !important; font-weight: 700 !important; }

/* Text inputs, selects, textareas: glass fill + iridescent border */
{$fields} {
  min-height: 3rem;
  width: 100%;
  padding: .75rem 1rem !important;
  border: 1.5px solid transparent !important;
  border-radius: .9rem !important;
  background: linear-gradient(var(--gf-glass-fill), var(--gf-glass-fill)) padding-box, var(--gf-neon) border-box !important;
  color: var(--gf-text) !important;
  font-size: 1rem !important;
  line-height: 1.4;
  box-shadow: var(--btn-bloom-default, 0 0 6px rgba(0,210,255,.2), 0 0 12px rgba(160,92,255,.14)) !important;
  -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
  transition: box-shadow .2s ease, background .2s ease;
  outline: none !important;
}
{$W} textarea { min-height: 7rem; resize: vertical; }
{$W} select {
  -webkit-appearance: none !important; appearance: none !important;
  padding-right: 2.75rem !important;
  background: {$chevron} no-repeat right 1rem center / 1.1rem 1.1rem, linear-gradient(var(--gf-glass-fill), var(--gf-glass-fill)) padding-box, var(--gf-neon) border-box !important;
}
{$W} select option { background: var(--color-surface, #1a2336); color: var(--color-text, #fff); }
{$W} :is(input, textarea)::placeholder { color: var(--color-input-placeholder, #64748b) !important; opacity: 1; }
{$fields}:hover {
  background: linear-gradient(var(--gf-glass-fill), var(--gf-glass-fill)) padding-box, var(--gf-neon-hover) border-box !important;
}
{$W} select:hover {
  background: {$chevron} no-repeat right 1rem center / 1.1rem 1.1rem, linear-gradient(var(--gf-glass-fill), var(--gf-glass-fill)) padding-box, var(--gf-neon-hover) border-box !important;
}
{$fields}:focus {
  background: linear-gradient(var(--gf-glass-fill-focus), var(--gf-glass-fill-focus)) padding-box, var(--gf-neon-active) border-box !important;
  box-shadow: var(--btn-bloom-hover, 0 0 10px rgba(0,210,255,.45), 0 0 20px rgba(160,92,255,.3)), 0 0 0 3px color-mix(in srgb, var(--color-primary-500, #135bec) 25%, transparent) !important;
}
{$W} select:focus {
  background: {$chevron} no-repeat right 1rem center / 1.1rem 1.1rem, linear-gradient(var(--gf-glass-fill-focus), var(--gf-glass-fill-focus)) padding-box, var(--gf-neon-active) border-box !important;
}
{$W} :is(input[readonly], .ginput_total) { cursor: default; font-weight: 700; }
{$W} .gfield_error :is(input, select, textarea) {
  background: linear-gradient(var(--gf-glass-fill), var(--gf-glass-fill)) padding-box, linear-gradient(135deg, #f87171, #ef4444) border-box !important;
}

/* Radios, checkboxes, consent */
{$W} :is(input[type="checkbox"], input[type="radio"]) { accent-color: var(--color-primary-500, #135bec); width: 1.15rem; height: 1.15rem; }
{$W} .gchoice label, {$W} .ginput_container_consent label { color: var(--color-text, #fff) !important; }

/* File uploads */
{$W} input[type="file"] { color: var(--gf-muted); }
{$W} input[type="file"]::file-selector-button {
  margin-right: .9rem; padding: .6rem 1.2rem; border-radius: 9999px; cursor: pointer; font-weight: 700;
  border: 1.5px solid transparent; color: var(--color-text, #fff);
  background: linear-gradient(var(--gf-glass-fill), var(--gf-glass-fill)) padding-box, var(--gf-neon) border-box;
}

/* Buttons (submit, next, previous) + our own buttons */
{$buttons}, {$ours} {
  display: inline-flex !important; align-items: center; justify-content: center; gap: .5rem;
  width: auto !important; height: auto !important; min-width: 0 !important; aspect-ratio: auto !important;
  min-height: 44px; padding: .7rem 1.6rem !important;
  border: 1.5px solid transparent !important; border-radius: 9999px !important;
  background: linear-gradient(var(--btn-glass-fill, rgba(10,18,30,.68)), var(--btn-glass-fill, rgba(10,18,30,.68))) padding-box, linear-gradient(135deg, var(--btn-neon-from, rgba(0,210,255,.5)), var(--btn-neon-mid, rgba(19,100,236,.45)), var(--btn-neon-to, rgba(160,92,255,.5))) border-box !important;
  color: var(--color-text, #fff) !important; font-weight: 700 !important; font-size: .95rem !important; line-height: 1.2 !important;
  white-space: nowrap; text-decoration: none !important; cursor: pointer;
  box-shadow: var(--btn-bloom-default, 0 0 6px rgba(0,210,255,.2), 0 0 12px rgba(160,92,255,.14)) !important;
  -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
  transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
}
{$buttons}:hover, {$ours}:hover {
  background: linear-gradient(var(--btn-glass-fill-hover, rgba(10,18,30,.82)), var(--btn-glass-fill-hover, rgba(10,18,30,.82))) padding-box, linear-gradient(135deg, var(--btn-neon-from-hover, rgba(0,210,255,.85)), var(--btn-neon-mid-hover, rgba(19,100,236,.8)), var(--btn-neon-to-hover, rgba(160,92,255,.85))) border-box !important;
  box-shadow: var(--btn-bloom-hover, 0 0 10px rgba(0,210,255,.45), 0 0 20px rgba(160,92,255,.3)) !important;
  transform: translateY(-2px);
}
{$buttons}:active, {$ours}:active {
  transform: translateY(1px) scale(.98);
  background: linear-gradient(var(--btn-glass-fill-active, rgba(0,14,32,.92)), var(--btn-glass-fill-active, rgba(0,14,32,.92))) padding-box, linear-gradient(135deg, var(--btn-neon-from-active, #00d2ff), var(--btn-neon-mid-active, #1364ec), var(--btn-neon-to-active, #a05cff)) border-box !important;
  box-shadow: var(--btn-bloom-active, 0 0 18px rgba(0,210,255,.5)) !important;
}
{$buttons}:focus-visible, {$ours}:focus-visible {
  outline: none;
  box-shadow: var(--btn-bloom-default, 0 0 6px rgba(0,210,255,.2)), 0 0 0 4px var(--btn-primary-ring, rgba(19,91,236,.45)) !important;
}
{$ours}[disabled], {$buttons}[disabled] { opacity: .45; cursor: not-allowed; transform: none !important; }
/* Primary action: submit / pay / next gets the stronger border */
{$W} :is(.gform_button, .gform_next_button, button[type="submit"], input[type="submit"]) {
  background: linear-gradient(var(--btn-glass-fill, rgba(10,18,30,.68)), var(--btn-glass-fill, rgba(10,18,30,.68))) padding-box, linear-gradient(135deg, var(--btn-neon-from-hover, rgba(0,210,255,.85)), var(--btn-neon-mid-hover, rgba(19,100,236,.8)), var(--btn-neon-to-hover, rgba(160,92,255,.85))) border-box !important;
}
{$W} :is(.gform_footer, .gform_page_footer) { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.5rem !important; }

/* Multi-page progress steps */
{$W} .gf_page_steps { border-bottom: 0 !important; position: relative; padding-bottom: 1rem; margin-bottom: 1.5rem; }
{$W} .gf_page_steps::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 1px; background: var(--gf-neon); opacity: .7; }
{$W} .gf_step_number {
  border: 1.5px solid transparent !important;
  background: linear-gradient(var(--gf-glass-fill), var(--gf-glass-fill)) padding-box, var(--gf-neon) border-box !important;
  color: var(--gf-muted) !important;
}
{$W} .gf_step_active .gf_step_number, {$W} .gf_step_completed .gf_step_number {
  background: var(--gf-neon-active) !important; color: #fff !important; box-shadow: var(--btn-bloom-hover, 0 0 12px rgba(0,210,255,.4));
}
{$W} .gf_step_label { color: var(--color-text, #fff) !important; }
{$W} .gf_step_pending .gf_step_label { color: var(--gf-muted) !important; }

/* Validation */
{$W} :is(.gfield_validation_message, .validation_message) {
  color: #fecaca !important; background: rgba(239,68,68,.12) !important;
  border: 1px solid rgba(248,113,113,.55) !important; border-radius: .6rem !important; padding: .6rem .9rem !important; margin-top: .5rem;
}
{$W} .gform_validation_errors {
  background: rgba(239,68,68,.12) !important; border: 1px solid rgba(248,113,113,.55) !important; border-radius: .9rem !important; box-shadow: none !important;
}
{$W} .gform_validation_errors :is(h2, .gform_submission_error, li, a) { color: #fecaca !important; }

/* Stripe card field */
{$W} .gfield--type-stripe_creditcard .StripeElement {
  display: block; min-height: 3rem; padding: .9rem 1rem; box-sizing: border-box;
  border: 1.5px solid transparent; border-radius: .9rem;
  background: linear-gradient(var(--gf-glass-fill), var(--gf-glass-fill)) padding-box, var(--gf-neon) border-box;
  box-shadow: var(--btn-bloom-default, 0 0 6px rgba(0,210,255,.2));
}
{$W} .gfield--type-stripe_creditcard .StripeElement--focus {
  background: linear-gradient(var(--gf-glass-fill-focus), var(--gf-glass-fill-focus)) padding-box, var(--gf-neon-active) border-box;
  box-shadow: var(--btn-bloom-hover, 0 0 10px rgba(0,210,255,.45));
}
{$W} .gfield--type-stripe_creditcard .StripeElement--invalid { background: linear-gradient(var(--gf-glass-fill), var(--gf-glass-fill)) padding-box, linear-gradient(135deg, #f87171, #ef4444) border-box; }
{$W} .gfield--type-stripe_creditcard .gfield_validation_message a { text-decoration: underline; font-weight: 600; }
{$W} .gfield--type-stripe_creditcard .ginput_full + .ginput_full { margin-top: .75rem; }

/* Our small helpers */
.sccc-car-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; margin: .75rem 0 1.25rem; }
.sccc-car-actions__count { font-weight: 700; color: var(--color-muted, #9da6b9); }
/* Vendor Sign-up choices: rectangular cards, not pills */
button.sccc-vendor-signup__choice.sccc-vendor-signup__choice {
  flex-direction: column; align-items: flex-start !important; justify-content: flex-start !important; gap: .35rem;
  width: 100% !important; min-height: 5.5rem; white-space: normal !important; text-align: left;
  border-radius: .9rem !important; padding: 1.1rem 1.35rem !important;
}
button.sccc-vendor-signup__choice.sccc-vendor-signup__choice:hover { transform: translateY(-2px); }
button.sccc-vendor-signup__choice.sccc-vendor-signup__choice[aria-pressed="true"] {
  background: linear-gradient(var(--btn-glass-fill-active, rgba(0,14,32,.92)), var(--btn-glass-fill-active, rgba(0,14,32,.92))) padding-box, linear-gradient(135deg, var(--btn-neon-from-active, #00d2ff), var(--btn-neon-mid-active, #1364ec), var(--btn-neon-to-active, #a05cff)) border-box !important;
  box-shadow: var(--btn-bloom-hover, 0 0 12px rgba(0,210,255,.4)) !important;
}
CSS;

        return '<style id="sccc-glass-forms">'.$css.'</style>';
    }
}
