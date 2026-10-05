{{--
  resources/views/blocks/section-header.blade.php

  Reusable Section Header block view for the Space City Car Club Sage theme.

  Why this file exists:
  - Keeps section intro markup consistent across pages.
  - Fixes wrapper alignment separately from text alignment.
  - Provides predictable bottom spacing so the next block does not sit too close.
  - Shared look: resources/css/components/section-header.css (also used by <x-ui.section-header>).
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
     * Layout, type and spacing live in resources/css/components/section-header.css.
     * Only the headline highlight stays here, scoped to this instance.
     */

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