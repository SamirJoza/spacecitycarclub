{{--
  ============================================================================
  File path + filename: resources/views/blocks/benefits-proof.blade.php
  ============================================================================
  Purpose:
  - Render the reusable Benefits + Proof Gutenberg block.

  Why this file exists:
  - This block combines the benefits content and proof collage into one section.
  - It preserves the agreed fixed right-column rhythm:
    image / pod / pod / image
  - Optional extra proof pods render below the collage without breaking the
    primary layout.
  - Styles are block-scoped and support both light and dark theme signals.
  - Margin classes apply to the OUTER section wrapper.
  - Padding classes apply to the INNER section band.
  - The section supports an optional full-width background image behind the
    entire block area.
  - The heading supports [[highlight]] markup through preformatted HTML passed
    in from the block class.

  Important patch in this version:
  - This version keeps the user-provided working file intact.
  - Only the collage images in the proof column now render black and white by
    default and reveal color on hover.
  - The section background image is intentionally excluded from this effect.
  - Heading highlight styles now support the shared gradient/solid options:
    Club Blue, Signal Red, Space City, Club Blue Solid, Signal Red Solid,
    and Deep Purple Solid.
--}}

@php
  $rawBlock = $block->block ?? null;

  if (is_array($rawBlock)) {
    $anchor = $rawBlock['anchor'] ?? '';
    $customClassName = $rawBlock['className'] ?? '';
  } elseif (is_object($rawBlock)) {
    $anchor = $rawBlock->anchor ?? '';
    $customClassName = $rawBlock->className ?? '';
  } else {
    $anchor = '';
    $customClassName = '';
  }

  $blockId = !empty($anchor) ? $anchor : 'sccc-benefits-proof-' . uniqid();

  $layoutClass = $sectionLayout === 'full_width'
    ? 'sccc-benefits-proof-block--full'
    : 'sccc-benefits-proof-block--contained';

  /**
   * Split spacing classes by responsibility.
   *
   * Why this exists:
   * - mt/mb should affect the OUTER section wrapper.
   * - pt/pb/pl/pr should affect the INNER band/card shell.
   */
  $rawSpacingClasses = preg_split(
    '/\s+/',
    trim((string) ($sectionSpacingClasses ?? '')),
    -1,
    PREG_SPLIT_NO_EMPTY
  );

  $sectionMarginClasses = [];
  $bandPaddingClasses = [];

  foreach ($rawSpacingClasses as $spacingClass) {
    if (preg_match('/^(mt|mb)-/', $spacingClass)) {
      $sectionMarginClasses[] = $spacingClass;
      continue;
    }

    if (preg_match('/^(pt|pb|pl|pr|px|py)-/', $spacingClass)) {
      $bandPaddingClasses[] = $spacingClass;
      continue;
    }
  }

  $sectionClasses = trim(implode(' ', array_filter([
    'sccc-benefits-proof-block',
    $layoutClass,
    'relative',
    'overflow-hidden',
    'isolate',
    implode(' ', $sectionMarginClasses),
    $block->classes ?? '',
    $customClassName,
  ])));

  $bandClasses = trim(implode(' ', array_filter([
    'sccc-benefits-proof-block__band',
    implode(' ', $bandPaddingClasses),
  ])));

  $hasSectionBackground = !empty($sectionBackgroundImageId);
  $backgroundImagePosition = $sectionBackgroundPosition ?? 'right center';

  $iconSvgs = [
    'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 2v4M16 2v4M3 10h18"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M8 14h3v3H8z"/></svg>',
    'tag' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 13l-7 7-10-10V4h6z"/><circle cx="7.5" cy="7.5" r="1.2"/></svg>',
    'briefcase' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/></svg>',
    'community' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3l7 3v6c0 4.5-2.9 7.8-7 9-4.1-1.2-7-4.5-7-9V6z"/></svg>',
    'car' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 16l1.5-5A2 2 0 0 1 8.4 9h7.2a2 2 0 0 1 1.9 2L19 16"/><path d="M3 16h18v3a1 1 0 0 1-1 1h-1a2 2 0 0 1-2-2H7a2 2 0 0 1-2 2H4a1 1 0 0 1-1-1z"/><circle cx="7.5" cy="16.5" r="1.5"/><circle cx="16.5" cy="16.5" r="1.5"/></svg>',
    'star' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2.5 2.9 5.88 6.5.95-4.7 4.58 1.1 6.47L12 17.3l-5.8 3.08 1.1-6.47L2.6 9.33l6.5-.95z"/></svg>',
    'megaphone' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 11v2a2 2 0 0 0 2 2h2l3 5h2l-1.5-5H13l6 4V5l-6 4H5a2 2 0 0 0-2 2z"/></svg>',
  ];

  $renderImage = function ($attachmentId, $sizeClass) {
    if (!empty($attachmentId)) {
      return wp_get_attachment_image($attachmentId, 'large', false, [
        'class' => 'sccc-benefits-proof-block__image ' . $sizeClass,
        'loading' => 'lazy',
      ]);
    }

    return '<div class="sccc-benefits-proof-block__image-placeholder ' . e($sizeClass) . '"><span>Image</span></div>';
  };
@endphp

@once
  <style>
    .sccc-benefits-proof-block {
      --sccc-bp-section-bg: #071427;
      --sccc-bp-band-bg: rgba(4, 11, 25, 0.72);
      --sccc-bp-surface: rgba(12, 25, 48, 0.92);
      --sccc-bp-surface-2: rgba(14, 29, 56, 0.96);
      --sccc-bp-surface-3: rgba(46, 59, 88, 0.90);
      --sccc-bp-text: #ffffff;
      --sccc-bp-muted: #98a6bf;
      --sccc-bp-line: rgba(255, 255, 255, 0.10);
      --sccc-bp-accent: var(--color-primary-500, var(--color-primary, #2979ff));
      --sccc-bp-shadow:
        0 18px 42px rgba(0, 0, 0, 0.34),
        0 0 0 1px rgba(255, 255, 255, 0.03);

      /* Shared heading highlight options */
      --sccc-gradient-club-blue: linear-gradient(90deg, #135bec 0%, #1f8fff 52%, #71d7ff 100%);
      --sccc-gradient-signal-red: linear-gradient(90deg, #b31324 0%, #ff1744 50%, #ff7a59 100%);
      --sccc-gradient-space-city: linear-gradient(90deg, #135bec 0%, #43beff 42%, #ae71ff 72%, #ff1744 100%);
      --sccc-color-club-blue: #135bec;
      --sccc-color-signal-red: #e53935;
      --sccc-color-deep-purple: #ae71ff;

      /* Dark theme background image tuning */
      --sccc-bp-bg-image-opacity: 0.56;
      --sccc-bp-bg-image-filter: grayscale(100%) contrast(1.05) brightness(0.82);
      --sccc-bp-bg-overlay:
        linear-gradient(
          90deg,
          rgba(4, 11, 25, 0.72) 0%,
          rgba(4, 11, 25, 0.64) 45%,
          rgba(4, 11, 25, 0.34) 72%,
          rgba(4, 11, 25, 0.54) 100%
        );

      /**
       * Collage image effect
       *
       * Why these variables exist:
       * - Only the two proof-column images should be black and white by default.
       * - On hover they should reveal color smoothly.
       */
      --sccc-bp-collage-image-filter: grayscale(1) contrast(1.02) brightness(0.88);
      --sccc-bp-collage-image-filter-hover: grayscale(0) contrast(1) brightness(1);

      color: var(--sccc-bp-text);
      background:
        radial-gradient(circle at 10% 18%, rgba(41, 121, 255, 0.10), transparent 34%),
        radial-gradient(circle at 92% 12%, rgba(41, 121, 255, 0.08), transparent 24%),
        var(--sccc-bp-section-bg);
    }

    html[data-theme="light"] .sccc-benefits-proof-block {
      --sccc-bp-section-bg: #eef3f9;
      --sccc-bp-band-bg: rgba(255, 255, 255, 0.76);
      --sccc-bp-surface: rgba(255, 255, 255, 0.96);
      --sccc-bp-surface-2: rgba(255, 255, 255, 0.98);
      --sccc-bp-surface-3: rgba(236, 242, 250, 0.94);
      --sccc-bp-text: #101622;
      --sccc-bp-muted: #5f6670;
      --sccc-bp-line: rgba(16, 22, 34, 0.10);
      --sccc-bp-shadow:
        0 18px 34px rgba(16, 22, 34, 0.08),
        0 0 0 1px rgba(16, 22, 34, 0.04);

      /* Light theme background image tuning */
      --sccc-bp-bg-image-opacity: 0.52;
      --sccc-bp-bg-image-filter: grayscale(55%) contrast(1.02) brightness(0.98);
      --sccc-bp-bg-overlay:
        linear-gradient(
          90deg,
          rgba(244, 247, 251, 0.52) 0%,
          rgba(244, 247, 251, 0.40) 45%,
          rgba(244, 247, 251, 0.12) 72%,
          rgba(244, 247, 251, 0.32) 100%
        );

      --sccc-bp-collage-image-filter: grayscale(1) contrast(1.01) brightness(0.93);
      --sccc-bp-collage-image-filter-hover: grayscale(0) contrast(1) brightness(1);
    }

    html[data-theme="dark"] .sccc-benefits-proof-block,
    html.dark .sccc-benefits-proof-block,
    .dark .sccc-benefits-proof-block {
      --sccc-bp-section-bg: #071427;
      --sccc-bp-band-bg: rgba(4, 11, 25, 0.72);
      --sccc-bp-surface: rgba(12, 25, 48, 0.92);
      --sccc-bp-surface-2: rgba(14, 29, 56, 0.96);
      --sccc-bp-surface-3: rgba(46, 59, 88, 0.90);
      --sccc-bp-text: #ffffff;
      --sccc-bp-muted: #98a6bf;
      --sccc-bp-line: rgba(255, 255, 255, 0.10);
      --sccc-bp-shadow:
        0 18px 42px rgba(0, 0, 0, 0.34),
        0 0 0 1px rgba(255, 255, 255, 0.03);
      --sccc-bp-bg-image-opacity: 0.56;
      --sccc-bp-bg-image-filter: grayscale(100%) contrast(1.05) brightness(0.82);
      --sccc-bp-bg-overlay:
        linear-gradient(
          90deg,
          rgba(4, 11, 25, 0.72) 0%,
          rgba(4, 11, 25, 0.64) 45%,
          rgba(4, 11, 25, 0.34) 72%,
          rgba(4, 11, 25, 0.54) 100%
        );
      --sccc-bp-collage-image-filter: grayscale(1) contrast(1.02) brightness(0.88);
      --sccc-bp-collage-image-filter-hover: grayscale(0) contrast(1) brightness(1);
    }

    .sccc-benefits-proof-block__section-background {
      position: absolute;
      inset: 0;
      z-index: -2;
      pointer-events: none;
    }

    .sccc-benefits-proof-block__section-background::after {
      content: "";
      position: absolute;
      inset: 0;
      background: var(--sccc-bp-bg-overlay);
    }

    .sccc-benefits-proof-block__section-background img {
      width: 100%;
      height: 100%;
      display: block;
      object-fit: cover;
      opacity: var(--sccc-bp-bg-image-opacity);
      filter: var(--sccc-bp-bg-image-filter);
      transform: scale(1.01);
    }

    .sccc-benefits-proof-block__band {
      position: relative;
      isolation: isolate;
      background: var(--sccc-bp-band-bg);
      backdrop-filter: blur(2px);
    }

    .sccc-benefits-proof-block--contained .sccc-benefits-proof-block__band {
      max-width: 84rem;
      margin-inline: auto;
      border-radius: 1.5rem;
      border: 1px solid var(--sccc-bp-line);
      box-shadow: var(--sccc-bp-shadow);
    }

    .sccc-benefits-proof-block--full .sccc-benefits-proof-block__band {
      border-block: 1px solid var(--sccc-bp-line);
    }

    .sccc-benefits-proof-block__inner {
      max-width: 84rem;
      margin-inline: auto;
    }

    .sccc-benefits-proof-block__layout {
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      gap: 4rem;
      align-items: start;
    }

    @media (min-width: 1024px) {
      .sccc-benefits-proof-block__layout {
        grid-template-columns: minmax(0, 1.08fr) minmax(0, 0.92fr);
        gap: clamp(2.5rem, 4vw, 5rem);
        align-items: center;
      }
    }

    .sccc-benefits-proof-block__content,
    .sccc-benefits-proof-block__proof {
      min-width: 0;
    }

    @media (min-width: 1024px) {
      .sccc-benefits-proof-block__proof {
        width: 100%;
        max-width: 38rem;
        justify-self: end;
      }
    }

    .sccc-benefits-proof-block__eyebrow {
      margin: 0 0 0.75rem;
      font-family: var(--font-display, var(--font-headline, "Space Grotesk", system-ui, sans-serif));
      font-size: 0.75rem;
      font-weight: 800;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--sccc-bp-accent);
    }

    .sccc-benefits-proof-block__heading {
      margin: 0;
      font-family: var(--font-display, var(--font-headline, "Space Grotesk", system-ui, sans-serif));
      font-size: clamp(2.35rem, 4.2vw, 4.25rem);
      font-weight: 800;
      line-height: 0.98;
      letter-spacing: -0.04em;
      color: var(--sccc-bp-text);
      max-width: 11ch;
      text-transform: uppercase;
    }

    .sccc-benefits-proof-block__heading-accent {
      color: var(--sccc-bp-accent);
    }

    .sccc-benefits-proof-block__heading-accent strong {
      font-weight: 900;
    }

    .sccc-benefits-proof-block__heading-accent--club-blue,
    .sccc-benefits-proof-block__heading-accent--signal-red,
    .sccc-benefits-proof-block__heading-accent--space-city {
      background-image: var(--sccc-gradient-club-blue);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      -webkit-text-fill-color: transparent;
    }

    .sccc-benefits-proof-block__heading-accent--club-blue {
      background-image: var(--sccc-gradient-club-blue);
    }

    .sccc-benefits-proof-block__heading-accent--signal-red {
      background-image: var(--sccc-gradient-signal-red);
    }

    .sccc-benefits-proof-block__heading-accent--space-city {
      background-image: var(--sccc-gradient-space-city);
    }

    .sccc-benefits-proof-block__heading-accent--club-blue-solid {
      color: var(--sccc-color-club-blue);
    }

    .sccc-benefits-proof-block__heading-accent--signal-red-solid {
      color: var(--sccc-color-signal-red);
    }

    .sccc-benefits-proof-block__heading-accent--deep-purple-solid {
      color: var(--sccc-color-deep-purple);
    }

    .sccc-benefits-proof-block__intro {
      margin-top: 1.9rem;
      color: var(--sccc-bp-muted);
      font-size: 1.05rem;
      line-height: 1.7;
      max-width: 34rem;
    }

    .sccc-benefits-proof-block__benefits {
      margin-top: 3rem;
      display: grid;
      grid-template-columns: repeat(1, minmax(0, 1fr));
      gap: 2.1rem 2.5rem;
      max-width: 44rem;
    }

    @media (min-width: 640px) {
      .sccc-benefits-proof-block__benefits {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    .sccc-benefits-proof-block__benefit {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 0;
    }

    .sccc-benefits-proof-block__benefit-icon {
      width: auto;
      height: auto;
      display: inline-flex;
      align-items: center;
      justify-content: flex-start;
      margin-bottom: 1rem;
      color: var(--sccc-bp-accent);
      border: 0;
      background: transparent;
      box-shadow: none;
      border-radius: 0;
      padding: 0;
    }

    .sccc-benefits-proof-block__benefit-icon svg {
      width: 1.91.9rem;
      height: 1.9rem;
      display: block;
    }

    .sccc-benefits-proof-block__benefit-title {
      margin: 0;
      font-family: var(--font-display, var(--font-headline, "Space Grotesk", system-ui, sans-serif));
      font-size: 1rem;
      font-weight: 800;
      color: var(--sccc-bp-text);
    }

    .sccc-benefits-proof-block__benefit-description {
      margin: 0.55rem 0 0;
      color: var(--sccc-bp-muted);
      line-height: 1.65;
      font-size: 0.95rem;
    }

    .sccc-benefits-proof-block__image,
    .sccc-benefits-proof-block__image-placeholder {
      width: 100%;
      display: block;
      object-fit: cover;
      border-radius: 0.8rem;
      border: 1px solid var(--sccc-bp-line);
      box-shadow: var(--sccc-bp-shadow);
      background: var(--sccc-bp-surface);
    }

    /**
     * Collage images only:
     * - black and white by default
     * - color on hover
     *
     * Why this selector:
     * - Limits the effect to the proof-column images only
     * - Does not touch the section background image
     */
    .sccc-benefits-proof-block__proof .sccc-benefits-proof-block__image {
      filter: var(--sccc-bp-collage-image-filter);
      transition:
        filter 260ms ease,
        transform 260ms ease;
    }

    .sccc-benefits-proof-block__proof .sccc-benefits-proof-block__image:hover {
      filter: var(--sccc-bp-collage-image-filter-hover);
      transform: translateY(-2px);
    }

    .sccc-benefits-proof-block__image-placeholder {
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--sccc-bp-muted);
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      font-size: 0.8rem;
    }

    .sccc-benefits-proof-block__pod {
      border-radius: 0.8rem;
      border: 1px solid var(--sccc-bp-line);
      background: var(--sccc-bp-surface-3);
      box-shadow: var(--sccc-bp-shadow);
      padding: 1.5rem;
    }

    .sccc-benefits-proof-block__pod--members {
      background: linear-gradient(180deg, color-mix(in srgb, var(--sccc-bp-accent) 92%, white 0%), color-mix(in srgb, var(--sccc-bp-accent) 84%, black 4%));
      border-color: color-mix(in srgb, var(--sccc-bp-accent) 45%, transparent);
    }

    .sccc-benefits-proof-block__pod--members .sccc-benefits-proof-block__pod-value,
    .sccc-benefits-proof-block__pod--members .sccc-benefits-proof-block__pod-title {
      color: #ffffff;
    }

    .sccc-benefits-proof-block__pod-value {
      margin: 0;
      font-family: var(--font-display, var(--font-headline, "Space Grotesk", system-ui, sans-serif));
      font-size: clamp(2rem, 3vw, 2.5rem);
      font-weight: 800;
      line-height: 1;
      letter-spacing: -0.03em;
      color: var(--sccc-bp-text);
    }

    .sccc-benefits-proof-block__pod-title {
      margin: 0.65rem 0 0;
      color: var(--sccc-bp-muted);
      font-family: var(--font-display, var(--font-headline, "Space Grotesk", system-ui, sans-serif));
      font-size: 0.76rem;
      font-weight: 800;
      letter-spacing: 0.16em;
      text-transform: uppercase;
    }
  </style>
@endonce

<section
  id="{{ esc_attr($blockId) }}"
  class="{{ esc_attr($sectionClasses) }}"
>
  @if ($hasSectionBackground)
    <div class="sccc-benefits-proof-block__section-background" aria-hidden="true">
      {!! wp_get_attachment_image($sectionBackgroundImageId, 'full', false, [
        'loading' => 'lazy',
        'style' => 'object-position: ' . esc_attr($backgroundImagePosition) . ';',
      ]) !!}
    </div>
  @endif

  <div class="{{ esc_attr($bandClasses) }}">
    <div class="sccc-benefits-proof-block__inner">
      <div class="sccc-benefits-proof-block__layout">
        <div class="sccc-benefits-proof-block__content">
          @if (!empty($eyebrow))
            <p class="sccc-benefits-proof-block__eyebrow">{{ $eyebrow }}</p>
          @endif

          @if (!empty($headingHtml))
            <h2 class="sccc-benefits-proof-block__heading">{!! $headingHtml !!}</h2>
          @endif

          @if (!empty($intro))
            <div class="sccc-benefits-proof-block__intro">
              {!! wp_kses_post($intro) !!}
            </div>
          @endif

          @if (!empty($benefits))
            <div class="sccc-benefits-proof-block__benefits">
              @foreach ($benefits as $benefit)
                @php
                  $iconKey = $benefit['icon'] ?? 'calendar';
                  $iconSvg = $iconSvgs[$iconKey] ?? $iconSvgs['calendar'];
                @endphp

                <article class="sccc-benefits-proof-block__benefit">
                  <div class="sccc-benefits-proof-block__benefit-icon" aria-hidden="true">
                    {!! $iconSvg !!}
                  </div>

                  <div>
                    <h3 class="sccc-benefits-proof-block__benefit-title">{{ $benefit['title'] ?? '' }}</h3>
                    <div class="sccc-benefits-proof-block__benefit-description">
                      {!! wp_kses_post($benefit['description'] ?? '') !!}
                    </div>
                  </div>
                </article>
              @endforeach
            </div>
          @endif
        </div>

        <div class="sccc-benefits-proof-block__proof">
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-4">
              {!! $renderImage($imagePrimaryId, 'h-72') !!}

              <div class="sccc-benefits-proof-block__pod sccc-benefits-proof-block__pod--members">
                <p class="sccc-benefits-proof-block__pod-value">{{ $membersPod['value'] ?? '10+' }}</p>
                <p class="sccc-benefits-proof-block__pod-title">{{ $membersPod['title'] ?? 'Active Members' }}</p>
              </div>
            </div>

            <div class="space-y-4 pt-8">
              <div class="sccc-benefits-proof-block__pod">
                <p class="sccc-benefits-proof-block__pod-value">{{ $manualPod['value'] ?? '0+' }}</p>
                <p class="sccc-benefits-proof-block__pod-title">{{ $manualPod['title'] ?? 'Annual Events' }}</p>
              </div>

              {!! $renderImage($imageSecondaryId, 'h-80') !!}
            </div>

            @if (!empty($extraProofPods))
              <div class="col-span-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($extraProofPods as $pod)
                  <div class="sccc-benefits-proof-block__pod">
                    <p class="sccc-benefits-proof-block__pod-value">{{ $pod['value'] ?? '' }}</p>
                    <p class="sccc-benefits-proof-block__pod-title">{{ $pod['title'] ?? '' }}</p>
                  </div>
                @endforeach
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
</section>