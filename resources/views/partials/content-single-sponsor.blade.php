{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/partials/content-single-sponsor.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the public-facing Sponsor single view using the updated Sponsor ACF
|   fields and the provided HTML mockup as the visual reference.
|
| Why this file exists:
| - Sponsor singles need their own company-focused presentation rather than
|   reusing the standard blog post content template.
| - The public template should surface business information only and
|   intentionally exclude the internal "Primary Contact" fields.
|
| Field/data choices used here:
| - Company fields only:
|   - sponsor_company_tagline
|   - sponsor_company_website
|   - sponsor_company_address_1
|   - sponsor_company_address_2
|   - sponsor_company_city
|   - sponsor_company_state
|   - sponsor_company_zip
|   - sponsor_company_phone
|   - sponsor_company_email
| - Sponsorship/meta:
|   - sponsor_tier taxonomy
|   - sponsor_since_year
| - Assets:
|   - sponsor_logo_light
|   - sponsor_logo_dark
|   - featured image fallback
|
| Notes:
| - Post content is used as the main "About" body.
| - The larger hero logo is intentional so sponsor branding reads clearly.
| - The tier card now uses the explicit tier accent colors directly from the
|   sponsorship card system so the icon and title cannot fall back to neutral.
| - The main-section address/map block has been removed as redundant.
| - The sponsor CTA beneath the content area is loaded from Theme Settings via
|   a dedicated partial so editors can manage its text and buttons globally.
| - Primary contact fields are intentionally not rendered anywhere.
|--------------------------------------------------------------------------
--}}

@php
  $companyName = html_entity_decode(get_the_title(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $tagline = trim((string) get_field('sponsor_company_tagline'));
  $website = trim((string) get_field('sponsor_company_website'));
  $address1 = trim((string) get_field('sponsor_company_address_1'));
  $address2 = trim((string) get_field('sponsor_company_address_2'));
  $city = trim((string) get_field('sponsor_company_city'));
  $state = trim((string) get_field('sponsor_company_state'));
  $zip = trim((string) get_field('sponsor_company_zip'));
  $companyPhone = trim((string) get_field('sponsor_company_phone'));
  $companyEmail = trim((string) get_field('sponsor_company_email'));
  $sponsorSinceYear = (int) get_field('sponsor_since_year');

  $logoLightId = (int) get_field('sponsor_logo_light');
  $logoDarkId = (int) get_field('sponsor_logo_dark');
  $featuredLogoId = (int) get_post_thumbnail_id(get_the_ID());

  if ($website !== '' && !preg_match('#^https?://#i', $website)) {
    $website = 'https://' . ltrim($website, '/');
  }

  $website = $website !== '' ? esc_url($website) : '';

  $addressLines = array_values(array_filter([
    $address1,
    $address2,
    trim(implode(', ', array_filter([$city, $state])) . ($zip ? ' ' . $zip : '')),
  ], fn ($value) => $value !== ''));

  $tierTerms = get_the_terms(get_the_ID(), 'sponsor_tier');
  $tierPriority = [
    'platinum' => 1,
    'gold' => 2,
    'silver' => 3,
    'bronze' => 4,
    'supporter' => 5,
  ];

  $tier = null;

  if (is_array($tierTerms) && !empty($tierTerms)) {
    usort($tierTerms, function ($left, $right) use ($tierPriority) {
      $leftRank = $tierPriority[$left->slug] ?? 99;
      $rightRank = $tierPriority[$right->slug] ?? 99;

      return $leftRank <=> $rightRank;
    });

    $tier = $tierTerms[0];
  }

  $tierSlug = $tier?->slug ?: 'supporter';
  $tierName = $tier?->name ?: 'Supporter';

  /**
   * Tier display metadata:
   * - Uses the exact accent cues you provided from the sponsorship signup cards.
   * - `color` is the main tier accent.
   * - `soft` is the lighter companion tone for subtle surfaces.
   */
  $tierMeta = [
    'platinum' => [
      'label' => 'Platinum Sponsor',
      'accent' => 'is-tier-platinum',
      'icon' => 'star',
      'color' => '#7cc7ff',
      'soft' => '#dff3ff',
    ],
    'gold' => [
      'label' => 'Gold Sponsor',
      'accent' => 'is-tier-gold',
      'icon' => 'medal',
      'color' => '#d4af37',
      'soft' => '#ffe7a0',
    ],
    'silver' => [
      'label' => 'Silver Sponsor',
      'accent' => 'is-tier-silver',
      'icon' => 'shield',
      'color' => '#b8c1cc',
      'soft' => '#eef3f8',
    ],
    'bronze' => [
      'label' => 'Bronze Sponsor',
      'accent' => 'is-tier-bronze',
      'icon' => 'circle',
      'color' => '#cd7f32',
      'soft' => '#f5c48d',
    ],
    'supporter' => [
      'label' => 'Supporter',
      'accent' => 'is-tier-supporter',
      'icon' => 'circle',
      'color' => '#cbd5e1',
      'soft' => '#e8eef5',
    ],
  ];

  $tierDisplay = $tierMeta[$tierSlug] ?? $tierMeta['supporter'];

  $darkLogoId = $logoDarkId ?: ($featuredLogoId ?: $logoLightId);
  $lightLogoId = $logoLightId ?: ($featuredLogoId ?: $logoDarkId);

  $hasDualLogos = $darkLogoId && $lightLogoId;
  $singleLogoId = $darkLogoId ?: $lightLogoId;

  $contentHasBody = trim(wp_strip_all_tags(get_the_content())) !== '';
  $safeEmail = $companyEmail ? antispambot($companyEmail) : '';

  $companyPhoneRaw = trim((string) get_field('sponsor_company_phone'));

  $companyPhoneDigits = preg_replace('/\D+/', '', $companyPhoneRaw);

  // Handle numbers saved with a leading US country code: 1XXXXXXXXXX
  if (strlen($companyPhoneDigits) === 11 && str_starts_with($companyPhoneDigits, '1')) {
      $companyPhoneDigits = substr($companyPhoneDigits, 1);
  }

  $companyPhone = $companyPhoneRaw;

  if (strlen($companyPhoneDigits) === 10) {
      $companyPhone = sprintf(
          '(%s) %s-%s',
          substr($companyPhoneDigits, 0, 3),
          substr($companyPhoneDigits, 3, 3),
          substr($companyPhoneDigits, 6)
      );
  }
  
@endphp

@once
  <style>
    .sccc-sponsor-single {
      --sccc-ss-bg: #0f1724;
      --sccc-ss-surface: rgba(22, 31, 48, 0.82);
      --sccc-ss-surface-strong: rgba(31, 42, 64, 0.96);
      --sccc-ss-border: rgba(148, 163, 184, 0.16);
      --sccc-ss-text: #f8fafc;
      --sccc-ss-muted: #a5b4c9;
      --sccc-ss-heading: #ffffff;
      --sccc-ss-primary: #2f78ff;
      --sccc-ss-primary-soft: rgba(47, 120, 255, 0.18);
      --sccc-ss-shadow: 0 18px 50px rgba(2, 8, 23, 0.35);
      --sccc-ss-radius: 1.5rem;
      position: relative;
      margin-inline: auto;
      max-width: 1200px;
      padding: clamp(1.25rem, 2vw, 2rem) 1rem 4rem;
      color: var(--sccc-ss-text);
    }

    html[data-theme="light"] .sccc-sponsor-single {
      --sccc-ss-bg: #f7f9fc;
      --sccc-ss-surface: rgba(255, 255, 255, 0.9);
      --sccc-ss-surface-strong: rgba(255, 255, 255, 0.96);
      --sccc-ss-border: rgba(15, 23, 42, 0.08);
      --sccc-ss-text: #142033;
      --sccc-ss-muted: #5d6b80;
      --sccc-ss-heading: #101827;
      --sccc-ss-primary: #1d4ed8;
      --sccc-ss-primary-soft: rgba(29, 78, 216, 0.1);
      --sccc-ss-shadow: 0 18px 44px rgba(15, 23, 42, 0.08);
    }

    html[data-theme="dark"] .sccc-sponsor-single,
    .dark .sccc-sponsor-single {
      --sccc-ss-bg: #0f1724;
      --sccc-ss-surface: rgba(22, 31, 48, 0.82);
      --sccc-ss-surface-strong: rgba(31, 42, 64, 0.96);
      --sccc-ss-border: rgba(148, 163, 184, 0.16);
      --sccc-ss-text: #f8fafc;
      --sccc-ss-muted: #a5b4c9;
      --sccc-ss-heading: #ffffff;
      --sccc-ss-primary: #2f78ff;
      --sccc-ss-primary-soft: rgba(47, 120, 255, 0.18);
      --sccc-ss-shadow: 0 18px 50px rgba(2, 8, 23, 0.35);
    }

    .sccc-sponsor-single__backdrop {
      position: absolute;
      inset: 0;
      pointer-events: none;
      border-radius: calc(var(--sccc-ss-radius) + 0.5rem);
      background:
        radial-gradient(circle at 50% 0%, rgba(47, 120, 255, 0.14), transparent 52%),
        linear-gradient(180deg, rgba(47, 120, 255, 0.04), transparent 25%);
      opacity: 1;
    }

    .sccc-sponsor-single__hero {
      position: relative;
      overflow: hidden;
      border: 1px solid var(--sccc-ss-border);
      border-radius: calc(var(--sccc-ss-radius) + 0.25rem);
      background:
        radial-gradient(circle at center, rgba(47, 120, 255, 0.12), transparent 60%),
        linear-gradient(180deg, color-mix(in oklab, var(--sccc-ss-surface-strong) 90%, transparent), color-mix(in oklab, var(--sccc-ss-surface) 92%, transparent));
      box-shadow: var(--sccc-ss-shadow);
      padding: clamp(2.5rem, 4vw, 4.75rem) 1.25rem;
    }

    .sccc-sponsor-single__hero-inner {
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1.85rem;
      text-align: center;
    }

    .sccc-sponsor-single__logo-frame {
      position: relative;
      display: grid;
      place-items: center;
      width: min(100%, clamp(22rem, 52vw, 40rem));
      min-height: clamp(14rem, 26vw, 21rem);
      padding: 2.25rem 2.75rem;
      border-radius: 1.8rem;
      border: 1px solid var(--sccc-ss-border);
      background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.02)),
        var(--sccc-ss-surface-strong);
      box-shadow:
        0 0 0 0.25rem rgba(47, 120, 255, 0.06),
        0 0 42px rgba(47, 120, 255, 0.24);
    }

    .sccc-sponsor-single__logo-frame img {
      display: block;
      width: auto;
      max-width: 100%;
      max-height: clamp(10rem, 22vw, 14rem);
      object-fit: contain;
    }

    .sccc-sponsor-single__logo--light,
    .sccc-sponsor-single__logo--dark {
      display: none;
    }

    html[data-theme="light"] .sccc-sponsor-single__logo--light {
      display: block;
    }

    html[data-theme="light"] .sccc-sponsor-single__logo--dark {
      display: none;
    }

    html[data-theme="dark"] .sccc-sponsor-single__logo--dark,
    .dark .sccc-sponsor-single__logo--dark {
      display: block;
    }

    html[data-theme="dark"] .sccc-sponsor-single__logo--light,
    .dark .sccc-sponsor-single__logo--light {
      display: none;
    }

    .sccc-sponsor-single__logo--single {
      display: block;
    }

    .sccc-sponsor-single__logo-fallback {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 7.5rem;
      height: 7.5rem;
      border-radius: 1.25rem;
      background: var(--sccc-ss-primary-soft);
      color: var(--sccc-ss-heading);
      font-size: 1.85rem;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .sccc-sponsor-single__tier-badge {
      position: absolute;
      right: -0.25rem;
      bottom: -0.5rem;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.55rem 0.85rem;
      border-radius: 999px;
      border: 1px solid var(--sccc-ss-border);
      background: color-mix(in oklab, var(--sccc-ss-surface-strong) 92%, transparent);
      box-shadow: var(--sccc-ss-shadow);
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .sccc-sponsor-single__tier-badge svg {
      width: 1rem;
      height: 1rem;
      flex: 0 0 auto;
    }

    .sccc-sponsor-single__hero-copy {
      max-width: 60rem;
    }

    .sccc-sponsor-single__title {
      margin: 0;
      color: var(--sccc-ss-heading);
      font-size: clamp(2rem, 4vw, 3.6rem);
      font-weight: 900;
      line-height: 0.98;
      letter-spacing: -0.03em;
      text-transform: uppercase;
    }

    .sccc-sponsor-single__tagline {
      margin: 0.65rem 0 0;
      color: color-mix(in oklab, var(--sccc-ss-primary) 55%, var(--sccc-ss-text));
      font-size: clamp(1rem, 1.5vw, 1.15rem);
      font-weight: 700;
      letter-spacing: 0.04em;
    }

    .sccc-sponsor-single__grid {
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      gap: 1.5rem;
      margin-top: 2rem;
    }

    @media (min-width: 1024px) {
      .sccc-sponsor-single__grid {
        grid-template-columns: minmax(0, 1fr) 20rem;
        align-items: start;
      }
    }

    @media (max-width: 767px) {
      .sccc-sponsor-single__logo-frame {
        width: min(100%, 28rem);
        min-height: 12rem;
        padding: 1.75rem 1.75rem 2rem;
      }

      .sccc-sponsor-single__logo-frame img {
        max-height: 9rem;
      }
    }

    .sccc-sponsor-single__content {
      display: grid;
      gap: 1.5rem;
    }

    .sccc-sponsor-single__panel {
      border: 1px solid var(--sccc-ss-border);
      border-radius: var(--sccc-ss-radius);
      background: var(--sccc-ss-surface);
      box-shadow: var(--sccc-ss-shadow);
      backdrop-filter: blur(12px);
    }

    .sccc-sponsor-single__panel--content {
      padding: clamp(1.25rem, 2.5vw, 2rem);
    }

    .sccc-sponsor-single__section-head {
      margin: 0 0 1.25rem;
      padding-left: 1rem;
      border-left: 4px solid var(--sccc-ss-primary);
      color: var(--sccc-ss-heading);
      font-size: clamp(1.35rem, 2vw, 2rem);
      font-weight: 800;
      line-height: 1.1;
    }

    .sccc-sponsor-single__prose {
      color: var(--sccc-ss-text);
      font-size: 1rem;
      line-height: 1.8;
    }

    .sccc-sponsor-single__prose > *:first-child {
      margin-top: 0;
    }

    .sccc-sponsor-single__prose > *:last-child {
      margin-bottom: 0;
    }

    .sccc-sponsor-single__prose p,
    .sccc-sponsor-single__prose ul,
    .sccc-sponsor-single__prose ol {
      margin: 0 0 1rem;
    }

    .sccc-sponsor-single__prose h2,
    .sccc-sponsor-single__prose h3,
    .sccc-sponsor-single__prose h4 {
      color: var(--sccc-ss-heading);
      margin: 1.6rem 0 0.85rem;
      line-height: 1.2;
    }

    .sccc-sponsor-single__button,
    .sccc-sponsor-single__button:visited {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.65rem;
      min-height: 3rem;
      padding: 0.9rem 1.15rem;
      border-radius: 0.95rem;
      border: 1px solid transparent;
      text-decoration: none;
      font-weight: 800;
      transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        background-color 0.2s ease,
        border-color 0.2s ease,
        color 0.2s ease;
    }

    .sccc-sponsor-single__button:hover,
    .sccc-sponsor-single__button:focus-visible {
      transform: translateY(-1px);
      text-decoration: none;
      outline: none;
    }

    .sccc-sponsor-single__button svg {
      width: 1rem;
      height: 1rem;
      flex: 0 0 auto;
    }

    .sccc-sponsor-single__button--primary {
      background: var(--sccc-ss-primary);
      color: #fff;
      box-shadow: 0 0 30px rgba(47, 120, 255, 0.22);
    }

    .sccc-sponsor-single__button--primary:hover,
    .sccc-sponsor-single__button--primary:focus-visible {
      box-shadow: 0 0 34px rgba(47, 120, 255, 0.28);
      filter: brightness(1.03);
      color: #fff;
    }

    .sccc-sponsor-single__button--ghost {
      border-color: var(--sccc-ss-border);
      background: color-mix(in oklab, var(--sccc-ss-surface-strong) 94%, transparent);
      color: var(--sccc-ss-heading);
    }

    .sccc-sponsor-single__button--ghost:hover,
    .sccc-sponsor-single__button--ghost:focus-visible {
      border-color: color-mix(in oklab, var(--sccc-ss-primary) 30%, var(--sccc-ss-border));
      color: var(--sccc-ss-heading);
    }

    .sccc-sponsor-single__sidebar {
      display: grid;
      gap: 1rem;
    }

    .sccc-sponsor-single__sidebar-card {
      padding: 1.25rem;
    }

    .sccc-sponsor-single__sidebar-label {
      margin: 0 0 0.9rem;
      color: var(--sccc-ss-muted);
      font-size: 0.74rem;
      font-weight: 800;
      letter-spacing: 0.18em;
      text-transform: uppercase;
    }

    .sccc-sponsor-single__tier-row {
      display: flex;
      align-items: center;
      gap: 0.8rem;
    }

    .sccc-sponsor-single__tier-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 999px;
      flex: 0 0 auto;
    }

    .sccc-sponsor-single__tier-icon svg {
      width: 1.2rem;
      height: 1.2rem;
    }

    .sccc-sponsor-single__tier-name {
      margin: 0;
      font-size: 1.15rem;
      font-weight: 800;
      line-height: 1.2;
    }

    .sccc-sponsor-single__tier-meta {
      margin: 0.25rem 0 0;
      color: var(--sccc-ss-muted);
      font-size: 0.92rem;
      line-height: 1.5;
    }

    .sccc-sponsor-single__details {
      display: grid;
      gap: 1.25rem;
    }

    .sccc-sponsor-single__detail-group + .sccc-sponsor-single__detail-group {
      padding-top: 1.25rem;
      border-top: 1px solid var(--sccc-ss-border);
    }

    .sccc-sponsor-single__detail-row {
      display: grid;
      grid-template-columns: 1.4rem minmax(0, 1fr);
      gap: 0.75rem;
      align-items: start;
    }

    .sccc-sponsor-single__detail-row + .sccc-sponsor-single__detail-row {
      margin-top: 0.85rem;
    }

    .sccc-sponsor-single__detail-icon {
      color: var(--sccc-ss-primary);
      line-height: 1;
      padding-top: 0.15rem;
    }

    .sccc-sponsor-single__detail-icon svg {
      width: 1rem;
      height: 1rem;
      display: block;
    }

    .sccc-sponsor-single__detail-body {
      min-width: 0;
      color: var(--sccc-ss-text);
      font-size: 0.95rem;
      line-height: 1.65;
    }

    .sccc-sponsor-single__detail-body a,
    .sccc-sponsor-single__detail-body a:visited {
      color: var(--sccc-ss-heading);
      text-decoration: none;
      border-bottom: 1px solid transparent;
      transition: border-color 0.2s ease, color 0.2s ease;
    }

    .sccc-sponsor-single__detail-body a:hover,
    .sccc-sponsor-single__detail-body a:focus-visible {
      color: var(--sccc-ss-primary);
      border-color: currentColor;
      outline: none;
    }

    .sccc-sponsor-single__detail-strong {
      display: block;
      color: var(--sccc-ss-heading);
      font-weight: 800;
    }
  </style>
@endonce

<article @php(post_class('sccc-sponsor-single ' . $tierDisplay['accent']))>
  <div class="sccc-sponsor-single__backdrop" aria-hidden="true"></div>

  <section class="sccc-sponsor-single__hero">
    <div class="sccc-sponsor-single__hero-inner">
      <div class="sccc-sponsor-single__logo-frame">
        @if ($hasDualLogos)
          {!! wp_get_attachment_image(
            $lightLogoId,
            'full',
            false,
            [
              'class' => 'sccc-sponsor-single__logo--light',
              'loading' => 'eager',
              'alt' => $companyName,
            ]
          ) !!}

          {!! wp_get_attachment_image(
            $darkLogoId,
            'full',
            false,
            [
              'class' => 'sccc-sponsor-single__logo--dark',
              'loading' => 'eager',
              'alt' => $companyName,
            ]
          ) !!}
        @elseif ($singleLogoId)
          {!! wp_get_attachment_image(
            $singleLogoId,
            'full',
            false,
            [
              'class' => 'sccc-sponsor-single__logo--single',
              'loading' => 'eager',
              'alt' => $companyName,
            ]
          ) !!}
        @else
          <span class="sccc-sponsor-single__logo-fallback" aria-hidden="true">
            {{ strtoupper(substr($companyName, 0, 2)) }}
          </span>
        @endif

        <span
          class="sccc-sponsor-single__tier-badge"
          style="color: {{ esc_attr($tierDisplay['color']) }};"
        >
          @if ($tierDisplay['icon'] === 'star')
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="m12 2.75 2.76 5.6 6.18.9-4.47 4.36 1.06 6.15L12 16.86 6.47 19.76l1.06-6.15L3.06 9.25l6.18-.9L12 2.75Z"/>
            </svg>
          @elseif ($tierDisplay['icon'] === 'medal')
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M7 2h4l1 3-3 5H6L4 5l3-3Zm10 0 3 3-2 5h-3l-3-5 1-3h4Zm-5 8a6 6 0 1 1 0 12 6 6 0 0 1 0-12Zm0 3.1 1.12 2.28 2.51.37-1.81 1.77.43 2.5L12 18.84l-2.25 1.18.43-2.5-1.81-1.77 2.51-.37L12 13.1Z"/>
            </svg>
          @elseif ($tierDisplay['icon'] === 'shield')
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M12 2.5 4.5 5.2v5.96c0 5.02 3.19 9.72 7.5 10.84 4.31-1.12 7.5-5.82 7.5-10.84V5.2L12 2.5Z"/>
            </svg>
          @else
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <circle cx="12" cy="12" r="7"/>
            </svg>
          @endif

          <span>{{ $tierName }}</span>
        </span>
      </div>

      <div class="sccc-sponsor-single__hero-copy">
        <h1 class="sccc-sponsor-single__title">{{ $companyName }}</h1>

        @if ($tagline !== '')
          <p class="sccc-sponsor-single__tagline">{{ $tagline }}</p>
        @endif
      </div>
    </div>
  </section>

  <div class="sccc-sponsor-single__grid">
    <div class="sccc-sponsor-single__content">
      <section class="sccc-sponsor-single__panel sccc-sponsor-single__panel--content">
        <h2 class="sccc-sponsor-single__section-head">About {{ $companyName }}</h2>

        @if ($contentHasBody)
          <div class="entry-content sccc-sponsor-single__prose">
            @php(the_content())
          </div>
        @else
          <p class="sccc-sponsor-single__detail-body">
            More information about {{ $companyName }} is coming soon.
          </p>
        @endif
      </section>

      @include('partials.sponsor-single-cta')
    </div>

    <aside class="sccc-sponsor-single__sidebar" aria-label="Sponsor details">
      @if ($website)
        <a
          class="sccc-sponsor-single__button sccc-sponsor-single__button--primary"
          href="{{ $website }}"
          target="_blank"
          rel="noopener noreferrer"
        >
          <span>Visit Website</span>

          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M14 5h5v5"/>
            <path d="M10 14 19 5"/>
            <path d="M19 14v4a1 1 0 0 1-1 1h-4"/>
            <path d="M10 5H6a1 1 0 0 0-1 1v4"/>
            <path d="M5 10v4a1 1 0 0 0 1 1h4"/>
          </svg>
        </a>
      @endif

      <section class="sccc-sponsor-single__panel sccc-sponsor-single__sidebar-card">
        <p class="sccc-sponsor-single__sidebar-label">Partner Level</p>

        <div class="sccc-sponsor-single__tier-row">
          <span
            class="sccc-sponsor-single__tier-icon"
            aria-hidden="true"
            style="
              color: {{ esc_attr($tierDisplay['color']) }};
              background: color-mix(in oklab, {{ esc_attr($tierDisplay['soft']) }} 24%, transparent);
              box-shadow: inset 0 0 0 1px color-mix(in oklab, {{ esc_attr($tierDisplay['color']) }} 24%, transparent);
            "
          >
            @if ($tierDisplay['icon'] === 'star')
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="m12 2.75 2.76 5.6 6.18.9-4.47 4.36 1.06 6.15L12 16.86 6.47 19.76l1.06-6.15L3.06 9.25l6.18-.9L12 2.75Z"/>
              </svg>
            @elseif ($tierDisplay['icon'] === 'medal')
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M7 2h4l1 3-3 5H6L4 5l3-3Zm10 0 3 3-2 5h-3l-3-5 1-3h4Zm-5 8a6 6 0 1 1 0 12 6 6 0 0 1 0-12Zm0 3.1 1.12 2.28 2.51.37-1.81 1.77.43 2.5L12 18.84l-2.25 1.18.43-2.5-1.81-1.77 2.51-.37L12 13.1Z"/>
              </svg>
            @elseif ($tierDisplay['icon'] === 'shield')
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2.5 4.5 5.2v5.96c0 5.02 3.19 9.72 7.5 10.84 4.31-1.12 7.5-5.82 7.5-10.84V5.2L12 2.5Z"/>
              </svg>
            @else
              <svg viewBox="0 0 24 24" fill="currentColor">
                <circle cx="12" cy="12" r="7"/>
              </svg>
            @endif
          </span>

          <div>
            <p
              class="sccc-sponsor-single__tier-name"
              style="color: {{ esc_attr($tierDisplay['color']) }};"
            >
              {{ $tierDisplay['label'] }}
            </p>

            @if ($sponsorSinceYear > 0)
              <p class="sccc-sponsor-single__tier-meta">Sponsor since {{ $sponsorSinceYear }}</p>
            @endif
          </div>
        </div>
      </section>

      @if (!empty($addressLines) || $companyPhone || $companyEmail)
        <section class="sccc-sponsor-single__panel sccc-sponsor-single__sidebar-card">
          <div class="sccc-sponsor-single__details">
            @if (!empty($addressLines))
              <div class="sccc-sponsor-single__detail-group">
                <p class="sccc-sponsor-single__sidebar-label">Address</p>

                <div class="sccc-sponsor-single__detail-row">
                  <span class="sccc-sponsor-single__detail-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                      <path d="M12 21s6-4.35 6-10a6 6 0 1 0-12 0c0 5.65 6 10 6 10Z"/>
                      <circle cx="12" cy="11" r="2.5"/>
                    </svg>
                  </span>

                  <div class="sccc-sponsor-single__detail-body">
                    <span class="sccc-sponsor-single__detail-strong">{{ $companyName }}</span>

                    @foreach ($addressLines as $line)
                      <div>{{ $line }}</div>
                    @endforeach
                  </div>
                </div>
              </div>
            @endif

            @if ($companyPhone || $companyEmail)
              <div class="sccc-sponsor-single__detail-group">
                <p class="sccc-sponsor-single__sidebar-label">Contact Info</p>

                @if ($companyPhone)
                  <div class="sccc-sponsor-single__detail-row">
                    <span class="sccc-sponsor-single__detail-icon" aria-hidden="true">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.8 19.8 0 0 1 3.08 5.18 2 2 0 0 1 5.06 3h3a2 2 0 0 1 2 1.72l.34 2.27a2 2 0 0 1-.57 1.73L8.4 10.15a16 16 0 0 0 5.45 5.45l1.43-1.43a2 2 0 0 1 1.73-.57l2.27.34A2 2 0 0 1 22 16.92Z"/>
                      </svg>
                    </span>

                    <div class="sccc-sponsor-single__detail-body">
                      <a href="tel:{{ preg_replace('/[^0-9+]/', '', $companyPhone) }}">{{ $companyPhone }}</a>
                    </div>
                  </div>
                @endif

                @if ($companyEmail)
                  <div class="sccc-sponsor-single__detail-row">
                    <span class="sccc-sponsor-single__detail-icon" aria-hidden="true">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                        <path d="M4 6h16v12H4z"/>
                        <path d="m4 7 8 6 8-6"/>
                      </svg>
                    </span>

                    <div class="sccc-sponsor-single__detail-body">
                      <a href="mailto:{!! $safeEmail !!}">{!! $safeEmail !!}</a>
                    </div>
                  </div>
                @endif
              </div>
            @endif
          </div>
        </section>
      @endif
    </aside>
  </div>
</article>