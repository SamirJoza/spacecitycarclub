{{--
==============================================================================
File path + filename: resources/views/blocks/merch-product-promo.blade.php
==============================================================================

Purpose:
- Render the custom merch/product promo card block.

Why this file exists:
- The block needs to mirror the provided merch sidebar widget layout closely:
  gradient frame, inner panel, decorative bag icon, editable headline/copy,
  featured product row, and a strong CTA button.
- The user requested block-contained styling so the CSS lives here and is
  scoped to this block only.

Important:
- `$headline`, `$copy`, `$buttonText`, and `$card` are prepared in the block
  class.
- The selected WooCommerce product remains the source of truth for:
  - image
  - product name
  - price
- The product image is rendered as a circle per the user's latest request.
--}}

@if ($card || $isPreview)
  @once
    <style>
      /* ==========================================================================
         Merch Product Promo block
         Scoped styles only — intentionally not part of the global theme bundle.
         ========================================================================== */

      .sccc-merch-product-promo {
        --sccc-merch-surface: #f6f6f8;
        --sccc-merch-surface-dark: #101022;
        --sccc-merch-text: #0f172a;
        --sccc-merch-text-dark: #ffffff;
        --sccc-merch-muted: #64748b;
        --sccc-merch-muted-dark: #94a3b8;
        --sccc-merch-border-light: rgba(226, 232, 240, 1);
        --sccc-merch-border-dark: rgba(255, 255, 255, 0.08);
        --sccc-merch-price-light: #9a3412;
        --sccc-merch-price-dark: #fbbf24;
        --sccc-merch-button-bg-light: #0f172a;
        --sccc-merch-button-text-light: #ffffff;
        --sccc-merch-button-bg-dark: #ffffff;
        --sccc-merch-button-text-dark: #0f172a;
        width: 100%;
        margin-block: 1rem;
      }

      .sccc-merch-product-promo *,
      .sccc-merch-product-promo *::before,
      .sccc-merch-product-promo *::after {
        box-sizing: border-box;
      }

      .sccc-merch-product-promo__frame {
        position: relative;
        border-radius: 1.5rem;
        padding: 0.25rem;
        background: linear-gradient(135deg, #1313ec 0%, #9333ea 52%, #60a5fa 100%);
        box-shadow:
          0 20px 25px -5px rgba(0, 0, 0, 0.12),
          0 8px 10px -6px rgba(0, 0, 0, 0.10);
      }

      .sccc-merch-product-promo__inner {
        position: relative;
        height: 100%;
        overflow: hidden;
        border-radius: 1rem;
        padding: 1.25rem;
        background: var(--sccc-merch-surface);
        color: var(--sccc-merch-text);
      }

      [data-theme="dark"] .sccc-merch-product-promo__inner,
      .dark .sccc-merch-product-promo__inner {
        background: var(--sccc-merch-surface-dark);
        color: var(--sccc-merch-text-dark);
      }

      .sccc-merch-product-promo__watermark {
        position: absolute;
        top: 0;
        right: 0;
        padding: 0.75rem;
        opacity: 0.10;
        pointer-events: none;
      }

      .sccc-merch-product-promo__watermark svg {
        display: block;
        width: 3.75rem;
        height: 3.75rem;
      }

      .sccc-merch-product-promo__headline {
        position: relative;
        z-index: 1;
        margin: 0 0 0.5rem;
        font-size: 1.125rem;
        line-height: 1.25;
        font-weight: 700;
        color: inherit;
      }

      .sccc-merch-product-promo__copy {
        position: relative;
        z-index: 1;
        margin: 0 0 1rem;
        font-size: 0.875rem;
        line-height: 1.5;
        color: var(--sccc-merch-muted);
      }

      [data-theme="dark"] .sccc-merch-product-promo__copy,
      .dark .sccc-merch-product-promo__copy {
        color: var(--sccc-merch-muted-dark);
      }

      .sccc-merch-product-promo__product {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
      }

      .sccc-merch-product-promo__thumb {
        flex: 0 0 auto;
        width: 4rem;
        height: 4rem;
        overflow: hidden;
        border-radius: 9999px;
        background: #e2e8f0;
      }

      [data-theme="dark"] .sccc-merch-product-promo__thumb,
      .dark .sccc-merch-product-promo__thumb {
        background: rgba(255, 255, 255, 0.10);
      }

      .sccc-merch-product-promo__thumb img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
      }

      .sccc-merch-product-promo__thumb-fallback {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        color: #64748b;
      }

      [data-theme="dark"] .sccc-merch-product-promo__thumb-fallback,
      .dark .sccc-merch-product-promo__thumb-fallback {
        color: #cbd5e1;
      }

      .sccc-merch-product-promo__thumb-fallback svg {
        width: 1.5rem;
        height: 1.5rem;
      }

      .sccc-merch-product-promo__details {
        min-width: 0;
        flex: 1 1 auto;
      }

      .sccc-merch-product-promo__product-title {
        margin: 0 0 0.25rem;
        font-size: 0.875rem;
        line-height: 1.4;
        font-weight: 700;
        color: inherit;
      }

      .sccc-merch-product-promo__price {
        font-size: 0.875rem;
        line-height: 1.4;
        font-weight: 700;
        color: var(--sccc-merch-price-light);
      }

      [data-theme="dark"] .sccc-merch-product-promo__price,
      .dark .sccc-merch-product-promo__price {
        color: var(--sccc-merch-price-dark);
      }

      .sccc-merch-product-promo__price .amount {
        color: inherit;
      }

      .sccc-merch-product-promo__price del {
        opacity: 0.65;
        margin-right: 0.25rem;
      }

      .sccc-merch-product-promo__price ins {
        color: inherit;
        text-decoration: none;
      }

      .sccc-merch-product-promo__button {
        position: relative;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        width: 100%;
        min-height: 2.75rem;
        padding: 0.625rem 1rem;
        border: 0;
        border-radius: 0.75rem;
        background: var(--sccc-merch-button-bg-light);
        color: var(--sccc-merch-button-text-light);
        font-size: 0.875rem;
        line-height: 1.2;
        font-weight: 700;
        text-decoration: none;
        transition: opacity 180ms ease, transform 180ms ease;
      }

      [data-theme="dark"] .sccc-merch-product-promo__button,
      .dark .sccc-merch-product-promo__button {
        background: var(--sccc-merch-button-bg-dark);
        color: var(--sccc-merch-button-text-dark);
      }

      .sccc-merch-product-promo__button:hover,
      .sccc-merch-product-promo__button:focus-visible {
        opacity: 0.90;
        transform: translateY(-1px);
        color: inherit;
        text-decoration: none;
      }

      .sccc-merch-product-promo__button-icon {
        width: 1.125rem;
        height: 1.125rem;
        flex: 0 0 auto;
      }

      .sccc-merch-product-promo__preview {
        position: relative;
        z-index: 1;
        margin: 0;
        padding: 0.875rem 1rem;
        border: 1px dashed var(--sccc-merch-border-light);
        border-radius: 0.875rem;
        font-size: 0.8125rem;
        line-height: 1.5;
        color: var(--sccc-merch-muted);
      }

      [data-theme="dark"] .sccc-merch-product-promo__preview,
      .dark .sccc-merch-product-promo__preview {
        border-color: var(--sccc-merch-border-dark);
        color: var(--sccc-merch-muted-dark);
      }
    </style>
  @endonce

  <section {{ $attributes->class('sccc-merch-product-promo') }}>
    <div class="sccc-merch-product-promo__frame">
      <div class="sccc-merch-product-promo__inner">
        <div class="sccc-merch-product-promo__watermark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path
              d="M6 7V6a6 6 0 1 1 12 0v1M5 7h14l-1 12H6L5 7Z"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </div>

        <h3 class="sccc-merch-product-promo__headline">{{ $headline }}</h3>
        <p class="sccc-merch-product-promo__copy">{{ $copy }}</p>

        @if ($card)
          <div class="sccc-merch-product-promo__product">
            <div class="sccc-merch-product-promo__thumb" aria-hidden="true">
              @if ($card['imageUrl'])
                <img
                  src="{{ esc_url($card['imageUrl']) }}"
                  alt="{{ esc_attr($card['title']) }}"
                  loading="lazy"
                  decoding="async"
                >
              @else
                <div class="sccc-merch-product-promo__thumb-fallback">
                  <svg viewBox="0 0 24 24" fill="none">
                    <path
                      d="M6 7V6a6 6 0 1 1 12 0v1M5 7h14l-1 12H6L5 7Z"
                      stroke="currentColor"
                      stroke-width="1.6"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                  </svg>
                </div>
              @endif
            </div>

            <div class="sccc-merch-product-promo__details">
              <p class="sccc-merch-product-promo__product-title">{{ $card['title'] }}</p>

              @if (! empty($card['priceHtml']))
                <div class="sccc-merch-product-promo__price">
                  {!! wp_kses_post($card['priceHtml']) !!}
                </div>
              @endif
            </div>
          </div>

          <a class="sccc-merch-product-promo__button" href="{{ esc_url($card['buttonUrl']) }}">
            <svg class="sccc-merch-product-promo__button-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path
                d="M6 7V6a6 6 0 1 1 12 0v1M5 7h14l-1 12H6L5 7Z"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <span>{{ $card['buttonText'] }}</span>
          </a>
        @elseif ($isPreview)
          <p class="sccc-merch-product-promo__preview">
            Select a WooCommerce product in the block settings to populate the product image, name, and price.
          </p>
        @endif
      </div>
    </div>
  </section>
@endif