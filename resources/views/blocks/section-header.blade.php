{{--
  resources/views/blocks/section-header.blade.php

  Reusable Section Header block view for the Space City Car Club Sage theme.

  Why this file exists:
  - Keeps section intro markup consistent across pages.
  - Fixes wrapper alignment separately from text alignment.
  - Provides predictable bottom spacing so the next block does not sit too close.
  - Keeps all visual CSS scoped to this one block instance.
--}}
@php
  $uid = 'sccc-section-header-' . uniqid();

  /**
   * Width presets.
   *
   * Why this exists:
   * - Editors get readable content widths without manually choosing utility classes.
   * - w-full below makes max-width behavior predictable inside both normal containers
   *   and wider/full-width block contexts.
   */
  $widthClass = match ($width ?? 'normal') {
    'narrow' => 'max-w-[60ch]',
    'wide'   => 'max-w-[92ch]',
    default  => 'max-w-[75ch]',
  };

  /**
   * Alignment presets.
   *
   * Why this exists:
   * - text-* controls the text alignment.
   * - items-* controls the flex children.
   * - mr-auto / mx-auto / ml-auto controls where the max-width wrapper itself sits.
   */
  $align = $align ?? '';
  $alignClass = match ($align) {
    'left'   => 'text-left items-start mr-auto',
    'right'  => 'text-right items-end ml-auto',
    'center' => 'text-center items-center mx-auto',
    default  => 'text-left items-start mr-auto',
  };

  /**
   * Bottom spacing preset.
   *
   * Why this exists:
   * - Gives the block a reliable visual rhythm even when Gutenberg margin controls
   *   are left untouched.
   */
  $spacingAfter = match ($spacing_after ?? 'medium') {
    'none'  => 'sccc-section-header--space-none',
    'small' => 'sccc-section-header--space-small',
    'large' => 'sccc-section-header--space-large',
    'xl'    => 'sccc-section-header--space-xl',
    default => 'sccc-section-header--space-medium',
  };
@endphp

<div id="{{ $uid }}" class="{{ $block->classes }} sccc-section-header {{ $spacingAfter }}">
  <div class="sccc-sh w-full {{ $alignClass }} {{ $widthClass }}">
    @if (!empty($eyebrow))
      <p class="sccc-sh__eyebrow">{!! e($eyebrow) !!}</p>
    @endif

    @if (!empty($headline))
      <h2 class="sccc-sh__headline">{!! $highlighted !!}</h2>
    @endif

    @if (!empty($description))
      <div class="sccc-sh__desc">{!! nl2br(e($description)) !!}</div>
    @endif

    @if (!empty($show_accent_rule))
      <span class="sccc-sh__accent" aria-hidden="true"></span>
    @endif
  </div>

  <style>
    /*
     * Instance scope.
     *
     * All styles are attached to this generated ID so this block does not leak into
     * other headings, Gutenberg blocks, WooCommerce templates, or plugin markup.
     */
    #{{ $uid }} {
      --sccc-gradient-club-blue: linear-gradient(90deg, #135bec 0%, #1f8fff 52%, #71d7ff 100%);
      --sccc-gradient-signal-red: linear-gradient(90deg, #b31324 0%, #ff1744 50%, #ff7a59 100%);
      --sccc-gradient-space-city: linear-gradient(90deg, #135bec 0%, #43beff 42%, #ae71ff 72%, #ff1744 100%);
      --sccc-color-club-blue: #135bec;
      --sccc-color-signal-red: #e53935;
      --sccc-color-deep-purple: #ae71ff;
    }

    /*
     * Default spacing after the block.
     *
     * These are intentionally margin-block-end values so Gutenberg's own top/bottom
     * padding controls can still be used independently when needed.
     */
    #{{ $uid }}.sccc-section-header--space-none {
      margin-block-end: 0;
    }

    #{{ $uid }}.sccc-section-header--space-small {
      margin-block-end: clamp(1rem, 2vw, 1.5rem);
    }

    #{{ $uid }}.sccc-section-header--space-medium {
      margin-block-end: clamp(1.5rem, 3vw, 3rem);
    }

    #{{ $uid }}.sccc-section-header--space-large {
      margin-block-end: clamp(2.25rem, 4vw, 4.5rem);
    }

    #{{ $uid }}.sccc-section-header--space-xl {
      margin-block-end: clamp(3rem, 6vw, 6rem);
    }

    /*
     * Main header stack.
     *
     * display:flex enables the alignment classes from PHP/Tailwind to control both
     * copy alignment and the accent rule position.
     */
    #{{ $uid }} .sccc-sh {
      display: flex;
      flex-direction: column;
      gap: .5rem;
    }

    #{{ $uid }} .sccc-sh__eyebrow {
      font-family: var(--font-headline, inherit);
      font-size: .75rem;
      font-weight: 700;
      letter-spacing: .12em;
      line-height: 1.2;
      margin: 0;
      text-transform: uppercase;
      color: var(--color-primary-500, var(--primary));
    }

    #{{ $uid }} .sccc-sh__headline {
      font-family: var(--font-headline, inherit);
      font-weight: 800;
      line-height: 1.05;
      margin: 0;
      font-size: clamp(1.75rem, 3.6vw, 3rem);
      color: var(--color-text, var(--text, currentColor));
    }

    #{{ $uid }} .sccc-sh__desc {
      font-family: var(--font-body, inherit);
      font-size: 1rem;
      line-height: 1.65;
      margin: .25rem 0 0;
      max-width: 72ch;
      color: var(--color-muted, var(--muted, rgba(255, 255, 255, .72)));
    }

    /*
     * Optional visual finish.
     *
     * The rule gives the header a clear bottom edge and helps visually separate it
     * from the following block without forcing heavy padding.
     */
    #{{ $uid }} .sccc-sh__accent {
      display: block;
      width: min(9rem, 42vw);
      height: 2px;
      margin-block-start: .55rem;
      border-radius: 999px;
      background: var(--sccc-gradient-space-city);
      box-shadow: 0 0 18px color-mix(in oklab, var(--sccc-color-club-blue) 42%, transparent);
    }

    /* Highlight span wiring */
    #{{ $uid }} [data-sccc-highlight],
    #{{ $uid }} .sccc-text-gradient {
      background-image: var(--sccc-gradient-club-blue);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      -webkit-text-fill-color: transparent;
      text-shadow: none;
      white-space: normal;
    }

    #{{ $uid }} [data-sccc-highlight] strong,
    #{{ $uid }} .sccc-text-gradient strong {
      font-weight: 900;
    }

    #{{ $uid }} .sccc-text-gradient--club-blue {
      background-image: var(--sccc-gradient-club-blue);
    }

    #{{ $uid }} .sccc-text-gradient--signal-red {
      background-image: var(--sccc-gradient-signal-red);
    }

    #{{ $uid }} .sccc-text-gradient--space-city {
      background-image: var(--sccc-gradient-space-city);
    }

    #{{ $uid }} .sccc-text-gradient--club-blue-solid,
    #{{ $uid }} .sccc-text-gradient--signal-red-solid,
    #{{ $uid }} .sccc-text-gradient--deep-purple-solid {
      background: none;
      -webkit-background-clip: initial;
      background-clip: initial;
      -webkit-text-fill-color: currentColor;
    }

    #{{ $uid }} .sccc-text-gradient--club-blue-solid {
      color: var(--sccc-color-club-blue);
    }

    #{{ $uid }} .sccc-text-gradient--signal-red-solid {
      color: var(--sccc-color-signal-red);
    }

    #{{ $uid }} .sccc-text-gradient--deep-purple-solid {
      color: var(--sccc-color-deep-purple);
    }
  </style>
</div>