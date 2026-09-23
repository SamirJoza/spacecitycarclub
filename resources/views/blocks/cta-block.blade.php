{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/blocks/cta-block.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the reusable CTA Block.
|
| Why this file exists:
| - The block is intentionally generic so it can be reused anywhere in
|   Gutenberg with editable text, alignment, spacing, themes, and one or two
|   buttons.
|
| Notes:
| - This block uses the same polished visual language as the rest of the
|   project so it feels native to the site.
| - Three curated themes are provided:
|   - Cobalt: cool blue / polished default
|   - Slate: neutral glass / more understated
|   - Aurora: richer blue-violet glow
| - Each theme includes light and dark mode adjustments for readability.
| - Full-width is now controlled by Gutenberg's built-in alignfull class.
| - Optional background images always use cover positioning.
| - Overlay colors are curated to preserve contrast in light and dark theme.
|
| Change note:
| - Fixes visible "<br />" output in the CTA body field.
| - Uses WordPress formatting + sanitization before printing formatted HTML.
| - Keeps the change scoped to this Blade file only.
|--------------------------------------------------------------------------
--}}

@php
  $blockClasses = [
    'sccc-cta-block',
    'is-align-' . ($alignment ?? 'center'),
    'is-theme-' . ($theme ?? 'cobalt'),
    'is-mt-' . ($marginTop ?? 'none'),
    'is-mb-' . ($marginBottom ?? 'none'),
  ];

  $cardClasses = [
    'sccc-cta-block__card',
    'is-pt-' . ($paddingTop ?? 'md'),
    'is-pb-' . ($paddingBottom ?? 'md'),
  ];

  $hasBackgroundImage = !empty($backgroundImageUrl);
  $backgroundOverlayColor = $backgroundOverlayColor ?? 'dark';
  $backgroundOverlayOpacity = is_numeric($backgroundOverlayOpacity ?? null) ? (float) $backgroundOverlayOpacity : 0.58;

  if ($hasBackgroundImage) {
    $blockClasses[] = 'has-background-image';
    $blockClasses[] = 'is-overlay-' . $backgroundOverlayColor;
  }

  $cardStyle = $hasBackgroundImage
    ? '--sccc-cta-background-image: url("' . esc_url($backgroundImageUrl) . '"); --sccc-cta-overlay-opacity: ' . esc_attr((string) $backgroundOverlayOpacity) . ';'
    : '';

  $hasPrimary = !empty($primaryButton['enabled']);
  $hasSecondary = !empty($secondaryButton['enabled']);

  /**
   * Format CTA body copy safely for frontend display.
   *
   * Why this exists:
   * - ACF Text Area fields may already return <br> tags when the field's
   *   "New Lines" setting is set to automatically add line breaks.
   * - Escaping the value before output causes those <br> tags to appear as
   *   visible frontend text.
   * - This helper decodes existing entities, lets WordPress normalize paragraphs
   *   and line breaks, then sanitizes the final HTML before Blade prints it.
   */
  $formatCtaBodyText = static function ($value): string {
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
    .sccc-cta-block {
      --sccc-cta-bg-start: rgba(15, 23, 42, 0.96);
      --sccc-cta-bg-end: rgba(17, 24, 39, 0.82);
      --sccc-cta-glow: rgba(47, 120, 255, 0.12);
      --sccc-cta-border: rgba(148, 163, 184, 0.16);
      --sccc-cta-text: #f8fafc;
      --sccc-cta-heading: #ffffff;
      --sccc-cta-muted: #9fb0c8;
      --sccc-cta-accent: #2f78ff;
      --sccc-cta-accent-soft: rgba(47, 120, 255, 0.12);
      --sccc-cta-shadow: 0 18px 50px rgba(2, 8, 23, 0.28);
      --sccc-cta-radius: 1.65rem;
      position: relative;
      width: 100%;
      color: var(--sccc-cta-text);
    }

    .sccc-cta-block.is-theme-cobalt {
      --sccc-cta-bg-start: rgba(15, 23, 42, 0.96);
      --sccc-cta-bg-end: rgba(17, 24, 39, 0.82);
      --sccc-cta-glow: rgba(47, 120, 255, 0.12);
      --sccc-cta-border: rgba(148, 163, 184, 0.16);
      --sccc-cta-text: #f8fafc;
      --sccc-cta-heading: #ffffff;
      --sccc-cta-muted: #9fb0c8;
      --sccc-cta-accent: #2f78ff;
      --sccc-cta-accent-soft: rgba(47, 120, 255, 0.12);
      --sccc-cta-shadow: 0 18px 50px rgba(2, 8, 23, 0.28);
    }

    .sccc-cta-block.is-theme-slate {
      --sccc-cta-bg-start: rgba(24, 28, 38, 0.96);
      --sccc-cta-bg-end: rgba(30, 36, 48, 0.84);
      --sccc-cta-glow: rgba(148, 163, 184, 0.10);
      --sccc-cta-border: rgba(148, 163, 184, 0.18);
      --sccc-cta-text: #f5f7fb;
      --sccc-cta-heading: #ffffff;
      --sccc-cta-muted: #b2bdca;
      --sccc-cta-accent: #94a3b8;
      --sccc-cta-accent-soft: rgba(148, 163, 184, 0.14);
      --sccc-cta-shadow: 0 18px 50px rgba(5, 10, 20, 0.24);
    }

    .sccc-cta-block.is-theme-aurora {
      --sccc-cta-bg-start: rgba(20, 19, 53, 0.96);
      --sccc-cta-bg-end: rgba(12, 32, 63, 0.84);
      --sccc-cta-glow: rgba(88, 184, 255, 0.14);
      --sccc-cta-border: rgba(110, 139, 255, 0.18);
      --sccc-cta-text: #f8f9ff;
      --sccc-cta-heading: #ffffff;
      --sccc-cta-muted: #c0c9eb;
      --sccc-cta-accent: #6e8bff;
      --sccc-cta-accent-soft: rgba(110, 139, 255, 0.14);
      --sccc-cta-shadow: 0 18px 52px rgba(8, 12, 30, 0.32);
    }

    html[data-theme="light"] .sccc-cta-block.is-theme-cobalt {
      --sccc-cta-bg-start: rgba(248, 251, 255, 0.98);
      --sccc-cta-bg-end: rgba(240, 246, 255, 0.94);
      --sccc-cta-glow: rgba(29, 78, 216, 0.08);
      --sccc-cta-border: rgba(15, 23, 42, 0.08);
      --sccc-cta-text: #122033;
      --sccc-cta-heading: #101827;
      --sccc-cta-muted: #5f6e83;
      --sccc-cta-accent: #1d4ed8;
      --sccc-cta-accent-soft: rgba(29, 78, 216, 0.08);
      --sccc-cta-shadow: 0 18px 44px rgba(15, 23, 42, 0.08);
    }

    html[data-theme="light"] .sccc-cta-block.is-theme-slate {
      --sccc-cta-bg-start: rgba(250, 251, 253, 0.98);
      --sccc-cta-bg-end: rgba(242, 244, 247, 0.94);
      --sccc-cta-glow: rgba(100, 116, 139, 0.08);
      --sccc-cta-border: rgba(51, 65, 85, 0.10);
      --sccc-cta-text: #192432;
      --sccc-cta-heading: #111827;
      --sccc-cta-muted: #616e7f;
      --sccc-cta-accent: #475569;
      --sccc-cta-accent-soft: rgba(71, 85, 105, 0.08);
      --sccc-cta-shadow: 0 18px 44px rgba(15, 23, 42, 0.07);
    }

    html[data-theme="light"] .sccc-cta-block.is-theme-aurora {
      --sccc-cta-bg-start: rgba(248, 245, 255, 0.98);
      --sccc-cta-bg-end: rgba(239, 245, 255, 0.94);
      --sccc-cta-glow: rgba(88, 184, 255, 0.10);
      --sccc-cta-border: rgba(99, 102, 241, 0.12);
      --sccc-cta-text: #1a2140;
      --sccc-cta-heading: #171d36;
      --sccc-cta-muted: #636b8a;
      --sccc-cta-accent: #4f46e5;
      --sccc-cta-accent-soft: rgba(79, 70, 229, 0.10);
      --sccc-cta-shadow: 0 18px 44px rgba(37, 44, 97, 0.08);
    }

    .dark .sccc-cta-block.is-theme-cobalt,
    .dark .sccc-cta-block.is-theme-slate,
    .dark .sccc-cta-block.is-theme-aurora,
    html[data-theme="dark"] .sccc-cta-block.is-theme-cobalt,
    html[data-theme="dark"] .sccc-cta-block.is-theme-slate,
    html[data-theme="dark"] .sccc-cta-block.is-theme-aurora {
      color: var(--sccc-cta-text);
    }

    .sccc-cta-block.is-mt-none {
      margin-top: 0;
    }

    .sccc-cta-block.is-mt-sm {
      margin-top: 2rem;
    }

    .sccc-cta-block.is-mt-md {
      margin-top: 3.5rem;
    }

    .sccc-cta-block.is-mt-lg {
      margin-top: 5rem;
    }

    .sccc-cta-block.is-mt-xl {
      margin-top: 7rem;
    }

    .sccc-cta-block.is-mb-none {
      margin-bottom: 0;
    }

    .sccc-cta-block.is-mb-sm {
      margin-bottom: 2rem;
    }

    .sccc-cta-block.is-mb-md {
      margin-bottom: 3.5rem;
    }

    .sccc-cta-block.is-mb-lg {
      margin-bottom: 5rem;
    }

    .sccc-cta-block.is-mb-xl {
      margin-bottom: 7rem;
    }

    .sccc-cta-block__container {
      position: relative;
      max-width: 1320px;
      margin-inline: auto;
      padding-inline: 1rem;
    }

    .sccc-cta-block.alignfull .sccc-cta-block__container {
      max-width: none;
      padding-inline: 0;
    }

    .sccc-cta-block__card {
      --sccc-cta-px: clamp(1.6rem, 2.8vw, 2.6rem);
      --sccc-cta-pt: 2.2rem;
      --sccc-cta-pb: 2.2rem;
      position: relative;
      overflow: hidden;
      border: 1px solid var(--sccc-cta-border);
      border-radius: var(--sccc-cta-radius);
      background:
        radial-gradient(circle at top center, var(--sccc-cta-glow), transparent 42%),
        linear-gradient(
          180deg,
          var(--sccc-cta-bg-start),
          var(--sccc-cta-bg-end)
        );
      box-shadow: var(--sccc-cta-shadow);
      padding: var(--sccc-cta-pt) var(--sccc-cta-px) var(--sccc-cta-pb);
      backdrop-filter: blur(12px);
    }

    .sccc-cta-block.has-background-image .sccc-cta-block__card {
      --sccc-cta-overlay-color: #020617;
      background:
        radial-gradient(circle at top center, color-mix(in oklab, var(--sccc-cta-accent) 20%, transparent), transparent 46%),
        linear-gradient(
          180deg,
          rgba(15, 23, 42, 0.72),
          rgba(2, 8, 23, 0.72)
        );
    }

    .sccc-cta-block.has-background-image.is-overlay-dark .sccc-cta-block__card,
    .sccc-cta-block.has-background-image.is-overlay-blue .sccc-cta-block__card,
    .sccc-cta-block.has-background-image.is-overlay-accent .sccc-cta-block__card {
      --sccc-cta-text: #f8fafc;
      --sccc-cta-heading: #ffffff;
      --sccc-cta-muted: rgba(226, 232, 240, 0.86);
    }

    .sccc-cta-block.has-background-image.is-overlay-dark .sccc-cta-block__card {
      --sccc-cta-overlay-color: #020617;
    }

    .sccc-cta-block.has-background-image.is-overlay-blue .sccc-cta-block__card {
      --sccc-cta-overlay-color: #0b1f44;
    }

    .sccc-cta-block.has-background-image.is-overlay-accent .sccc-cta-block__card {
      --sccc-cta-overlay-color: color-mix(in oklab, var(--sccc-cta-accent) 74%, #020617);
    }

    .sccc-cta-block.has-background-image.is-overlay-light .sccc-cta-block__card {
      --sccc-cta-overlay-color: #f8fafc;
      --sccc-cta-text: #122033;
      --sccc-cta-heading: #101827;
      --sccc-cta-muted: rgba(31, 41, 55, 0.78);
    }

    .sccc-cta-block__background {
      position: absolute;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      background-image: var(--sccc-cta-background-image);
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      transform: scale(1.01);
    }

    .sccc-cta-block__overlay {
      position: absolute;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      background: var(--sccc-cta-overlay-color);
      opacity: var(--sccc-cta-overlay-opacity, 0.58);
    }

    .sccc-cta-block.alignfull .sccc-cta-block__card {
      border-radius: 0;
      border-left: 0;
      border-right: 0;
    }

    .sccc-cta-block__card::before {
      content: "";
      position: absolute;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.05) 50%, transparent 100%);
      opacity: 0.7;
    }

    .sccc-cta-block__card.is-pt-none {
      --sccc-cta-pt: 0;
    }

    .sccc-cta-block__card.is-pt-sm {
      --sccc-cta-pt: 1.5rem;
    }

    .sccc-cta-block__card.is-pt-md {
      --sccc-cta-pt: 2.2rem;
    }

    .sccc-cta-block__card.is-pt-lg {
      --sccc-cta-pt: 3.25rem;
    }

    .sccc-cta-block__card.is-pt-xl {
      --sccc-cta-pt: 4.5rem;
    }

    .sccc-cta-block__card.is-pb-none {
      --sccc-cta-pb: 0;
    }

    .sccc-cta-block__card.is-pb-sm {
      --sccc-cta-pb: 1.5rem;
    }

    .sccc-cta-block__card.is-pb-md {
      --sccc-cta-pb: 2.2rem;
    }

    .sccc-cta-block__card.is-pb-lg {
      --sccc-cta-pb: 3.25rem;
    }

    .sccc-cta-block__card.is-pb-xl {
      --sccc-cta-pb: 4.5rem;
    }

    .sccc-cta-block__inner {
      position: relative;
      z-index: 2;
      display: grid;
      gap: 1rem;
      max-width: 70rem;
    }

    .sccc-cta-block.alignfull .sccc-cta-block__inner {
      max-width: 76rem;
      margin-inline: auto;
    }

    .sccc-cta-block.is-align-center .sccc-cta-block__inner {
      text-align: center;
      justify-items: center;
    }

    .sccc-cta-block.is-align-left .sccc-cta-block__inner {
      text-align: left;
      justify-items: start;
    }

    .sccc-cta-block__eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      width: fit-content;
      margin: 0;
      padding: 0.38rem 0.78rem;
      border-radius: 999px;
      background: color-mix(in oklab, var(--sccc-cta-accent) 12%, transparent);
      color: color-mix(in oklab, var(--sccc-cta-accent) 72%, var(--sccc-cta-heading));
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    .sccc-cta-block__heading {
      margin: 0;
      color: var(--sccc-cta-heading);
      font-size: clamp(2rem, 3.8vw, 3.4rem);
      font-weight: 900;
      line-height: 0.98;
      letter-spacing: -0.03em;
    }

    .sccc-cta-block__body {
      margin: 0;
      max-width: 60ch;
      color: var(--sccc-cta-muted);
      font-size: 1rem;
      line-height: 1.8;
    }

    /*
     * Formatted CTA body reset.
     *
     * Why this exists:
     * - The body can now render safe WordPress-formatted HTML.
     * - WordPress may generate <p> tags through wpautop(), so this keeps the
     *   spacing visually close to the original single-paragraph design while
     *   allowing intentional editor line breaks.
     */
    .sccc-cta-block__body p {
      margin: 0;
    }

    .sccc-cta-block__body p + p {
      margin-top: 0.75rem;
    }

    .sccc-cta-block__actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.85rem;
      margin-top: 0.35rem;
    }

    .sccc-cta-block.is-align-center .sccc-cta-block__actions {
      justify-content: center;
    }

    .sccc-cta-block.is-align-left .sccc-cta-block__actions {
      justify-content: flex-start;
    }

    .sccc-cta-block__button,
    .sccc-cta-block__button:visited {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.65rem;
      min-height: 3rem;
      padding: 0.9rem 1.15rem;
      border-radius: 0.95rem;
      border: 1px solid color-mix(in oklab, var(--sccc-cta-accent) 28%, var(--sccc-cta-border));
      background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.02)),
        color-mix(in oklab, var(--sccc-cta-bg-start) 78%, transparent);
      color: var(--sccc-cta-heading);
      text-decoration: none;
      font-weight: 800;
      box-shadow:
        0 0 0 1px color-mix(in oklab, var(--sccc-cta-accent) 8%, transparent),
        0 0 26px color-mix(in oklab, var(--sccc-cta-accent) 16%, transparent),
        inset 0 1px 0 rgba(255, 255, 255, 0.12);
      transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        border-color 0.2s ease,
        color 0.2s ease,
        background-color 0.2s ease;
      backdrop-filter: blur(10px);
    }

    .sccc-cta-block__button:hover,
    .sccc-cta-block__button:focus-visible {
      transform: translateY(-1px);
      border-color: color-mix(in oklab, var(--sccc-cta-accent) 44%, var(--sccc-cta-border));
      color: var(--sccc-cta-heading);
      text-decoration: none;
      box-shadow:
        0 0 0 1px color-mix(in oklab, var(--sccc-cta-accent) 12%, transparent),
        0 0 34px color-mix(in oklab, var(--sccc-cta-accent) 24%, transparent),
        inset 0 1px 0 rgba(255, 255, 255, 0.16);
      outline: none;
    }

    .sccc-cta-block__button--primary {
      box-shadow:
        0 0 0 1px color-mix(in oklab, var(--sccc-cta-accent) 14%, transparent),
        0 0 38px color-mix(in oklab, var(--sccc-cta-accent) 28%, transparent),
        inset 0 1px 0 rgba(255, 255, 255, 0.16);
    }

    @media (max-width: 767px) {
      .sccc-cta-block.is-mt-sm {
        margin-top: 1.5rem;
      }

      .sccc-cta-block.is-mt-md {
        margin-top: 2.5rem;
      }

      .sccc-cta-block.is-mt-lg {
        margin-top: 3.5rem;
      }

      .sccc-cta-block.is-mt-xl {
        margin-top: 5rem;
      }

      .sccc-cta-block.is-mb-sm {
        margin-bottom: 1.5rem;
      }

      .sccc-cta-block.is-mb-md {
        margin-bottom: 2.5rem;
      }

      .sccc-cta-block.is-mb-lg {
        margin-bottom: 3.5rem;
      }

      .sccc-cta-block.is-mb-xl {
        margin-bottom: 5rem;
      }

      .sccc-cta-block__card {
        --sccc-cta-px: 1.4rem;
      }

      .sccc-cta-block__card.is-pt-lg {
        --sccc-cta-pt: 2.6rem;
      }

      .sccc-cta-block__card.is-pt-xl {
        --sccc-cta-pt: 3.4rem;
      }

      .sccc-cta-block__card.is-pb-lg {
        --sccc-cta-pb: 2.6rem;
      }

      .sccc-cta-block__card.is-pb-xl {
        --sccc-cta-pb: 3.4rem;
      }

      .sccc-cta-block__heading {
        font-size: clamp(1.8rem, 8vw, 2.4rem);
      }

      .sccc-cta-block__actions {
        width: 100%;
      }

      .sccc-cta-block__button,
      .sccc-cta-block__button:visited {
        width: 100%;
      }
    }
  </style>
@endonce

<section {{ $attributes->class($blockClasses) }}>
  <div class="sccc-cta-block__container">
    <div @class($cardClasses) @if ($cardStyle !== '') style="{{ $cardStyle }}" @endif>
      @if ($hasBackgroundImage)
        <div class="sccc-cta-block__background" aria-hidden="true"></div>
        <div class="sccc-cta-block__overlay" aria-hidden="true"></div>
      @endif

      <div class="sccc-cta-block__inner">
        @if (!empty($eyebrow))
          <p class="sccc-cta-block__eyebrow">{{ $eyebrow }}</p>
        @endif

        @if (!empty($heading))
          <h2 class="sccc-cta-block__heading">{{ $heading }}</h2>
        @endif

        @if (!empty($body))
          <div class="sccc-cta-block__body">{!! $formatCtaBodyText($body) !!}</div>
        @endif

        @if ($hasPrimary || $hasSecondary)
          <div class="sccc-cta-block__actions">
            @if ($hasPrimary)
              <a
                class="sccc-cta-block__button sccc-cta-block__button--primary"
                href="{{ esc_url($primaryButton['url']) }}"
              >
                <span>{{ $primaryButton['label'] }}</span>
              </a>
            @endif

            @if ($hasSecondary)
              <a
                class="sccc-cta-block__button sccc-cta-block__button--secondary"
                href="{{ esc_url($secondaryButton['url']) }}"
              >
                <span>{{ $secondaryButton['label'] }}</span>
              </a>
            @endif
          </div>
        @endif
      </div>
    </div>
  </div>
</section>