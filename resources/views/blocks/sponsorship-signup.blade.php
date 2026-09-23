{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/blocks/sponsorship-signup.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the sponsorship tier grid and the connected Gravity Form area.
| - Provide a no-JS fallback via query-string URLs for tier selection.
| - Provide a JS enhancement that immediately sets the Gravity Forms tier field
|   and scrolls to the form when the field is present on the page.
| - Animate the sponsorship cards with a GSAP "dealer throw" entrance and a
|   featured-card sheen when the card group enters the viewport.
|
| Why this file exists:
| - The block class owns the data structure and normalization.
| - This view owns the output, the visual treatment, and the client-side
|   handoff between a tier button and the Gravity Forms field.
| - The sponsorship cards are a strong visual conversion element, so subtle
|   motion helps direct attention without making the section feel gimmicky.
|
| Notes:
| - Styling is intentionally scoped to `.sccc-sponsorship-signup` so this block
|   can be dropped into the page without leaking into other areas.
| - The section heading and intro above the cards are rendered here so the block
|   can match the original sponsorship layout.
| - The CSS uses semantic tier modifiers (`is-bronze`, `is-silver`, etc.) so
|   the accents follow the tier itself instead of the editor's card order.
| - The featured tier badge renders as a centered pill overlapping the top edge
|   of the featured card.
| - Light theme receives its own token overrides because the default card system
|   is intentionally dark-biased.
| - The top accent is handled by a real `border-top` on each card, which keeps
|   the rounded corners cleaner, especially on the light theme.
| - The featured sheen is clipped inside the featured card only, sits behind
|   the card content/button, and stays below the pill badge.
| - The GSAP block animation replays on enter and enter-back, and resets on
|   leave and leave-back so the sequence can run again.
| - The JS targets Gravity Forms fields using the standard DOM IDs:
|   `input_{form_id}_{field_id}`.
| - We do not manually read `$block->anchor` here because that property is not
|   available on this block class instance in your current setup. Instead, we
|   let the block wrapper attributes flow through `$attributes`.
| - The section background is intentionally full and not rounded, while the
|   cards and the form keep their own softer rounded corners.
--}}
<section
  {{ $attributes->merge(['class' => 'sccc-sponsorship-signup']) }}
  data-sccc-sponsor-signup
  data-sccc-form-id="{{ $form['id'] ?: '' }}"
  data-sccc-tier-field-id="{{ $form['tier_field_id'] ?: '' }}"
  data-sccc-param="{{ $form['parameter_name'] }}"
  data-sccc-anchor="{{ $form['anchor_id'] }}"
>
  @once
    <style>
      .sccc-sponsorship-signup {
        --sccc-bg: var(--color-background, transparent);
        --sccc-surface: var(--color-surface, #111827);
        --sccc-surface-2: color-mix(in oklab, var(--color-surface, #111827) 88%, black 12%);
        --sccc-text: var(--color-text, #ffffff);
        --sccc-muted: var(--color-muted, rgba(255, 255, 255, 0.72));
        --sccc-line: rgba(255, 255, 255, 0.12);
        --sccc-primary: var(--color-primary-500, #2979ff);
        --sccc-accent: var(--color-accent-500, #7dd3fc);
        --sccc-danger: #ef4444;
        --sccc-glow: 0 10px 30px color-mix(in oklab, var(--color-primary-500, #2979ff) 18%, transparent);
        color: var(--sccc-text);
      }

      html[data-theme="light"] .sccc-sponsorship-signup {
        --sccc-surface: #eef3f9;
        --sccc-surface-2: #ffffff;
        --sccc-text: #14213d;
        --sccc-muted: #62738f;
        --sccc-line: rgba(20, 33, 61, 0.12);
        --sccc-primary: #3b82f6;
        --sccc-accent: #67c7ff;
        --sccc-glow: 0 16px 36px color-mix(in oklab, var(--sccc-primary) 14%, transparent);
      }

      .sccc-sponsorship-signup__shell {
        position: relative;
        overflow: hidden;
        border-top: 1px solid var(--sccc-line);
        border-bottom: 1px solid var(--sccc-line);
        border-left: 0;
        border-right: 0;
        border-radius: 0;
        background:
          radial-gradient(circle at top right, color-mix(in oklab, var(--sccc-primary) 10%, transparent) 0%, transparent 30%),
          radial-gradient(circle at top left, color-mix(in oklab, var(--sccc-accent) 8%, transparent) 0%, transparent 25%),
          color-mix(in oklab, var(--sccc-surface) 92%, transparent);
        box-shadow: none;
      }

      html[data-theme="light"] .sccc-sponsorship-signup__shell {
        background:
          radial-gradient(circle at top right, color-mix(in oklab, var(--sccc-primary) 8%, white 92%) 0%, transparent 34%),
          radial-gradient(circle at top left, rgba(255, 255, 255, 0.88) 0%, transparent 24%),
          linear-gradient(180deg, rgba(255, 255, 255, 0.84), rgba(241, 246, 253, 0.96));
      }

      .sccc-sponsorship-signup__shell::before {
        content: "";
        position: absolute;
        inset: 0;
        pointer-events: none;
        background:
          linear-gradient(180deg, color-mix(in oklab, white 5%, transparent), transparent 18%);
        opacity: 0.9;
      }

      html[data-theme="light"] .sccc-sponsorship-signup__shell::before {
        background:
          linear-gradient(180deg, rgba(255, 255, 255, 0.72), rgba(255, 255, 255, 0) 18%);
        opacity: 1;
      }

      .sccc-sponsorship-signup__container {
        position: relative;
        z-index: 1;
        max-width: 90rem;
        margin-inline: auto;
        padding: clamp(1.25rem, 2.2vw, 2rem);
        padding-top: 4rem;
      }

      .sccc-sponsorship-signup__header {
        max-width: 48rem;
        margin: 0 auto 2rem;
        text-align: center;
      }

      .sccc-sponsorship-signup__heading {
        margin: 0 0 0.65rem;
        color: var(--sccc-text);
        font-size: clamp(1.9rem, 3.8vw, 3rem);
        line-height: 1.05;
        font-weight: 800;
        letter-spacing: -0.03em;
      }

      .sccc-sponsorship-signup__intro {
        color: var(--sccc-muted);
        line-height: 1.75;
        font-size: 1rem;
      }

      .sccc-sponsorship-signup__grid {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 1rem;
        align-items: stretch;
        perspective: 1400px;
      }

      @media (min-width: 700px) {
        .sccc-sponsorship-signup__grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }
      }

      @media (min-width: 1180px) {
        .sccc-sponsorship-signup__grid {
          grid-template-columns: repeat(4, minmax(0, 1fr));
        }
      }

      .sccc-sponsorship-signup__card {
        --tier-accent: var(--sccc-primary);
        --tier-accent-soft: var(--sccc-accent);
        position: relative;
        display: flex;
        flex-direction: column;
        min-height: 100%;
        padding: 1.6rem;
        overflow: visible;
        border: 1px solid var(--sccc-line);
        border-top: 3px solid var(--tier-accent);
        border-radius: 1.35rem;
        background:
          linear-gradient(180deg, color-mix(in oklab, white 2%, transparent), transparent 22%),
          color-mix(in oklab, var(--sccc-surface-2) 94%, transparent);
        box-shadow: 0 14px 28px rgba(0, 0, 0, 0.12);
        transition:
          transform 180ms ease,
          border-color 180ms ease,
          box-shadow 180ms ease,
          background 180ms ease;
        transform-style: preserve-3d;
      }

      html[data-theme="light"] .sccc-sponsorship-signup__card {
        background:
          radial-gradient(circle at top right, color-mix(in oklab, var(--tier-accent) 8%, transparent) 0%, transparent 34%),
          linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 249, 253, 0.92)),
          color-mix(in oklab, var(--sccc-surface-2) 98%, white 2%);
        box-shadow:
          0 16px 28px rgba(15, 23, 42, 0.08),
          0 4px 10px rgba(15, 23, 42, 0.04);
      }

      .sccc-sponsorship-signup__card::after {
        content: "";
        position: absolute;
        top: -3.75rem;
        right: -3rem;
        width: 11rem;
        height: 11rem;
        border-radius: 9999px;
        background: radial-gradient(
          circle,
          color-mix(in oklab, var(--tier-accent) 16%, transparent) 0%,
          transparent 66%
        );
        pointer-events: none;
      }

      html[data-theme="light"] .sccc-sponsorship-signup__card::after {
        background: radial-gradient(
          circle,
          color-mix(in oklab, var(--tier-accent) 12%, white 88%) 0%,
          transparent 68%
        );
      }

      .sccc-sponsorship-signup__card:hover {
        transform: translateY(-4px);
        border-color: color-mix(in oklab, var(--tier-accent) 38%, var(--sccc-line));
        border-top-color: var(--tier-accent);
        box-shadow:
          0 14px 28px rgba(0, 0, 0, 0.12),
          0 12px 30px color-mix(in oklab, var(--tier-accent) 12%, transparent);
      }

      html[data-theme="light"] .sccc-sponsorship-signup__card:hover {
        box-shadow:
          0 20px 38px rgba(15, 23, 42, 0.12),
          0 10px 20px color-mix(in oklab, var(--tier-accent) 10%, transparent);
      }

      .sccc-sponsorship-signup__card.is-bronze {
        --tier-accent: #cd7f32;
        --tier-accent-soft: #f5c48d;
      }

      .sccc-sponsorship-signup__card.is-silver {
        --tier-accent: #b8c1cc;
        --tier-accent-soft: #eef3f8;
      }

      .sccc-sponsorship-signup__card.is-gold {
        --tier-accent: #d4af37;
        --tier-accent-soft: #ffe7a0;
      }

      .sccc-sponsorship-signup__card.is-platinum {
        --tier-accent: #7cc7ff;
        --tier-accent-soft: #dff3ff;
      }

      .sccc-sponsorship-signup__card.is-featured {
        --tier-accent: var(--sccc-primary);
        --tier-accent-soft: color-mix(in oklab, var(--sccc-accent) 70%, white 30%);
        border-width: 2px;
        border-top-width: 3px;
        border-color: color-mix(in oklab, var(--sccc-primary) 70%, white 10%);
        border-top-color: var(--tier-accent);
        box-shadow:
          0 0 0 1px color-mix(in oklab, var(--sccc-primary) 35%, transparent),
          0 16px 34px color-mix(in oklab, var(--sccc-primary) 18%, transparent);
      }

      html[data-theme="light"] .sccc-sponsorship-signup__card.is-featured {
        background:
          radial-gradient(circle at top right, color-mix(in oklab, var(--tier-accent) 10%, white 90%) 0%, transparent 36%),
          linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(243, 248, 255, 0.94)),
          color-mix(in oklab, var(--sccc-surface-2) 98%, white 2%);
        box-shadow:
          0 0 0 1px color-mix(in oklab, var(--tier-accent) 30%, transparent),
          0 20px 40px color-mix(in oklab, var(--tier-accent) 14%, transparent),
          0 10px 22px rgba(15, 23, 42, 0.08);
      }

      .sccc-sponsorship-signup__featured-sheen-clip {
        position: absolute;
        inset: 0;
        border-radius: inherit;
        overflow: hidden;
        pointer-events: none;
        z-index: 1;
      }

      .sccc-sponsorship-signup__featured-sheen {
        position: absolute;
        top: -20%;
        bottom: -20%;
        left: -88%;
        width: 86%;
        opacity: 0;
        transform: skewX(-14deg) translate3d(0, 0, 0);
        background: linear-gradient(
          90deg,
          rgba(255, 255, 255, 0) 0%,
          rgba(255, 255, 255, 0.015) 10%,
          rgba(255, 255, 255, 0.03) 18%,
          rgba(255, 255, 255, 0.06) 28%,
          rgba(255, 255, 255, 0.10) 38%,
          rgba(255, 255, 255, 0.15) 46%,
          rgba(255, 255, 255, 0.18) 50%,
          rgba(255, 255, 255, 0.15) 54%,
          rgba(255, 255, 255, 0.10) 62%,
          rgba(255, 255, 255, 0.06) 72%,
          rgba(255, 255, 255, 0.03) 82%,
          rgba(255, 255, 255, 0.015) 90%,
          rgba(255, 255, 255, 0) 100%
        );
        filter: blur(16px);
      }

      html[data-theme="light"] .sccc-sponsorship-signup__featured-sheen {
        background: linear-gradient(
          90deg,
          rgba(255, 255, 255, 0) 0%,
          rgba(255, 255, 255, 0.03) 10%,
          rgba(255, 255, 255, 0.06) 18%,
          rgba(255, 255, 255, 0.10) 28%,
          rgba(255, 255, 255, 0.16) 38%,
          rgba(255, 255, 255, 0.24) 46%,
          rgba(255, 255, 255, 0.30) 50%,
          rgba(255, 255, 255, 0.24) 54%,
          rgba(255, 255, 255, 0.16) 62%,
          rgba(255, 255, 255, 0.10) 72%,
          rgba(255, 255, 255, 0.06) 82%,
          rgba(255, 255, 255, 0.03) 90%,
          rgba(255, 255, 255, 0) 100%
        );
        filter: blur(18px);
      }

      .sccc-sponsorship-signup__card-inner {
        position: relative;
        z-index: 2;
        display: flex;
        flex-direction: column;
        min-height: 100%;
      }

      .sccc-sponsorship-signup__card.is-featured .sccc-sponsorship-signup__card-inner {
        padding-top: 0.4rem;
      }

      .sccc-sponsorship-signup__featured-badge {
        position: absolute;
        top: 0;
        left: 50%;
        transform: translate(-50%, -50%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 2rem;
        padding: 0.45rem 0.95rem;
        border-radius: 9999px;
        border: 1px solid color-mix(in oklab, var(--tier-accent) 55%, white 10%);
        background:
          linear-gradient(180deg, color-mix(in oklab, white 10%, transparent), transparent 50%),
          color-mix(in oklab, var(--sccc-surface) 90%, transparent);
        color: var(--sccc-text);
        font-size: 0.74rem;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        white-space: nowrap;
        box-shadow:
          0 10px 22px color-mix(in oklab, var(--tier-accent) 16%, transparent),
          inset 0 1px 0 rgba(255, 255, 255, 0.08);
        z-index: 4;
        will-change: transform, opacity;
      }

      html[data-theme="light"] .sccc-sponsorship-signup__featured-badge {
        background:
          linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(245, 248, 253, 0.88)),
          color-mix(in oklab, var(--sccc-surface-2) 96%, white 4%);
        color: color-mix(in oklab, var(--tier-accent) 72%, #14213d);
        box-shadow:
          0 10px 20px color-mix(in oklab, var(--tier-accent) 10%, transparent),
          0 4px 10px rgba(15, 23, 42, 0.06);
      }

      .sccc-sponsorship-signup__tier-name {
        margin: 0 0 0.55rem;
        font-size: 1.325rem;
        line-height: 1.15;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: color-mix(in oklab, var(--tier-accent) 42%, var(--sccc-text));
      }

      .sccc-sponsorship-signup__price {
        display: flex;
        align-items: baseline;
        gap: 0.35rem;
        margin-bottom: 0.85rem;
      }

      .sccc-sponsorship-signup__price-main {
        font-size: 2rem;
        line-height: 1;
        font-weight: 800;
        color: var(--sccc-text);
      }

      .sccc-sponsorship-signup__price-suffix {
        color: var(--sccc-muted);
        font-size: 0.95rem;
        font-weight: 500;
      }

      .sccc-sponsorship-signup__description {
        margin: 0 0 1rem;
        color: var(--sccc-muted);
        line-height: 1.65;
      }

      .sccc-sponsorship-signup__benefits {
        display: grid;
        gap: 0.75rem;
        margin: 0 0 1.35rem;
        padding: 0;
        list-style: none;
      }

      .sccc-sponsorship-signup__benefits li {
        display: grid;
        grid-template-columns: 1rem 1fr;
        gap: 0.7rem;
        align-items: start;
        color: var(--sccc-text);
        line-height: 1.55;
      }

      .sccc-sponsorship-signup__benefits li::before {
        content: "";
        display: block;
        width: 0.7rem;
        height: 0.7rem;
        margin-top: 0.34rem;
        border-radius: 9999px;
        background: linear-gradient(135deg, var(--tier-accent), var(--tier-accent-soft));
        box-shadow: 0 0 0 0.25rem color-mix(in oklab, var(--tier-accent) 14%, transparent);
      }

      .sccc-sponsorship-signup__cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
        margin-top: auto;
        min-height: 3rem;
        padding: 0.85rem 1rem;
        border: 1px solid color-mix(in oklab, var(--tier-accent) 26%, var(--sccc-line));
        border-radius: 0.95rem;
        background:
          linear-gradient(180deg, color-mix(in oklab, white 4%, transparent), transparent 40%),
          color-mix(in oklab, var(--tier-accent) 14%, transparent);
        color: var(--sccc-text);
        text-decoration: none;
        font-weight: 700;
        transition:
          transform 180ms ease,
          border-color 180ms ease,
          background-color 180ms ease,
          box-shadow 180ms ease;
      }

      html[data-theme="light"] .sccc-sponsorship-signup__cta {
        background:
          linear-gradient(180deg, rgba(255, 255, 255, 0.55), rgba(255, 255, 255, 0) 48%),
          color-mix(in oklab, var(--tier-accent) 14%, white 86%);
        box-shadow:
          inset 0 1px 0 rgba(255, 255, 255, 0.75),
          0 6px 14px rgba(15, 23, 42, 0.04);
      }

      .sccc-sponsorship-signup__cta:hover,
      .sccc-sponsorship-signup__cta:focus-visible {
        transform: translateY(-1px);
        border-color: color-mix(in oklab, var(--tier-accent) 58%, white 10%);
        box-shadow: 0 12px 28px color-mix(in oklab, var(--tier-accent) 18%, transparent);
        outline: none;
      }

      html[data-theme="light"] .sccc-sponsorship-signup__cta:hover,
      html[data-theme="light"] .sccc-sponsorship-signup__cta:focus-visible {
        box-shadow:
          0 12px 24px color-mix(in oklab, var(--tier-accent) 12%, transparent),
          0 8px 18px rgba(15, 23, 42, 0.06);
      }

      .sccc-sponsorship-signup__form {
        width: 100%;
        max-width: 60rem;
        margin: 7rem auto 0;
        padding: clamp(1rem, 1.45vw, 1.35rem);
        border: 1px solid var(--sccc-line);
        border-radius: 1.5rem;
        background:
          radial-gradient(circle at top right, color-mix(in oklab, var(--sccc-primary) 10%, transparent) 0%, transparent 25%),
          color-mix(in oklab, var(--sccc-surface) 95%, transparent);
        box-shadow: 0 14px 30px rgba(0, 0, 0, 0.12);
      }

      html[data-theme="light"] .sccc-sponsorship-signup__form {
        background:
          radial-gradient(circle at top right, color-mix(in oklab, var(--sccc-primary) 8%, white 92%) 0%, transparent 28%),
          linear-gradient(180deg, rgba(255, 255, 255, 0.97), rgba(245, 248, 253, 0.93));
        box-shadow:
          0 20px 38px rgba(15, 23, 42, 0.10),
          0 6px 14px rgba(15, 23, 42, 0.04);
      }

      .sccc-sponsorship-signup__form-header {
        max-width: 40rem;
        margin: 0 auto 1.2rem;
        text-align: center;
      }

      .sccc-sponsorship-signup__form-heading {
        margin: 0 0 0.55rem;
        color: var(--sccc-text);
        font-size: clamp(1.5rem, 2.7vw, 2rem);
        line-height: 1.1;
        font-weight: 800;
        letter-spacing: -0.02em;
      }

      .sccc-sponsorship-signup__form-intro {
        color: var(--sccc-muted);
        line-height: 1.7;
      }

      .sccc-sponsorship-signup__notice {
        padding: 1rem 1.1rem;
        border: 1px dashed color-mix(in oklab, var(--sccc-primary) 30%, var(--sccc-line));
        border-radius: 1rem;
        background: color-mix(in oklab, var(--sccc-primary) 8%, transparent);
        color: var(--sccc-muted);
        line-height: 1.65;
      }

      .sccc-sponsorship-signup .gform_wrapper {
        margin: 0;
      }

      .sccc-sponsorship-signup .gform_wrapper .gform_required_legend {
        display: none;
      }

      .sccc-sponsorship-signup .gform_wrapper .gform_body {
        width: 100%;
      }

      .sccc-sponsorship-signup .gform_wrapper .gform_fields {
        --gf-form-gap-y: 1.4rem;
        --gf-form-gap-x: 1rem;
      }

      .sccc-sponsorship-signup .gform_wrapper :is(input:not([type="checkbox"]):not([type="radio"]), select, textarea) {
        min-height: 3rem;
        border: 1px solid color-mix(in oklab, var(--sccc-primary) 16%, var(--sccc-line));
        border-radius: 0.95rem;
        background: color-mix(in oklab, var(--sccc-surface-2) 96%, transparent);
        color: var(--sccc-text);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
      }

      html[data-theme="light"] .sccc-sponsorship-signup .gform_wrapper :is(input:not([type="checkbox"]):not([type="radio"]), select, textarea) {
        background: rgba(255, 255, 255, 0.92);
        box-shadow:
          inset 0 1px 0 rgba(255, 255, 255, 0.85),
          0 2px 6px rgba(15, 23, 42, 0.03);
      }

      .sccc-sponsorship-signup .gform_wrapper textarea {
        min-height: 8rem;
      }

      .sccc-sponsorship-signup .gform_wrapper :is(input, select, textarea)::placeholder {
        color: color-mix(in oklab, var(--sccc-muted) 82%, transparent);
      }

      .sccc-sponsorship-signup .gform_wrapper :is(input, select, textarea):focus {
        border-color: color-mix(in oklab, var(--sccc-primary) 60%, white 8%);
        box-shadow:
          0 0 0 0.2rem color-mix(in oklab, var(--sccc-primary) 12%, transparent),
          inset 0 1px 0 rgba(255, 255, 255, 0.03);
        outline: none;
      }

      .sccc-sponsorship-signup .gform_wrapper .gfield_label,
      .sccc-sponsorship-signup .gform_wrapper legend {
        color: var(--sccc-text);
        font-weight: 700;
      }

      .sccc-sponsorship-signup .gform_wrapper .gfield_required,
      .sccc-sponsorship-signup .gform_wrapper .gfield_required_text {
        color: var(--sccc-danger) !important;
      }

      .sccc-sponsorship-signup .gform_wrapper .gform_footer,
      .sccc-sponsorship-signup .gform_wrapper .gform_page_footer,
      .sccc-sponsorship-signup .gform_wrapper #field_submit {
        margin-top: 1.25rem;
      }

      .sccc-sponsorship-signup .gform_wrapper .gform_button,
      .sccc-sponsorship-signup .gform_wrapper button[type="submit"],
      .sccc-sponsorship-signup .gform_wrapper input[type="submit"],
      .sccc-sponsorship-signup .gform_wrapper .gform_footer input.button,
      .sccc-sponsorship-signup .gform_wrapper .gform_footer input[type="submit"],
      .sccc-sponsorship-signup .gform_wrapper .gform_page_footer input[type="submit"] {
        display: inline-block !important;
        width: auto !important;
        min-height: 3rem;
        padding: 0.9rem 1.7rem;
        border: 1px solid color-mix(in oklab, var(--sccc-primary) 45%, white 5%);
        border-radius: 9999px !important;
        -webkit-appearance: none;
        appearance: none;
        background: linear-gradient(135deg, color-mix(in oklab, var(--sccc-primary) 92%, black 8%), color-mix(in oklab, var(--sccc-accent) 40%, var(--sccc-primary) 60%));
        color: white;
        font-weight: 800;
        line-height: 1.1;
        box-shadow: var(--sccc-glow);
      }

      .sccc-sponsorship-signup .gform_wrapper .gform_button:hover,
      .sccc-sponsorship-signup .gform_wrapper button[type="submit"]:hover,
      .sccc-sponsorship-signup .gform_wrapper input[type="submit"]:hover,
      .sccc-sponsorship-signup .gform_wrapper .gform_footer input.button:hover,
      .sccc-sponsorship-signup .gform_wrapper .gform_footer input[type="submit"]:hover,
      .sccc-sponsorship-signup .gform_wrapper .gform_page_footer input[type="submit"]:hover {
        filter: brightness(1.05);
      }

      @media (max-width: 1024px) {
        .sccc-sponsorship-signup__form {
          max-width: 54rem;
        }
      }

      @media (max-width: 767px) {
        .sccc-sponsorship-signup__container {
          padding-top: 3rem;
        }

        .sccc-sponsorship-signup__header {
          margin-bottom: 1.5rem;
        }

        .sccc-sponsorship-signup__form {
          max-width: 100%;
          margin-top: 2.5rem;
          padding: 1rem;
        }

        .sccc-sponsorship-signup__form-header {
          margin-bottom: 1.1rem;
        }
      }
    </style>
  @endonce

  <div class="sccc-sponsorship-signup__shell">
    <div class="sccc-sponsorship-signup__container">
      @if ($heading || $intro)
        <header class="sccc-sponsorship-signup__header">
          @if ($heading)
            <h2 class="sccc-sponsorship-signup__heading">{{ $heading }}</h2>
          @endif

          @if ($intro)
            <div class="sccc-sponsorship-signup__intro">{!! $intro !!}</div>
          @endif
        </header>
      @endif

      @if ($tiers)
        <div class="sccc-sponsorship-signup__grid" data-sccc-sponsor-grid>
          @foreach ($tiers as $tier)
            <article
              class="sccc-sponsorship-signup__card {{ $tier['variant_class'] }} {{ $tier['is_featured'] ? 'is-featured' : '' }}"
              data-sccc-sponsor-card
            >
              @if ($tier['is_featured'])
                <span class="sccc-sponsorship-signup__featured-sheen-clip" aria-hidden="true">
                  <span class="sccc-sponsorship-signup__featured-sheen" data-sccc-featured-sheen></span>
                </span>
              @endif

              @if ($tier['is_featured'] && $tier['badge'])
                <div class="sccc-sponsorship-signup__featured-badge" data-sccc-featured-badge>
                  <span>{{ $tier['badge'] }}</span>
                </div>
              @endif

              <div class="sccc-sponsorship-signup__card-inner">
                <div>
                  <h3 class="sccc-sponsorship-signup__tier-name">{{ $tier['name'] }}</h3>

                  @if ($tier['formatted_price'])
                    <div class="sccc-sponsorship-signup__price">
                      <span class="sccc-sponsorship-signup__price-main">{{ $tier['formatted_price'] }}</span>

                      @if ($tier['price_suffix'])
                        <span class="sccc-sponsorship-signup__price-suffix">{{ $tier['price_suffix'] }}</span>
                      @endif
                    </div>
                  @endif

                  @if ($tier['description'])
                    <div class="sccc-sponsorship-signup__description">{!! $tier['description'] !!}</div>
                  @endif
                </div>

                @if ($tier['benefits'])
                  <ul class="sccc-sponsorship-signup__benefits">
                    @foreach ($tier['benefits'] as $benefit)
                      <li>{{ $benefit }}</li>
                    @endforeach
                  </ul>
                @endif

                <a
                  class="sccc-sponsorship-signup__cta"
                  href="{{ $tier['url'] }}"
                  data-sccc-sponsor-tier="{{ $tier['slug'] }}"
                >
                  <span>{{ $tier['button_label'] }}</span>
                  <span aria-hidden="true">→</span>
                </a>
              </div>
            </article>
          @endforeach
        </div>
      @elseif (is_admin())
        <div class="sccc-sponsorship-signup__notice">
          Add at least one sponsorship tier to preview this block.
        </div>
      @endif

      <div
        class="sccc-sponsorship-signup__form"
        id="{{ $form['anchor_id'] }}"
      >
        <div class="sccc-sponsorship-signup__form-header">
          <h3 class="sccc-sponsorship-signup__form-heading">{{ $form['heading'] }}</h3>

          @if ($form['intro'])
            <div class="sccc-sponsorship-signup__form-intro">{!! $form['intro'] !!}</div>
          @endif
        </div>

        @if ($form['html'])
          {!! $form['html'] !!}
        @else
          <div class="sccc-sponsorship-signup__notice">
            Add a valid Gravity Form ID to this block when you are ready to embed the sponsor application form.
          </div>
        @endif
      </div>
    </div>
  </div>

  @once
    <script>
      (() => {
        if (window.__scccSponsorSignupAnimationBooted) {
          return;
        }

        window.__scccSponsorSignupAnimationBooted = true;

        const loadScriptOnce = (src) => {
          const existing = document.querySelector(`script[src="${src}"]`);

          if (existing) {
            return new Promise((resolve, reject) => {
              if (existing.dataset.loaded === 'true') {
                resolve();
                return;
              }

              existing.addEventListener('load', () => resolve(), { once: true });
              existing.addEventListener('error', () => reject(new Error(`Failed to load ${src}`)), { once: true });
            });
          }

          return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.dataset.loaded = 'false';

            script.addEventListener('load', () => {
              script.dataset.loaded = 'true';
              resolve();
            }, { once: true });

            script.addEventListener('error', () => {
              reject(new Error(`Failed to load ${src}`));
            }, { once: true });

            document.head.appendChild(script);
          });
        };

        window.__scccGsapLoader = window.__scccGsapLoader || (async () => {
          if (!window.gsap) {
            await loadScriptOnce('https://cdn.jsdelivr.net/npm/gsap@3/dist/gsap.min.js');
          }

          if (!window.ScrollTrigger) {
            await loadScriptOnce('https://cdn.jsdelivr.net/npm/gsap@3/dist/ScrollTrigger.min.js');
          }

          return {
            gsap: window.gsap,
            ScrollTrigger: window.ScrollTrigger,
          };
        })();

        const initSponsorAnimations = (root, gsap, ScrollTrigger) => {
          if (!root || root.dataset.scccSponsorAnimInit === 'true') {
            return;
          }

          root.dataset.scccSponsorAnimInit = 'true';

          gsap.registerPlugin(ScrollTrigger);

          const grid = root.querySelector('[data-sccc-sponsor-grid]');

          if (!grid) {
            return;
          }

          const cards = Array.from(grid.querySelectorAll('[data-sccc-sponsor-card]'));

          if (!cards.length) {
            return;
          }

          const featuredCard = grid.querySelector('.sccc-sponsorship-signup__card.is-featured');
          const featuredBadge = featuredCard ? featuredCard.querySelector('[data-sccc-featured-badge]') : null;
          const featuredSheen = featuredCard ? featuredCard.querySelector('[data-sccc-featured-sheen]') : null;

          const mm = gsap.matchMedia(root);
          let activeTimeline = null;

          const getOffsets = (count, mobile) => {
            if (count === 1) return [0];
            if (count === 2) return mobile ? [-18, 18] : [-44, 44];
            if (count === 3) return mobile ? [-14, 0, 14] : [-56, 0, 56];
            return mobile ? [-14, -4, 4, 14] : [-62, -22, 22, 62];
          };

          const getRotations = (count, mobile) => {
            if (count === 1) return [0];
            if (count === 2) return mobile ? [-1.6, 1.6] : [-3.5, 3.5];
            if (count === 3) return mobile ? [-1.2, 0, 1.2] : [-3.5, 0, 3.5];
            return mobile ? [-1.5, -0.6, 0.6, 1.5] : [-4, -1.4, 1.4, 4];
          };

          const killSequence = () => {
            if (activeTimeline) {
              activeTimeline.kill();
              activeTimeline = null;
            }
          };

          const resetSequence = () => {
            killSequence();

            const mobile = window.matchMedia('(max-width: 767px)').matches;
            const offsets = getOffsets(cards.length, mobile);
            const rotations = getRotations(cards.length, mobile);

            gsap.set(cards, {
              autoAlpha: 0,
              x: (index) => offsets[index] ?? 0,
              y: mobile ? 34 : 44,
              rotation: (index) => rotations[index] ?? 0,
              scale: 0.992,
              transformOrigin: '50% 50%',
            });

            if (featuredBadge) {
              gsap.set(featuredBadge, {
                xPercent: -50,
                yPercent: -50,
                y: 0,
                autoAlpha: 0,
                scale: 0.94,
                transformOrigin: '50% 50%',
              });
            }

            if (featuredSheen) {
              gsap.set(featuredSheen, {
                xPercent: -138,
                autoAlpha: 0,
              });
            }
          };

          const addSheenPass = (timeline, startAt) => {
            if (!featuredSheen) {
              return;
            }

            const sheenPass = gsap.timeline({
              repeat: 2,
              repeatDelay: 0.18,
            });

            sheenPass
              .fromTo(featuredSheen, {
                xPercent: -138,
                autoAlpha: 0,
              }, {
                xPercent: 148,
                duration: 2.35,
                ease: 'power1.inOut',
              }, 0)
              .to(featuredSheen, {
                autoAlpha: 0.42,
                duration: 0.52,
                ease: 'sine.out',
              }, 0)
              .to(featuredSheen, {
                autoAlpha: 0.42,
                duration: 1.08,
                ease: 'none',
              }, 0.52)
              .to(featuredSheen, {
                autoAlpha: 0,
                duration: 0.75,
                ease: 'sine.inOut',
              }, 1.60);

            timeline.add(sheenPass, startAt);
          };

          const buildSequence = () => {
            killSequence();
            resetSequence();

            activeTimeline = gsap.timeline({
              paused: true,
              defaults: {
                overwrite: true,
              },
            });

            activeTimeline.to(cards, {
              autoAlpha: 1,
              x: 0,
              y: 0,
              rotation: 0,
              scale: 1,
              duration: 1.02,
              ease: 'power4.out',
              stagger: {
                each: 0.16,
              },
            }, 0);

            if (featuredBadge) {
              activeTimeline.to(featuredBadge, {
                xPercent: -50,
                yPercent: -50,
                y: 0,
                autoAlpha: 1,
                scale: 1,
                duration: 0.42,
                ease: 'power3.out',
              }, 0.86);
            }

            if (featuredSheen) {
              addSheenPass(activeTimeline, 1.08);
            }

            return activeTimeline;
          };

          const playSequence = () => {
            const tl = buildSequence();
            tl.play(0);
          };

          mm.add('(prefers-reduced-motion: no-preference)', () => {
            resetSequence();

            const trigger = ScrollTrigger.create({
              trigger: grid,
              start: 'top 82%',
              end: 'bottom 12%',
              onEnter: () => playSequence(),
              onEnterBack: () => playSequence(),
              onLeave: () => resetSequence(),
              onLeaveBack: () => resetSequence(),
            });

            ScrollTrigger.refresh();

            return () => {
              trigger.kill();
              resetSequence();

              gsap.set(cards, {
                clearProps: 'all',
                autoAlpha: 1,
                x: 0,
                y: 0,
                rotation: 0,
                scale: 1,
              });

              if (featuredBadge) {
                gsap.set(featuredBadge, {
                  clearProps: 'all',
                  xPercent: -50,
                  yPercent: -50,
                  y: 0,
                  autoAlpha: 1,
                  scale: 1,
                });
              }

              if (featuredSheen) {
                gsap.set(featuredSheen, {
                  clearProps: 'all',
                  autoAlpha: 0,
                  xPercent: -138,
                });
              }
            };
          });

          mm.add('(prefers-reduced-motion: reduce)', () => {
            killSequence();

            gsap.set(cards, {
              clearProps: 'all',
              autoAlpha: 1,
              x: 0,
              y: 0,
              rotation: 0,
              scale: 1,
            });

            if (featuredBadge) {
              gsap.set(featuredBadge, {
                clearProps: 'all',
                xPercent: -50,
                yPercent: -50,
                y: 0,
                autoAlpha: 1,
                scale: 1,
              });
            }

            if (featuredSheen) {
              gsap.set(featuredSheen, {
                clearProps: 'all',
                autoAlpha: 0,
                xPercent: -138,
              });
            }
          });
        };

        const bootSponsorAnimations = () => {
          window.__scccGsapLoader
            .then(({ gsap, ScrollTrigger }) => {
              document
                .querySelectorAll('[data-sccc-sponsor-signup]')
                .forEach((root) => initSponsorAnimations(root, gsap, ScrollTrigger));
            })
            .catch(() => {});
        };

        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', bootSponsorAnimations, { once: true });
        } else {
          bootSponsorAnimations();
        }

        window.addEventListener('load', bootSponsorAnimations, { once: true });
      })();
    </script>
  @endonce

  @once
    <script>
      (() => {
        if (window.__scccSponsorSignupBound) {
          return;
        }

        window.__scccSponsorSignupBound = true;

        const getField = (root) => {
          const formId = root.dataset.scccFormId;
          const fieldId = root.dataset.scccTierFieldId;

          if (!formId || !fieldId) {
            return null;
          }

          return (
            document.getElementById(`input_${formId}_${fieldId}`) ||
            root.querySelector(`[name="input_${fieldId}"]`)
          );
        };

        const setFieldValue = (root, value) => {
          const field = getField(root);

          if (!field || !value) {
            return false;
          }

          const tagName = field.tagName.toLowerCase();
          const type = (field.getAttribute('type') || '').toLowerCase();

          if (tagName === 'select' || ['hidden', 'text'].includes(type)) {
            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
          }

          return false;
        };

        const scrollToForm = (root) => {
          const anchorId = root.dataset.scccAnchor;
          const target = anchorId ? document.getElementById(anchorId) : null;

          if (target) {
            target.scrollIntoView({
              behavior: 'smooth',
              block: 'start',
            });
          }
        };

        const applyQueryStringValue = (root) => {
          const param = root.dataset.scccParam || 'sccc_sponsor_tier';
          const params = new URLSearchParams(window.location.search);
          const value = params.get(param);

          if (!value) {
            return;
          }

          setFieldValue(root, value);
        };

        const initBlocks = () => {
          document
            .querySelectorAll('[data-sccc-sponsor-signup]')
            .forEach((root) => applyQueryStringValue(root));
        };

        document.addEventListener('click', (event) => {
          const trigger = event.target.closest('[data-sccc-sponsor-tier]');

          if (!trigger) {
            return;
          }

          const root = trigger.closest('[data-sccc-sponsor-signup]');

          if (!root) {
            return;
          }

          const value = trigger.dataset.scccSponsorTier || '';
          const applied = setFieldValue(root, value);

          if (!applied) {
            return;
          }

          event.preventDefault();

          const param = root.dataset.scccParam || 'sccc_sponsor_tier';
          const anchorId = root.dataset.scccAnchor || 'contact';
          const url = new URL(window.location.href);

          url.searchParams.set(param, value);
          url.hash = anchorId;

          window.history.replaceState({}, '', url.toString());

          scrollToForm(root);
        });

        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', initBlocks);
        } else {
          initBlocks();
        }
      })();
    </script>
  @endonce
</section>