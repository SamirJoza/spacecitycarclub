{{--
  File path + filename: resources/views/woocommerce/content-single-product.blade.php

  Purpose:
  - Render the SCCC single product content partial.
  - Keep WooCommerce's native variation/add-to-cart logic intact.
  - Replace the visual presentation with an SCCC-styled product shell.
  - Prevent Printify gallery images from dumping vertically under the main image.
  - Show color/size variation controls as styled buttons while still updating the
    native WooCommerce variation selects behind the scenes.
  - Restore the WooCommerce "You may also like..." upsells section.
  - Restore the WooCommerce "Related products" section.

  Latest changes:
  - Product description tables now force readable text colors on table cells and
    all nested descendants.
  - Desktop sizing tables use fixed layout, centered TH/TD values, tabular
    numerals, and a stable first-column label width.
  - Mobile sizing tables are now rebuilt into human-readable size cards.
  - Instead of grouping mobile cards by measurement, the mobile layout now groups
    by size. Example: XS card contains Width, Length, Sleeve Length, and Size
    Tolerance.
  - JavaScript reads the original imported table and creates the mobile size-card
    layout without changing the stored WooCommerce product description.

  Important:
  - This file does not change product prices.
  - This file does not modify Printify product data.
  - This file does not replace checkout/cart logic.
  - This file does not hide member-only products.
  - The single product page wrapper/container belongs in:
    resources/views/woocommerce/single-product.blade.php
--}}

@php
  global $product;

  if (! $product instanceof \WC_Product) {
      $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;
  }

  $isValidProduct = $product instanceof \WC_Product;
  $productId = $isValidProduct ? (int) $product->get_id() : 0;

  /*
   * Decode display text before Blade escapes it.
   *
   * Why:
   * Printify/Woo imports may store entities such as &amp;. We decode first,
   * then let Blade escape normally.
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
   * Keep WooCommerce product classes.
   *
   * Why:
   * WooCommerce and extensions often rely on these classes.
   */
  $productClassAttribute = 'class="product sccc-product-single"';

  if ($isValidProduct && function_exists('wc_product_class')) {
      ob_start();
      wc_product_class('sccc-product-single', $product);
      $capturedProductClassAttribute = trim((string) ob_get_clean());

      if ($capturedProductClassAttribute !== '') {
          $productClassAttribute = $capturedProductClassAttribute;
      }
  }

  $productTitle = $isValidProduct ? $displayText($product->get_name() ?: get_the_title($productId)) : '';
  $productPriceHtml = $isValidProduct ? (string) $product->get_price_html() : '';
  $productShortDescription = $isValidProduct ? apply_filters('woocommerce_short_description', $product->get_short_description()) : '';
  $productSku = $isValidProduct ? (string) $product->get_sku() : '';
  $isOnSale = $isValidProduct ? (bool) $product->is_on_sale() : false;

  /*
   * Build image payloads.
   *
   * Why:
   * We only need a default image and the next three static gallery images.
   * The selected variation image will be supplied by WooCommerce's variation JS
   * and swapped into the main image + first thumbnail.
   */
  $imagePayload = static function (int $imageId, string $fallbackAlt = '') use ($displayText): ?array {
      if ($imageId <= 0) {
          return null;
      }

      $imageUrl = wp_get_attachment_image_url($imageId, 'woocommerce_single')
          ?: wp_get_attachment_image_url($imageId, 'large')
          ?: wp_get_attachment_image_url($imageId, 'full');

      if (! $imageUrl) {
          return null;
      }

      $fullUrl = wp_get_attachment_image_url($imageId, 'full') ?: $imageUrl;
      $thumbUrl = wp_get_attachment_image_url($imageId, 'woocommerce_gallery_thumbnail')
          ?: wp_get_attachment_image_url($imageId, 'thumbnail')
          ?: $imageUrl;

      $alt = get_post_meta($imageId, '_wp_attachment_image_alt', true);
      $alt = $displayText($alt !== '' ? $alt : $fallbackAlt);

      return [
          'id' => $imageId,
          'src' => $imageUrl,
          'full' => $fullUrl,
          'thumb' => $thumbUrl,
          'alt' => $alt,
      ];
  };

  $featuredImageId = $isValidProduct ? (int) $product->get_image_id() : 0;
  $mainImage = $imagePayload($featuredImageId, $productTitle);

  if ($mainImage === null && function_exists('wc_placeholder_img_src')) {
      $placeholderSingle = wc_placeholder_img_src('woocommerce_single');
      $placeholderThumb = wc_placeholder_img_src('woocommerce_gallery_thumbnail');

      $mainImage = [
          'id' => 0,
          'src' => $placeholderSingle,
          'full' => $placeholderSingle,
          'thumb' => $placeholderThumb,
          'alt' => $productTitle,
      ];
  }

  /*
   * Static thumbnails.
   *
   * Why:
   * These are intentionally static. We no longer attempt to infer color from
   * filenames, alt text, or upload order. The first thumbnail changes with the
   * selected variation; these three remain predictable product gallery images.
   */
  $staticGalleryItems = [];

  if ($isValidProduct) {
      foreach ($product->get_gallery_image_ids() as $galleryImageId) {
          $galleryImageId = (int) $galleryImageId;

          if ($galleryImageId <= 0 || $galleryImageId === $featuredImageId) {
              continue;
          }

          $payload = $imagePayload($galleryImageId, $productTitle);

          if ($payload === null) {
              continue;
          }

          $staticGalleryItems[] = $payload;

          if (count($staticGalleryItems) >= 3) {
              break;
          }
      }
  }

  /*
   * Server-side variation matrix.
   *
   * Why:
   * WooCommerce may avoid embedding all variations in data-product_variations
   * for products with many combinations. When that happens, the front-end cannot
   * accurately disable impossible combinations from the default Woo JSON alone.
   * This lightweight matrix gives the JS enough data to cross out invalid
   * options like Purple + 5XL without replacing WooCommerce's cart logic.
   */
  $scccVariationMatrix = [];

  if ($isValidProduct && $product->is_type('variable') && method_exists($product, 'get_children')) {
      foreach ($product->get_children() as $variationId) {
          $variation = wc_get_product((int) $variationId);

          if (! $variation instanceof \WC_Product_Variation) {
              continue;
          }

          $variationImage = null;
          $variationImageId = (int) $variation->get_image_id();

          if ($variationImageId > 0) {
              $image = $imagePayload($variationImageId, $productTitle);

              if ($image !== null) {
                  $variationImage = [
                      'image_id' => (int) $image['id'],
                      'id' => (int) $image['id'],
                      'src' => (string) $image['src'],
                      'full_src' => (string) $image['full'],
                      'thumb_src' => (string) $image['thumb'],
                      'gallery_thumbnail_src' => (string) $image['thumb'],
                      'alt' => (string) $image['alt'],
                      'title' => (string) $image['alt'],
                  ];
              }
          }

          $isPurchasable = (bool) $variation->is_purchasable() && (bool) $variation->is_in_stock();

          if (method_exists($variation, 'variation_is_visible')) {
              $isPurchasable = $isPurchasable && (bool) $variation->variation_is_visible();
          }

          if (method_exists($variation, 'variation_is_active')) {
              $isPurchasable = $isPurchasable && (bool) $variation->variation_is_active();
          }

          $scccVariationMatrix[] = [
              'variation_id' => (int) $variation->get_id(),
              'attributes' => $variation->get_variation_attributes(),
              'is_purchasable' => $isPurchasable,
              'is_in_stock' => (bool) $variation->is_in_stock(),
              'variation_is_active' => method_exists($variation, 'variation_is_active')
                  ? (bool) $variation->variation_is_active()
                  : true,
              'variation_is_visible' => method_exists($variation, 'variation_is_visible')
                  ? (bool) $variation->variation_is_visible()
                  : true,
              'image' => $variationImage,
          ];
      }
  }

  $scccVariationMatrixJson = wp_json_encode($scccVariationMatrix);

  if (! is_string($scccVariationMatrixJson)) {
      $scccVariationMatrixJson = '[]';
  }

  /*
   * Product category links.
   */
  $categoryLinks = [];

  if ($isValidProduct) {
      $categoryTerms = get_the_terms($productId, 'product_cat');

      if (! is_wp_error($categoryTerms) && ! empty($categoryTerms)) {
          foreach ($categoryTerms as $categoryTerm) {
              if (! $categoryTerm instanceof \WP_Term) {
                  continue;
              }

              $defaultProductCategoryId = (int) get_option('default_product_cat');

              if ($defaultProductCategoryId > 0 && (int) $categoryTerm->term_id === $defaultProductCategoryId) {
                  continue;
              }

              $termLink = get_term_link($categoryTerm);

              if (is_wp_error($termLink)) {
                  continue;
              }

              $categoryLinks[] = [
                  'label' => $displayText($categoryTerm->name),
                  'url' => (string) $termLink,
              ];
          }
      }
  }

  /*
   * WooCommerce upsells and related products.
   *
   * Why:
   * Our custom template intentionally avoided the full
   * woocommerce_after_single_product_summary hook earlier so we could control
   * where tabs appeared and avoid dumping default sections in the wrong place.
   * Now we explicitly render the two product recommendation sections in a
   * controlled SCCC wrapper.
   */
  $upsellProductsHtml = '';
  $relatedProductsHtml = '';

  if ($isValidProduct) {
      if (function_exists('woocommerce_upsell_display')) {
          ob_start();
          woocommerce_upsell_display(4, 4);
          $upsellProductsHtml = trim((string) ob_get_clean());
      }

      if (function_exists('woocommerce_related_products')) {
          ob_start();
          woocommerce_related_products([
              'posts_per_page' => 4,
              'columns' => 4,
              'orderby' => 'rand',
          ]);
          $relatedProductsHtml = trim((string) ob_get_clean());
      } elseif (function_exists('woocommerce_output_related_products')) {
          ob_start();
          woocommerce_output_related_products();
          $relatedProductsHtml = trim((string) ob_get_clean());
      }
  }
@endphp

@if ($isValidProduct)
  @php
    /*
     * Keep WooCommerce notices and validation messages.
     */
    do_action('woocommerce_before_single_product');
  @endphp

  @if (post_password_required())
    <div class="sccc-product-single__password">
      {!! get_the_password_form() !!}
    </div>
  @else
    <article
      id="product-{{ $productId }}"
      {!! $productClassAttribute !!}
      data-sccc-product-single
      data-sccc-variation-matrix="{{ esc_attr($scccVariationMatrixJson) }}"
    >
      <div class="sccc-product-single__shell">
        <div class="sccc-product-single__grid">
          <section
            class="sccc-product-single__gallery"
            data-sccc-product-gallery
            aria-label="{{ esc_attr__('Product images', 'sage') }}"
          >
            <figure class="sccc-product-single__main-media">
              @if (!empty($mainImage))
                <a
                  href="{{ esc_url($mainImage['full']) }}"
                  class="sccc-product-single__main-link"
                  data-sccc-main-link
                >
                  <img
                    src="{{ esc_url($mainImage['src']) }}"
                    alt="{{ esc_attr($mainImage['alt']) }}"
                    class="sccc-product-single__main-image"
                    data-sccc-main-image
                    data-default-src="{{ esc_url($mainImage['src']) }}"
                    data-default-full="{{ esc_url($mainImage['full']) }}"
                    data-default-thumb="{{ esc_url($mainImage['thumb']) }}"
                    data-default-alt="{{ esc_attr($mainImage['alt']) }}"
                    data-default-image-id="{{ esc_attr((string) $mainImage['id']) }}"
                    loading="eager"
                  >
                </a>
              @endif

              @if ($isOnSale)
                <span class="sccc-product-single__sale-badge">
                  {{ esc_html__('Sale', 'woocommerce') }}
                </span>
              @endif
            </figure>

            @if (!empty($mainImage) || !empty($staticGalleryItems))
              <div
                class="sccc-product-single__thumbs"
                data-sccc-gallery-thumbs
                aria-label="{{ esc_attr__('Product image thumbnails', 'sage') }}"
              >
                @if (!empty($mainImage))
                  <button
                    type="button"
                    class="sccc-product-single__thumb sccc-product-single__thumb--dynamic is-active"
                    data-sccc-gallery-thumb
                    data-sccc-variation-thumb
                    data-image-id="{{ esc_attr((string) $mainImage['id']) }}"
                    data-src="{{ esc_url($mainImage['src']) }}"
                    data-full="{{ esc_url($mainImage['full']) }}"
                    data-thumb="{{ esc_url($mainImage['thumb']) }}"
                    data-alt="{{ esc_attr($mainImage['alt']) }}"
                    aria-label="{{ esc_attr__('View selected product image', 'sage') }}"
                  >
                    <img
                      src="{{ esc_url($mainImage['thumb']) }}"
                      alt=""
                      class="sccc-product-single__thumb-image"
                      data-sccc-variation-thumb-image
                      loading="lazy"
                    >
                  </button>
                @endif

                @foreach ($staticGalleryItems as $index => $galleryItem)
                  <button
                    type="button"
                    class="sccc-product-single__thumb"
                    data-sccc-gallery-thumb
                    data-image-id="{{ esc_attr((string) $galleryItem['id']) }}"
                    data-src="{{ esc_url($galleryItem['src']) }}"
                    data-full="{{ esc_url($galleryItem['full']) }}"
                    data-thumb="{{ esc_url($galleryItem['thumb']) }}"
                    data-alt="{{ esc_attr($galleryItem['alt']) }}"
                    aria-label="{{ esc_attr(sprintf(__('View product gallery image %d', 'sage'), $index + 1)) }}"
                  >
                    <img
                      src="{{ esc_url($galleryItem['thumb']) }}"
                      alt=""
                      class="sccc-product-single__thumb-image"
                      loading="lazy"
                    >
                  </button>
                @endforeach
              </div>
            @endif
          </section>

          <section class="sccc-product-single__summary" aria-label="{{ esc_attr__('Product summary', 'sage') }}">
            <div class="sccc-product-single__summary-card">
              <p class="sccc-product-single__eyebrow">
                {{ __('Shop Merchandise', 'sage') }}
              </p>

              <h1 class="product_title entry-title sccc-product-single__title">
                {{ $productTitle }}
              </h1>

              @if ($productPriceHtml !== '')
                <div class="price sccc-product-single__price">
                  {!! wp_kses_post($productPriceHtml) !!}
                </div>
              @endif

              @if ($productShortDescription !== '')
                <div class="woocommerce-product-details__short-description sccc-product-single__short-description">
                  {!! wp_kses_post($productShortDescription) !!}
                </div>
              @endif

              <div class="sccc-product-single__cart">
                @php
                  /*
                   * Render only WooCommerce's native add-to-cart form.
                   *
                   * Why:
                   * This preserves the variation engine while avoiding duplicate
                   * default summary output.
                   */
                  woocommerce_template_single_add_to_cart();
                @endphp
              </div>

              <div class="sccc-product-single__meta">
                @if ($productSku !== '')
                  <div class="sccc-product-single__meta-row">
                    <span>{{ __('SKU', 'sage') }}</span>
                    <strong>{{ $displayText($productSku) }}</strong>
                  </div>
                @endif

                @if (! empty($categoryLinks))
                  <div class="sccc-product-single__meta-row">
                    <span>{{ __('Categories', 'sage') }}</span>

                    <div class="sccc-product-single__category-list">
                      @foreach ($categoryLinks as $categoryLink)
                        <a href="{{ esc_url($categoryLink['url']) }}">
                          {{ $categoryLink['label'] }}
                        </a>
                      @endforeach
                    </div>
                  </div>
                @endif
              </div>
            </div>
          </section>
        </div>
      </div>

      <section class="sccc-product-single__details">
        @php
          /*
           * Render product tabs only.
           *
           * Why:
           * The full woocommerce_after_single_product_summary hook also outputs
           * upsells and related products. We render those separately below so
           * their placement and styling stay controlled.
           */
          if (function_exists('woocommerce_output_product_data_tabs')) {
              woocommerce_output_product_data_tabs();
          }
        @endphp
      </section>

      @if ($upsellProductsHtml !== '' || $relatedProductsHtml !== '')
        <section
          class="sccc-product-single__recommendations"
          aria-label="{{ esc_attr__('Product recommendations', 'sage') }}"
        >
          @if ($upsellProductsHtml !== '')
            <div class="sccc-product-single__recommendation-section sccc-product-single__recommendation-section--upsells">
              {!! $upsellProductsHtml !!}
            </div>
          @endif

          @if ($relatedProductsHtml !== '')
            <div class="sccc-product-single__recommendation-section sccc-product-single__recommendation-section--related">
              {!! $relatedProductsHtml !!}
            </div>
          @endif
        </section>
      @endif
    </article>

    @php
      do_action('woocommerce_after_single_product');
    @endphp
  @endif
@endif

@once
  <style>
    /*
     * File path + filename: resources/views/woocommerce/content-single-product.blade.php
     *
     * Scoped single product styling.
     */

    .sccc-single-product-page {
      --sccc-single-card-bg:
        radial-gradient(
          620px 320px at 8% 0%,
          color-mix(in oklab, var(--color-primary-500) 12%, transparent),
          transparent 64%
        ),
        radial-gradient(
          520px 300px at 96% 4%,
          color-mix(in oklab, var(--color-accent-500) 9%, transparent),
          transparent 66%
        ),
        color-mix(in oklab, var(--color-surface) 88%, transparent);
      --sccc-single-card-border: color-mix(in oklab, var(--color-line) 86%, transparent);
      --sccc-single-card-shadow:
        0 22px 60px rgb(0 0 0 / 0.2),
        inset 0 1px 0 rgb(255 255 255 / 0.06);
      --sccc-single-field-bg: color-mix(in oklab, var(--color-surface) 74%, transparent);
      --sccc-single-gradient-border: linear-gradient(
        135deg,
        rgba(113, 215, 255, 0.86) 0%,
        rgba(67, 190, 255, 0.56) 42%,
        rgba(174, 113, 255, 0.78) 100%
      );
    }

    html[data-theme="light"] .sccc-single-product-page {
      --sccc-single-card-bg:
        linear-gradient(rgba(255, 255, 255, 0.94), rgba(255, 255, 255, 0.9)),
        radial-gradient(
          620px 320px at 8% 0%,
          color-mix(in oklab, var(--color-primary-500) 12%, transparent),
          transparent 64%
        ),
        radial-gradient(
          520px 300px at 96% 4%,
          color-mix(in oklab, var(--color-accent-500) 10%, transparent),
          transparent 66%
        );
      --sccc-single-card-border: color-mix(in oklab, var(--color-primary-500) 26%, var(--color-line));
      --sccc-single-card-shadow:
        0 22px 60px rgba(15, 23, 42, 0.12),
        0 0 26px color-mix(in oklab, var(--color-primary-500) 9%, transparent),
        inset 0 1px 0 rgba(255, 255, 255, 0.82);
      --sccc-single-field-bg:
        linear-gradient(
          135deg,
          color-mix(in oklab, #ffffff 78%, var(--color-primary-500) 22%),
          color-mix(in oklab, #ffffff 76%, var(--color-accent-500) 24%)
        );
    }

    .sccc-single-product-page,
    .sccc-single-product-page * {
      box-sizing: border-box;
    }

    .sccc-product-single__shell {
      border: 1px solid var(--sccc-single-card-border);
      border-radius: 1.5rem;
      padding: 1rem;
      background: var(--sccc-single-card-bg);
      box-shadow: var(--sccc-single-card-shadow);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
    }

    .sccc-product-single__grid {
      display: grid;
      grid-template-columns: minmax(0, 1.04fr) minmax(22rem, 0.96fr);
      gap: 1.5rem;
      align-items: start;
    }

    .sccc-product-single__gallery,
    .sccc-product-single__summary {
      min-width: 0;
    }

    .sccc-product-single__main-media {
      position: relative;
      margin: 0;
      border: 1px solid color-mix(in oklab, var(--color-line) 76%, transparent);
      border-radius: 1.25rem;
      background: #fff;
      box-shadow:
        0 18px 44px rgb(0 0 0 / 0.18),
        inset 0 1px 0 rgb(255 255 255 / 0.08);
      overflow: hidden;
    }

    .sccc-product-single__main-link {
      display: block;
    }

    .sccc-product-single__main-image {
      width: 100%;
      aspect-ratio: 1 / 1;
      display: block;
      object-fit: contain;
      background: #fff;
      transition:
        opacity 0.18s ease,
        transform 0.22s ease;
    }

    .sccc-product-single__main-image.is-changing {
      opacity: 0.35;
      transform: scale(0.985);
    }

    .sccc-product-single__sale-badge {
      position: absolute;
      top: 1rem;
      right: 1rem;
      z-index: 2;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      padding: 0.45rem 0.72rem;
      color: #fff;
      background:
        linear-gradient(
          135deg,
          color-mix(in oklab, var(--color-accent-500) 88%, #ffffff 0%),
          color-mix(in oklab, var(--color-primary-500) 76%, #000000 0%)
        );
      box-shadow:
        0 12px 26px color-mix(in oklab, var(--color-accent-500) 24%, transparent),
        inset 0 1px 0 rgb(255 255 255 / 0.16);
      font-size: 0.72rem;
      line-height: 1;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .sccc-product-single__thumbs {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 0.75rem;
      margin-top: 0.85rem;
    }

    .sccc-product-single__thumb {
      min-width: 0;
      border: 1px solid transparent;
      border-radius: 0.9rem;
      padding: 0.25rem;
      background:
        linear-gradient(color-mix(in oklab, var(--color-surface) 78%, transparent), color-mix(in oklab, var(--color-surface) 78%, transparent)) padding-box,
        linear-gradient(135deg, color-mix(in oklab, var(--color-primary-500) 28%, transparent), color-mix(in oklab, var(--color-accent-500) 22%, transparent)) border-box;
      box-shadow:
        0 10px 24px rgb(0 0 0 / 0.14),
        inset 0 1px 0 rgb(255 255 255 / 0.06);
      cursor: pointer;
      transition:
        transform 0.18s ease,
        box-shadow 0.18s ease,
        filter 0.18s ease;
    }

    .sccc-product-single__thumb:hover,
    .sccc-product-single__thumb:focus-visible,
    .sccc-product-single__thumb.is-active {
      transform: translateY(-1px);
      box-shadow:
        0 16px 34px color-mix(in oklab, var(--color-primary-500) 20%, transparent),
        0 0 22px color-mix(in oklab, var(--color-accent-500) 12%, transparent);
      outline: none;
      filter: brightness(1.05);
    }

    .sccc-product-single__thumb-image {
      width: 100%;
      aspect-ratio: 1 / 1;
      display: block;
      object-fit: cover;
      border-radius: 0.68rem;
      background: #fff;
    }

    .sccc-product-single__summary {
      position: sticky;
      top: calc(var(--wp-admin--admin-bar--height, 0px) + 9.25rem);
    }

    .sccc-product-single__summary-card {
      border: 1px solid color-mix(in oklab, var(--color-line) 80%, transparent);
      border-radius: 1.25rem;
      padding: clamp(1rem, 2vw, 1.5rem);
      background:
        radial-gradient(
          440px 240px at 0% 0%,
          color-mix(in oklab, var(--color-primary-500) 10%, transparent),
          transparent 64%
        ),
        color-mix(in oklab, var(--color-surface) 78%, transparent);
      box-shadow:
        0 16px 38px rgb(0 0 0 / 0.14),
        inset 0 1px 0 rgb(255 255 255 / 0.06);
    }

    html[data-theme="light"] .sccc-product-single__summary-card {
      background:
        radial-gradient(
          440px 240px at 0% 0%,
          color-mix(in oklab, var(--color-primary-500) 8%, transparent),
          transparent 64%
        ),
        rgba(255, 255, 255, 0.88);
      box-shadow:
        0 16px 38px rgba(15, 23, 42, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 0.84);
    }

    .sccc-product-single__eyebrow {
      margin: 0 0 0.45rem;
      color: var(--color-accent-500);
      font-size: 0.72rem;
      line-height: 1;
      font-weight: 900;
      letter-spacing: 0.16em;
      text-transform: uppercase;
    }

    .sccc-product-single__title {
      margin: 0;
      color: var(--color-text);
      font-family: var(--font-display);
      font-size: clamp(1.55rem, 2.8vw, 2.35rem);
      line-height: 1.08;
      letter-spacing: -0.035em;
    }

    .sccc-product-single__price {
      margin-top: 0.85rem;
      color: var(--color-accent-500);
      font-size: 1.1rem;
      font-weight: 900;
    }

    .sccc-product-single__price del {
      color: var(--color-muted);
      opacity: 0.76;
      font-weight: 700;
    }

    .sccc-product-single__price ins {
      text-decoration: none;
    }

    .sccc-product-single__short-description {
      margin-top: 1rem;
      color: var(--color-muted);
      font-size: 0.96rem;
      line-height: 1.7;
    }

    .sccc-product-single__cart {
      margin-top: 1.15rem;
    }

    .sccc-product-single__cart form.cart {
      display: grid;
      gap: 0.9rem;
      margin: 0;
    }

    .sccc-product-single__cart .variations_form.sccc-variation-form--enhanced table.variations {
      position: absolute !important;
      width: 1px !important;
      height: 1px !important;
      margin: 0 !important;
      padding: 0 !important;
      overflow: hidden !important;
      clip: rect(0 0 0 0) !important;
      clip-path: inset(50%) !important;
      white-space: nowrap !important;
      border: 0 !important;
    }

    .sccc-product-single__cart table.variations {
      width: 100%;
      border-collapse: collapse;
      margin: 0;
    }

    .sccc-product-single__cart table.variations th,
    .sccc-product-single__cart table.variations td {
      display: block;
      padding: 0;
      text-align: left;
    }

    .sccc-product-single__cart table.variations label {
      display: block;
      margin: 0 0 0.35rem;
      color: var(--color-text);
      font-size: 0.75rem;
      font-weight: 900;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    .sccc-product-single__cart select,
    .sccc-product-single__cart input.qty {
      min-height: 2.7rem;
      border: 1px solid transparent;
      border-radius: 0.9rem;
      padding: 0 0.9rem;
      color: var(--color-text);
      background:
        linear-gradient(color-mix(in oklab, var(--color-surface) 78%, transparent), color-mix(in oklab, var(--color-surface) 78%, transparent)) padding-box,
        var(--sccc-single-gradient-border) border-box;
      box-shadow:
        0 10px 22px rgb(0 0 0 / 0.1),
        inset 0 1px 0 rgb(255 255 255 / 0.06);
      outline: none;
    }

    .sccc-product-single__cart .single_variation_wrap {
      display: grid;
      gap: 0.85rem;
    }

    .sccc-product-single__cart .woocommerce-variation {
      color: var(--color-muted);
    }

    .sccc-product-single__cart .woocommerce-variation-price {
      color: var(--color-accent-500);
      font-weight: 900;
    }

    .sccc-product-single__cart .woocommerce-variation-availability {
      color: var(--color-muted);
      font-size: 0.9rem;
    }

    .sccc-product-single__cart :is(.woocommerce-error, .woocommerce-info, .woocommerce-message),
    .sccc-product-single__cart .woocommerce-variation-availability .stock.out-of-stock {
      position: relative;
      display: grid;
      gap: 0.35rem;
      width: 100%;
      margin: 0.9rem 0 0 !important;
      border: 1px solid transparent !important;
      border-radius: 1rem;
      padding: 0.95rem 1rem 0.95rem 3.1rem !important;
      color: var(--color-text) !important;
      background:
        linear-gradient(
          color-mix(in oklab, var(--color-surface) 82%, transparent),
          color-mix(in oklab, var(--color-surface) 72%, transparent)
        ) padding-box,
        var(--sccc-single-gradient-border) border-box !important;
      box-shadow:
        0 16px 34px rgb(0 0 0 / 0.18),
        0 0 22px color-mix(in oklab, var(--color-primary-500) 14%, transparent),
        inset 0 1px 0 rgb(255 255 255 / 0.08);
      list-style: none !important;
      overflow: hidden;
      font-size: 0.95rem;
      line-height: 1.6;
      font-weight: 700;
    }

    html[data-theme="light"] .sccc-product-single__cart :is(.woocommerce-error, .woocommerce-info, .woocommerce-message),
    html[data-theme="light"] .sccc-product-single__cart .woocommerce-variation-availability .stock.out-of-stock {
      background:
        linear-gradient(
          rgba(255, 255, 255, 0.92),
          color-mix(in oklab, #ffffff 82%, var(--color-primary-500) 18%)
        ) padding-box,
        var(--sccc-single-gradient-border) border-box !important;
      box-shadow:
        0 16px 34px rgba(15, 23, 42, 0.12),
        0 0 22px color-mix(in oklab, var(--color-primary-500) 10%, transparent),
        inset 0 1px 0 rgba(255, 255, 255, 0.86);
    }

    .sccc-product-single__cart :is(.woocommerce-error, .woocommerce-info, .woocommerce-message)::before,
    .sccc-product-single__cart .woocommerce-variation-availability .stock.out-of-stock::before {
      content: "!";
      position: absolute;
      left: 1rem;
      top: 50%;
      width: 1.35rem;
      height: 1.35rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid transparent;
      border-radius: 999px;
      color: #fff !important;
      background:
        linear-gradient(
          135deg,
          color-mix(in oklab, var(--color-primary-500) 84%, transparent),
          color-mix(in oklab, var(--color-accent-500) 60%, var(--color-primary-500))
        ) padding-box,
        var(--sccc-single-gradient-border) border-box;
      box-shadow:
        0 0 18px color-mix(in oklab, var(--color-primary-500) 26%, transparent),
        inset 0 1px 0 rgb(255 255 255 / 0.18);
      font-family: inherit !important;
      font-size: 0.78rem;
      font-weight: 900;
      line-height: 1;
      transform: translateY(-50%);
    }

    .sccc-product-single__cart :is(.woocommerce-error, .woocommerce-info, .woocommerce-message)::after,
    .sccc-product-single__cart .woocommerce-variation-availability .stock.out-of-stock::after {
      content: "";
      position: absolute;
      inset: 0 auto 0 0;
      width: 3px;
      background: var(--sccc-single-gradient-border);
      opacity: 0.95;
    }

    .sccc-product-single__cart :is(.woocommerce-error, .woocommerce-info, .woocommerce-message) li {
      margin: 0 !important;
      padding: 0 !important;
      color: inherit !important;
      list-style: none !important;
      font-size: 0.95rem;
      line-height: 1.6;
      font-weight: 700;
    }

    .sccc-product-single__cart :is(.woocommerce-error, .woocommerce-info, .woocommerce-message) a {
      color: var(--color-accent-500) !important;
      font-weight: 900;
      text-decoration: none;
    }

    .sccc-product-single__cart :is(.woocommerce-error, .woocommerce-info, .woocommerce-message) a:hover,
    .sccc-product-single__cart :is(.woocommerce-error, .woocommerce-info, .woocommerce-message) a:focus-visible {
      color: var(--color-primary-500) !important;
      outline: none;
    }

    .sccc-product-single__cart .quantity {
      display: inline-flex;
      width: fit-content;
    }

    .sccc-product-single__cart .single_add_to_cart_button,
    .sccc-product-single__cart button.button,
    .sccc-product-single__cart .button {
      min-height: 2.75rem !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      border: 1px solid transparent !important;
      border-radius: 999px !important;
      padding: 0.78rem 1.4rem !important;
      color: var(--color-text) !important;
      background:
        linear-gradient(var(--sccc-single-field-bg), var(--sccc-single-field-bg)) padding-box,
        var(--sccc-single-gradient-border) border-box !important;
      box-shadow:
        0 12px 26px rgb(0 0 0 / 0.16),
        inset 0 1px 0 rgb(255 255 255 / 0.08) !important;
      font-size: 0.84rem !important;
      line-height: 1 !important;
      font-weight: 900 !important;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      cursor: pointer;
      transition:
        transform 0.18s ease,
        box-shadow 0.18s ease,
        color 0.18s ease,
        background 0.18s ease,
        filter 0.18s ease !important;
    }

    .sccc-product-single__cart .single_add_to_cart_button:hover,
    .sccc-product-single__cart .single_add_to_cart_button:focus-visible,
    .sccc-product-single__cart button.button:hover,
    .sccc-product-single__cart button.button:focus-visible {
      color: #fff !important;
      background:
        linear-gradient(
          135deg,
          color-mix(in oklab, var(--color-primary-500) 84%, transparent),
          color-mix(in oklab, var(--color-accent-500) 56%, var(--color-primary-500))
        ) !important;
      box-shadow:
        0 18px 38px color-mix(in oklab, var(--color-primary-500) 26%, transparent),
        0 0 24px color-mix(in oklab, var(--color-accent-500) 18%, transparent),
        inset 0 1px 0 rgb(255 255 255 / 0.16) !important;
      transform: translateY(-1px);
      filter: brightness(1.06);
      outline: none;
    }

    .sccc-product-single__cart .single_add_to_cart_button.disabled,
    .sccc-product-single__cart .single_add_to_cart_button:disabled {
      opacity: 0.55 !important;
      cursor: not-allowed !important;
      filter: grayscale(0.25);
      transform: none !important;
    }

    .sccc-product-single__cart .reset_variations {
      display: inline-flex !important;
      width: fit-content;
      margin-top: 0.45rem;
      color: var(--color-muted);
      font-size: 0.82rem;
      font-weight: 800;
    }

    .sccc-product-single__cart .reset_variations:hover,
    .sccc-product-single__cart .reset_variations:focus-visible {
      color: var(--color-accent-500);
      outline: none;
    }

    .sccc-variation-picker {
      display: grid;
      gap: 1rem;
    }

    .sccc-variation-picker__group {
      display: grid;
      gap: 0.55rem;
    }

    .sccc-variation-picker__label {
      margin: 0;
      color: var(--color-text);
      font-size: 0.75rem;
      font-weight: 900;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    .sccc-variation-picker__options {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }

    .sccc-variation-option {
      position: relative;
      min-height: 2.35rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.48rem;
      border: 1px solid transparent;
      border-radius: 999px;
      padding: 0.55rem 0.75rem;
      color: var(--color-muted);
      background:
        linear-gradient(color-mix(in oklab, var(--color-surface) 70%, transparent), color-mix(in oklab, var(--color-surface) 70%, transparent)) padding-box,
        linear-gradient(135deg, color-mix(in oklab, var(--color-line) 90%, transparent), color-mix(in oklab, var(--color-line) 65%, transparent)) border-box;
      box-shadow:
        0 10px 22px rgb(0 0 0 / 0.1),
        inset 0 1px 0 rgb(255 255 255 / 0.06);
      font-size: 0.82rem;
      line-height: 1;
      font-weight: 900;
      cursor: pointer;
      transition:
        color 0.18s ease,
        background 0.18s ease,
        box-shadow 0.18s ease,
        transform 0.18s ease,
        opacity 0.18s ease;
    }

    html[data-theme="light"] .sccc-variation-option {
      background:
        linear-gradient(rgba(255, 255, 255, 0.82), rgba(255, 255, 255, 0.82)) padding-box,
        linear-gradient(135deg, color-mix(in oklab, var(--color-primary-500) 24%, transparent), color-mix(in oklab, var(--color-accent-500) 18%, transparent)) border-box;
    }

    .sccc-variation-option:hover,
    .sccc-variation-option:focus-visible,
    .sccc-variation-option.is-selected {
      color: #fff;
      background:
        linear-gradient(
          135deg,
          color-mix(in oklab, var(--color-primary-500) 84%, transparent),
          color-mix(in oklab, var(--color-accent-500) 56%, var(--color-primary-500))
        ) padding-box,
        linear-gradient(135deg, color-mix(in oklab, var(--color-primary-500) 74%, transparent), color-mix(in oklab, var(--color-accent-500) 64%, transparent)) border-box;
      box-shadow:
        0 14px 28px color-mix(in oklab, var(--color-primary-500) 18%, transparent),
        0 0 18px color-mix(in oklab, var(--color-accent-500) 10%, transparent);
      transform: translateY(-1px);
      outline: none;
    }

    html[data-theme="light"] .sccc-variation-option:hover:not(.is-disabled),
    html[data-theme="light"] .sccc-variation-option:focus-visible:not(.is-disabled) {
      color: #ffffff;
      background:
        linear-gradient(
          135deg,
          color-mix(in oklab, var(--color-primary-500) 88%, #0f172a 12%),
          color-mix(in oklab, var(--color-accent-500) 68%, var(--color-primary-500) 32%)
        ) padding-box,
        var(--sccc-single-gradient-border) border-box;
      box-shadow:
        0 14px 30px color-mix(in oklab, var(--color-primary-500) 22%, transparent),
        0 0 20px color-mix(in oklab, var(--color-accent-500) 16%, transparent),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
    }

    html[data-theme="light"] .sccc-variation-option:hover:not(.is-disabled) .sccc-variation-option__text,
    html[data-theme="light"] .sccc-variation-option:focus-visible:not(.is-disabled) .sccc-variation-option__text {
      color: #ffffff;
      text-shadow: 0 1px 1px rgba(15, 23, 42, 0.35);
    }

    html[data-theme="light"] .sccc-variation-option:hover:not(.is-disabled) .sccc-variation-option__box,
    html[data-theme="light"] .sccc-variation-option:focus-visible:not(.is-disabled) .sccc-variation-option__box {
      border-color: rgba(255, 255, 255, 0.78);
      background:
        linear-gradient(
          rgba(255, 255, 255, 0.16),
          rgba(255, 255, 255, 0.08)
        ) padding-box,
        linear-gradient(
          135deg,
          rgba(255, 255, 255, 0.88),
          rgba(255, 255, 255, 0.42)
        ) border-box;
    }

    html[data-theme="light"] .sccc-variation-option:hover:not(.is-disabled) .sccc-variation-option__swatch,
    html[data-theme="light"] .sccc-variation-option:focus-visible:not(.is-disabled) .sccc-variation-option__swatch {
      border-color: rgba(255, 255, 255, 0.88);
      box-shadow:
        0 0 0 1px rgba(15, 23, 42, 0.18),
        0 0 14px rgba(255, 255, 255, 0.4);
    }

    html[data-theme="light"] .sccc-variation-option.is-selected {
      color: #ffffff;
      background:
        linear-gradient(
          135deg,
          color-mix(in oklab, var(--color-primary-500) 94%, #0f172a 6%),
          color-mix(in oklab, var(--color-accent-500) 74%, var(--color-primary-500) 26%)
        ) padding-box,
        var(--sccc-single-gradient-border) border-box;
      box-shadow:
        0 14px 30px color-mix(in oklab, var(--color-primary-500) 24%, transparent),
        0 0 20px color-mix(in oklab, var(--color-accent-500) 18%, transparent),
        inset 0 1px 0 rgba(255, 255, 255, 0.22);
    }

    html[data-theme="light"] .sccc-variation-option.is-selected .sccc-variation-option__text {
      color: #ffffff;
      text-shadow: 0 1px 1px rgba(15, 23, 42, 0.35);
    }

    html[data-theme="light"] .sccc-variation-option.is-selected .sccc-variation-option__box {
      border-color: rgba(255, 255, 255, 0.82);
      background:
        linear-gradient(
          rgba(255, 255, 255, 0.18),
          rgba(255, 255, 255, 0.08)
        ) padding-box,
        linear-gradient(
          135deg,
          rgba(255, 255, 255, 0.92),
          rgba(255, 255, 255, 0.46)
        ) border-box;
    }

    html[data-theme="light"] .sccc-variation-option.is-selected .sccc-variation-option__box::after {
      background: #ffffff;
      box-shadow: 0 0 10px rgba(255, 255, 255, 0.55);
    }

    html[data-theme="light"] .sccc-variation-option.is-selected .sccc-variation-option__swatch {
      border-color: rgba(255, 255, 255, 0.88);
      box-shadow:
        0 0 0 1px rgba(15, 23, 42, 0.18),
        0 0 14px rgba(255, 255, 255, 0.4);
    }

    .sccc-variation-option.is-disabled {
      opacity: 0.45;
      cursor: not-allowed;
      filter: grayscale(0.35);
    }

    .sccc-variation-option.is-disabled::after {
      content: "";
      position: absolute;
      left: 0.65rem;
      right: 0.65rem;
      top: 50%;
      height: 2px;
      border-radius: 999px;
      background: currentColor;
      transform: rotate(-12deg);
      opacity: 0.88;
      pointer-events: none;
    }

    .sccc-variation-option__box {
      width: 1rem;
      height: 1rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: 0 0 auto;
      border: 1px solid color-mix(in oklab, var(--color-line) 90%, transparent);
      border-radius: 0.3rem;
      background:
        linear-gradient(color-mix(in oklab, var(--color-surface) 72%, transparent), color-mix(in oklab, var(--color-surface) 72%, transparent)) padding-box,
        var(--sccc-single-gradient-border) border-box;
    }

    .sccc-variation-option__box::after {
      content: "";
      width: 0.45rem;
      height: 0.45rem;
      border-radius: 0.14rem;
      background: linear-gradient(135deg, var(--color-primary-500), var(--color-accent-500));
      opacity: 0;
      transform: scale(0.45);
      transition: opacity 0.16s ease, transform 0.16s ease;
    }

    .sccc-variation-option.is-selected .sccc-variation-option__box::after {
      opacity: 1;
      transform: scale(1);
    }

    .sccc-variation-option__swatch {
      width: 1rem;
      height: 1rem;
      border-radius: 999px;
      border: 1px solid rgb(255 255 255 / 0.45);
      background: linear-gradient(135deg, var(--color-primary-500), var(--color-accent-500));
      box-shadow: 0 0 0 1px rgb(0 0 0 / 0.1);
    }

    .sccc-product-single__meta {
      display: grid;
      gap: 0.65rem;
      margin-top: 1.2rem;
      padding-top: 1rem;
      border-top: 1px solid color-mix(in oklab, var(--color-line) 80%, transparent);
    }

    .sccc-product-single__meta-row {
      display: grid;
      gap: 0.22rem;
      color: var(--color-muted);
      font-size: 0.82rem;
      line-height: 1.4;
    }

    .sccc-product-single__meta-row > span {
      color: var(--color-accent-500);
      font-size: 0.68rem;
      font-weight: 900;
      letter-spacing: 0.14em;
      text-transform: uppercase;
    }

    .sccc-product-single__meta-row strong {
      color: var(--color-text);
      font-weight: 800;
    }

    .sccc-product-single__category-list {
      display: flex;
      flex-wrap: wrap;
      gap: 0.35rem;
    }

    .sccc-product-single__category-list a {
      color: var(--color-muted);
      transition: color 0.18s ease;
    }

    .sccc-product-single__category-list a:hover,
    .sccc-product-single__category-list a:focus-visible {
      color: var(--color-accent-500);
      outline: none;
    }

    .sccc-product-single__details {
      margin-top: 2rem;
    }

    .sccc-product-single__details .woocommerce-tabs {
      border: 1px solid var(--sccc-single-card-border);
      border-radius: 1.25rem;
      background: var(--sccc-single-card-bg);
      box-shadow: var(--sccc-single-card-shadow);
      overflow: hidden;
    }

    .sccc-product-single__details .woocommerce-tabs ul.tabs {
      display: flex;
      flex-wrap: wrap;
      gap: 0.35rem;
      margin: 0 !important;
      padding: 0.75rem !important;
      border-bottom: 1px solid color-mix(in oklab, var(--color-line) 82%, transparent);
      list-style: none;
    }

    .sccc-product-single__details .woocommerce-tabs ul.tabs::before,
    .sccc-product-single__details .woocommerce-tabs ul.tabs::after {
      content: none !important;
    }

    .sccc-product-single__details .woocommerce-tabs ul.tabs li {
      border: 0 !important;
      border-radius: 999px !important;
      margin: 0 !important;
      padding: 0 !important;
      background: transparent !important;
    }

    .sccc-product-single__details .woocommerce-tabs ul.tabs li::before,
    .sccc-product-single__details .woocommerce-tabs ul.tabs li::after {
      content: none !important;
    }

    .sccc-product-single__details .woocommerce-tabs ul.tabs li a {
      display: inline-flex !important;
      align-items: center;
      justify-content: center;
      min-height: 2.35rem;
      border: 1px solid transparent;
      border-radius: 999px;
      padding: 0.6rem 0.9rem !important;
      color: var(--color-muted) !important;
      background:
        linear-gradient(color-mix(in oklab, var(--color-surface) 72%, transparent), color-mix(in oklab, var(--color-surface) 72%, transparent)) padding-box,
        linear-gradient(135deg, color-mix(in oklab, var(--color-line) 90%, transparent), color-mix(in oklab, var(--color-line) 65%, transparent)) border-box;
      font-size: 0.82rem;
      font-weight: 900 !important;
      text-decoration: none !important;
    }

    .sccc-product-single__details .woocommerce-tabs ul.tabs li.active a,
    .sccc-product-single__details .woocommerce-tabs ul.tabs li a:hover,
    .sccc-product-single__details .woocommerce-tabs ul.tabs li a:focus-visible {
      color: #fff !important;
      background:
        linear-gradient(
          135deg,
          color-mix(in oklab, var(--color-primary-500) 84%, transparent),
          color-mix(in oklab, var(--color-accent-500) 56%, var(--color-primary-500))
        ) padding-box,
        var(--sccc-single-gradient-border) border-box;
      outline: none;
    }

    .sccc-product-single__details .woocommerce-Tabs-panel {
      padding: clamp(1rem, 2vw, 1.5rem) !important;
      color: var(--color-muted);
      line-height: 1.75;
    }

    .sccc-product-single__details .woocommerce-Tabs-panel h2,
    .sccc-product-single__details .woocommerce-Tabs-panel h3 {
      color: var(--color-text);
      font-family: var(--font-display);
      letter-spacing: -0.02em;
    }

    .sccc-product-single__details .woocommerce-Tabs-panel--description p,
    .sccc-product-single__details .woocommerce-Tabs-panel[id^="tab-description"] p {
      color: var(--color-muted);
      font-size: 1rem;
      line-height: 1.75;
      font-weight: 400;
    }

    .sccc-product-single__details .woocommerce-Tabs-panel--description p:first-of-type,
    .sccc-product-single__details .woocommerce-Tabs-panel[id^="tab-description"] p:first-of-type {
      color: var(--color-muted);
      font-size: 1rem !important;
      line-height: 1.75 !important;
      font-weight: 400 !important;
      letter-spacing: normal !important;
    }

    .sccc-product-single__details .woocommerce-Tabs-panel--description p:first-of-type::first-line,
    .sccc-product-single__details .woocommerce-Tabs-panel[id^="tab-description"] p:first-of-type::first-line {
      font-size: inherit;
      line-height: inherit;
      font-weight: inherit;
      letter-spacing: inherit;
    }

    /*
     * Product description tables.
     *
     * Desktop:
     * - Original table remains visible.
     * - Size columns are centered and aligned.
     *
     * Mobile:
     * - Original imported table is hidden.
     * - JavaScript creates .sccc-product-size-chart-mobile.
     * - Each mobile card is one size with the measurements listed below it.
     */
    .sccc-product-single__details .woocommerce-Tabs-panel {
      --sccc-product-table-bg:
        radial-gradient(
          520px 220px at 0% 0%,
          color-mix(in oklab, var(--color-primary-500) 9%, transparent),
          transparent 68%
        ),
        color-mix(in oklab, #0f172a 82%, var(--color-surface) 18%);
      --sccc-product-table-border: rgba(255, 255, 255, 0.16);
      --sccc-product-table-row-border: rgba(255, 255, 255, 0.13);
      --sccc-product-table-text: rgba(232, 240, 255, 0.86);
      --sccc-product-table-muted: rgba(210, 222, 240, 0.72);
      --sccc-product-table-heading: rgba(255, 255, 255, 0.96);
      --sccc-product-table-row-label: rgba(245, 249, 255, 0.92);
      --sccc-product-table-head-bg: rgba(59, 130, 246, 0.16);
      --sccc-product-table-hover-bg: rgba(59, 130, 246, 0.09);
      --sccc-product-table-first-col-width: 13.5rem;
      overflow-x: auto;
    }

    html[data-theme="light"] .sccc-product-single__details .woocommerce-Tabs-panel {
      --sccc-product-table-bg:
        radial-gradient(
          520px 220px at 0% 0%,
          color-mix(in oklab, var(--color-primary-500) 6%, transparent),
          transparent 68%
        ),
        rgba(255, 255, 255, 0.94);
      --sccc-product-table-border: rgba(15, 23, 42, 0.16);
      --sccc-product-table-row-border: rgba(15, 23, 42, 0.12);
      --sccc-product-table-text: rgba(15, 23, 42, 0.82);
      --sccc-product-table-muted: rgba(15, 23, 42, 0.66);
      --sccc-product-table-heading: rgba(15, 23, 42, 0.96);
      --sccc-product-table-row-label: rgba(15, 23, 42, 0.9);
      --sccc-product-table-head-bg: rgba(59, 130, 246, 0.1);
      --sccc-product-table-hover-bg: rgba(59, 130, 246, 0.07);
    }

    .dark .sccc-product-single__details .woocommerce-Tabs-panel,
    html[data-theme="dark"] .sccc-product-single__details .woocommerce-Tabs-panel {
      --sccc-product-table-bg:
        radial-gradient(
          520px 220px at 0% 0%,
          color-mix(in oklab, var(--color-primary-500) 9%, transparent),
          transparent 68%
        ),
        color-mix(in oklab, #0f172a 82%, var(--color-surface) 18%);
      --sccc-product-table-border: rgba(255, 255, 255, 0.16);
      --sccc-product-table-row-border: rgba(255, 255, 255, 0.13);
      --sccc-product-table-text: rgba(232, 240, 255, 0.86);
      --sccc-product-table-muted: rgba(210, 222, 240, 0.72);
      --sccc-product-table-heading: rgba(255, 255, 255, 0.96);
      --sccc-product-table-row-label: rgba(245, 249, 255, 0.92);
      --sccc-product-table-head-bg: rgba(59, 130, 246, 0.16);
      --sccc-product-table-hover-bg: rgba(59, 130, 246, 0.09);
    }

    .sccc-product-single__details .woocommerce-Tabs-panel table {
      width: 100%;
      min-width: 58rem;
      margin: 1.15rem 0 0;
      border: 1px solid var(--sccc-product-table-border);
      border-radius: 1rem;
      border-collapse: separate;
      border-spacing: 0;
      table-layout: fixed;
      background: var(--sccc-product-table-bg);
      box-shadow:
        0 14px 32px rgb(0 0 0 / 0.14),
        inset 0 1px 0 rgb(255 255 255 / 0.06);
      overflow: hidden;
      color: var(--sccc-product-table-text) !important;
      font-size: 0.92rem;
      line-height: 1.45;
      font-variant-numeric: tabular-nums lining-nums;
      font-feature-settings: "tnum" 1, "lnum" 1;
    }

    html[data-theme="light"] .sccc-product-single__details .woocommerce-Tabs-panel table {
      box-shadow:
        0 14px 32px rgba(15, 23, 42, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.86);
    }

    .sccc-product-single__details .woocommerce-Tabs-panel table :is(th, td) {
      width: auto;
      padding: 0.9rem 0.75rem;
      border: 0;
      border-bottom: 1px solid var(--sccc-product-table-row-border);
      color: var(--sccc-product-table-text) !important;
      -webkit-text-fill-color: var(--sccc-product-table-text) !important;
      text-align: center !important;
      vertical-align: middle;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      font-variant-numeric: inherit;
      font-feature-settings: inherit;
    }

    .sccc-product-single__details .woocommerce-Tabs-panel table :is(th, td) :is(
      a,
      abbr,
      b,
      code,
      em,
      i,
      label,
      mark,
      small,
      span,
      strong,
      sub,
      sup
    ) {
      color: inherit !important;
      -webkit-text-fill-color: inherit !important;
      text-align: inherit !important;
      font-variant-numeric: inherit;
      font-feature-settings: inherit;
    }

    .sccc-product-single__details .woocommerce-Tabs-panel table.sccc-product-table-has-thead tbody tr > :first-child,
    .sccc-product-single__details .woocommerce-Tabs-panel table.sccc-product-table-has-body-header tbody tr:not(:first-child) > :first-child,
    .sccc-product-single__details .woocommerce-Tabs-panel table:not(.sccc-product-table-has-thead):not(.sccc-product-table-has-body-header) tbody tr:not(:first-child) > :first-child,
    .sccc-product-single__details .woocommerce-Tabs-panel table tfoot tr > :first-child {
      width: var(--sccc-product-table-first-col-width);
      min-width: var(--sccc-product-table-first-col-width);
      max-width: var(--sccc-product-table-first-col-width);
      padding-left: 1rem;
      padding-right: 1rem;
      color: var(--sccc-product-table-row-label) !important;
      -webkit-text-fill-color: var(--sccc-product-table-row-label) !important;
      text-align: left !important;
      font-weight: 800;
      text-overflow: clip;
    }

    .sccc-product-single__details .woocommerce-Tabs-panel table.sccc-product-table-has-thead tbody tr > :first-child *,
    .sccc-product-single__details .woocommerce-Tabs-panel table.sccc-product-table-has-body-header tbody tr:not(:first-child) > :first-child *,
    .sccc-product-single__details .woocommerce-Tabs-panel table:not(.sccc-product-table-has-thead):not(.sccc-product-table-has-body-header) tbody tr:not(:first-child) > :first-child *,
    .sccc-product-single__details .woocommerce-Tabs-panel table tfoot tr > :first-child * {
      color: inherit !important;
      -webkit-text-fill-color: inherit !important;
      text-align: inherit !important;
    }

    .sccc-product-single__details .woocommerce-Tabs-panel table thead :is(th, td),
    .sccc-product-single__details .woocommerce-Tabs-panel table.sccc-product-table-has-body-header tbody tr:first-child :is(th, td),
    .sccc-product-single__details .woocommerce-Tabs-panel table:not(.sccc-product-table-has-thead):not(.sccc-product-table-has-body-header) tbody tr:first-child :is(th, td) {
      color: var(--sccc-product-table-heading) !important;
      -webkit-text-fill-color: var(--sccc-product-table-heading) !important;
      background: var(--sccc-product-table-head-bg);
      text-align: center !important;
      font-size: 0.82rem;
      font-weight: 900;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .sccc-product-single__details .woocommerce-Tabs-panel table thead :is(th, td) *,
    .sccc-product-single__details .woocommerce-Tabs-panel table.sccc-product-table-has-body-header tbody tr:first-child :is(th, td) *,
    .sccc-product-single__details .woocommerce-Tabs-panel table:not(.sccc-product-table-has-thead):not(.sccc-product-table-has-body-header) tbody tr:first-child :is(th, td) * {
      color: inherit !important;
      -webkit-text-fill-color: inherit !important;
      text-align: inherit !important;
    }

    .sccc-product-single__details .woocommerce-Tabs-panel table thead tr > :first-child,
    .sccc-product-single__details .woocommerce-Tabs-panel table.sccc-product-table-has-body-header tbody tr:first-child > :first-child,
    .sccc-product-single__details .woocommerce-Tabs-panel table:not(.sccc-product-table-has-thead):not(.sccc-product-table-has-body-header) tbody tr:first-child > :first-child {
      width: var(--sccc-product-table-first-col-width);
      min-width: var(--sccc-product-table-first-col-width);
      max-width: var(--sccc-product-table-first-col-width);
      text-align: center !important;
    }

    .sccc-product-single__details .woocommerce-Tabs-panel table tbody tr:last-child :is(th, td),
    .sccc-product-single__details .woocommerce-Tabs-panel table tfoot tr:last-child :is(th, td) {
      border-bottom: 0;
    }

    .sccc-product-single__details .woocommerce-Tabs-panel table tbody tr:hover :is(th, td) {
      background: var(--sccc-product-table-hover-bg);
      color: var(--sccc-product-table-heading) !important;
      -webkit-text-fill-color: var(--sccc-product-table-heading) !important;
    }

    .sccc-product-single__details .woocommerce-Tabs-panel table tbody tr:hover :is(th, td) * {
      color: inherit !important;
      -webkit-text-fill-color: inherit !important;
      text-align: inherit !important;
    }

    .sccc-product-single__details .woocommerce-Tabs-panel table caption {
      caption-side: bottom;
      padding-top: 0.75rem;
      color: var(--sccc-product-table-muted) !important;
      -webkit-text-fill-color: var(--sccc-product-table-muted) !important;
      font-size: 0.86rem;
      text-align: left;
    }

    .sccc-product-single__details .woocommerce-Tabs-panel table + p {
      margin-top: 1rem;
    }

    .sccc-product-size-chart-mobile {
      display: none;
    }

    @media (max-width: 640px) {
      .sccc-product-single__details .woocommerce-Tabs-panel {
        overflow-x: visible;
      }

      .sccc-product-single__details .woocommerce-Tabs-panel table.sccc-product-table-is-enhanced {
        display: none;
      }

      .sccc-product-size-chart-mobile {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.85rem;
        margin-top: 1rem;
      }

      .sccc-product-size-chart-mobile__card {
        border: 1px solid var(--sccc-product-table-border);
        border-radius: 1rem;
        padding: 0.9rem;
        background: var(--sccc-product-table-bg);
        box-shadow:
          0 14px 28px rgb(0 0 0 / 0.12),
          inset 0 1px 0 rgb(255 255 255 / 0.06);
      }

      .sccc-product-size-chart-mobile__title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin: 0 0 0.75rem;
        padding-bottom: 0.55rem;
        border-bottom: 1px solid var(--sccc-product-table-row-border);
        color: var(--sccc-product-table-heading);
        -webkit-text-fill-color: var(--sccc-product-table-heading);
        font-size: 0.95rem;
        line-height: 1.2;
        font-weight: 950;
        letter-spacing: 0.08em;
        text-transform: uppercase;
      }

      .sccc-product-size-chart-mobile__list {
        display: grid;
        gap: 0.45rem;
        margin: 0;
      }

      .sccc-product-size-chart-mobile__row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 0.75rem;
        align-items: baseline;
        min-width: 0;
        padding: 0.55rem 0.65rem;
        border: 1px solid var(--sccc-product-table-row-border);
        border-radius: 0.72rem;
        background: rgba(255, 255, 255, 0.035);
      }

      html[data-theme="light"] .sccc-product-size-chart-mobile__row {
        background: rgba(15, 23, 42, 0.035);
      }

      .sccc-product-size-chart-mobile__label {
        min-width: 0;
        color: var(--sccc-product-table-muted);
        -webkit-text-fill-color: var(--sccc-product-table-muted);
        font-size: 0.72rem;
        line-height: 1.25;
        font-weight: 850;
        letter-spacing: 0.04em;
        text-transform: uppercase;
      }

      .sccc-product-size-chart-mobile__value {
        color: var(--sccc-product-table-text);
        -webkit-text-fill-color: var(--sccc-product-table-text);
        font-size: 0.9rem;
        line-height: 1.2;
        font-weight: 850;
        text-align: right;
        font-variant-numeric: tabular-nums lining-nums;
        font-feature-settings: "tnum" 1, "lnum" 1;
      }
    }

    .sccc-product-single__details textarea,
    .sccc-product-single__details select,
    .sccc-product-single__details input[type="text"],
    .sccc-product-single__details input[type="email"] {
      border: 1px solid color-mix(in oklab, var(--color-line) 75%, transparent);
      border-radius: 0.9rem;
      padding: 0.75rem 0.9rem;
      color: var(--color-text);
      background: color-mix(in oklab, var(--color-surface) 78%, transparent);
    }

    /*
     * Product recommendation sections.
     *
     * Why:
     * WooCommerce's default upsells and related products are useful, but their
     * default output needs to match the SCCC shop card treatment.
     */
    .sccc-product-single__recommendations {
      display: grid;
      gap: clamp(1.5rem, 3vw, 2.25rem);
      margin-top: clamp(2rem, 4vw, 3rem);
    }

    .sccc-product-single__recommendation-section {
      border: 1px solid var(--sccc-single-card-border);
      border-radius: 1.5rem;
      padding: clamp(1rem, 2vw, 1.35rem);
      background: var(--sccc-single-card-bg);
      box-shadow: var(--sccc-single-card-shadow);
      overflow: hidden;
    }

    .sccc-product-single__recommendation-section :is(.up-sells, .related) > h2 {
      position: relative;
      margin: 0 0 1.1rem;
      padding-left: 0.9rem;
      color: var(--color-text);
      font-family: var(--font-display);
      font-size: clamp(1.45rem, 2.4vw, 2rem);
      line-height: 1.05;
      letter-spacing: -0.03em;
    }

    .sccc-product-single__recommendation-section :is(.up-sells, .related) > h2::before {
      content: "";
      position: absolute;
      left: 0;
      top: 0.15em;
      bottom: 0.12em;
      width: 3px;
      border-radius: 999px;
      background: var(--sccc-single-gradient-border);
      box-shadow: 0 0 16px color-mix(in oklab, var(--color-primary-500) 24%, transparent);
    }

    .sccc-product-single__recommendations ul.products {
      display: grid !important;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: clamp(1rem, 2vw, 1.25rem);
      margin: 0 !important;
      padding: 0 !important;
      list-style: none !important;
    }

    .sccc-product-single__recommendations ul.products::before,
    .sccc-product-single__recommendations ul.products::after {
      content: none !important;
    }

    .sccc-product-single__recommendations ul.products li.product {
      float: none !important;
      width: auto !important;
      margin: 0 !important;
      min-width: 0;
      border: 1px solid color-mix(in oklab, var(--color-line) 78%, transparent);
      border-radius: 1.15rem;
      padding: 0.75rem;
      background:
        radial-gradient(
          360px 180px at 0% 0%,
          color-mix(in oklab, var(--color-primary-500) 10%, transparent),
          transparent 66%
        ),
        color-mix(in oklab, var(--color-surface) 82%, transparent);
      box-shadow:
        0 16px 34px rgb(0 0 0 / 0.16),
        inset 0 1px 0 rgb(255 255 255 / 0.06);
      transition:
        transform 0.18s ease,
        border-color 0.18s ease,
        box-shadow 0.18s ease;
    }

    html[data-theme="light"] .sccc-product-single__recommendations ul.products li.product {
      background:
        radial-gradient(
          360px 180px at 0% 0%,
          color-mix(in oklab, var(--color-primary-500) 8%, transparent),
          transparent 66%
        ),
        rgba(255, 255, 255, 0.9);
      box-shadow:
        0 16px 34px rgba(15, 23, 42, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 0.84);
    }

    .sccc-product-single__recommendations ul.products li.product:hover,
    .sccc-product-single__recommendations ul.products li.product:focus-within {
      transform: translateY(-2px);
      border-color: color-mix(in oklab, var(--color-primary-500) 38%, var(--color-line));
      box-shadow:
        0 20px 44px color-mix(in oklab, var(--color-primary-500) 18%, rgb(0 0 0 / 0.18)),
        0 0 24px color-mix(in oklab, var(--color-accent-500) 12%, transparent),
        inset 0 1px 0 rgb(255 255 255 / 0.08);
    }

    .sccc-product-single__recommendations ul.products li.product a {
      color: inherit;
      text-decoration: none;
    }

    .sccc-product-single__recommendations ul.products li.product img {
      width: 100% !important;
      aspect-ratio: 1 / 1;
      display: block;
      margin: 0 0 0.8rem !important;
      border-radius: 0.9rem;
      object-fit: cover;
      background: #fff;
    }

    .sccc-product-single__recommendations ul.products li.product .woocommerce-loop-product__title,
    .sccc-product-single__recommendations ul.products li.product h2,
    .sccc-product-single__recommendations ul.products li.product h3,
    .sccc-product-single__recommendations .sccc-product-card__title {
      margin: 0.35rem 0 0;
      padding: 0 !important;
      color: var(--color-text);
      font-size: 0.95rem;
      line-height: 1.25;
      font-weight: 900;
      letter-spacing: -0.01em;
    }

    .sccc-product-single__recommendations ul.products li.product .price,
    .sccc-product-single__recommendations .sccc-product-card__price {
      display: block;
      margin-top: 0.6rem;
      color: var(--color-muted);
      font-size: 0.95rem;
      line-height: 1.3;
      font-weight: 800;
    }

    .sccc-product-single__recommendations ul.products li.product .price ins {
      color: var(--color-accent-500);
      text-decoration: none;
    }

    .sccc-product-single__recommendations ul.products li.product .price del {
      color: var(--color-muted);
      opacity: 0.68;
    }

    /*
     * Recommendation buttons.
     *
     * Why:
     * The rendered HTML shows the actual controls are anchor tags inside
     * .sccc-product-card__actions, which lives inside each recommendation
     * section. This direct selector intentionally avoids assumptions about the
     * outer card/list-item classes.
     */
    .sccc-product-single__recommendation-section .sccc-product-card__actions > a,
    .sccc-product-single__recommendation-section--upsells .sccc-product-card__actions > a,
    .sccc-product-single__recommendation-section--related .sccc-product-card__actions > a {
      position: relative !important;
      isolation: isolate !important;
      width: fit-content !important;
      min-width: 8.75rem !important;
      min-height: 2.55rem !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 0.45rem !important;
      margin: 0.95rem 0 0 !important;
      border: 1px solid transparent !important;
      border-radius: 999px !important;
      padding: 0.78rem 1.15rem !important;
      color: var(--color-text) !important;
      background:
        linear-gradient(
          color-mix(in oklab, var(--color-surface) 78%, var(--color-primary-500) 18%),
          color-mix(in oklab, var(--color-surface) 72%, var(--color-accent-500) 14%)
        ) padding-box,
        var(--sccc-single-gradient-border) border-box !important;
      box-shadow:
        0 12px 26px rgb(0 0 0 / 0.22),
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

    html[data-theme="light"] .sccc-product-single__recommendation-section .sccc-product-card__actions > a,
    html[data-theme="light"] .sccc-product-single__recommendation-section--upsells .sccc-product-card__actions > a,
    html[data-theme="light"] .sccc-product-single__recommendation-section--related .sccc-product-card__actions > a {
      color: color-mix(in oklab, var(--color-text) 94%, #0f172a 6%) !important;
      background:
        linear-gradient(
          color-mix(in oklab, #ffffff 74%, var(--color-primary-500) 26%),
          color-mix(in oklab, #ffffff 80%, var(--color-accent-500) 20%)
        ) padding-box,
        var(--sccc-single-gradient-border) border-box !important;
      box-shadow:
        0 12px 26px rgba(15, 23, 42, 0.14),
        0 0 20px color-mix(in oklab, var(--color-primary-500) 13%, transparent),
        inset 0 1px 0 rgba(255, 255, 255, 0.9) !important;
    }

    .sccc-product-single__recommendation-section .sccc-product-card__actions > a:hover,
    .sccc-product-single__recommendation-section .sccc-product-card__actions > a:focus-visible,
    .sccc-product-single__recommendation-section--upsells .sccc-product-card__actions > a:hover,
    .sccc-product-single__recommendation-section--upsells .sccc-product-card__actions > a:focus-visible,
    .sccc-product-single__recommendation-section--related .sccc-product-card__actions > a:hover,
    .sccc-product-single__recommendation-section--related .sccc-product-card__actions > a:focus-visible {
      color: #fff !important;
      background:
        linear-gradient(
          135deg,
          color-mix(in oklab, var(--color-primary-500) 86%, #0f172a 8%),
          color-mix(in oklab, var(--color-accent-500) 58%, var(--color-primary-500) 42%)
        ) padding-box,
        var(--sccc-single-gradient-border) border-box !important;
      box-shadow:
        0 16px 34px color-mix(in oklab, var(--color-primary-500) 28%, transparent),
        0 0 26px color-mix(in oklab, var(--color-accent-500) 18%, transparent),
        inset 0 1px 0 rgb(255 255 255 / 0.18) !important;
      transform: translateY(-1px);
      outline: none !important;
      filter: brightness(1.06);
    }

    .sccc-product-single__recommendation-section .sccc-product-card__actions > a.added_to_cart {
      margin-left: 0.45rem !important;
    }

    @media (max-width: 1100px) {
      .sccc-product-single__recommendations ul.products {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
    }

    @media (max-width: 980px) {
      .sccc-product-single__grid {
        grid-template-columns: 1fr;
      }

      .sccc-product-single__summary {
        position: static;
      }

      .sccc-product-single__recommendations ul.products {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 640px) {
      .sccc-product-single__shell {
        border-radius: 1.15rem;
        padding: 0.75rem;
      }

      .sccc-product-single__thumbs {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }

      .sccc-product-single__title {
        font-size: clamp(1.65rem, 9vw, 2.25rem);
        line-height: 1.08;
      }

      .sccc-product-single__cart .woocommerce-variation-add-to-cart {
        display: grid !important;
        gap: 0.85rem;
        align-items: start;
      }

      .sccc-product-single__cart .quantity {
        margin-bottom: 0 !important;
      }

      .sccc-product-single__cart .single_add_to_cart_button {
        width: 100% !important;
        margin-top: 0 !important;
      }

      .sccc-product-single__recommendations {
        margin-top: 2rem;
      }

      .sccc-product-single__recommendation-section {
        border-radius: 1.15rem;
        padding: 0.85rem;
      }

      .sccc-product-single__recommendations ul.products {
        grid-template-columns: 1fr;
      }

      .sccc-product-single__recommendation-section .sccc-product-card__actions > a,
      .sccc-product-single__recommendation-section--upsells .sccc-product-card__actions > a,
      .sccc-product-single__recommendation-section--related .sccc-product-card__actions > a {
        width: 100% !important;
      }

      .sccc-product-single__recommendation-section .sccc-product-card__actions > a.added_to_cart {
        margin-left: 0 !important;
      }
    }
  </style>

  <script>
    /*
     * File path + filename: resources/views/woocommerce/content-single-product.blade.php
     *
     * Purpose:
     * - Enhance WooCommerce variable product selects with SCCC-styled controls.
     * - Calculate size availability from the exact WooCommerce variation matrix.
     * - Keep unavailable options visible but crossed out.
     * - Update the main image and first thumbnail from the selected variation.
     * - Keep the next three thumbnails static.
     * - Rebuild product description sizing tables into human-readable mobile
     *   size cards.
     */
    (function () {
      const colorMap = {
        black: '#111827',
        white: '#f8fafc',
        gray: '#9ca3af',
        grey: '#9ca3af',
        'heather-gray': '#9ca3af',
        'heather-grey': '#9ca3af',
        'heathered-gray': '#9ca3af',
        'heathered-grey': '#9ca3af',
        silver: '#cbd5e1',
        red: '#ef4444',
        blue: '#2563eb',
        navy: '#172554',
        royal: '#1d4ed8',
        'royal-blue': '#1d4ed8',
        green: '#16a34a',
        'kelly-green': '#15803d',
        olive: '#4d7c0f',
        yellow: '#facc15',
        gold: '#f59e0b',
        orange: '#f97316',
        purple: '#7c3aed',
        pink: '#ec4899',
        brown: '#92400e',
        tan: '#d6b58a',
        beige: '#d6b58a',
        cream: '#fff7ed',
        natural: '#f5e6c8',
        daisy: '#fde68a',
      };

      const ready = (callback) => {
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', callback);
          return;
        }

        callback();
      };

      const slugify = (value) => String(value || '')
        .toLowerCase()
        .trim()
        .replace(/&amp;/g, 'and')
        .replace(/&/g, 'and')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

      const decodeEntities = (value) => {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = String(value || '');

        return textarea.value;
      };

      const getVariationData = (form) => {
        const raw = form.getAttribute('data-product_variations');

        if (!raw || raw === 'false') {
          return [];
        }

        try {
          const parsed = JSON.parse(raw);
          return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
          return [];
        }
      };

      const getScccVariationMatrix = (root) => {
        const raw = root.getAttribute('data-sccc-variation-matrix');

        if (!raw) {
          return [];
        }

        try {
          const parsed = JSON.parse(raw);
          return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
          return [];
        }
      };

      const isColorSelect = (select) => {
        const name = String(select.name || '').toLowerCase();
        const label = String(select.closest('tr')?.querySelector('label')?.textContent || '').toLowerCase();

        return name.includes('color') || name.includes('colour') || label.includes('color') || label.includes('colour');
      };

      const isSizeSelect = (select) => {
        const name = String(select.name || '').toLowerCase();
        const label = String(select.closest('tr')?.querySelector('label')?.textContent || '').toLowerCase();

        return name.includes('size') || label.includes('size');
      };

      const optionLabel = (option) => decodeEntities(option.textContent || option.label || option.value);

      const variationCanBePurchased = (variation) => {
        if (!variation) {
          return false;
        }

        if (variation.is_purchasable === false) {
          return false;
        }

        if (variation.variation_is_active === false || variation.variation_is_visible === false) {
          return false;
        }

        if (variation.is_in_stock === false) {
          return false;
        }

        return true;
      };

      const attributeMatches = (variation, attributeName, selectedValue) => {
        if (!selectedValue) {
          return true;
        }

        const attrs = variation && variation.attributes ? variation.attributes : {};

        if (!Object.prototype.hasOwnProperty.call(attrs, attributeName)) {
          return false;
        }

        const variationValue = attrs[attributeName];

        if (variationValue === '') {
          return true;
        }

        return slugify(variationValue) === slugify(selectedValue);
      };

      /*
       * Build mobile size cards from imported product description tables.
       *
       * Why:
       * Imported Printify size charts are column-based:
       *   XS | S | M | L
       *   Width | 16 | 18 | 20 | 22
       *
       * That works on desktop, but not on narrow phones. On mobile, shoppers need
       * each size grouped together:
       *   XS
       *   Width: 16
       *   Length: 27
       */
      const enhanceProductDescriptionTables = () => {
        document
          .querySelectorAll('.sccc-product-single__details .woocommerce-Tabs-panel table')
          .forEach((table) => {
            const rows = Array.from(table.rows || []);

            if (!rows.length) {
              return;
            }

            const existingMobileChart = table.nextElementSibling;

            if (
              existingMobileChart
              && existingMobileChart.classList
              && existingMobileChart.classList.contains('sccc-product-size-chart-mobile')
            ) {
              existingMobileChart.remove();
            }

            const thead = table.tHead;
            const theadHeaderRow = thead && thead.rows.length ? thead.rows[0] : null;
            const headerRow = theadHeaderRow || rows[0];

            if (!headerRow) {
              return;
            }

            const hasRealThead = Boolean(theadHeaderRow);

            table.classList.add('sccc-product-table-is-enhanced');
            table.classList.toggle('sccc-product-table-has-thead', hasRealThead);
            table.classList.toggle('sccc-product-table-has-body-header', !hasRealThead);

            const headerCells = Array.from(headerRow.cells || []).map((cell) => {
              return String(cell.textContent || '').trim();
            });

            const measurementRows = rows
              .filter((row) => {
                if (!hasRealThead && row === headerRow) {
                  return false;
                }

                if (row.parentElement && row.parentElement.tagName.toLowerCase() === 'thead') {
                  return false;
                }

                return row.cells && row.cells.length > 1;
              })
              .map((row) => {
                const cells = Array.from(row.cells || []);
                const label = String(cells[0]?.textContent || '').trim();

                return {
                  label,
                  cells,
                };
              })
              .filter((rowData) => rowData.label !== '');

            rows.forEach((row) => {
              if (!hasRealThead && row === headerRow) {
                return;
              }

              if (row.parentElement && row.parentElement.tagName.toLowerCase() === 'thead') {
                return;
              }

              Array.from(row.cells || []).forEach((cell, index) => {
                if (index === 0) {
                  return;
                }

                const label = headerCells[index] || '';

                if (label) {
                  cell.setAttribute('data-sccc-column-label', label);
                  return;
                }

                cell.removeAttribute('data-sccc-column-label');
              });
            });

            const sizeLabels = headerCells
              .map((label, index) => {
                return {
                  label,
                  index,
                };
              })
              .filter((item) => item.index > 0 && item.label !== '');

            if (!sizeLabels.length || !measurementRows.length) {
              return;
            }

            const mobileChart = document.createElement('div');
            mobileChart.className = 'sccc-product-size-chart-mobile';
            mobileChart.setAttribute('aria-label', 'Mobile size chart');

            sizeLabels.forEach((size) => {
              const card = document.createElement('section');
              card.className = 'sccc-product-size-chart-mobile__card';

              const title = document.createElement('h3');
              title.className = 'sccc-product-size-chart-mobile__title';
              title.textContent = size.label;

              const list = document.createElement('dl');
              list.className = 'sccc-product-size-chart-mobile__list';

              measurementRows.forEach((measurement) => {
                const value = String(measurement.cells[size.index]?.textContent || '').trim();

                if (value === '') {
                  return;
                }

                const row = document.createElement('div');
                row.className = 'sccc-product-size-chart-mobile__row';

                const term = document.createElement('dt');
                term.className = 'sccc-product-size-chart-mobile__label';
                term.textContent = measurement.label;

                const detail = document.createElement('dd');
                detail.className = 'sccc-product-size-chart-mobile__value';
                detail.textContent = value;

                row.appendChild(term);
                row.appendChild(detail);
                list.appendChild(row);
              });

              if (!list.children.length) {
                return;
              }

              card.appendChild(title);
              card.appendChild(list);
              mobileChart.appendChild(card);
            });

            if (mobileChart.children.length) {
              table.insertAdjacentElement('afterend', mobileChart);
            }
          });
      };

      ready(() => {
        enhanceProductDescriptionTables();

        document.querySelectorAll('[data-sccc-product-single]').forEach((root) => {
          const mainImage = root.querySelector('[data-sccc-main-image]');
          const mainLink = root.querySelector('[data-sccc-main-link]');
          const variationThumb = root.querySelector('[data-sccc-variation-thumb]');
          const variationThumbImage = root.querySelector('[data-sccc-variation-thumb-image]');
          const thumbs = Array.from(root.querySelectorAll('[data-sccc-gallery-thumb]'));
          const form = root.querySelector('form.variations_form');

          const setMainImage = (src, full, alt, imageId) => {
            if (!mainImage || !src) {
              return;
            }

            mainImage.classList.add('is-changing');

            window.setTimeout(() => {
              mainImage.src = src;
              mainImage.alt = alt || mainImage.getAttribute('data-default-alt') || '';

              if (mainLink && full) {
                mainLink.href = full;
              }

              thumbs.forEach((thumb) => {
                const thumbImageId = thumb.getAttribute('data-image-id') || '';
                const thumbSrc = thumb.getAttribute('data-src') || '';

                thumb.classList.toggle(
                  'is-active',
                  (imageId && thumbImageId === String(imageId)) || (!imageId && thumbSrc === src)
                );
              });

              mainImage.classList.remove('is-changing');
            }, 90);
          };

          const updateDynamicThumb = (image) => {
            if (!variationThumb || !variationThumbImage || !image || !image.src) {
              return;
            }

            const src = image.src;
            const full = image.full_src || image.src;
            const thumb = image.gallery_thumbnail_src || image.thumb_src || image.src;
            const alt = image.alt || image.title || '';
            const imageId = image.image_id || image.id || '';

            variationThumb.dataset.src = src;
            variationThumb.dataset.full = full;
            variationThumb.dataset.thumb = thumb;
            variationThumb.dataset.alt = alt;
            variationThumb.dataset.imageId = String(imageId);

            variationThumb.setAttribute('data-src', src);
            variationThumb.setAttribute('data-full', full);
            variationThumb.setAttribute('data-thumb', thumb);
            variationThumb.setAttribute('data-alt', alt);
            variationThumb.setAttribute('data-image-id', String(imageId));

            variationThumbImage.src = thumb;
          };

          const resetDynamicImage = () => {
            if (!mainImage) {
              return;
            }

            const defaultSrc = mainImage.getAttribute('data-default-src') || '';
            const defaultFull = mainImage.getAttribute('data-default-full') || defaultSrc;
            const defaultThumb = mainImage.getAttribute('data-default-thumb') || defaultSrc;
            const defaultAlt = mainImage.getAttribute('data-default-alt') || '';
            const defaultImageId = mainImage.getAttribute('data-default-image-id') || '';

            if (defaultSrc) {
              setMainImage(defaultSrc, defaultFull, defaultAlt, defaultImageId);
            }

            if (variationThumb && variationThumbImage) {
              variationThumb.dataset.src = defaultSrc;
              variationThumb.dataset.full = defaultFull;
              variationThumb.dataset.thumb = defaultThumb;
              variationThumb.dataset.alt = defaultAlt;
              variationThumb.dataset.imageId = defaultImageId;

              variationThumb.setAttribute('data-src', defaultSrc);
              variationThumb.setAttribute('data-full', defaultFull);
              variationThumb.setAttribute('data-thumb', defaultThumb);
              variationThumb.setAttribute('data-alt', defaultAlt);
              variationThumb.setAttribute('data-image-id', defaultImageId);

              variationThumbImage.src = defaultThumb;
            }
          };

          thumbs.forEach((thumb) => {
            thumb.addEventListener('click', () => {
              setMainImage(
                thumb.getAttribute('data-src'),
                thumb.getAttribute('data-full'),
                thumb.getAttribute('data-alt'),
                thumb.getAttribute('data-image-id')
              );
            });
          });

          if (!form) {
            return;
          }

          const selects = Array.from(form.querySelectorAll('select[name^="attribute_"]'));

          if (!selects.length) {
            return;
          }

          const wooVariationData = getVariationData(form);
          const scccVariationMatrix = getScccVariationMatrix(root);
          const variations = scccVariationMatrix.length ? scccVariationMatrix : wooVariationData;

          const groups = selects.map((select) => {
            let type = 'default';

            if (isColorSelect(select)) {
              type = 'color';
            } else if (isSizeSelect(select)) {
              type = 'size';
            }

            return {
              select,
              type,
              name: select.name,
              label: decodeEntities(select.closest('tr')?.querySelector('label')?.textContent || select.name.replace(/^attribute_/, '')),
              buttons: [],
            };
          });

          const selectedAttributes = () => {
            const selected = {};

            groups.forEach((group) => {
              selected[group.name] = group.select.value || '';
            });

            return selected;
          };

          const variationMatchesSelection = (variation, selected, overrideGroup, overrideValue) => {
            return groups.every((group) => {
              const selectedValue = group.name === overrideGroup.name
                ? overrideValue
                : selected[group.name];

              return attributeMatches(variation, group.name, selectedValue);
            });
          };

          const optionIsAvailable = (group, value) => {
            const nativeOption = Array.from(group.select.options).find((option) => option.value === value);

            if (nativeOption && nativeOption.disabled) {
              return false;
            }

            if (!variations.length) {
              return true;
            }

            const selected = selectedAttributes();

            return variations.some((variation) => {
              return variationCanBePurchased(variation) && variationMatchesSelection(variation, selected, group, value);
            });
          };

          const findBestVariationForCurrentSelection = () => {
            if (!variations.length) {
              return null;
            }

            const selected = selectedAttributes();

            return variations.find((variation) => {
              if (!variationCanBePurchased(variation)) {
                return false;
              }

              return groups.every((group) => {
                return attributeMatches(variation, group.name, selected[group.name]);
              });
            }) || null;
          };

          const maybeUpdateImageFromCurrentSelection = () => {
            const selected = selectedAttributes();
            const hasAnySelection = Object.values(selected).some((value) => value !== '');

            if (!hasAnySelection) {
              resetDynamicImage();
              return;
            }

            const bestVariation = findBestVariationForCurrentSelection();

            if (!bestVariation || !bestVariation.image || !bestVariation.image.src) {
              return;
            }

            updateDynamicThumb(bestVariation.image);

            setMainImage(
              bestVariation.image.src,
              bestVariation.image.full_src || bestVariation.image.src,
              bestVariation.image.alt || bestVariation.image.title || '',
              bestVariation.image.image_id || bestVariation.image.id || ''
            );
          };

          const picker = document.createElement('div');
          picker.className = 'sccc-variation-picker';

          groups.forEach((group) => {
            const groupEl = document.createElement('div');
            groupEl.className = 'sccc-variation-picker__group';

            const labelEl = document.createElement('p');
            labelEl.className = 'sccc-variation-picker__label';
            labelEl.textContent = group.label;

            const optionsEl = document.createElement('div');
            optionsEl.className = 'sccc-variation-picker__options';
            optionsEl.setAttribute('role', 'radiogroup');
            optionsEl.setAttribute('aria-label', group.label);

            Array.from(group.select.options).forEach((option) => {
              if (!option.value) {
                return;
              }

              const value = option.value;
              const valueSlug = slugify(value);
              const button = document.createElement('button');

              button.type = 'button';
              button.className = 'sccc-variation-option';
              button.dataset.value = value;
              button.dataset.valueSlug = valueSlug;
              button.setAttribute('role', 'radio');
              button.setAttribute('aria-checked', group.select.value === value ? 'true' : 'false');

              if (group.type === 'size') {
                const box = document.createElement('span');
                box.className = 'sccc-variation-option__box';
                box.setAttribute('aria-hidden', 'true');
                button.appendChild(box);
              }

              if (group.type === 'color') {
                const swatch = document.createElement('span');
                swatch.className = 'sccc-variation-option__swatch';
                swatch.setAttribute('aria-hidden', 'true');
                swatch.style.background = colorMap[valueSlug] || colorMap[slugify(optionLabel(option))] || '';
                button.appendChild(swatch);
              }

              const text = document.createElement('span');
              text.className = 'sccc-variation-option__text';
              text.textContent = optionLabel(option);
              button.appendChild(text);

              button.addEventListener('click', () => {
                if (button.disabled || button.classList.contains('is-disabled')) {
                  return;
                }

                group.select.value = value;
                group.select.dispatchEvent(new Event('change', { bubbles: true }));

                if (window.jQuery) {
                  window.jQuery(group.select).trigger('change');
                }

                updateControls();
                maybeUpdateImageFromCurrentSelection();
              });

              group.buttons.push(button);
              optionsEl.appendChild(button);
            });

            groupEl.appendChild(labelEl);
            groupEl.appendChild(optionsEl);
            picker.appendChild(groupEl);
          });

          const variationsTable = form.querySelector('table.variations');

          if (variationsTable) {
            variationsTable.parentNode.insertBefore(picker, variationsTable);
            form.classList.add('sccc-variation-form--enhanced');
          }

          function updateControls() {
            let changedSelection = false;

            groups.forEach((group) => {
              group.buttons.forEach((button) => {
                const value = button.dataset.value || '';
                const isSelected = group.select.value === value;
                const isAvailable = optionIsAvailable(group, value);

                button.classList.toggle('is-selected', isSelected);
                button.classList.toggle('is-disabled', !isAvailable);
                button.disabled = !isAvailable;
                button.setAttribute('aria-checked', isSelected ? 'true' : 'false');

                if (!isAvailable && isSelected) {
                  group.select.value = '';
                  changedSelection = true;
                }
              });
            });

            if (changedSelection) {
              groups.forEach((group) => {
                group.select.dispatchEvent(new Event('change', { bubbles: true }));

                if (window.jQuery) {
                  window.jQuery(group.select).trigger('change');
                }
              });
            }

            groups.forEach((group) => {
              group.buttons.forEach((button) => {
                const value = button.dataset.value || '';
                const isSelected = group.select.value === value;
                const isAvailable = optionIsAvailable(group, value);

                button.classList.toggle('is-selected', isSelected);
                button.classList.toggle('is-disabled', !isAvailable);
                button.disabled = !isAvailable;
                button.setAttribute('aria-checked', isSelected ? 'true' : 'false');
              });
            });
          }

          selects.forEach((select) => {
            select.addEventListener('change', () => {
              updateControls();
              maybeUpdateImageFromCurrentSelection();
            });
          });

          if (window.jQuery) {
            const $form = window.jQuery(form);

            $form.on('found_variation', function (event, variation) {
              if (variation && variation.image && variation.image.src) {
                updateDynamicThumb(variation.image);

                setMainImage(
                  variation.image.src,
                  variation.image.full_src || variation.image.src,
                  variation.image.alt || variation.image.title || '',
                  variation.image.image_id || variation.image_id || ''
                );
              }

              updateControls();
            });

            $form.on('reset_data hide_variation', function () {
              updateControls();

              if (!findBestVariationForCurrentSelection()) {
                resetDynamicImage();
              }
            });
          }

          updateControls();
          maybeUpdateImageFromCurrentSelection();
        });
      });
    })();
  </script>
@endonce