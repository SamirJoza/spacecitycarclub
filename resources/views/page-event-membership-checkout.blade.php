{{--
  File: resources/views/page-event-membership-checkout.blade.php
  Template Name: Event Membership Checkout
  Purpose: This page-specific template is used to override the default PMPro membership checkout page when the user is checking out for an event.
  Version: 1.0.0
  Author: Samir Joza
  Date: 2026-07-13
  License: MIT License
  Copyright: 2026 Samir Joza

  -----------------------------------------------------------------------------
  Page-specific template for the PMPro membership checkout page.

  Why this exists:
  -----------------------------------------------------------------------------
  The event/tablet signup flow links users to:

    /membership-checkout/?level=2&sccc_event_signup=1

  Without this file, the checkout page uses the normal site layout, which brings
  back the full header, navigation, newsletter, and footer.

  This template conditionally uses:
  - layouts.kiosk when checkout is part of the onsite/tablet event flow
  - layouts.app for normal website membership checkout

  Important:
  -----------------------------------------------------------------------------
  This does not replace or override PMPro checkout logic.
  It only changes the Sage layout wrapper and scoped styling around the normal
  PMPro checkout page content.

  Current refinements:
  -----------------------------------------------------------------------------
  - Removes the inline JavaScript helper that was rendering as visible code.
  - Removes the "Already have an account? Log in here" CTA from the shared
    event-terminal checkout flow.
  - Aligns radio/checkbox controls with their labels.
  - Keeps the password requirement text compact under the password field area.
  - Makes "Show Password" readable as an inline action.
  - Makes Terms/About Us links clearly recognizable as links.
  - Keeps PMPro checkout logic, gateway logic, validation, and submit behavior
    untouched.

  If the checkout page slug ever changes, rename this file to match:
    resources/views/page-{checkout-page-slug}.blade.php
--}}

@php
  /**
   * Event checkout detection.
   * ---------------------------------------------------------------------------
   * We intentionally check both:
   * - the URL flag added by the event signup level cards
   * - the event signup cookie set by EventSignupFlow
   *
   * The cookie fallback matters because checkout/payment validation can reload or
   * repost the checkout page without preserving the original query string.
   *
   * This is layout-only detection. Event source/security logic remains in the
   * EventSignupFlow support class.
   */
  $eventSignupQuery = isset($_GET['sccc_event_signup'])
      ? sanitize_text_field(wp_unslash($_GET['sccc_event_signup']))
      : '';

  $hasEventSignupQuery = $eventSignupQuery === '1';
  $hasEventSignupCookie = ! empty($_COOKIE['sccc_event_signup']);

  $usesKioskLayout = $hasEventSignupQuery || $hasEventSignupCookie;
@endphp

@extends($usesKioskLayout ? 'layouts.kiosk' : 'layouts.app')

@section('content')
  @if ($usesKioskLayout)
    <style>
      /* ========================================================================
         SCCC Event Checkout — kiosk shell
         ======================================================================== */

      .sccc-event-checkout {
        --sccc-checkout-bg: #0f1520;
        --sccc-checkout-panel: rgba(255, 255, 255, 0.07);
        --sccc-checkout-field: rgba(15, 23, 42, 0.76);
        --sccc-checkout-field-hover: rgba(15, 23, 42, 0.9);
        --sccc-checkout-border: rgba(255, 255, 255, 0.13);
        --sccc-checkout-border-strong: rgba(255, 255, 255, 0.22);
        --sccc-checkout-heading: #ffffff;
        --sccc-checkout-body: #9fb0d0;
        --sccc-checkout-muted: rgba(255, 255, 255, 0.66);
        --sccc-checkout-primary: var(--color-primary, #2563eb);
        --sccc-checkout-error: #f87171;
        --sccc-checkout-success: #34d399;
        --sccc-checkout-warning: #fbbf24;

        min-height: calc(100svh - 3.5rem);
        padding: clamp(1.25rem, 3vw, 2.5rem) 1rem;
        background:
          radial-gradient(720px 340px at 50% 0%, color-mix(in srgb, var(--sccc-checkout-primary) 15%, transparent) 0%, transparent 72%),
          radial-gradient(700px 340px at 84% 78%, rgba(168, 85, 247, 0.08) 0%, transparent 70%),
          var(--sccc-checkout-bg);
      }

      .sccc-event-checkout__shell {
        width: min(100%, 58rem);
        margin-inline: auto;
      }

      .sccc-event-checkout__header {
        margin-bottom: clamp(1.15rem, 2.5vw, 1.85rem);
        text-align: center;
      }

      .sccc-event-checkout__title {
        margin: 0;
        color: var(--sccc-checkout-heading);
        font-size: clamp(2rem, 4.8vw, 3rem);
        font-weight: 950;
        line-height: 1;
        letter-spacing: -0.045em;
      }

      .sccc-event-checkout__intro {
        max-width: 42rem;
        margin: 0.75rem auto 0;
        color: var(--sccc-checkout-body);
        font-size: clamp(1rem, 1.75vw, 1.125rem);
        line-height: 1.5;
      }

      /*
       * The outer wrapper is intentionally not styled as another card.
       * PMPro already outputs logical checkout sections. Those sections become
       * the primary visual cards below.
       */
      .sccc-event-checkout__card {
        border: 0;
        border-radius: 0;
        background: transparent;
        box-shadow: none;
      }

      .sccc-event-checkout__content {
        padding: 0;
      }

      .sccc-event-checkout__privacy {
        margin-top: 1rem;
        text-align: center;
      }

      .sccc-event-checkout__privacy p {
        margin: 0;
        color: var(--sccc-checkout-muted);
        font-size: 0.84rem;
        line-height: 1.5;
      }

      /* ========================================================================
         PMPro generated checkout content — typography reset
         ======================================================================== */

      .sccc-event-checkout__content #pmpro_form,
      .sccc-event-checkout__content .pmpro,
      .sccc-event-checkout__content .pmpro_form {
        color: var(--sccc-checkout-body);
      }

      .sccc-event-checkout__content #pmpro_form a,
      .sccc-event-checkout__content .pmpro a,
      .sccc-event-checkout__content .pmpro_form a {
        color: var(--sccc-checkout-heading);
        font-weight: 850;
        text-decoration: underline;
        text-decoration-thickness: 1px;
        text-decoration-color: color-mix(in srgb, var(--sccc-checkout-primary) 70%, transparent);
        text-underline-offset: 0.2em;
      }

      .sccc-event-checkout__content #pmpro_form a:hover,
      .sccc-event-checkout__content .pmpro a:hover,
      .sccc-event-checkout__content .pmpro_form a:hover {
        color: #ffffff;
        text-decoration-color: #ffffff;
      }

      .sccc-event-checkout__content #pmpro_form h1,
      .sccc-event-checkout__content #pmpro_form h2,
      .sccc-event-checkout__content #pmpro_form h3,
      .sccc-event-checkout__content #pmpro_form h4,
      .sccc-event-checkout__content .pmpro h1,
      .sccc-event-checkout__content .pmpro h2,
      .sccc-event-checkout__content .pmpro h3,
      .sccc-event-checkout__content .pmpro h4,
      .sccc-event-checkout__content .pmpro_form h1,
      .sccc-event-checkout__content .pmpro_form h2,
      .sccc-event-checkout__content .pmpro_form h3,
      .sccc-event-checkout__content .pmpro_form h4 {
        color: var(--sccc-checkout-heading);
        font-weight: 950;
        letter-spacing: -0.025em;
      }

      .sccc-event-checkout__content #pmpro_form p,
      .sccc-event-checkout__content #pmpro_form li,
      .sccc-event-checkout__content .pmpro p,
      .sccc-event-checkout__content .pmpro li,
      .sccc-event-checkout__content .pmpro_form p,
      .sccc-event-checkout__content .pmpro_form li {
        color: var(--sccc-checkout-body);
      }

      /* ========================================================================
         Remove login CTA for shared event-terminal checkout
         ======================================================================== */

      .sccc-event-checkout__content .pmpro_actions_nav,
      .sccc-event-checkout__content .pmpro_actions_nav-left,
      .sccc-event-checkout__content .pmpro_actions_nav-right,
      .sccc-event-checkout__content .pmpro_checkout-field-login,
      .sccc-event-checkout__content .pmpro_login_wrap,
      .sccc-event-checkout__content .pmpro_checkout_login,
      .sccc-event-checkout__content #pmpro_user_fields .pmpro_actions_nav,
      .sccc-event-checkout__content #pmpro_user_fields .pmpro_checkout-field-login,
      .sccc-event-checkout__content #pmpro_user_fields p:has(a[href*="login"]) {
        display: none;
      }

      /* ========================================================================
         PMPro section/card styling
         ========================================================================
         First flatten plugin-default nested cards, then restyle only the primary
         checkout sections. This avoids the multiple-card-layer look.
         ======================================================================== */

      .sccc-event-checkout__content .pmpro_section,
      .sccc-event-checkout__content .pmpro_card,
      .sccc-event-checkout__content .pmpro_checkout,
      .sccc-event-checkout__content .pmpro_checkout_box,
      .sccc-event-checkout__content .pmpro_form_fieldset {
        border: 0;
        border-radius: 0;
        background: transparent;
        box-shadow: none;
      }

      .sccc-event-checkout__content .pmpro_section_content,
      .sccc-event-checkout__content .pmpro_card_content,
      .sccc-event-checkout__content .pmpro_checkout-fields,
      .sccc-event-checkout__content .pmpro_checkout_box > *,
      .sccc-event-checkout__content .pmpro_form_fields {
        background: transparent;
      }

      .sccc-event-checkout__content #pmpro_pricing_fields,
      .sccc-event-checkout__content #pmpro_user_fields,
      .sccc-event-checkout__content #pmpro_payment_method,
      .sccc-event-checkout__content #pmpro_billing_address_fields,
      .sccc-event-checkout__content #pmpro_payment_information_fields,
      .sccc-event-checkout__content #pmpro_tos_fields,
      .sccc-event-checkout__content #pmpro_form > .pmpro_section,
      .sccc-event-checkout__content #pmpro_form > .pmpro_card,
      .sccc-event-checkout__content #pmpro_form > .pmpro_checkout,
      .sccc-event-checkout__content #pmpro_form > .pmpro_checkout_box,
      .sccc-event-checkout__content #pmpro_form > .pmpro_form_fieldset,
      .sccc-event-checkout__content #pmpro_form > fieldset,
      .sccc-event-checkout__content #pmpro_form > .pmpro > .pmpro_section,
      .sccc-event-checkout__content #pmpro_form > .pmpro > .pmpro_card,
      .sccc-event-checkout__content #pmpro_form > .pmpro > .pmpro_checkout,
      .sccc-event-checkout__content #pmpro_form > .pmpro > .pmpro_checkout_box,
      .sccc-event-checkout__content #pmpro_form > .pmpro > .pmpro_form_fieldset {
        margin: 0 0 1.15rem;
        border: 1px solid var(--sccc-checkout-border);
        border-radius: 1.05rem;
        background:
          radial-gradient(420px 180px at 12% 0%, color-mix(in srgb, var(--sccc-checkout-primary) 9%, transparent), transparent 68%),
          linear-gradient(135deg, rgba(255, 255, 255, 0.075), rgba(255, 255, 255, 0.035)),
          var(--sccc-checkout-panel);
        box-shadow:
          0 1px 0 rgba(255, 255, 255, 0.08) inset,
          0 16px 42px rgba(0, 0, 0, 0.24);
        padding: clamp(1rem, 2.5vw, 1.55rem);
        color: var(--sccc-checkout-body);
        overflow: visible;
      }

      .sccc-event-checkout__content #pmpro_form > .pmpro_section:last-child,
      .sccc-event-checkout__content #pmpro_form > .pmpro_card:last-child,
      .sccc-event-checkout__content #pmpro_form > .pmpro_checkout:last-child,
      .sccc-event-checkout__content #pmpro_form > .pmpro_checkout_box:last-child,
      .sccc-event-checkout__content #pmpro_form > .pmpro_form_fieldset:last-child,
      .sccc-event-checkout__content #pmpro_form > fieldset:last-child {
        margin-bottom: 0;
      }

      .sccc-event-checkout__content #pmpro_form > .pmpro_section h2,
      .sccc-event-checkout__content #pmpro_form > .pmpro_card h2,
      .sccc-event-checkout__content #pmpro_form > .pmpro_checkout h2,
      .sccc-event-checkout__content #pmpro_form > .pmpro_checkout_box h2,
      .sccc-event-checkout__content #pmpro_form > .pmpro_form_fieldset legend,
      .sccc-event-checkout__content #pmpro_form > fieldset legend,
      .sccc-event-checkout__content #pmpro_pricing_fields h2,
      .sccc-event-checkout__content #pmpro_user_fields h2,
      .sccc-event-checkout__content #pmpro_payment_method h2,
      .sccc-event-checkout__content #pmpro_billing_address_fields h2,
      .sccc-event-checkout__content #pmpro_payment_information_fields h2 {
        margin-top: 0;
        margin-bottom: 1rem;
        color: var(--sccc-checkout-heading);
        font-size: clamp(1.18rem, 2vw, 1.45rem);
        font-weight: 950;
        line-height: 1.12;
      }

      /* ========================================================================
         PMPro notices/messages
         ======================================================================== */

      .sccc-event-checkout__content .pmpro_message,
      .sccc-event-checkout__content .pmpro_alert,
      .sccc-event-checkout__content .pmpro_error,
      .sccc-event-checkout__content .pmpro_success,
      .sccc-event-checkout__content .pmpro_warning {
        margin: 0 0 1rem;
        border-radius: 0.85rem;
        padding: 0.85rem 1rem;
        border: 1px solid var(--sccc-checkout-border);
        background:
          linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.035)),
          rgba(255, 255, 255, 0.055);
        color: var(--sccc-checkout-heading);
        font-size: 0.95rem;
        font-weight: 750;
        line-height: 1.45;
      }

      .sccc-event-checkout__content .pmpro_message a,
      .sccc-event-checkout__content .pmpro_alert a,
      .sccc-event-checkout__content .pmpro_error a,
      .sccc-event-checkout__content .pmpro_success a,
      .sccc-event-checkout__content .pmpro_warning a {
        color: #ffffff;
        text-decoration: underline;
        text-underline-offset: 0.18em;
      }

      .sccc-event-checkout__content .pmpro_error {
        border-color: color-mix(in srgb, var(--sccc-checkout-error) 54%, transparent);
        background:
          linear-gradient(135deg, color-mix(in srgb, var(--sccc-checkout-error) 14%, transparent), rgba(255, 255, 255, 0.035)),
          rgba(255, 255, 255, 0.055);
      }

      .sccc-event-checkout__content .pmpro_success {
        border-color: color-mix(in srgb, var(--sccc-checkout-success) 54%, transparent);
        background:
          linear-gradient(135deg, color-mix(in srgb, var(--sccc-checkout-success) 14%, transparent), rgba(255, 255, 255, 0.035)),
          rgba(255, 255, 255, 0.055);
      }

      .sccc-event-checkout__content .pmpro_warning,
      .sccc-event-checkout__content .pmpro_alert {
        border-color: color-mix(in srgb, var(--sccc-checkout-warning) 48%, transparent);
        background:
          linear-gradient(135deg, color-mix(in srgb, var(--sccc-checkout-warning) 12%, transparent), rgba(255, 255, 255, 0.035)),
          rgba(255, 255, 255, 0.055);
      }

      /* ========================================================================
         PMPro form fields
         ======================================================================== */

      .sccc-event-checkout__content .pmpro_checkout-field,
      .sccc-event-checkout__content .pmpro_form_field,
      .sccc-event-checkout__content .pmpro_field {
        color: var(--sccc-checkout-body);
      }

      .sccc-event-checkout__content label,
      .sccc-event-checkout__content .pmpro_form_label,
      .sccc-event-checkout__content .pmpro_checkout-field label,
      .sccc-event-checkout__content .pmpro_form_field label,
      .sccc-event-checkout__content .pmpro_field label {
        display: inline-block;
        margin-bottom: 0.4rem;
        color: color-mix(in srgb, var(--sccc-checkout-heading) 90%, var(--sccc-checkout-body));
        font-size: 0.88rem;
        font-weight: 850;
        line-height: 1.25;
      }

      .sccc-event-checkout__content .pmpro_asterisk,
      .sccc-event-checkout__content .pmpro_required,
      .sccc-event-checkout__content abbr.required {
        color: #fda4af;
        text-decoration: none;
      }

      .sccc-event-checkout__content .pmpro_form_hint,
      .sccc-event-checkout__content .pmpro_form_field-hint,
      .sccc-event-checkout__content .pmpro_checkout-field small,
      .sccc-event-checkout__content .pmpro_checkout-field .description,
      .sccc-event-checkout__content .pmpro_form_field .description {
        display: block;
        margin-top: 0.42rem;
        color: var(--sccc-checkout-muted);
        font-size: 0.78rem;
        line-height: 1.38;
      }

      .sccc-event-checkout__content input[type="text"],
      .sccc-event-checkout__content input[type="password"],
      .sccc-event-checkout__content input[type="email"],
      .sccc-event-checkout__content input[type="tel"],
      .sccc-event-checkout__content input[type="number"],
      .sccc-event-checkout__content input[type="url"],
      .sccc-event-checkout__content input[type="search"],
      .sccc-event-checkout__content select,
      .sccc-event-checkout__content textarea,
      .sccc-event-checkout__content .input,
      .sccc-event-checkout__content .pmpro_form_input {
        width: 100%;
        min-height: 2.9rem;
        border: 1px solid var(--sccc-checkout-border-strong);
        border-radius: 0.72rem;
        background:
          linear-gradient(180deg, rgba(255, 255, 255, 0.055), rgba(255, 255, 255, 0.018)),
          var(--sccc-checkout-field);
        color: var(--sccc-checkout-heading);
        padding: 0.68rem 0.82rem;
        font-size: 1rem;
        line-height: 1.3;
        box-shadow:
          0 1px 0 rgba(255, 255, 255, 0.08) inset,
          0 8px 18px rgba(0, 0, 0, 0.14);
        transition:
          border-color 160ms ease,
          background-color 160ms ease,
          box-shadow 160ms ease,
          color 160ms ease;
      }

      .sccc-event-checkout__content select {
        color-scheme: dark;
        padding-right: 2.2rem;
      }

      .sccc-event-checkout__content textarea {
        min-height: 7rem;
        resize: vertical;
      }

      .sccc-event-checkout__content input[type="text"]::placeholder,
      .sccc-event-checkout__content input[type="password"]::placeholder,
      .sccc-event-checkout__content input[type="email"]::placeholder,
      .sccc-event-checkout__content input[type="tel"]::placeholder,
      .sccc-event-checkout__content input[type="number"]::placeholder,
      .sccc-event-checkout__content input[type="url"]::placeholder,
      .sccc-event-checkout__content input[type="search"]::placeholder,
      .sccc-event-checkout__content textarea::placeholder {
        color: color-mix(in srgb, var(--sccc-checkout-muted) 72%, transparent);
      }

      .sccc-event-checkout__content input[type="text"]:hover,
      .sccc-event-checkout__content input[type="password"]:hover,
      .sccc-event-checkout__content input[type="email"]:hover,
      .sccc-event-checkout__content input[type="tel"]:hover,
      .sccc-event-checkout__content input[type="number"]:hover,
      .sccc-event-checkout__content input[type="url"]:hover,
      .sccc-event-checkout__content input[type="search"]:hover,
      .sccc-event-checkout__content select:hover,
      .sccc-event-checkout__content textarea:hover,
      .sccc-event-checkout__content .input:hover,
      .sccc-event-checkout__content .pmpro_form_input:hover {
        border-color: color-mix(in srgb, var(--sccc-checkout-primary) 44%, var(--sccc-checkout-border-strong));
        background:
          linear-gradient(180deg, rgba(255, 255, 255, 0.065), rgba(255, 255, 255, 0.02)),
          var(--sccc-checkout-field-hover);
      }

      .sccc-event-checkout__content input[type="text"]:focus,
      .sccc-event-checkout__content input[type="password"]:focus,
      .sccc-event-checkout__content input[type="email"]:focus,
      .sccc-event-checkout__content input[type="tel"]:focus,
      .sccc-event-checkout__content input[type="number"]:focus,
      .sccc-event-checkout__content input[type="url"]:focus,
      .sccc-event-checkout__content input[type="search"]:focus,
      .sccc-event-checkout__content select:focus,
      .sccc-event-checkout__content textarea:focus,
      .sccc-event-checkout__content .input:focus,
      .sccc-event-checkout__content .pmpro_form_input:focus {
        outline: none;
        border-color: color-mix(in srgb, var(--sccc-checkout-primary) 74%, #ffffff 12%);
        background:
          linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.025)),
          var(--sccc-checkout-field-hover);
        box-shadow:
          0 0 0 3px color-mix(in srgb, var(--sccc-checkout-primary) 25%, transparent),
          0 1px 0 rgba(255, 255, 255, 0.10) inset,
          0 12px 26px rgba(0, 0, 0, 0.18);
      }

      .sccc-event-checkout__content input:-webkit-autofill,
      .sccc-event-checkout__content input:-webkit-autofill:hover,
      .sccc-event-checkout__content input:-webkit-autofill:focus {
        -webkit-text-fill-color: var(--sccc-checkout-heading);
        -webkit-box-shadow: 0 0 0 1000px #172033 inset;
        caret-color: var(--sccc-checkout-heading);
      }

      /* ========================================================================
         Password row / strong password guidance
         ======================================================================== */

      .sccc-event-checkout__content #pmpro_user_fields,
      .sccc-event-checkout__content #pmpro_user_fields .pmpro_checkout-fields,
      .sccc-event-checkout__content #pmpro_user_fields .pmpro_form_fields,
      .sccc-event-checkout__content #pmpro_user_fields .pmpro_section_content,
      .sccc-event-checkout__content #pmpro_user_fields .pmpro_card_content {
        overflow: visible;
      }

      .sccc-event-checkout__content .pmpro_show_password,
      .sccc-event-checkout__content button.pmpro_show_password,
      .sccc-event-checkout__content .pmpro_checkout-field-password a,
      .sccc-event-checkout__content .pmpro_checkout-field-password button,
      .sccc-event-checkout__content .pmpro_form_field-password a,
      .sccc-event-checkout__content .pmpro_form_field-password button {
        color: var(--sccc-checkout-heading);
        font-size: 0.82rem;
        font-weight: 900;
        text-decoration: underline;
        text-decoration-color: color-mix(in srgb, var(--sccc-checkout-primary) 72%, transparent);
        text-underline-offset: 0.2em;
      }

      .sccc-event-checkout__content .pmpro_show_password:hover,
      .sccc-event-checkout__content button.pmpro_show_password:hover,
      .sccc-event-checkout__content .pmpro_checkout-field-password a:hover,
      .sccc-event-checkout__content .pmpro_checkout-field-password button:hover,
      .sccc-event-checkout__content .pmpro_form_field-password a:hover,
      .sccc-event-checkout__content .pmpro_form_field-password button:hover {
        color: #ffffff;
        text-decoration-color: #ffffff;
      }

      .sccc-event-checkout__content #pmpro_user_fields #pmprosp-password-notice,
      .sccc-event-checkout__content #pmpro_user_fields .pmprosp-password-notice,
      .sccc-event-checkout__content #pmpro_user_fields .pmpro_sp_password_notice,
      .sccc-event-checkout__content #pmpro_user_fields .pmpro_password_notice,
      .sccc-event-checkout__content #pmpro_user_fields .pmpro_password_strength_notice,
      .sccc-event-checkout__content #pmpro_user_fields .pmpro_form_field-password .pmpro_form_hint,
      .sccc-event-checkout__content #pmpro_user_fields .pmpro_form_field-password .pmpro_form_field-hint,
      .sccc-event-checkout__content #pmpro_user_fields .pmpro_checkout-field-password .pmpro_form_hint,
      .sccc-event-checkout__content #pmpro_user_fields .pmpro_checkout-field-password .pmpro_form_field-hint,
      .sccc-event-checkout__content #pmpro_user_fields .pmpro_checkout-field-password .description {
        display: block;
        margin-top: 0.6rem;
        max-width: 24rem;
        color: var(--sccc-checkout-muted);
        font-size: 0.76rem;
        line-height: 1.35;
        font-weight: 700;
      }

      .sccc-event-checkout__content #pmprosp-container,
      .sccc-event-checkout__content .pmpro_form-strong-password-indicator {
        margin-top: 0.55rem;
        margin-bottom: 0;
      }

      .sccc-event-checkout__content #pmprosp-password-strength {
        color: var(--sccc-checkout-heading);
        font-size: 0.78rem;
        font-weight: 900;
        line-height: 1;
      }

      .sccc-event-checkout__content .pmprosp-progressbar {
        height: 0.45rem;
        background-color: rgba(255, 255, 255, 0.20);
      }

      .sccc-event-checkout__content span.pmprosp-tooltip__password {
        color: var(--sccc-checkout-muted);
        border-bottom: 1px dotted color-mix(in srgb, var(--sccc-checkout-primary) 55%, transparent);
        cursor: help;
        font-size: 0.76rem;
        font-weight: 700;
        line-height: 1.2;
        vertical-align: baseline;
      }

      .sccc-event-checkout__content [data-tooltip]::before {
        background:
          linear-gradient(135deg, rgba(17, 24, 39, 0.98), rgba(30, 41, 59, 0.98));
        border: 1px solid var(--sccc-checkout-border);
        color: #ffffff;
        font-size: 0.8rem;
        line-height: 1.35;
        max-width: min(22rem, 80vw);
        text-align: left;
      }

      /* ========================================================================
         Checkboxes, radios, and agreement rows
         ======================================================================== */

      .sccc-event-checkout__content input[type="checkbox"],
      .sccc-event-checkout__content input[type="radio"] {
        flex: 0 0 auto;
        width: 1rem;
        height: 1rem;
        margin: 0;
        accent-color: var(--sccc-checkout-primary);
      }

      .sccc-event-checkout__content #pmpro_payment_method .pmpro_form_field-radio,
      .sccc-event-checkout__content #pmpro_payment_method .pmpro_checkout-field-radio,
      .sccc-event-checkout__content #pmpro_payment_method .pmpro_form_field,
      .sccc-event-checkout__content #pmpro_payment_method .pmpro_checkout-field,
      .sccc-event-checkout__content #pmpro_payment_method span,
      .sccc-event-checkout__content .pmpro_payment_method span,
      .sccc-event-checkout__content .pmpro_checkout-field-radio,
      .sccc-event-checkout__content .pmpro_form_field-radio {
        display: flex;
        align-items: center;
        gap: 0.62rem;
      }

      .sccc-event-checkout__content #pmpro_payment_method .pmpro_form_field-radio,
      .sccc-event-checkout__content #pmpro_payment_method .pmpro_checkout-field-radio,
      .sccc-event-checkout__content #pmpro_payment_method .pmpro_form_field,
      .sccc-event-checkout__content #pmpro_payment_method .pmpro_checkout-field,
      .sccc-event-checkout__content #pmpro_payment_method span,
      .sccc-event-checkout__content .pmpro_payment_method span {
        margin: 0.65rem 0;
      }

      .sccc-event-checkout__content #pmpro_payment_method label,
      .sccc-event-checkout__content .pmpro_checkout-field-radio label,
      .sccc-event-checkout__content .pmpro_form_field-radio label {
        margin: 0;
        color: var(--sccc-checkout-body);
        font-size: 0.95rem;
        font-weight: 750;
        line-height: 1.35;
      }

      .sccc-event-checkout__content #pmpro_tos_fields {
        display: flex;
        align-items: center;
        gap: 0.65rem;
      }

      .sccc-event-checkout__content #pmpro_tos_fields > * {
        margin: 0;
      }

      .sccc-event-checkout__content .pmpro_checkout-field-checkbox label,
      .sccc-event-checkout__content .pmpro_checkout-field-agree label,
      .sccc-event-checkout__content .pmpro_checkout-field-terms label,
      .sccc-event-checkout__content .pmpro_form_field-checkbox label,
      .sccc-event-checkout__content #pmpro_tos_fields label {
        display: inline-flex;
        align-items: center;
        gap: 0.62rem;
        margin: 0;
        color: var(--sccc-checkout-body);
        font-size: 0.95rem;
        font-weight: 750;
        line-height: 1.4;
      }

      .sccc-event-checkout__content .pmpro_checkout-field-checkbox label input,
      .sccc-event-checkout__content .pmpro_checkout-field-agree label input,
      .sccc-event-checkout__content .pmpro_checkout-field-terms label input,
      .sccc-event-checkout__content .pmpro_form_field-checkbox label input,
      .sccc-event-checkout__content #pmpro_tos_fields label input {
        margin: 0;
      }

      .sccc-event-checkout__content .pmpro_checkout-field-checkbox a,
      .sccc-event-checkout__content .pmpro_checkout-field-agree a,
      .sccc-event-checkout__content .pmpro_checkout-field-terms a,
      .sccc-event-checkout__content .pmpro_form_field-checkbox a,
      .sccc-event-checkout__content #pmpro_tos_fields a {
        color: #ffffff;
        font-weight: 950;
        text-decoration: underline;
        text-decoration-thickness: 1px;
        text-decoration-color: color-mix(in srgb, var(--sccc-checkout-primary) 82%, transparent);
        text-underline-offset: 0.2em;
      }

      /* ========================================================================
         PMPro layout polish
         ======================================================================== */

      .sccc-event-checkout__content .pmpro_checkout-fields {
        display: block;
      }

      .sccc-event-checkout__content .pmpro_cols-2,
      .sccc-event-checkout__content .pmpro_form_fields-2col,
      .sccc-event-checkout__content .pmpro_form_fieldset-2col .pmpro_form_fields {
        gap: 1rem;
        overflow: visible;
      }

      .sccc-event-checkout__content .pmpro_payment-expiration {
        display: flex;
        align-items: center;
        gap: 0.55rem;
      }

      .sccc-event-checkout__content .pmpro_payment-expiration select {
        min-width: 6.5rem;
      }

      .sccc-event-checkout__content hr {
        border: 0;
        border-top: 1px solid var(--sccc-checkout-border);
        margin: 1.25rem 0;
      }

      /* ========================================================================
         PMPro submit area / buttons
         ======================================================================== */

      .sccc-event-checkout__content .pmpro_submit,
      .sccc-event-checkout__content .pmpro_form_submit {
        margin-top: 1.2rem;
      }

      .sccc-event-checkout__content .pmpro_btn,
      .sccc-event-checkout__content input[type="submit"],
      .sccc-event-checkout__content button[type="submit"],
      .sccc-event-checkout__content #pmpro_btn-submit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 3rem;
        border: 1px solid color-mix(in srgb, var(--sccc-checkout-primary) 48%, transparent);
        border-radius: 0.72rem;
        background:
          radial-gradient(circle at 18% 20%, rgba(255, 255, 255, 0.18), transparent 34%),
          linear-gradient(135deg, var(--sccc-checkout-primary), #1d4ed8);
        color: #ffffff;
        padding: 0.78rem 1.1rem;
        font-size: 0.92rem;
        font-weight: 950;
        line-height: 1.2;
        text-decoration: none;
        box-shadow:
          0 14px 30px color-mix(in srgb, var(--sccc-checkout-primary) 22%, transparent),
          0 1px 0 rgba(255, 255, 255, 0.18) inset;
        cursor: pointer;
        transition:
          transform 160ms ease,
          filter 160ms ease,
          box-shadow 160ms ease;
      }

      .sccc-event-checkout__content .pmpro_btn:hover,
      .sccc-event-checkout__content input[type="submit"]:hover,
      .sccc-event-checkout__content button[type="submit"]:hover,
      .sccc-event-checkout__content #pmpro_btn-submit:hover {
        transform: translateY(-1px);
        filter: brightness(1.08);
        color: #ffffff;
      }

      .sccc-event-checkout__content .pmpro_btn:focus-visible,
      .sccc-event-checkout__content input[type="submit"]:focus-visible,
      .sccc-event-checkout__content button[type="submit"]:focus-visible,
      .sccc-event-checkout__content #pmpro_btn-submit:focus-visible {
        outline: none;
        box-shadow:
          0 0 0 3px color-mix(in srgb, var(--sccc-checkout-primary) 32%, transparent),
          0 14px 30px color-mix(in srgb, var(--sccc-checkout-primary) 22%, transparent),
          0 1px 0 rgba(255, 255, 255, 0.18) inset;
      }

      .sccc-event-checkout__content .pmpro_btn-cancel,
      .sccc-event-checkout__content .pmpro_btn-secondary {
        border-color: var(--sccc-checkout-border-strong);
        background:
          linear-gradient(135deg, rgba(255, 255, 255, 0.085), rgba(255, 255, 255, 0.035)),
          rgba(255, 255, 255, 0.06);
        box-shadow:
          0 1px 0 rgba(255, 255, 255, 0.10) inset,
          0 10px 22px rgba(0, 0, 0, 0.16);
      }

      /* ========================================================================
         Gateway/payment-specific iframe containers
         ======================================================================== */

      .sccc-event-checkout__content iframe,
      .sccc-event-checkout__content .StripeElement,
      .sccc-event-checkout__content .pmpro_payment_frame {
        border-radius: 0.72rem;
        max-width: 100%;
      }

      .sccc-event-checkout__content #Expiry {
        width: 100px !important;
      }
      /* ========================================================================
         Responsive refinement
         ======================================================================== */

      @media (max-width: 767.98px) {
        .sccc-event-checkout__content #pmpro_pricing_fields,
        .sccc-event-checkout__content #pmpro_user_fields,
        .sccc-event-checkout__content #pmpro_payment_method,
        .sccc-event-checkout__content #pmpro_billing_address_fields,
        .sccc-event-checkout__content #pmpro_payment_information_fields,
        .sccc-event-checkout__content #pmpro_tos_fields,
        .sccc-event-checkout__content #pmpro_form > .pmpro_section,
        .sccc-event-checkout__content #pmpro_form > .pmpro_card,
        .sccc-event-checkout__content #pmpro_form > .pmpro_checkout,
        .sccc-event-checkout__content #pmpro_form > .pmpro_checkout_box,
        .sccc-event-checkout__content #pmpro_form > .pmpro_form_fieldset,
        .sccc-event-checkout__content #pmpro_form > fieldset,
        .sccc-event-checkout__content #pmpro_form > .pmpro > .pmpro_section,
        .sccc-event-checkout__content #pmpro_form > .pmpro > .pmpro_card,
        .sccc-event-checkout__content #pmpro_form > .pmpro > .pmpro_checkout,
        .sccc-event-checkout__content #pmpro_form > .pmpro > .pmpro_checkout_box,
        .sccc-event-checkout__content #pmpro_form > .pmpro > .pmpro_form_fieldset {
          padding: 1rem;
        }

        .sccc-event-checkout__content .pmpro_payment-expiration {
          flex-wrap: wrap;
        }
      }
    </style>

    <section class="sccc-event-checkout">
      <div class="sccc-event-checkout__shell">
        <header class="sccc-event-checkout__header">
          <h1 class="sccc-event-checkout__title">
            Secure Membership Checkout
          </h1>

          <p class="sccc-event-checkout__intro">
            Complete your membership signup below. After checkout, check your email for login instructions and next steps.
          </p>
        </header>

        <div class="sccc-event-checkout__card">
          <div class="sccc-event-checkout__content">
            @while (have_posts())
              @php(the_post())
              @php(the_content())
            @endwhile
          </div>
        </div>

        <div class="sccc-event-checkout__privacy">
          <p>
            For your privacy, this event terminal resets after signup. You will not remain logged in on this device.
          </p>
        </div>
      </div>
    </section>
  @else
    @while (have_posts())
      @php(the_post())
      @php(the_content())
    @endwhile
  @endif
@endsection
