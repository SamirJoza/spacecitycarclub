{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/blocks/trust-block.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the "Trust Block" for sponsor-facing proof points.
| - Show a heading row plus up to four flexible trust cards.
| - Support responsive card layouts that match the requested breakpoints.
| - Animate numeric auto-generated values each time cards enter the viewport.
|
| Why this file exists:
| - The sponsors page needs a dedicated credibility/stat block between the
|   masthead and the sponsor tiers.
| - The layout should adapt cleanly between 3 and 4 cards without relying on
|   brittle nth-child hacks.
| - The icon and accent system should stay visually linked automatically.
|
| Notes:
| - Desktop columns are controlled by the computed grid modifier class.
| - Tablet becomes 2 columns for 2/3/4 card layouts.
| - Mobile always stacks to 1 column.
| - Styling is instance-scoped to avoid bleeding into other blocks/pages.
| - The block outer band is intentionally a full section treatment while the
|   cards themselves keep the softer rounded corners.
| - Counter behavior is handled inline with the same GSAP loading pattern used
|   in the working hero block shared by the user.
--}}

@php
  $uid = 'sccc-trust-' . uniqid();
@endphp

<div id="{{ $uid }}" class="{{ $block->classes }}">
  <section class="sccc-trust">
    <div class="sccc-trust__inner">
      <header class="sccc-trust__header">
        <div class="sccc-trust__header-copy">
          @if (!empty($heading))
            <h2 class="sccc-trust__heading">{{ $heading }}</h2>
          @endif

          @if (!empty($intro))
            <div class="sccc-trust__intro">{!! $intro !!}</div>
          @endif
        </div>

        <div class="sccc-trust__rule" aria-hidden="true"></div>
      </header>

      @if (!empty($cards))
        <div class="sccc-trust__grid {{ $gridClass }}">
          @foreach ($cards as $card)
            <article class="sccc-trust__card {{ $card['accent_class'] }}" data-sccc-trust-card>
              <div class="sccc-trust__icon-wrap" aria-hidden="true">
                <span class="material-symbols-outlined sccc-trust__icon">{{ $card['icon'] }}</span>
              </div>

              <div class="sccc-trust__value">
                @if (!empty($card['prefix']))
                  <span class="sccc-trust__value-prefix">{{ $card['prefix'] }}</span>
                @endif

                <span class="sccc-trust__value-viewport">
                  <span
                    class="sccc-trust__value-main"
                    data-sccc-trust-value
                    @if ($card['can_animate'] && $card['raw_value'] !== null)
                      data-target="{{ $card['raw_value'] }}"
                      data-format="{{ $card['value_format'] }}"
                    @endif
                  >
                    {{ $card['display_value'] !== '' ? $card['display_value'] : '—' }}
                  </span>
                </span>

                @if (!empty($card['suffix']))
                  <span class="sccc-trust__value-suffix">{{ $card['suffix'] }}</span>
                @endif
              </div>

              <p class="sccc-trust__title">{{ $card['title'] }}</p>
            </article>
          @endforeach
        </div>
      @elseif (is_admin())
        <div class="sccc-trust__notice">
          Add at least one trust card to preview this block.
        </div>
      @endif
    </div>
  </section>

  <style>
    /* ==========================================================================
       File path + filename: resources/views/blocks/trust-block.blade.php
       Instance-scoped styles for the Trust Block
       ========================================================================== */

    #{{ $uid }} .sccc-trust {
      --trust-text: var(--color-text, #ffffff);
      --trust-muted: var(--color-muted, rgba(255, 255, 255, 0.74));
      --trust-surface: var(--color-surface, #111827);
      --trust-surface-2: color-mix(in oklab, var(--color-surface, #111827) 92%, black 8%);
      --trust-line: var(--color-line, rgba(255, 255, 255, 0.12));
      --trust-primary: var(--color-primary-500, #2979ff);
      --trust-shadow: 0 16px 34px rgba(0, 0, 0, 0.12);

      position: relative;
      overflow: hidden;
      border-top: 1px solid var(--trust-line);
      border-bottom: 1px solid var(--trust-line);
      background:
        radial-gradient(circle at top right, color-mix(in oklab, var(--trust-primary) 8%, transparent) 0%, transparent 28%),
        radial-gradient(circle at top left, rgba(255, 255, 255, 0.04), transparent 25%),
        color-mix(in oklab, var(--trust-surface) 96%, transparent);
      color: var(--trust-text);
    }

    #{{ $uid }} .sccc-trust__inner {
      width: min(100% - 2rem, 1280px);
      margin-inline: auto;
      padding: clamp(1.5rem, 2.8vw, 2.5rem) 0;
    }

    #{{ $uid }} .sccc-trust__header {
      display: flex;
      align-items: flex-end;
      gap: 1.25rem;
      margin-bottom: 1.6rem;
    }

    #{{ $uid }} .sccc-trust__header-copy {
      flex: 0 0 auto;
      max-width: 42rem;
    }

    #{{ $uid }} .sccc-trust__heading {
      margin: 0 0 0.35rem;
      font-size: clamp(1.9rem, 3vw, 2.6rem);
      line-height: 1.05;
      font-weight: 800;
      letter-spacing: -0.03em;
      color: var(--trust-text);
    }

    #{{ $uid }} .sccc-trust__intro {
      color: var(--trust-muted);
      line-height: 1.7;
    }

    #{{ $uid }} .sccc-trust__rule {
      flex: 1 1 auto;
      height: 1px;
      margin-bottom: 0.45rem;
      background: linear-gradient(90deg, var(--trust-line), transparent);
    }

    #{{ $uid }} .sccc-trust__grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1rem;
    }

    #{{ $uid }} .sccc-trust__grid.sccc-trust__grid--1 {
      grid-template-columns: 1fr;
    }

    #{{ $uid }} .sccc-trust__grid.sccc-trust__grid--2 {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    #{{ $uid }} .sccc-trust__grid.sccc-trust__grid--3 {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    #{{ $uid }} .sccc-trust__grid.sccc-trust__grid--4 {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    #{{ $uid }} .sccc-trust__card {
      --trust-accent: var(--trust-primary);
      --trust-accent-soft: #dbeafe;

      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      gap: 0.9rem;
      min-height: 100%;
      padding: 1.5rem;
      border: 1px solid var(--trust-line);
      border-radius: 1.2rem;
      background:
        linear-gradient(180deg, color-mix(in oklab, white 3%, transparent), transparent 26%),
        color-mix(in oklab, var(--trust-surface-2) 94%, transparent);
      box-shadow: var(--trust-shadow);
      transition:
        transform 180ms ease,
        border-color 180ms ease,
        box-shadow 180ms ease;
    }

    #{{ $uid }} .sccc-trust__card::before {
      content: "";
      position: absolute;
      inset: 0 0 auto 0;
      height: 3px;
      background: linear-gradient(90deg, var(--trust-accent), var(--trust-accent-soft));
    }

    #{{ $uid }} .sccc-trust__card::after {
      content: "";
      position: absolute;
      top: -2.5rem;
      right: -2rem;
      width: 8rem;
      height: 8rem;
      border-radius: 9999px;
      background: radial-gradient(circle, color-mix(in oklab, var(--trust-accent) 18%, transparent) 0%, transparent 66%);
      pointer-events: none;
    }

    #{{ $uid }} .sccc-trust__card:hover {
      transform: translateY(-4px);
      border-color: color-mix(in oklab, var(--trust-accent) 34%, var(--trust-line));
      box-shadow:
        var(--trust-shadow),
        0 12px 28px color-mix(in oklab, var(--trust-accent) 12%, transparent);
    }

    #{{ $uid }} .sccc-trust__card.is-primary {
      --trust-accent: #2979ff;
      --trust-accent-soft: #dbeafe;
    }

    #{{ $uid }} .sccc-trust__card.is-cyan {
      --trust-accent: #22d3ee;
      --trust-accent-soft: #cffafe;
    }

    #{{ $uid }} .sccc-trust__card.is-purple {
      --trust-accent: #a855f7;
      --trust-accent-soft: #f3e8ff;
    }

    #{{ $uid }} .sccc-trust__card.is-emerald {
      --trust-accent: #10b981;
      --trust-accent-soft: #d1fae5;
    }

    #{{ $uid }} .sccc-trust__card.is-amber {
      --trust-accent: #f59e0b;
      --trust-accent-soft: #fef3c7;
    }

    #{{ $uid }} .sccc-trust__icon-wrap {
      position: relative;
      width: 3rem;
      height: 3rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 9999px;
      background: color-mix(in oklab, var(--trust-accent) 12%, transparent);
      box-shadow: 0 0 0 0.35rem color-mix(in oklab, var(--trust-accent) 8%, transparent);
    }

    #{{ $uid }} .sccc-trust__icon {
      font-size: 1.7rem;
      line-height: 1;
      color: var(--trust-accent);
    }

    #{{ $uid }} .sccc-trust__value {
      display: flex;
      align-items: baseline;
      gap: 0.25rem;
      color: var(--trust-text);
    }

    #{{ $uid }} .sccc-trust__value-prefix,
    #{{ $uid }} .sccc-trust__value-suffix {
      font-size: 1rem;
      font-weight: 700;
      color: var(--trust-muted);
    }

    #{{ $uid }} .sccc-trust__value-viewport {
      display: inline-flex;
      align-items: baseline;
      overflow: hidden;
      min-height: 1em;
    }

    #{{ $uid }} .sccc-trust__value-main {
      display: inline-block;
      font-size: clamp(2rem, 3.5vw, 3rem);
      line-height: 1;
      font-weight: 800;
      letter-spacing: -0.04em;
      color: var(--trust-text);
      font-variant-numeric: tabular-nums;
      will-change: transform, opacity;
    }

    #{{ $uid }} .sccc-trust__title {
      margin: 0;
      color: var(--trust-muted);
      font-size: 1rem;
      line-height: 1.55;
      font-weight: 600;
    }

    #{{ $uid }} .sccc-trust__notice {
      padding: 1rem 1.1rem;
      border: 1px dashed var(--trust-line);
      border-radius: 1rem;
      background: color-mix(in oklab, var(--trust-primary) 8%, transparent);
      color: var(--trust-muted);
    }

    @media (max-width: 1024px) {
      #{{ $uid }} .sccc-trust__grid.sccc-trust__grid--2,
      #{{ $uid }} .sccc-trust__grid.sccc-trust__grid--3,
      #{{ $uid }} .sccc-trust__grid.sccc-trust__grid--4 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      #{{ $uid }} .sccc-trust__header {
        align-items: flex-start;
        flex-direction: column;
      }

      #{{ $uid }} .sccc-trust__rule {
        width: 100%;
        margin-bottom: 0;
      }
    }

    @media (max-width: 767px) {
      #{{ $uid }} .sccc-trust__inner {
        width: min(100% - 1rem, 1280px);
      }

      #{{ $uid }} .sccc-trust__grid.sccc-trust__grid--2,
      #{{ $uid }} .sccc-trust__grid.sccc-trust__grid--3,
      #{{ $uid }} .sccc-trust__grid.sccc-trust__grid--4 {
        grid-template-columns: 1fr;
      }

      #{{ $uid }} .sccc-trust__card {
        padding: 1.25rem;
      }
    }
  </style>

  <script>
    (function(rootId){
      var root = document.getElementById(rootId);
      if (!root) return;

      if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
      }

      var needs = [];
      if (!window.gsap) needs.push('https://cdn.jsdelivr.net/npm/gsap@3/dist/gsap.min.js');
      if (!window.ScrollTrigger) needs.push('https://cdn.jsdelivr.net/npm/gsap@3/dist/ScrollTrigger.min.js');

      function load(u){
        return new Promise(function(res, rej){
          var s = document.createElement('script');
          s.src = u;
          s.onload = res;
          s.onerror = rej;
          document.head.appendChild(s);
        });
      }

      (needs.reduce(function(p, u){
        return p.then(function(){ return load(u); });
      }, Promise.resolve())).then(init).catch(init);

      function init(){
        var gsap = window.gsap;
        var ST = window.ScrollTrigger;

        if (!gsap || !ST) return;

        gsap.registerPlugin(ST);

        var cards = Array.prototype.slice.call(
          root.querySelectorAll('[data-sccc-trust-card]')
        ).filter(function(card){
          return card.querySelector('[data-sccc-trust-value][data-target]');
        });

        if (!cards.length) return;

        function formatRoundedPlus(value){
          var number = Math.max(0, Math.floor(value));

          if (number < 10) return String(number);
          if (number < 100) return String(Math.floor(number / 10) * 10) + '+';
          if (number < 1000) return String(Math.floor(number / 100) * 100) + '+';

          return String(Math.floor(number / 1000)) + 'k+';
        }

        function formatExact(value){
          var number = Math.max(0, Math.floor(value));
          return new Intl.NumberFormat().format(number);
        }

        function renderValue(el, value){
          var format = el.getAttribute('data-format') || 'rounded_plus';

          el.textContent = format === 'exact'
            ? formatExact(value)
            : formatRoundedPlus(value);
        }

        var instanceTriggers = [];

        function killInstance(){
          instanceTriggers.forEach(function(trigger){
            try { trigger.kill(); } catch (e) {}
          });

          instanceTriggers = [];

          cards.forEach(function(card){
            var valueEl = card.querySelector('[data-sccc-trust-value][data-target]');
            if (!valueEl) return;

            if (valueEl._scccTrustState) {
              try { gsap.killTweensOf(valueEl._scccTrustState); } catch (e) {}
            }

            try { gsap.killTweensOf(valueEl); } catch (e) {}
          });
        }

        function resetCard(card){
          var valueEl = card.querySelector('[data-sccc-trust-value][data-target]');
          if (!valueEl) return;

          if (valueEl._scccTrustState) {
            gsap.killTweensOf(valueEl._scccTrustState);
            valueEl._scccTrustState.value = 0;
          }

          gsap.killTweensOf(valueEl);
          renderValue(valueEl, 0);

          gsap.set(valueEl, {
            yPercent: 110,
            autoAlpha: 0
          });
        }

        function animateCard(card){
          var valueEl = card.querySelector('[data-sccc-trust-value][data-target]');
          if (!valueEl) return;

          var target = parseInt(valueEl.getAttribute('data-target') || '', 10);
          if (isNaN(target)) return;

          if (!valueEl._scccTrustState) {
            valueEl._scccTrustState = { value: 0 };
          }

          var state = valueEl._scccTrustState;

          gsap.killTweensOf(state);
          gsap.killTweensOf(valueEl);

          state.value = 0;
          renderValue(valueEl, 0);

          var tl = gsap.timeline({
            defaults: { overwrite: true }
          });

          tl.fromTo(valueEl, {
            yPercent: 110,
            autoAlpha: 0
          }, {
            yPercent: 0,
            autoAlpha: 1,
            duration: 0.45,
            ease: 'power2.out'
          }, 0);

          tl.to(state, {
            value: target,
            duration: 1.05,
            ease: 'power2.out',
            onUpdate: function(){
              renderValue(valueEl, state.value);
            },
            onComplete: function(){
              renderValue(valueEl, target);
            }
          }, 0);
        }

        killInstance();

        cards.forEach(function(card){
          resetCard(card);

          var trigger = ST.create({
            trigger: card,
            start: 'top 85%',
            end: 'bottom 15%',
            onEnter: function(){ animateCard(card); },
            onEnterBack: function(){ animateCard(card); },
            onLeave: function(){ resetCard(card); },
            onLeaveBack: function(){ resetCard(card); }
          });

          instanceTriggers.push(trigger);

          if (trigger.isActive) {
            animateCard(card);
          }
        });

        window.addEventListener('resize', function(){
          try { ST.refresh(); } catch(e) {}
        }, { passive: true });

        try { ST.refresh(); } catch(e) {}
      }
    })('{{ $uid }}');
  </script>
</div>