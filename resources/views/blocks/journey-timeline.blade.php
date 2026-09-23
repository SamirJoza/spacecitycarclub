{{--
==============================================================================
File path + filename: resources/views/blocks/journey-timeline.blade.php
==============================================================================

Purpose:
- Render a flexible timeline content block for page layouts.

Why this file exists:
- The visual target is the timeline section from the provided About Us page.
- The structure must remain flexible and editor-friendly while preserving the
  strong visual rhythm of the reference:
  - centered heading
  - narrow rail
  - circular icon marker
  - connector line
  - title + date label + description
- The block styles are intentionally scoped and embedded here so they do not
  add bulk or side effects to the main stylesheet.

Dark theme note:
- The dark theme stays very close to the provided HTML example:
  - dark background section
  - primary accent
  - muted timeline body copy
  - blue-gray connector line
  - primary-on-dark icon chip treatment

Light theme note:
- The same structure is preserved, but colors are softened for readability.

Important:
- This block assumes `material-symbols-outlined` is already available globally,
  which matches the project setup described by the user.
- Repeater rows are already normalized by the block class.

Change note:
- Fixes visible "<br />" output in the intro and description fields.
- Uses WordPress formatting + sanitization before printing formatted HTML.
- Keeps the change scoped to this Blade file only.
--}}

@php
  /**
   * Format timeline body copy safely for frontend display.
   *
   * Why this exists:
   * - ACF Text Area fields can already return <br> tags depending on the
   *   field's "New Lines" setting.
   * - Escaping the value before printing it causes those <br> tags to appear
   *   as visible frontend text.
   * - This helper allows normal line breaks/paragraphs while still sanitizing
   *   the final HTML through WordPress before Blade prints it.
   */
  $formatTimelineText = static function ($value): string {
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
       Journey Timeline block
       Fully scoped so it does not leak into the rest of the theme.
       ========================================================================== */

    .sccc-journey-timeline {
      --sccc-timeline-bg-light: #f6f6f8;
      --sccc-timeline-bg-dark: #101622;
      --sccc-timeline-text-light: #111722;
      --sccc-timeline-text-dark: #ffffff;
      --sccc-timeline-copy-light: #5f6b7a;
      --sccc-timeline-copy-dark: #92a4c9;
      --sccc-timeline-primary: #135bec;
      --sccc-timeline-line-light: #c5d1ea;
      --sccc-timeline-line-dark: #324467;
      --sccc-timeline-chip-light: rgba(19, 91, 236, 0.10);
      --sccc-timeline-chip-dark: #135bec;
      width: 100%;
      margin-block: 1.5rem;
      padding-block: 4rem;
      background: var(--sccc-timeline-bg-light);
    }

    [data-theme="dark"] .sccc-journey-timeline,
    .dark .sccc-journey-timeline {
      background: var(--sccc-timeline-bg-dark);
    }

    .sccc-journey-timeline *,
    .sccc-journey-timeline *::before,
    .sccc-journey-timeline *::after {
      box-sizing: border-box;
    }

    .sccc-journey-timeline__inner {
      max-width: 60rem;
      margin-inline: auto;
      padding-inline: 1rem;
    }

    .sccc-journey-timeline__header {
      margin-bottom: 2rem;
      text-align: center;
    }

    .sccc-journey-timeline__heading {
      margin: 0;
      padding-top: 1.5rem;
      padding-bottom: 0.75rem;
      color: var(--sccc-timeline-text-light);
      font-size: 2rem;
      font-weight: 700;
      line-height: 1.2;
      letter-spacing: -0.02em;
      text-transform: uppercase;
    }

    [data-theme="dark"] .sccc-journey-timeline__heading,
    .dark .sccc-journey-timeline__heading {
      color: var(--sccc-timeline-text-dark);
    }

    .sccc-journey-timeline__intro {
      max-width: 40rem;
      margin: 0 auto;
      color: var(--sccc-timeline-copy-light);
      font-size: 1rem;
      line-height: 1.7;
    }

    [data-theme="dark"] .sccc-journey-timeline__intro,
    .dark .sccc-journey-timeline__intro {
      color: var(--sccc-timeline-copy-dark);
    }

    /*
     * Formatted body-copy reset.
     *
     * Why this exists:
     * - The Blade template now allows safe formatted HTML for intro and
     *   description copy.
     * - WordPress may generate <p> tags through wpautop(), so these rules keep
     *   the spacing clean and consistent with the original design.
     */
    .sccc-journey-timeline__intro p,
    .sccc-journey-timeline__description p {
      margin: 0;
    }

    .sccc-journey-timeline__intro p + p,
    .sccc-journey-timeline__description p + p {
      margin-top: 0.75rem;
    }

    .sccc-journey-timeline__grid {
      display: grid;
      grid-template-columns: 2.5rem minmax(0, 1fr);
      column-gap: 1rem;
    }

    .sccc-journey-timeline__rail {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.25rem;
      padding-top: 0.75rem;
    }

    .sccc-journey-timeline__line {
      width: 2px;
      background: var(--sccc-timeline-line-light);
      flex: 0 0 auto;
    }

    [data-theme="dark"] .sccc-journey-timeline__line,
    .dark .sccc-journey-timeline__line {
      background: var(--sccc-timeline-line-dark);
    }

    .sccc-journey-timeline__line--top {
      height: 1rem;
    }

    .sccc-journey-timeline__line--bottom {
      min-height: 3.75rem;
      height: 100%;
      flex-grow: 1;
      background:
        linear-gradient(
          to bottom,
          var(--sccc-timeline-primary),
          var(--sccc-timeline-line-light)
        );
    }

    [data-theme="dark"] .sccc-journey-timeline__line--bottom,
    .dark .sccc-journey-timeline__line--bottom {
      background:
        linear-gradient(
          to bottom,
          var(--sccc-timeline-primary),
          var(--sccc-timeline-line-dark)
        );
    }

    .sccc-journey-timeline__icon-wrap {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.25rem;
      border-radius: 9999px;
      background: var(--sccc-timeline-chip-light);
      color: var(--sccc-timeline-primary);
    }

    [data-theme="dark"] .sccc-journey-timeline__icon-wrap,
    .dark .sccc-journey-timeline__icon-wrap {
      background: var(--sccc-timeline-chip-dark);
      color: #ffffff;
    }

    .sccc-journey-timeline__icon-wrap.is-emphasized {
      box-shadow:
        0 0 0 6px rgba(19, 91, 236, 0.10),
        0 10px 24px rgba(19, 91, 236, 0.18);
    }

    [data-theme="dark"] .sccc-journey-timeline__icon-wrap.is-emphasized,
    .dark .sccc-journey-timeline__icon-wrap.is-emphasized {
      box-shadow:
        0 0 0 6px rgba(19, 91, 236, 0.16),
        0 10px 24px rgba(19, 91, 236, 0.28);
    }

    .sccc-journey-timeline__icon {
      display: block;
      font-size: 1.125rem;
      line-height: 1;
    }

    .sccc-journey-timeline__item {
      padding-top: 0.75rem;
      padding-bottom: 2rem;
    }

    .sccc-journey-timeline__item:last-child {
      padding-bottom: 0.75rem;
    }

    .sccc-journey-timeline__title {
      margin: 0;
      color: var(--sccc-timeline-text-light);
      font-size: 1.25rem;
      font-weight: 700;
      line-height: 1.35;
    }

    [data-theme="dark"] .sccc-journey-timeline__title,
    .dark .sccc-journey-timeline__title {
      color: var(--sccc-timeline-text-dark);
    }

    .sccc-journey-timeline__date {
      display: inline-block;
      margin-top: 0.25rem;
      margin-bottom: 0.5rem;
      color: var(--sccc-timeline-primary);
      font-size: 0.875rem;
      font-weight: 700;
      line-height: 1.35;
    }

    .sccc-journey-timeline__description {
      margin: 0;
      color: var(--sccc-timeline-copy-light);
      font-size: 1rem;
      font-weight: 400;
      line-height: 1.7;
    }

    [data-theme="dark"] .sccc-journey-timeline__description,
    .dark .sccc-journey-timeline__description {
      color: var(--sccc-timeline-copy-dark);
    }

    .sccc-journey-timeline__empty {
      text-align: center;
      color: var(--sccc-timeline-copy-light);
      font-size: 1rem;
      line-height: 1.6;
    }

    [data-theme="dark"] .sccc-journey-timeline__empty,
    .dark .sccc-journey-timeline__empty {
      color: var(--sccc-timeline-copy-dark);
    }

    @media (min-width: 768px) {
      .sccc-journey-timeline__inner {
        padding-inline: 0;
      }

      .sccc-journey-timeline__grid {
        padding-inline: 1rem;
      }

      .sccc-journey-timeline__heading {
        font-size: 2rem;
      }

      .sccc-journey-timeline__title {
        font-size: 1.25rem;
      }
    }
  </style>
@endonce

<section {{ $attributes->class('sccc-journey-timeline') }}>
  <div class="sccc-journey-timeline__inner">
    <header class="sccc-journey-timeline__header">
      <h2 class="sccc-journey-timeline__heading">{{ $heading }}</h2>

      @if ($intro)
        <div class="sccc-journey-timeline__intro">{!! $formatTimelineText($intro) !!}</div>
      @endif
    </header>

    @if (! empty($items))
      <div class="sccc-journey-timeline__grid">
        @foreach ($items as $item)
          <div class="sccc-journey-timeline__rail">
            @unless ($loop->first)
              <div class="sccc-journey-timeline__line sccc-journey-timeline__line--top" aria-hidden="true"></div>
            @endunless

            <div class="sccc-journey-timeline__icon-wrap {{ $item['isEmphasized'] ? 'is-emphasized' : '' }}">
              <span class="material-symbols-outlined sccc-journey-timeline__icon">{{ $item['icon'] }}</span>
            </div>

            @unless ($loop->last)
              <div class="sccc-journey-timeline__line sccc-journey-timeline__line--bottom" aria-hidden="true"></div>
            @endunless
          </div>

          <div class="sccc-journey-timeline__item">
            @if ($item['title'])
              <p class="sccc-journey-timeline__title">{{ $item['title'] }}</p>
            @endif

            @if ($item['dateLabel'])
              <span class="sccc-journey-timeline__date">{{ $item['dateLabel'] }}</span>
            @endif

            @if ($item['description'])
              <div class="sccc-journey-timeline__description">{!! $formatTimelineText($item['description']) !!}</div>
            @endif
          </div>
        @endforeach
      </div>
    @else
      <p class="sccc-journey-timeline__empty">
        Add at least one timeline item to display the journey timeline.
      </p>

      @if ($isPreview)
        <p class="sccc-journey-timeline__empty">
          This block is built for repeatable milestones with curated Material Symbols icons.
        </p>
      @endif
    @endif
  </div>
</section>