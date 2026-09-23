{{--
==============================================================================
File path + filename: resources/views/blocks/garage-showcase.blade.php
==============================================================================

Purpose:
- Render the dynamic Garage Showcase block.

Why this file exists:
- The user wants a section inspired by the "The Garage" example layout:
  - section intro at the top
  - 5-image mosaic
  - one large lead tile and four supporting tiles
- The intro area needs to stay flexible, so it uses InnerBlocks instead of
  fixed ACF text fields.
- The gallery itself is automatic and built from live member vehicle data.

Theme compatibility:
- Dark mode stays close to the provided reference:
  - dark section background
  - strong image contrast
  - hover overlay reveal
  - crisp white type over images
- Light mode keeps the same energy:
  - bright section background
  - subtle primary-tinted glow
  - stronger tile shadows and soft borders so the gallery still feels lively

Important:
- This block assumes the project already loads Gutenberg and ACF block assets.
- The section intro is intentionally flexible:
  - starter heading + paragraph
  - no template lock
  - buttons and a few other content blocks allowed
- The gallery is not linked because a stable public member/vehicle destination
  was not specified.
--}}

@php
  /**
   * InnerBlocks JSON attributes must be escaped once here, then output raw.
   */
  $allowedBlocksAttr = esc_attr(wp_json_encode($allowedBlocks ?? []));
  $templateAttr = esc_attr(wp_json_encode($innerBlocksTemplate ?? []));

  /**
   * Count tiles so the markup can gracefully adjust if fewer than 5 vehicles
   * are currently eligible.
   */
  $itemCount = is_array($items ?? null) ? count($items) : 0;
@endphp

@once
  <style>
    /* ==========================================================================
       Garage Showcase block
       Fully scoped so styles remain local to this block.
       ========================================================================== */

    .sccc-garage-showcase {
      --sccc-garage-bg-light: #ffffff;
      --sccc-garage-bg-dark: #0c111a;
      --sccc-garage-text-light: #111722;
      --sccc-garage-text-dark: #ffffff;
      --sccc-garage-copy-light: #6b7280;
      --sccc-garage-copy-dark: #9ca3af;
      --sccc-garage-primary: #135bec;
      --sccc-garage-border-light: rgba(17, 23, 34, 0.08);
      --sccc-garage-border-dark: rgba(255, 255, 255, 0.08);
      --sccc-garage-shadow-light:
        0 26px 46px -28px rgba(15, 23, 42, 0.24),
        0 18px 28px -24px rgba(19, 91, 236, 0.18);
      --sccc-garage-shadow-dark:
        0 28px 48px -28px rgba(0, 0, 0, 0.55),
        0 18px 30px -24px rgba(19, 91, 236, 0.22);

      position: relative;
      width: 100%;
      margin-block: 1.5rem;
      padding-block: 4rem;
      background: var(--sccc-garage-bg-light);
      overflow: hidden;
    }

    [data-theme="dark"] .sccc-garage-showcase,
    .dark .sccc-garage-showcase {
      background: var(--sccc-garage-bg-dark);
    }

    .sccc-garage-showcase *,
    .sccc-garage-showcase *::before,
    .sccc-garage-showcase *::after {
      box-sizing: border-box;
    }

    .sccc-garage-showcase__blob {
      position: absolute;
      top: 0;
      right: 0;
      width: 28rem;
      height: 28rem;
      border-radius: 9999px;
      background: rgba(19, 91, 236, 0.07);
      filter: blur(48px);
      transform: translate(40%, -45%);
      pointer-events: none;
    }

    [data-theme="dark"] .sccc-garage-showcase__blob,
    .dark .sccc-garage-showcase__blob {
      background: rgba(19, 91, 236, 0.12);
    }

    .sccc-garage-showcase__inner {
      position: relative;
      z-index: 1;
      max-width: 75rem;
      margin-inline: auto;
      padding-inline: 1rem;
    }

    .sccc-garage-showcase__intro {
      margin-bottom: 2rem;
    }

    .sccc-garage-showcase__intro .wp-block-heading,
    .sccc-garage-showcase__intro h1,
    .sccc-garage-showcase__intro h2,
    .sccc-garage-showcase__intro h3,
    .sccc-garage-showcase__intro h4,
    .sccc-garage-showcase__intro h5,
    .sccc-garage-showcase__intro h6 {
      color: var(--sccc-garage-text-light);
      margin-top: 0;
      margin-bottom: 0.5rem;
      font-size: 1.875rem;
      font-weight: 700;
      line-height: 1.2;
      letter-spacing: -0.02em;
      text-transform: uppercase;
    }

    [data-theme="dark"] .sccc-garage-showcase__intro .wp-block-heading,
    .dark .sccc-garage-showcase__intro .wp-block-heading,
    [data-theme="dark"] .sccc-garage-showcase__intro h1,
    .dark .sccc-garage-showcase__intro h1,
    [data-theme="dark"] .sccc-garage-showcase__intro h2,
    .dark .sccc-garage-showcase__intro h2,
    [data-theme="dark"] .sccc-garage-showcase__intro h3,
    .dark .sccc-garage-showcase__intro h3,
    [data-theme="dark"] .sccc-garage-showcase__intro h4,
    .dark .sccc-garage-showcase__intro h4,
    [data-theme="dark"] .sccc-garage-showcase__intro h5,
    .dark .sccc-garage-showcase__intro h5,
    [data-theme="dark"] .sccc-garage-showcase__intro h6,
    .dark .sccc-garage-showcase__intro h6 {
      color: var(--sccc-garage-text-dark);
    }

    .sccc-garage-showcase__intro p,
    .sccc-garage-showcase__intro li {
      color: var(--sccc-garage-copy-light);
      margin-top: 0;
      line-height: 1.7;
    }

    [data-theme="dark"] .sccc-garage-showcase__intro p,
    .dark .sccc-garage-showcase__intro p,
    [data-theme="dark"] .sccc-garage-showcase__intro li,
    .dark .sccc-garage-showcase__intro li {
      color: var(--sccc-garage-copy-dark);
    }

    .sccc-garage-showcase__intro .wp-block-buttons {
      margin-top: 1rem;
    }

    .sccc-garage-showcase__grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 1rem;
      grid-auto-rows: 200px;
    }

    @media (min-width: 768px) {
      .sccc-garage-showcase__grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
      }
    }

    .sccc-garage-showcase__tile {
      position: relative;
      overflow: hidden;
      border-radius: 0.75rem;
      border: 1px solid var(--sccc-garage-border-light);
      background: #111827;
      box-shadow: var(--sccc-garage-shadow-light);
    }

    [data-theme="dark"] .sccc-garage-showcase__tile,
    .dark .sccc-garage-showcase__tile {
      border-color: var(--sccc-garage-border-dark);
      box-shadow: var(--sccc-garage-shadow-dark);
    }

    .sccc-garage-showcase__tile.is-lead {
      grid-column: span 2 / span 2;
      grid-row: span 2 / span 2;
    }

    .sccc-garage-showcase__tile.is-full {
      grid-column: span 2 / span 2;
      grid-row: span 2 / span 2;
      min-height: 26rem;
    }

    @media (min-width: 768px) {
      .sccc-garage-showcase__tile.is-full {
        grid-column: span 4 / span 4;
      }
    }

    .sccc-garage-showcase__image {
      display: block;
      width: 100%;
      height: 100%;
      object-fit: cover;
      transform: scale(1);
      transition: transform 500ms ease;
    }

    .sccc-garage-showcase__tile:hover .sccc-garage-showcase__image {
      transform: scale(1.08);
    }

    .sccc-garage-showcase__overlay {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: flex-end;
      padding: 1rem;
      background: linear-gradient(to top, rgba(0, 0, 0, 0.84), rgba(0, 0, 0, 0));
      opacity: 0;
      transition: opacity 300ms ease;
    }

    .sccc-garage-showcase__tile.is-lead .sccc-garage-showcase__overlay,
    .sccc-garage-showcase__tile.is-full .sccc-garage-showcase__overlay {
      padding: 1.5rem;
    }

    .sccc-garage-showcase__tile:hover .sccc-garage-showcase__overlay {
      opacity: 1;
    }

    .sccc-garage-showcase__caption {
      min-width: 0;
    }

    .sccc-garage-showcase__title {
      margin: 0;
      color: #ffffff;
      font-weight: 700;
      line-height: 1.25;
      font-size: 0.95rem;
    }

    .sccc-garage-showcase__tile.is-lead .sccc-garage-showcase__title,
    .sccc-garage-showcase__tile.is-full .sccc-garage-showcase__title {
      font-size: 1.1rem;
    }

    .sccc-garage-showcase__meta {
      margin-top: 0.25rem;
      color: rgba(255, 255, 255, 0.82);
      font-size: 0.78rem;
      line-height: 1.4;
    }

    .sccc-garage-showcase__tile.is-lead .sccc-garage-showcase__meta,
    .sccc-garage-showcase__tile.is-full .sccc-garage-showcase__meta {
      font-size: 0.875rem;
    }

    .sccc-garage-showcase__empty {
      padding: 2rem;
      border: 1px dashed var(--sccc-garage-border-light);
      border-radius: 0.75rem;
      color: var(--sccc-garage-copy-light);
      background: rgba(19, 91, 236, 0.03);
      text-align: center;
    }

    [data-theme="dark"] .sccc-garage-showcase__empty,
    .dark .sccc-garage-showcase__empty {
      border-color: var(--sccc-garage-border-dark);
      color: var(--sccc-garage-copy-dark);
      background: rgba(19, 91, 236, 0.06);
    }
  </style>
@endonce

<section {{ $attributes->class('sccc-garage-showcase') }}>
  <div class="sccc-garage-showcase__blob" aria-hidden="true"></div>

  <div class="sccc-garage-showcase__inner">
    <div class="sccc-garage-showcase__intro">
      <InnerBlocks
        allowedBlocks="{!! $allowedBlocksAttr !!}"
        template="{!! $templateAttr !!}"
      />
    </div>

    @if ($itemCount > 0)
      <div class="sccc-garage-showcase__grid">
        @foreach ($items as $item)
          @php
            /**
             * Tile layout logic:
             * - 1 item  => full-width hero tile
             * - 2 items => two equal larger tiles
             * - 3+      => first tile is the lead tile, matching the example
             */
            $tileClasses = 'sccc-garage-showcase__tile group';

            if ($itemCount === 1) {
                $tileClasses .= ' is-full';
            } elseif ($itemCount === 2) {
                $tileClasses .= ' is-lead';
            } elseif ($loop->first) {
                $tileClasses .= ' is-lead';
            }
          @endphp

          <div class="{{ $tileClasses }}">
            {!! wp_get_attachment_image(
                (int) $item['image_id'],
                'large',
                false,
                [
                    'class' => 'sccc-garage-showcase__image',
                    'loading' => 'lazy',
                    'decoding' => 'async',
                    'alt' => $item['alt'],
                ]
            ) !!}

            <div class="sccc-garage-showcase__overlay" aria-hidden="true">
              <div class="sccc-garage-showcase__caption">
                <p class="sccc-garage-showcase__title">{{ $item['title'] }}</p>
                <p class="sccc-garage-showcase__meta">{{ $item['member_name'] }}@if($item['subtitle'] !== $item['member_name']) &bull; {{ $item['subtitle'] }}@endif</p>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    @else
      <div class="sccc-garage-showcase__empty">
        No member vehicles with images are available yet.
      </div>
    @endif
  </div>
</section>