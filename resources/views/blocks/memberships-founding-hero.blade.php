{{--
  resources/views/blocks/memberships-founding-hero.blade.php
  File: resources/views/blocks/memberships-founding-hero.blade.php

  Change requested
  -----------------------------------------------------------------------------
  - Trigger the badge flash every time the card enters view (scroll down OR up).
  - Avoid constant retriggers while the card remains in view (cooldown lock).
  - Keep steady halo; GSAP only does the 3-flash burst.
  - ScrollTrigger if available, otherwise IntersectionObserver fallback.
--}}

@php
  $uid = 'sccc-founding-hero-' . uniqid();
@endphp

<style>
  /* ==========================================================================
     SCCC Founding Hero — theme scoping (block-only)
     ========================================================================== */

  #{{ $uid }} .sccc-hero-title { color: #0f172a; }
  #{{ $uid }} .sccc-hero-subtitle { color: #475569; }

  #{{ $uid }} a { text-decoration: none; }

  #{{ $uid }} .sccc-founding-card {
    background: linear-gradient(180deg, rgba(255,255,255,0.92), rgba(255,255,255,0.88));
    color: #0f172a;
    border: 0;
    outline: 0;
    box-shadow:
      0 22px 55px rgba(2, 6, 23, 0.18),
      0 1px 0 rgba(255,255,255,0.55) inset;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
  }

  #{{ $uid }} .sccc-card-title,
  #{{ $uid }} .sccc-card-price { color: #0f172a; }

  #{{ $uid }} .sccc-card-suffix { color: #64748b; }
  #{{ $uid }} .sccc-card-desc { color: #475569; }

  html.dark[data-theme="dark"] #{{ $uid }} .sccc-hero-title { color: #ffffff; }
  html.dark[data-theme="dark"] #{{ $uid }} .sccc-hero-subtitle { color: #92a4c9; }

  html.dark[data-theme="dark"] #{{ $uid }} .sccc-founding-card {
    background: linear-gradient(180deg, rgba(25,34,51,0.84), rgba(25,34,51,0.72));
    color: #ffffff;
    border: 0;
    box-shadow:
      0 10px 18px rgba(0,0,0,0.55),
      0 40px 110px rgba(0,0,0,0.65),
      0 1px 0 rgba(255,255,255,0.08) inset;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
  }

  html.dark[data-theme="dark"] #{{ $uid }} .sccc-card-title,
  html.dark[data-theme="dark"] #{{ $uid }} .sccc-card-price { color: #ffffff; }

  html.dark[data-theme="dark"] #{{ $uid }} .sccc-card-suffix { color: rgba(255,255,255,0.65); }
  html.dark[data-theme="dark"] #{{ $uid }} .sccc-card-desc { color: #92a4c9; }

  #{{ $uid }} .sccc-left-panel {
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
  }

  /* ==========================================================================
     Gradient top border — responsive placement
     ========================================================================== */

  #{{ $uid }} .sccc-top-gradient {
    position: absolute;
    height: 4px;
    background: linear-gradient(90deg, #2563eb, #a855f7, #2563eb);
    z-index: 20;
    pointer-events: none;

    left: 0;
    right: 0;
    top: 250px;
  }

  @media (min-width: 768px) {
    #{{ $uid }} .sccc-top-gradient {
      top: 0;
      left: auto;
      right: 0;
      width: 60%;
    }
  }

  /* ==========================================================================
     Urgency badge — steady halo + GSAP burst
     ========================================================================== */

  #{{ $uid }} .sccc-urgency-ribbon {
    position: absolute;
    z-index: 50;
    pointer-events: none;
    transform-origin: top right;

    top: 16px;
    right: -18px;
    transform: rotate(14deg) scale(0.78);

    filter: drop-shadow(0 18px 38px rgba(0,0,0,0.45));
  }

  #{{ $uid }} .sccc-urgency-ribbon span {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    border-radius: 999px;
    font-weight: 900;
    font-size: 13px;
    letter-spacing: 0.02em;
    color: #0b1220;
    background: linear-gradient(135deg, #fbbf24, #fb7185);
    box-shadow:
      0 18px 40px rgba(0,0,0,0.40),
      0 1px 0 rgba(255,255,255,0.35) inset;
    white-space: nowrap;

    position: relative;
    isolation: isolate;

    --haloOpacity: .18;
    --haloScale: 1;
    --haloBlur: 16px;
  }

  #{{ $uid }} .sccc-urgency-ribbon span::before {
    content: "";
    position: absolute;
    inset: -22px -28px;
    border-radius: 999px;
    z-index: -1;

    opacity: var(--haloOpacity);
    transform: scale(var(--haloScale));
    filter: blur(var(--haloBlur));

    background:
      radial-gradient(circle at 30% 45%, rgba(251,191,36,.85), rgba(251,113,133,.35), rgba(0,0,0,0) 70%),
      radial-gradient(circle at 70% 55%, rgba(59,130,246,.55), rgba(168,85,247,.22), rgba(0,0,0,0) 72%);
  }

  @media (min-width: 375px) {
    #{{ $uid }} .sccc-urgency-ribbon { right: -22px; transform: rotate(16deg) scale(0.82); }
  }

  @media (min-width: 640px) {
    #{{ $uid }} .sccc-urgency-ribbon { top: 22px; right: -28px; transform: rotate(18deg) scale(0.90); }
  }

  @media (min-width: 768px) {
    #{{ $uid }} .sccc-urgency-ribbon { top: 26px; right: -24px; transform: rotate(20deg) scale(0.98); }
  }

  @media (min-width: 1024px) {
    #{{ $uid }} .sccc-urgency-ribbon { top: 36px; right: -80px; transform: rotate(25deg) scale(1); }
  }

  html.dark[data-theme="dark"] #{{ $uid }} .sccc-urgency-ribbon span {
    box-shadow:
      0 22px 52px rgba(0,0,0,0.60),
      0 1px 0 rgba(255,255,255,0.22) inset;

    --haloOpacity: .26;
    --haloBlur: 18px;
  }

  @media (prefers-reduced-motion: reduce) {
    #{{ $uid }} .sccc-urgency-ribbon { filter: none; }
  }
</style>

<section id="{{ $uid }}" class="sccc-founding-hero relative overflow-hidden py-12 md:py-20">
  <div class="absolute inset-0 pointer-events-none bg-gradient-to-br from-blue-500/10 via-purple-500/5 to-transparent"></div>
  <div class="absolute top-0 right-0 -mr-20 -mt-20 h-[500px] w-[500px] rounded-full bg-blue-600/20 blur-[100px] pointer-events-none"></div>

  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 relative z-10">
    <div class="mb-12 text-center">
      <h1 class="sccc-hero-title mb-4 text-4xl font-black leading-tight tracking-tight md:text-6xl">
        {{ $heading ?? 'Join the Crew' }}
      </h1>

      @if (!empty($subheading))
        <p class="sccc-hero-subtitle mx-auto max-w-2xl text-lg">
          {{ $subheading }}
        </p>
      @endif
    </div>

    <div class="mx-auto max-w-4xl transform transition-all hover:-translate-y-1">
      <div class="sccc-founding-card relative rounded-xl" data-founding-card>

        <div class="sccc-top-gradient"></div>

        @if (!empty($badge_text))
          <div class="sccc-urgency-ribbon">
            <span data-urgency-badge>
              <span class="material-symbols-outlined" style="font-size:20px; line-height:1;">local_fire_department</span>
              {{ $badge_text }}
            </span>
          </div>
        @endif

        <div class="overflow-hidden rounded-xl">
          <div class="flex flex-col md:flex-row md:min-h-[340px] items-stretch">
            <div
              class="sccc-left-panel relative md:w-2/5 w-full min-h-[250px] md:min-h-[340px]"
              @if (!empty($image_url))
                style='background-image: url("{{ $image_url }}");'
              @endif
            >
              <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent md:bg-gradient-to-r md:from-transparent md:to-[#192233]/80"></div>

              @if (!empty($badge_left))
                <div class="absolute bottom-4 left-4 md:hidden">
                  <span class="inline-flex items-center rounded-full bg-blue-600 px-3 py-1 text-xs font-bold text-white shadow-lg shadow-blue-600/40">
                    {{ $badge_left }}
                  </span>
                </div>
              @endif
            </div>

            <div class="flex flex-col justify-center p-6 md:w-3/5 md:p-8">
              <div class="mb-2 flex items-center justify-between">
                @if (!empty($badge_left))
                  <span class="hidden md:inline-flex items-center rounded-full bg-blue-600/10 px-3 py-1 text-xs font-bold text-blue-700 ring-1 ring-inset ring-blue-600/25
                    [html.dark[data-theme=dark]_&]:bg-blue-500/15
                    [html.dark[data-theme=dark]_&]:text-blue-200
                    [html.dark[data-theme=dark]_&]:ring-blue-400/20
                  ">
                    {{ $badge_left }}
                  </span>
                @else
                  <span class="hidden md:block"></span>
                @endif

                <span></span>
              </div>

              <h3 class="sccc-card-title mb-2 text-2xl font-bold">
                {{ $level_name ?? 'Founding Member Status' }}
              </h3>

              <div class="mb-4 flex items-baseline gap-2">
                @if (!empty($price))
                  <span class="sccc-card-price text-4xl font-black">
                    {{ $price }}
                  </span>
                @endif

                @if (!empty($price_suffix))
                  <span class="sccc-card-suffix text-sm font-medium">
                    {{ $price_suffix }}
                  </span>
                @endif
              </div>

              @if (!empty($description))
                <p class="sccc-card-desc mb-6">
                  {!! $description !!}
                </p>
              @endif

              @if (!empty($cta_url) && !empty($cta_label))
                <a
                  href="{{ $cta_url }}"
                  class="group relative flex w-full items-center justify-center overflow-hidden rounded-lg bg-blue-600 px-6 py-3 text-sm font-bold text-white transition-all hover:bg-blue-500 hover:shadow-lg hover:shadow-blue-600/30 sm:w-auto"
                >
                  <span class="relative z-10 flex items-center gap-2">
                    {{ $cta_label }}
                    <span class="material-symbols-outlined text-[18px] transition-transform group-hover:translate-x-1">arrow_forward</span>
                  </span>
                </a>
              @endif
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  {{-- Instance-scoped GSAP: flash 3x every time card enters view (down OR up) --}}
  <script>
    (function(rootId){
      var root = document.getElementById(rootId);
      if (!root) return;

      if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

      var card  = root.querySelector('[data-founding-card]');
      var badge = root.querySelector('[data-urgency-badge]');
      if (!card || !badge) return;

      // Prevent spam retriggers while staying in view
      var locked = false;
      function lock(ms){
        locked = true;
        window.setTimeout(function(){ locked = false; }, ms || 1200);
      }

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
        if (!gsap) return;

        if (ST && gsap.registerPlugin) {
          try { gsap.registerPlugin(ST); } catch(e) {}
        }

        function getBaseline(){
          var styles = window.getComputedStyle(badge);
          var op = parseFloat((styles.getPropertyValue('--haloOpacity') || '0.18').trim()) || 0.18;
          var blur = (styles.getPropertyValue('--haloBlur') || '16px').trim() || '16px';
          return { opacity: op, blur: blur };
        }

        function flash(){
          if (locked) return;
          lock(1400);

          var base = getBaseline();

          try { gsap.killTweensOf(badge); } catch(e) {}

          gsap.set(badge, {
            '--haloOpacity': base.opacity,
            '--haloScale': 1,
            '--haloBlur': base.blur
          });

          gsap.timeline()
            .to(badge, {
              '--haloOpacity': Math.min(base.opacity + 0.42, 0.75),
              '--haloScale': 1.10,
              '--haloBlur': '22px',
              duration: 0.22,
              ease: 'power2.out',
              repeat: 5,       // up/down x3
              yoyo: true,
              repeatDelay: 0.12
            })
            .to(badge, {
              '--haloOpacity': base.opacity,
              '--haloScale': 1,
              '--haloBlur': base.blur,
              duration: 0.35,
              ease: 'power2.out'
            }, '+=0.05');
        }

        // Use ScrollTrigger if available: fire on enter + enterBack
        if (ST && typeof ST.create === 'function') {
          ST.create({
            trigger: card,
            start: 'top 75%',
            end: 'bottom 25%',
            onEnter: flash,
            onEnterBack: flash
          });
          return;
        }

        // Fallback: IntersectionObserver
        // - triggers when entering view
        // - rearms after leaving view
        var armed = true;
        var io = new IntersectionObserver(function(entries){
          entries.forEach(function(entry){
            if (entry.isIntersecting && armed) {
              armed = false;
              flash();
            }
            if (!entry.isIntersecting) {
              armed = true;
            }
          });
        }, { threshold: 0.35 });

        io.observe(card);
      }
    })('{{ $uid }}');
  </script>
</section>
