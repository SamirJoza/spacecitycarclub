{{--
  File path + filename: resources/views/woocommerce/single-product.blade.php

  Purpose:
  - Provide the WooCommerce single product wrapper for Sage/Blade.
  - Keep the single product page inside the normal Sage layout so the site
    header, shop nav, footer, scripts, and global theme structure remain intact.
  - Add the SCCC single-product container around WooCommerce's main product
    content so the product layout no longer stretches edge-to-edge.
  - Theme WooCommerce notices/messages that render above the product content.
  - Delegate the actual product layout to:
    resources/views/woocommerce/content-single-product.blade.php

  Why this file exists:
  - generoi/sage-woocommerce bridges WooCommerce templates into Blade.
  - This wrapper should stay thin and should not build the full product layout.
  - The product design, gallery, variation controls, and tabs belong in the
    content-single-product.blade.php partial.
  - WooCommerce notices are often printed by Woo hooks around the main content,
    so the wrapper is the safest place to style those messages consistently.

  Important:
  - Do not build the full product design here.
  - Do not remove the WooCommerce before/after main-content hooks.
  - Do not move the custom product gallery/variation UI into this wrapper.
  - This file does not change product prices.
  - This file does not alter cart or checkout behavior.
--}}

@extends('layouts.app')

@section('content')
  @php
    /*
     * WooCommerce compatibility hook.
     *
     * Why:
     * The Sage layout already renders the real site header, but this action is
     * kept because WooCommerce/extensions may listen for it.
     */
    do_action('get_header', 'shop');
  @endphp

  <section
    class="sccc-single-product-page"
    aria-label="{{ esc_attr__('Product details', 'sage') }}"
  >
    <div class="sccc-single-product-page__container">
      @php
        /*
         * WooCommerce main content opening hook.
         *
         * Why:
         * This preserves WooCommerce breadcrumbs/notices/wrapper behavior while
         * keeping those elements inside the SCCC container.
         */
        do_action('woocommerce_before_main_content');
      @endphp

      @while (have_posts())
        @php
          the_post();

          /*
           * WooCommerce loop compatibility.
           *
           * Why:
           * Mirrors the standard Sage WooCommerce single product wrapper while
           * still allowing our content-single-product.blade.php partial to own
           * the visual layout.
           */
          do_action('woocommerce_shop_loop');

          /*
           * Load the actual product content partial.
           *
           * This resolves to:
           * resources/views/woocommerce/content-single-product.blade.php
           */
          wc_get_template_part('content', 'single-product');
        @endphp
      @endwhile

      @php
        /*
         * WooCommerce main content closing hook.
         *
         * Why:
         * This closes whatever WooCommerce opened in
         * woocommerce_before_main_content and keeps the markup balanced.
         */
        do_action('woocommerce_after_main_content');
      @endphp
    </div>
  </section>

  @php
    /*
     * WooCommerce compatibility hooks.
     *
     * Why:
     * These stay outside the product container so the theme/footer structure does
     * not get trapped inside the product layout.
     */
    do_action('get_sidebar', 'shop');
    do_action('get_footer', 'shop');
  @endphp
@endsection

@once
  <style>
    /*
     * File path + filename: resources/views/woocommerce/single-product.blade.php
     *
     * Purpose:
     * - Provide the outer spacing and max-width container for the single product
     *   page.
     * - Theme WooCommerce notices/messages that appear above product content.
     *
     * Why this lives here:
     * - The wrapper owns the page container.
     * - WooCommerce notices can render before the product partial, so styling
     *   them here keeps messages inside the SCCC product page treatment.
     * - The partial still owns the product card/gallery/variation styling.
     */

    .sccc-single-product-page {
      --sccc-single-container: 80rem;

      /*
       * Notice design tokens.
       *
       * Why:
       * These are scoped to the single product page so message styling can be
       * adjusted without affecting unrelated theme notices elsewhere.
       */
      --sccc-notice-bg:
        radial-gradient(
          520px 180px at 0% 0%,
          color-mix(in oklab, var(--color-primary-500) 12%, transparent),
          transparent 68%
        ),
        radial-gradient(
          480px 180px at 100% 0%,
          color-mix(in oklab, var(--color-accent-500) 10%, transparent),
          transparent 70%
        ),
        color-mix(in oklab, var(--color-surface) 86%, transparent);
      --sccc-notice-border: color-mix(in oklab, var(--color-line) 78%, transparent);
      --sccc-notice-text: var(--color-text);
      --sccc-notice-muted: var(--color-muted);
      --sccc-notice-shadow:
        0 18px 44px rgb(0 0 0 / 0.2),
        0 0 24px color-mix(in oklab, var(--color-primary-500) 12%, transparent),
        inset 0 1px 0 rgb(255 255 255 / 0.08);
      --sccc-notice-gradient-border: linear-gradient(
        135deg,
        rgba(113, 215, 255, 0.9) 0%,
        rgba(67, 190, 255, 0.58) 42%,
        rgba(174, 113, 255, 0.82) 100%
      );
      --sccc-notice-success: #8aa51f;
      --sccc-notice-info: var(--color-primary-500);
      --sccc-notice-error: #ef4444;

      position: relative;
      isolation: isolate;
      padding-block: clamp(1rem, 2vw, 1.5rem) clamp(3rem, 5vw, 5rem);
      overflow: clip;
    }

    html[data-theme="light"] .sccc-single-product-page {
      --sccc-notice-bg:
        linear-gradient(
          135deg,
          rgba(255, 255, 255, 0.94),
          color-mix(in oklab, #ffffff 84%, var(--color-primary-500) 16%)
        ),
        radial-gradient(
          520px 180px at 0% 0%,
          color-mix(in oklab, var(--color-primary-500) 10%, transparent),
          transparent 68%
        ),
        radial-gradient(
          480px 180px at 100% 0%,
          color-mix(in oklab, var(--color-accent-500) 9%, transparent),
          transparent 70%
        );
      --sccc-notice-border: color-mix(in oklab, var(--color-primary-500) 24%, var(--color-line));
      --sccc-notice-shadow:
        0 18px 44px rgba(15, 23, 42, 0.12),
        0 0 24px color-mix(in oklab, var(--color-primary-500) 10%, transparent),
        inset 0 1px 0 rgba(255, 255, 255, 0.86);
    }

    .sccc-single-product-page__container {
      width: min(100% - 2rem, var(--sccc-single-container));
      margin-inline: auto;
    }

    .sccc-single-product-page .woocommerce-breadcrumb {
      margin: 0 0 1rem;
      color: var(--color-muted);
      font-size: 0.82rem;
      line-height: 1.4;
    }

    .sccc-single-product-page .woocommerce-breadcrumb a {
      color: var(--color-muted);
      text-decoration: none;
      transition: color 0.18s ease;
    }

    .sccc-single-product-page .woocommerce-breadcrumb a:hover,
    .sccc-single-product-page .woocommerce-breadcrumb a:focus-visible {
      color: var(--color-accent-500);
      outline: none;
    }

    /*
     * WooCommerce notices wrapper.
     *
     * Why:
     * WooCommerce commonly outputs notices inside .woocommerce-notices-wrapper.
     * We keep spacing here so success/error/info messages sit neatly between
     * breadcrumbs and the product content.
     */
    .sccc-single-product-page .woocommerce-notices-wrapper {
      display: grid;
      gap: 0.85rem;
      margin: 0 0 1.15rem;
    }

    .sccc-single-product-page .woocommerce-notices-wrapper:empty {
      display: none;
    }

    /*
     * Classic WooCommerce notices.
     *
     * Why:
     * WooCommerce classic notices use .woocommerce-message, .woocommerce-info,
     * and .woocommerce-error. These classes are used across cart, product, and
     * checkout flows, but this styling is scoped to the single product page.
     */
    .sccc-single-product-page :is(.woocommerce-message, .woocommerce-info, .woocommerce-error) {
      position: relative;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.7rem 1rem;
      min-height: 4.1rem;
      margin: 0 !important;
      border: 1px solid transparent !important;
      border-radius: 1rem;
      padding: 0.9rem 1rem 0.9rem 3.35rem !important;
      color: var(--sccc-notice-text) !important;
      background:
        linear-gradient(var(--sccc-notice-bg), var(--sccc-notice-bg)) padding-box,
        var(--sccc-notice-gradient-border) border-box !important;
      box-shadow: var(--sccc-notice-shadow);
      list-style: none !important;
      overflow: hidden;
      font-size: 1rem;
      line-height: 1.5;
      font-weight: 650;
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
    }

    .sccc-single-product-page :is(.woocommerce-message, .woocommerce-info, .woocommerce-error)::before {
      position: absolute;
      left: 1rem;
      top: 50%;
      width: 1.45rem;
      height: 1.45rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid transparent;
      border-radius: 999px;
      color: #fff !important;
      background:
        linear-gradient(
          135deg,
          color-mix(in oklab, var(--sccc-notice-current-color, var(--color-primary-500)) 86%, transparent),
          color-mix(in oklab, var(--color-accent-500) 58%, var(--sccc-notice-current-color, var(--color-primary-500)))
        ) padding-box,
        var(--sccc-notice-gradient-border) border-box;
      box-shadow:
        0 0 18px color-mix(in oklab, var(--sccc-notice-current-color, var(--color-primary-500)) 28%, transparent),
        inset 0 1px 0 rgb(255 255 255 / 0.2);
      font-family: inherit !important;
      font-size: 0.82rem;
      font-weight: 900;
      line-height: 1;
      transform: translateY(-50%);
    }

    .sccc-single-product-page :is(.woocommerce-message, .woocommerce-info, .woocommerce-error)::after {
      content: "";
      position: absolute;
      inset: 0 auto 0 0;
      width: 3px;
      background: var(--sccc-notice-gradient-border);
      opacity: 0.95;
      pointer-events: none;
    }

    .sccc-single-product-page .woocommerce-message {
      --sccc-notice-current-color: var(--sccc-notice-success);
    }

    .sccc-single-product-page .woocommerce-message::before {
      content: "✓" !important;
    }

    .sccc-single-product-page .woocommerce-info {
      --sccc-notice-current-color: var(--sccc-notice-info);
    }

    .sccc-single-product-page .woocommerce-info::before {
      content: "i" !important;
    }

    .sccc-single-product-page .woocommerce-error {
      --sccc-notice-current-color: var(--sccc-notice-error);
      align-items: flex-start;
    }

    .sccc-single-product-page .woocommerce-error::before {
      content: "!" !important;
      top: 1.45rem;
      transform: none;
    }

    .sccc-single-product-page .woocommerce-error li {
      width: 100%;
      margin: 0 !important;
      padding: 0 !important;
      color: inherit !important;
      list-style: none !important;
      font-size: 1rem;
      line-height: 1.55;
      font-weight: 650;
    }

    /*
     * WooCommerce notice buttons.
     *
     * Why:
     * The “View cart” link in WooCommerce messages uses .button / .wc-forward.
     * We style it like the rest of the SCCC glass/neon controls while keeping it
     * visually secondary to the message text.
     */
    .sccc-single-product-page :is(.woocommerce-message, .woocommerce-info, .woocommerce-error) :is(.button, .wc-forward) {
      min-height: 2.45rem !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      margin-left: auto !important;
      border: 1px solid transparent !important;
      border-radius: 999px !important;
      padding: 0.7rem 1.05rem !important;
      color: var(--color-text) !important;
      background:
        linear-gradient(
          color-mix(in oklab, var(--color-surface) 78%, transparent),
          color-mix(in oklab, var(--color-surface) 68%, transparent)
        ) padding-box,
        var(--sccc-notice-gradient-border) border-box !important;
      box-shadow:
        0 10px 24px rgb(0 0 0 / 0.14),
        inset 0 1px 0 rgb(255 255 255 / 0.08) !important;
      font-size: 0.84rem !important;
      line-height: 1 !important;
      font-weight: 900 !important;
      text-decoration: none !important;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      transition:
        transform 0.18s ease,
        color 0.18s ease,
        background 0.18s ease,
        box-shadow 0.18s ease,
        filter 0.18s ease !important;
    }

    html[data-theme="light"] .sccc-single-product-page :is(.woocommerce-message, .woocommerce-info, .woocommerce-error) :is(.button, .wc-forward) {
      background:
        linear-gradient(
          rgba(255, 255, 255, 0.92),
          color-mix(in oklab, #ffffff 78%, var(--color-primary-500) 22%)
        ) padding-box,
        var(--sccc-notice-gradient-border) border-box !important;
      box-shadow:
        0 10px 24px rgba(15, 23, 42, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 0.86) !important;
    }

    .sccc-single-product-page :is(.woocommerce-message, .woocommerce-info, .woocommerce-error) :is(.button, .wc-forward):hover,
    .sccc-single-product-page :is(.woocommerce-message, .woocommerce-info, .woocommerce-error) :is(.button, .wc-forward):focus-visible {
      color: #fff !important;
      background:
        linear-gradient(
          135deg,
          color-mix(in oklab, var(--color-primary-500) 84%, transparent),
          color-mix(in oklab, var(--color-accent-500) 58%, var(--color-primary-500))
        ) padding-box,
        var(--sccc-notice-gradient-border) border-box !important;
      box-shadow:
        0 16px 34px color-mix(in oklab, var(--color-primary-500) 22%, transparent),
        0 0 24px color-mix(in oklab, var(--color-accent-500) 16%, transparent),
        inset 0 1px 0 rgb(255 255 255 / 0.16) !important;
      transform: translateY(-1px);
      outline: none;
      filter: brightness(1.06);
    }

    /*
     * Links inside notice text.
     *
     * Why:
     * Keeps notice links visible without making them look like default blue
     * browser links.
     */
    .sccc-single-product-page :is(.woocommerce-message, .woocommerce-info, .woocommerce-error) a:not(.button):not(.wc-forward) {
      color: var(--color-accent-500) !important;
      font-weight: 900;
      text-decoration: none;
    }

    .sccc-single-product-page :is(.woocommerce-message, .woocommerce-info, .woocommerce-error) a:not(.button):not(.wc-forward):hover,
    .sccc-single-product-page :is(.woocommerce-message, .woocommerce-info, .woocommerce-error) a:not(.button):not(.wc-forward):focus-visible {
      color: var(--color-primary-500) !important;
      outline: none;
    }

    /*
     * Block-style WooCommerce notices.
     *
     * Why:
     * Depending on WooCommerce settings/templates, notices may render as block
     * notice banners instead of classic notice wrappers. We style both so the
     * theme remains consistent.
     */
    .sccc-single-product-page .wc-block-components-notice-banner {
      position: relative;
      display: flex !important;
      align-items: center;
      gap: 0.7rem 1rem;
      min-height: 4.1rem;
      margin: 0 0 1.15rem !important;
      border: 1px solid transparent !important;
      border-radius: 1rem !important;
      padding: 0.9rem 1rem !important;
      color: var(--sccc-notice-text) !important;
      background:
        linear-gradient(var(--sccc-notice-bg), var(--sccc-notice-bg)) padding-box,
        var(--sccc-notice-gradient-border) border-box !important;
      box-shadow: var(--sccc-notice-shadow);
      overflow: hidden;
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
    }

    .sccc-single-product-page .wc-block-components-notice-banner::after {
      content: "";
      position: absolute;
      inset: 0 auto 0 0;
      width: 3px;
      background: var(--sccc-notice-gradient-border);
      opacity: 0.95;
      pointer-events: none;
    }

    .sccc-single-product-page .wc-block-components-notice-banner > svg {
      width: 1.45rem;
      height: 1.45rem;
      flex: 0 0 auto;
      border-radius: 999px;
      padding: 0.25rem;
      color: #fff !important;
      fill: currentColor !important;
      background:
        linear-gradient(
          135deg,
          color-mix(in oklab, var(--sccc-notice-current-color, var(--color-primary-500)) 86%, transparent),
          color-mix(in oklab, var(--color-accent-500) 58%, var(--sccc-notice-current-color, var(--color-primary-500)))
        );
      box-shadow:
        0 0 18px color-mix(in oklab, var(--sccc-notice-current-color, var(--color-primary-500)) 28%, transparent),
        inset 0 1px 0 rgb(255 255 255 / 0.2);
    }

    .sccc-single-product-page .wc-block-components-notice-banner.is-success {
      --sccc-notice-current-color: var(--sccc-notice-success);
    }

    .sccc-single-product-page .wc-block-components-notice-banner.is-info {
      --sccc-notice-current-color: var(--sccc-notice-info);
    }

    .sccc-single-product-page .wc-block-components-notice-banner.is-error {
      --sccc-notice-current-color: var(--sccc-notice-error);
    }

    .sccc-single-product-page .wc-block-components-notice-banner__content {
      flex: 1 1 auto;
      color: var(--sccc-notice-text) !important;
      font-size: 1rem;
      line-height: 1.5;
      font-weight: 650;
    }

    .sccc-single-product-page .wc-block-components-notice-banner__content a {
      color: var(--color-accent-500) !important;
      font-weight: 900;
      text-decoration: none;
    }

    .sccc-single-product-page .wc-block-components-notice-banner__content a:hover,
    .sccc-single-product-page .wc-block-components-notice-banner__content a:focus-visible {
      color: var(--color-primary-500) !important;
      outline: none;
    }

    @media (max-width: 640px) {
      .sccc-single-product-page__container {
        width: min(100% - 1.25rem, var(--sccc-single-container));
      }

      .sccc-single-product-page :is(.woocommerce-message, .woocommerce-info, .woocommerce-error) {
        align-items: flex-start;
        padding: 0.95rem 1rem 1rem 3.2rem !important;
        font-size: 0.95rem;
      }

      .sccc-single-product-page :is(.woocommerce-message, .woocommerce-info, .woocommerce-error)::before {
        top: 1.45rem;
        transform: none;
      }

      .sccc-single-product-page :is(.woocommerce-message, .woocommerce-info, .woocommerce-error) :is(.button, .wc-forward) {
        width: 100%;
        margin-left: 0 !important;
        margin-top: 0.35rem !important;
      }

      .sccc-single-product-page .wc-block-components-notice-banner {
        align-items: flex-start;
      }

      .sccc-single-product-page .wc-block-components-notice-banner__content {
        font-size: 0.95rem;
      }
    }
  </style>
@endonce