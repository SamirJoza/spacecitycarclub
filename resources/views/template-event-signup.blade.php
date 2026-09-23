{{--
  Template Name: Event Signup

  File: resources/views/template-event-signup.blade.php

  Purpose:
  -----------------------------------------------------------------------------
  Tablet-friendly onsite membership signup entry page.

  Flow:
  - Shows only PMPro levels allowed by sccc_event_signup_level_ids.
  - Defaults to levels 2, 3, 4.
  - Reuses PMPro checkout/payment by linking to the normal checkout page.
  - Starts event signup mode through EventSignupFlow.
  - Founding Member availability is handled by FoundingCap through EventSignupFlow.

  Layout:
  -----------------------------------------------------------------------------
  This template extends layouts.kiosk instead of layouts.app so the tablet flow
  does not load the normal site header/footer.

  Current refinements:
  -----------------------------------------------------------------------------
  - The large Space City Car Club logo is the main brand anchor.
  - The visible H1 is task-focused: "Choose Your Membership".
  - The event context remains lightweight and secondary.
  - The checkout/email instruction is emphasized by typography only; no card,
    no icon, no eyebrow, no blue link-like treatment.
  - The checkout/email instruction spans the intro area across both columns.
  - Event logo upload is supported through the Event Signup settings page.
  - Founding price suffix is displayed below the price so "/ one-time" does not
    wrap awkwardly beside the large price.
  - Non-featured buttons look active instead of disabled/gray.
  - PMPro-sourced card text is intentionally left untouched.
--}}

@extends('layouts.kiosk')

@section('content')
  @php
    use App\Support\PMPro\EventSignupFlow;

    $flowAvailable = class_exists(EventSignupFlow::class);
    $enabled = $flowAvailable ? EventSignupFlow::isEnabled() : false;
    $allowed = $flowAvailable ? EventSignupFlow::canStartFromCurrentRequest() : false;
    $plans = ($enabled && $allowed && $flowAvailable) ? EventSignupFlow::eventSignupPlans() : [];

    /**
     * --------------------------------------------------------------------------
     * Club / page logo
     * --------------------------------------------------------------------------
     * Order:
     * 1) WordPress Custom Logo
     * 2) Theme asset fallback candidates
     * 3) No logo markup, text-only fallback
     */
    $siteName = get_bloginfo('name') ?: 'Space City Car Club';

    $clubLogoHtml = '';
    $customLogoId = (int) get_theme_mod('custom_logo');

    if ($customLogoId > 0) {
        $clubLogoHtml = wp_get_attachment_image($customLogoId, 'full', false, [
            'class' => 'sccc-event-signup__club-logo-img',
            'alt' => $siteName,
            'loading' => 'eager',
            'decoding' => 'async',
        ]);
    } else {
        $clubLogoUrl = '';
        $clubLogoCandidates = [
            'resources/images/logo.png',
            'resources/images/logo.svg',
            'resources/images/sccc-logo.png',
            'resources/images/sccc-logo.svg',
            'resources/images/brand/logo.png',
            'resources/images/brand/logo.svg',
        ];

        foreach ($clubLogoCandidates as $candidate) {
            if (file_exists(get_theme_file_path($candidate))) {
                $clubLogoUrl = get_theme_file_uri($candidate);
                break;
            }
        }

        if ($clubLogoUrl !== '') {
            $clubLogoHtml = '<img src="' . esc_url($clubLogoUrl) . '" alt="' . esc_attr($siteName) . '" class="sccc-event-signup__club-logo-img" loading="eager" decoding="async">';
        }
    }

    /**
     * --------------------------------------------------------------------------
     * Active event context
     * --------------------------------------------------------------------------
     * Event context should only show when a real event name is configured.
     *
     * EventSignupFlow::eventName() intentionally has a generic fallback for the
     * business logic layer. For layout purposes, we read the raw option so we can
     * distinguish "no event configured" from a real active event.
     */
    $rawConfiguredEventName = function_exists('get_field')
        ? trim((string) (get_field('sccc_event_signup_event_name', 'option') ?: ''))
        : '';

    $eventName = $rawConfiguredEventName;

    $genericEventNames = [
        'space city car club event signup',
        'event signup',
    ];

    $hasEventContext = $eventName !== ''
        && ! in_array(mb_strtolower($eventName), $genericEventNames, true);

    if (! $hasEventContext) {
        $eventName = '';
    }

    /**
     * --------------------------------------------------------------------------
     * Event logo
     * --------------------------------------------------------------------------
     * Reads from the ACF options page registered in:
     * app/Fields/EventSignupSettings.php
     *
     * Supported field name:
     * - sccc_event_signup_event_logo
     */
    $resolveAcfImageUrl = static function ($value): string {
        if (empty($value)) {
            return '';
        }

        if (is_array($value)) {
            if (! empty($value['url']) && is_string($value['url'])) {
                return trim($value['url']);
            }

            if (! empty($value['ID'])) {
                return (string) (wp_get_attachment_image_url((int) $value['ID'], 'full') ?: '');
            }

            if (! empty($value['id'])) {
                return (string) (wp_get_attachment_image_url((int) $value['id'], 'full') ?: '');
            }

            return '';
        }

        if (is_numeric($value)) {
            return (string) (wp_get_attachment_image_url((int) $value, 'full') ?: '');
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return '';
            }

            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return $value;
            }

            if (ctype_digit($value)) {
                return (string) (wp_get_attachment_image_url((int) $value, 'full') ?: '');
            }
        }

        return '';
    };

    $eventLogoRaw = function_exists('get_field')
        ? get_field('sccc_event_signup_event_logo', 'option')
        : null;

    $eventLogoUrl = $resolveAcfImageUrl($eventLogoRaw);

    $eventLogoHtml = '';
    if ($hasEventContext && $eventLogoUrl !== '') {
        $eventLogoHtml = '<img src="' . esc_url($eventLogoUrl) . '" alt="' . esc_attr($eventName) . '" class="sccc-event-signup__event-logo-img" loading="eager" decoding="async">';
    }
  @endphp

  <style>
    /* ==========================================================================
       SCCC Event Signup — page scoped
       ========================================================================== */

    .sccc-event-signup {
      --sccc-event-bg: #f8fafc;
      --sccc-event-card-bg: rgba(255,255,255,0.88);
      --sccc-event-card-border: rgba(15,23,42,0.12);
      --sccc-event-card-border-hover: rgba(15,23,42,0.22);
      --sccc-event-heading: #0f172a;
      --sccc-event-body: #475569;
      --sccc-event-muted: #64748b;
      --sccc-event-button-bg: #0f172a;
      --sccc-event-button-bg-hover: #1e293b;
      --sccc-event-button-text: #ffffff;
      --sccc-event-primary: var(--color-primary, #135bec);
      --sccc-event-shadow: 0 18px 55px rgba(2,6,23,0.14);

      background:
        radial-gradient(680px 320px at 50% 0%, color-mix(in srgb, var(--sccc-event-primary) 14%, transparent) 0%, transparent 72%),
        radial-gradient(560px 280px at 18% 74%, rgba(59,130,246,0.09) 0%, transparent 68%),
        radial-gradient(640px 320px at 84% 78%, rgba(168,85,247,0.07) 0%, transparent 70%),
        var(--sccc-event-bg);
    }

    html.dark[data-theme="dark"] .sccc-event-signup,
    .dark .sccc-event-signup {
      --sccc-event-bg: #0f1520;
      --sccc-event-card-bg: rgba(255,255,255,0.07);
      --sccc-event-card-border: rgba(255,255,255,0.12);
      --sccc-event-card-border-hover: rgba(255,255,255,0.24);
      --sccc-event-heading: #ffffff;
      --sccc-event-body: #92a4c9;
      --sccc-event-muted: rgba(255,255,255,0.68);
      --sccc-event-button-bg: rgba(255,255,255,0.12);
      --sccc-event-button-bg-hover: rgba(255,255,255,0.18);
      --sccc-event-button-text: #ffffff;
      --sccc-event-shadow:
        0 1px 0 rgba(255,255,255,0.10) inset,
        0 18px 55px rgba(0,0,0,0.58);
    }

    .sccc-event-signup a {
      text-decoration: none;
    }

    /* --------------------------------------------------------------------------
       Hero / intro area
       -------------------------------------------------------------------------- */

    .sccc-event-signup__intro-shell {
      margin-inline: auto;
      margin-bottom: 2.85rem;
      width: 100%;
    }

    .sccc-event-signup__intro-shell--centered {
      max-width: 54rem;
      text-align: center;
    }

    .sccc-event-signup__intro-shell--with-event {
      display: grid;
      gap: 1.65rem;
      max-width: 74rem;
      align-items: end;
    }

    .sccc-event-signup__club-logo {
      display: flex;
      justify-content: center;
      margin-bottom: 1rem;
    }

    .sccc-event-signup__club-logo-img {
      display: block;
      width: auto;
      height: clamp(6rem, 10vw, 8rem);
      max-width: min(50vw, 15rem);
      object-fit: contain;
      filter: drop-shadow(0 12px 28px rgba(0,0,0,0.22));
    }

    .sccc-event-signup__hero-copy {
      min-width: 0;
      text-align: center;
    }

    .sccc-event-signup__title {
      margin: 0;
      font-size: clamp(2.2rem, 5.2vw, 3.35rem);
      font-weight: 950;
      line-height: 0.98;
      letter-spacing: -0.045em;
      color: var(--sccc-event-heading);
    }

    .sccc-event-signup__intro {
      margin: 0.85rem auto 0;
      max-width: 34rem;
      font-size: clamp(1.02rem, 1.75vw, 1.14rem);
      line-height: 1.45;
      color: var(--sccc-event-body);
    }

    .sccc-event-signup__after-text {
      margin: 1.25rem auto 0;
      max-width: 62rem;
      font-size: clamp(1.08rem, 1.95vw, 1.34rem);
      font-weight: 850;
      line-height: 1.42;
      letter-spacing: -0.015em;
      color: var(--sccc-event-heading);
      text-align: center;
      text-wrap: balance;
    }

    .sccc-event-signup__after-text strong {
      color: inherit;
      font-weight: 950;
    }

    .sccc-event-signup__after-text span {
      color: var(--sccc-event-heading);
    }

    /* --------------------------------------------------------------------------
       Event context
       --------------------------------------------------------------------------
       The event is context, not the main action. No divider, no pill, no label.
       The event logo is intentionally smaller than the club logo.
       -------------------------------------------------------------------------- */

    .sccc-event-signup__event-context {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 1rem;
      min-width: 0;
      color: var(--sccc-event-heading);
    }

    .sccc-event-signup__event-logo {
      flex: 0 0 auto;
      display: flex;
      align-items: center;
      justify-content: center;
      width: clamp(5rem, 7vw, 6.25rem);
      height: clamp(5rem, 7vw, 6.25rem);
    }

    .sccc-event-signup__event-logo-img {
      display: block;
      width: 100%;
      height: 100%;
      object-fit: contain;
      filter: drop-shadow(0 10px 24px rgba(0,0,0,0.24));
    }

    .sccc-event-signup__event-logo-fallback {
      font-size: clamp(2.25rem, 4vw, 2.8rem);
      line-height: 1;
      color: var(--sccc-event-primary);
      filter: drop-shadow(0 0 18px color-mix(in srgb, var(--sccc-event-primary) 40%, transparent));
    }

    .sccc-event-signup__event-copy {
      min-width: 0;
      text-align: left;
    }

    .sccc-event-signup__event-title {
      margin: 0;
      font-size: clamp(1.35rem, 2.3vw, 1.75rem);
      font-weight: 950;
      line-height: 1.08;
      letter-spacing: -0.035em;
      color: var(--sccc-event-heading);
      word-break: break-word;
    }

    /* --------------------------------------------------------------------------
       Cards
       -------------------------------------------------------------------------- */

    .sccc-event-signup__grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1rem;
    }

    .sccc-event-signup__card {
      position: relative;
      isolation: isolate;
      display: flex;
      min-height: 100%;
      flex-direction: column;
      border: 1px solid var(--sccc-event-card-border);
      border-radius: 1rem;
      padding: 1rem;
      background: var(--sccc-event-card-bg);
      box-shadow: var(--sccc-event-shadow);
      -webkit-backdrop-filter: blur(22px) saturate(140%);
      backdrop-filter: blur(22px) saturate(140%);
      transition:
        border-color 180ms ease,
        box-shadow 180ms ease,
        transform 180ms ease;
    }

    .sccc-event-signup__card::before {
      content: "";
      position: absolute;
      inset: 0;
      z-index: -1;
      border-radius: inherit;
      opacity: 0;
      background:
        linear-gradient(135deg, rgba(255,255,255,0.18), transparent 38%),
        radial-gradient(420px 160px at 24% 0%, rgba(59,130,246,0.20), transparent 70%);
      transition: opacity 180ms ease;
      pointer-events: none;
    }

    .sccc-event-signup__card::after {
      content: "";
      position: absolute;
      left: 1.15rem;
      right: 1.15rem;
      top: 0;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.36), transparent);
      opacity: 0.75;
      pointer-events: none;
    }

    .sccc-event-signup__card:hover {
      border-color: var(--sccc-event-card-border-hover);
    }

    .sccc-event-signup__card:hover::before {
      opacity: 1;
    }

    .sccc-event-signup__card--featured {
      border-color: var(--sccc-event-primary);
      box-shadow:
        0 0 0 1px color-mix(in srgb, var(--sccc-event-primary) 35%, transparent) inset,
        0 0 28px color-mix(in srgb, var(--sccc-event-primary) 32%, transparent),
        var(--sccc-event-shadow);
    }

    .sccc-event-signup__card--founding {
      border-color: rgba(251,191,36,0.42);
      box-shadow:
        0 0 0 1px rgba(251,191,36,0.20) inset,
        0 0 32px rgba(251,191,36,0.10),
        var(--sccc-event-shadow);
    }

    .sccc-event-signup__card--founding::before {
      opacity: 1;
      background:
        radial-gradient(520px 180px at 50% -12%, rgba(251,191,36,0.20), transparent 70%),
        linear-gradient(135deg, rgba(255,255,255,0.14), transparent 38%);
    }

    .sccc-event-signup__card--sold-out {
      opacity: 0.68;
      filter: saturate(0.75);
    }

    .sccc-event-signup__card-head {
      margin-top: 0.4rem;
    }

    .sccc-event-signup__card-title {
      margin: 0;
      font-size: clamp(1.1rem, 2.2vw, 1.45rem);
      font-weight: 950;
      line-height: 1.08;
      color: var(--sccc-event-heading);
    }

    .sccc-event-signup__price-row {
      display: flex;
      align-items: baseline;
      gap: 0.36rem;
      flex-wrap: wrap;
      margin-top: 1.05rem;
    }

    .sccc-event-signup__price {
      font-size: clamp(2rem, 3.8vw, 2.75rem);
      font-weight: 950;
      line-height: 0.98;
      letter-spacing: -0.045em;
      color: var(--sccc-event-heading);
    }

    .sccc-event-signup__suffix {
      font-size: 0.8rem;
      font-weight: 800;
      line-height: 1.2;
      color: var(--sccc-event-muted);
      white-space: nowrap;
    }

    .sccc-event-signup__card--founding .sccc-event-signup__price-row {
      display: block;
    }

    .sccc-event-signup__card--founding .sccc-event-signup__suffix {
      display: block;
      margin-top: 0.28rem;
      white-space: normal;
    }

    /* --------------------------------------------------------------------------
       Designed badges
       -------------------------------------------------------------------------- */

    .sccc-event-signup__badge {
      position: absolute;
      top: -0.9rem;
      left: 50%;
      z-index: 20;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.4rem;
      max-width: calc(100% - 1.3rem);
      padding: 0.45rem 0.78rem;
      border-radius: 999px;
      transform: translateX(-50%);
      font-size: 0.68rem;
      font-weight: 950;
      line-height: 1;
      letter-spacing: 0.01em;
      white-space: nowrap;
      border: 1px solid rgba(255,255,255,0.28);
      box-shadow:
        0 14px 30px rgba(0,0,0,0.28),
        0 1px 0 rgba(255,255,255,0.28) inset;
    }

    .sccc-event-signup__badge .material-symbols-outlined {
      font-size: 0.9rem;
      line-height: 1;
      font-variation-settings: "FILL" 1, "wght" 500, "GRAD" 0, "opsz" 20;
    }

    .sccc-event-signup__badge--founding {
      color: #0f172a;
      background:
        radial-gradient(circle at 18% 20%, rgba(255,255,255,0.42), transparent 34%),
        linear-gradient(135deg, #fbbf24 0%, #fb7185 55%, #a855f7 130%);
      border-color: rgba(255,255,255,0.35);
    }

    .sccc-event-signup__badge--founding::before {
      content: "";
      position: absolute;
      inset: -14px -20px;
      z-index: -1;
      border-radius: 999px;
      opacity: 0.34;
      filter: blur(18px);
      background:
        radial-gradient(circle at 35% 50%, rgba(251,191,36,0.95), transparent 62%),
        radial-gradient(circle at 70% 50%, rgba(251,113,133,0.72), transparent 68%);
      pointer-events: none;
    }

    .sccc-event-signup__badge--featured {
      color: #ffffff;
      background:
        radial-gradient(circle at 18% 20%, rgba(255,255,255,0.26), transparent 34%),
        linear-gradient(135deg, var(--sccc-event-primary), #2563eb);
    }

    .sccc-event-signup__badge--sold-out {
      color: #ffffff;
      background:
        radial-gradient(circle at 18% 20%, rgba(255,255,255,0.18), transparent 34%),
        linear-gradient(135deg, #64748b, #334155);
    }

    /* --------------------------------------------------------------------------
       Buttons
       -------------------------------------------------------------------------- */

    .sccc-event-signup__button-wrap {
      margin-top: 1.15rem;
    }

    .sccc-event-signup__button {
      display: flex;
      width: 100%;
      align-items: center;
      justify-content: center;
      border-radius: 0.55rem;
      padding: 0.76rem 1rem;
      text-align: center;
      font-size: 0.82rem;
      font-weight: 950;
      color: var(--sccc-event-button-text);
      transition:
        transform 160ms ease,
        filter 160ms ease,
        background-color 160ms ease,
        box-shadow 160ms ease;
    }

    .sccc-event-signup__button:hover {
      transform: translateY(-1px);
      filter: brightness(1.08);
    }

    .sccc-event-signup__button--default {
      border: 1px solid rgba(96,165,250,0.24);
      background:
        radial-gradient(circle at 18% 20%, rgba(255,255,255,0.11), transparent 34%),
        linear-gradient(135deg, rgba(37,99,235,0.82), rgba(30,64,175,0.58));
      box-shadow: 0 12px 28px rgba(37,99,235,0.14);
    }

    .sccc-event-signup__button--featured {
      background: var(--sccc-event-primary);
      box-shadow: 0 14px 30px color-mix(in srgb, var(--sccc-event-primary) 24%, transparent);
    }

    .sccc-event-signup__button--founding {
      color: #0f172a;
      background:
        radial-gradient(circle at 18% 20%, rgba(255,255,255,0.28), transparent 34%),
        linear-gradient(135deg, #fbbf24 0%, #fb7185 70%);
      box-shadow: 0 14px 30px rgba(251,191,36,0.18);
    }

    .sccc-event-signup__button--disabled {
      cursor: not-allowed;
      background: rgba(100,116,139,0.72);
      color: #ffffff;
    }

    /* --------------------------------------------------------------------------
       WYSIWYG / PMPro description
       -------------------------------------------------------------------------- */

    .sccc-event-signup__wysiwyg {
      margin-top: 1.05rem;
      color: var(--sccc-event-body);
      font-size: 0.82rem;
      line-height: 1.58;
    }

    .sccc-event-signup__wysiwyg > :first-child {
      margin-top: 0;
    }

    .sccc-event-signup__wysiwyg > :last-child {
      margin-bottom: 0;
    }

    .sccc-event-signup__wysiwyg p {
      margin: 0;
      color: var(--sccc-event-body);
    }

    .sccc-event-signup__wysiwyg p + p {
      margin-top: .58rem;
    }

    .sccc-event-signup__wysiwyg ul {
      margin: 0;
      padding: 0;
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: .58rem;
    }

    .sccc-event-signup__wysiwyg ul > li {
      display: flex;
      align-items: flex-start;
      gap: .55rem;
      color: var(--sccc-event-body);
    }

    .sccc-event-signup__wysiwyg ul > li > p {
      margin: 0;
      color: inherit;
    }

    .sccc-event-signup__wysiwyg ul > li::before {
      content: "verified";
      font-family: "Material Symbols Outlined";
      font-size: 1rem;
      line-height: 1;
      font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 20;
      flex: 0 0 auto;
      transform: translateY(2px);
      color: var(--sccc-event-primary);
    }

    .sccc-event-signup__card--founding .sccc-event-signup__wysiwyg ul > li::before {
      color: #fbbf24;
    }

    /* --------------------------------------------------------------------------
       Privacy note
       -------------------------------------------------------------------------- */

    .sccc-event-signup__privacy {
      margin: 1.45rem auto 0;
      max-width: 56rem;
      border: 1px solid var(--sccc-event-card-border);
      border-radius: 1rem;
      background: var(--sccc-event-card-bg);
      padding: 0.85rem 1.15rem;
      text-align: center;
      box-shadow: 0 8px 24px rgba(2,6,23,0.10);
    }

    .sccc-event-signup__privacy p {
      margin: 0;
      font-size: 0.8rem;
      line-height: 1.45;
      color: var(--sccc-event-body);
    }

    /* --------------------------------------------------------------------------
       iPad portrait first
       -------------------------------------------------------------------------- */

    @media (min-width: 768px) {
      .sccc-event-signup {
        background:
          radial-gradient(720px 340px at 50% 0%, color-mix(in srgb, var(--sccc-event-primary) 15%, transparent) 0%, transparent 72%),
          radial-gradient(620px 300px at 18% 74%, rgba(59,130,246,0.10) 0%, transparent 68%),
          radial-gradient(700px 340px at 84% 78%, rgba(168,85,247,0.08) 0%, transparent 70%),
          var(--sccc-event-bg);
      }

      .sccc-event-signup__intro-shell {
        margin-bottom: 3.15rem;
      }

      .sccc-event-signup__intro-shell--with-event {
        grid-template-columns: minmax(0, 1.08fr) minmax(18rem, 0.92fr);
      }

      .sccc-event-signup__club-logo {
        grid-column: 1 / -1;
      }

      .sccc-event-signup__after-text {
        grid-column: 1 / -1;
      }

      .sccc-event-signup__intro-shell--with-event .sccc-event-signup__hero-copy {
        text-align: left;
      }

      .sccc-event-signup__intro-shell--with-event .sccc-event-signup__intro {
        margin-left: 0;
        margin-right: 0;
      }

      .sccc-event-signup__intro-shell--with-event .sccc-event-signup__after-text {
        margin-left: 0;
        margin-right: 0;
        text-align: left;
      }

      .sccc-event-signup__event-context {
        justify-content: flex-start;
      }

      .sccc-event-signup__grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
        max-width: 58rem;
        margin-inline: auto;
      }

      .sccc-event-signup__card {
        padding: 1.05rem;
        border-radius: 1.05rem;
      }

      .sccc-event-signup__card-title {
        font-size: 1.15rem;
      }

      .sccc-event-signup__price {
        font-size: 2.15rem;
      }

      .sccc-event-signup__suffix {
        font-size: 0.78rem;
      }

      .sccc-event-signup__button {
        font-size: 0.78rem;
      }

      .sccc-event-signup__wysiwyg {
        font-size: 0.78rem;
      }
    }

    /* --------------------------------------------------------------------------
       iPad landscape / desktop growth
       -------------------------------------------------------------------------- */

    @media (min-width: 1024px) {
      .sccc-event-signup__intro-shell {
        margin-bottom: 3.35rem;
      }

      .sccc-event-signup__club-logo-img {
        height: clamp(7rem, 8.5vw, 8.75rem);
        max-width: min(30vw, 16rem);
      }

      .sccc-event-signup__intro-shell--with-event {
        max-width: 76rem;
        grid-template-columns: minmax(0, 1.18fr) minmax(20rem, 0.82fr);
        gap: 2rem;
      }

      .sccc-event-signup__event-logo {
        width: 6.8rem;
        height: 6.8rem;
      }

      .sccc-event-signup__event-title {
        font-size: 1.78rem;
      }

      .sccc-event-signup__grid {
        max-width: 68rem;
        gap: 1.35rem;
      }

      .sccc-event-signup__card {
        padding: 1.35rem;
        border-radius: 1.15rem;
      }

      .sccc-event-signup__card-title {
        font-size: 1.25rem;
      }

      .sccc-event-signup__price {
        font-size: 2.55rem;
      }

      .sccc-event-signup__button {
        font-size: 0.84rem;
      }

      .sccc-event-signup__wysiwyg {
        font-size: 0.86rem;
        line-height: 1.62;
      }
    }

    @media (min-width: 1280px) {
      .sccc-event-signup__grid {
        max-width: 74rem;
        gap: 1.75rem;
      }

      .sccc-event-signup__card {
        padding: 1.5rem;
      }

      .sccc-event-signup__card-title {
        font-size: 1.35rem;
      }

      .sccc-event-signup__price {
        font-size: 2.85rem;
      }
    }

    @media (max-width: 767.98px) {
      .sccc-event-signup__event-context {
        max-width: 28rem;
        margin-inline: auto;
      }
    }
  </style>

  <section class="sccc-event-signup relative min-h-[calc(100svh-3.5rem)] overflow-hidden py-4 sm:py-5 md:py-6 lg:py-7 xl:py-9">
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-blue-500/10 via-transparent to-transparent"></div>
    <div class="pointer-events-none absolute right-[-12rem] top-[-12rem] h-[32rem] w-[32rem] rounded-full bg-blue-600/20 blur-[110px]"></div>

    <div class="relative z-10 mx-auto max-w-6xl px-4 sm:px-5 lg:px-7 xl:max-w-7xl xl:px-8">
      <div class="sccc-event-signup__intro-shell {{ $hasEventContext ? 'sccc-event-signup__intro-shell--with-event' : 'sccc-event-signup__intro-shell--centered' }}">
        @if (! empty($clubLogoHtml))
          <div class="sccc-event-signup__club-logo">
            {!! $clubLogoHtml !!}
          </div>
        @endif

        <div class="sccc-event-signup__hero-copy">
          <h1 class="sccc-event-signup__title">
            Choose Your Membership
          </h1>

          <p class="sccc-event-signup__intro">
            Pick a level below to join today.
          </p>
        </div>

        @if ($hasEventContext)
          <aside class="sccc-event-signup__event-context" aria-label="Current signup event">
            @if (! empty($eventLogoHtml))
              <div class="sccc-event-signup__event-logo" aria-hidden="true">
                {!! $eventLogoHtml !!}
              </div>
            @else
              <div class="sccc-event-signup__event-logo" aria-hidden="true">
                <span class="sccc-event-signup__event-logo-fallback material-symbols-outlined">event</span>
              </div>
            @endif

            <div class="sccc-event-signup__event-copy">
              <h2 class="sccc-event-signup__event-title">
                {{ $eventName }}
              </h2>
            </div>
          </aside>
        @endif

        <p class="sccc-event-signup__after-text">
          <strong>After checkout,</strong>
          <span>check your email for login instructions and next steps.</span>
        </p>
      </div>

      @if (! $flowAvailable)
        <div class="mx-auto max-w-2xl rounded-2xl border border-red-500/30 bg-red-500/10 p-6 text-center text-[color:var(--sccc-event-heading)]">
          <h2 class="text-xl font-black">Event signup is not available.</h2>
          <p class="mt-2 text-sm text-[color:var(--sccc-event-body)]">
            The event signup support file is not loaded.
          </p>
        </div>
      @elseif (! $enabled)
        <div class="mx-auto max-w-2xl rounded-2xl border border-[color:var(--sccc-event-card-border)] bg-[var(--sccc-event-card-bg)] p-6 text-center text-[color:var(--sccc-event-heading)] shadow-[var(--sccc-event-shadow)]">
          <h2 class="text-xl font-black">Event signup is currently closed.</h2>
          <p class="mt-2 text-sm text-[color:var(--sccc-event-body)]">
            Please check with a Space City Car Club team member.
          </p>
        </div>
      @elseif (! $allowed)
        <div class="mx-auto max-w-2xl rounded-2xl border border-[color:var(--sccc-event-card-border)] bg-[var(--sccc-event-card-bg)] p-6 text-center text-[color:var(--sccc-event-heading)] shadow-[var(--sccc-event-shadow)]">
          <h2 class="text-xl font-black">This event signup link is not available.</h2>
          <p class="mt-2 text-sm text-[color:var(--sccc-event-body)]">
            Please ask a Space City Car Club team member for the correct signup link.
          </p>
        </div>
      @elseif (empty($plans))
        <div class="mx-auto max-w-2xl rounded-2xl border border-dashed border-[color:var(--sccc-event-card-border)] bg-[var(--sccc-event-card-bg)] p-6 text-center text-[color:var(--sccc-event-heading)]">
          <h2 class="text-xl font-black">No membership levels are available.</h2>
          <p class="mt-2 text-sm text-[color:var(--sccc-event-body)]">
            Confirm PMPro is active and that membership levels 2, 3, and 4 exist.
          </p>
        </div>
      @else
        <div class="sccc-event-signup__grid">
          @foreach ($plans as $plan)
            @php
              $isFeatured = !empty($plan['is_featured']);
              $isFounding = !empty($plan['is_founding']);
              $isSoldOut = ($plan['status'] ?? '') === 'sold_out';

              $rawSuffix = (string) ($plan['suffix'] ?? '');
              $cleanSuffix = trim(preg_replace('/^\s*\/\s*/', '', $rawSuffix));

              if ($isFounding && str_contains(mb_strtolower($cleanSuffix), 'one-time')) {
                  $displaySuffix = 'one-time founding membership';
              } else {
                  $displaySuffix = $rawSuffix;
              }

              $cardClasses = 'sccc-event-signup__card';

              if ($isFeatured) {
                $cardClasses .= ' sccc-event-signup__card--featured';
              }

              if ($isFounding) {
                $cardClasses .= ' sccc-event-signup__card--founding';
              }

              if ($isSoldOut) {
                $cardClasses .= ' sccc-event-signup__card--sold-out';
              }

              $badgeClasses = 'sccc-event-signup__badge';

              if ($isSoldOut) {
                $badgeClasses .= ' sccc-event-signup__badge--sold-out';
              } elseif ($isFounding) {
                $badgeClasses .= ' sccc-event-signup__badge--founding';
              } else {
                $badgeClasses .= ' sccc-event-signup__badge--featured';
              }

              $buttonClasses = 'sccc-event-signup__button';

              if ($isSoldOut) {
                $buttonClasses .= ' sccc-event-signup__button--disabled';
              } elseif ($isFounding) {
                $buttonClasses .= ' sccc-event-signup__button--founding';
              } elseif ($isFeatured) {
                $buttonClasses .= ' sccc-event-signup__button--featured';
              } else {
                $buttonClasses .= ' sccc-event-signup__button--default';
              }

              $badgeIcon = $isSoldOut ? 'block' : ($isFounding ? 'local_fire_department' : 'stars');
            @endphp

            <article class="{{ $cardClasses }}">
              @if (!empty($plan['badge']))
                <div class="{{ $badgeClasses }}">
                  <span class="material-symbols-outlined" aria-hidden="true">{{ $badgeIcon }}</span>
                  <span>{{ $plan['badge'] }}</span>
                </div>
              @endif

              <div class="sccc-event-signup__card-head">
                <h2 class="sccc-event-signup__card-title">
                  {{ $plan['name'] ?? 'Membership' }}
                </h2>

                <div class="sccc-event-signup__price-row">
                  @if (!empty($plan['price']))
                    <span class="sccc-event-signup__price">
                      {{ $plan['price'] }}
                    </span>
                  @endif

                  @if (!empty($displaySuffix))
                    <span class="sccc-event-signup__suffix">
                      {{ $displaySuffix }}
                    </span>
                  @endif
                </div>
              </div>

              <div class="sccc-event-signup__button-wrap">
                @if ($isSoldOut)
                  <div class="{{ $buttonClasses }}">
                    Sold Out
                  </div>
                @elseif (!empty($plan['cta_url']) && !empty($plan['cta_label']))
                  <a
                    href="{{ $plan['cta_url'] }}"
                    class="{{ $buttonClasses }}"
                    rel="nofollow"
                  >
                    {{ $plan['cta_label'] }}
                  </a>
                @endif
              </div>

              @if (!empty($plan['tagline']))
                <div class="sccc-event-signup__wysiwyg">
                  {!! wp_kses_post($plan['tagline']) !!}
                </div>
              @endif
            </article>
          @endforeach
        </div>

        <div class="sccc-event-signup__privacy">
          <p>
            For your privacy, this event terminal resets after each signup. You will not remain logged in on this device.
          </p>
        </div>
      @endif
    </div>
  </section>

  <script>
    (function(){
      // Avoid restoring stale form/page state from browser back-forward cache.
      window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
          window.location.reload();
        }
      });
    })();
  </script>
@endsection