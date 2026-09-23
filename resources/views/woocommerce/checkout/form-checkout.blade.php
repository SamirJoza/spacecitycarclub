{{--
  File: resources/views/woocommerce/checkout/form-checkout.blade.php

  Purpose:
  Classic WooCommerce checkout Blade override for Space City Car Club.

  Why this version exists:
  - Removes the Composer dependency that caused:
      Undefined variable $scccCheckoutAriaLabel
  - Keeps the template self-contained and safe
  - Preserves WooCommerce checkout hook order for compatibility
  - Adds local checkout-only CSS for the requested fixes:
      1. "Add a note to your order" checkbox + textarea polish
      2. Return to cart arrow/text spacing fix
      3. Apply button reduced so it feels proportional
      4. WooCommerce Checkout Block select/dropdown readability fix

  Important:
  - This template is for the classic shortcode checkout flow.
  - The Checkout page content should normally be:
      [woocommerce_checkout]
  - The dropdown styling below also defensively targets WooCommerce Checkout
    Block select classes because the provided rendered HTML uses block markup.
  - If the live checkout page is using the Checkout Block and this Blade file is
    not rendered, move the "WooCommerce Checkout Block select controls" CSS
    section into the checkout/global stylesheet.
--}}

@php
  defined('ABSPATH') || exit;

  /**
   * Keep checkout strings local to the view.
   *
   * Why:
   * The earlier version expected a Composer to provide these variables.
   * Since that binding did not occur, the template crashed.
   * Localizing them here removes that dependency and keeps the file stable.
   */
  $scccCheckoutAriaLabel = __('Checkout', 'woocommerce');
  $scccCheckoutOrderHeading = __('Your order', 'woocommerce');
  $scccCheckoutMustBeLoggedInMessage = (string) apply_filters(
    'woocommerce_checkout_must_be_logged_in_message',
    __('You must be logged in to checkout.', 'woocommerce')
  );

  /**
   * Preserve WooCommerce's standard hook order.
   *
   * Why:
   * Checkout extensions and event/ticket integrations often rely on the
   * default action sequence to inject fields, notices, totals, and payment UI.
   */
  do_action('woocommerce_before_checkout_form', $checkout);
@endphp

@if (! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in())
  <div class="woocommerce-info">
    {{ $scccCheckoutMustBeLoggedInMessage }}
  </div>
@else
  @once
    <style>
      /* ==========================================================================
         File: resources/views/woocommerce/checkout/form-checkout.blade.php
         Section: Checkout-local CSS
         Purpose:
         Keep these fixes local to the classic checkout template so they do not
         become another globally loaded stylesheet.
         ========================================================================== */

      .sccc-checkout {
        width: 100%;
      }

      /* ==========================================================================
         Shared checkout field tokens
         Why:
         These variables keep the native checkout fields and the WooCommerce
         Checkout Block select controls visually aligned with the SCCC glassy
         theme without requiring a larger refactor.
         ========================================================================== */
      .sccc-checkout,
      .wc-block-checkout__shipping-fields,
      .wc-block-checkout__billing-fields,
      .wp-block-woocommerce-checkout-shipping-address-block,
      .wp-block-woocommerce-checkout-billing-address-block {
        --sccc-block-select-bg: linear-gradient(180deg, rgba(21, 29, 50, 0.96) 0%, rgba(15, 23, 42, 0.98) 100%);
        --sccc-block-select-option-bg: #111827;
        --sccc-block-select-option-text: #eef3ff;
        --sccc-block-select-option-muted: rgba(221, 229, 243, 0.58);
        --sccc-block-select-text: #eef3ff;
        --sccc-block-select-label: rgba(221, 229, 243, 0.58);
        --sccc-block-select-border: rgba(255, 255, 255, 0.18);
        --sccc-block-select-hover-border: rgba(101, 210, 255, 0.28);
        --sccc-block-select-focus-border: rgba(101, 210, 255, 0.58);
        --sccc-block-select-focus-ring: rgba(101, 210, 255, 0.14);
        --sccc-block-select-icon: rgba(221, 229, 243, 0.76);
        --sccc-block-select-shine: rgba(255, 255, 255, 0.035);
        --sccc-block-select-outline: rgba(255, 255, 255, 0.025);
      }

      /* ==========================================================================
         Order notes area
         Why:
         Tightens the checkbox row and makes the notes textarea visually match
         the rest of the SCCC form fields.
         ========================================================================== */
      .sccc-checkout .woocommerce-additional-fields {
        margin-top: 1.25rem;
      }

      .sccc-checkout .woocommerce-additional-fields__field-wrapper {
        display: flex;
        flex-direction: column;
        gap: 0.9rem;
      }

      .sccc-checkout #order_comments_field {
        margin: 0;
      }

      .sccc-checkout .woocommerce-additional-fields .woocommerce-form__label-for-checkbox,
      .sccc-checkout .woocommerce-additional-fields label.checkbox {
        display: inline-flex;
        align-items: flex-start;
        gap: 0.75rem;
        margin: 0;
        color: var(--sccc-copy, rgba(230, 236, 249, 0.82));
        font-size: 0.96rem;
        line-height: 1.45;
        cursor: pointer;
      }

      .sccc-checkout .woocommerce-additional-fields .woocommerce-form__input-checkbox,
      .sccc-checkout .woocommerce-additional-fields input[type="checkbox"] {
        flex: 0 0 auto;
        width: 1.05rem;
        height: 1.05rem;
        margin: 0.14rem 0 0 !important;
        accent-color: var(--sccc-eyebrow, #65d2ff);
      }

      .sccc-checkout textarea#order_comments {
        display: block;
        width: 100%;
        min-height: 7rem;
        padding: 0.95rem 1rem;
        border: 1px solid var(--sccc-field-border, rgba(255, 255, 255, 0.18));
        border-radius: 1rem;
        background: var(
          --sccc-field-bg,
          linear-gradient(180deg, rgba(13, 20, 44, 0.96) 0%, rgba(10, 15, 35, 0.98) 100%)
        );
        color: var(--sccc-field-text, #eef3ff);
        -webkit-text-fill-color: var(--sccc-field-text, #eef3ff);
        box-shadow:
          inset 0 1px 0 var(--sccc-field-shine, rgba(255, 255, 255, 0.03)),
          0 0 0 1px var(--sccc-field-outline, rgba(255, 255, 255, 0.02));
        font-size: 0.98rem;
        line-height: 1.5;
        resize: vertical;
        transition:
          border-color 0.18s ease,
          box-shadow 0.18s ease,
          background-color 0.18s ease;
      }

      .sccc-checkout textarea#order_comments::placeholder {
        color: var(--sccc-field-placeholder, rgba(221, 229, 243, 0.46));
        opacity: 1;
      }

      .sccc-checkout textarea#order_comments:hover {
        border-color: var(--sccc-field-hover-border, rgba(101, 210, 255, 0.26));
      }

      .sccc-checkout textarea#order_comments:focus {
        border-color: var(--sccc-field-focus-border, rgba(101, 210, 255, 0.52));
        box-shadow:
          0 0 0 4px var(--sccc-field-focus-ring, rgba(101, 210, 255, 0.13)),
          inset 0 1px 0 var(--sccc-field-shine, rgba(255, 255, 255, 0.03)),
          0 0 0 1px var(--sccc-field-outline, rgba(255, 255, 255, 0.02));
        outline: none;
      }

      /* ==========================================================================
         Return to cart link
         Why:
         WooCommerce often uses ::before for the backward arrow.
         On your layout the icon was crowding the label, so this normalizes the
         link into an inline-flex row and replaces the icon with a clean arrow.
         ========================================================================== */
      .sccc-checkout a.wc-backward {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.55rem;
        min-height: 2.75rem;
        padding: 0.75rem 1rem;
        text-indent: 0;
        line-height: 1;
      }

      .sccc-checkout a.wc-backward::before {
        content: "←";
        position: static;
        float: none;
        margin: 0;
        width: auto;
        height: auto;
        line-height: 1;
        font-size: 0.95em;
        transform: translateY(-0.02em);
      }

      /* ==========================================================================
         Coupon row / Apply button
         Why:
         Keeps the coupon input and button visually balanced and makes the Apply
         button feel less oversized.
         ========================================================================== */
      .sccc-checkout .coupon {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 0.75rem;
        align-items: center;
      }

      .sccc-checkout .coupon .input-text,
      .sccc-checkout .woocommerce-form-coupon .input-text {
        min-width: 0;
      }

      .sccc-checkout .coupon button[name="apply_coupon"],
      .sccc-checkout .coupon .button {
        width: auto !important;
        min-width: 5.75rem;
        min-height: 2.75rem !important;
        padding: 0.75rem 1rem !important;
        border-radius: 0.95rem !important;
        font-size: 0.9rem !important;
        line-height: 1 !important;
        white-space: nowrap;
      }

      /* ==========================================================================
         WooCommerce Checkout Block select controls
         Why:
         The provided rendered HTML uses WooCommerce Checkout Block classes:
         - .wc-blocks-components-select__container
         - .wc-blocks-components-select__select
         - .wc-blocks-components-select__label
         - .wc-blocks-components-select__expand

         The screenshot shows the native country/state select controls rendering
         white on white. These rules force the closed select, floating label,
         dropdown icon, and browser option list to use the SCCC field colors.
         ========================================================================== */
      .sccc-checkout .wc-blocks-components-select__container,
      .wc-block-checkout__shipping-fields .wc-blocks-components-select__container,
      .wc-block-checkout__billing-fields .wc-blocks-components-select__container,
      .wp-block-woocommerce-checkout-shipping-address-block .wc-blocks-components-select__container,
      .wp-block-woocommerce-checkout-billing-address-block .wc-blocks-components-select__container {
        position: relative;
        border-radius: 0.95rem;
        background: var(--sccc-block-select-bg);
      }

      .sccc-checkout .wc-blocks-components-select__select,
      .wc-block-checkout__shipping-fields .wc-blocks-components-select__select,
      .wc-block-checkout__billing-fields .wc-blocks-components-select__select,
      .wp-block-woocommerce-checkout-shipping-address-block .wc-blocks-components-select__select,
      .wp-block-woocommerce-checkout-billing-address-block .wc-blocks-components-select__select {
        display: block;
        width: 100%;
        min-height: 3.15rem;
        padding: 1.18rem 2.75rem 0.42rem 0.75rem !important;
        border: 1px solid var(--sccc-block-select-border) !important;
        border-radius: 0.95rem !important;
        background: var(--sccc-block-select-bg) !important;
        color: var(--sccc-block-select-text) !important;
        -webkit-text-fill-color: var(--sccc-block-select-text) !important;
        box-shadow:
          inset 0 1px 0 var(--sccc-block-select-shine),
          0 0 0 1px var(--sccc-block-select-outline);
        font-size: 0.96rem;
        line-height: 1.25;
        appearance: none;
        -webkit-appearance: none;
        transition:
          border-color 0.18s ease,
          box-shadow 0.18s ease,
          background-color 0.18s ease;
      }

      .sccc-checkout .wc-blocks-components-select__select:hover,
      .wc-block-checkout__shipping-fields .wc-blocks-components-select__select:hover,
      .wc-block-checkout__billing-fields .wc-blocks-components-select__select:hover,
      .wp-block-woocommerce-checkout-shipping-address-block .wc-blocks-components-select__select:hover,
      .wp-block-woocommerce-checkout-billing-address-block .wc-blocks-components-select__select:hover {
        border-color: var(--sccc-block-select-hover-border) !important;
      }

      .sccc-checkout .wc-blocks-components-select__select:focus,
      .sccc-checkout .wc-blocks-components-select__select:focus-visible,
      .wc-block-checkout__shipping-fields .wc-blocks-components-select__select:focus,
      .wc-block-checkout__shipping-fields .wc-blocks-components-select__select:focus-visible,
      .wc-block-checkout__billing-fields .wc-blocks-components-select__select:focus,
      .wc-block-checkout__billing-fields .wc-blocks-components-select__select:focus-visible,
      .wp-block-woocommerce-checkout-shipping-address-block .wc-blocks-components-select__select:focus,
      .wp-block-woocommerce-checkout-shipping-address-block .wc-blocks-components-select__select:focus-visible,
      .wp-block-woocommerce-checkout-billing-address-block .wc-blocks-components-select__select:focus,
      .wp-block-woocommerce-checkout-billing-address-block .wc-blocks-components-select__select:focus-visible {
        border-color: var(--sccc-block-select-focus-border) !important;
        box-shadow:
          0 0 0 4px var(--sccc-block-select-focus-ring),
          inset 0 1px 0 var(--sccc-block-select-shine),
          0 0 0 1px var(--sccc-block-select-outline);
        outline: none !important;
      }

      .sccc-checkout .wc-blocks-components-select__label,
      .wc-block-checkout__shipping-fields .wc-blocks-components-select__label,
      .wc-block-checkout__billing-fields .wc-blocks-components-select__label,
      .wp-block-woocommerce-checkout-shipping-address-block .wc-blocks-components-select__label,
      .wp-block-woocommerce-checkout-billing-address-block .wc-blocks-components-select__label {
        color: var(--sccc-block-select-label) !important;
        -webkit-text-fill-color: var(--sccc-block-select-label) !important;
        background: transparent !important;
        z-index: 2;
      }

      .sccc-checkout .wc-blocks-components-select__expand,
      .wc-block-checkout__shipping-fields .wc-blocks-components-select__expand,
      .wc-block-checkout__billing-fields .wc-blocks-components-select__expand,
      .wp-block-woocommerce-checkout-shipping-address-block .wc-blocks-components-select__expand,
      .wp-block-woocommerce-checkout-billing-address-block .wc-blocks-components-select__expand {
        color: var(--sccc-block-select-icon) !important;
        fill: var(--sccc-block-select-icon) !important;
        pointer-events: none;
      }

      .sccc-checkout .wc-blocks-components-select__expand path,
      .wc-block-checkout__shipping-fields .wc-blocks-components-select__expand path,
      .wc-block-checkout__billing-fields .wc-blocks-components-select__expand path,
      .wp-block-woocommerce-checkout-shipping-address-block .wc-blocks-components-select__expand path,
      .wp-block-woocommerce-checkout-billing-address-block .wc-blocks-components-select__expand path {
        fill: currentColor !important;
      }

      .sccc-checkout .wc-blocks-components-select__select option,
      .sccc-checkout .wc-blocks-components-select__select optgroup,
      .wc-block-checkout__shipping-fields .wc-blocks-components-select__select option,
      .wc-block-checkout__shipping-fields .wc-blocks-components-select__select optgroup,
      .wc-block-checkout__billing-fields .wc-blocks-components-select__select option,
      .wc-block-checkout__billing-fields .wc-blocks-components-select__select optgroup,
      .wp-block-woocommerce-checkout-shipping-address-block .wc-blocks-components-select__select option,
      .wp-block-woocommerce-checkout-shipping-address-block .wc-blocks-components-select__select optgroup,
      .wp-block-woocommerce-checkout-billing-address-block .wc-blocks-components-select__select option,
      .wp-block-woocommerce-checkout-billing-address-block .wc-blocks-components-select__select optgroup {
        background-color: var(--sccc-block-select-option-bg) !important;
        color: var(--sccc-block-select-option-text) !important;
      }

      .sccc-checkout .wc-blocks-components-select__select option:disabled,
      .wc-block-checkout__shipping-fields .wc-blocks-components-select__select option:disabled,
      .wc-block-checkout__billing-fields .wc-blocks-components-select__select option:disabled,
      .wp-block-woocommerce-checkout-shipping-address-block .wc-blocks-components-select__select option:disabled,
      .wp-block-woocommerce-checkout-billing-address-block .wc-blocks-components-select__select option:disabled {
        color: var(--sccc-block-select-option-muted) !important;
      }

      .sccc-checkout .wc-blocks-components-select__select option:checked,
      .wc-block-checkout__shipping-fields .wc-blocks-components-select__select option:checked,
      .wc-block-checkout__billing-fields .wc-blocks-components-select__select option:checked,
      .wp-block-woocommerce-checkout-shipping-address-block .wc-blocks-components-select__select option:checked,
      .wp-block-woocommerce-checkout-billing-address-block .wc-blocks-components-select__select option:checked {
        background-color: color-mix(in srgb, var(--sccc-eyebrow, #65d2ff) 26%, var(--sccc-block-select-option-bg)) !important;
        color: var(--sccc-block-select-option-text) !important;
      }

      /* ==========================================================================
         Light theme readability
         Why:
         Keeps order notes and block select controls readable if checkout is
         viewed in light mode.
         ========================================================================== */
      html[data-theme="light"] .sccc-checkout,
      html[data-theme="light"] .wc-block-checkout__shipping-fields,
      html[data-theme="light"] .wc-block-checkout__billing-fields,
      html[data-theme="light"] .wp-block-woocommerce-checkout-shipping-address-block,
      html[data-theme="light"] .wp-block-woocommerce-checkout-billing-address-block {
        --sccc-block-select-bg: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(245, 248, 252, 0.98) 100%);
        --sccc-block-select-option-bg: #ffffff;
        --sccc-block-select-option-text: #132033;
        --sccc-block-select-option-muted: rgba(19, 32, 51, 0.46);
        --sccc-block-select-text: #132033;
        --sccc-block-select-label: rgba(19, 32, 51, 0.58);
        --sccc-block-select-border: rgba(19, 32, 51, 0.18);
        --sccc-block-select-hover-border: rgba(41, 121, 255, 0.32);
        --sccc-block-select-focus-border: rgba(41, 121, 255, 0.56);
        --sccc-block-select-focus-ring: rgba(41, 121, 255, 0.14);
        --sccc-block-select-icon: rgba(19, 32, 51, 0.68);
        --sccc-block-select-shine: rgba(255, 255, 255, 0.72);
        --sccc-block-select-outline: rgba(19, 32, 51, 0.04);
      }

      html[data-theme="light"] .sccc-checkout textarea#order_comments {
        color: var(--sccc-field-text, #132033);
        -webkit-text-fill-color: var(--sccc-field-text, #132033);
      }

      html[data-theme="light"] .sccc-checkout textarea#order_comments::placeholder {
        color: var(--sccc-field-placeholder, rgba(19, 32, 51, 0.42));
      }

      /* ==========================================================================
         Mobile stacking
         Why:
         Makes the coupon row stack cleanly on small screens.
         ========================================================================== */
      @media (max-width: 767px) {
        .sccc-checkout .coupon {
          grid-template-columns: 1fr;
        }

        .sccc-checkout .coupon button[name="apply_coupon"],
        .sccc-checkout .coupon .button {
          width: 100% !important;
        }
      }
    </style>
  @endonce

  <div class="sccc-checkout">
    <form
      name="checkout"
      method="post"
      class="checkout woocommerce-checkout"
      action="{{ esc_url(wc_get_checkout_url()) }}"
      enctype="multipart/form-data"
      aria-label="{{ esc_attr($scccCheckoutAriaLabel) }}"
    >
      @if ($checkout->get_checkout_fields())
        @php do_action('woocommerce_checkout_before_customer_details'); @endphp

        <div class="col2-set" id="customer_details">
          <div class="col-1">
            @php do_action('woocommerce_checkout_billing'); @endphp
          </div>

          <div class="col-2">
            @php do_action('woocommerce_checkout_shipping'); @endphp
          </div>
        </div>

        @php do_action('woocommerce_checkout_after_customer_details'); @endphp
      @endif

      @php do_action('woocommerce_checkout_before_order_review_heading'); @endphp

      <h3 id="order_review_heading">{{ $scccCheckoutOrderHeading }}</h3>

      @php do_action('woocommerce_checkout_before_order_review'); @endphp

      <div id="order_review" class="woocommerce-checkout-review-order">
        @php do_action('woocommerce_checkout_order_review'); @endphp
      </div>

      @php do_action('woocommerce_checkout_after_order_review'); @endphp
    </form>
  </div>
@endif