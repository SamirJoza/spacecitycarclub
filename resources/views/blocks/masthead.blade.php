{{--
  File path + filename: resources/views/blocks/masthead.blade.php
  -----------------------------------------------------------------------------
  Purpose:
  - Render the Masthead Gutenberg block.

  Why this file exists:
  - This is a dedicated internal-page masthead block for the business directory
    and similar internal landing pages.
  - It is intentionally separate from any existing generic Hero block.
  - Styling is instance-scoped so it does not bleed into other blocks or pages.

  Notes:
  - Highlight spans use the same class-based pattern already working elsewhere:
    `.text-gradient.text-gradient--{style}`.
  - This file now defines its own text-gradient variant classes locally so the
    selected gradient style works in both the editor preview and the front end.
  - The image overlay is the primary readability layer.
  - Buttons are intentionally stronger so they stand off the image.
  - Headline display is explicitly forced back to normal block/inline text flow
    so ACF-generated <br> tags can render as actual line breaks even if other
    global theme styles try to turn headings into flex containers.
  - Full-width instances intentionally drop the outer corner radius so the
    masthead reads as a true edge-to-edge segment.
  - Background motion uses editor-friendly keywords from ACF and maps them to
    CSS/JS behavior inside this view. Editors never need to enter raw
    percentages, pixel values, or transform values.
--}}

@php
  $uid = 'sccc-masthead-' . uniqid();

  $heightCss = match($height_preset ?? 'md') {
    'sm' => 'clamp(22rem, 40vw, 28rem)',
    'lg' => 'clamp(30rem, 56vw, 40rem)',
    default => 'clamp(25rem, 48vw, 34rem)',
  };

  /**
   * Convert the editor's static image-position keyword into valid CSS keywords.
   * Existing saved values of left/center/right are preserved, while new values
   * of top/bottom are also supported.
   */
  $bgPosition = match($background_focus ?? 'center') {
    'top'    => 'center top',
    'bottom' => 'center bottom',
    'left'   => 'left center',
    'right'  => 'right center',
    default  => 'center center',
  };

  $overlayAlpha = match($overlay_strength ?? 'medium') {
    'soft'   => '0.34',
    'strong' => '0.70',
    default  => '0.52',
  };

  $rawMotion = $background_motion ?? 'static';
  $motion = in_array($rawMotion, ['static', 'parallax', 'pan'], true)
    ? $rawMotion
    : 'static';

  $hasImage = !empty($background_image_url);
  $hasMotion = $hasImage && in_array($motion, ['parallax', 'pan'], true);

  $rawParallaxStrength = $parallax_strength ?? 'medium';
  $parallaxStrength = in_array($rawParallaxStrength, ['small', 'medium', 'large'], true)
    ? $rawParallaxStrength
    : 'medium';

  $rawPanDirection = $pan_direction ?? 'vertical';
  $panDirection = in_array($rawPanDirection, ['vertical', 'horizontal'], true)
    ? $rawPanDirection
    : 'vertical';

  $rawVerticalStart = $pan_vertical_start ?? 'top';
  $verticalStart = in_array($rawVerticalStart, ['top', 'center', 'bottom'], true)
    ? $rawVerticalStart
    : 'top';

  $rawVerticalEnd = $pan_vertical_end ?? 'bottom';
  $verticalEnd = in_array($rawVerticalEnd, ['top', 'center', 'bottom'], true)
    ? $rawVerticalEnd
    : 'bottom';

  $rawHorizontalStart = $pan_horizontal_start ?? 'left';
  $horizontalStart = in_array($rawHorizontalStart, ['left', 'center', 'right'], true)
    ? $rawHorizontalStart
    : 'left';

  $rawHorizontalEnd = $pan_horizontal_end ?? 'right';
  $horizontalEnd = in_array($rawHorizontalEnd, ['left', 'center', 'right'], true)
    ? $rawHorizontalEnd
    : 'right';

  $panStart = $panDirection === 'horizontal' ? $horizontalStart : $verticalStart;
  $panEnd = $panDirection === 'horizontal' ? $horizontalEnd : $verticalEnd;

  $rawPanRange = $pan_range ?? 'large';
  $panRange = in_array($rawPanRange, ['small', 'medium', 'large'], true)
    ? $rawPanRange
    : 'large';

  $motionClass = match($motion) {
    'parallax' => 'sccc-dm--parallax',
    'pan'      => 'sccc-dm--pan',
    default    => '',
  };

  $align = $content_align ?? 'left';
  $contentClass = match($align) {
    'center' => 'sccc-dm__content--center',
    'right'  => 'sccc-dm__content--right',
    default  => 'sccc-dm__content--left',
  };

  $primaryUrl    = $primary_link['url'] ?? '';
  $primaryLabel  = $primary_link['title'] ?? 'Learn More';
  $primaryTarget = $primary_link['target'] ?? '';

  $secondaryUrl    = $secondary_link['url'] ?? '';
  $secondaryLabel  = $secondary_link['title'] ?? 'More Info';
  $secondaryTarget = $secondary_link['target'] ?? '';
@endphp

<div id="{{ $uid }}" class="{{ $block->classes }}">
  <section
    class="sccc-dm {{ $hasMotion ? $motionClass : '' }}"
    data-sccc-bg-motion="{{ esc_attr($hasMotion ? $motion : 'static') }}"
    data-sccc-parallax-strength="{{ esc_attr($parallaxStrength) }}"
    data-sccc-pan-direction="{{ esc_attr($panDirection) }}"
    data-sccc-pan-start="{{ esc_attr($panStart) }}"
    data-sccc-pan-end="{{ esc_attr($panEnd) }}"
    data-sccc-pan-range="{{ esc_attr($panRange) }}"
    style="
      --dm-min-h: {{ $heightCss }};
      --dm-bg-position: {{ $bgPosition }};
      --dm-overlay-alpha: {{ $overlayAlpha }};
      --dm-parallax-y: 0px;
    "
  >
    <div class="sccc-dm__media" aria-hidden="true">
      @if (!empty($background_image_url))
        <img
          class="sccc-dm__media-image"
          src="{{ esc_url($background_image_url) }}"
          alt="{{ esc_attr($background_image_alt) }}"
          loading="lazy"
        >
      @endif

      <span class="sccc-dm__media-scrim"></span>
      <span class="sccc-dm__media-aura"></span>
    </div>

    <div class="sccc-dm__inner">
      <div class="sccc-dm__content {{ $contentClass }}">
        <div class="sccc-dm__copy">
          @if (!empty($badge))
            <p class="sccc-dm__badge">{{ $badge }}</p>
          @endif

          @if (!empty($eyebrow))
            <p class="sccc-dm__eyebrow">{{ $eyebrow }}</p>
          @endif

          @if (!empty($headline))
            <h2 class="sccc-dm__headline">{!! $headline_rendered !!}</h2>
          @endif

          @if (!empty($description))
            <p class="sccc-dm__description">{!! $description !!}</p>
          @endif

          @if (!empty($primaryUrl) || !empty($secondaryUrl))
            <div class="sccc-dm__actions">
              @if (!empty($primaryUrl))
                <a
                  class="sccc-dm__button sccc-dm__button--primary"
                  href="{{ esc_url($primaryUrl) }}"
                  @if($primaryTarget) target="{{ esc_attr($primaryTarget) }}" rel="noopener" @endif
                >
                  <span class="sccc-dm__button-face">
                    <span class="sccc-dm__button-label">{{ $primaryLabel }}</span>
                  </span>
                </a>
              @endif

              @if (!empty($secondaryUrl))
                <a
                  class="sccc-dm__button sccc-dm__button--ghost"
                  href="{{ esc_url($secondaryUrl) }}"
                  @if($secondaryTarget) target="{{ esc_attr($secondaryTarget) }}" rel="noopener" @endif
                >
                  <span class="sccc-dm__button-face">
                    <span class="sccc-dm__button-label">{{ $secondaryLabel }}</span>
                  </span>
                </a>
              @endif
            </div>
          @endif
        </div>
      </div>
    </div>
  </section>

  <style>
    /* ==========================================================================
       File path + filename: resources/views/blocks/masthead.blade.php
       Instance-scoped styles for the Masthead block
       ========================================================================== */

    #{{ $uid }} .sccc-dm {
      --dm-text: #ffffff;
      --dm-muted: rgba(255, 255, 255, 0.88);
      --dm-primary: #66d2ff;
      --dm-accent: #b071ff;

      /* Stronger button contrast so they do not blend into the photo */
      --dm-button-text: #fbfdff;
      --dm-button-text-muted: #eef4ff;
      --dm-button-face: rgba(4, 8, 22, 0.72);
      --dm-button-face-hover: rgba(6, 10, 26, 0.86);
      --dm-button-face-line: rgba(255, 255, 255, 0.14);
      --dm-button-edge-line: rgba(113, 215, 255, 0.28);
      --dm-button-ring-size: 2px;
      --dm-button-glow:
        0 14px 34px rgba(67, 190, 255, 0.16),
        0 10px 22px rgba(176, 113, 255, 0.12),
        0 8px 20px rgba(0, 0, 0, 0.26);
      --dm-button-glow-hover:
        0 20px 46px rgba(67, 190, 255, 0.22),
        0 14px 28px rgba(176, 113, 255, 0.16),
        0 10px 24px rgba(0, 0, 0, 0.34);
      --dm-button-gradient: linear-gradient(
        90deg,
        rgba(113, 215, 255, 0) 0%,
        rgba(113, 215, 255, 0.96) 18%,
        rgba(67, 190, 255, 0.98) 48%,
        rgba(174, 113, 255, 0.92) 82%,
        rgba(174, 113, 255, 0) 100%
      );

      /*
       * Shared highlight options for this block.
       * These names match the ACF editor labels in app/Blocks/Masthead.php.
       */
      --sccc-gradient-club-blue: linear-gradient(90deg, #135bec 0%, #1f8fff 52%, #71d7ff 100%);
      --sccc-gradient-signal-red: linear-gradient(90deg, #b31324 0%, #ff1744 50%, #ff7a59 100%);
      --sccc-gradient-space-city: linear-gradient(90deg, #135bec 0%, #43beff 42%, #ae71ff 72%, #ff1744 100%);
      --sccc-color-club-blue: #135bec;
      --sccc-color-signal-red: #e53935;
      --sccc-color-deep-purple: #ae71ff;

      position: relative;
      overflow: hidden;
      min-height: var(--dm-min-h);
      border-radius: 1.75rem;
      background:
        radial-gradient(circle at top left, rgba(97, 152, 255, 0.12), transparent 32%),
        radial-gradient(circle at top right, rgba(176, 113, 255, 0.14), transparent 28%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0.02) 100%);
      border: 1px solid rgba(255, 255, 255, 0.10);
      box-shadow:
        0 24px 80px rgba(20, 31, 59, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.10);
    }

    /*
     * Full-width blocks should not keep rounded outer corners.
     * We scope this to the wrapper because Gutenberg/ACF block classes are
     * printed on the outer element, not the inner section.
     */
    #{{ $uid }}.alignfull .sccc-dm,
    #{{ $uid }}.alignfull .sccc-dm__media {
      border-radius: 0;
    }

    html.dark[data-theme="dark"] #{{ $uid }} .sccc-dm,
    html[data-theme="dark"] #{{ $uid }} .sccc-dm {
      --dm-text: #f8faff;
      --dm-muted: #c2cae0;
      --dm-primary: #5f8fff;
      --dm-accent: #b071ff;
      --dm-button-text: #f7fbff;
      --dm-button-text-muted: #e5edff;
      --dm-button-face: rgba(4, 7, 18, 0.76);
      --dm-button-face-hover: rgba(6, 10, 24, 0.88);
      --dm-button-face-line: rgba(255, 255, 255, 0.12);
      --dm-button-edge-line: rgba(113, 215, 255, 0.22);
    }

    #{{ $uid }} .sccc-dm__media {
      position: absolute;
      inset: 0;
      overflow: hidden;
      border-radius: inherit;
      z-index: 0;
    }

    #{{ $uid }} .sccc-dm__media-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: var(--dm-bg-position);
      display: block;
      transform: translate3d(0, 0, 0) scale(1.02);
      transform-origin: center;
    }

    /*
     * Subtle Parallax mode:
     * - The image is enlarged just enough so its movement does not reveal empty
     *   edges inside the masthead.
     * - JS only changes --dm-parallax-y; the transform stays scoped here.
     */
    #{{ $uid }} .sccc-dm--parallax .sccc-dm__media-image {
      min-height: calc(100% + 7rem);
      height: calc(100% + 7rem);
      transform: translate3d(0, var(--dm-parallax-y), 0) scale(1.07);
      will-change: transform;
    }

    /*
     * Image Pan mode:
     * - Movement happens through object-position, not layout.
     * - This is better for mastheads because the scroll runway is short.
     * - The static object-position remains the reduced-motion fallback.
     */
    #{{ $uid }} .sccc-dm--pan .sccc-dm__media-image {
      transform: translate3d(0, 0, 0) scale(1.02);
      will-change: object-position;
    }

    #{{ $uid }} .sccc-dm__media-scrim,
    #{{ $uid }} .sccc-dm__media-aura {
      position: absolute;
      inset: 0;
      display: block;
      pointer-events: none;
    }

    #{{ $uid }} .sccc-dm__media-scrim {
      background:
        linear-gradient(
          135deg,
          rgba(6, 12, 26, calc(var(--dm-overlay-alpha) + 0.12)) 0%,
          rgba(8, 13, 30, var(--dm-overlay-alpha)) 42%,
          rgba(10, 14, 28, calc(var(--dm-overlay-alpha) + 0.08)) 100%
        );
    }

    #{{ $uid }} .sccc-dm__media-aura {
      background:
        radial-gradient(circle at 12% 18%, rgba(97, 170, 255, 0.22), transparent 28%),
        radial-gradient(circle at 86% 18%, rgba(176, 113, 255, 0.18), transparent 24%);
      mix-blend-mode: screen;
      opacity: 0.88;
    }

    #{{ $uid }} .sccc-dm__inner {
      position: relative;
      z-index: 1;
      width: min(100% - 2rem, 1280px);
      margin-inline: auto;
      min-height: var(--dm-min-h);
      display: flex;
      align-items: stretch;
    }

    #{{ $uid }} .sccc-dm__content {
      width: 100%;
      display: flex;
      align-items: center;
      padding: clamp(1.35rem, 3vw, 2.4rem);
    }

    #{{ $uid }} .sccc-dm__content--left {
      justify-content: flex-start;
      text-align: left;
    }

    #{{ $uid }} .sccc-dm__content--center {
      justify-content: center;
      text-align: center;
    }

    #{{ $uid }} .sccc-dm__content--right {
      justify-content: flex-end;
      text-align: right;
    }

    #{{ $uid }} .sccc-dm__copy {
      width: 100%;
      display: flex;
      flex-direction: column;
    }

    #{{ $uid }} .sccc-dm__badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      align-self: flex-start;
      margin: 0 0 0.9rem;
      padding: 0.42rem 0.82rem;
      border-radius: 999px;
      font-family: var(--font-headline, inherit);
      font-size: clamp(0.72rem, 1vw, 0.8rem);
      font-weight: 700;
      letter-spacing: 0.04em;
      color: var(--dm-primary);
      background: color-mix(in srgb, var(--dm-primary) 16%, transparent);
      border: 1px solid color-mix(in srgb, var(--dm-primary) 18%, transparent);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }

    #{{ $uid }} .sccc-dm__content--center .sccc-dm__badge {
      align-self: center;
    }

    #{{ $uid }} .sccc-dm__content--right .sccc-dm__badge {
      align-self: flex-end;
    }

    #{{ $uid }} .sccc-dm__eyebrow {
      margin: 0 0 0.45rem;
      font-family: var(--font-headline, inherit);
      font-size: clamp(0.75rem, 1vw, 0.84rem);
      font-weight: 800;
      letter-spacing: 0.13em;
      text-transform: uppercase;
      color: var(--dm-text);
      opacity: 0.96;
      text-shadow: 0 2px 12px rgba(0, 0, 0, 0.34);
    }

    #{{ $uid }} .sccc-dm__headline {
      font-family: var(--font-headline, inherit);
      font-weight: 800;
      line-height: 0.96;
      margin: 0;
      font-size: clamp(2.15rem, 5.3vw, 5rem);
      letter-spacing: -0.02em;
      color: var(--dm-text);
      text-wrap: balance;
      text-shadow: 0 4px 18px rgba(0, 0, 0, 0.28);

      /*
       * Critical fix:
       * - Force the headline back into normal block text flow.
       * - This prevents theme/global heading rules like flex/inline-flex from
       *   swallowing a real <br> that is already present in the DOM.
       */
      display: block !important;
      white-space: normal !important;
    }

    /*
     * Ensure the preserved line break element behaves like a real line break
     * even if a global reset targets <br> unexpectedly.
     */
    #{{ $uid }} .sccc-dm__headline br {
      display: block;
      content: "";
    }

    #{{ $uid }} .sccc-dm__description {
      margin: 1rem 0 0;
      font-size: clamp(1rem, 1.35vw, 1.22rem);
      line-height: 1.72;
      color: var(--dm-muted);
      text-wrap: pretty;
      text-shadow: 0 2px 12px rgba(0, 0, 0, 0.22);
    }

    /* --------------------------------------------------------------------------
       Local highlight classes so the selected variant works in both
       editor preview and front end, without depending on outside utilities.
       -------------------------------------------------------------------------- */
    #{{ $uid }} .text-gradient {
      background-image: var(--sccc-gradient-club-blue);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
      color: transparent;
      text-shadow: none;

      /*
       * Critical fix:
       * - Keep the highlighted fragment participating in normal inline text flow.
       * - Do not allow nowrap or inline-flex behavior to prevent <br> from
       *   visually breaking the line after the first phrase.
       */
      display: inline !important;
      white-space: normal !important;
    }

    #{{ $uid }} .text-gradient strong {
      font-weight: 900;
    }

    #{{ $uid }} .text-gradient--club-blue {
      background-image: var(--sccc-gradient-club-blue);
    }

    #{{ $uid }} .text-gradient--signal-red {
      background-image: var(--sccc-gradient-signal-red);
    }

    #{{ $uid }} .text-gradient--space-city {
      background-image: var(--sccc-gradient-space-city);
    }

    #{{ $uid }} .text-gradient--club-blue-solid,
    #{{ $uid }} .text-gradient--signal-red-solid,
    #{{ $uid }} .text-gradient--deep-purple-solid {
      background: none;
      -webkit-background-clip: initial;
      background-clip: initial;
      -webkit-text-fill-color: currentColor;
    }

    #{{ $uid }} .text-gradient--club-blue-solid {
      color: var(--sccc-color-club-blue);
    }

    #{{ $uid }} .text-gradient--signal-red-solid {
      color: var(--sccc-color-signal-red);
    }

    #{{ $uid }} .text-gradient--deep-purple-solid {
      color: var(--sccc-color-deep-purple);
    }

    #{{ $uid }} .sccc-dm__actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.8rem;
      align-items: center;
      margin-top: 1.3rem;
    }

    #{{ $uid }} .sccc-dm__content--center .sccc-dm__actions {
      justify-content: center;
    }

    #{{ $uid }} .sccc-dm__content--right .sccc-dm__actions {
      justify-content: flex-end;
    }

    #{{ $uid }} .sccc-dm__button {
      position: relative;
      display: inline-flex;
      align-items: stretch;
      justify-content: center;
      padding: 0;
      border: 0;
      border-radius: 999px;
      background: transparent;
      text-decoration: none;
      white-space: nowrap;
      line-height: 1;
      cursor: pointer;
      isolation: isolate;
      box-shadow:
        0 0 0 1px var(--dm-button-edge-line),
        var(--dm-button-glow);
      transition: transform 180ms ease, box-shadow 180ms ease, opacity 180ms ease;
    }

    #{{ $uid }} .sccc-dm__button::before {
      content: "";
      position: absolute;
      inset: 0;
      padding: var(--dm-button-ring-size);
      border-radius: inherit;
      background: var(--dm-button-gradient);
      pointer-events: none;
      z-index: -1;
      -webkit-mask:
        linear-gradient(#000 0 0) content-box,
        linear-gradient(#000 0 0);
      -webkit-mask-composite: xor;
              mask-composite: exclude;
    }

    #{{ $uid }} .sccc-dm__button::after {
      content: "";
      position: absolute;
      inset: 0;
      border-radius: inherit;
      background: var(--dm-button-gradient);
      filter: blur(10px);
      opacity: 0.26;
      pointer-events: none;
      z-index: -2;
      transition: opacity 180ms ease;
    }

    #{{ $uid }} .sccc-dm__button:hover {
      transform: translateY(-1px);
      box-shadow:
        0 0 0 1px color-mix(in srgb, var(--dm-button-edge-line) 135%, white 0%),
        var(--dm-button-glow-hover);
    }

    #{{ $uid }} .sccc-dm__button:hover::after {
      opacity: 0.38;
    }

    #{{ $uid }} .sccc-dm__button-face {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 2.42rem;
      padding: 0.5rem 1.12rem;
      border-radius: inherit;
      background: var(--dm-button-face);
      color: var(--dm-button-text);
      backdrop-filter: blur(14px) saturate(120%);
      -webkit-backdrop-filter: blur(14px) saturate(120%);
      box-shadow:
        inset 0 1px 0 var(--dm-button-face-line),
        inset 0 -1px 0 rgba(255, 255, 255, 0.03);
      transition: background 180ms ease, box-shadow 180ms ease, color 180ms ease;
    }

    #{{ $uid }} .sccc-dm__button:hover .sccc-dm__button-face {
      background: var(--dm-button-face-hover);
      box-shadow:
        inset 0 1px 0 color-mix(in srgb, var(--dm-button-face-line) 120%, white 0%),
        inset 0 -1px 0 rgba(255, 255, 255, 0.05);
    }

    #{{ $uid }} .sccc-dm__button-label {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: clamp(0.92rem, 1vw, 1rem);
      font-weight: 700;
      letter-spacing: 0.01em;
      color: inherit;
      text-shadow: none;
    }

    #{{ $uid }} .sccc-dm__button--ghost .sccc-dm__button-face {
      color: var(--dm-button-text-muted);
    }

    #{{ $uid }} .sccc-dm__button--ghost .sccc-dm__button-label {
      font-weight: 600;
    }

    @media (prefers-reduced-motion: reduce) {
      #{{ $uid }} .sccc-dm--parallax .sccc-dm__media-image,
      #{{ $uid }} .sccc-dm--pan .sccc-dm__media-image {
        height: 100%;
        min-height: 100%;
        object-position: var(--dm-bg-position);
        transform: translate3d(0, 0, 0) scale(1.02);
        will-change: auto;
      }
    }

    @media (max-width: 991px) {
      #{{ $uid }} .sccc-dm__headline {
        font-size: clamp(1.95rem, 8vw, 3.6rem);
      }
    }

    @media (max-width: 767px) {
      #{{ $uid }} .sccc-dm {
        border-radius: 1.35rem;
      }

      #{{ $uid }} .sccc-dm__inner {
        width: min(100% - 1rem, 1280px);
      }

      #{{ $uid }} .sccc-dm__content,
      #{{ $uid }} .sccc-dm__content--left,
      #{{ $uid }} .sccc-dm__content--center,
      #{{ $uid }} .sccc-dm__content--right {
        justify-content: center;
        text-align: left;
        padding: 1rem;
      }

      #{{ $uid }} .sccc-dm__actions {
        flex-direction: column;
        align-items: stretch;
      }

      #{{ $uid }} .sccc-dm__button {
        width: 100%;
      }

      #{{ $uid }} .sccc-dm__button-face {
        width: 100%;
      }
    }
  </style>

  @if ($hasMotion)
    <script>
      (() => {
        /*
         * File path + filename: resources/views/blocks/masthead.blade.php
         * -------------------------------------------------------------------
         * Purpose:
         * - Add instance-scoped background motion to this masthead only.
         *
         * Why this script lives here:
         * - The block already uses instance-scoped CSS variables and inline
         *   styles per block instance.
         * - Keeping the motion local prevents this internal-page masthead from
         *   affecting any other hero, image, or media block on the site.
         *
         * Why the admin uses keywords:
         * - Editors choose friendly values like top, bottom, left, right, small,
         *   medium, and large.
         * - This script maps those keywords to the numeric values needed for
         *   smooth browser rendering.
         *
         * Accessibility/performance notes:
         * - The effect stops when the visitor prefers reduced motion.
         * - Scroll work is batched through requestAnimationFrame so transforms
         *   and object-position updates happen before paint instead of doing
         *   direct work on every scroll event.
         */
        const root = document.getElementById(@json($uid));

        if (!root || !('requestAnimationFrame' in window)) {
          return;
        }

        const section = root.querySelector('.sccc-dm');
        const image = root.querySelector('.sccc-dm__media-image');

        if (!section || !image) {
          return;
        }

        const motion = section.dataset.scccBgMotion || 'static';

        if (!['parallax', 'pan'].includes(motion)) {
          return;
        }

        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

        const keywordPercent = {
          top: 0,
          left: 0,
          center: 50,
          bottom: 100,
          right: 100,
        };

        const strengthMap = {
          small: 0.18,
          medium: 0.28,
          large: 0.42,
        };

        const rangeMap = {
          small: 0.35,
          medium: 0.65,
          large: 1,
        };

        const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
        const keywordToPercent = (keyword, fallback = 'center') => keywordPercent[keyword] ?? keywordPercent[fallback];

        let ticking = false;

        const resetMotion = () => {
          image.style.setProperty('--dm-parallax-y', '0px');
          image.style.removeProperty('object-position');
        };

        const getMastheadProgress = () => {
          const rect = section.getBoundingClientRect();

          if (rect.height <= 0) {
            return 0;
          }

          /*
           * Header/masthead-specific progress:
           * - 0 when the masthead top is still at or below the viewport top.
           * - 1 when the masthead has scrolled through its own height.
           *
           * This works better for a page header than viewport-entry progress,
           * because a masthead usually starts near the top and has limited
           * scroll distance before it leaves view.
           */
          return clamp((rect.top * -1) / rect.height, 0, 1);
        };

        const updateParallax = (progress) => {
          const strength = strengthMap[section.dataset.scccParallaxStrength || 'medium'] ?? strengthMap.medium;
          const maxTravel = Math.min(96, Math.max(36, section.offsetHeight * strength));
          const offset = maxTravel * progress * -1;

          image.style.setProperty('--dm-parallax-y', `${offset.toFixed(2)}px`);
        };

        const updatePan = (progress) => {
          const direction = section.dataset.scccPanDirection || 'vertical';
          const startKeyword = section.dataset.scccPanStart || (direction === 'horizontal' ? 'left' : 'top');
          const endKeyword = section.dataset.scccPanEnd || (direction === 'horizontal' ? 'right' : 'bottom');
          const range = rangeMap[section.dataset.scccPanRange || 'large'] ?? rangeMap.large;
          const start = keywordToPercent(startKeyword);
          const end = keywordToPercent(endKeyword);
          const current = start + ((end - start) * progress * range);

          if (direction === 'horizontal') {
            image.style.objectPosition = `${current.toFixed(2)}% 50%`;
            return;
          }

          image.style.objectPosition = `50% ${current.toFixed(2)}%`;
        };

        const updateMotion = () => {
          ticking = false;

          if (reducedMotion.matches) {
            resetMotion();
            return;
          }

          const rect = section.getBoundingClientRect();
          const viewportHeight = window.innerHeight || document.documentElement.clientHeight;

          if (rect.bottom < 0 || rect.top > viewportHeight) {
            return;
          }

          const progress = getMastheadProgress();

          if (motion === 'parallax') {
            updateParallax(progress);
            return;
          }

          if (motion === 'pan') {
            updatePan(progress);
          }
        };

        const requestUpdate = () => {
          if (ticking) {
            return;
          }

          ticking = true;
          window.requestAnimationFrame(updateMotion);
        };

        window.addEventListener('scroll', requestUpdate, { passive: true });
        window.addEventListener('resize', requestUpdate);

        if (typeof reducedMotion.addEventListener === 'function') {
          reducedMotion.addEventListener('change', requestUpdate);
        } else if (typeof reducedMotion.addListener === 'function') {
          reducedMotion.addListener(requestUpdate);
        }

        requestUpdate();
      })();
    </script>
  @endif
</div>