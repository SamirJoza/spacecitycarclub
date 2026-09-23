{{--
  File: resources/views/blocks/action-steps.blade.php

  Action Steps block template.

  This version keeps the current layout, main CTA styling, and numbered step
  markers, but makes the right-pane step CTAs visually smaller so they do not
  compete with the main CTA in the left column.

  What changed in this version:
  - Added a dedicated .sccc-action-steps__step-cta class for per-step CTAs
  - Added a dedicated .sccc-action-steps__step-cta-icon class for smaller arrows
  - Reduced the right-pane CTA padding, font size, icon size, glow, and shadow
  - Kept the main CTA unchanged so it remains the primary action
  - Kept the neon/glass treatment, but made step CTAs feel secondary
  - Kept the number marker readability fixes for light and dark mode

  Notes:
  - This file assumes the block class and field structure remain the same.
  - The section stays full width, while content remains inside the site container.
  - Styling is scoped to .sccc-action-steps so it does not leak into other blocks.
--}}

@once
  <style>
    /**
     * Action Steps scoped component variables.
     *
     * Default values are intentionally light-theme friendly because the main
     * issue reported was poor readability in light mode.
     */
    .sccc-action-steps {
      --sccc-action-cta-border-gradient: linear-gradient(
        90deg,
        rgba(113, 215, 255, 0.98),
        rgba(67, 190, 255, 1),
        rgba(174, 113, 255, 0.96),
        rgba(255, 23, 68, 0.90)
      );

      --sccc-action-cta-fill: rgba(255, 255, 255, 0.94);
      --sccc-action-cta-fill-hover: rgba(255, 255, 255, 1);
      --sccc-action-cta-text: #111827;
      --sccc-action-cta-shadow-primary: rgba(41, 121, 255, 0.16);
      --sccc-action-cta-shadow-accent: rgba(255, 23, 68, 0.10);
      --sccc-action-cta-inner-highlight: rgba(255, 255, 255, 0.60);

      /**
       * Number marker variables.
       *
       * These are tuned to be readable on light backgrounds.
       * The text is dark, the fill is light glass, and the border is stronger.
       */
      --sccc-action-marker-fill-start: rgba(255, 255, 255, 0.98);
      --sccc-action-marker-fill-end: rgba(232, 239, 249, 0.96);
      --sccc-action-marker-text: #17335f;
      --sccc-action-marker-border: rgba(23, 51, 95, 0.42);
      --sccc-action-marker-spot: rgba(113, 215, 255, 0.18);
      --sccc-action-marker-highlight: rgba(255, 255, 255, 0.88);
      --sccc-action-marker-shadow-primary: rgba(41, 121, 255, 0.16);
      --sccc-action-marker-shadow-accent: rgba(174, 113, 255, 0.08);
    }

    /**
     * Dark theme values.
     *
     * The project standard is plain data-theme values: light and dark.
     * We also support the .dark class, since the project uses it on <html>.
     */
    html[data-theme="dark"] .sccc-action-steps,
    .dark .sccc-action-steps {
      --sccc-action-cta-fill: rgba(15, 23, 42, 0.88);
      --sccc-action-cta-fill-hover: rgba(20, 31, 52, 0.94);
      --sccc-action-cta-text: #ffffff;
      --sccc-action-cta-shadow-primary: rgba(41, 121, 255, 0.22);
      --sccc-action-cta-shadow-accent: rgba(255, 23, 68, 0.14);
      --sccc-action-cta-inner-highlight: rgba(255, 255, 255, 0.12);

      --sccc-action-marker-fill-start: rgba(18, 27, 45, 0.94);
      --sccc-action-marker-fill-end: rgba(23, 36, 59, 0.86);
      --sccc-action-marker-text: #ffffff;
      --sccc-action-marker-border: rgba(113, 215, 255, 0.34);
      --sccc-action-marker-spot: rgba(113, 215, 255, 0.32);
      --sccc-action-marker-highlight: rgba(255, 255, 255, 0.14);
      --sccc-action-marker-shadow-primary: rgba(41, 121, 255, 0.22);
      --sccc-action-marker-shadow-accent: rgba(174, 113, 255, 0.10);
    }

    /**
     * Light theme values.
     *
     * Declared explicitly so the block is predictable in light mode and
     * does not depend only on defaults.
     */
    html[data-theme="light"] .sccc-action-steps {
      --sccc-action-cta-fill: rgba(255, 255, 255, 0.94);
      --sccc-action-cta-fill-hover: rgba(255, 255, 255, 1);
      --sccc-action-cta-text: #111827;
      --sccc-action-cta-shadow-primary: rgba(41, 121, 255, 0.16);
      --sccc-action-cta-shadow-accent: rgba(229, 57, 53, 0.10);
      --sccc-action-cta-inner-highlight: rgba(255, 255, 255, 0.60);

      --sccc-action-marker-fill-start: rgba(255, 255, 255, 0.98);
      --sccc-action-marker-fill-end: rgba(232, 239, 249, 0.96);
      --sccc-action-marker-text: #17335f;
      --sccc-action-marker-border: rgba(23, 51, 95, 0.42);
      --sccc-action-marker-spot: rgba(113, 215, 255, 0.18);
      --sccc-action-marker-highlight: rgba(255, 255, 255, 0.88);
      --sccc-action-marker-shadow-primary: rgba(41, 121, 255, 0.16);
      --sccc-action-marker-shadow-accent: rgba(174, 113, 255, 0.08);
    }

    /**
     * Main CTA.
     *
     * This is intentionally the strongest CTA treatment in the block.
     * It belongs to the left column and should remain visually dominant.
     */
    .sccc-action-steps__cta {
      color: var(--sccc-action-cta-text) !important;
      text-decoration: none !important;
      border: 1px solid transparent;
      background:
        linear-gradient(
          145deg,
          var(--sccc-action-cta-fill),
          var(--sccc-action-cta-fill)
        ) padding-box,
        var(--sccc-action-cta-border-gradient) border-box;
      box-shadow:
        0 0 0 1px rgba(113, 215, 255, 0.14),
        0 12px 30px var(--sccc-action-cta-shadow-primary),
        0 0 24px var(--sccc-action-cta-shadow-accent),
        inset 0 1px 0 var(--sccc-action-cta-inner-highlight);
    }

    .sccc-action-steps__cta:hover,
    .sccc-action-steps__cta:focus-visible {
      color: var(--sccc-action-cta-text) !important;
      text-decoration: none !important;
      background:
        linear-gradient(
          145deg,
          var(--sccc-action-cta-fill-hover),
          var(--sccc-action-cta-fill-hover)
        ) padding-box,
        var(--sccc-action-cta-border-gradient) border-box;
      box-shadow:
        0 0 0 1px rgba(113, 215, 255, 0.22),
        0 16px 36px var(--sccc-action-cta-shadow-primary),
        0 0 30px var(--sccc-action-cta-shadow-accent),
        inset 0 1px 0 var(--sccc-action-cta-inner-highlight);
    }

    /**
     * Main CTA arrow badge.
     *
     * This is only used if the main CTA later receives an icon.
     * It keeps the stronger visual weight aligned with the main CTA.
     */
    .sccc-action-steps__cta-icon {
      color: var(--sccc-action-cta-text) !important;
      border: 1px solid transparent;
      background:
        linear-gradient(
          145deg,
          var(--sccc-action-cta-fill),
          var(--sccc-action-cta-fill)
        ) padding-box,
        var(--sccc-action-cta-border-gradient) border-box;
      box-shadow:
        0 0 12px var(--sccc-action-cta-shadow-primary),
        inset 0 1px 0 var(--sccc-action-cta-inner-highlight);
    }

    .sccc-action-steps__cta:hover .sccc-action-steps__cta-icon,
    .sccc-action-steps__cta:focus-visible .sccc-action-steps__cta-icon {
      background:
        linear-gradient(
          145deg,
          var(--sccc-action-cta-fill-hover),
          var(--sccc-action-cta-fill-hover)
        ) padding-box,
        var(--sccc-action-cta-border-gradient) border-box;
    }

    /**
     * Per-step CTA.
     *
     * This is the smaller right-pane action style. It intentionally uses the
     * same design language as the main CTA, but with less padding, less glow,
     * a smaller icon, and a quieter shadow so it reads as secondary.
     */
    .sccc-action-steps__step-cta {
      color: var(--sccc-action-cta-text) !important;
      text-decoration: none !important;
      border: 1px solid transparent;
      background:
        linear-gradient(
          145deg,
          var(--sccc-action-cta-fill),
          var(--sccc-action-cta-fill)
        ) padding-box,
        var(--sccc-action-cta-border-gradient) border-box;
      box-shadow:
        0 0 0 1px rgba(113, 215, 255, 0.08),
        0 7px 18px color-mix(in oklab, var(--sccc-action-cta-shadow-primary) 70%, transparent),
        0 0 12px color-mix(in oklab, var(--sccc-action-cta-shadow-accent) 70%, transparent),
        inset 0 1px 0 var(--sccc-action-cta-inner-highlight);
      opacity: 0.92;
    }

    .sccc-action-steps__step-cta:hover,
    .sccc-action-steps__step-cta:focus-visible {
      color: var(--sccc-action-cta-text) !important;
      text-decoration: none !important;
      background:
        linear-gradient(
          145deg,
          var(--sccc-action-cta-fill-hover),
          var(--sccc-action-cta-fill-hover)
        ) padding-box,
        var(--sccc-action-cta-border-gradient) border-box;
      box-shadow:
        0 0 0 1px rgba(113, 215, 255, 0.14),
        0 9px 22px color-mix(in oklab, var(--sccc-action-cta-shadow-primary) 80%, transparent),
        0 0 16px color-mix(in oklab, var(--sccc-action-cta-shadow-accent) 78%, transparent),
        inset 0 1px 0 var(--sccc-action-cta-inner-highlight);
      opacity: 1;
    }

    /**
     * Per-step CTA arrow badge.
     *
     * This icon is smaller than the main CTA icon so the action list does not
     * overpower the primary button in the left column.
     */
    .sccc-action-steps__step-cta-icon {
      color: var(--sccc-action-cta-text) !important;
      border: 1px solid transparent;
      background:
        linear-gradient(
          145deg,
          var(--sccc-action-cta-fill),
          var(--sccc-action-cta-fill)
        ) padding-box,
        var(--sccc-action-cta-border-gradient) border-box;
      box-shadow:
        0 0 8px color-mix(in oklab, var(--sccc-action-cta-shadow-primary) 70%, transparent),
        inset 0 1px 0 var(--sccc-action-cta-inner-highlight);
    }

    .sccc-action-steps__step-cta:hover .sccc-action-steps__step-cta-icon,
    .sccc-action-steps__step-cta:focus-visible .sccc-action-steps__step-cta-icon {
      background:
        linear-gradient(
          145deg,
          var(--sccc-action-cta-fill-hover),
          var(--sccc-action-cta-fill-hover)
        ) padding-box,
        var(--sccc-action-cta-border-gradient) border-box;
    }

    /**
     * Number marker.
     *
     * This replaces the previous inline-styled circle with a proper
     * theme-aware component. The marker uses stronger text contrast in
     * light mode, which is the main issue being fixed here.
     */
    .sccc-action-steps__step-marker {
      position: relative;
      border: 1px solid var(--sccc-action-marker-border);
      background: linear-gradient(
        145deg,
        var(--sccc-action-marker-fill-start),
        var(--sccc-action-marker-fill-end)
      );
      box-shadow:
        0 0 0 1px color-mix(in oklab, var(--sccc-action-marker-border) 36%, transparent),
        0 10px 28px var(--sccc-action-marker-shadow-primary),
        0 0 20px var(--sccc-action-marker-shadow-accent),
        inset 0 1px 0 var(--sccc-action-marker-highlight);
    }

    .sccc-action-steps__step-marker::before {
      content: "";
      position: absolute;
      inset: 0;
      border-radius: 9999px;
      background: radial-gradient(
        circle at 30% 30%,
        var(--sccc-action-marker-spot) 0%,
        transparent 58%
      );
      pointer-events: none;
    }

    .sccc-action-steps__step-marker-number {
      position: relative;
      z-index: 1;
      color: var(--sccc-action-marker-text);
      text-shadow: 0 1px 0 var(--sccc-action-marker-highlight);
    }
  </style>
@endonce

@php
  /**
   * Normalize Gutenberg block metadata.
   *
   * In some contexts the block object may be an object, in others an array.
   * This keeps anchor and custom class handling safe and predictable.
   */
  $blockData = is_object($block ?? null)
      ? get_object_vars($block)
      : (array) ($block ?? []);

  /**
   * Build the section ID.
   *
   * A custom anchor wins if one is set in the editor.
   * Otherwise we fall back to a generated ID from Gutenberg.
   */
  $sectionId = $blockData['anchor'] ?? '';

  if (empty($sectionId) && ! empty($blockData['id'])) {
      $sectionId = 'action-steps-' . $blockData['id'];
  }

  /**
   * Preserve Gutenberg custom classes.
   *
   * We keep them on the outer section only so users can still target
   * the block if needed, without breaking the internal structure.
   */
  $customClass = $blockData['className'] ?? '';

  /**
   * Section padding keyword maps.
   *
   * These follow the project’s preferred spacing keyword approach:
   * none, s, m, l, xl, xxl.
   */
  $paddingMapTop = [
      'none' => 'pt-0',
      's' => 'pt-8',
      'm' => 'pt-12',
      'l' => 'pt-16',
      'xl' => 'pt-20 md:pt-24',
      'xxl' => 'pt-24 md:pt-32',
  ];

  $paddingMapBottom = [
      'none' => 'pb-0',
      's' => 'pb-8',
      'm' => 'pb-12',
      'l' => 'pb-16',
      'xl' => 'pb-20 md:pb-24',
      'xxl' => 'pb-24 md:pb-32',
  ];

  $topPaddingClass = $paddingMapTop[$padding_top ?? 'xl'] ?? $paddingMapTop['xl'];
  $bottomPaddingClass = $paddingMapBottom[$padding_bottom ?? 'xl'] ?? $paddingMapBottom['xl'];

  /**
   * Background treatment handling.
   *
   * These options are intentionally subtle so the block can work on
   * high-importance pages without overpowering adjacent sections.
   */
  $backgroundTreatment = $background_treatment ?? 'subtle';

  $backgroundClasses = match ($backgroundTreatment) {
      'none' => 'bg-transparent',
      'surface' => 'bg-surface',
      'dark' => 'bg-neutral-950 text-white',
      default => 'bg-surface',
  };

  $backgroundStyle = match ($backgroundTreatment) {
      'subtle' => 'background:
          radial-gradient(680px 320px at 8% 0%, color-mix(in oklab, var(--color-primary-500, #2979ff) 18%, transparent) 0%, transparent 68%),
          radial-gradient(540px 280px at 95% 100%, color-mix(in oklab, var(--color-accent-500, #ff1744) 14%, transparent) 0%, transparent 70%),
          var(--color-surface);',
      'dark' => 'background:
          radial-gradient(720px 360px at 8% 0%, rgba(41, 121, 255, 0.22) 0%, transparent 68%),
          radial-gradient(560px 300px at 95% 100%, rgba(255, 23, 68, 0.18) 0%, transparent 72%),
          #050505;',
      default => '',
  };

  /**
   * CTA field handling.
   *
   * ACF link fields return an array when populated.
   */
  $ctaTitle = $primary_cta['title'] ?? '';
  $ctaUrl = $primary_cta['url'] ?? '';
  $ctaTarget = $primary_cta['target'] ?? '_self';

  /**
   * Fallback editor content.
   *
   * This makes the block more useful immediately after insertion,
   * even before content is entered in the editor.
   */
  $fallbackSteps = [
      [
          'step_title' => 'Attend an Event',
          'step_description' => 'Show up, bring people with you, and help create more awareness through the community we are building.',
          'step_link' => null,
      ],
      [
          'step_title' => 'Share the Mission',
          'step_description' => 'Help more people learn about Isaiah 117 House and why the work matters.',
          'step_link' => null,
      ],
      [
          'step_title' => 'Support the Cause',
          'step_description' => 'Give, donate approved items, join raffles, or help raise awareness through club events.',
          'step_link' => null,
      ],
      [
          'step_title' => 'Partner With Us',
          'step_description' => 'Sponsors and local businesses help us reach more people and create stronger community-focused events.',
          'step_link' => null,
      ],
  ];

  $displaySteps = ! empty($steps) ? $steps : $fallbackSteps;

  /**
   * Reusable accent gradient.
   *
   * This is used for small decorative rules and section accents.
   */
  $accentGradient = 'var(--sccc-header-underline-gradient, linear-gradient(90deg, rgba(113, 215, 255, 0) 0%, rgba(113, 215, 255, 0.96) 18%, rgba(67, 190, 255, 0.98) 48%, rgba(174, 113, 255, 0.92) 82%, rgba(174, 113, 255, 0) 100%))';
@endphp

<section
  @if (! empty($sectionId)) id="{{ esc_attr($sectionId) }}" @endif
  class="sccc-action-steps {{ esc_attr($customClass) }} {{ $backgroundClasses }} {{ $topPaddingClass }} {{ $bottomPaddingClass }} relative overflow-hidden"
  @if (! empty($backgroundStyle)) style="{!! $backgroundStyle !!}" @endif
>
  {{--
    Section inner container.

    The block spans full width, but all content stays aligned to the site
    container for consistency with the rest of the page.
  --}}
  <div class="relative mx-auto max-w-7xl px-4 md:px-6">
    <div class="grid gap-12 lg:grid-cols-[minmax(0,0.82fr)_minmax(0,1.18fr)] lg:items-start lg:gap-16 xl:gap-24">
      {{--
        Left column.

        This area explains the section and can optionally remain visually
        anchored on larger screens. It is intentionally kept clean and
        spacious so the right-side action list has room to breathe.
      --}}
      <div class="max-w-2xl lg:sticky lg:top-28 lg:self-start">
        @if (! empty($headline))
          <h2 class="font-display text-4xl font-bold uppercase leading-[0.95] tracking-[0.04em] text-text md:text-5xl xl:text-6xl">
            {{ $headline }}
          </h2>
        @endif

        <span
          class="mt-5 block h-1 w-24 rounded-full"
          style="background: {!! $accentGradient !!};"
          aria-hidden="true"
        ></span>

        @if (! empty($description))
          <div class="mt-8 max-w-prose space-y-6 text-lg leading-8 text-muted">
            {!! wp_kses_post($description) !!}
          </div>
        @endif

        @if (! empty($closing_copy))
          <div class="mt-8 flex items-start gap-4">
            <span
              class="mt-2 block h-12 w-1 shrink-0 rounded-full"
              style="background: {!! $accentGradient !!};"
              aria-hidden="true"
            ></span>

            <div class="text-lg leading-8 text-text">
              {!! wp_kses_post($closing_copy) !!}
            </div>
          </div>
        @endif

        @if (! empty($ctaTitle) && ! empty($ctaUrl))
          <div class="mt-10">
            <a
              href="{{ esc_url($ctaUrl) }}"
              target="{{ esc_attr($ctaTarget) }}"
              class="sccc-action-steps__cta inline-flex items-center justify-center rounded-full px-5 py-3 text-sm font-bold uppercase tracking-wide backdrop-blur-md transition hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            >
              {{ $ctaTitle }}
            </a>
          </div>
        @endif
      </div>

      {{--
        Right column.

        This is intentionally not a grid of cards. Instead, it is a more
        designed action flow with:
        - a vertical guide line
        - styled number markers
        - step content blocks with subtle accent treatment
      --}}
      @if (! empty($displaySteps))
        <div class="relative">
          {{--
            Vertical guide rail.

            This creates a stronger visual flow and gives the numbered list a
            more purposeful, editorial feel. Hidden on very small screens where
            layout needs more flexibility.
          --}}
          <div
            class="pointer-events-none absolute left-[1.75rem] top-4 hidden w-px sm:block"
            style="bottom: 1rem; background: linear-gradient(180deg, rgba(113, 215, 255, 0) 0%, rgba(113, 215, 255, 0.45) 18%, rgba(67, 190, 255, 0.75) 48%, rgba(174, 113, 255, 0.55) 82%, rgba(174, 113, 255, 0) 100%);"
            aria-hidden="true"
          ></div>

          <ol class="space-y-8 md:space-y-10">
            @foreach ($displaySteps as $index => $step)
              @php
                $stepTitle = $step['step_title'] ?? '';
                $stepDescription = $step['step_description'] ?? '';
                $stepLink = $step['step_link'] ?? null;

                $stepLinkTitle = is_array($stepLink) ? ($stepLink['title'] ?? '') : '';
                $stepLinkUrl = is_array($stepLink) ? ($stepLink['url'] ?? '') : '';
                $stepLinkTarget = is_array($stepLink) ? ($stepLink['target'] ?? '_self') : '_self';

                $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
              @endphp

              <li class="relative grid gap-5 sm:grid-cols-[4.5rem_1fr] sm:gap-6">
                {{--
                  Number marker.

                  This version uses the scoped marker class above so the number
                  remains readable in both themes, especially light mode.
                --}}
                @if (! empty($show_numbers))
                  <div class="sccc-action-steps__step-marker relative z-10 flex h-14 w-14 items-center justify-center rounded-full backdrop-blur-md">
                    <span class="sccc-action-steps__step-marker-number font-display text-xl font-bold tracking-[0.08em] md:text-2xl">
                      {{ $number }}
                    </span>
                  </div>
                @endif

                {{--
                  Step content.

                  Not a card, but still given enough definition and polish to
                  feel designed. The accent rule under the title helps each
                  step feel intentional without relying on large separators.
                --}}
                <div class="{{ empty($show_numbers) ? 'sm:col-span-2' : '' }} pt-1">
                  @if (! empty($stepTitle))
                    <h3 class="text-2xl font-bold leading-tight text-text md:text-[1.75rem]">
                      {{ $stepTitle }}
                    </h3>
                  @endif

                  <span
                    class="mt-4 block h-px w-24 rounded-full"
                    style="background: {!! $accentGradient !!}; opacity: 0.95;"
                    aria-hidden="true"
                  ></span>

                  @if (! empty($stepDescription))
                    <p class="mt-4 max-w-2xl text-lg leading-8 text-muted">
                      {{ $stepDescription }}
                    </p>
                  @endif

                  {{--
                    Step CTA.

                    Uses the smaller right-pane CTA class so these links stay
                    useful without competing with the main CTA in the left
                    column.
                  --}}
                  @if (! empty($stepLinkTitle) && ! empty($stepLinkUrl))
                    <a
                      href="{{ esc_url($stepLinkUrl) }}"
                      target="{{ esc_attr($stepLinkTarget) }}"
                      class="sccc-action-steps__step-cta group/stepcta mt-5 inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-[0.6875rem] font-bold uppercase tracking-[0.14em] backdrop-blur-md transition hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                    >
                      <span>{{ $stepLinkTitle }}</span>

                      <span
                        class="sccc-action-steps__step-cta-icon flex h-5 w-5 items-center justify-center rounded-full text-[0.65rem] leading-none transition-transform duration-200 group-hover/stepcta:translate-x-0.5"
                        aria-hidden="true"
                      >
                        →
                      </span>
                    </a>
                  @endif
                </div>
              </li>
            @endforeach
          </ol>
        </div>
      @endif
    </div>
  </div>
</section>