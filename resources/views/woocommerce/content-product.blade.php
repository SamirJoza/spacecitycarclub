{{--
  File path + filename: resources/views/woocommerce/content-product.blade.php

  Purpose:
  - Render one product card inside the custom SCCC WooCommerce product loop.
  - Keep WooCommerce product behavior intact while giving the listing predictable
    SCCC-specific markup for styling.
  - Support both archive product cards and recommendation product cards rendered
    inside the single product page sections.

  Why this file exists:
  - WooCommerce's default product loop markup is not structured enough for the
    custom SCCC shop card design.
  - This template controls each loop card only. It does not change product
    pricing, member-only visibility, Printify data, checkout behavior, or cart
    behavior.
  - The same WooCommerce loop card can be reused in multiple places, including:
    shop archives, product category archives, upsells, and related products.

  Current update:
  - Builds the WooCommerce loop add-to-cart anchor directly from WooCommerce's
    normal product methods and filter pattern.
  - Adds `data-sccc-button-patched="1"` to the generated anchor as a quick
    rendered-source marker so we can confirm this exact template is active.
  - Adds inline glass/neon button styling directly to the generated anchor.
  - This bypasses stylesheet order and specificity issues.
  - Decodes HTML entities in product titles and category labels before output.
  - Fixes visible strings like "Bottles &amp; Tumblers" so they render as
    "Bottles & Tumblers".
  - Keeps Blade escaping active for display text by decoding first, then
    outputting with {{ }}.

  Important:
  - WooCommerce still decides the product URL, add-to-cart URL, button label,
    product type, purchasable state, stock state, and AJAX support.
  - The button output is intentionally printed raw because it is generated from
    escaped WooCommerce values and mirrors WooCommerce's own loop template output.
--}}

@php
  global $product;

  /*
   * Resolve the current WooCommerce product object.
   *
   * Why:
   * WooCommerce normally provides $product in the loop, but resolving it here
   * makes this template safer if the loop context changes.
   */
  if (! $product instanceof \WC_Product) {
      $product = function_exists('wc_get_product')
          ? wc_get_product(get_the_ID())
          : null;
  }

  $isValidProduct = $product instanceof \WC_Product && $product->is_visible();

  /*
   * Decode display text before Blade escapes it.
   *
   * Why:
   * Some imported Printify/WooCommerce values can contain stored entities such
   * as &amp; or &#038;. If we output those directly through Blade escaping, the
   * visitor can see the literal entity instead of the intended character.
   */
  $displayText = static function ($value): string {
      $value = html_entity_decode(
          (string) $value,
          ENT_QUOTES | ENT_HTML5,
          get_bloginfo('charset') ?: 'UTF-8'
      );

      return trim(wp_strip_all_tags($value));
  };

  /*
   * Convert an array of HTML attributes into a safe string.
   *
   * Why:
   * WooCommerce normally uses wc_implode_html_attributes() inside its loop
   * add-to-cart template. We use it when available and provide a tiny fallback
   * so the template still behaves predictably.
   */
  $implodeAttributes = static function (array $attributes): string {
      $attributes = array_filter(
          $attributes,
          static fn ($value): bool => $value !== null && $value !== false && $value !== ''
      );

      if (function_exists('wc_implode_html_attributes')) {
          return wc_implode_html_attributes($attributes);
      }

      $html = '';

      foreach ($attributes as $attributeName => $attributeValue) {
          $html .= sprintf(
              ' %s="%s"',
              esc_attr((string) $attributeName),
              esc_attr((string) $attributeValue)
          );
      }

      return trim($html);
  };

  $productId = 0;
  $productUrl = '';
  $productTitle = '';
  $productPrice = '';
  $productImage = '';
  $productCategoryLabel = '';
  $isOnSale = false;
  $productClassAttribute = 'class="product sccc-product-card"';
  $addToCartHtml = '';

  if ($isValidProduct) {
      $productId = (int) $product->get_id();
      $productUrl = (string) $product->get_permalink();
      $productTitle = $displayText($product->get_name() ?: get_the_title($productId));
      $productPrice = (string) $product->get_price_html();
      $isOnSale = (bool) $product->is_on_sale();

      /*
       * Capture WooCommerce's product class output.
       *
       * Why:
       * wc_product_class() echoes the full class attribute. Capturing it lets us
       * keep WooCommerce's product classes while still adding our custom card
       * class.
       */
      ob_start();
      wc_product_class('sccc-product-card', $product);
      $capturedProductClassAttribute = trim((string) ob_get_clean());

      if ($capturedProductClassAttribute !== '') {
          $productClassAttribute = $capturedProductClassAttribute;
      }

      /*
       * Product image.
       *
       * Why:
       * Use WooCommerce's product image output so placeholders, image sizes, and
       * product thumbnails continue to behave as expected.
       */
      $productImage = $product->get_image('woocommerce_thumbnail', [
          'class' => 'sccc-product-card__image',
          'loading' => 'lazy',
      ]);

      /*
       * Product category label.
       *
       * Why:
       * The card uses a short eyebrow label. We pick the deepest assigned product
       * category so a product in "Home & Living > Bottles & Tumblers" shows the
       * more specific category label.
       */
      $categoryTerms = get_the_terms($productId, 'product_cat');

      if (! is_wp_error($categoryTerms) && ! empty($categoryTerms)) {
          $categoryTerms = array_values(array_filter(
              $categoryTerms,
              static function ($term): bool {
                  if (! $term instanceof \WP_Term) {
                      return false;
                  }

                  $defaultProductCategoryId = (int) get_option('default_product_cat');

                  return $defaultProductCategoryId <= 0 || (int) $term->term_id !== $defaultProductCategoryId;
              }
          ));

          usort($categoryTerms, static function (\WP_Term $left, \WP_Term $right): int {
              $leftDepth = count(get_ancestors((int) $left->term_id, 'product_cat'));
              $rightDepth = count(get_ancestors((int) $right->term_id, 'product_cat'));

              if ($leftDepth === $rightDepth) {
                  return strcasecmp($left->name, $right->name);
              }

              return $rightDepth <=> $leftDepth;
          });

          if (! empty($categoryTerms) && $categoryTerms[0] instanceof \WP_Term) {
              $productCategoryLabel = $displayText($categoryTerms[0]->name);
          }
      }

      /*
       * Add-to-cart / select-options button.
       *
       * Why:
       * WooCommerce's loop add-to-cart template outputs a single anchor and
       * passes it through the `woocommerce_loop_add_to_cart_link` filter.
       * Earlier stylesheet-based attempts did not visibly affect this anchor,
       * so this version makes the button styling part of the generated anchor.
       *
       * What to check after installing:
       * - Inspect the rendered anchor.
       * - It should include data-sccc-button-patched="1".
       * - If that attribute is missing, this template is not the active source
       *   for the rendered recommendation buttons or a cache is serving old Blade.
       */
      $buttonClasses = [
          'button',
          'sccc-product-card__button',
          'product_type_' . sanitize_html_class($product->get_type()),
      ];

      if ($product->is_purchasable() && $product->is_in_stock()) {
          $buttonClasses[] = 'add_to_cart_button';
      }

      if ($product->supports('ajax_add_to_cart') && $product->is_purchasable() && $product->is_in_stock()) {
          $buttonClasses[] = 'ajax_add_to_cart';
      }

      $buttonClass = implode(' ', array_unique(array_filter($buttonClasses)));

      $buttonInlineStyle = implode('', [
          'position:relative!important;',
          'isolation:isolate!important;',
          'width:fit-content!important;',
          'min-width:8.4rem!important;',
          'min-height:2.45rem!important;',
          'display:inline-flex!important;',
          'align-items:center!important;',
          'justify-content:center!important;',
          'gap:.45rem!important;',
          'margin:0!important;',
          'border:1px solid rgba(113,215,255,.86)!important;',
          'border-radius:999px!important;',
          'padding:.74rem 1.1rem!important;',
          'color:var(--color-text,#f8fafc)!important;',
          'background:linear-gradient(135deg,rgba(32,68,96,.92),rgba(55,45,104,.92))!important;',
          'box-shadow:0 12px 26px rgba(0,0,0,.2),0 0 20px rgba(67,190,255,.18),inset 0 1px 0 rgba(255,255,255,.14)!important;',
          'font-size:.78rem!important;',
          'line-height:1!important;',
          'font-weight:900!important;',
          'text-align:center!important;',
          'text-decoration:none!important;',
          'text-transform:uppercase!important;',
          'letter-spacing:.055em!important;',
          'appearance:none!important;',
          'cursor:pointer!important;',
          'opacity:1!important;',
          'overflow:hidden!important;',
      ]);

      $buttonAttributes = [
          'data-sccc-button-patched' => '1',
          'style' => $buttonInlineStyle,
      ];

      if ($product->is_purchasable() && $product->is_in_stock()) {
          $buttonAttributes['data-product_id'] = (string) $productId;
          $buttonAttributes['data-product_sku'] = (string) $product->get_sku();
          $buttonAttributes['aria-label'] = wp_strip_all_tags($product->add_to_cart_description());
          $buttonAttributes['rel'] = 'nofollow';
      }

      $ariaDescribedbyText = '';

      if (method_exists($product, 'add_to_cart_aria_describedby')) {
          $ariaDescribedbyText = (string) $product->add_to_cart_aria_describedby();
      }

      $ariaDescribedby = $ariaDescribedbyText !== ''
          ? sprintf('aria-describedby="woocommerce_loop_add_to_cart_link_describedby_%s"', esc_attr((string) $productId))
          : '';

      $loopAddToCartArgs = [
          'quantity' => 1,
          'class' => $buttonClass,
          'attributes' => $buttonAttributes,
      ];

      if ($ariaDescribedbyText !== '') {
          $loopAddToCartArgs['aria-describedby_text'] = $ariaDescribedbyText;
      }

      $addToCartHtml = sprintf(
          '<a href="%s" %s data-quantity="%s" class="%s" %s>%s</a>',
          esc_url($product->add_to_cart_url()),
          $ariaDescribedby,
          esc_attr('1'),
          esc_attr($buttonClass),
          $implodeAttributes($buttonAttributes),
          esc_html($product->add_to_cart_text())
      );

      $addToCartHtml = apply_filters(
          'woocommerce_loop_add_to_cart_link',
          $addToCartHtml,
          $product,
          $loopAddToCartArgs
      );

      /*
       * Final forced marker/style pass.
       *
       * Why:
       * Some plugins can replace the entire anchor through the WooCommerce
       * filter above. If that happens, re-add the marker and inline style to
       * the first anchor so the rendered output remains testable and styled.
       */
      if (is_string($addToCartHtml) && str_contains($addToCartHtml, '<a') && ! str_contains($addToCartHtml, 'data-sccc-button-patched=')) {
          $forcedAttributes = sprintf(
              ' data-sccc-button-patched="1" style="%s"',
              esc_attr($buttonInlineStyle)
          );

          $addToCartHtml = preg_replace('/<a\b/', '<a' . $forcedAttributes, $addToCartHtml, 1) ?: $addToCartHtml;
      }

      if ($ariaDescribedbyText !== '') {
          $addToCartHtml .= sprintf(
              '<span id="woocommerce_loop_add_to_cart_link_describedby_%s" class="screen-reader-text">%s</span>',
              esc_attr((string) $productId),
              esc_html($ariaDescribedbyText)
          );
      }

      $addToCartHtml = trim($addToCartHtml);
  }
@endphp

@if ($isValidProduct)
  <li {!! $productClassAttribute !!}>
    <article class="sccc-product-card__inner">
      @if ($isOnSale)
        <span class="sccc-product-card__badge">
          {{ esc_html__('Sale', 'woocommerce') }}
        </span>
      @endif

      <a
        href="{!! esc_url($productUrl) !!}"
        class="sccc-product-card__media-link"
        aria-label="{{ sprintf(__('View product: %s', 'sage'), $productTitle) }}"
      >
        <figure class="sccc-product-card__media">
          {!! wp_kses_post($productImage) !!}
        </figure>
      </a>

      <div class="sccc-product-card__body">
        @if ($productCategoryLabel !== '')
          <p class="sccc-product-card__eyebrow">
            {{ $productCategoryLabel }}
          </p>
        @endif

        <h2 class="woocommerce-loop-product__title sccc-product-card__title">
          <a href="{!! esc_url($productUrl) !!}" class="sccc-product-card__title-link">
            {{ $productTitle }}
          </a>
        </h2>

        @if ($productPrice !== '')
          <div class="price sccc-product-card__price">
            {!! wp_kses_post($productPrice) !!}
          </div>
        @endif
      </div>

      @if ($addToCartHtml !== '')
        <div class="sccc-product-card__actions">
          {!! $addToCartHtml !!}
        </div>
      @endif
    </article>
  </li>
@endif

@once
  <style>
    /*
     * File path + filename: resources/views/woocommerce/content-product.blade.php
     *
     * Product card action button fallback styling.
     *
     * Why this lives here:
     * - This is the template that creates `.sccc-product-card__actions`.
     * - The same card is used on archive pages and inside single-product
     *   recommendation sections.
     * - Inline styling above should visibly style the button even if this CSS
     *   loses the cascade.
     * - This CSS remains as a normal fallback and hover enhancement.
     */

    .sccc-product-card__actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.5rem;
      margin-top: 0.95rem;
    }

    .sccc-product-card__actions > a[data-sccc-button-patched="1"],
    .sccc-product-card__actions > a.sccc-product-card__button,
    .sccc-product-card__actions > a.button {
      position: relative !important;
      isolation: isolate !important;
      width: fit-content !important;
      min-width: 8.4rem !important;
      min-height: 2.45rem !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 0.45rem !important;
      margin: 0 !important;
      border: 1px solid transparent !important;
      border-radius: 999px !important;
      padding: 0.74rem 1.1rem !important;
      color: var(--color-text) !important;
      background:
        linear-gradient(
          color-mix(in oklab, var(--color-surface) 78%, var(--color-primary-500) 18%),
          color-mix(in oklab, var(--color-surface) 72%, var(--color-accent-500) 14%)
        ) padding-box,
        linear-gradient(
          135deg,
          rgba(113, 215, 255, 0.86) 0%,
          rgba(67, 190, 255, 0.56) 42%,
          rgba(174, 113, 255, 0.78) 100%
        ) border-box !important;
      box-shadow:
        0 12px 26px rgb(0 0 0 / 0.2),
        0 0 20px color-mix(in oklab, var(--color-primary-500) 16%, transparent),
        inset 0 1px 0 rgb(255 255 255 / 0.12) !important;
      font-size: 0.78rem !important;
      line-height: 1 !important;
      font-weight: 900 !important;
      text-align: center !important;
      text-decoration: none !important;
      text-transform: uppercase !important;
      letter-spacing: 0.055em !important;
      appearance: none !important;
      cursor: pointer !important;
      opacity: 1 !important;
      overflow: hidden !important;
      transition:
        transform 0.18s ease,
        color 0.18s ease,
        background 0.18s ease,
        box-shadow 0.18s ease,
        filter 0.18s ease !important;
    }

    html[data-theme="light"] .sccc-product-card__actions > a[data-sccc-button-patched="1"],
    html[data-theme="light"] .sccc-product-card__actions > a.sccc-product-card__button,
    html[data-theme="light"] .sccc-product-card__actions > a.button {
      color: color-mix(in oklab, var(--color-text) 94%, #0f172a 6%) !important;
      background:
        linear-gradient(
          color-mix(in oklab, #ffffff 74%, var(--color-primary-500) 26%),
          color-mix(in oklab, #ffffff 80%, var(--color-accent-500) 20%)
        ) padding-box,
        linear-gradient(
          135deg,
          rgba(113, 215, 255, 0.86) 0%,
          rgba(67, 190, 255, 0.56) 42%,
          rgba(174, 113, 255, 0.78) 100%
        ) border-box !important;
      box-shadow:
        0 12px 26px rgba(15, 23, 42, 0.14),
        0 0 20px color-mix(in oklab, var(--color-primary-500) 13%, transparent),
        inset 0 1px 0 rgba(255, 255, 255, 0.9) !important;
    }

    .sccc-product-card__actions > a[data-sccc-button-patched="1"]:hover,
    .sccc-product-card__actions > a[data-sccc-button-patched="1"]:focus-visible,
    .sccc-product-card__actions > a.sccc-product-card__button:hover,
    .sccc-product-card__actions > a.sccc-product-card__button:focus-visible,
    .sccc-product-card__actions > a.button:hover,
    .sccc-product-card__actions > a.button:focus-visible {
      color: #ffffff !important;
      background:
        linear-gradient(
          135deg,
          color-mix(in oklab, var(--color-primary-500) 86%, #0f172a 8%),
          color-mix(in oklab, var(--color-accent-500) 58%, var(--color-primary-500) 42%)
        ) padding-box,
        linear-gradient(
          135deg,
          rgba(113, 215, 255, 0.92) 0%,
          rgba(67, 190, 255, 0.68) 42%,
          rgba(174, 113, 255, 0.86) 100%
        ) border-box !important;
      box-shadow:
        0 16px 34px color-mix(in oklab, var(--color-primary-500) 28%, transparent),
        0 0 26px color-mix(in oklab, var(--color-accent-500) 18%, transparent),
        inset 0 1px 0 rgb(255 255 255 / 0.18) !important;
      transform: translateY(-1px);
      outline: none !important;
      filter: brightness(1.06);
    }

    .sccc-product-card__actions > a.loading,
    .sccc-product-card__actions > a.added {
      opacity: 0.82 !important;
    }

    @media (max-width: 640px) {
      .sccc-product-card__actions > a[data-sccc-button-patched="1"],
      .sccc-product-card__actions > a.sccc-product-card__button,
      .sccc-product-card__actions > a.button {
        width: 100% !important;
      }
    }
  </style>
@endonce