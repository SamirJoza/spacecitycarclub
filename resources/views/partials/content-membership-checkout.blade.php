{{--
  resources/views/partials/content-membership-checkout.blade.php
  File: resources/views/partials/content-membership-checkout.blade.php

  What this partial does
  -----------------------------------------------------------------------------
  - Renders the Membership Checkout page content (PMPro output) inside a themed
    wrapper that matches the SCCC dark/light theme system:
      Light:  <html data-theme="light">
      Dark:   <html data-theme="dark" class="dark">

  IMPORTANT
  -----------------------------------------------------------------------------
  - We do NOT rely on Tailwind's `dark:` variants here because they’re not
    applying reliably in your environment.
  - All styling is instance-scoped to this page wrapper to avoid collateral impact.

  Recent fix
  -----------------------------------------------------------------------------
  - Light theme inconsistencies were caused by a few elements still inheriting
    dark defaults (title color, glow overlay strength, cost/expiration blocks,
    and benefits icon color). Those are now overridden in the Light Theme section.
--}}

@php
  $uid = 'sccc-membership-checkout-' . uniqid();
@endphp

<style>
  /* ==========================================================================
     SCCC Membership Checkout — TUNING KNOBS (easy tweaks)
     ========================================================================== */

  #{{ $uid }}{
    --sccc-primary: var(--color-primary,#135bec);

    /* Dark page background (dialed back vs. bright wash) */
    --bg-base: #070d18;
    --bg-deep: #050a14;

    /* Background glow strength (lower = darker, more premium) */
    --glow-a: .12;     /* primary glow intensity */
    --glow-b: .08;     /* secondary glow intensity */

    /* Card glass */
    --card-top: rgba(255,255,255,.10);
    --card-bot: rgba(255,255,255,.06);
    --card-border: rgba(255,255,255,.12);
    --card-inset: rgba(255,255,255,.10);
    --card-shadow: rgba(0,0,0,.60);

    /* “Membership card” (benefits) insert */
    --benefits-top: rgba(19,91,236,.18);
    --benefits-bot: rgba(255,255,255,.06);
    --benefits-border: rgba(255,255,255,.14);

    /* Inputs */
    --field-bg: rgba(255,255,255,.08);
    --field-border: rgba(255,255,255,.14);
    --field-text: rgba(255,255,255,.92);
    --field-placeholder: rgba(255,255,255,.45);

    /* Copy */
    --text: rgba(255,255,255,.92);
    --muted: rgba(255,255,255,.65);
  }

  /* ==========================================================================
     Wrapper + background (DARK defaults are scoped here)
     ========================================================================== */

  #{{ $uid }}{
    position: relative;
    overflow: hidden;
    background: radial-gradient(1200px 520px at 50% -10%,
                  color-mix(in srgb, var(--sccc-primary) calc(var(--glow-a) * 100%), transparent) 0%,
                  transparent 62%),
                radial-gradient(900px 520px at 10% 110%,
                  rgba(255,255,255,var(--glow-b)) 0%,
                  transparent 65%),
                linear-gradient(180deg, var(--bg-base), var(--bg-deep));
  }

  /* Extra subtle “atmosphere” layer (keeps it glassy without getting bright) */
  #{{ $uid }}::before{
    content:"";
    position:absolute;
    inset:-2px;
    pointer-events:none;
    background:
      radial-gradient(900px 320px at 80% 10%,
        rgba(19,91,236,.10) 0%,
        rgba(19,91,236,0) 60%),
      radial-gradient(700px 260px at 20% 0%,
        rgba(255,255,255,.06) 0%,
        rgba(255,255,255,0) 60%);
    opacity: .55; /* dark default */
  }

  #{{ $uid }} a { text-decoration: none; }

  #{{ $uid }} .sccc-checkout-inner{
    position: relative;
    z-index: 2;
  }

  #{{ $uid }} .sccc-checkout-title{
    color: #fff;
    text-shadow: 0 22px 65px rgba(0,0,0,.55);
  }

  /* ==========================================================================
     PMPro base layout
     ========================================================================== */

  #{{ $uid }} .pmpro_section{
    margin: 0 auto;
    max-width: 760px;
    color: var(--text);
  }

  #{{ $uid }} .pmpro_form{
    margin-top: 18px;
  }

  /* PMPro message (top + bottom) */
  #{{ $uid }} .pmpro_message{
    border-radius: 14px;
    padding: 12px 14px;
    margin: 0 0 16px 0;

    background: rgba(15,23,42,.55);
    border: 1px solid rgba(255,255,255,.14);
    color: rgba(255,255,255,.90);

    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);

    box-shadow:
      0 16px 40px rgba(0,0,0,.45),
      0 1px 0 rgba(255,255,255,.10) inset;
  }

  #{{ $uid }} .pmpro_message a{
    color: color-mix(in srgb, var(--sccc-primary) 85%, white);
    font-weight: 800;
    text-decoration: underline;
    text-underline-offset: 3px;
  }

  /* ==========================================================================
     Glass cards (the “pop”)
     ========================================================================== */

  #{{ $uid }} .pmpro_card{
    position: relative;
    overflow: hidden;
    border-radius: 18px;
    border: 1px solid var(--card-border);

    background: linear-gradient(180deg, var(--card-top), var(--card-bot));
    backdrop-filter: blur(16px) saturate(140%);
    -webkit-backdrop-filter: blur(16px) saturate(140%);

    box-shadow:
      0 24px 70px var(--card-shadow),
      0 1px 0 var(--card-inset) inset;
    padding: 0;
  }

  /* “Sheen” highlight layer (this is what sells the glass) */
  #{{ $uid }} .pmpro_card::before{
    content:"";
    position:absolute;
    inset: -1px;
    pointer-events:none;
    background:
      radial-gradient(900px 280px at 15% 0%,
        rgba(255,255,255,.16) 0%,
        rgba(255,255,255,0) 58%),
      radial-gradient(680px 260px at 85% -10%,
        rgba(19,91,236,.14) 0%,
        rgba(19,91,236,0) 60%);
    opacity: .65;
  }

  #{{ $uid }} .pmpro_card_content{
    position: relative;
    z-index: 2;
    padding: 22px 22px 20px;
    background: transparent; /* critical to prevent “white blocks” */
  }

  /* Card headings */
  #{{ $uid }} .pmpro_card_title,
  #{{ $uid }} .pmpro_form_heading{
    color: #fff;
    font-weight: 900;
    letter-spacing: -0.01em;
    margin: 0 0 12px 0;
  }

  #{{ $uid }} .pmpro_card p,
  #{{ $uid }} .pmpro_card div{
    color: var(--muted);
  }

  /* ==========================================================================
     “Included Benefits” as a membership card insert (not blockquote)
     ========================================================================== */

  #{{ $uid }} .pmpro_level_description_text{
    position: relative;
    margin: 16px 0 18px;
    padding: 18px 18px 16px 18px;

    border-radius: 16px;
    border: 1px solid var(--benefits-border);
    background: linear-gradient(135deg, var(--benefits-top), var(--benefits-bot));

    box-shadow:
      0 18px 46px rgba(0,0,0,.45),
      0 1px 0 rgba(255,255,255,.14) inset;

    backdrop-filter: blur(14px) saturate(150%);
    -webkit-backdrop-filter: blur(14px) saturate(150%);

    transform: rotate(-0.25deg);
  }

  /* Small “pinned label” */
  #{{ $uid }} .pmpro_level_description_text::before{
    content: "Included Benefits";
    position: absolute;
    top: -12px;
    right: 16px;
    padding: 7px 12px;
    border-radius: 999px;

    font-size: 12px;
    font-weight: 900;
    letter-spacing: .02em;

    color: #0b1220; /* dark default */
    background: linear-gradient(135deg,
      color-mix(in srgb, var(--sccc-primary) 85%, white),
      rgba(168,85,247,.85)
    );

    box-shadow: 0 18px 40px rgba(0,0,0,.45);
  }

  /* Benefits list: Material Symbols “verified” as bullet (no inline <span> needed) */
  #{{ $uid }} .pmpro_level_description_text ul{
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 10px;
  }

  #{{ $uid }} .pmpro_level_description_text li{
    position: relative;
    padding-left: 28px;
    color: rgba(255,255,255,.85);
    font-size: 14px;
    line-height: 1.35;
  }

  #{{ $uid }} .pmpro_level_description_text li::before{
    content: "verified";
    position: absolute;
    left: 0;
    top: -1px;

    font-family: "Material Symbols Outlined";
    font-variation-settings: "FILL" 0, "wght" 600, "GRAD" 0, "opsz" 20;

    font-size: 20px;
    line-height: 1;

    color: color-mix(in srgb, var(--sccc-primary) 85%, white); /* dark default */
    opacity: .95;
  }

  /* ==========================================================================
     Cost + Expiration: make it unmistakable
     ========================================================================== */

  #{{ $uid }} #pmpro_level_cost{
    margin-top: 16px;
    display: grid;
    gap: 10px;
  }

  #{{ $uid }} .pmpro_level_cost_text,
  #{{ $uid }} .pmpro_level_expiration_text{
    border-radius: 14px;
    padding: 12px 14px;

    background: rgba(0,0,0,.20); /* dark default */
    border: 1px solid rgba(255,255,255,.12);

    box-shadow:
      0 14px 30px rgba(0,0,0,.35),
      0 1px 0 rgba(255,255,255,.10) inset;
  }

  #{{ $uid }} .pmpro_level_cost_text p,
  #{{ $uid }} .pmpro_level_expiration_text p{
    margin: 0;
    color: rgba(255,255,255,.82);
  }

  #{{ $uid }} .pmpro_level_cost_text strong{
    color: #fff;
    font-size: 1.25em;
    font-weight: 950;
    letter-spacing: -0.01em;
  }

  /* ==========================================================================
     Fields + button styling
     ========================================================================== */

  #{{ $uid }} .pmpro_form_label{
    color: rgba(255,255,255,.82);
    font-weight: 800;
  }

  #{{ $uid }} .pmpro_form_input{
    width: 100%;
    border-radius: 12px;
    border: 1px solid var(--field-border);
    background: var(--field-bg);
    color: var(--field-text);
    padding: 10px 12px;

    box-shadow:
      0 10px 24px rgba(0,0,0,.28),
      0 1px 0 rgba(255,255,255,.08) inset;

    outline: none;
  }

  #{{ $uid }} .pmpro_form_input::placeholder{
    color: var(--field-placeholder);
  }

  #{{ $uid }} .pmpro_form_input:focus{
    border-color: color-mix(in srgb, var(--sccc-primary) 55%, rgba(255,255,255,.25));
    box-shadow:
      0 0 0 3px color-mix(in srgb, var(--sccc-primary) 28%, transparent),
      0 12px 26px rgba(0,0,0,.30),
      0 1px 0 rgba(255,255,255,.10) inset;
  }

  #{{ $uid }} .pmpro_btn,
  #{{ $uid }} .pmpro_btn-submit-checkout{
    appearance: none;
    border: 0;
    cursor: pointer;
    border-radius: 12px;
    padding: 12px 16px;

    font-weight: 900;
    color: #fff;
    background: var(--sccc-primary);

    box-shadow:
      0 20px 50px rgba(0,0,0,.45),
      0 1px 0 rgba(255,255,255,.18) inset;
    transition: transform .15s ease, filter .15s ease;
  }

  #{{ $uid }} .pmpro_btn:hover,
  #{{ $uid }} .pmpro_btn-submit-checkout:hover{
    filter: brightness(1.05);
    transform: translateY(-1px);
  }

  /* ==========================================================================
     LIGHT THEME (when .dark is NOT present)
     ========================================================================== */

  html[data-theme="light"] #{{ $uid }}{
    --bg-base: #f6f8fc;
    --bg-deep: #eef2ff;

    --glow-a: .08;
    --glow-b: .05;

    --card-top: rgba(255,255,255,.92);
    --card-bot: rgba(255,255,255,.86);
    --card-border: rgba(2,6,23,.10);
    --card-inset: rgba(255,255,255,.55);
    --card-shadow: rgba(2,6,23,.14);

    --benefits-top: rgba(19,91,236,.12);
    --benefits-bot: rgba(255,255,255,.85);
    --benefits-border: rgba(2,6,23,.10);

    --field-bg: rgba(255,255,255,.95);
    --field-border: rgba(2,6,23,.16);
    --field-text: rgba(2,6,23,.92);
    --field-placeholder: rgba(2,6,23,.40);

    --text: rgba(2,6,23,.92);
    --muted: rgba(2,6,23,.70);
  }

  /* Light: title must be dark (was inheriting dark white) */
  html[data-theme="light"] #{{ $uid }} .sccc-checkout-title{
    color: #0f172a;
    text-shadow: 0 18px 40px rgba(15,23,42,.12);
  }

  /* Light: dial the atmosphere overlay down (dark strength made it look off) */
  html[data-theme="light"] #{{ $uid }}::before{
    opacity: .28;
  }

  html[data-theme="light"] #{{ $uid }} .pmpro_card_title,
  html[data-theme="light"] #{{ $uid }} .pmpro_form_heading{
    color: rgba(2,6,23,.92);
  }

  html[data-theme="light"] #{{ $uid }} .pmpro_message{
    background: rgba(255,255,255,.92);
    color: rgba(2,6,23,.82);
    border: 1px solid rgba(15,23,42,.10);
  }

  html[data-theme="light"] #{{ $uid }} .pmpro_form_label{
    color: rgba(2,6,23,.82);
  }

  /* Light: benefits text is dark, and icon should be pure primary (not mixed with white) */
  html[data-theme="light"] #{{ $uid }} .pmpro_level_description_text li{
    color: rgba(2,6,23,.80);
  }
  html[data-theme="light"] #{{ $uid }} .pmpro_level_description_text li::before{
    color: var(--sccc-primary);
    opacity: .95;
  }

  /* Light: the pinned label needs readable text on its gradient chip */
  html[data-theme="light"] #{{ $uid }} .pmpro_level_description_text::before{
    color: #ffffff;
  }

  /* Light: cost + expiration blocks were still using dark pill background */
  html[data-theme="light"] #{{ $uid }} .pmpro_level_cost_text,
  html[data-theme="light"] #{{ $uid }} .pmpro_level_expiration_text{
    background: rgba(255,255,255,.86);
    border: 1px solid rgba(15,23,42,.12);
    box-shadow:
      0 12px 30px rgba(15,23,42,.10),
      0 1px 0 rgba(255,255,255,.70) inset;
  }

  html[data-theme="light"] #{{ $uid }} .pmpro_level_cost_text p,
  html[data-theme="light"] #{{ $uid }} .pmpro_level_expiration_text p{
    color: rgba(2,6,23,.78);
  }

  html[data-theme="light"] #{{ $uid }} .pmpro_level_cost_text strong{
    color: rgba(2,6,23,.95);
  }

  /* ==========================================================================
     Reduced motion
     ========================================================================== */

  @media (prefers-reduced-motion: reduce){
    #{{ $uid }} .pmpro_btn,
    #{{ $uid }} .pmpro_btn-submit-checkout{
      transition: none;
    }
  }
</style>

<section id="{{ $uid }}" class="py-10 md:py-14">
  <div class="sccc-checkout-inner mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
    <header class="mb-6 text-center">
      <h1 class="sccc-checkout-title text-3xl md:text-4xl font-black tracking-tight">
        {{ get_the_title() }}
      </h1>
    </header>

    <div class="sccc-checkout-content">
      @php(the_content())
    </div>
  </div>
</section>
