{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/blocks/sponsor-showcase.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the Sponsor Showcase block as either a static rail or a moving
|   scroller.
| - Keep the outer section full width so every background treatment spans the
|   section consistently.
| - Bind the heading, description, sponsor rail, and CTA to the theme's
|   Tailwind container so the block flows with the overall content layout.
| - Give higher tiers more visual weight through larger logo slots.
| - Optionally show the sponsor company name underneath each logo.
| - Optionally show a bottom CTA with lead-in copy and one link button.
| - Keep sponsor eligibility strict: published posts, active sponsor status,
|   and at least one logo source from the Sponsor CPT.
|
| Why this file exists:
| - This block is rendered through Log1x ACF Composer.
| - In this stack, the Blade view receives the block instance as `$block`
|   and a prepared attribute bag as `$attributes`.
| - That means anchor/id/class handling must come from `$attributes`, not from
|   `$block['anchor']` / `$block['className']` array access that belongs to
|   plain ACF PHP templates.
|
| Notes:
| - Theme-specific sponsor logo fields are preferred when available.
| - The Sponsor featured image is used as the fallback logo.
| - The scroller pauses on hover instead of using a visible pause button.
| - The bottom CTA button text comes from the ACF Link field title.
| - Background treatment is controlled by the block Composer fields.
| - Header alignment is controlled by the block Composer fields and mapped to
|   scoped classes so only this block's heading/intro copy is affected.
--}}

@php
  /**
   * Why this top section is intentionally small:
   * - The earlier crash came from treating `$block` like an array.
   * - With ACF Composer, wrapper id/class/style already live in `$attributes`.
   * - So we only compute view-specific flags and safe CSS classes here.
   */
  $baseClass = 'sccc-sponsor-showcase';
  $layoutClass = $layout === 'scroller' ? 'is-scroller' : 'is-static';
  $hasScrollerTrack = $layout === 'scroller' && count($track) > 1;
  $hasBottomCta = !empty($showBottomCta) && (!empty($bottomCtaLeadIn) || !empty($bottomCtaLink['url']));
  $headerAlign = in_array(($headerAlign ?? 'left'), ['left', 'center', 'right'], true) ? $headerAlign : 'left';

  /**
   * Format sponsor showcase body copy safely for frontend display.
   *
   * Why this exists:
   * - ACF Text Area fields may already return <br> tags when new_lines is set
   *   to br in the Composer field.
   * - Escaping that value before output makes <br /> appear as visible text.
   * - This helper lets WordPress normalize paragraphs/line breaks, then
   *   sanitizes the final HTML before Blade prints it.
   */
  $formatSponsorShowcaseText = static function ($value): string {
    $value = trim((string) ($value ?? ''));

    if ($value === '') {
      return '';
    }

    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return wp_kses_post(wpautop($value));
  };

  /**
   * Render the correct sponsor logo markup for theme-aware display.
   *
   * Why this exists:
   * - The block now receives the same resolved logo set as the single Sponsor
   *   template: light logo, dark logo, and featured-image fallback.
   * - If both display variants are available, CSS shows the right one based on
   *   html[data-theme].
   * - Duplicate marquee tracks are decorative, so their image alt text is blank.
   */
  $renderSponsorShowcaseLogo = static function (array $sponsor, bool $decorative = false): string {
    $alt = $decorative ? '' : (string) ($sponsor['logo_alt'] ?? $sponsor['title'] ?? '');

    if (!empty($sponsor['has_dual_logos']) && !empty($sponsor['logo_light_id']) && !empty($sponsor['logo_dark_id'])) {
      $lightAlt = $decorative ? '' : (string) ($sponsor['logo_light_alt'] ?? $alt);
      $darkAlt = $decorative ? '' : (string) ($sponsor['logo_dark_alt'] ?? $alt);

      return (wp_get_attachment_image(
        (int) $sponsor['logo_light_id'],
        'full',
        false,
        [
          'class' => 'sccc-sponsor-showcase__logo-image sccc-sponsor-showcase__logo-image--light',
          'loading' => 'lazy',
          'alt' => $lightAlt,
        ]
      ) ?: '') . (wp_get_attachment_image(
        (int) $sponsor['logo_dark_id'],
        'full',
        false,
        [
          'class' => 'sccc-sponsor-showcase__logo-image sccc-sponsor-showcase__logo-image--dark',
          'loading' => 'lazy',
          'alt' => $darkAlt,
        ]
      ) ?: '');
    }

    if (!empty($sponsor['logo_id'])) {
      return wp_get_attachment_image(
        (int) $sponsor['logo_id'],
        'full',
        false,
        [
          'class' => 'sccc-sponsor-showcase__logo-image sccc-sponsor-showcase__logo-image--single',
          'loading' => 'lazy',
          'alt' => $alt,
        ]
      ) ?: '';
    }

    return '';
  };

  /**
   * These class suffixes are validated in app/Blocks/SponsorShowcase.php.
   * Keeping the naming predictable makes the CSS easy to scan and avoids
   * creating a new generated utility dependency for this small block.
   */
  $styleClasses = [
    'has-background-'.($backgroundTreatment ?? 'none'),
    'has-spacing-'.($verticalSpacing ?? 'normal'),
    'has-tile-style-'.($tileStyle ?? 'glass_neon'),
    'has-header-align-'.$headerAlign,
  ];
@endphp

@once
  <style>
    .sccc-sponsor-showcase {
      --sccc-sponsor-gap: 0.85rem;
      --sccc-sponsor-line: var(--color-line, rgba(148, 163, 184, 0.2));
      --sccc-sponsor-surface: color-mix(in oklab, var(--color-surface, #ffffff) 90%, transparent);
      --sccc-sponsor-surface-strong: color-mix(in oklab, var(--color-surface, #ffffff) 97%, transparent);
      --sccc-sponsor-text: var(--color-text, inherit);
      --sccc-sponsor-muted: var(--color-muted, rgba(100, 116, 139, 1));
      --sccc-sponsor-blue: var(--color-primary-500, #2979ff);
      --sccc-sponsor-red: var(--color-secondary-500, var(--color-accent-500, #ff1744));
      --sccc-sponsor-accent: var(--color-accent-500, var(--sccc-sponsor-blue));
      --sccc-sponsor-gradient: var(
        --sccc-header-underline-gradient,
        linear-gradient(
          90deg,
          rgba(113, 215, 255, 0) 0%,
          rgba(113, 215, 255, 0.96) 18%,
          rgba(67, 190, 255, 0.98) 48%,
          rgba(255, 23, 68, 0.92) 82%,
          rgba(255, 23, 68, 0) 100%
        )
      );
      /*
       * Full-bleed behavior:
       * - The section itself always spans the viewport.
       * - This keeps background treatments consistent even if the block is
       *   dropped inside a constrained Gutenberg/container layout.
       * - The inner wrapper below handles the actual Tailwind container width.
       */
      position: relative;
      left: 50%;
      width: 100vw;
      max-width: 100vw;
      margin-left: -50vw;
      margin-right: -50vw;
      overflow: hidden;
      color: var(--sccc-sponsor-text);
      background: transparent;
    }

    /**
     * Spacing belongs on the outer section so the background treatment has
     * room to breathe when the block is used standalone.
     */
    .sccc-sponsor-showcase.has-spacing-none {
      padding-block: 0;
    }

    .sccc-sponsor-showcase.has-spacing-compact {
      padding-block: clamp(1rem, 2vw, 1.75rem);
    }

    .sccc-sponsor-showcase.has-spacing-normal {
      padding-block: clamp(1.5rem, 3vw, 2.75rem);
    }

    .sccc-sponsor-showcase.has-spacing-generous {
      padding-block: clamp(2.5rem, 5vw, 4.75rem);
    }

    /**
     * Background treatments intentionally live on the full-width section.
     * This lets every visual band span edge-to-edge while the inner content
     * remains aligned to the site's Tailwind container.
     */
    .sccc-sponsor-showcase.has-background-none {
      background: transparent;
    }

    .sccc-sponsor-showcase.has-background-subtle_glow {
      background:
        radial-gradient(760px 320px at 12% 0%, color-mix(in oklab, var(--sccc-sponsor-blue) 16%, transparent), transparent 62%),
        radial-gradient(720px 320px at 88% 100%, color-mix(in oklab, var(--sccc-sponsor-red) 14%, transparent), transparent 64%);
    }

    .sccc-sponsor-showcase.has-background-neon_band {
      background:
        linear-gradient(120deg, color-mix(in oklab, var(--sccc-sponsor-blue) 13%, transparent), transparent 38%),
        linear-gradient(300deg, color-mix(in oklab, var(--sccc-sponsor-red) 12%, transparent), transparent 44%),
        color-mix(in oklab, var(--color-surface, #ffffff) 45%, transparent);
      border-block: 1px solid color-mix(in oklab, var(--sccc-sponsor-blue) 22%, var(--sccc-sponsor-line));
    }

    .sccc-sponsor-showcase.has-background-glass_panel {
      background:
        radial-gradient(760px 320px at 12% 0%, color-mix(in oklab, var(--sccc-sponsor-blue) 13%, transparent), transparent 62%),
        radial-gradient(720px 320px at 88% 100%, color-mix(in oklab, var(--sccc-sponsor-red) 11%, transparent), transparent 64%),
        color-mix(in oklab, var(--color-surface, #ffffff) 72%, transparent);
      border-block: 1px solid color-mix(in oklab, var(--sccc-sponsor-blue) 16%, var(--sccc-sponsor-line));
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(12px);
    }

    .sccc-sponsor-showcase.has-background-glass_panel::before {
      content: "";
      position: absolute;
      inset: 0;
      background: var(--sccc-sponsor-gradient);
      opacity: 0.18;
      pointer-events: none;
      mask-image: linear-gradient(to bottom, black 0, transparent 70%);
      -webkit-mask-image: linear-gradient(to bottom, black 0, transparent 70%);
    }

    /**
     * The inner wrapper is intentionally handled with Tailwind classes in the
     * markup: container + mx-auto + responsive horizontal padding.
     *
     * This keeps the section full width while the content follows the same
     * alignment rhythm as the rest of the theme.
     */
    .sccc-sponsor-showcase__inner {
      position: relative;
      z-index: 1;
    }

    .sccc-sponsor-showcase__header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .sccc-sponsor-showcase__copy {
      min-width: 0;
    }

    /**
     * Header alignment variants.
     *
     * Why this exists:
     * - Editors can align the heading and intro copy without affecting the
     *   sponsor logo rail, scroller behavior, or bottom CTA layout.
     * - The classes are controlled by SponsorShowcase.php, so only known values
     *   can reach the frontend.
     */
    .sccc-sponsor-showcase.has-header-align-left .sccc-sponsor-showcase__header {
      justify-content: flex-start;
      text-align: left;
    }

    .sccc-sponsor-showcase.has-header-align-center .sccc-sponsor-showcase__header {
      justify-content: center;
      text-align: center;
    }

    .sccc-sponsor-showcase.has-header-align-right .sccc-sponsor-showcase__header {
      justify-content: flex-end;
      text-align: right;
    }

    .sccc-sponsor-showcase.has-header-align-center .sccc-sponsor-showcase__copy {
      margin-inline: auto;
    }

    .sccc-sponsor-showcase.has-header-align-right .sccc-sponsor-showcase__copy {
      margin-left: auto;
    }

    .sccc-sponsor-showcase__heading {
      margin: 0;
      color: var(--sccc-sponsor-text);
      font-family: var(--font-display, inherit);
      font-size: clamp(1.15rem, 1.15rem + 0.45vw, 1.65rem);
      line-height: 1.1;
      letter-spacing: 0.01em;
      text-wrap: balance;
    }

    .sccc-sponsor-showcase__intro {
      margin: 0.45rem 0 0;
      max-width: 64ch;
      color: var(--sccc-sponsor-muted);
      font-size: 0.95rem;
      line-height: 1.6;
    }

    .sccc-sponsor-showcase.has-header-align-center .sccc-sponsor-showcase__intro {
      margin-inline: auto;
    }

    .sccc-sponsor-showcase.has-header-align-right .sccc-sponsor-showcase__intro {
      margin-left: auto;
      margin-right: 0;
    }

    /**
     * Formatted copy reset.
     *
     * Why this exists:
     * - The intro and bottom CTA copy are now allowed to render safe formatted
     *   HTML from WordPress, including generated paragraph tags.
     * - These rules preserve the original compact spacing while allowing
     *   intentional editor line breaks.
     */
    .sccc-sponsor-showcase__intro p,
    .sccc-sponsor-showcase__cta-copy p {
      margin: 0;
    }

    .sccc-sponsor-showcase__intro p + p,
    .sccc-sponsor-showcase__cta-copy p + p {
      margin-top: 0.65rem;
    }

    .sccc-sponsor-showcase__static-list,
    .sccc-sponsor-showcase__track {
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .sccc-sponsor-showcase__static-list {
      display: flex;
      flex-wrap: wrap;
      align-items: stretch;
      justify-content: center;
      gap: var(--sccc-sponsor-gap);
    }

    .sccc-sponsor-showcase__marquee {
      overflow: hidden;
      mask-image: linear-gradient(to right, transparent 0, black 6%, black 94%, transparent 100%);
      -webkit-mask-image: linear-gradient(to right, transparent 0, black 6%, black 94%, transparent 100%);
    }

    .sccc-sponsor-showcase__marquee-inner {
      display: flex;
      align-items: stretch;
      gap: var(--sccc-sponsor-gap);
      width: max-content;
      will-change: transform;
      animation: scccSponsorMarquee var(--sccc-sponsor-duration, 42s) linear infinite;
    }

    @media (hover: hover) {
      .sccc-sponsor-showcase__marquee:hover .sccc-sponsor-showcase__marquee-inner {
        animation-play-state: paused;
      }
    }

    .sccc-sponsor-showcase__track {
      display: flex;
      align-items: stretch;
      gap: var(--sccc-sponsor-gap);
      flex-wrap: nowrap;
    }

    .sccc-sponsor-showcase__item {
      --sccc-sponsor-logo-height: 4.25rem;
      --sccc-sponsor-item-width: 13.5rem;
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.6rem;
      min-width: var(--sccc-sponsor-item-width);
      padding: 1rem 1.15rem;
      border-radius: 1.15rem;
      border: 1px solid var(--sccc-sponsor-line);
      background: color-mix(in oklab, var(--color-surface, #ffffff) 94%, transparent);
      text-decoration: none;
      color: inherit;
      isolation: isolate;
      transition:
        transform 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        background-color 0.2s ease;
    }

    .sccc-sponsor-showcase__item.has-company-name {
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 0.65rem;
      text-align: center;
      min-height: 9.25rem;
      padding: 1.15rem 1.15rem 1rem;
    }

    /**
     * Glass Neon is the default SCCC treatment. It moves the visual energy into
     * the logo tiles instead of making the whole block a heavy rounded card.
     */
    .sccc-sponsor-showcase.has-tile-style-glass_neon .sccc-sponsor-showcase__item {
      border-color: color-mix(in oklab, var(--sccc-sponsor-blue) 22%, var(--sccc-sponsor-line));
      background:
        linear-gradient(135deg, color-mix(in oklab, var(--sccc-sponsor-blue) 9%, transparent), transparent 48%),
        linear-gradient(315deg, color-mix(in oklab, var(--sccc-sponsor-red) 8%, transparent), transparent 52%),
        color-mix(in oklab, var(--color-surface, #ffffff) 92%, transparent);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
    }

    .sccc-sponsor-showcase.has-tile-style-glass_neon .sccc-sponsor-showcase__item::before {
      content: "";
      position: absolute;
      inset: -1px;
      border-radius: inherit;
      padding: 1px;
      background: var(--sccc-sponsor-gradient);
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.2s ease;
      mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
      mask-composite: exclude;
      -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
      -webkit-mask-composite: xor;
      z-index: -1;
    }

    .sccc-sponsor-showcase.has-tile-style-minimal .sccc-sponsor-showcase__item {
      background: color-mix(in oklab, var(--color-surface, #ffffff) 70%, transparent);
      box-shadow: none;
    }

    .sccc-sponsor-showcase__item:hover,
    .sccc-sponsor-showcase__item:focus-visible {
      text-decoration: none;
      color: inherit;
      transform: translateY(-1px);
      border-color: color-mix(in oklab, var(--sccc-sponsor-accent) 46%, var(--sccc-sponsor-line));
      box-shadow:
        0 14px 30px color-mix(in oklab, var(--sccc-sponsor-blue) 13%, transparent),
        0 0 0 3px color-mix(in oklab, var(--sccc-sponsor-red) 10%, transparent);
      outline: none;
    }

    .sccc-sponsor-showcase.has-tile-style-glass_neon .sccc-sponsor-showcase__item:hover::before,
    .sccc-sponsor-showcase.has-tile-style-glass_neon .sccc-sponsor-showcase__item:focus-visible::before {
      opacity: 0.78;
    }

    .sccc-sponsor-showcase__item.is-tier-platinum {
      --sccc-sponsor-logo-height: 5.15rem;
      --sccc-sponsor-item-width: 17rem;
    }

    .sccc-sponsor-showcase__item.is-tier-gold {
      --sccc-sponsor-logo-height: 4.85rem;
      --sccc-sponsor-item-width: 16rem;
    }

    .sccc-sponsor-showcase__item.is-tier-silver {
      --sccc-sponsor-logo-height: 4.55rem;
      --sccc-sponsor-item-width: 15.25rem;
    }

    .sccc-sponsor-showcase__item.is-tier-bronze {
      --sccc-sponsor-logo-height: 4.25rem;
      --sccc-sponsor-item-width: 14.5rem;
    }

    .sccc-sponsor-showcase__item.is-tier-supporter {
      --sccc-sponsor-logo-height: 4rem;
      --sccc-sponsor-item-width: 14rem;
    }

    .sccc-sponsor-showcase__logo {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 0;
      width: 100%;
    }

    .sccc-sponsor-showcase__logo img {
      display: block;
      width: auto;
      max-width: 100%;
      height: var(--sccc-sponsor-logo-height);
      object-fit: contain;
    }

    .sccc-sponsor-showcase__logo-image--light {
      display: block;
    }

    .sccc-sponsor-showcase__logo-image--dark {
      display: none;
    }

    html[data-theme="light"] .sccc-sponsor-showcase__logo-image--light {
      display: block;
    }

    html[data-theme="light"] .sccc-sponsor-showcase__logo-image--dark {
      display: none;
    }

    html[data-theme="dark"] .sccc-sponsor-showcase__logo-image--dark,
    .dark .sccc-sponsor-showcase__logo-image--dark {
      display: block;
    }

    html[data-theme="dark"] .sccc-sponsor-showcase__logo-image--light,
    .dark .sccc-sponsor-showcase__logo-image--light {
      display: none;
    }

    .sccc-sponsor-showcase__logo-image--single {
      display: block;
    }

    .sccc-sponsor-showcase__name {
      display: block;
      max-width: 100%;
      font-size: 0.84rem;
      line-height: 1.35;
      font-weight: 700;
      color: var(--sccc-sponsor-muted);
      text-wrap: balance;
    }

    .sccc-sponsor-showcase__empty {
      display: grid;
      place-items: center;
      min-height: 7rem;
      border-radius: 1rem;
      border: 1px dashed var(--sccc-sponsor-line);
      color: var(--sccc-sponsor-muted);
      text-align: center;
      padding: 1rem;
      background: color-mix(in oklab, var(--color-surface, #ffffff) 42%, transparent);
    }

    .sccc-sponsor-showcase__cta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-top: clamp(1rem, 2vw, 1.4rem);
      padding-top: clamp(1rem, 2vw, 1.35rem);
      border-top: 1px solid var(--sccc-sponsor-line);
    }

    .sccc-sponsor-showcase__cta-copy {
      min-width: 0;
      max-width: 68ch;
      color: var(--sccc-sponsor-muted);
      font-size: 0.95rem;
      line-height: 1.6;
    }

    .sccc-sponsor-showcase__cta-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: 0 0 auto;
      min-height: 2.75rem;
      padding: 0.8rem 1.18rem;
      border-radius: 999px;
      border: 1px solid color-mix(in oklab, var(--sccc-sponsor-blue) 55%, var(--sccc-sponsor-line));
      background:
        linear-gradient(135deg, color-mix(in oklab, var(--sccc-sponsor-blue) 16%, transparent), transparent 52%),
        linear-gradient(315deg, color-mix(in oklab, var(--sccc-sponsor-red) 14%, transparent), transparent 58%),
        var(--sccc-sponsor-surface-strong);
      color: var(--sccc-sponsor-text);
      font-size: 0.9rem;
      font-weight: 800;
      line-height: 1;
      text-decoration: none;
      white-space: nowrap;
      box-shadow: 0 12px 24px color-mix(in oklab, var(--sccc-sponsor-blue) 14%, transparent);
      transition:
        transform 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        background-color 0.2s ease;
    }

    .sccc-sponsor-showcase__cta-button:hover,
    .sccc-sponsor-showcase__cta-button:focus-visible {
      color: var(--sccc-sponsor-text);
      text-decoration: none;
      transform: translateY(-1px);
      border-color: color-mix(in oklab, var(--sccc-sponsor-red) 60%, var(--sccc-sponsor-blue));
      box-shadow:
        0 16px 30px color-mix(in oklab, var(--sccc-sponsor-blue) 22%, transparent),
        0 0 0 3px color-mix(in oklab, var(--sccc-sponsor-red) 15%, transparent);
      outline: none;
    }

    @keyframes scccSponsorMarquee {
      from {
        transform: translateX(0);
      }
      to {
        transform: translateX(calc(-50% - (var(--sccc-sponsor-gap) / 2)));
      }
    }

    @media (max-width: 767px) {
      .sccc-sponsor-showcase__inner {
        padding-inline: 1rem;
      }

      .sccc-sponsor-showcase__header {
        flex-direction: column;
      }

      .sccc-sponsor-showcase__item {
        --sccc-sponsor-logo-height: 3.75rem;
        --sccc-sponsor-item-width: 12.75rem;
      }

      .sccc-sponsor-showcase__item.is-tier-platinum {
        --sccc-sponsor-logo-height: 4.35rem;
        --sccc-sponsor-item-width: 14.25rem;
      }

      .sccc-sponsor-showcase__item.is-tier-gold {
        --sccc-sponsor-logo-height: 4.15rem;
        --sccc-sponsor-item-width: 13.75rem;
      }

      .sccc-sponsor-showcase__item.is-tier-silver {
        --sccc-sponsor-logo-height: 3.95rem;
        --sccc-sponsor-item-width: 13.25rem;
      }

      .sccc-sponsor-showcase__item.is-tier-bronze {
        --sccc-sponsor-logo-height: 3.8rem;
        --sccc-sponsor-item-width: 12.9rem;
      }

      .sccc-sponsor-showcase__item.is-tier-supporter {
        --sccc-sponsor-logo-height: 3.65rem;
        --sccc-sponsor-item-width: 12.5rem;
      }

      .sccc-sponsor-showcase__item.has-company-name {
        min-height: 8.65rem;
      }

      .sccc-sponsor-showcase__cta {
        align-items: stretch;
        flex-direction: column;
      }

      .sccc-sponsor-showcase__cta-button {
        width: 100%;
        white-space: normal;
        text-align: center;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .sccc-sponsor-showcase__marquee-inner {
        animation: none;
      }

      .sccc-sponsor-showcase__item,
      .sccc-sponsor-showcase__cta-button {
        transition: none;
      }
    }
  </style>
@endonce

<section
  {{ $attributes->class(array_merge([$baseClass, $layoutClass], $styleClasses)) }}
  data-sponsor-showcase
  data-layout="{{ esc_attr($layout) }}"
  @if($hasScrollerTrack)
    style="--sccc-sponsor-duration: {{ (int) $marqueeDuration }}s;"
  @endif
>
  <div class="sccc-sponsor-showcase__inner container mx-auto px-4 md:px-6">
    @if (!empty($heading) || !empty($introText))
      <div class="sccc-sponsor-showcase__header">
        <div class="sccc-sponsor-showcase__copy">
          @if (!empty($heading))
            <h2 class="sccc-sponsor-showcase__heading">{{ $heading }}</h2>
          @endif

          @if (!empty($introText))
            <div class="sccc-sponsor-showcase__intro">{!! $formatSponsorShowcaseText($introText) !!}</div>
          @endif
        </div>
      </div>
    @endif

    @if (!empty($sponsors))
      @if ($hasScrollerTrack)
        <div class="sccc-sponsor-showcase__marquee" aria-label="Sponsor logos">
          <div class="sccc-sponsor-showcase__marquee-inner">
            <ul class="sccc-sponsor-showcase__track" role="list">
              @foreach ($track as $sponsor)
                @php
                  $itemClass = 'sccc-sponsor-showcase__item is-tier-'.($sponsor['tier_slug'] ?? 'supporter').($showCompanyNames ? ' has-company-name' : '');
                @endphp

                <li>
                  @if (!empty($sponsor['link_url']))
                    <a
                      class="{{ esc_attr($itemClass) }}"
                      href="{{ esc_url($sponsor['link_url']) }}"
                      target="_self"
                      aria-label="{{ esc_attr($sponsor['title']) }}"
                    >
                      <span class="sccc-sponsor-showcase__logo">
                        {!! $renderSponsorShowcaseLogo($sponsor) !!}
                      </span>

                      @if ($showCompanyNames)
                        <span class="sccc-sponsor-showcase__name">{{ $sponsor['title'] }}</span>
                      @endif
                    </a>
                  @else
                    <div class="{{ esc_attr($itemClass) }}" aria-label="{{ esc_attr($sponsor['title']) }}">
                      <span class="sccc-sponsor-showcase__logo">
                        {!! $renderSponsorShowcaseLogo($sponsor) !!}
                      </span>

                      @if ($showCompanyNames)
                        <span class="sccc-sponsor-showcase__name">{{ $sponsor['title'] }}</span>
                      @endif
                    </div>
                  @endif
                </li>
              @endforeach
            </ul>

            <ul class="sccc-sponsor-showcase__track" role="list" aria-hidden="true">
              @foreach ($track as $sponsor)
                @php
                  $itemClass = 'sccc-sponsor-showcase__item is-tier-'.($sponsor['tier_slug'] ?? 'supporter').($showCompanyNames ? ' has-company-name' : '');
                @endphp

                <li>
                  @if (!empty($sponsor['link_url']))
                    <a
                      class="{{ esc_attr($itemClass) }}"
                      href="{{ esc_url($sponsor['link_url']) }}"
                      target="_self"
                      tabindex="-1"
                    >
                      <span class="sccc-sponsor-showcase__logo">
                        {!! $renderSponsorShowcaseLogo($sponsor, true) !!}
                      </span>

                      @if ($showCompanyNames)
                        <span class="sccc-sponsor-showcase__name">{{ $sponsor['title'] }}</span>
                      @endif
                    </a>
                  @else
                    <div class="{{ esc_attr($itemClass) }}">
                      <span class="sccc-sponsor-showcase__logo">
                        {!! $renderSponsorShowcaseLogo($sponsor, true) !!}
                      </span>

                      @if ($showCompanyNames)
                        <span class="sccc-sponsor-showcase__name">{{ $sponsor['title'] }}</span>
                      @endif
                    </div>
                  @endif
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      @else
        <ul class="sccc-sponsor-showcase__static-list" role="list" aria-label="Sponsor logos">
          @foreach ($sponsors as $sponsor)
            @php
              $itemClass = 'sccc-sponsor-showcase__item is-tier-'.($sponsor['tier_slug'] ?? 'supporter').($showCompanyNames ? ' has-company-name' : '');
            @endphp

            <li>
              @if (!empty($sponsor['link_url']))
                <a
                  class="{{ esc_attr($itemClass) }}"
                  href="{{ esc_url($sponsor['link_url']) }}"
                  target="_self"
                  aria-label="{{ esc_attr($sponsor['title']) }}"
                >
                  <span class="sccc-sponsor-showcase__logo">
                    {!! $renderSponsorShowcaseLogo($sponsor) !!}
                  </span>

                  @if ($showCompanyNames)
                    <span class="sccc-sponsor-showcase__name">{{ $sponsor['title'] }}</span>
                  @endif
                </a>
              @else
                <div class="{{ esc_attr($itemClass) }}" aria-label="{{ esc_attr($sponsor['title']) }}">
                  <span class="sccc-sponsor-showcase__logo">
                    {!! $renderSponsorShowcaseLogo($sponsor) !!}
                  </span>

                  @if ($showCompanyNames)
                    <span class="sccc-sponsor-showcase__name">{{ $sponsor['title'] }}</span>
                  @endif
                </div>
              @endif
            </li>
          @endforeach
        </ul>
      @endif
    @else
      <div class="sccc-sponsor-showcase__empty">
        <div>
          <strong>No active sponsors found.</strong><br>
          Sponsors must be published, marked active, and have a sponsor logo or featured image fallback.
        </div>
      </div>
    @endif

    @if ($hasBottomCta)
      <div class="sccc-sponsor-showcase__cta">
        @if (!empty($bottomCtaLeadIn))
          <div class="sccc-sponsor-showcase__cta-copy">
            {!! $formatSponsorShowcaseText($bottomCtaLeadIn) !!}
          </div>
        @endif

        @if (!empty($bottomCtaLink['url']))
          <a
            class="sccc-sponsor-showcase__cta-button"
            href="{{ esc_url($bottomCtaLink['url']) }}"
            target="{{ esc_attr($bottomCtaLink['target'] ?? '_self') }}"
            @if (!empty($bottomCtaLink['rel']))
              rel="{{ esc_attr($bottomCtaLink['rel']) }}"
            @endif
          >
            {{ $bottomCtaLink['title'] ?? __('Become a Sponsor', 'sccc') }}
          </a>
        @endif
      </div>
    @endif
  </div>
</section>