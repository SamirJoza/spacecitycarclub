{{--
==============================================================================
File path + filename: resources/views/blocks/image-text-split.blade.php
==============================================================================

Purpose:
- Render a flexible split layout with an image on one side and nested content
  blocks on the other.

Why this file exists:
- The user wants a page/content block inspired by the "Origin Story" section:
  text on one side, image on the other, with a switch to flip the image left
  or right on larger screens.
- The text side must support nested Gutenberg blocks, not fixed text fields.
- The opening heading in the text area gets a neon/gradient underline treatment.
- The section shell always spans full width, while the inner content remains
  constrained by a selectable Tailwind-style container width.

Important implementation notes:
- Only one InnerBlocks area is used because WordPress/ACF supports a single
  InnerBlocks region per block.
- The starter template is a Heading + Paragraph.
- The template is intentionally not locked, so editors may remove the starter
  blocks or insert other allowed blocks.
- The image alt text is read from the ACF image array, which reflects the
  WordPress attachment alt text metadata.
- Optional CTA fields render below the nested text content and stay out of the
  image/media area.
- Parallax and scroller treatments use the optional background image with
  editor-controlled speed, direction, overlay color, and overlay opacity.

Critical troubleshooting note:
- The InnerBlocks JSON attributes must only be escaped once.
- In Blade, pre-escaped JSON should be output raw with `{!! !!}`.
- Using `{{ }}` after `esc_attr()` causes double-escaping and can break the
  editor preview.

Theme compatibility:
- Dark mode supports both project theme signals:
  - html[data-theme="dark"]
  - .dark
- Light mode supports:
  - html[data-theme="light"]
- Non-image background treatments are aligned with the Event Impact Flow block:
  - transparent
  - surface
  - gradient
  - dark_panel

Layout behavior:
- Section shell: always full width / full bleed on the front end
- Editor shell: expands across the block editor canvas so the background is
  visible while editing
- Inner content: selectable Tailwind-style container width
- Mobile: single-column stacked layout with text first
- Desktop: two-column split layout
- Image left/right switch applies on larger screens

Styling note:
- All CSS is scoped to `.sccc-image-text-split` so it does not bleed into the
  rest of the theme.
--}}

@php
  $imageUrl = '';

  if (is_array($image) && ! empty($image['ID'])) {
      $imageUrl = wp_get_attachment_image_url((int) $image['ID'], 'large');
  }

  $imageAlt = '';

  if (is_array($image)) {
      $imageAlt = trim((string) ($image['alt'] ?? ''));
  }

  $backgroundImageUrl = '';

  if (is_array($backgroundImage) && ! empty($backgroundImage['ID'])) {
      $backgroundImageUrl = wp_get_attachment_image_url((int) $backgroundImage['ID'], 'full');
  }

  $hasMotionBackground = $backgroundImageUrl && in_array($backgroundTreatment, ['parallax', 'scroller'], true);

  $parallaxSpeedValue = is_numeric($parallaxSpeed ?? null)
      ? max(1, min(10, (float) $parallaxSpeed))
      : 4;

  $scrollerSpeedValue = is_numeric($scrollerSpeed ?? null)
      ? max(1, min(10, (float) $scrollerSpeed))
      : 4;

  $scrollerDirectionValue = in_array($scrollerDirection ?? '', ['top_to_bottom', 'bottom_to_top'], true)
      ? $scrollerDirection
      : 'top_to_bottom';

  $backgroundOverlayColorValue = is_string($backgroundOverlayColor ?? null)
      && preg_match('/^#[0-9A-Fa-f]{6}$/', $backgroundOverlayColor) === 1
          ? $backgroundOverlayColor
          : '#020617';

  $backgroundOverlayOpacityValue = is_numeric($backgroundOverlayOpacity ?? null)
      ? max(0, min(90, (float) $backgroundOverlayOpacity)) / 100
      : 0.55;

  $scrollerDurationValue = max(12, 54 - ($scrollerSpeedValue * 4));

  $ctaItems = [];

  if (in_array($ctaCount, ['one', 'two'], true) && is_array($primaryCta)) {
      $ctaItems[] = [
          'type' => 'primary',
          'link' => $primaryCta,
      ];
  }

  if ($ctaCount === 'two' && is_array($secondaryCta)) {
      $ctaItems[] = [
          'type' => 'secondary',
          'link' => $secondaryCta,
      ];
  }

  /**
   * These JSON strings are intentionally escaped once here and then printed
   * raw in the InnerBlocks attributes below.
   *
   * Do NOT swap these to `{{ }}` output later, or the JSON will be escaped
   * a second time and InnerBlocks may fail to initialize in the editor.
   */
  $allowedBlocksAttr = esc_attr(wp_json_encode($allowedBlocks ?? []));
  $templateAttr = esc_attr(wp_json_encode($innerBlocksTemplate ?? []));
@endphp

@once
  <style>
    /* ==========================================================================
       Image / Text Split block
       Fully scoped so styles stay local to this block.
       ========================================================================== */

    .sccc-image-text-split {
      --sccc-split-bg: transparent;
      --sccc-split-text: var(--color-text, #f8fafc);
      --sccc-split-muted: var(--color-muted, #a0a7b2);
      --sccc-split-surface: var(--color-surface, rgba(20, 20, 20, 0.86));
      --sccc-split-line: var(--color-line, rgba(255, 255, 255, 0.14));
      --sccc-split-primary: var(--color-primary-500, #2979ff);
      --sccc-split-accent: var(--color-accent-500, #ff1744);
      --sccc-split-purple: #a855f7;
      --sccc-split-surface-light: #ffffff;
      --sccc-split-surface-dark: #1a2332;
      --sccc-split-copy-light: #5f6b7a;
      --sccc-split-copy-dark: #d1d5db;

      --sccc-gradient-club-blue: linear-gradient(90deg, #135bec 0%, #1f8fff 52%, #71d7ff 100%);
      --sccc-gradient-signal-red: linear-gradient(90deg, #b31324 0%, #ff1744 50%, #ff7a59 100%);
      --sccc-gradient-space-city: linear-gradient(90deg, #135bec 0%, #43beff 42%, #ae71ff 72%, #ff1744 100%);
      --sccc-color-club-blue: #135bec;

      --sccc-split-neon-gradient: var(--sccc-header-underline-gradient, var(--sccc-gradient-space-city));
      --sccc-split-shadow:
        0 24px 48px -24px rgba(15, 23, 42, 0.28),
        0 14px 28px -24px rgba(19, 91, 236, 0.18);

      position: relative;
      isolation: isolate;
      overflow: hidden;
      width: 100vw;
      max-width: 100vw;
      margin-top: 1.5rem;
      margin-right: calc(50% - 50vw);
      margin-bottom: 1.5rem;
      margin-left: calc(50% - 50vw);
      padding-block: 4rem;
      color: var(--sccc-split-text);
      background: var(--sccc-split-bg);
    }

    html[data-theme="light"] .sccc-image-text-split {
      --sccc-split-text: var(--color-text, #111827);
      --sccc-split-muted: var(--color-muted, #4b5563);
      --sccc-split-surface: var(--color-surface, rgba(255, 255, 255, 0.94));
      --sccc-split-line: var(--color-line, rgba(17, 24, 39, 0.14));
      --sccc-split-shadow: 0 20px 50px rgba(15, 23, 42, 0.10);
    }

    html[data-theme="dark"] .sccc-image-text-split,
    .dark .sccc-image-text-split {
      --sccc-split-text: var(--color-text, #ffffff);
      --sccc-split-muted: var(--color-muted, #a0a7b2);
      --sccc-split-surface: var(--color-surface, rgba(20, 20, 20, 0.84));
      --sccc-split-line: var(--color-line, rgba(255, 255, 255, 0.14));
      --sccc-split-shadow: 0 20px 60px rgba(0, 0, 0, 0.34);
    }

    @supports (width: 100dvw) {
      .sccc-image-text-split {
        width: 100dvw;
        max-width: 100dvw;
        margin-right: calc(50% - 50dvw);
        margin-left: calc(50% - 50dvw);
      }
    }

    /*
    |--------------------------------------------------------------------------
    | Gutenberg editor width correction
    |--------------------------------------------------------------------------
    | The front end uses true full-bleed viewport math.
    | Inside Gutenberg, the ACF block is wrapped by editor containers that can
    | limit width. These selectors expand the editor wrapper so the section
    | background is visible across the block canvas while still keeping the
    | actual content inside the selected inner container.
    */
    .editor-styles-wrapper [data-type="acf/image-text-split"],
    .editor-styles-wrapper .wp-block-acf-image-text-split,
    body.wp-admin .editor-styles-wrapper [data-type="acf/image-text-split"],
    body.wp-admin .editor-styles-wrapper .wp-block-acf-image-text-split {
      width: 100% !important;
      max-width: none !important;
    }

    .editor-styles-wrapper .sccc-image-text-split,
    body.wp-admin .editor-styles-wrapper .sccc-image-text-split {
      width: 100% !important;
      max-width: none !important;
      margin-right: 0 !important;
      margin-left: 0 !important;
    }

    .sccc-image-text-split *,
    .sccc-image-text-split *::before,
    .sccc-image-text-split *::after {
      box-sizing: border-box;
    }

    /* ==========================================================================
       Section spacing controls
       These map the editor's keyword choices to predictable section spacing.
       ========================================================================== */

    .sccc-image-text-split[data-padding-top="none"] { padding-top: 0; }
    .sccc-image-text-split[data-padding-top="s"] { padding-top: clamp(1.5rem, 3vw, 2.5rem); }
    .sccc-image-text-split[data-padding-top="m"] { padding-top: clamp(2.5rem, 5vw, 4rem); }
    .sccc-image-text-split[data-padding-top="l"] { padding-top: clamp(4rem, 7vw, 6rem); }
    .sccc-image-text-split[data-padding-top="xl"] { padding-top: clamp(5rem, 9vw, 8rem); }
    .sccc-image-text-split[data-padding-top="xxl"] { padding-top: clamp(6rem, 11vw, 10rem); }

    .sccc-image-text-split[data-padding-bottom="none"] { padding-bottom: 0; }
    .sccc-image-text-split[data-padding-bottom="s"] { padding-bottom: clamp(1.5rem, 3vw, 2.5rem); }
    .sccc-image-text-split[data-padding-bottom="m"] { padding-bottom: clamp(2.5rem, 5vw, 4rem); }
    .sccc-image-text-split[data-padding-bottom="l"] { padding-bottom: clamp(4rem, 7vw, 6rem); }
    .sccc-image-text-split[data-padding-bottom="xl"] { padding-bottom: clamp(5rem, 9vw, 8rem); }
    .sccc-image-text-split[data-padding-bottom="xxl"] { padding-bottom: clamp(6rem, 11vw, 10rem); }

    .sccc-image-text-split[data-margin-top="none"] { margin-top: 0; }
    .sccc-image-text-split[data-margin-top="s"] { margin-top: 1.5rem; }
    .sccc-image-text-split[data-margin-top="m"] { margin-top: 3rem; }
    .sccc-image-text-split[data-margin-top="l"] { margin-top: 4.5rem; }
    .sccc-image-text-split[data-margin-top="xl"] { margin-top: 6rem; }
    .sccc-image-text-split[data-margin-top="xxl"] { margin-top: 8rem; }

    .sccc-image-text-split[data-margin-bottom="none"] { margin-bottom: 0; }
    .sccc-image-text-split[data-margin-bottom="s"] { margin-bottom: 1.5rem; }
    .sccc-image-text-split[data-margin-bottom="m"] { margin-bottom: 3rem; }
    .sccc-image-text-split[data-margin-bottom="l"] { margin-bottom: 4.5rem; }
    .sccc-image-text-split[data-margin-bottom="xl"] { margin-bottom: 6rem; }
    .sccc-image-text-split[data-margin-bottom="xxl"] { margin-bottom: 8rem; }

    /* ==========================================================================
       Non-image background treatments
       Matched to the Event Impact Flow background behavior.
       ========================================================================== */

    .sccc-image-text-split[data-background-treatment="transparent"],
    .sccc-image-text-split[data-background-treatment="surface"],
    .sccc-image-text-split[data-background-treatment="default"] {
      --sccc-split-bg: transparent;
    }

    .sccc-image-text-split[data-background-treatment="gradient"],
    .sccc-image-text-split[data-background-treatment="muted"] {
      --sccc-split-bg:
        radial-gradient(circle at 10% 0%, color-mix(in oklab, var(--sccc-split-primary) 12%, transparent) 0%, transparent 36%),
        radial-gradient(circle at 90% 100%, color-mix(in oklab, var(--sccc-split-accent) 10%, transparent) 0%, transparent 38%),
        transparent;
    }

    .sccc-image-text-split[data-background-treatment="dark_panel"],
    .sccc-image-text-split[data-background-treatment="dark"] {
      --sccc-split-bg:
        radial-gradient(circle at 14% 0%, rgba(41, 121, 255, 0.14) 0%, transparent 34%),
        radial-gradient(circle at 86% 100%, rgba(255, 23, 68, 0.12) 0%, transparent 40%),
        transparent;
    }

    /*
    |--------------------------------------------------------------------------
    | Image-based background treatments
    |--------------------------------------------------------------------------
    | Parallax and scroller use a separate absolutely positioned layer so the
    | section can control speed, direction, overlay color, and overlay opacity.
    */
    .sccc-image-text-split[data-background-treatment="parallax"],
    .sccc-image-text-split[data-background-treatment="scroller"] {
      --sccc-split-bg: #050914;
    }

    .sccc-image-text-split__background {
      position: absolute;
      inset: 0;
      z-index: -4;
      display: block;
      pointer-events: none;
      background-image: var(--sccc-bg-image);
      background-position: center;
      background-repeat: no-repeat;
      background-size: cover;
      transform: translate3d(0, 0, 0) scale(1);
      will-change: transform;
    }

    .sccc-image-text-split[data-background-treatment="parallax"] .sccc-image-text-split__background {
      inset: -12%;
      transform: translate3d(0, 0, 0) scale(1.12);
    }

    .sccc-image-text-split[data-background-treatment="scroller"] .sccc-image-text-split__background {
      inset: -10%;
      transform: scale(1.12);
      animation-duration: var(--sccc-bg-scroll-duration, 38s);
      animation-timing-function: ease-in-out;
      animation-iteration-count: infinite;
      animation-direction: alternate;
      animation-fill-mode: both;
    }

    .sccc-image-text-split[data-background-treatment="scroller"][data-scroller-direction="top_to_bottom"] .sccc-image-text-split__background {
      background-position: center top;
      animation-name: sccc-image-text-split-scroll-down;
    }

    .sccc-image-text-split[data-background-treatment="scroller"][data-scroller-direction="bottom_to_top"] .sccc-image-text-split__background {
      background-position: center bottom;
      animation-name: sccc-image-text-split-scroll-up;
    }

    .sccc-image-text-split__background-color-overlay {
      position: absolute;
      inset: 0;
      z-index: -3;
      display: block;
      pointer-events: none;
      background: var(--sccc-bg-overlay-color, #020617);
      opacity: var(--sccc-bg-overlay-opacity, 0.55);
    }

    .sccc-image-text-split__background-scrim {
      position: absolute;
      inset: 0;
      z-index: -2;
      display: block;
      pointer-events: none;
      background:
        linear-gradient(90deg, rgba(4, 8, 16, 0.78) 0%, rgba(4, 8, 16, 0.56) 46%, rgba(4, 8, 16, 0.22) 100%),
        radial-gradient(48rem 28rem at 10% 12%, rgba(67, 190, 255, 0.18), transparent 64%);
    }

    @keyframes sccc-image-text-split-scroll-down {
      0% {
        transform: scale(1.12) translate3d(0, -4%, 0);
      }

      100% {
        transform: scale(1.12) translate3d(0, 4%, 0);
      }
    }

    @keyframes sccc-image-text-split-scroll-up {
      0% {
        transform: scale(1.12) translate3d(0, 4%, 0);
      }

      100% {
        transform: scale(1.12) translate3d(0, -4%, 0);
      }
    }

    @media (max-width: 767px), (prefers-reduced-motion: reduce) {
      .sccc-image-text-split[data-background-treatment="parallax"] .sccc-image-text-split__background {
        transform: scale(1.08);
      }

      .sccc-image-text-split[data-background-treatment="scroller"] .sccc-image-text-split__background {
        animation: none;
        transform: scale(1.08);
      }
    }

    .sccc-image-text-split:is(
      [data-background-treatment="parallax"],
      [data-background-treatment="scroller"]
    ) .sccc-image-text-split__innerblocks,
    .sccc-image-text-split:is(
      [data-background-treatment="parallax"],
      [data-background-treatment="scroller"]
    ) .sccc-image-text-split__innerblocks .wp-block-heading,
    .sccc-image-text-split:is(
      [data-background-treatment="parallax"],
      [data-background-treatment="scroller"]
    ) .sccc-image-text-split__innerblocks h1,
    .sccc-image-text-split:is(
      [data-background-treatment="parallax"],
      [data-background-treatment="scroller"]
    ) .sccc-image-text-split__innerblocks h2,
    .sccc-image-text-split:is(
      [data-background-treatment="parallax"],
      [data-background-treatment="scroller"]
    ) .sccc-image-text-split__innerblocks h3,
    .sccc-image-text-split:is(
      [data-background-treatment="parallax"],
      [data-background-treatment="scroller"]
    ) .sccc-image-text-split__innerblocks h4,
    .sccc-image-text-split:is(
      [data-background-treatment="parallax"],
      [data-background-treatment="scroller"]
    ) .sccc-image-text-split__innerblocks h5,
    .sccc-image-text-split:is(
      [data-background-treatment="parallax"],
      [data-background-treatment="scroller"]
    ) .sccc-image-text-split__innerblocks h6 {
      color: #ffffff;
    }

    .sccc-image-text-split:is(
      [data-background-treatment="parallax"],
      [data-background-treatment="scroller"]
    ) .sccc-image-text-split__innerblocks p,
    .sccc-image-text-split:is(
      [data-background-treatment="parallax"],
      [data-background-treatment="scroller"]
    ) .sccc-image-text-split__innerblocks li,
    .sccc-image-text-split:is(
      [data-background-treatment="parallax"],
      [data-background-treatment="scroller"]
    ) .sccc-image-text-split__innerblocks blockquote {
      color: rgba(255, 255, 255, 0.82);
    }

    /* ==========================================================================
       Inner container widths
       Values are aligned to common Tailwind max-width concepts.
       ========================================================================== */

    .sccc-image-text-split__inner {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 80rem;
      margin-inline: auto;
      padding-inline: clamp(1rem, 2.5vw, 2rem);
      display: grid;
      gap: 3rem;
      align-items: center;
    }

    .sccc-image-text-split[data-container-width="narrow"] .sccc-image-text-split__inner {
      max-width: 64rem;
    }

    .sccc-image-text-split[data-container-width="standard"] .sccc-image-text-split__inner {
      max-width: 80rem;
    }

    .sccc-image-text-split[data-container-width="wide"] .sccc-image-text-split__inner {
      max-width: 96rem;
    }

    .sccc-image-text-split__content {
      order: 1;
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
      min-width: 0;
    }

    .sccc-image-text-split__media {
      order: 2;
      min-width: 0;
    }

    /* ==========================================================================
       Media frame
       Image receives the requested neon border treatment and shadow.
       ========================================================================== */

    .sccc-image-text-split__media-frame {
      position: relative;
      overflow: hidden;
      min-height: 25rem;
      border: 1px solid transparent;
      border-radius: 0.75rem;
      background:
        linear-gradient(var(--sccc-split-surface-light), var(--sccc-split-surface-light)) padding-box,
        var(--sccc-split-neon-gradient) border-box;
      box-shadow:
        var(--sccc-split-shadow),
        0 0 0 1px rgba(67, 190, 255, 0.10),
        0 0 30px rgba(67, 190, 255, 0.20),
        0 0 52px rgba(174, 113, 255, 0.12);
    }

    html[data-theme="dark"] .sccc-image-text-split__media-frame,
    .dark .sccc-image-text-split__media-frame,
    .sccc-image-text-split:is(
      [data-background-treatment="parallax"],
      [data-background-treatment="scroller"]
    ) .sccc-image-text-split__media-frame {
      background:
        linear-gradient(var(--sccc-split-surface-dark), var(--sccc-split-surface-dark)) padding-box,
        var(--sccc-split-neon-gradient) border-box;
      box-shadow:
        0 24px 56px -28px rgba(0, 0, 0, 0.70),
        0 0 0 1px rgba(67, 190, 255, 0.16),
        0 0 34px rgba(67, 190, 255, 0.24),
        0 0 58px rgba(174, 113, 255, 0.16);
    }

    .sccc-image-text-split__image {
      display: block;
      width: 100%;
      height: 100%;
      min-height: 25rem;
      object-fit: cover;
      transition: transform 700ms ease;
    }

    .sccc-image-text-split__media-frame:hover .sccc-image-text-split__image,
    .sccc-image-text-split__media-frame:focus-within .sccc-image-text-split__image {
      transform: scale(1.05);
    }

    .sccc-image-text-split__overlay {
      position: absolute;
      inset: 0;
      background: rgba(19, 91, 236, 0.18);
      transition: background-color 300ms ease;
      pointer-events: none;
    }

    .sccc-image-text-split__media-frame:hover .sccc-image-text-split__overlay,
    .sccc-image-text-split__media-frame:focus-within .sccc-image-text-split__overlay {
      background: transparent;
    }

    .sccc-image-text-split__placeholder {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 25rem;
      padding: 2rem;
      text-align: center;
      color: var(--sccc-split-muted);
      background:
        linear-gradient(135deg, rgba(19, 91, 236, 0.06), rgba(59, 130, 246, 0.02)),
        var(--sccc-split-surface);
    }

    .sccc-image-text-split__placeholder-copy {
      margin: 0;
      font-size: 1.125rem;
      line-height: 1.6;
    }

    /* ==========================================================================
       InnerBlocks typography
       ========================================================================== */

    .sccc-image-text-split__innerblocks {
      color: var(--sccc-split-text);
    }

    .sccc-image-text-split__innerblocks p,
    .sccc-image-text-split__innerblocks li,
    .sccc-image-text-split__innerblocks blockquote {
      color: var(--sccc-split-muted);
      line-height: 1.75;
    }

    .sccc-image-text-split__innerblocks .wp-block-heading,
    .sccc-image-text-split__innerblocks h1,
    .sccc-image-text-split__innerblocks h2,
    .sccc-image-text-split__innerblocks h3,
    .sccc-image-text-split__innerblocks h4,
    .sccc-image-text-split__innerblocks h5,
    .sccc-image-text-split__innerblocks h6 {
      color: var(--sccc-split-text);
      line-height: 1.2;
      letter-spacing: -0.02em;
    }

    /*
    |--------------------------------------------------------------------------
    | Neon gradient underline for the opening heading
    |--------------------------------------------------------------------------
    */
    .sccc-image-text-split__innerblocks > .wp-block-heading:first-child,
    .sccc-image-text-split__innerblocks > h1:first-child,
    .sccc-image-text-split__innerblocks > h2:first-child,
    .sccc-image-text-split__innerblocks > h3:first-child,
    .sccc-image-text-split__innerblocks > h4:first-child,
    .sccc-image-text-split__innerblocks > h5:first-child,
    .sccc-image-text-split__innerblocks > h6:first-child,
    .sccc-image-text-split__innerblocks > .block-editor-inner-blocks > .block-editor-block-list__layout > .wp-block-heading:first-child,
    .sccc-image-text-split__innerblocks > .acf-innerblocks-container > .wp-block-heading:first-child {
      position: relative;
      margin-bottom: 2rem;
      padding-bottom: 1.25rem;
    }

    .sccc-image-text-split[data-headline-margin-bottom="none"] .sccc-image-text-split__innerblocks > .wp-block-heading:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="none"] .sccc-image-text-split__innerblocks > h1:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="none"] .sccc-image-text-split__innerblocks > h2:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="none"] .sccc-image-text-split__innerblocks > h3:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="none"] .sccc-image-text-split__innerblocks > h4:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="none"] .sccc-image-text-split__innerblocks > h5:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="none"] .sccc-image-text-split__innerblocks > h6:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="none"] .sccc-image-text-split__innerblocks > .block-editor-inner-blocks > .block-editor-block-list__layout > .wp-block-heading:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="none"] .sccc-image-text-split__innerblocks > .acf-innerblocks-container > .wp-block-heading:first-child {
      margin-bottom: 0;
    }

    .sccc-image-text-split[data-headline-margin-bottom="s"] .sccc-image-text-split__innerblocks > .wp-block-heading:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="s"] .sccc-image-text-split__innerblocks > h1:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="s"] .sccc-image-text-split__innerblocks > h2:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="s"] .sccc-image-text-split__innerblocks > h3:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="s"] .sccc-image-text-split__innerblocks > h4:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="s"] .sccc-image-text-split__innerblocks > h5:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="s"] .sccc-image-text-split__innerblocks > h6:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="s"] .sccc-image-text-split__innerblocks > .block-editor-inner-blocks > .block-editor-block-list__layout > .wp-block-heading:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="s"] .sccc-image-text-split__innerblocks > .acf-innerblocks-container > .wp-block-heading:first-child {
      margin-bottom: 1rem;
    }

    .sccc-image-text-split[data-headline-margin-bottom="m"] .sccc-image-text-split__innerblocks > .wp-block-heading:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="m"] .sccc-image-text-split__innerblocks > h1:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="m"] .sccc-image-text-split__innerblocks > h2:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="m"] .sccc-image-text-split__innerblocks > h3:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="m"] .sccc-image-text-split__innerblocks > h4:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="m"] .sccc-image-text-split__innerblocks > h5:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="m"] .sccc-image-text-split__innerblocks > h6:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="m"] .sccc-image-text-split__innerblocks > .block-editor-inner-blocks > .block-editor-block-list__layout > .wp-block-heading:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="m"] .sccc-image-text-split__innerblocks > .acf-innerblocks-container > .wp-block-heading:first-child {
      margin-bottom: 2rem;
    }

    .sccc-image-text-split[data-headline-margin-bottom="l"] .sccc-image-text-split__innerblocks > .wp-block-heading:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="l"] .sccc-image-text-split__innerblocks > h1:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="l"] .sccc-image-text-split__innerblocks > h2:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="l"] .sccc-image-text-split__innerblocks > h3:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="l"] .sccc-image-text-split__innerblocks > h4:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="l"] .sccc-image-text-split__innerblocks > h5:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="l"] .sccc-image-text-split__innerblocks > h6:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="l"] .sccc-image-text-split__innerblocks > .block-editor-inner-blocks > .block-editor-block-list__layout > .wp-block-heading:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="l"] .sccc-image-text-split__innerblocks > .acf-innerblocks-container > .wp-block-heading:first-child {
      margin-bottom: 3rem;
    }

    .sccc-image-text-split[data-headline-margin-bottom="xl"] .sccc-image-text-split__innerblocks > .wp-block-heading:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="xl"] .sccc-image-text-split__innerblocks > h1:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="xl"] .sccc-image-text-split__innerblocks > h2:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="xl"] .sccc-image-text-split__innerblocks > h3:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="xl"] .sccc-image-text-split__innerblocks > h4:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="xl"] .sccc-image-text-split__innerblocks > h5:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="xl"] .sccc-image-text-split__innerblocks > h6:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="xl"] .sccc-image-text-split__innerblocks > .block-editor-inner-blocks > .block-editor-block-list__layout > .wp-block-heading:first-child,
    .sccc-image-text-split[data-headline-margin-bottom="xl"] .sccc-image-text-split__innerblocks > .acf-innerblocks-container > .wp-block-heading:first-child {
      margin-bottom: 4rem;
    }

    .sccc-image-text-split__innerblocks > .wp-block-heading:first-child::after,
    .sccc-image-text-split__innerblocks > h1:first-child::after,
    .sccc-image-text-split__innerblocks > h2:first-child::after,
    .sccc-image-text-split__innerblocks > h3:first-child::after,
    .sccc-image-text-split__innerblocks > h4:first-child::after,
    .sccc-image-text-split__innerblocks > h5:first-child::after,
    .sccc-image-text-split__innerblocks > h6:first-child::after,
    .sccc-image-text-split__innerblocks > .block-editor-inner-blocks > .block-editor-block-list__layout > .wp-block-heading:first-child::after,
    .sccc-image-text-split__innerblocks > .acf-innerblocks-container > .wp-block-heading:first-child::after {
      content: '';
      position: absolute;
      left: 0;
      bottom: 0;
      width: min(16rem, 56%);
      height: 0.25rem;
      border-radius: 9999px;
      background: var(--sccc-split-neon-gradient);
      box-shadow:
        0 0 10px rgba(67, 190, 255, 0.35),
        0 0 20px rgba(174, 113, 255, 0.18);
    }

    .sccc-image-text-split__innerblocks .wp-block-buttons {
      margin-top: 1.5rem;
    }

    .sccc-image-text-split__innerblocks .wp-block-button__link {
      border-radius: 0.5rem;
    }

    /* ==========================================================================
       CTA field output
       These buttons are separate from InnerBlocks and always live in the text area.
       ========================================================================== */

    .sccc-image-text-split__ctas {
      display: flex;
      flex-wrap: wrap;
      gap: 0.875rem;
      align-items: center;
      margin-top: 0.25rem;
    }

    .sccc-image-text-split__cta {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 2.875rem;
      padding: 0.72rem 1.15rem;
      border-radius: 9999px;
      font-size: 0.92rem;
      font-weight: 800;
      line-height: 1.1;
      text-decoration: none !important;
      transition:
        transform 180ms ease,
        box-shadow 180ms ease,
        border-color 180ms ease,
        background-color 180ms ease,
        color 180ms ease;
    }

    .sccc-image-text-split__cta:hover,
    .sccc-image-text-split__cta:focus-visible {
      transform: translateY(-1px);
      text-decoration: none !important;
    }

    .sccc-image-text-split__cta:focus-visible {
      outline: 2px solid rgba(113, 215, 255, 0.88);
      outline-offset: 3px;
    }

    .sccc-image-text-split__cta--primary {
      border: 1px solid transparent;
      color: var(--sccc-split-text);
      background:
        linear-gradient(var(--sccc-split-surface), var(--sccc-split-surface)) padding-box,
        var(--sccc-split-neon-gradient) border-box;
      box-shadow:
        0 0 0 1px rgba(67, 190, 255, 0.10),
        0 0 24px rgba(67, 190, 255, 0.18);
    }

    .sccc-image-text-split__cta--primary:hover,
    .sccc-image-text-split__cta--primary:focus-visible {
      box-shadow:
        0 0 0 1px rgba(67, 190, 255, 0.18),
        0 0 30px rgba(67, 190, 255, 0.26),
        0 0 42px rgba(174, 113, 255, 0.16);
    }

    .sccc-image-text-split__cta--secondary {
      border: 1px solid var(--sccc-split-line);
      color: var(--sccc-split-text);
      background: color-mix(in oklab, var(--sccc-split-surface) 70%, transparent);
      box-shadow: 0 10px 24px -20px rgba(15, 23, 42, 0.35);
    }

    .sccc-image-text-split__cta--secondary:hover,
    .sccc-image-text-split__cta--secondary:focus-visible {
      border-color: rgba(67, 190, 255, 0.52);
      background: rgba(67, 190, 255, 0.10);
      box-shadow: 0 14px 30px -24px rgba(67, 190, 255, 0.50);
    }

    @media (min-width: 768px) {
      .sccc-image-text-split__inner {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 3rem;
      }

      .sccc-image-text-split[data-image-position="left"] .sccc-image-text-split__media {
        order: 1;
      }

      .sccc-image-text-split[data-image-position="left"] .sccc-image-text-split__content {
        order: 2;
      }

      .sccc-image-text-split[data-image-position="right"] .sccc-image-text-split__content {
        order: 1;
      }

      .sccc-image-text-split[data-image-position="right"] .sccc-image-text-split__media {
        order: 2;
      }
    }
  </style>

  <script>
    /*
     * Image / Text Split parallax background controller
     *
     * Why this exists:
     * - CSS background-attachment gives no practical per-block speed control.
     * - This small scoped controller lets the editor's Parallax Speed field
     *   affect only this block's transformed background layer.
     */
    (() => {
      const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

      const updateParallaxBackgrounds = () => {
        if (motionQuery.matches) {
          return;
        }

        const sections = document.querySelectorAll(
          '.sccc-image-text-split[data-background-treatment="parallax"]'
        );

        sections.forEach((section) => {
          const background = section.querySelector('.sccc-image-text-split__background');

          if (!background) {
            return;
          }

          const speed = Math.min(
            10,
            Math.max(1, Number.parseFloat(section.dataset.parallaxSpeed || '4'))
          );

          const rect = section.getBoundingClientRect();
          const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
          const sectionCenterOffset = rect.top + (rect.height / 2) - (viewportHeight / 2);
          const movement = sectionCenterOffset * speed * -0.035;

          background.style.transform = `translate3d(0, ${movement}px, 0) scale(1.12)`;
        });
      };

      let ticking = false;

      const requestUpdate = () => {
        if (ticking) {
          return;
        }

        ticking = true;

        window.requestAnimationFrame(() => {
          updateParallaxBackgrounds();
          ticking = false;
        });
      };

      window.addEventListener('scroll', requestUpdate, { passive: true });
      window.addEventListener('resize', requestUpdate);
      document.addEventListener('DOMContentLoaded', updateParallaxBackgrounds);

      updateParallaxBackgrounds();
    })();
  </script>
@endonce

<section
  {{ $attributes->class('sccc-image-text-split') }}
  data-image-position="{{ esc_attr($imagePosition) }}"
  data-container-width="{{ esc_attr($containerWidth) }}"
  data-padding-top="{{ esc_attr($sectionPaddingTop) }}"
  data-padding-bottom="{{ esc_attr($sectionPaddingBottom) }}"
  data-margin-top="{{ esc_attr($sectionMarginTop) }}"
  data-margin-bottom="{{ esc_attr($sectionMarginBottom) }}"
  data-background-treatment="{{ esc_attr($backgroundTreatment) }}"
  data-headline-margin-bottom="{{ esc_attr($headlineMarginBottom) }}"
  data-parallax-speed="{{ esc_attr((string) $parallaxSpeedValue) }}"
  data-scroller-direction="{{ esc_attr($scrollerDirectionValue) }}"
  style="
    --sccc-bg-image: url('{{ esc_url($backgroundImageUrl) }}');
    --sccc-bg-scroll-duration: {{ esc_attr((string) $scrollerDurationValue) }}s;
    --sccc-bg-overlay-color: {{ esc_attr($backgroundOverlayColorValue) }};
    --sccc-bg-overlay-opacity: {{ esc_attr((string) $backgroundOverlayOpacityValue) }};
  "
>
  @if ($hasMotionBackground)
    <span class="sccc-image-text-split__background" aria-hidden="true"></span>
    <span class="sccc-image-text-split__background-color-overlay" aria-hidden="true"></span>
    <span class="sccc-image-text-split__background-scrim" aria-hidden="true"></span>
  @endif

  <div class="sccc-image-text-split__inner">
    <div class="sccc-image-text-split__content">
      <InnerBlocks
        class="sccc-image-text-split__innerblocks"
        allowedBlocks="{!! $allowedBlocksAttr !!}"
        template="{!! $templateAttr !!}"
      />

      @if (! empty($ctaItems))
        <div class="sccc-image-text-split__ctas" aria-label="Section actions">
          @foreach ($ctaItems as $ctaItem)
            @php
              $ctaLink = $ctaItem['link'];
              $ctaTarget = $ctaLink['target'] ?? '_self';
            @endphp

            <a
              class="sccc-image-text-split__cta sccc-image-text-split__cta--{{ esc_attr($ctaItem['type']) }}"
              href="{{ esc_url($ctaLink['url']) }}"
              @if ($ctaTarget !== '_self') target="{{ esc_attr($ctaTarget) }}" rel="noopener noreferrer" @endif
            >
              {{ esc_html($ctaLink['title']) }}
            </a>
          @endforeach
        </div>
      @endif
    </div>

    <div class="sccc-image-text-split__media">
      <div class="sccc-image-text-split__media-frame">
        @if ($imageUrl)
          <img
            class="sccc-image-text-split__image"
            src="{{ esc_url($imageUrl) }}"
            alt="{{ esc_attr($imageAlt) }}"
            loading="lazy"
            decoding="async"
          >
          <div class="sccc-image-text-split__overlay" aria-hidden="true"></div>
        @else
          <div class="sccc-image-text-split__placeholder">
            <p class="sccc-image-text-split__placeholder-copy">
              Select an image for this section.
            </p>
          </div>
        @endif
      </div>
    </div>
  </div>
</section>