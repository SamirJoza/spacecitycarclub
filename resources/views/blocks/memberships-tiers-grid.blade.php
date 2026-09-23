{{--
  resources/views/blocks/memberships-tiers-grid.blade.php
  File: resources/views/blocks/memberships-tiers-grid.blade.php

  Theme handling note
  -----------------------------------------------------------------------------
  Tailwind's `dark:` variant isn't reliable in this project, so this block uses:
  - LIGHT (default) styles via CSS variables on `.sccc-membership-grid`
  - DARK overrides scoped to: `html.dark[data-theme="dark"] ...` / `.dark ...`

  PMPro WYSIWYG list handling
  -----------------------------------------------------------------------------
  Editors should NOT have to paste a Material Symbols <span> into each <li>.
  ANY <ul><li> inside the card WYSIWYG gets a default bullet icon:

  - Icon: Material Symbols "verified"
  - Featured ("Most Popular") card: BLUE icon
  - All other cards: GREEN icon

  GSAP "card dealer" scroll animation (instance-scoped, like founding-hero)
  -----------------------------------------------------------------------------
  - Cards "slide in" from the left, staggered (dealer toss vibe)
  - Fires every time the block enters view (scroll down OR up)
  - Re-arms only after leaving view (prevents spam retriggers while in view)
  - Uses ScrollTrigger if available, otherwise IntersectionObserver fallback
  - CDN fallback only if gsap/ScrollTrigger are missing (same pattern as founding-hero)
--}}

@php
  $uid = 'sccc-tiers-grid-' . uniqid();

  $plans = $plans ?? [];

  // Use your theme variable if present, fallback to the same blue you’ve been using.
  $primaryCss = 'var(--color-primary,#135bec)';
@endphp

<section id="{{ $uid }}" class="sccc-membership-grid relative py-16 md:py-24 bg-[var(--sccc-section-bg)]">
  <div class="sccc-grid-divider absolute inset-x-0 top-0 h-px"></div>

  {{-- subtle section wash (opacity is theme-controlled via CSS vars) --}}
  <div class="sccc-grid-wash pointer-events-none absolute inset-0"
       style="background:
        radial-gradient(700px 360px at 50% 10%, color-mix(in srgb, {{ $primaryCss }} 18%, transparent) 0%, transparent 70%),
        radial-gradient(900px 520px at 10% 110%, rgba(255,255,255,0.06) 0%, transparent 65%);"
       aria-hidden="true"></div>

  <style>
    .sccc-membership-grid a { text-decoration: none; }

    /* Animation polish (safe; doesn’t change layout) */
    .sccc-membership-grid .sccc-tier { will-change: transform, opacity; }

    /* -----------------------------------------------------------------------
       Theme tokens (DEFAULT = LIGHT)
       ----------------------------------------------------------------------- */
    .sccc-membership-grid {
      /* section */
      --sccc-section-bg: #f8fafc;
      --sccc-divider-mid: rgba(15, 23, 42, 0.10);
      --sccc-wash-opacity: 0.55; /* dial blue wash back on LIGHT */

      /* cards */
      --sccc-card-bg: rgba(255,255,255,0.86);
      --sccc-card-border: rgba(15, 23, 42, 0.12);
      --sccc-card-border-hover: rgba(15, 23, 42, 0.20);
      --sccc-card-shadow: 0 18px 55px rgba(2, 6, 23, 0.12);

      /* typography */
      --sccc-heading: #0f172a;
      --sccc-subheading: #475569;
      --sccc-suffix: #64748b;

      /* descriptions / lists */
      --sccc-desc: rgba(15, 23, 42, 0.70);
      --sccc-list-enabled: rgba(15, 23, 42, 0.80);
      --sccc-list-disabled: rgba(15, 23, 42, 0.55);
      --sccc-list-disabled-icon: rgba(15, 23, 42, 0.50);

      /* empty state */
      --sccc-empty-bg: rgba(15, 23, 42, 0.03);
      --sccc-empty-border: rgba(15, 23, 42, 0.18);
      --sccc-empty-text: rgba(15, 23, 42, 0.75);

      /* button focus ring */
      --sccc-btn-ring: rgba(15, 23, 42, 0.22);

      /* non-featured CTA (LIGHT) */
      --sccc-btn-nonfeatured-bg: #0f172a;
      --sccc-btn-nonfeatured-bg-hover: #1e293b;
      --sccc-btn-nonfeatured-text: #ffffff;
    }

    /* -----------------------------------------------------------------------
       DARK theme overrides (SCOPED)
       ----------------------------------------------------------------------- */
    html.dark[data-theme="dark"] .sccc-membership-grid,
    .dark .sccc-membership-grid {
      --sccc-section-bg: #0f1520;
      --sccc-divider-mid: rgba(255,255,255,0.10);
      --sccc-wash-opacity: 1;

      /* glassier surface on DARK */
      --sccc-card-bg: rgba(255,255,255,0.06);
      --sccc-card-border: rgba(255,255,255,0.12);
      --sccc-card-border-hover: rgba(255,255,255,0.22);
      --sccc-card-shadow:
        0 1px 0 rgba(255,255,255,0.10) inset,
        0 18px 55px rgba(0,0,0,0.55);

      --sccc-heading: #ffffff;
      --sccc-subheading: #92a4c9;
      --sccc-suffix: #92a4c9;

      --sccc-desc: rgba(255,255,255,0.70);
      --sccc-list-enabled: rgba(255,255,255,0.80);
      --sccc-list-disabled: rgba(255,255,255,0.60);
      --sccc-list-disabled-icon: rgba(255,255,255,0.60);

      --sccc-empty-bg: rgba(255,255,255,0.05);
      --sccc-empty-border: rgba(255,255,255,0.20);
      --sccc-empty-text: rgba(255,255,255,0.70);

      --sccc-btn-ring: rgba(255,255,255,0.60);

      /* DARK non-featured CTA: subtle glass button */
      --sccc-btn-nonfeatured-bg: rgba(255,255,255,0.10);
      --sccc-btn-nonfeatured-bg-hover: rgba(255,255,255,0.15);
      --sccc-btn-nonfeatured-text: #ffffff;
    }

    /* divider + wash behavior */
    .sccc-membership-grid .sccc-grid-divider {
      background: linear-gradient(to right, transparent, var(--sccc-divider-mid), transparent);
    }
    .sccc-membership-grid .sccc-grid-wash {
      opacity: var(--sccc-wash-opacity);
    }

    /* Dark-only extra glass sheen + saturation */
    html.dark[data-theme="dark"] .sccc-membership-grid .sccc-tier,
    .dark .sccc-membership-grid .sccc-tier {
      -webkit-backdrop-filter: blur(26px) saturate(140%);
      backdrop-filter: blur(26px) saturate(140%);
      background-image:
        radial-gradient(120% 85% at 20% 0%, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0) 60%),
        radial-gradient(110% 95% at 85% 120%, color-mix(in srgb, var(--color-primary,#135bec) 16%, transparent) 0%, transparent 65%);
      background-repeat: no-repeat;
    }

    /* Featured card glow */
    .sccc-membership-grid .sccc-tier--featured {
      box-shadow:
        0 0 0 1px rgba(19,91,236,.22) inset,
        0 0 14px rgba(19,91,236,.16),
        0 18px 55px rgba(2,6,23,.18) !important;
    }
    html.dark[data-theme="dark"] .sccc-membership-grid .sccc-tier--featured,
    .dark .sccc-membership-grid .sccc-tier--featured {
      box-shadow:
        0 0 0 1px rgba(19,91,236,.35) inset,
        0 0 28px color-mix(in srgb, var(--color-primary,#135bec) 45%, transparent),
        0 18px 55px rgba(0,0,0,.60) !important;
    }

    /* -----------------------------------------------------------------------
       ✅ WYSIWYG rendering + default UL bullet icon (Material Symbols)
       ----------------------------------------------------------------------- */

    /* Per-card icon color: green by default, blue when featured */
    .sccc-membership-grid .sccc-tier { --sccc-wysiwyg-icon: #22c55e; } /* green */
    .sccc-membership-grid .sccc-tier--featured { --sccc-wysiwyg-icon: var(--color-primary,#135bec); } /* blue */

    .sccc-membership-grid .sccc-tier__wysiwyg {
      color: var(--sccc-desc);
    }

    .sccc-membership-grid .sccc-tier__wysiwyg > :first-child { margin-top: 0; }
    .sccc-membership-grid .sccc-tier__wysiwyg > :last-child  { margin-bottom: 0; }

    /* paragraphs inside WYSIWYG */
    .sccc-membership-grid .sccc-tier__wysiwyg p {
      margin: 0;
      color: var(--sccc-desc);
    }
    .sccc-membership-grid .sccc-tier__wysiwyg p + p { margin-top: .75rem; }

    /* UL becomes our icon list */
    .sccc-membership-grid .sccc-tier__wysiwyg ul {
      margin: 0;
      padding: 0;
      list-style: none; /* removes native bullets */
      display: flex;
      flex-direction: column;
      gap: .75rem;
    }

    .sccc-membership-grid .sccc-tier__wysiwyg ul > li {
      display: flex;
      align-items: flex-start;
      gap: .75rem;
      color: var(--sccc-list-enabled);
    }

    /* Normalize editor-injected p tags inside list items */
    .sccc-membership-grid .sccc-tier__wysiwyg ul > li > p {
      margin: 0;
      color: inherit;
    }

    /* Default bullet icon: Material Symbols "verified" */
    .sccc-membership-grid .sccc-tier__wysiwyg ul > li::before {
      content: "verified";
      font-family: "Material Symbols Outlined";
      font-size: 20px;
      line-height: 1;
      font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 20;
      flex: 0 0 auto;
      transform: translateY(1px);
      color: var(--sccc-wysiwyg-icon);
    }

    /* Keep OL as normal (but align color) */
    .sccc-membership-grid .sccc-tier__wysiwyg ol {
      margin: 0;
      padding-left: 1.25rem;
      color: var(--sccc-list-enabled);
    }
    .sccc-membership-grid .sccc-tier__wysiwyg ol > li { margin: .35rem 0; }
  </style>

  <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mb-12 text-center md:mb-16">
      <h2 class="text-3xl font-bold text-[color:var(--sccc-heading)]">
        {{ $heading ?? 'Select Your Membership' }}
      </h2>

      @if (!empty($subheading))
        <p class="mt-2 text-[color:var(--sccc-subheading)]">
          {{ $subheading }}
        </p>
      @endif
    </div>

    @if (empty($plans))
      <div class="mx-auto max-w-2xl rounded-xl border border-dashed border-[color:var(--sccc-empty-border)] bg-[var(--sccc-empty-bg)] p-6 text-center text-[color:var(--sccc-empty-text)]">
        <div class="font-semibold">No membership levels to display.</div>
        <div class="mt-1 text-sm">
          In the block sidebar, select one or more <strong>Membership Levels to Display</strong>.
        </div>
      </div>
    @else
      <div class="grid grid-cols-1 gap-8 md:grid-cols-2 md:gap-6 lg:gap-8 items-start" data-sccc-tier-grid>
        @foreach ($plans as $plan)
          @php
            $isFeatured  = !empty($plan['is_featured']);
            $badge       = $plan['badge'] ?? null;

            $featuresRaw = $plan['features'] ?? null;
            $hasFeatures = is_array($featuresRaw) && !empty($featuresRaw);

            // PMPro description/tagline can be plain text OR sanitized HTML (WYSIWYG).
            $desc = trim((string) ($plan['tagline'] ?? ''));
            $descHasHtml = $desc !== '' && (bool) preg_match('/<[^>]+>/', $desc);

            // Glass card shells (STATIC class strings so Tailwind always generates them)
            $cardBase = $isFeatured
              ? 'relative flex flex-col rounded-2xl border-2 p-6 h-full z-10 md:-translate-y-4 sccc-tier sccc-tier--featured'
              : 'relative flex flex-col rounded-2xl border p-6 h-full sccc-tier';

            // Glass look (theme-driven via CSS vars)
            $cardGlass = 'bg-[var(--sccc-card-bg)] backdrop-blur-xl shadow-[var(--sccc-card-shadow)]';

            // Borders (theme-driven via CSS vars)
            $cardBorder = $isFeatured
              ? 'border-transparent'
              : 'border-[color:var(--sccc-card-border)] hover:border-[color:var(--sccc-card-border-hover)]';

            // Featured inline style: keep BLUE border-color only
            $featuredStyle = $isFeatured
              ? 'border-color: '.$primaryCss.';'
              : '';
          @endphp

          <div class="{{ $cardBase }} {{ $cardGlass }} {{ $cardBorder }}"
               @if($isFeatured) style="{{ $featuredStyle }}" @endif>

            {{-- Featured badge --}}
            @if ($isFeatured && !empty($badge))
              <div
                class="absolute -top-4 left-1/2 -translate-x-1/2 rounded-full px-4 py-1 text-xs font-bold text-white shadow-lg"
                style="background: {{ $primaryCss }};"
              >
                {{ $badge }}
              </div>
            @endif

            {{-- Header --}}
            <div class="mb-4 @if($isFeatured) mt-2 @endif">
              <h3 class="text-lg font-bold text-[color:var(--sccc-heading)]">
                {{ $plan['name'] ?? '' }}
              </h3>
            </div>

            {{-- Price --}}
            <div class="mb-6 flex items-baseline gap-2">
              @if (!empty($plan['price']))
                <span class="@if($isFeatured) text-5xl @else text-4xl @endif font-black tracking-tight text-[color:var(--sccc-heading)]">
                  {{ $plan['price'] }}
                </span>
              @endif

              @if (!empty($plan['suffix']))
                <span class="text-sm font-bold text-[color:var(--sccc-suffix)]">
                  {{ $plan['suffix'] }}
                </span>
              @endif
            </div>

            {{-- CTA (before description/features) --}}
            @if (!empty($plan['cta_url']) && !empty($plan['cta_label']))
              <a
                href="{{ $plan['cta_url'] }}"
                class="w-full rounded-lg px-4 py-3 text-center text-sm font-bold transition-colors
                       focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--sccc-btn-ring)]
                       @if($isFeatured)
                         text-white shadow-lg
                       @else
                         bg-[var(--sccc-btn-nonfeatured-bg)] text-[color:var(--sccc-btn-nonfeatured-text)]
                         hover:bg-[var(--sccc-btn-nonfeatured-bg-hover)]
                       @endif"
                @if($isFeatured)
                  style="background: {{ $primaryCss }};"
                @endif
              >
                {{ $plan['cta_label'] }}
              </a>
            @endif

            {{-- Description / Benefits AFTER CTA --}}
            @if ($hasFeatures)
              <ul class="mt-6 space-y-3">
                @foreach ($featuresRaw as $item)
                  @php
                    $text = is_array($item) ? (string) ($item['text'] ?? '') : (string) $item;
                    $enabled = is_array($item) ? (bool) ($item['enabled'] ?? true) : true;

                    $icon = $enabled ? 'check_circle' : 'cancel';

                    $liClass = $enabled
                      ? 'flex items-start gap-3 text-sm text-[color:var(--sccc-list-enabled)]'
                      : 'flex items-start gap-3 text-sm text-[color:var(--sccc-list-disabled)] opacity-60';

                    $iconClass = $enabled
                      ? ($isFeatured ? 'text-[20px] text-[color:var(--color-primary,#135bec)]' : 'text-[20px] text-green-500')
                      : 'text-[20px] text-[color:var(--sccc-list-disabled-icon)]';
                  @endphp

                  @if ($text !== '')
                    <li class="{{ $liClass }}">
                      <span class="material-symbols-outlined {{ $iconClass }}">{{ $icon }}</span>
                      <span @if(!$enabled) class="line-through" @endif>{{ $text }}</span>
                    </li>
                  @endif
                @endforeach
              </ul>

            @elseif ($desc !== '')
              @if ($descHasHtml)
                {{-- Render PMPro WYSIWYG as HTML (sanitized). UL/LI gets the default "verified" icon via CSS. --}}
                <div class="sccc-tier__wysiwyg mt-6 text-sm">
                  {!! wp_kses_post($desc) !!}
                </div>
              @else
                <p class="mt-6 text-sm text-[color:var(--sccc-desc)]">
                  {{ $desc }}
                </p>
              @endif
            @endif

          </div>
        @endforeach
      </div>
    @endif
  </div>

  {{-- Instance-scoped GSAP: "dealer slide-in" every time block enters view (down OR up) --}}
  <script>
    (function(rootId){
      var root = document.getElementById(rootId);
      if (!root) return;

      // Don’t animate in wp-admin / block editor screens.
      if (document.body && (document.body.classList.contains('wp-admin') || document.body.classList.contains('block-editor-page'))) return;

      // Respect reduced motion.
      if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

      // Prevent double-init for this block instance (defensive).
      if (root.dataset.scccDealerInit === '1') return;
      root.dataset.scccDealerInit = '1';

      var grid  = root.querySelector('[data-sccc-tier-grid]');
      var cards = root.querySelectorAll('.sccc-tier');
      if (!grid || !cards || !cards.length) return;

      // CDN fallbacks if gsap/ScrollTrigger not present (same pattern as founding-hero)
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
        if (!gsap) return;

        if (ST && gsap.registerPlugin) {
          try { gsap.registerPlugin(ST); } catch(e) {}
        }

        // Rearm lock: only play once per entry; rearm after leaving view.
        var armed = true;

        // Clean slate: removes inline transform/opacity from prior runs,
        // letting Tailwind transforms (like md:-translate-y-4) remain intact.
        function reset(){
          try {
            gsap.killTweensOf(cards);
            gsap.set(cards, { clearProps: 'transform,opacity' });
          } catch(e) {}
        }

        // "Dealer" slide-in: stacked left + slight rotation + stagger toss.
        // IMPORTANT: we animate X/rotate/opacity only (no Y),
        // so existing Tailwind translateY (featured lift) is preserved.
        function play(){
          if (!armed) return;
          armed = false;

          reset();

          gsap.from(cards, {
            opacity: 0,
            x: function(i){ return -220 - (i * 28); },   // stacked to the left (dealer deck vibe)
            rotate: function(i){ return -7 + (i * 0.6); }, // tiny variation per card
            duration: 0.85,
            ease: 'back.out(1.35)',
            stagger: { each: 0.12, from: 0 },
            overwrite: 'auto'
          });
        }

        // Use ScrollTrigger if available: play on enter + enterBack, reset/rearm on leave.
        if (ST && typeof ST.create === 'function') {
          ST.create({
            trigger: root,
            start: 'top 75%',
            end: 'bottom 25%',
            onEnter: function(){ play(); },
            onEnterBack: function(){ play(); },
            onLeave: function(){ reset(); armed = true; },
            onLeaveBack: function(){ reset(); armed = true; }
          });
          return;
        }

        // Fallback: IntersectionObserver
        // - triggers when entering view
        // - rearms + resets after leaving view
        var io = new IntersectionObserver(function(entries){
          entries.forEach(function(entry){
            if (entry.isIntersecting) {
              play();
            } else {
              reset();
              armed = true;
            }
          });
        }, { threshold: 0.35 });

        io.observe(root);
      }
    })('{{ $uid }}');
  </script>
</section>
