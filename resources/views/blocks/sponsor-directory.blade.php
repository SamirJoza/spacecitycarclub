{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/blocks/sponsor-directory.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the Sponsor Directory block.
| - Present sponsors in different tier-based layouts inspired by the provided
|   all-sponsors HTML reference.
|
| Why this file exists:
| - The user wants a reusable sponsor-grouping section that can be dropped onto
|   any page without forcing a dedicated page template.
| - The layout varies by tier:
|   - Platinum = larger feature cards
|   - Gold = medium card grid
|   - Silver = compact logo wall
|   - Bronze / Supporter = simpler linked list treatment
|
| Notes:
| - This is an ACF Composer block view, so wrapper classes should come from
|   `$attributes`.
| - Theme-aware light/dark logo handling matches the current sponsor single
|   approach, with the featured image acting as a fallback asset source.
| - The background treatment lives on the full-width section itself instead of
|   the inner container so the visual treatment spans edge to edge.
| - The inner container handles max-width and content alignment.
| - Top and bottom section spacing are controlled by editor-facing block fields.
| - This version tightens the Bronze list spacing and explicitly top-aligns the
|   Silver/Bronze split cards so the Bronze heading starts at the top like the
|   Silver section.
| - This version fixes visible encoded entities such as "&amp;" and "&hellip;"
|   by fully decoding HTML entities first, then escaping output once in the
|   correct context.
| - This version increases Gold and Silver logo display sizes so lower-tier
|   sponsor logos remain readable while preserving the tier hierarchy.
| - This version adds Sponsor Since badges to Gold, Silver, Bronze, and Supporter
|   tiers so the year is not limited to Platinum sponsor cards.
|--------------------------------------------------------------------------
--}}

@php
  $baseClass = 'sccc-sponsor-directory';

  $platinumGroup = $groups['platinum'] ?? null;
  $goldGroup = $groups['gold'] ?? null;
  $silverGroup = $groups['silver'] ?? null;
  $bronzeGroup = $groups['bronze'] ?? null;
  $supporterGroup = $groups['supporter'] ?? null;

  $hasSilver = !empty($silverGroup);
  $hasBronze = !empty($bronzeGroup);

  $sectionClasses = [
    $baseClass,
    'is-pt-' . ($topPadding ?? 'md'),
    'is-pb-' . ($bottomPadding ?? 'md'),
  ];

  /*
  |--------------------------------------------------------------------------
  | Safe text helpers
  |--------------------------------------------------------------------------
  | Why these helpers exist:
  | - Some WordPress/ACF values may already contain encoded entities such as
  |   "&amp;" or "&hellip;" before they reach this Blade view.
  | - Blade's {{ }} output escapes values automatically, which is good for
  |   safety, but can make already-encoded entities appear visibly on the page.
  | - We decode HTML entities first, then escape once for the final output
  |   context.
  |
  | Why html_entity_decode() is used:
  | - wp_specialchars_decode() handles only the core special characters.
  | - html_entity_decode() also handles named entities such as "&hellip;".
  |
  | Why decoding runs more than once:
  | - Some values can arrive double-encoded, for example "&amp;hellip;".
  | - Running a short, capped decode loop normalizes those safely without
  |   creating an endless loop.
  |
  | Important:
  | - Visible text still uses Blade's escaped {{ }} syntax.
  | - Attribute values escaped with WordPress helpers are printed with {!! !!}
  |   because those helpers already return escaped strings.
  |--------------------------------------------------------------------------
  */
  $decodeText = static function ($value): string {
    if ($value === null || (! is_scalar($value))) {
      return '';
    }

    $text = (string) $value;
    $charset = get_option('blog_charset') ?: 'UTF-8';

    for ($i = 0; $i < 3; $i++) {
      $decoded = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, $charset);

      if ($decoded === $text) {
        break;
      }

      $text = $decoded;
    }

    return $text;
  };

  $safeAttr = static function ($value) use ($decodeText): string {
    return esc_attr($decodeText($value));
  };

  $safeUrl = static function ($value): string {
    if ($value === null || (! is_scalar($value))) {
      return '';
    }

    return esc_url((string) $value);
  };

  $renderSponsorDirectoryLogo = static function (array $sponsor) use ($decodeText): string {
    $lightLogoId = (int) ($sponsor['light_logo_id'] ?? 0);
    $darkLogoId = (int) ($sponsor['dark_logo_id'] ?? 0);
    $singleLogoId = (int) ($sponsor['single_logo_id'] ?? 0);

    $fallbackLogoId = $singleLogoId ?: ($lightLogoId ?: $darkLogoId);
    $resolvedLightLogoId = $lightLogoId ?: $fallbackLogoId;
    $resolvedDarkLogoId = $darkLogoId ?: $fallbackLogoId;
    $logoAlt = $decodeText($sponsor['logo_alt'] ?? $sponsor['title'] ?? '');

    if ($resolvedLightLogoId <= 0 && $resolvedDarkLogoId <= 0) {
      return '';
    }

    if ($resolvedLightLogoId > 0 && $resolvedDarkLogoId > 0 && $resolvedLightLogoId !== $resolvedDarkLogoId) {
      return (wp_get_attachment_image(
        $resolvedLightLogoId,
        'full',
        false,
        [
          'class' => 'sccc-sponsor-directory__logo--light',
          'loading' => 'lazy',
          'alt' => $logoAlt,
        ]
      ) ?: '') . (wp_get_attachment_image(
        $resolvedDarkLogoId,
        'full',
        false,
        [
          'class' => 'sccc-sponsor-directory__logo--dark',
          'loading' => 'lazy',
          'alt' => $logoAlt,
        ]
      ) ?: '');
    }

    $singleResolvedLogoId = $resolvedLightLogoId ?: $resolvedDarkLogoId;

    return wp_get_attachment_image(
      $singleResolvedLogoId,
      'full',
      false,
      [
        'class' => 'sccc-sponsor-directory__logo--single',
        'loading' => 'lazy',
        'alt' => $logoAlt,
      ]
    ) ?: '';
  };
@endphp

@once
  <style>
    .sccc-sponsor-directory {
      --sccc-sd-surface: rgba(17, 24, 39, 0.82);
      --sccc-sd-surface-strong: rgba(15, 23, 42, 0.96);
      --sccc-sd-border: rgba(148, 163, 184, 0.16);
      --sccc-sd-text: #f8fafc;
      --sccc-sd-heading: #ffffff;
      --sccc-sd-muted: #9fb0c8;
      --sccc-sd-primary: #2f78ff;
      --sccc-sd-primary-soft: rgba(47, 120, 255, 0.12);
      --sccc-sd-shadow: 0 18px 50px rgba(2, 8, 23, 0.28);
      --sccc-sd-radius: 1.5rem;
      position: relative;
      width: 100%;
      color: var(--sccc-sd-text);
      background:
        radial-gradient(circle at top center, rgba(47, 120, 255, 0.14), transparent 36%),
        linear-gradient(
          180deg,
          color-mix(in oklab, rgba(15, 23, 42, 0.96) 96%, transparent),
          color-mix(in oklab, rgba(17, 24, 39, 0.82) 98%, transparent)
        );
    }

    .sccc-sponsor-directory.is-pt-none {
      padding-top: 0;
    }

    .sccc-sponsor-directory.is-pt-sm {
      padding-top: 2rem;
    }

    .sccc-sponsor-directory.is-pt-md {
      padding-top: 3.5rem;
    }

    .sccc-sponsor-directory.is-pt-lg {
      padding-top: 5rem;
    }

    .sccc-sponsor-directory.is-pt-xl {
      padding-top: 7rem;
    }

    .sccc-sponsor-directory.is-pb-none {
      padding-bottom: 0;
    }

    .sccc-sponsor-directory.is-pb-sm {
      padding-bottom: 2rem;
    }

    .sccc-sponsor-directory.is-pb-md {
      padding-bottom: 3.5rem;
    }

    .sccc-sponsor-directory.is-pb-lg {
      padding-bottom: 5rem;
    }

    .sccc-sponsor-directory.is-pb-xl {
      padding-bottom: 7rem;
    }

    .sccc-sponsor-directory__container {
      position: relative;
      z-index: 1;
      max-width: 1320px;
      margin-inline: auto;
      padding-inline: 1rem;
    }

    html[data-theme="light"] .sccc-sponsor-directory {
      --sccc-sd-surface: rgba(255, 255, 255, 0.88);
      --sccc-sd-surface-strong: rgba(255, 255, 255, 0.98);
      --sccc-sd-border: rgba(15, 23, 42, 0.08);
      --sccc-sd-text: #122033;
      --sccc-sd-heading: #101827;
      --sccc-sd-muted: #5f6e83;
      --sccc-sd-primary: #1d4ed8;
      --sccc-sd-primary-soft: rgba(29, 78, 216, 0.08);
      --sccc-sd-shadow: 0 18px 44px rgba(15, 23, 42, 0.08);
      background:
        radial-gradient(circle at top center, rgba(29, 78, 216, 0.10), transparent 38%),
        linear-gradient(
          180deg,
          rgba(246, 249, 255, 0.94),
          rgba(241, 246, 255, 0.92)
        );
    }

    html[data-theme="dark"] .sccc-sponsor-directory,
    .dark .sccc-sponsor-directory {
      --sccc-sd-surface: rgba(17, 24, 39, 0.82);
      --sccc-sd-surface-strong: rgba(15, 23, 42, 0.96);
      --sccc-sd-border: rgba(148, 163, 184, 0.16);
      --sccc-sd-text: #f8fafc;
      --sccc-sd-heading: #ffffff;
      --sccc-sd-muted: #9fb0c8;
      --sccc-sd-primary: #2f78ff;
      --sccc-sd-primary-soft: rgba(47, 120, 255, 0.12);
      --sccc-sd-shadow: 0 18px 50px rgba(2, 8, 23, 0.28);
    }

    .sccc-sponsor-directory__header {
      position: relative;
      z-index: 1;
      display: grid;
      gap: 0.8rem;
      margin-bottom: 2rem;
      text-align: center;
    }

    .sccc-sponsor-directory__eyebrow {
      display: inline-flex;
      justify-self: center;
      align-items: center;
      gap: 0.45rem;
      width: fit-content;
      margin: 0;
      padding: 0.35rem 0.72rem;
      border-radius: 999px;
      background: color-mix(in oklab, var(--sccc-sd-primary) 12%, transparent);
      color: color-mix(in oklab, var(--sccc-sd-primary) 62%, var(--sccc-sd-heading));
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    .sccc-sponsor-directory__heading {
      margin: 0;
      color: var(--sccc-sd-heading);
      font-size: clamp(1.8rem, 3vw, 3rem);
      font-weight: 900;
      line-height: 0.98;
      letter-spacing: -0.03em;
    }

    .sccc-sponsor-directory__intro {
      margin: 0 auto;
      max-width: 62ch;
      color: var(--sccc-sd-muted);
      font-size: 1rem;
      line-height: 1.8;
    }

    .sccc-sponsor-directory__body {
      position: relative;
      z-index: 1;
      display: grid;
      gap: 2rem;
    }

    .sccc-sponsor-directory__tier {
      --tier-accent: #cbd5e1;
      --tier-accent-soft: #e8eef5;
      display: grid;
      gap: 1.25rem;
    }

    .sccc-sponsor-directory__tier.is-tier-platinum {
      --tier-accent: #7cc7ff;
      --tier-accent-soft: #dff3ff;
    }

    .sccc-sponsor-directory__tier.is-tier-gold {
      --tier-accent: #d4af37;
      --tier-accent-soft: #ffe7a0;
    }

    .sccc-sponsor-directory__tier.is-tier-silver {
      --tier-accent: #b8c1cc;
      --tier-accent-soft: #eef3f8;
    }

    .sccc-sponsor-directory__tier.is-tier-bronze {
      --tier-accent: #cd7f32;
      --tier-accent-soft: #f5c48d;
    }

    .sccc-sponsor-directory__tier.is-tier-supporter {
      --tier-accent: #cbd5e1;
      --tier-accent-soft: #e8eef5;
    }

    .sccc-sponsor-directory__tier-head {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .sccc-sponsor-directory__tier-line {
      flex: 1 1 auto;
      height: 1px;
      background: color-mix(in oklab, var(--tier-accent) 22%, var(--sccc-sd-border));
    }

    .sccc-sponsor-directory__tier-title {
      margin: 0;
      color: var(--tier-accent);
      font-size: 0.82rem;
      font-weight: 800;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .sccc-sponsor-directory__empty-tier,
    .sccc-sponsor-directory__empty-state {
      padding: 1rem 1.15rem;
      border: 1px dashed color-mix(in oklab, var(--tier-accent) 24%, var(--sccc-sd-border));
      border-radius: 1rem;
      color: var(--sccc-sd-muted);
      background: color-mix(in oklab, var(--sccc-sd-surface-strong) 88%, transparent);
    }

    .sccc-sponsor-directory__empty-state {
      border-color: var(--sccc-sd-border);
      text-align: center;
    }

    .sccc-sponsor-directory__platinum-grid {
      display: grid;
      grid-template-columns: repeat(1, minmax(0, 1fr));
      gap: 1rem;
    }

    @media (min-width: 900px) {
      .sccc-sponsor-directory__platinum-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    .sccc-sponsor-directory__gold-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 1rem;
    }

    @media (min-width: 768px) {
      .sccc-sponsor-directory__gold-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
    }

    @media (min-width: 1200px) {
      .sccc-sponsor-directory__gold-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
      }
    }

    .sccc-sponsor-directory__lower-grid {
      display: grid;
      gap: 1.25rem;
      align-items: start;
    }

    @media (min-width: 1024px) {
      .sccc-sponsor-directory__lower-grid {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
      }
    }

    .sccc-sponsor-directory__logo-wall {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.85rem;
    }

    @media (min-width: 768px) {
      .sccc-sponsor-directory__logo-wall {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
    }

    .sccc-sponsor-directory__bronze-list {
      display: grid;
      grid-template-columns: repeat(1, minmax(0, 1fr));
      gap: 0.65rem 0.85rem;
      list-style: none;
      margin: 0;
      padding: 0;
      align-content: start;
    }

    @media (min-width: 640px) {
      .sccc-sponsor-directory__bronze-list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    .sccc-sponsor-directory__card,
    .sccc-sponsor-directory__logo-card,
    .sccc-sponsor-directory__list-link,
    .sccc-sponsor-directory__split-card {
      border: 1px solid var(--sccc-sd-border);
      background: color-mix(in oklab, var(--sccc-sd-surface-strong) 94%, transparent);
      box-shadow: var(--sccc-sd-shadow);
      backdrop-filter: blur(12px);
    }

    .sccc-sponsor-directory__card,
    .sccc-sponsor-directory__logo-card,
    .sccc-sponsor-directory__list-link {
      display: block;
      text-decoration: none;
      color: inherit;
      transition:
        transform 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        background-color 0.2s ease,
        filter 0.2s ease;
    }

    .sccc-sponsor-directory__card:hover,
    .sccc-sponsor-directory__card:focus-visible,
    .sccc-sponsor-directory__logo-card:hover,
    .sccc-sponsor-directory__logo-card:focus-visible,
    .sccc-sponsor-directory__list-link:hover,
    .sccc-sponsor-directory__list-link:focus-visible {
      transform: translateY(-2px);
      border-color: color-mix(in oklab, var(--tier-accent) 34%, var(--sccc-sd-border));
      box-shadow:
        0 0 0 1px color-mix(in oklab, var(--tier-accent) 12%, transparent),
        0 18px 40px rgba(2, 8, 23, 0.16),
        0 0 26px color-mix(in oklab, var(--tier-accent) 16%, transparent);
      outline: none;
      text-decoration: none;
      color: inherit;
    }

    .sccc-sponsor-directory__card {
      overflow: hidden;
      border-radius: 1.25rem;
    }

    .sccc-sponsor-directory__card--platinum {
      padding: 1.35rem;
      background:
        radial-gradient(circle at top right, color-mix(in oklab, var(--tier-accent) 12%, transparent), transparent 32%),
        linear-gradient(
          180deg,
          color-mix(in oklab, var(--sccc-sd-surface-strong) 98%, transparent),
          color-mix(in oklab, var(--sccc-sd-surface) 96%, transparent)
        );
    }

    .sccc-sponsor-directory__card--gold {
      padding: 1rem;
      border-radius: 1rem;
    }

    .sccc-sponsor-directory__logo-card {
      display: grid;
      gap: 0.65rem;
      align-content: start;
      padding: 0.85rem;
      border-radius: 1rem;
      text-align: center;
    }

    .sccc-sponsor-directory__logo-panel {
      position: relative;
      display: grid;
      place-items: center;
      min-height: 12rem;
      padding: 1.75rem;
      border-radius: 1rem;
      background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0.05)),
        color-mix(in oklab, var(--sccc-sd-surface-strong) 98%, transparent);
      border: 1px solid color-mix(in oklab, var(--tier-accent) 16%, var(--sccc-sd-border));
    }

    .sccc-sponsor-directory__card--platinum .sccc-sponsor-directory__logo-panel {
      min-height: 15rem;
      padding: 2rem 2.25rem;
    }

    .sccc-sponsor-directory__card--gold .sccc-sponsor-directory__logo-panel {
      min-height: 12.25rem;
      padding: 1.05rem;
      border-radius: 0.9rem;
    }

    .sccc-sponsor-directory__logo-card .sccc-sponsor-directory__logo-panel {
      aspect-ratio: 1 / 1;
      min-height: 0;
      padding: 0.65rem;
      border-radius: 0.85rem;
    }

    .sccc-sponsor-directory__logo-wrap {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      min-width: 0;
    }

    .sccc-sponsor-directory__logo-wrap img {
      display: block;
      width: auto;
      max-width: 100%;
      max-height: 7rem;
      object-fit: contain;
    }

    .sccc-sponsor-directory__card--platinum .sccc-sponsor-directory__logo-wrap img {
      max-height: 10.75rem;
    }

    .sccc-sponsor-directory__card--gold .sccc-sponsor-directory__logo-wrap img {
      max-height: 10.75rem;
      max-width: 98%;
    }

    .sccc-sponsor-directory__logo-card .sccc-sponsor-directory__logo-wrap img {
      max-height: 10.25rem;
      max-width: 98%;
      filter: grayscale(0.05);
      opacity: 1;
      transition: filter 0.2s ease, opacity 0.2s ease, transform 0.2s ease;
    }

    .sccc-sponsor-directory__logo-card:hover .sccc-sponsor-directory__logo-wrap img,
    .sccc-sponsor-directory__logo-card:focus-visible .sccc-sponsor-directory__logo-wrap img {
      filter: none;
      opacity: 1;
      transform: scale(1.03);
    }

    .sccc-sponsor-directory__logo-wrap img.sccc-sponsor-directory__logo--light,
    .sccc-sponsor-directory__logo-wrap img.sccc-sponsor-directory__logo--dark {
      display: none;
    }

    html[data-theme="light"] .sccc-sponsor-directory__logo-wrap img.sccc-sponsor-directory__logo--light {
      display: block;
    }

    html[data-theme="light"] .sccc-sponsor-directory__logo-wrap img.sccc-sponsor-directory__logo--dark {
      display: none;
    }

    html[data-theme="dark"] .sccc-sponsor-directory__logo-wrap img.sccc-sponsor-directory__logo--dark,
    .dark .sccc-sponsor-directory__logo-wrap img.sccc-sponsor-directory__logo--dark {
      display: block;
    }

    html[data-theme="dark"] .sccc-sponsor-directory__logo-wrap img.sccc-sponsor-directory__logo--light,
    .dark .sccc-sponsor-directory__logo-wrap img.sccc-sponsor-directory__logo--light {
      display: none;
    }

    .sccc-sponsor-directory__logo-wrap img.sccc-sponsor-directory__logo--single {
      display: block;
    }

    .sccc-sponsor-directory__name {
      margin: 0;
      color: var(--sccc-sd-heading);
      font-weight: 800;
      line-height: 1.15;
      letter-spacing: -0.02em;
    }

    .sccc-sponsor-directory__card--platinum .sccc-sponsor-directory__name {
      font-size: clamp(1.4rem, 2vw, 1.9rem);
    }

    .sccc-sponsor-directory__card--gold .sccc-sponsor-directory__name {
      margin-top: 0.85rem;
      font-size: 1rem;
      text-align: center;
    }

    .sccc-sponsor-directory__logo-card .sccc-sponsor-directory__name {
      font-size: 0.9rem;
    }

    .sccc-sponsor-directory__tagline {
      margin: 0.4rem 0 0;
      color: color-mix(in oklab, var(--tier-accent) 58%, var(--sccc-sd-text));
      font-size: 0.9rem;
      font-weight: 700;
      line-height: 1.45;
    }

    .sccc-sponsor-directory__summary {
      margin: 0.75rem 0 0;
      color: var(--sccc-sd-muted);
      font-size: 0.96rem;
      line-height: 1.7;
    }

    .sccc-sponsor-directory__meta {
      margin-top: 1rem;
      display: flex;
      flex-wrap: wrap;
      gap: 0.55rem;
    }

    .sccc-sponsor-directory__card--gold .sccc-sponsor-directory__meta,
    .sccc-sponsor-directory__logo-card .sccc-sponsor-directory__meta {
      justify-content: center;
      margin-top: 0.75rem;
    }

    .sccc-sponsor-directory__chip {
      display: inline-flex;
      align-items: center;
      width: fit-content;
      min-height: 1.8rem;
      padding: 0.28rem 0.65rem;
      border-radius: 999px;
      background: color-mix(in oklab, var(--tier-accent) 16%, transparent);
      color: var(--tier-accent);
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .sccc-sponsor-directory__list-link {
      display: flex;
      align-items: center;
      gap: 0.7rem;
      min-height: 2.85rem;
      padding: 0.72rem 0.9rem;
      border-radius: 0.9rem;
    }

    .sccc-sponsor-directory__bullet {
      width: 0.45rem;
      height: 0.45rem;
      flex: 0 0 auto;
      border-radius: 999px;
      background: var(--tier-accent);
      box-shadow: 0 0 0 0.22rem color-mix(in oklab, var(--tier-accent) 18%, transparent);
    }

    .sccc-sponsor-directory__list-details {
      display: grid;
      gap: 0.18rem;
      min-width: 0;
    }

    .sccc-sponsor-directory__list-name {
      color: var(--sccc-sd-heading);
      font-weight: 700;
      line-height: 1.3;
    }

    .sccc-sponsor-directory__list-since {
      display: inline-flex;
      width: fit-content;
      color: color-mix(in oklab, var(--tier-accent) 72%, var(--sccc-sd-muted));
      font-size: 0.67rem;
      font-weight: 800;
      line-height: 1.2;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .sccc-sponsor-directory__split-card {
      padding: 1rem;
      border-radius: 1.25rem;
      display: grid;
      gap: 1rem;
      align-content: start;
      align-self: start;
    }

    @media (max-width: 767px) {
      .sccc-sponsor-directory.is-pt-sm {
        padding-top: 1.5rem;
      }

      .sccc-sponsor-directory.is-pt-md {
        padding-top: 2.5rem;
      }

      .sccc-sponsor-directory.is-pt-lg {
        padding-top: 3.5rem;
      }

      .sccc-sponsor-directory.is-pt-xl {
        padding-top: 5rem;
      }

      .sccc-sponsor-directory.is-pb-sm {
        padding-bottom: 1.5rem;
      }

      .sccc-sponsor-directory.is-pb-md {
        padding-bottom: 2.5rem;
      }

      .sccc-sponsor-directory.is-pb-lg {
        padding-bottom: 3.5rem;
      }

      .sccc-sponsor-directory.is-pb-xl {
        padding-bottom: 5rem;
      }

      .sccc-sponsor-directory__logo-panel {
        min-height: 10rem;
        padding: 1.35rem;
      }

      .sccc-sponsor-directory__card--platinum .sccc-sponsor-directory__logo-panel {
        min-height: 12rem;
        padding: 1.5rem 1.5rem 1.75rem;
      }

      .sccc-sponsor-directory__card--gold .sccc-sponsor-directory__logo-panel {
        min-height: 11rem;
        padding: 0.9rem;
      }

      .sccc-sponsor-directory__logo-card .sccc-sponsor-directory__logo-panel {
        padding: 0.55rem;
      }

      .sccc-sponsor-directory__logo-wrap img {
        max-height: 6rem;
      }

      .sccc-sponsor-directory__card--platinum .sccc-sponsor-directory__logo-wrap img {
        max-height: 8.75rem;
      }

      .sccc-sponsor-directory__card--gold .sccc-sponsor-directory__logo-wrap img {
        max-height: 8.5rem;
        max-width: 98%;
      }

      .sccc-sponsor-directory__logo-card .sccc-sponsor-directory__logo-wrap img {
        max-height: 8.25rem;
        max-width: 98%;
      }

      .sccc-sponsor-directory__card--gold .sccc-sponsor-directory__name {
        margin-top: 0.75rem;
      }

      .sccc-sponsor-directory__bronze-list {
        gap: 0.55rem;
      }

      .sccc-sponsor-directory__list-link {
        min-height: 2.85rem;
        padding: 0.68rem 0.85rem;
      }
    }
  </style>
@endonce

<section {{ $attributes->class($sectionClasses) }}>
  <div class="sccc-sponsor-directory__container">
    @if ($eyebrow !== '' || $heading !== '' || $introText !== '')
      <header class="sccc-sponsor-directory__header">
        @if ($eyebrow !== '')
          <p class="sccc-sponsor-directory__eyebrow">{{ $decodeText($eyebrow) }}</p>
        @endif

        @if ($heading !== '')
          <h2 class="sccc-sponsor-directory__heading">{{ $decodeText($heading) }}</h2>
        @endif

        @if ($introText !== '')
          <p class="sccc-sponsor-directory__intro">{!! nl2br(e($decodeText($introText))) !!}</p>
        @endif
      </header>
    @endif

    <div class="sccc-sponsor-directory__body">
      @if ($hasSponsors)
        @if ($platinumGroup)
          <section class="sccc-sponsor-directory__tier is-tier-{{ sanitize_html_class($decodeText($platinumGroup['slug'] ?? '')) }}">
            <div class="sccc-sponsor-directory__tier-head">
              <span class="sccc-sponsor-directory__tier-line" aria-hidden="true"></span>
              <h3 class="sccc-sponsor-directory__tier-title">{{ $decodeText($platinumGroup['title'] ?? '') }}</h3>
              <span class="sccc-sponsor-directory__tier-line" aria-hidden="true"></span>
            </div>

            @if (!empty($platinumGroup['items']))
              <div class="sccc-sponsor-directory__platinum-grid">
                @foreach ($platinumGroup['items'] as $sponsor)
                  <a
                    class="sccc-sponsor-directory__card sccc-sponsor-directory__card--platinum"
                    href="{!! $safeUrl($sponsor['permalink'] ?? '') !!}"
                    aria-label="{!! $safeAttr($sponsor['title'] ?? '') !!}"
                  >
                    <div class="sccc-sponsor-directory__logo-panel">
                      <span class="sccc-sponsor-directory__logo-wrap">
                        {!! $renderSponsorDirectoryLogo($sponsor) !!}
                      </span>
                    </div>

                    <div>
                      <h4 class="sccc-sponsor-directory__name">{{ $decodeText($sponsor['title'] ?? '') }}</h4>

                      @if (($sponsor['tagline'] ?? '') !== '')
                        <p class="sccc-sponsor-directory__tagline">{{ $decodeText($sponsor['tagline'] ?? '') }}</p>
                      @endif

                      @if (($sponsor['summary'] ?? '') !== '')
                        <p class="sccc-sponsor-directory__summary">{{ $decodeText($sponsor['summary'] ?? '') }}</p>
                      @endif

                      @if (($sponsor['since_year'] ?? 0) > 0)
                        <div class="sccc-sponsor-directory__meta">
                          <span class="sccc-sponsor-directory__chip">Since {{ (int) $sponsor['since_year'] }}</span>
                        </div>
                      @endif
                    </div>
                  </a>
                @endforeach
              </div>
            @else
              <p class="sccc-sponsor-directory__empty-tier">{{ $decodeText($platinumGroup['empty_text'] ?? '') }}</p>
            @endif
          </section>
        @endif

        @if ($goldGroup)
          <section class="sccc-sponsor-directory__tier is-tier-{{ sanitize_html_class($decodeText($goldGroup['slug'] ?? '')) }}">
            <div class="sccc-sponsor-directory__tier-head">
              <span class="sccc-sponsor-directory__tier-line" aria-hidden="true"></span>
              <h3 class="sccc-sponsor-directory__tier-title">{{ $decodeText($goldGroup['title'] ?? '') }}</h3>
              <span class="sccc-sponsor-directory__tier-line" aria-hidden="true"></span>
            </div>

            @if (!empty($goldGroup['items']))
              <div class="sccc-sponsor-directory__gold-grid">
                @foreach ($goldGroup['items'] as $sponsor)
                  <a
                    class="sccc-sponsor-directory__card sccc-sponsor-directory__card--gold"
                    href="{!! $safeUrl($sponsor['permalink'] ?? '') !!}"
                    aria-label="{!! $safeAttr($sponsor['title'] ?? '') !!}"
                  >
                    <div class="sccc-sponsor-directory__logo-panel">
                      <span class="sccc-sponsor-directory__logo-wrap">
                        {!! $renderSponsorDirectoryLogo($sponsor) !!}
                      </span>
                    </div>

                    <h4 class="sccc-sponsor-directory__name">{{ $decodeText($sponsor['title'] ?? '') }}</h4>

                    @if (($sponsor['tagline'] ?? '') !== '')
                      <p class="sccc-sponsor-directory__tagline">{{ $decodeText($sponsor['tagline'] ?? '') }}</p>
                    @endif

                    @if (($sponsor['since_year'] ?? 0) > 0)
                      <div class="sccc-sponsor-directory__meta">
                        <span class="sccc-sponsor-directory__chip">Since {{ (int) $sponsor['since_year'] }}</span>
                      </div>
                    @endif
                  </a>
                @endforeach
              </div>
            @else
              <p class="sccc-sponsor-directory__empty-tier">{{ $decodeText($goldGroup['empty_text'] ?? '') }}</p>
            @endif
          </section>
        @endif

        @if ($hasSilver || $hasBronze)
          <div class="sccc-sponsor-directory__lower-grid">
            @if ($silverGroup)
              <section class="sccc-sponsor-directory__tier is-tier-{{ sanitize_html_class($decodeText($silverGroup['slug'] ?? '')) }} sccc-sponsor-directory__split-card">
                <div class="sccc-sponsor-directory__tier-head">
                  <span class="sccc-sponsor-directory__tier-line" aria-hidden="true"></span>
                  <h3 class="sccc-sponsor-directory__tier-title">{{ $decodeText($silverGroup['title'] ?? '') }}</h3>
                  <span class="sccc-sponsor-directory__tier-line" aria-hidden="true"></span>
                </div>

                @if (!empty($silverGroup['items']))
                  <div class="sccc-sponsor-directory__logo-wall">
                    @foreach ($silverGroup['items'] as $sponsor)
                      <a
                        class="sccc-sponsor-directory__logo-card"
                        href="{!! $safeUrl($sponsor['permalink'] ?? '') !!}"
                        aria-label="{!! $safeAttr($sponsor['title'] ?? '') !!}"
                      >
                        <div class="sccc-sponsor-directory__logo-panel">
                          <span class="sccc-sponsor-directory__logo-wrap">
                            {!! $renderSponsorDirectoryLogo($sponsor) !!}
                          </span>
                        </div>

                        <h4 class="sccc-sponsor-directory__name">{{ $decodeText($sponsor['title'] ?? '') }}</h4>

                        @if (($sponsor['since_year'] ?? 0) > 0)
                          <div class="sccc-sponsor-directory__meta">
                            <span class="sccc-sponsor-directory__chip">Since {{ (int) $sponsor['since_year'] }}</span>
                          </div>
                        @endif
                      </a>
                    @endforeach
                  </div>
                @else
                  <p class="sccc-sponsor-directory__empty-tier">{{ $decodeText($silverGroup['empty_text'] ?? '') }}</p>
                @endif
              </section>
            @endif

            @if ($bronzeGroup)
              <section class="sccc-sponsor-directory__tier is-tier-{{ sanitize_html_class($decodeText($bronzeGroup['slug'] ?? '')) }} sccc-sponsor-directory__split-card">
                <div class="sccc-sponsor-directory__tier-head">
                  <span class="sccc-sponsor-directory__tier-line" aria-hidden="true"></span>
                  <h3 class="sccc-sponsor-directory__tier-title">{{ $decodeText($bronzeGroup['title'] ?? '') }}</h3>
                  <span class="sccc-sponsor-directory__tier-line" aria-hidden="true"></span>
                </div>

                @if (!empty($bronzeGroup['items']))
                  <ul class="sccc-sponsor-directory__bronze-list" role="list">
                    @foreach ($bronzeGroup['items'] as $sponsor)
                      <li>
                        <a class="sccc-sponsor-directory__list-link" href="{!! $safeUrl($sponsor['permalink'] ?? '') !!}">
                          <span class="sccc-sponsor-directory__bullet" aria-hidden="true"></span>

                          <span class="sccc-sponsor-directory__list-details">
                            <span class="sccc-sponsor-directory__list-name">{{ $decodeText($sponsor['title'] ?? '') }}</span>

                            @if (($sponsor['since_year'] ?? 0) > 0)
                              <span class="sccc-sponsor-directory__list-since">Since {{ (int) $sponsor['since_year'] }}</span>
                            @endif
                          </span>
                        </a>
                      </li>
                    @endforeach
                  </ul>
                @else
                  <p class="sccc-sponsor-directory__empty-tier">{{ $decodeText($bronzeGroup['empty_text'] ?? '') }}</p>
                @endif
              </section>
            @endif
          </div>
        @endif

        @if ($supporterGroup)
          <section class="sccc-sponsor-directory__tier is-tier-{{ sanitize_html_class($decodeText($supporterGroup['slug'] ?? '')) }}">
            <div class="sccc-sponsor-directory__tier-head">
              <span class="sccc-sponsor-directory__tier-line" aria-hidden="true"></span>
              <h3 class="sccc-sponsor-directory__tier-title">{{ $decodeText($supporterGroup['title'] ?? '') }}</h3>
              <span class="sccc-sponsor-directory__tier-line" aria-hidden="true"></span>
            </div>

            @if (!empty($supporterGroup['items']))
              <ul class="sccc-sponsor-directory__bronze-list" role="list">
                @foreach ($supporterGroup['items'] as $sponsor)
                  <li>
                    <a class="sccc-sponsor-directory__list-link" href="{!! $safeUrl($sponsor['permalink'] ?? '') !!}">
                      <span class="sccc-sponsor-directory__bullet" aria-hidden="true"></span>

                      <span class="sccc-sponsor-directory__list-details">
                        <span class="sccc-sponsor-directory__list-name">{{ $decodeText($sponsor['title'] ?? '') }}</span>

                        @if (($sponsor['since_year'] ?? 0) > 0)
                          <span class="sccc-sponsor-directory__list-since">Since {{ (int) $sponsor['since_year'] }}</span>
                        @endif
                      </span>
                    </a>
                  </li>
                @endforeach
              </ul>
            @else
              <p class="sccc-sponsor-directory__empty-tier">{{ $decodeText($supporterGroup['empty_text'] ?? '') }}</p>
            @endif
          </section>
        @endif
      @else
        <div class="sccc-sponsor-directory__empty-state">
          No active published sponsors are available for the selected tiers yet.
        </div>
      @endif
    </div>
  </div>
</section>