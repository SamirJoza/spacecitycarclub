{{--
  File: resources/views/blocks/hero.blade.php

  Renders the Space City Car Club Hero block. This view keeps the visual rules
  scoped to the individual Hero instance so multiple Hero blocks can appear on
  the same page without fighting over overlay, text, CTA buttons, or motion settings.
--}}

@php
  $uid = 'sccc-hero-' . uniqid();

  // ---------------------------------------------------------------------------
  // Alignment helpers
  // ---------------------------------------------------------------------------
  // These classes keep the editor-facing alignment setting mapped to the flex
  // and text alignment utilities already used by the existing Hero block.
  $align = $text_align ?? 'center';
  $contentAlignClass =
      $align === 'left'  ? 'items-start text-left'
    : ($align === 'right' ? 'items-end text-right'
    : 'items-center text-center');

  $ctaJustify =
      $align === 'center' ? 'justify-center'
    : ($align === 'right' ? 'justify-end'
    : 'justify-start');

  $mode = $mode ?? 'sliced';
  $motionEnabled = !empty($motion_enabled);
  $scrollLen = (int) ($scroll_len_px ?? 1100);
  $scaleTo   = (float) ($scale_to ?? 1.35);

  // Height variable from PHP.
  $minHeight = $min_height ?? '100vh';

  // ---------------------------------------------------------------------------
  // Theme-aware overlay values
  // ---------------------------------------------------------------------------
  // Defaults come from app/Blocks/Hero.php: dark = black, light = white.
  // The CSS below flips the active overlay color based on html[data-theme].
  $overlayOpacity = $overlay_opacity ?? '0.35';
  $overlayDarkRgb = $overlay_color_dark_rgb ?? '0 0 0';
  $overlayLightRgb = $overlay_color_light_rgb ?? '255 255 255';

  $hasBg = !empty($bg_url);
@endphp

<section
  id="{{ $uid }}"
  data-hero
  data-mode="{{ esc_attr($mode) }}"
  data-motion="{{ $motionEnabled ? '1' : '0' }}"
  data-scroll-len="{{ $scrollLen }}"
  data-scale-to="{{ $scaleTo }}"
  style="--hero-overlay-opacity: {{ $overlayOpacity }}; --hero-overlay-dark-rgb: {{ $overlayDarkRgb }}; --hero-overlay-light-rgb: {{ $overlayLightRgb }}; --hero-min-h: {{ $minHeight }};"
  class="relative overflow-hidden isolate"
>
  {{-- Background --}}
  @if ($hasBg)
    <div class="absolute inset-0 z-0">
      @if ($mode === 'sliced')
        {{-- Sliced wall: 6 columns, same image sliced via offsets --}}
        <div class="sccc-hero__images absolute inset-0 grid grid-cols-6" aria-hidden="true">
          @for ($i = 0; $i < 6; $i++)
            <div class="sccc-hero__slice relative overflow-hidden">
              <img
                class="sccc-hero__img absolute top-0 left-0 h-full object-cover"
                src="{{ esc_url($bg_url) }}"
                alt="{{ $i === 0 ? esc_attr($bg_alt) : '' }}"
                loading="eager"
                decoding="async"
              >
            </div>
          @endfor
        </div>
      @else
        {{-- Standard: single image background --}}
        <div class="absolute inset-0">
          <img
            class="h-full w-full object-cover"
            src="{{ esc_url($bg_url) }}"
            alt="{{ esc_attr($bg_alt) }}"
            loading="eager"
            decoding="async"
          >
        </div>
      @endif

      {{-- Theme-aware overlay --}}
      <div class="sccc-hero__overlay absolute inset-0" aria-hidden="true"></div>

      {{-- Subtle glow wash (static) --}}
      <div
        class="absolute inset-0 pointer-events-none"
        aria-hidden="true"
        style="background: radial-gradient(60% 50% at 50% 40%, rgba(19,91,236,.22) 0%, rgba(19,91,236,0) 70%);"
      ></div>
    </div>
  @endif

  {{-- Content --}}
  <div
    class="relative z-10 flex w-full px-4 md:px-10"
    style="min-height: var(--hero-min-h);"
  >
    <div class="mx-auto flex w-full max-w-[960px] flex-col justify-center gap-5 py-16 md:py-20 {{ $contentAlignClass }}">
      @if (!empty($logo_url))
        <div class="flex">
          <img
            src="{{ esc_url($logo_url) }}"
            alt="Logo"
            class="h-auto w-[clamp(120px,20vw,320px)] drop-shadow"
            loading="eager"
            decoding="async"
          >
        </div>
      @endif

      @if (!empty($kicker))
        <div class="inline-flex">
          <span class="sccc-hero__kicker rounded-full px-4 py-1 text-xs font-semibold tracking-wider backdrop-blur-sm">
            {{ $kicker }}
          </span>
        </div>
      @endif

      @if (!empty($headline_rendered))
        <h1 class="sccc-hero__headline font-black leading-[1.03] tracking-[-0.03em] text-5xl md:text-7xl">
          {!! $headline_rendered !!}
        </h1>
      @endif

      @if (!empty($subhead))
        <p class="sccc-hero__subhead max-w-2xl text-base md:text-xl leading-relaxed">
          {!! nl2br(e($subhead)) !!}
        </p>
      @endif

      @if (!empty($cta_primary_label) && !empty($cta_primary_url))
        <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 {{ $ctaJustify }}">
          <a
            href="{{ esc_url($cta_primary_url) }}"
            class="sccc-hero__cta sccc-hero__primary-cta inline-flex h-12 items-center justify-center px-8 text-base font-bold focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
          >
            {{ $cta_primary_label }}
          </a>

          @if (!empty($cta_secondary_label) && !empty($cta_secondary_url))
            <a
              href="{{ esc_url($cta_secondary_url) }}"
              class="sccc-hero__cta sccc-hero__secondary-cta inline-flex h-12 items-center justify-center px-8 text-base font-bold backdrop-blur-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
            >
              {{ $cta_secondary_label }}
            </a>
          @endif
        </div>
      @endif
    </div>
  </div>

  {{-- Minimal scoped CSS needed for slicing math, theme-aware overlay, theme-aware text, and CTA styling --}}
  <style>
    #{{ $uid }} {
      transform: translateZ(0);

      /*
       * Overlay defaults to the dark-theme color so browsers without the project
       * data-theme attribute still render the safer dark overlay.
       */
      --hero-overlay-active-rgb: var(--hero-overlay-dark-rgb);

      /*
       * Hero copy defaults to light text for the dark theme. In light mode the
       * text flips to dark below, matching the site-wide theme behavior.
       */
      --hero-text-rgb: 255 255 255;
      --hero-muted-text-opacity: 0.86;
      --hero-control-bg-rgb: 255 255 255;
      --hero-control-border-rgb: 255 255 255;
      --hero-control-bg-opacity: 0.10;
      --hero-control-border-opacity: 0.20;

      /*
       * CTA styling tokens.
       *
       * The primary CTA is still the "blue" button, but it now behaves more
       * like the rest of the SCCC theme: neon edge, glassy core, deeper shadow,
       * and a stronger hover glow instead of a flat default-blue rectangle.
       */
      --hero-primary-color: var(--color-primary-500, var(--color-primary, var(--primary, #135bec)));
      --hero-neon-cyan: #43beff;
      --hero-neon-purple: #ae71ff;
      --hero-cta-radius: 999px;
      --hero-cta-gradient-border: linear-gradient(
        90deg,
        rgba(19, 91, 236, 0) 0%,
        var(--hero-primary-color) 18%,
        var(--hero-neon-cyan) 52%,
        var(--hero-neon-purple) 84%,
        rgba(174, 113, 255, 0) 100%
      );
      --hero-cta-blue-glow: color-mix(in srgb, var(--hero-primary-color) 42%, transparent);
      --hero-cta-cyan-glow: rgba(67, 190, 255, .34);
      --hero-cta-purple-glow: rgba(174, 113, 255, .22);

      /*
       * Shared highlight options for this block.
       * These names match the ACF editor labels in app/Blocks/Hero.php.
       */
      --sccc-gradient-club-blue: linear-gradient(90deg, #135bec 0%, #1f8fff 52%, #71d7ff 100%);
      --sccc-gradient-signal-red: linear-gradient(90deg, #b31324 0%, #ff1744 50%, #ff7a59 100%);
      --sccc-gradient-space-city: linear-gradient(90deg, #135bec 0%, #43beff 42%, #ae71ff 72%, #ff1744 100%);
      --sccc-color-club-blue: #135bec;
      --sccc-color-signal-red: #e53935;
      --sccc-color-deep-purple: #ae71ff;
    }

    html[data-theme="dark"] #{{ $uid }},
    .dark #{{ $uid }} {
      --hero-overlay-active-rgb: var(--hero-overlay-dark-rgb);
      --hero-text-rgb: 255 255 255;
      --hero-muted-text-opacity: 0.86;
      --hero-control-bg-rgb: 255 255 255;
      --hero-control-border-rgb: 255 255 255;
      --hero-control-bg-opacity: 0.10;
      --hero-control-border-opacity: 0.20;
      --hero-primary-core-bg: rgba(2, 18, 50, .72);
      --hero-primary-core-bg-hover: rgba(6, 28, 76, .84);
    }

    html[data-theme="light"] #{{ $uid }} {
      --hero-overlay-active-rgb: var(--hero-overlay-light-rgb);
      --hero-text-rgb: 28 28 28;
      --hero-muted-text-opacity: 0.82;
      --hero-control-bg-rgb: 255 255 255;
      --hero-control-border-rgb: 28 28 28;
      --hero-control-bg-opacity: 0.54;
      --hero-control-border-opacity: 0.18;
      --hero-cta-blue-glow: color-mix(in srgb, var(--hero-primary-color) 24%, transparent);
      --hero-cta-cyan-glow: rgba(67, 190, 255, .18);
      --hero-cta-purple-glow: rgba(174, 113, 255, .12);
      --hero-primary-core-bg: rgba(255, 255, 255, .72);
      --hero-primary-core-bg-hover: rgba(255, 255, 255, .88);
    }

    #{{ $uid }} .sccc-hero__overlay {
      background: rgb(var(--hero-overlay-active-rgb) / var(--hero-overlay-opacity));
    }

    #{{ $uid }} .sccc-hero__kicker {
      color: rgb(var(--hero-text-rgb));
      background-color: rgb(var(--hero-control-bg-rgb) / var(--hero-control-bg-opacity));
      border: 1px solid rgb(var(--hero-control-border-rgb) / var(--hero-control-border-opacity));
    }

    #{{ $uid }} .sccc-hero__headline {
      color: rgb(var(--hero-text-rgb));
    }

    #{{ $uid }} .sccc-hero__subhead {
      color: rgb(var(--hero-text-rgb) / var(--hero-muted-text-opacity));
    }

    /*
     * Hero CTA reset
     * --------------
     * The project has stronger global link styling in a few places. Repeating
     * this across link states ensures the CTA always reads as a button and never
     * as an underlined text link.
     */
    #{{ $uid }} .sccc-hero__cta,
    #{{ $uid }} .sccc-hero__cta:visited,
    #{{ $uid }} .sccc-hero__cta:hover,
    #{{ $uid }} .sccc-hero__cta:focus,
    #{{ $uid }} .sccc-hero__cta:focus-visible,
    #{{ $uid }} .sccc-hero__cta:active {
      text-decoration: none !important;
      text-decoration-line: none !important;
      text-underline-offset: 0 !important;
    }

    #{{ $uid }} .sccc-hero__cta {
      position: relative;
      isolation: isolate;
      overflow: visible;
      min-width: 8.75rem;
      border-radius: var(--hero-cta-radius);
      border: 1px solid transparent;
      transform: translateZ(0);
      transition:
        transform .18s ease,
        color .18s ease,
        background .18s ease,
        border-color .18s ease,
        box-shadow .18s ease,
        filter .18s ease;
    }

    /*
     * Neon gradient edge.
     *
     * This pseudo-element creates the theme-style neon outline without needing
     * extra markup around the button label.
     */
    #{{ $uid }} .sccc-hero__cta::before {
      content: "";
      position: absolute;
      inset: -1px;
      z-index: -1;
      padding: 1px;
      border-radius: inherit;
      pointer-events: none;
      background: var(--hero-cta-gradient-border);
      opacity: .92;
      -webkit-mask:
        linear-gradient(#000 0 0) content-box,
        linear-gradient(#000 0 0);
      -webkit-mask-composite: xor;
      mask-composite: exclude;
      transition:
        opacity .18s ease,
        filter .18s ease;
    }

    /*
     * Soft neon aura behind both CTA buttons.
     */
    #{{ $uid }} .sccc-hero__cta::after {
      content: "";
      position: absolute;
      inset: -7px -12px;
      z-index: -2;
      border-radius: inherit;
      pointer-events: none;
      background:
        radial-gradient(circle at 20% 50%, var(--hero-cta-blue-glow) 0%, transparent 44%),
        radial-gradient(circle at 56% 50%, var(--hero-cta-cyan-glow) 0%, transparent 46%),
        radial-gradient(circle at 88% 50%, var(--hero-cta-purple-glow) 0%, transparent 42%);
      filter: blur(11px);
      opacity: .58;
      transition:
        opacity .18s ease,
        filter .18s ease,
        transform .18s ease;
    }

    #{{ $uid }} .sccc-hero__cta:hover,
    #{{ $uid }} .sccc-hero__cta:focus-visible {
      transform: translateY(-1px) scale(1.018);
    }

    #{{ $uid }} .sccc-hero__cta:hover::before,
    #{{ $uid }} .sccc-hero__cta:focus-visible::before {
      opacity: 1;
      filter: brightness(1.22);
    }

    #{{ $uid }} .sccc-hero__cta:hover::after,
    #{{ $uid }} .sccc-hero__cta:focus-visible::after {
      opacity: .86;
      filter: blur(14px);
      transform: scale(1.05);
    }

    /*
     * Primary CTA
     * -----------
     * This replaces the flat blue button with a blue neon glass button. The
     * center still carries the primary blue identity, but the darker glass core
     * and cyan edge make it feel closer to the rest of the theme.
     */
    #{{ $uid }} .sccc-hero__primary-cta {
      color: #ffffff;
      background:
        linear-gradient(
          135deg,
          color-mix(in srgb, var(--hero-primary-color) 56%, var(--hero-primary-core-bg) 44%) 0%,
          color-mix(in srgb, var(--hero-neon-cyan) 24%, var(--hero-primary-core-bg) 76%) 52%,
          color-mix(in srgb, var(--hero-primary-color) 38%, rgba(2, 10, 26, .86) 62%) 100%
        );
      border-color: color-mix(in srgb, var(--hero-primary-color) 42%, rgba(255,255,255,.22));
      backdrop-filter: blur(18px) saturate(178%);
      -webkit-backdrop-filter: blur(18px) saturate(178%);
      box-shadow:
        0 14px 34px rgba(0, 0, 0, .28),
        0 0 24px var(--hero-cta-blue-glow),
        0 0 44px rgba(67, 190, 255, .20),
        inset 0 1px 0 rgba(255, 255, 255, .28),
        inset 0 -1px 0 rgba(0, 0, 0, .22);
      text-shadow:
        0 1px 12px rgba(0, 0, 0, .28),
        0 0 16px rgba(67, 190, 255, .24);
    }

    #{{ $uid }} .sccc-hero__primary-cta:hover,
    #{{ $uid }} .sccc-hero__primary-cta:focus-visible {
      background:
        linear-gradient(
          135deg,
          color-mix(in srgb, var(--hero-primary-color) 66%, var(--hero-primary-core-bg-hover) 34%) 0%,
          color-mix(in srgb, var(--hero-neon-cyan) 34%, var(--hero-primary-core-bg-hover) 66%) 52%,
          color-mix(in srgb, var(--hero-primary-color) 48%, rgba(2, 10, 26, .88) 52%) 100%
        );
      border-color: color-mix(in srgb, var(--hero-neon-cyan) 56%, rgba(255,255,255,.35));
      filter: brightness(1.06) saturate(1.08);
      box-shadow:
        0 18px 42px rgba(0, 0, 0, .32),
        0 0 34px var(--hero-cta-blue-glow),
        0 0 60px rgba(67, 190, 255, .30),
        0 0 80px rgba(174, 113, 255, .16),
        inset 0 1px 0 rgba(255, 255, 255, .34),
        inset 0 -1px 0 rgba(0, 0, 0, .18);
    }

    html[data-theme="light"] #{{ $uid }} .sccc-hero__primary-cta {
      color: #ffffff;
      background:
        linear-gradient(
          135deg,
          color-mix(in srgb, var(--hero-primary-color) 88%, #ffffff 12%) 0%,
          color-mix(in srgb, var(--hero-primary-color) 74%, var(--hero-neon-cyan) 26%) 52%,
          color-mix(in srgb, var(--hero-primary-color) 80%, #061225 20%) 100%
        );
      box-shadow:
        0 12px 28px rgba(15, 23, 42, .16),
        0 0 22px color-mix(in srgb, var(--hero-primary-color) 22%, transparent),
        0 0 38px rgba(67, 190, 255, .16),
        inset 0 1px 0 rgba(255, 255, 255, .34),
        inset 0 -1px 0 rgba(0, 0, 0, .16);
    }

    /*
     * Secondary CTA
     * -------------
     * Glass outline treatment. It is quieter than the primary button, but still
     * shares the same neon language and never shows an underline.
     */
    #{{ $uid }} .sccc-hero__secondary-cta {
      color: rgb(var(--hero-text-rgb));
      background:
        linear-gradient(
          135deg,
          rgb(var(--hero-control-bg-rgb) / calc(var(--hero-control-bg-opacity) + .08)) 0%,
          rgb(var(--hero-control-bg-rgb) / var(--hero-control-bg-opacity)) 100%
        );
      border-color: rgb(var(--hero-control-border-rgb) / var(--hero-control-border-opacity));
      backdrop-filter: blur(18px) saturate(170%);
      -webkit-backdrop-filter: blur(18px) saturate(170%);
      box-shadow:
        0 14px 34px rgba(0, 0, 0, .18),
        0 0 24px rgba(67, 190, 255, .14),
        inset 0 1px 0 rgba(255, 255, 255, .18);
    }

    #{{ $uid }} .sccc-hero__secondary-cta:hover,
    #{{ $uid }} .sccc-hero__secondary-cta:focus-visible {
      color: rgb(var(--hero-text-rgb));
      border-color: color-mix(in srgb, var(--hero-neon-cyan) 48%, rgb(var(--hero-control-border-rgb) / .32));
      background:
        linear-gradient(
          135deg,
          color-mix(in srgb, var(--hero-primary-color) 14%, rgb(var(--hero-control-bg-rgb) / .32)) 0%,
          rgb(var(--hero-control-bg-rgb) / calc(var(--hero-control-bg-opacity) + .14)) 100%
        );
      box-shadow:
        0 18px 42px rgba(0, 0, 0, .22),
        0 0 34px rgba(67, 190, 255, .20),
        0 0 52px rgba(174, 113, 255, .14),
        inset 0 1px 0 rgba(255, 255, 255, .24);
    }

    html[data-theme="light"] #{{ $uid }} .sccc-hero__secondary-cta {
      color: rgb(var(--hero-text-rgb));
      background:
        linear-gradient(
          135deg,
          rgba(255,255,255,.78) 0%,
          color-mix(in srgb, #ffffff 78%, var(--hero-primary-color) 10%) 100%
        );
      border-color: color-mix(in srgb, var(--hero-primary-color) 28%, rgba(28,28,28,.14));
      box-shadow:
        0 10px 24px rgba(15, 23, 42, .10),
        0 0 22px rgba(67, 190, 255, .14),
        inset 0 1px 0 rgba(255, 255, 255, .76);
    }

    #{{ $uid }} .text-gradient {
      background: var(--sccc-gradient-club-blue);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      -webkit-text-fill-color: transparent;
      font-weight: inherit;
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

    #{{ $uid }} .sccc-hero__images { will-change: transform; }
    #{{ $uid }} .sccc-hero__slice { will-change: transform; }
    #{{ $uid }} .sccc-hero__img {
      width: 600%;           /* 6 slices */
      max-width: none !important;
      max-height: none !important;
      display: block;
      backface-visibility: hidden;
      transform: translateZ(0);
      transform-origin: 50% 50%;
      will-change: transform;
    }

    @media (prefers-reduced-motion: reduce) {
      #{{ $uid }} .sccc-hero__slice,
      #{{ $uid }} .sccc-hero__img { transition: none !important; animation: none !important; }
    }
  </style>

  {{-- Instance-scoped JS (no fades, scroll-scrub transforms only) --}}
  <script>
    (function(rootId){
      var root = document.getElementById(rootId);
      if (!root) return;

      var mode = root.getAttribute('data-mode') || 'sliced';
      var motion = root.getAttribute('data-motion') === '1';
      if (!motion || mode !== 'sliced') return;

      // Respect reduced motion
      if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

      var scrollLen = parseInt(root.getAttribute('data-scroll-len') || '1100', 10);
      var scaleTo   = parseFloat(root.getAttribute('data-scale-to') || '1.35');

      // CDN fallbacks if gsap/ScrollTrigger not present
      var needs = [];
      if (!window.gsap) needs.push('https://cdn.jsdelivr.net/npm/gsap@3/dist/gsap.min.js');
      if (!window.ScrollTrigger) needs.push('https://cdn.jsdelivr.net/npm/gsap@3/dist/ScrollTrigger.min.js');

      function load(u){
        return new Promise(function(res, rej){
          var s = document.createElement('script');
          s.src = u; s.onload = res; s.onerror = rej;
          document.head.appendChild(s);
        });
      }

      (needs.reduce(function(p,u){ return p.then(function(){ return load(u); }); }, Promise.resolve()))
        .then(init).catch(init);

      function init(){
        var gsap = window.gsap, ST = window.ScrollTrigger;
        if (!gsap || !ST) return;
        gsap.registerPlugin(ST);

        var slices = Array.prototype.slice.call(root.querySelectorAll('.sccc-hero__slice'));
        var imgs   = Array.prototype.slice.call(root.querySelectorAll('.sccc-hero__img'));
        var n = slices.length;

        if (!n || n !== imgs.length || n !== 6) return;

        // Slice offsets: each img is 600% width, shifted left by -index*100%
        imgs.forEach(function(img, i){
          img.style.left = (-i * 100) + '%';
          img.style.top = '0';
          img.style.height = '100%';
        });

        // Kill only triggers created by THIS instance
        var instanceTriggers = [];

        function killInstance(){
          instanceTriggers.forEach(function(t){
            try { t.kill(); } catch(e) {}
          });
          instanceTriggers = [];
          gsap.killTweensOf(imgs);
        }

        killInstance();

        // Travel tuning (same spirit as your current block)
        var vh = window.innerHeight || 800;
        var BASE_TRAVEL = -vh * 0.55;
        var STEP_TRAVEL = -vh * 0.24;

        imgs.forEach(function(img, i){
          var tween = gsap.to(img, {
            y: function(){ return BASE_TRAVEL + i * STEP_TRAVEL; },
            ease: 'none',
            force3D: true,
            scrollTrigger: {
              trigger: root,
              start: 'top top',
              end: '+=' + scrollLen,
              scrub: true
            }
          });
          instanceTriggers.push(tween.scrollTrigger);
        });

        var zoomTween = gsap.to(imgs, {
          scale: scaleTo,
          ease: 'none',
          force3D: true,
          scrollTrigger: {
            trigger: root,
            start: 'top top',
            end: '+=' + scrollLen,
            scrub: true
          }
        });
        instanceTriggers.push(zoomTween.scrollTrigger);

        window.addEventListener('resize', function(){
          try { ST.refresh(); } catch(e) {}
        }, { passive: true });
      }
    })('{{ $uid }}');
  </script>
</section>