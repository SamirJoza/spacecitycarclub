{{--
  ============================================================================
  File path + filename: resources/views/blocks/testimonial.blade.php
  ============================================================================
  Purpose:
  - Render the reusable Testimonial Gutenberg block.

  Why this file exists:
  - The block supports:
    - CPT-backed testimonials
    - manual one-off testimonials
    - contained or full-width section mode
    - explicit custom spacing using Tailwind utility classes
    - light and dark theme support
    - styles that stay contained within this block only
    - visible schema microdata on the block markup itself

  Important implementation note:
  - Margin classes belong on the OUTER section wrapper.
  - Padding classes belong on the INNER testimonial band/card shell.
  - This prevents section padding from visually behaving like outer margin
    when the block is rendered in contained mode.

  Current tweak:
  - The quote icon has been simplified to match the reference more closely:
    - quotation marks only
    - no background treatment
    - no circle border
    - larger quote glyph
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

  $blockId = !empty($anchor) ? $anchor : 'sccc-testimonial-' . uniqid();

  $layoutClass = $sectionLayout === 'full_width'
    ? 'sccc-testimonial-block--full'
    : 'sccc-testimonial-block--contained';

  /**
   * Split spacing classes by responsibility.
   *
   * Why this exists:
   * - mt/mb should affect the OUTER section wrapper.
   * - pt/pb/pl/pr should affect the INNER band/card shell.
   * - Keeping both on the outer section causes padding to look like margin
   *   in contained layouts because the band is visually separated inside it.
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
    'sccc-testimonial-block',
    $layoutClass,
    implode(' ', $sectionMarginClasses),
    $block->classes ?? '',
    $customClassName,
  ])));

  $bandClasses = trim(implode(' ', array_filter([
    'sccc-testimonial-block__band',
    implode(' ', $bandPaddingClasses),
  ])));

  $hasImage = !empty($imageId);
  $avatarLetter = strtoupper(mb_substr(trim((string) $name), 0, 1)) ?: 'T';
@endphp

@once
  <style>
    .sccc-testimonial-block {
      --sccc-testimonial-bg: #141414;
      --sccc-testimonial-text: #ffffff;
      --sccc-testimonial-muted: #a0a7b2;
      --sccc-testimonial-line: rgba(255, 255, 255, 0.12);
      --sccc-testimonial-accent: var(--color-primary-500, var(--color-primary, #2979ff));
      --sccc-testimonial-shadow:
        0 18px 40px rgba(0, 0, 0, 0.34),
        0 0 0 1px rgba(255, 255, 255, 0.03);

      /**
       * Quote icon styling
       *
       * Why these variables exist:
       * - The reference treatment now calls for quotation marks only.
       * - No circle, no border, no background, no surrounding badge.
       * - The glyph itself should read larger and cleaner.
       */
      --sccc-testimonial-quote-text: var(--sccc-testimonial-accent);

      color: var(--sccc-testimonial-text);
    }

    html[data-theme="light"] .sccc-testimonial-block {
      --sccc-testimonial-bg: #f4f6f8;
      --sccc-testimonial-text: #101622;
      --sccc-testimonial-muted: #5f6670;
      --sccc-testimonial-line: rgba(16, 22, 34, 0.12);
      --sccc-testimonial-shadow:
        0 18px 34px rgba(16, 22, 34, 0.08),
        0 0 0 1px rgba(16, 22, 34, 0.04);
      --sccc-testimonial-quote-text: var(--color-primary-600, var(--color-primary, #2979ff));
    }

    html[data-theme="dark"] .sccc-testimonial-block,
    html.dark .sccc-testimonial-block,
    .dark .sccc-testimonial-block {
      --sccc-testimonial-bg: #0a0a0a;
      --sccc-testimonial-text: #ffffff;
      --sccc-testimonial-muted: #a0a7b2;
      --sccc-testimonial-line: rgba(255, 255, 255, 0.12);
      --sccc-testimonial-shadow:
        0 18px 40px rgba(0, 0, 0, 0.34),
        0 0 0 1px rgba(255, 255, 255, 0.03);
      --sccc-testimonial-quote-text: var(--color-primary-500, var(--color-primary, #2979ff));
    }

    .sccc-testimonial-block__band {
      position: relative;
      isolation: isolate;
      overflow: hidden;
      background:
        radial-gradient(circle at top left, rgba(41, 121, 255, 0.12), transparent 42%),
        radial-gradient(circle at top right, rgba(41, 121, 255, 0.08), transparent 36%),
        var(--sccc-testimonial-bg);
    }

    .sccc-testimonial-block--contained .sccc-testimonial-block__band {
      max-width: 84rem;
      margin-inline: auto;
      border-radius: 1.5rem;
      border: 1px solid var(--sccc-testimonial-line);
      box-shadow: var(--sccc-testimonial-shadow);
    }

    .sccc-testimonial-block--full .sccc-testimonial-block__band {
      border-block: 1px solid var(--sccc-testimonial-line);
    }

    .sccc-testimonial-block__container {
      max-width: 64rem;
      margin-inline: auto;
      text-align: center;
    }

    .sccc-testimonial-block__quote-icon {
      width: auto;
      height: auto;
      border-radius: 0;
      margin-inline: auto;
      margin-bottom: 1.65rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: transparent;
      color: var(--sccc-testimonial-quote-text);
      border: 0;
      box-shadow: none;
      padding: 0;
    }

    .sccc-testimonial-block__quote-icon svg {
      width: 3rem;
      height: 3rem;
      display: block;
    }

    .sccc-testimonial-block__quote {
      margin: 0;
      font-family: var(--font-display, var(--font-headline, "Space Grotesk", system-ui, sans-serif));
      font-weight: 800;
      letter-spacing: -0.03em;
      line-height: 1.12;
      color: var(--sccc-testimonial-text);
      font-size: clamp(1.9rem, 3vw, 3.4rem);
    }

    .sccc-testimonial-block__footer {
      margin-top: 2rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 1rem;
      text-align: left;
    }

    .sccc-testimonial-block__avatar {
      width: 3.5rem;
      height: 3.5rem;
      flex: 0 0 3.5rem;
      border-radius: 9999px;
      overflow: hidden;
      border: 2px solid color-mix(in srgb, var(--sccc-testimonial-accent) 60%, transparent);
      box-shadow: 0 10px 22px color-mix(in srgb, var(--sccc-testimonial-accent) 14%, transparent);
      background: color-mix(in srgb, var(--sccc-testimonial-accent) 12%, transparent);
    }

    .sccc-testimonial-block__avatar img {
      display: block;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .sccc-testimonial-block__avatar-placeholder {
      display: flex;
      width: 100%;
      height: 100%;
      align-items: center;
      justify-content: center;
      font-family: var(--font-display, var(--font-headline, "Space Grotesk", system-ui, sans-serif));
      font-weight: 800;
      font-size: 1rem;
      color: var(--sccc-testimonial-text);
      text-transform: uppercase;
    }

    .sccc-testimonial-block__name {
      margin: 0;
      font-family: var(--font-display, var(--font-headline, "Space Grotesk", system-ui, sans-serif));
      font-size: 0.95rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      color: var(--sccc-testimonial-text);
    }

    .sccc-testimonial-block__meta {
      margin: 0.3rem 0 0;
      font-size: 0.78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.16em;
      color: var(--sccc-testimonial-muted);
    }

    .sccc-testimonial-block__rich-text br {
      content: "";
    }

    .sccc-testimonial-block__schema-only {
      display: none;
    }

    @media (max-width: 640px) {
      .sccc-testimonial-block__quote-icon {
        margin-bottom: 1.4rem;
      }

      .sccc-testimonial-block__quote-icon svg {
        width: 2.55rem;
        height: 2.55rem;
      }

      .sccc-testimonial-block__footer {
        gap: 0.85rem;
      }

      .sccc-testimonial-block__avatar {
        width: 3rem;
        height: 3rem;
        flex-basis: 3rem;
      }
    }
  </style>
@endonce

<section
  id="{{ esc_attr($blockId) }}"
  class="{{ esc_attr($sectionClasses) }}"
  itemscope
  itemtype="https://schema.org/Review"
>
  <meta itemprop="reviewAspect" content="Membership Experience">

  <div class="sccc-testimonial-block__schema-only" itemprop="itemReviewed" itemscope itemtype="https://schema.org/Organization">
    <meta itemprop="name" content="Space City Car Club">
    <meta itemprop="url" content="{{ home_url('/') }}">
  </div>

  <div class="{{ esc_attr($bandClasses) }}">
    <div class="sccc-testimonial-block__container">
      @if ($showQuoteIcon)
        <div class="sccc-testimonial-block__quote-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M7.2 5.25c-2.62 1.82-4.2 4.62-4.2 7.65 0 3.38 2.35 5.85 5.52 5.85 2.74 0 4.88-2.1 4.88-4.88 0-2.72-1.95-4.65-4.53-4.65-.44 0-.84.04-1.2.14.55-1.26 1.56-2.41 2.96-3.28L7.2 5.25Zm10 0c-2.62 1.82-4.2 4.62-4.2 7.65 0 3.38 2.35 5.85 5.52 5.85 2.74 0 4.88-2.1 4.88-4.88 0-2.72-1.95-4.65-4.53-4.65-.44 0-.84.04-1.2.14.55-1.26 1.56-2.41 2.96-3.28L17.2 5.25Z"/>
          </svg>
        </div>
      @endif

      @if (!empty($quote))
        <blockquote class="sccc-testimonial-block__quote sccc-testimonial-block__rich-text" itemprop="reviewBody">
          {!! wp_kses_post($quote) !!}
        </blockquote>
      @endif

      @if (!empty($name) || !empty($metaLine) || $hasImage)
        <div class="sccc-testimonial-block__footer" itemprop="author" itemscope itemtype="https://schema.org/Person">
          <div class="sccc-testimonial-block__avatar" aria-hidden="true">
            @if ($hasImage)
              {!! wp_get_attachment_image($imageId, 'thumbnail', false, ['loading' => 'lazy', 'itemprop' => 'image']) !!}
            @else
              <div class="sccc-testimonial-block__avatar-placeholder">
                {{ $avatarLetter }}
              </div>
            @endif
          </div>

          <div class="sccc-testimonial-block__identity">
            @if (!empty($name))
              <p class="sccc-testimonial-block__name" itemprop="name">{{ $name }}</p>
            @endif

            @if (!empty($metaLine))
              <p class="sccc-testimonial-block__meta">{{ $metaLine }}</p>
            @endif
          </div>
        </div>
      @endif
    </div>
  </div>
</section>