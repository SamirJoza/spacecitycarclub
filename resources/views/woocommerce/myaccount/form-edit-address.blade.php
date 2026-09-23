<?php
/**
 * File: resources/views/woocommerce/myaccount/form-edit-address.blade.php
 *
 * Custom WooCommerce My Account address edit form for:
 * - /my-account/edit-address/billing/
 * - /my-account/edit-address/shipping/
 *
 * Notes:
 * - Plain PHP + HTML on purpose, even though this is a .blade.php file.
 * - No Blade layout directives in this file.
 * - Country / state fields are enhanced with SelectWoo.
 * - Dark mode is driven by: <html class="dark" data-theme="dark">
 * - Light mode is driven by: <html data-theme="light">
 */

defined('ABSPATH') || exit;

/**
 * --------------------------------------------------------------------------
 * Ensure WooCommerce scripts are loaded on this screen
 * --------------------------------------------------------------------------
 */
if (wp_script_is('selectWoo', 'registered') && ! wp_script_is('selectWoo', 'enqueued')) {
    wp_enqueue_script('selectWoo');
}

if (wp_style_is('select2', 'registered') && ! wp_style_is('select2', 'enqueued')) {
    wp_enqueue_style('select2');
}

if (wp_script_is('wc-country-select', 'registered') && ! wp_script_is('wc-country-select', 'enqueued')) {
    wp_enqueue_script('wc-country-select');
}

if (wp_script_is('wc-address-i18n', 'registered') && ! wp_script_is('wc-address-i18n', 'enqueued')) {
    wp_enqueue_script('wc-address-i18n');
}

/**
 * --------------------------------------------------------------------------
 * SelectWoo init
 * --------------------------------------------------------------------------
 */
if (wp_script_is('wc-address-i18n', 'registered')) {
    wp_add_inline_script(
        'wc-address-i18n',
        <<<'JS'
jQuery(function ($) {
  var $form = $('.sccc-account-address');

  if (!$form.length || typeof $.fn.selectWoo !== 'function') {
    return;
  }

  function enhanceAddressSelects(scope) {
    $(scope).find('select.country_select, select.state_select').each(function () {
      var $select = $(this);
      var $shell = $select.closest('.sccc-select-shell');
      var $dropdownParent = $shell.length ? $shell : $form;

      if ($select.hasClass('select2-hidden-accessible')) {
        $select.selectWoo('destroy');
      }

      if ($shell.length) {
        $shell.addClass('sccc-select-enhanced');
      }

      $select.selectWoo({
        width: '100%',
        dropdownAutoWidth: false,
        minimumResultsForSearch: 8,
        placeholder: $select.data('placeholder') || '',
        dropdownParent: $dropdownParent
      });
    });
  }

  enhanceAddressSelects($form);

  $(document.body).on('country_to_state_changed updated_wc_div init', function () {
    enhanceAddressSelects($form);
  });
});
JS,
        'after'
    );
}

/**
 * --------------------------------------------------------------------------
 * Page title / button class
 * --------------------------------------------------------------------------
 */
$page_title = ('billing' === $load_address)
    ? esc_html__('Billing address', 'woocommerce')
    : esc_html__('Shipping address', 'woocommerce');

$button_element_class = function_exists('wc_wp_theme_get_element_class_name')
    ? wc_wp_theme_get_element_class_name('button')
    : '';

/**
 * --------------------------------------------------------------------------
 * Small helper: safely compile HTML attributes
 * --------------------------------------------------------------------------
 */
$build_attrs = static function ($attributes) {
    $compiled = [];

    foreach ((array) $attributes as $name => $value) {
        if ($value === null || $value === false || $value === '') {
            continue;
        }

        if ($value === true) {
            $compiled[] = esc_attr($name);
            continue;
        }

        if (is_array($value)) {
            $value = implode(' ', array_filter($value));
        }

        $compiled[] = sprintf('%s="%s"', esc_attr($name), esc_attr((string) $value));
    }

    return implode(' ', $compiled);
};

/**
 * --------------------------------------------------------------------------
 * Inline fallback styles
 * --------------------------------------------------------------------------
 */
$shell_style = implode('; ', [
    'display:block',
    'position:relative',
    'width:100%',
    'padding:0.95rem 1rem',
    'border:1px solid var(--sccc-field-border, rgba(255,255,255,0.18))',
    'border-radius:1rem',
    'background:var(--sccc-field-bg, linear-gradient(180deg, rgba(13,20,44,0.96) 0%, rgba(10,15,35,0.98) 100%))',
    'box-shadow:inset 0 1px 0 var(--sccc-field-shine, rgba(255,255,255,0.03)), 0 0 0 1px var(--sccc-field-outline, rgba(255,255,255,0.02))',
    'transition:border-color .18s ease, box-shadow .18s ease, background-color .18s ease',
]) . ';';

$control_style = implode('; ', [
    'display:block',
    'width:100%',
    'min-height:1.5rem',
    'margin:0',
    'padding:0',
    'border:0 !important',
    'background:transparent !important',
    'background-color:transparent !important',
    'box-shadow:none !important',
    'outline:none',
    'color:var(--sccc-field-text, #eef3ff) !important',
    '-webkit-text-fill-color:var(--sccc-field-text, #eef3ff)',
    'font:inherit',
    'font-size:1rem',
    'line-height:1.35',
    'letter-spacing:0',
    '-webkit-appearance:none',
    'appearance:none',
]) . ';';

$textarea_style = $control_style . 'min-height:7rem; resize:vertical;';
$select_style   = $control_style . 'padding-right:2rem; cursor:pointer; color-scheme:dark;';
$submit_style   = implode('; ', [
    'display:inline-flex !important',
    'align-items:center !important',
    'justify-content:center !important',
    'min-height:3rem !important',
    'padding:0.85rem 1.3rem !important',
    'border:1px solid var(--sccc-submit-border, rgba(113,215,255,0.18)) !important',
    'border-radius:9999px !important',
    'background:var(--sccc-submit-bg, linear-gradient(180deg, #71d7ff 0%, #53c5f5 100%)) !important',
    'color:var(--sccc-submit-text, #081221) !important',
    '-webkit-text-fill-color:var(--sccc-submit-text, #081221)',
    'box-shadow:0 14px 34px var(--sccc-submit-shadow, rgba(83,197,245,0.18)) !important',
    'font:inherit !important',
    'font-size:0.86rem !important',
    'font-weight:700 !important',
    'line-height:1 !important',
    'letter-spacing:0.01em !important',
    'text-decoration:none !important',
    'cursor:pointer !important',
    'appearance:none !important',
    '-webkit-appearance:none !important',
    'transition:transform .18s ease, box-shadow .18s ease, filter .18s ease',
]) . ';';

/**
 * --------------------------------------------------------------------------
 * Scoped CSS
 * --------------------------------------------------------------------------
 */
echo <<<'CSS'
<style>
/* ==========================================================================
   SCCC Account Address
   Cleaned / consolidated drop-in stylesheet
   - Removes duplicate arrow, button, action, and mobile blocks
   - Removes custom shell arrow to prevent double-arrow issues
   - Keeps one SelectWoo arrow system only
   - Moves save button theme styling into variables
   ========================================================================== */

/* ==========================================================================
   Theme tokens: dark
   ========================================================================== */
   html.dark .sccc-account-address {
  --sccc-card-border: rgba(255, 255, 255, 0.08);
  --sccc-card-bg: linear-gradient(180deg, rgba(6, 12, 31, 0.96) 0%, rgba(3, 8, 24, 0.98) 100%);
  --sccc-divider: rgba(255, 255, 255, 0.07);
  --sccc-eyebrow: rgba(101, 210, 255, 0.92);
  --sccc-title: #f5f8ff;
  --sccc-copy: rgba(230, 236, 249, 0.72);
  --sccc-label: rgba(228, 235, 248, 0.9);
  --sccc-required: #ff5d77;

  --sccc-backlink-border: rgba(255, 255, 255, 0.11);
  --sccc-backlink-bg: rgba(255, 255, 255, 0.04);
  --sccc-backlink-text: #edf3ff;
  --sccc-backlink-hover-border: rgba(101, 210, 255, 0.36);
  --sccc-backlink-hover-bg: rgba(101, 210, 255, 0.1);
  --sccc-backlink-ring: rgba(101, 210, 255, 0.12);

  --sccc-field-bg: linear-gradient(180deg, rgba(13, 20, 44, 0.96) 0%, rgba(10, 15, 35, 0.98) 100%);
  --sccc-field-border: rgba(255, 255, 255, 0.18);
  --sccc-field-text: #eef3ff;
  --sccc-field-placeholder: rgba(221, 229, 243, 0.46);
  --sccc-field-shine: rgba(255, 255, 255, 0.03);
  --sccc-field-outline: rgba(255, 255, 255, 0.02);
  --sccc-field-hover-border: rgba(101, 210, 255, 0.26);
  --sccc-field-focus-border: rgba(101, 210, 255, 0.52);
  --sccc-field-focus-ring: rgba(101, 210, 255, 0.13);

  --sccc-select-dropdown-bg: #0d142c;
  --sccc-select-dropdown-border: rgba(255, 255, 255, 0.18);
  --sccc-select-search-bg: #081128;
  --sccc-select-option-bg-selected: rgba(255, 255, 255, 0.04);
  --sccc-select-option-bg-highlight: rgba(101, 210, 255, 0.18);
  --sccc-select-option-text-highlight: #ffffff;

  --sccc-submit-border: rgba(101, 210, 255, 0.26);
  --sccc-submit-bg: linear-gradient(180deg, rgba(16, 31, 66, 0.98) 0%, rgba(9, 18, 42, 0.98) 100%);
  --sccc-submit-text: #eef3ff;
  --sccc-submit-shadow:
    0 14px 34px rgba(0, 0, 0, 0.28),
    inset 0 1px 0 rgba(255, 255, 255, 0.06),
    0 0 0 1px rgba(101, 210, 255, 0.06);
  --sccc-submit-dot-bg: linear-gradient(180deg, #8de6ff 0%, #53c5f5 100%);
  --sccc-submit-dot-shadow:
    0 0 0 3px rgba(83, 197, 245, 0.12),
    0 0 18px rgba(83, 197, 245, 0.28);
  --sccc-submit-hover-border: rgba(101, 210, 255, 0.4);
  --sccc-submit-hover-shadow:
    0 18px 38px rgba(0, 0, 0, 0.34),
    inset 0 1px 0 rgba(255, 255, 255, 0.08),
    0 0 0 4px rgba(101, 210, 255, 0.12);
}

/* ==========================================================================
   Theme tokens: light
   ========================================================================== */
html[data-theme="light"] .sccc-account-address {
  --sccc-card-border: rgba(15, 23, 42, 0.08);
  --sccc-card-bg: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(246, 249, 253, 0.98) 100%);
  --sccc-divider: rgba(15, 23, 42, 0.08);
  --sccc-eyebrow: #0f83b6;
  --sccc-title: #0e1728;
  --sccc-copy: rgba(19, 32, 51, 0.72);
  --sccc-label: rgba(19, 32, 51, 0.88);
  --sccc-required: #e04761;

  --sccc-backlink-border: rgba(15, 23, 42, 0.12);
  --sccc-backlink-bg: rgba(255, 255, 255, 0.72);
  --sccc-backlink-text: #122135;
  --sccc-backlink-hover-border: rgba(15, 131, 182, 0.26);
  --sccc-backlink-hover-bg: rgba(15, 131, 182, 0.08);
  --sccc-backlink-ring: rgba(15, 131, 182, 0.08);

  --sccc-field-bg: linear-gradient(180deg, rgba(250, 252, 255, 0.98) 0%, rgba(244, 248, 252, 0.98) 100%);
  --sccc-field-border: rgba(15, 23, 42, 0.12);
  --sccc-field-text: #132033;
  --sccc-field-placeholder: rgba(19, 32, 51, 0.42);
  --sccc-field-shine: rgba(255, 255, 255, 0.7);
  --sccc-field-outline: rgba(15, 23, 42, 0.02);
  --sccc-field-hover-border: rgba(15, 131, 182, 0.24);
  --sccc-field-focus-border: rgba(15, 131, 182, 0.45);
  --sccc-field-focus-ring: rgba(15, 131, 182, 0.1);

  --sccc-select-dropdown-bg: #f8fbff;
  --sccc-select-dropdown-border: rgba(15, 23, 42, 0.12);
  --sccc-select-search-bg: #ffffff;
  --sccc-select-option-bg-selected: rgba(15, 23, 42, 0.04);
  --sccc-select-option-bg-highlight: rgba(15, 131, 182, 0.12);
  --sccc-select-option-text-highlight: #10233f;

  --sccc-submit-border: rgba(15, 131, 182, 0.22);
  --sccc-submit-bg: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(240, 247, 253, 0.98) 100%);
  --sccc-submit-text: #10233f;
  --sccc-submit-shadow:
    0 12px 28px rgba(15, 23, 42, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.9),
    0 0 0 1px rgba(15, 131, 182, 0.04);
  --sccc-submit-dot-bg: linear-gradient(180deg, #2bb3e8 0%, #0f83b6 100%);
  --sccc-submit-dot-shadow:
    0 0 0 3px rgba(15, 131, 182, 0.1),
    0 0 12px rgba(15, 131, 182, 0.12);
  --sccc-submit-hover-border: rgba(15, 131, 182, 0.36);
  --sccc-submit-hover-shadow:
    0 16px 34px rgba(15, 23, 42, 0.14),
    inset 0 1px 0 rgba(255, 255, 255, 1),
    0 0 0 4px rgba(15, 131, 182, 0.1);
}

/* ==========================================================================
   Component shell
   ========================================================================== */
.sccc-account-address {
  width: 100%;
  max-width: 100%;
}

.sccc-account-address__card {
  overflow: hidden;
  border: 1px solid var(--sccc-card-border, rgba(255, 255, 255, 0.08));
  border-radius: 1.25rem;
  background: var(--sccc-card-bg, linear-gradient(180deg, rgba(6, 12, 31, 0.96) 0%, rgba(3, 8, 24, 0.98) 100%));
  box-shadow:
    0 24px 60px rgba(0, 0, 0, 0.2),
    inset 0 1px 0 rgba(255, 255, 255, 0.02);
}

.sccc-account-address__inner {
  padding: 1.25rem;
}

@media (min-width: 768px) {
  .sccc-account-address__inner {
    padding: 2rem;
  }
}

/* ==========================================================================
   Header
   ========================================================================== */
.sccc-account-address__header {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  margin-bottom: 2rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid var(--sccc-divider, rgba(255, 255, 255, 0.07));
}

.sccc-account-address__eyebrow {
  margin: 0 0 0.5rem;
  color: var(--sccc-eyebrow, rgba(101, 210, 255, 0.92));
  font-size: 0.64rem;
  font-weight: 700;
  line-height: 1;
  letter-spacing: 0.22em;
  text-transform: uppercase;
}

.sccc-account-address__title {
  margin: 0;
  color: var(--sccc-title, #f5f8ff);
  font-family: var(--font-display, inherit);
  font-size: clamp(2.25rem, 4vw, 3.5rem);
  font-weight: 700;
  line-height: 0.98;
  letter-spacing: -0.03em;
}

.sccc-account-address__intro {
  max-width: 44rem;
  margin: 0.75rem 0 0;
  color: var(--sccc-copy, rgba(230, 236, 249, 0.72));
  font-size: 0.95rem;
  line-height: 1.6;
}

/* ==========================================================================
   Back link
   ========================================================================== */
.sccc-account-address__backlink {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  align-self: flex-start;
  min-height: 2.25rem;
  padding: 0.45rem 0.85rem;
  border: 1px solid var(--sccc-backlink-border, rgba(255, 255, 255, 0.11));
  border-radius: 9999px;
  background: var(--sccc-backlink-bg, rgba(255, 255, 255, 0.04));
  color: var(--sccc-backlink-text, #edf3ff);
  text-decoration: none;
  font-size: 0.8rem;
  font-weight: 700;
  line-height: 1;
  transition:
    background-color 160ms ease,
    border-color 160ms ease,
    color 160ms ease,
    box-shadow 160ms ease,
    transform 160ms ease;
}

.sccc-account-address__backlink:hover,
.sccc-account-address__backlink:focus-visible {
  border-color: var(--sccc-backlink-hover-border, rgba(101, 210, 255, 0.36));
  background: var(--sccc-backlink-hover-bg, rgba(101, 210, 255, 0.1));
  text-decoration: none;
  box-shadow: 0 0 0 4px var(--sccc-backlink-ring, rgba(101, 210, 255, 0.12));
  transform: translateY(-1px);
  outline: none;
}

/* ==========================================================================
   Form layout
   ========================================================================== */
.sccc-account-address .woocommerce-address-fields__field-wrapper {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1.25rem 1.5rem;
}

.sccc-account-address__row {
  margin: 0 !important;
  width: 100%;
  float: none !important;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.sccc-account-address__row.form-row-wide,
.sccc-account-address__row.woocommerce-form-row--wide,
.sccc-account-address__row--wide {
  grid-column: 1 / -1;
}

.sccc-account-address__label {
  display: block;
  margin: 0;
  color: var(--sccc-label, rgba(228, 235, 248, 0.9));
  font-size: 0.9rem;
  font-weight: 600;
  line-height: 1.35;
  letter-spacing: -0.01em;
}

.sccc-account-address .required {
  color: var(--sccc-required, #ff5d77);
  text-decoration: none;
}

.sccc-account-address .optional {
  opacity: 0.7;
}

.sccc-account-address .woocommerce-input-wrapper {
  display: block;
  width: 100%;
}

/* ==========================================================================
   Field shell / shared field behavior
   ========================================================================== */
.sccc-account-address .sccc-field-shell {
  display: block;
}

.sccc-account-address .sccc-field-shell:hover {
  border-color: var(--sccc-field-hover-border, rgba(101, 210, 255, 0.26)) !important;
}

.sccc-account-address .sccc-field-shell:focus-within {
  border-color: var(--sccc-field-focus-border, rgba(101, 210, 255, 0.52)) !important;
  box-shadow:
    0 0 0 4px var(--sccc-field-focus-ring, rgba(101, 210, 255, 0.13)),
    inset 0 1px 0 var(--sccc-field-shine, rgba(255, 255, 255, 0.03)),
    0 0 0 1px var(--sccc-field-outline, rgba(255, 255, 255, 0.02)) !important;
}

.sccc-account-address .sccc-field-control::placeholder {
  color: var(--sccc-field-placeholder, rgba(221, 229, 243, 0.46));
  opacity: 1;
}

.sccc-account-address .sccc-field-control:-webkit-autofill,
.sccc-account-address .sccc-field-control:-webkit-autofill:hover,
.sccc-account-address .sccc-field-control:-webkit-autofill:focus {
  -webkit-text-fill-color: var(--sccc-field-text, #eef3ff);
  box-shadow: 0 0 0 1000px transparent inset;
  transition: background-color 9999s ease-in-out 0s;
}

/* ==========================================================================
   Native select / SelectWoo shell
   - Custom shell arrow removed completely to prevent duplicate arrows
   ========================================================================== */
.sccc-account-address .sccc-select-shell {
  position: relative;
}

.sccc-account-address .sccc-select-shell::after {
  display: none !important;
  content: none !important;
}

/* ==========================================================================
   Native select styling before SelectWoo enhancement
   ========================================================================== */
.sccc-account-address select.sccc-field-control {
  width: 100%;
  border: 0 !important;
  border-radius: 0 !important;
  background: transparent !important;
  background-color: transparent !important;
  box-shadow: none !important;
  color: var(--sccc-field-text, #eef3ff) !important;
  -webkit-text-fill-color: var(--sccc-field-text, #eef3ff);
  color-scheme: dark;
}

html[data-theme="light"] .sccc-account-address select.sccc-field-control {
  color: var(--sccc-field-text, #132033) !important;
  -webkit-text-fill-color: var(--sccc-field-text, #132033);
  color-scheme: light;
}

/* ==========================================================================
   SelectWoo control styling
   ========================================================================== */
.sccc-account-address .sccc-select-shell > .select2-container {
  width: 100% !important;
}

.sccc-account-address .sccc-select-shell > .select2-container .select2-selection--single {
  position: relative !important;
  min-height: 3.4rem !important;
  border: 1px solid var(--sccc-field-border, rgba(255, 255, 255, 0.18)) !important;
  border-radius: 1rem !important;
  background: var(--sccc-field-bg, linear-gradient(180deg, rgba(13, 20, 44, 0.96) 0%, rgba(10, 15, 35, 0.98) 100%)) !important;
  box-shadow:
    inset 0 1px 0 var(--sccc-field-shine, rgba(255, 255, 255, 0.03)),
    0 0 0 1px var(--sccc-field-outline, rgba(255, 255, 255, 0.02)) !important;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease !important;
}

.sccc-account-address .sccc-select-shell > .select2-container:hover .select2-selection--single {
  border-color: var(--sccc-field-hover-border, rgba(101, 210, 255, 0.26)) !important;
}

.sccc-account-address .sccc-select-shell > .select2-container.select2-container--open .select2-selection--single,
.sccc-account-address .sccc-select-shell > .select2-container.select2-container--focus .select2-selection--single {
  border-color: var(--sccc-field-focus-border, rgba(101, 210, 255, 0.52)) !important;
  box-shadow: 0 0 0 4px var(--sccc-field-focus-ring, rgba(101, 210, 255, 0.13)) !important;
}

.sccc-account-address .sccc-select-shell > .select2-container .select2-selection__rendered {
  padding-left: 1rem !important;
  padding-right: 2.8rem !important;
  color: var(--sccc-field-text, #eef3ff) !important;
  -webkit-text-fill-color: var(--sccc-field-text, #eef3ff) !important;
  line-height: 3.3rem !important;
  font-size: 1rem !important;
  background: transparent !important;
}

.sccc-account-address .sccc-select-shell > .select2-container .select2-selection__placeholder {
  color: var(--sccc-field-placeholder, rgba(221, 229, 243, 0.46)) !important;
}

/* hide SelectWoo default arrow */
.sccc-account-address .sccc-select-shell > .select2-container .select2-selection__arrow,
.sccc-account-address .sccc-select-shell > .select2-container .select2-selection__arrow b {
  display: none !important;
  content: none !important;
  border: 0 !important;
}

/* single custom SelectWoo arrow */
.sccc-account-address .sccc-select-shell > .select2-container .select2-selection--single::after {
  content: "" !important;
  position: absolute !important;
  top: 50% !important;
  right: 1.15rem !important;
  width: 0.68rem !important;
  height: 0.68rem !important;
  border-right: 2.5px solid var(--sccc-field-text, #eef3ff) !important;
  border-bottom: 2.5px solid var(--sccc-field-text, #eef3ff) !important;
  transform: translateY(-62%) rotate(45deg) !important;
  transform-origin: center !important;
  pointer-events: none !important;
  opacity: 0.95 !important;
}

.sccc-account-address .sccc-select-shell > .select2-container.select2-container--open .select2-selection--single::after {
  transform: translateY(-38%) rotate(-135deg) !important;
}

/* ==========================================================================
   SelectWoo portal dropdown fix
   - Needed because the open dropdown is often appended outside the component
   - Scoped to Woo account pages only
   ========================================================================== */
html.dark body.woocommerce-account,
html[data-theme="light"] body.woocommerce-account {
  --sccc-portal-dropdown-bg: var(--sccc-select-dropdown-bg);
  --sccc-portal-dropdown-border: var(--sccc-select-dropdown-border);
  --sccc-portal-search-bg: var(--sccc-select-search-bg);
  --sccc-portal-field-text: var(--sccc-field-text);
  --sccc-portal-option-selected-bg: var(--sccc-select-option-bg-selected);
  --sccc-portal-option-highlight-bg: var(--sccc-select-option-bg-highlight);
  --sccc-portal-option-highlight-text: var(--sccc-select-option-text-highlight);
}

/* fallback in case body-level vars do not inherit as expected */
html.dark body.woocommerce-account {
  --sccc-portal-dropdown-bg: #0d142c;
  --sccc-portal-dropdown-border: rgba(255, 255, 255, 0.18);
  --sccc-portal-search-bg: #081128;
  --sccc-portal-field-text: #eef3ff;
  --sccc-portal-option-selected-bg: rgba(255, 255, 255, 0.04);
  --sccc-portal-option-highlight-bg: rgba(101, 210, 255, 0.18);
  --sccc-portal-option-highlight-text: #ffffff;
}

html[data-theme="light"] body.woocommerce-account {
  --sccc-portal-dropdown-bg: #f8fbff;
  --sccc-portal-dropdown-border: rgba(15, 23, 42, 0.12);
  --sccc-portal-search-bg: #ffffff;
  --sccc-portal-field-text: #132033;
  --sccc-portal-option-selected-bg: rgba(15, 23, 42, 0.04);
  --sccc-portal-option-highlight-bg: rgba(15, 131, 182, 0.12);
  --sccc-portal-option-highlight-text: #10233f;
}

body.woocommerce-account .select2-container--open .select2-dropdown,
body.woocommerce-account .select2-container--default.select2-container--open .select2-dropdown {
  background: var(--sccc-portal-dropdown-bg) !important;
  border: 1px solid var(--sccc-portal-dropdown-border) !important;
  border-radius: 1rem !important;
  overflow: hidden !important;
  box-shadow: 0 22px 44px rgba(0, 0, 0, 0.32) !important;
}

body.woocommerce-account .select2-container--open .select2-search--dropdown,
body.woocommerce-account .select2-container--default.select2-container--open .select2-search--dropdown {
  background: var(--sccc-portal-dropdown-bg) !important;
  padding: 0.75rem !important;
}

body.woocommerce-account .select2-container--open .select2-search__field,
body.woocommerce-account .select2-container--default.select2-container--open .select2-search__field {
  min-height: 2.75rem !important;
  background: var(--sccc-portal-search-bg) !important;
  color: var(--sccc-portal-field-text) !important;
  -webkit-text-fill-color: var(--sccc-portal-field-text) !important;
  border: 1px solid var(--sccc-portal-dropdown-border) !important;
  border-radius: 0.75rem !important;
  box-shadow: none !important;
  outline: none !important;
}

body.woocommerce-account .select2-container--open .select2-results,
body.woocommerce-account .select2-container--open .select2-results > .select2-results__options,
body.woocommerce-account .select2-container--open .select2-results__options,
body.woocommerce-account .select2-container--open .select2-results__options--nested {
  background: var(--sccc-portal-dropdown-bg) !important;
}

body.woocommerce-account .select2-container--default .select2-results__option {
  background: var(--sccc-portal-dropdown-bg) !important;
  color: var(--sccc-portal-field-text) !important;
  padding: 0.75rem 0.95rem !important;
  font-size: 0.95rem !important;
  line-height: 1.35 !important;
}

body.woocommerce-account .select2-container--default .select2-results__option[aria-selected="true"] {
  background: var(--sccc-portal-option-selected-bg) !important;
  color: var(--sccc-portal-field-text) !important;
}

body.woocommerce-account .select2-container--default .select2-results__option--highlighted[aria-selected],
body.woocommerce-account .select2-container--default .select2-results__option--highlighted[data-selected] {
  background: var(--sccc-portal-option-highlight-bg) !important;
  color: var(--sccc-portal-option-highlight-text) !important;
}

/* ==========================================================================
   Actions
   ========================================================================== */
.sccc-account-address__actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--sccc-divider, rgba(255, 255, 255, 0.07));
}

.sccc-account-address__note {
  margin: 0;
  color: var(--sccc-copy, rgba(230, 236, 249, 0.72));
  font-size: 0.9rem;
  line-height: 1.55;
}

.sccc-account-address__submit-wrap {
  margin: 0;
  flex-shrink: 0;
}

/* ==========================================================================
   Save address button
   ========================================================================== */
.sccc-account-address .sccc-account-address__submit,
.sccc-account-address button[name="save_address"] {
  position: relative;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 0.65rem;
  min-height: 3rem !important;
  padding: 0.9rem 1.35rem !important;
  border: 1px solid var(--sccc-submit-border, rgba(101, 210, 255, 0.26)) !important;
  border-radius: 9999px !important;
  background: var(--sccc-submit-bg, linear-gradient(180deg, rgba(16, 31, 66, 0.98) 0%, rgba(9, 18, 42, 0.98) 100%)) !important;
  color: var(--sccc-submit-text, #eef3ff) !important;
  -webkit-text-fill-color: var(--sccc-submit-text, #eef3ff) !important;
  box-shadow: var(--sccc-submit-shadow, 0 14px 34px rgba(0, 0, 0, 0.28)) !important;
  text-decoration: none !important;
  cursor: pointer !important;
  font-size: 0.9rem !important;
  font-weight: 700 !important;
  line-height: 1 !important;
  letter-spacing: 0.01em !important;
  appearance: none !important;
  -webkit-appearance: none !important;
  transition:
    transform 0.18s ease,
    box-shadow 0.18s ease,
    border-color 0.18s ease,
    filter 0.18s ease !important;
}

.sccc-account-address .sccc-account-address__submit::before,
.sccc-account-address button[name="save_address"]::before {
  content: "";
  width: 0.56rem;
  height: 0.56rem;
  border-radius: 9999px;
  flex: 0 0 auto;
  background: var(--sccc-submit-dot-bg, linear-gradient(180deg, #8de6ff 0%, #53c5f5 100%));
  box-shadow: var(--sccc-submit-dot-shadow, 0 0 0 3px rgba(83, 197, 245, 0.12));
}

.sccc-account-address .sccc-account-address__submit:hover,
.sccc-account-address button[name="save_address"]:hover,
.sccc-account-address .sccc-account-address__submit:focus-visible,
.sccc-account-address button[name="save_address"]:focus-visible {
  transform: translateY(-1px) !important;
  border-color: var(--sccc-submit-hover-border, rgba(101, 210, 255, 0.4)) !important;
  box-shadow: var(--sccc-submit-hover-shadow, 0 18px 38px rgba(0, 0, 0, 0.34)) !important;
  outline: none !important;
}

.sccc-account-address .sccc-account-address__submit:active,
.sccc-account-address button[name="save_address"]:active {
  transform: translateY(0) !important;
  filter: brightness(0.99) !important;
}

/* ==========================================================================
   Validation
   ========================================================================== */
.sccc-account-address .woocommerce-invalid .sccc-field-shell,
.sccc-account-address .woocommerce-invalid .select2-selection--single {
  border-color: rgba(255, 93, 119, 0.62) !important;
  box-shadow: 0 0 0 4px rgba(255, 93, 119, 0.08) !important;
}

/* ==========================================================================
   Mobile adjustments
   ========================================================================== */
@media (max-width: 767px) {
  .sccc-account-address .woocommerce-address-fields__field-wrapper {
    grid-template-columns: minmax(0, 1fr);
    gap: 1rem;
  }

  .sccc-account-address__row.form-row-wide,
  .sccc-account-address__row.woocommerce-form-row--wide,
  .sccc-account-address__row--wide {
    grid-column: auto;
  }

  .sccc-account-address__actions {
    flex-direction: column;
    align-items: stretch;
  }

  .sccc-account-address__submit-wrap,
  .sccc-account-address .sccc-account-address__submit,
  .sccc-account-address button[name="save_address"] {
    width: 100%;
  }
}
</style>
CSS;

/**
 * --------------------------------------------------------------------------
 * Begin template output
 * --------------------------------------------------------------------------
 */
do_action('woocommerce_before_edit_account_address_form');

if (! $load_address) {
    wc_get_template('myaccount/my-address.php');
    do_action('woocommerce_after_edit_account_address_form');
    return;
}
?>

<form method="post" novalidate class="sccc-account-address">
  <section class="sccc-account-address__card">
    <div class="sccc-account-address__inner">
      <header class="sccc-account-address__header">
        <div class="sccc-account-address__heading">
          <p class="sccc-account-address__eyebrow"><?php echo esc_html__('My account', 'woocommerce'); ?></p>
          <h1 class="sccc-account-address__title"><?php echo wp_kses_post(apply_filters('woocommerce_my_account_edit_address_title', $page_title, $load_address)); ?></h1>
          <p class="sccc-account-address__intro"><?php echo esc_html__('Keep your account address current for orders, deliveries, and billing records.', 'woocommerce'); ?></p>
        </div>

        <a class="sccc-account-address__backlink" href="<?php echo esc_url(wc_get_account_endpoint_url('edit-address')); ?>">
          <?php echo esc_html__('Back to addresses', 'woocommerce'); ?>
        </a>
      </header>

      <div class="woocommerce-address-fields">
        <?php do_action("woocommerce_before_edit_address_form_{$load_address}"); ?>

        <div class="woocommerce-address-fields__field-wrapper">
          <?php foreach ($address as $key => $field) : ?>
            <?php
            $type        = isset($field['type']) ? $field['type'] : 'text';
            $label       = isset($field['label']) ? $field['label'] : '';
            $description = isset($field['description']) ? $field['description'] : '';
            $placeholder = isset($field['placeholder']) ? $field['placeholder'] : '';
            $required    = ! empty($field['required']);
            $value       = wc_get_post_data_by_key($key, isset($field['value']) ? $field['value'] : '');
            $id          = isset($field['id']) ? $field['id'] : $key;
            $priority    = isset($field['priority']) ? $field['priority'] : '';
            $classes     = isset($field['class']) ? (array) $field['class'] : [];

            $input_class = trim(implode(' ', array_filter(array_merge(
                isset($field['input_class']) ? (array) $field['input_class'] : [],
                ['sccc-field-control', 'sccc-field-control--' . $type]
            ))));

            $is_wide =
                in_array('form-row-wide', $classes, true) ||
                in_array('woocommerce-form-row--wide', $classes, true);

            $row_classes = array_values(array_unique(array_filter(array_merge(
                ['form-row', 'sccc-account-address__row'],
                $classes,
                $required ? ['validate-required'] : [],
                $is_wide ? ['sccc-account-address__row--wide'] : []
            ))));

            $label_classes = trim(implode(' ', array_filter(array_merge(
                ['sccc-account-address__label'],
                isset($field['label_class']) ? (array) $field['label_class'] : []
            ))));

            $required_indicator = $required
                ? '&nbsp;<span class="required" aria-hidden="true">*</span>'
                : '&nbsp;<span class="optional">(' . esc_html__('optional', 'woocommerce') . ')</span>';

            $common_attrs = [
                'name'                     => $key,
                'id'                       => $id,
                'placeholder'              => $placeholder,
                'autocomplete'             => isset($field['autocomplete']) ? $field['autocomplete'] : null,
                'maxlength'                => isset($field['maxlength']) ? $field['maxlength'] : null,
                'minlength'                => isset($field['minlength']) ? $field['minlength'] : null,
                'aria-describedby'         => $description ? $id . '-description' : null,
                'aria-required'            => $required ? 'true' : null,
                'autofocus'                => ! empty($field['autofocus']) ? 'autofocus' : null,
                'data-sccc-themed-control' => 'true',
                'class'                    => $input_class,
            ];
            ?>

            <p class="<?php echo esc_attr(implode(' ', $row_classes)); ?>" id="<?php echo esc_attr($id); ?>_field" data-priority="<?php echo esc_attr((string) $priority); ?>">
              <?php if ($label && 'hidden' !== $type) : ?>
                <label for="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($label_classes); ?>">
                  <?php echo wp_kses_post($label . $required_indicator); ?>
                </label>
              <?php endif; ?>

              <span class="woocommerce-input-wrapper">
                <?php if ('hidden' === $type) : ?>

                  <input <?php echo $build_attrs(array_merge($common_attrs, [
                      'type'  => 'hidden',
                      'value' => $value,
                  ])); ?> />

                <?php elseif ('textarea' === $type) : ?>

                  <span class="sccc-field-shell" style="<?php echo esc_attr($shell_style); ?>">
                    <textarea <?php echo $build_attrs(array_merge($common_attrs, [
                        'rows'  => ! empty($field['custom_attributes']['rows']) ? (int) $field['custom_attributes']['rows'] : 4,
                        'cols'  => ! empty($field['custom_attributes']['cols']) ? (int) $field['custom_attributes']['cols'] : 5,
                        'style' => $textarea_style,
                    ])); ?>><?php echo esc_textarea($value); ?></textarea>
                  </span>

                <?php elseif ('country' === $type) : ?>

                  <?php
                  $countries = ('shipping_country' === $key)
                      ? WC()->countries->get_shipping_countries()
                      : WC()->countries->get_allowed_countries();

                  $country_placeholder = $placeholder ? $placeholder : esc_attr__('Select a country / region…', 'woocommerce');

                  $country_attrs = array_merge($common_attrs, [
                      'style'              => $select_style,
                      'class'              => trim('country_to_state country_select ' . $input_class),
                      'data-placeholder'   => $country_placeholder,
                      'data-input-classes' => $input_class,
                      'data-label'         => $label ? $label : null,
                  ]);
                  ?>

                  <span class="sccc-field-shell sccc-select-shell" style="<?php echo esc_attr($shell_style); ?>">
                    <select <?php echo $build_attrs($country_attrs); ?>>
                      <?php if (count($countries) > 1) : ?>
                        <option value=""><?php echo esc_html__('Select a country / region…', 'woocommerce'); ?></option>
                      <?php endif; ?>

                      <?php foreach ($countries as $country_code => $country_name) : ?>
                        <option value="<?php echo esc_attr($country_code); ?>" <?php echo selected($value, $country_code, false); ?>>
                          <?php echo esc_html($country_name); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </span>

                <?php elseif ('state' === $type) : ?>

                  <?php
                  $country_key = ('billing_state' === $key) ? 'billing_country' : 'shipping_country';

                  $for_country = wc_get_post_data_by_key(
                      $country_key,
                      isset($address[$country_key]['value']) ? $address[$country_key]['value'] : (isset($field['country']) ? $field['country'] : '')
                  );

                  $states = WC()->countries->get_states($for_country);
                  ?>

                  <?php if (is_array($states) && empty($states)) : ?>

                    <input <?php echo $build_attrs(array_merge($common_attrs, [
                        'type'               => 'hidden',
                        'value'              => '',
                        'readonly'           => 'readonly',
                        'data-input-classes' => $input_class,
                    ])); ?> />

                  <?php elseif (! is_null($for_country) && is_array($states)) : ?>

                    <?php
                    $state_placeholder = $placeholder ? $placeholder : esc_attr__('Select an option…', 'woocommerce');

                    $state_attrs = array_merge($common_attrs, [
                        'style'              => $select_style,
                        'class'              => trim('state_select ' . $input_class),
                        'data-placeholder'   => $state_placeholder,
                        'data-input-classes' => $input_class,
                        'data-label'         => $label ? $label : null,
                    ]);
                    ?>

                    <span class="sccc-field-shell sccc-select-shell" style="<?php echo esc_attr($shell_style); ?>">
                      <select <?php echo $build_attrs($state_attrs); ?>>
                        <option value=""><?php echo esc_html__('Select an option…', 'woocommerce'); ?></option>

                        <?php foreach ($states as $state_code => $state_name) : ?>
                          <option value="<?php echo esc_attr($state_code); ?>" <?php echo selected($value, $state_code, false); ?>>
                            <?php echo esc_html($state_name); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </span>

                  <?php else : ?>

                    <span class="sccc-field-shell" style="<?php echo esc_attr($shell_style); ?>">
                      <input <?php echo $build_attrs(array_merge($common_attrs, [
                          'type'               => 'text',
                          'value'              => $value,
                          'style'              => $control_style,
                          'data-input-classes' => $input_class,
                      ])); ?> />
                    </span>

                  <?php endif; ?>

                <?php elseif ('select' === $type) : ?>

                  <?php
                  $select_attrs = array_merge($common_attrs, [
                      'style'            => $select_style,
                      'class'            => trim('select ' . $input_class),
                      'data-placeholder' => $placeholder ? $placeholder : '',
                  ]);
                  ?>

                  <span class="sccc-field-shell sccc-select-shell" style="<?php echo esc_attr($shell_style); ?>">
                    <select <?php echo $build_attrs($select_attrs); ?>>
                      <?php foreach ((isset($field['options']) ? $field['options'] : []) as $option_key => $option_text) : ?>
                        <option value="<?php echo esc_attr($option_key); ?>" <?php echo selected($value, $option_key, false); ?>>
                          <?php echo esc_html($option_text); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </span>

                <?php else : ?>

                  <span class="sccc-field-shell" style="<?php echo esc_attr($shell_style); ?>">
                    <input <?php echo $build_attrs(array_merge($common_attrs, [
                        'type'  => $type,
                        'value' => $value,
                        'style' => $control_style,
                    ])); ?> />
                  </span>

                <?php endif; ?>

                <?php if ($description) : ?>
                  <span class="description" id="<?php echo esc_attr($id); ?>-description" aria-hidden="true">
                    <?php echo wp_kses_post($description); ?>
                  </span>
                <?php endif; ?>
              </span>
            </p>
          <?php endforeach; ?>
        </div>

        <?php do_action("woocommerce_after_edit_address_form_{$load_address}"); ?>

        <div class="sccc-account-address__actions">
          <p class="sccc-account-address__note">
            <?php echo esc_html__('Changes here update the address saved to your account.', 'woocommerce'); ?>
          </p>

          <p class="sccc-account-address__submit-wrap">
            <button
              type="submit"
              class="button sccc-account-address__submit<?php echo $button_element_class ? ' ' . esc_attr($button_element_class) : ''; ?>"
              name="save_address"
              value="<?php echo esc_attr__('Save address', 'woocommerce'); ?>"
            >
              <?php echo esc_html__('Save address', 'woocommerce'); ?>
            </button>

            <?php wp_nonce_field('woocommerce-edit_address', 'woocommerce-edit-address-nonce'); ?>
            <input type="hidden" name="action" value="edit_address" />
          </p>
        </div>
      </div>
    </div>
  </section>
</form>

<?php do_action('woocommerce_after_edit_account_address_form'); ?>