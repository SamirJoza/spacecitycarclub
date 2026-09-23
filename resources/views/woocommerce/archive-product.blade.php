{{--
  File path + filename: resources/views/woocommerce/archive-product.blade.php

  Purpose:
  - Override the default WooCommerce product archive template.
  - Provide a predictable SCCC shop archive structure.
  - Add a left-side filter panel for product search, department/category, price,
    Colors, and Sizes.
  - Keep the product grid and pagination in the existing styled archive area.

  Why this file exists:
  - WooCommerce’s default archive is currently rendered from the plugin.
  - We need predictable SCCC-specific classes for shop layout and styling.
  - The shop navigation already lives in the site header, so this template only
    controls the archive content below the breadcrumbs.

  Current update:
  - Adds a two-column archive layout:
    - left: shop filters
    - right: product grid + pagination
  - Keeps result count and catalog ordering in a toolbar above the filter/grid
    layout.
  - Removes the loose get_sidebar/get_footer compatibility calls because Sage
    already handles site layout/footer through layouts.app, and the shop filter
    sidebar is now intentionally rendered inside this template.

  Important:
  - This file does not render the shop navigation.
  - This file does not change member-only product logic.
  - This file does not change Printify pricing.
  - This file does not replace WooCommerce’s product query. It uses Woo-compatible
    query vars rendered by the filter partial.
--}}

@extends('layouts.app')

@section('content')
  @php
    /*
     * Keep WooCommerce's shop header hook.
     *
     * Why:
     * Some WooCommerce extensions listen for this action. Sage handles the real
     * site header through layouts.app, so this action is kept for compatibility,
     * not for rendering a duplicate header.
     */
    do_action('get_header', 'shop');

    /*
     * Keep WooCommerce content/breadcrumb wrapper hook.
     *
     * Why:
     * Our ShopNavigation support class already moves/stylizes breadcrumbs.
     * Keeping this hook here lets that behavior continue to work.
     */
    do_action('woocommerce_before_main_content');
  @endphp

  <section
    class="sccc-shop-archive"
    aria-label="{{ esc_attr__('Shop products', 'sage') }}"
  >
    <div class="sccc-shop-archive__container">
      @if (apply_filters('woocommerce_show_page_title', true))
        <header class="sccc-shop-archive__header">
          <h1 class="sccc-shop-archive__title">
            {!! woocommerce_page_title(false) !!}
          </h1>
        </header>
      @endif

      @php
        /*
         * Archive descriptions.
         *
         * Why:
         * WooCommerce uses this hook for shop/category/tag descriptions.
         * We keep it so category descriptions can still be used later.
         */
        do_action('woocommerce_archive_description');
      @endphp

      @if (woocommerce_product_loop())
        <div class="sccc-shop-archive__toolbar">
          <div class="sccc-shop-archive__toolbar-start">
            @php
              /*
               * Render the result count directly.
               *
               * Why:
               * Calling it directly inside this wrapper gives us clean left
               * alignment under the breadcrumbs and avoids default WooCommerce
               * float/layout behavior.
               */
              if (function_exists('woocommerce_result_count')) {
                  woocommerce_result_count();
              }
            @endphp
          </div>

          <div class="sccc-shop-archive__toolbar-end">
            @php
              /*
               * Render catalog ordering directly.
               *
               * Why:
               * This keeps the sorting dropdown tied to the right side of the
               * same toolbar row while keeping the markup predictable.
               */
              if (function_exists('woocommerce_catalog_ordering')) {
                  woocommerce_catalog_ordering();
              }
            @endphp
          </div>
        </div>

        <div class="sccc-shop-archive__layout">
          <aside
            class="sccc-shop-archive__filters"
            aria-label="{{ esc_attr__('Shop filters', 'sage') }}"
          >
            @include('woocommerce.partials.shop-filters')
          </aside>

          <div class="sccc-shop-archive__results">
            <ul class="products sccc-shop-archive__grid" role="list">
              @if (wc_get_loop_prop('total'))
                @while (have_posts())
                  @php
                    the_post();

                    /*
                     * Keep this hook for WooCommerce/extensions that need to run
                     * per product during archive loops.
                     */
                    do_action('woocommerce_shop_loop');
                  @endphp

                  @include('woocommerce.content-product')
                @endwhile
              @endif
            </ul>

            <div class="sccc-shop-archive__pagination">
              @php
                /*
                 * After-loop output.
                 *
                 * Default WooCommerce hooks here usually include pagination.
                 */
                do_action('woocommerce_after_shop_loop');
              @endphp
            </div>
          </div>
        </div>
      @else
        <div class="sccc-shop-archive__empty">
          @php
            /*
             * No-products output.
             *
             * Why:
             * Keeps WooCommerce's default empty archive message behavior.
             */
            do_action('woocommerce_no_products_found');
          @endphp
        </div>
      @endif
    </div>
  </section>

  @php
    /*
     * Keep WooCommerce closing/content wrapper hooks.
     *
     * Why:
     * These mirror WooCommerce's archive template structure and preserve
     * compatibility with extensions.
     */
    do_action('woocommerce_after_main_content');
  @endphp
@endsection