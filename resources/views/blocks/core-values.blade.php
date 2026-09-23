{{--
==============================================================================
File path + filename: resources/views/blocks/core-values.blade.php
==============================================================================

Purpose:
- Render the Core Values section as a reusable page block.

Why this file exists:
- The user wants a block that closely matches the supplied Core Values section
  from the example page, while allowing editors to fully control the heading,
  subheading, section spacing, and card content.
- The section styles are scoped to this block only so they do not affect the
  rest of the theme or add bulk to the global stylesheet.

Visual targets from the reference:
- White section on light theme, deep dark section on dark theme
- Decorative blurred primary blob in the top-right background
- Centered intro heading + subheading
- Cards with:
  - soft surface
  - subtle border
  - icon chip
  - hover lift
  - hover border accent
  - icon chip turning solid primary on hover
- Dark mode should stay very close to the supplied mockup

Responsive layout:
- Mobile: 1 card per row
- md: 2 cards per row
- lg: 3 cards per row

Improved layout behavior:
- 1 total card:
  - centered
  - constrained to a sensible max width
- 2 total cards:
  - use a clean 2-column layout at large screens
- 4 / 7 / 10 ... cards:
  - if the final row would contain one single orphan card in a 3-column large
    layout, that last card stretches across the full row on lg and up

Spacing behavior:
- Margin top, margin bottom, padding top, and padding bottom are controlled by
  ACF select fields.
- The values are applied to the outer section as CSS variables.
- This avoids relying on Tailwind utility classes entered manually through the
  Gutenberg Advanced panel.

Important:
- The site already loads Material Symbols globally.
- Card body text now allows safe WordPress-formatted line breaks and paragraphs.

Change note:
- Fixes visible "<br />" output in the subheading and card description fields.
- Uses WordPress formatting + sanitization before printing formatted HTML.
- Keeps the change scoped to this Blade file only.
--}}

@php
  $cardCount = is_array($cards ?? null) ? count($cards) : 0;
  $hasStretchedOrphanOnLarge = $cardCount > 3 && $cardCount % 3 === 1;

  $sectionStyle = sprintf(
    '--sccc-core-margin-top: %s; --sccc-core-margin-bottom: %s; --sccc-core-padding-top: %s; --sccc-core-padding-bottom: %s;',
    $marginTop ?? '1.5rem',
    $marginBottom ?? '1.5rem',
    $paddingTop ?? '3rem',
    $paddingBottom ?? '3rem'
  );

  /**
   * Format Core Values body copy safely for frontend display.
   *
   * Why this exists:
   * - ACF Text Area fields may already return <br> tags when the field's
   *   "New Lines" setting is set to automatically add line breaks.
   * - Escaping the value before printing it causes those <br> tags to appear
   *   as visible frontend text.
   * - This helper decodes existing entities, lets WordPress normalize paragraphs
   *   and line breaks, then sanitizes the final HTML before Blade prints it.
   */
  $formatCoreValuesText = static function ($value): string {
    $value = trim((string) ($value ?? ''));

    if ($value === '') {
      return '';
    }

    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return wp_kses_post(wpautop($value));
  };
@endphp

@once
  <style>
    /* ==========================================================================
       Core Values block
       Fully scoped so styles stay local to this block.
       ========================================================================== */

    .sccc-core-values {
      --sccc-core-bg-light: #ffffff;
      --sccc-core-bg-dark: #0c111a;
      --sccc-core-surface-light: #f6f6f8;
      --sccc-core-surface-dark: #1e293b;
      --sccc-core-text-light: #111722;
      --sccc-core-text-dark: #ffffff;
      --sccc-core-copy-light: #4b5563;
      --sccc-core-copy-dark: #9ca3af;
      --sccc-core-border-light: #e5e7eb;
      --sccc-core-border-dark: #1f2937;
      --sccc-core-primary: #135bec;
      --sccc-core-primary-soft: rgba(19, 91, 236, 0.10);
      --sccc-core-shadow-hover: 0 20px 30px -12px rgba(19, 91, 236, 0.16);
      width: 100%;
      margin-top: var(--sccc-core-margin-top, 1.5rem);
      margin-bottom: var(--sccc-core-margin-bottom, 1.5rem);
      padding-top: var(--sccc-core-padding-top, 3rem);
      padding-bottom: var(--sccc-core-padding-bottom, 3rem);
      background: var(--sccc-core-bg-light);
      position: relative;
      overflow: hidden;
    }

    [data-theme="dark"] .sccc-core-values,
    .dark .sccc-core-values {
      background: var(--sccc-core-bg-dark);
    }

    .sccc-core-values *,
    .sccc-core-values *::before,
    .sccc-core-values *::after {
      box-sizing: border-box;
    }

    .sccc-core-values__blob {
      position: absolute;
      top: 0;
      right: 0;
      width: 31.25rem;
      height: 31.25rem;
      border-radius: 9999px;
      background: rgba(19, 91, 236, 0.05);
      filter: blur(48px);
      transform: translate(50%, -50%);
      pointer-events: none;
    }

    .sccc-core-values__inner {
      position: relative;
      z-index: 1;
      max-width: 75rem;
      margin-inline: auto;
      padding-inline: 1rem;
    }

    .sccc-core-values__intro {
      max-width: 60rem;
      margin-inline: auto;
      margin-bottom: 3rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }

    .sccc-core-values__heading {
      margin: 0 0 1rem;
      color: var(--sccc-core-text-light);
      font-size: 1.875rem;
      font-weight: 700;
      line-height: 1.2;
      text-transform: uppercase;
      letter-spacing: -0.02em;
    }

    [data-theme="dark"] .sccc-core-values__heading,
    .dark .sccc-core-values__heading {
      color: var(--sccc-core-text-dark);
    }

    .sccc-core-values__subheading {
      margin: 0;
      max-width: 34rem;
      color: var(--sccc-core-copy-light);
      font-size: 1rem;
      line-height: 1.7;
    }

    [data-theme="dark"] .sccc-core-values__subheading,
    .dark .sccc-core-values__subheading {
      color: var(--sccc-core-copy-dark);
    }

    /*
     * Formatted body-copy reset.
     *
     * Why this exists:
     * - The Blade template now allows safe formatted HTML for subheading and
     *   card description copy.
     * - WordPress may generate <p> tags through wpautop(), so these rules keep
     *   the spacing clean and consistent with the original design.
     */
    .sccc-core-values__subheading p,
    .sccc-core-values__description p {
      margin: 0;
    }

    .sccc-core-values__subheading p + p,
    .sccc-core-values__description p + p {
      margin-top: 0.75rem;
    }

    .sccc-core-values__grid {
      max-width: 75rem;
      margin-inline: auto;
    }

    .sccc-core-values__card {
      height: 100%;
      padding: 2rem;
      border-radius: 0.75rem;
      background: var(--sccc-core-surface-light);
      border: 1px solid var(--sccc-core-border-light);
      transition:
        transform 300ms ease,
        border-color 300ms ease,
        box-shadow 300ms ease;
    }

    [data-theme="dark"] .sccc-core-values__card,
    .dark .sccc-core-values__card {
      background: var(--sccc-core-surface-dark);
      border-color: var(--sccc-core-border-dark);
    }

    .sccc-core-values__card:hover,
    .sccc-core-values__card:focus-within {
      transform: translateY(-0.25rem);
      border-color: rgba(19, 91, 236, 0.50);
      box-shadow: var(--sccc-core-shadow-hover);
    }

    .sccc-core-values__icon-wrap {
      width: 3rem;
      height: 3rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1.5rem;
      border-radius: 0.5rem;
      background: var(--sccc-core-primary-soft);
      color: var(--sccc-core-primary);
      transition:
        background-color 300ms ease,
        color 300ms ease;
    }

    .sccc-core-values__card:hover .sccc-core-values__icon-wrap,
    .sccc-core-values__card:focus-within .sccc-core-values__icon-wrap {
      background: var(--sccc-core-primary);
      color: #ffffff;
    }

    .sccc-core-values__icon {
      display: block;
      font-size: 1.875rem;
      line-height: 1;
    }

    .sccc-core-values__title {
      margin: 0 0 0.75rem;
      color: var(--sccc-core-text-light);
      font-size: 1.25rem;
      font-weight: 700;
      line-height: 1.3;
    }

    [data-theme="dark"] .sccc-core-values__title,
    .dark .sccc-core-values__title {
      color: var(--sccc-core-text-dark);
    }

    .sccc-core-values__description {
      margin: 0;
      color: var(--sccc-core-copy-light);
      font-size: 1rem;
      line-height: 1.7;
    }

    [data-theme="dark"] .sccc-core-values__description,
    .dark .sccc-core-values__description {
      color: var(--sccc-core-copy-dark);
    }

    .sccc-core-values__empty {
      color: var(--sccc-core-copy-light);
      text-align: center;
      font-size: 1rem;
      line-height: 1.6;
    }

    [data-theme="dark"] .sccc-core-values__empty,
    .dark .sccc-core-values__empty {
      color: var(--sccc-core-copy-dark);
    }

    /* ==========================================================================
       Smarter grid behavior for 1, 2, and orphaned final cards
       ========================================================================== */
    @media (min-width: 1024px) {
      .sccc-core-values__grid[data-card-count="1"] {
        grid-template-columns: minmax(0, 1fr);
        max-width: 42rem;
      }

      .sccc-core-values__grid[data-card-count="2"] {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .sccc-core-values__grid[data-stretched-orphan="true"] > .sccc-core-values__card:last-child {
        grid-column: 1 / -1;
      }
    }
  </style>
@endonce

<section {{ $attributes->class('sccc-core-values')->merge(['style' => $sectionStyle]) }}>
  <div class="sccc-core-values__blob" aria-hidden="true"></div>

  <div class="sccc-core-values__inner">
    <header class="sccc-core-values__intro">
      <h2 class="sccc-core-values__heading">{{ $heading }}</h2>

      @if ($subheading)
        <div class="sccc-core-values__subheading">{!! $formatCoreValuesText($subheading) !!}</div>
      @endif
    </header>

    @if (! empty($cards))
      <div
        class="sccc-core-values__grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
        data-card-count="{{ $cardCount }}"
        data-stretched-orphan="{{ $hasStretchedOrphanOnLarge ? 'true' : 'false' }}"
      >
        @foreach ($cards as $card)
          <article class="sccc-core-values__card group">
            <div class="sccc-core-values__icon-wrap" aria-hidden="true">
              <span class="material-symbols-outlined sccc-core-values__icon">{{ $card['icon'] }}</span>
            </div>

            @if ($card['title'])
              <h3 class="sccc-core-values__title">{{ $card['title'] }}</h3>
            @endif

            @if ($card['description'])
              <div class="sccc-core-values__description">{!! $formatCoreValuesText($card['description']) !!}</div>
            @endif
          </article>
        @endforeach
      </div>
    @else
      <p class="sccc-core-values__empty">Add at least one Core Values card to display this section.</p>

      @if ($isPreview)
        <p class="sccc-core-values__empty">This block supports a flexible number of cards and responsive 1 / 2 / 3 column layouts.</p>
      @endif
    @endif
  </div>
</section>