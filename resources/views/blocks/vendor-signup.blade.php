{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/blocks/vendor-signup.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the Vendor Sign-up block: open shows, the "Have you been a vendor
|   with us before?" choice, and the matching Gravity Form.
|
| Notes:
| - Both forms are rendered server-side; the choice only toggles which panel
|   is visible (progressive enhancement — without JS both panels show).
| - ?vendor_signup=returning|new preselects a panel (email / invite links).
| - All CSS is scoped to .sccc-vendor-signup and uses the theme tokens.
--}}

@once
  <style>
    .sccc-vendor-signup { padding-block: clamp(2.5rem, 6vw, 5rem); }
    .sccc-vendor-signup__inner { max-width: 56rem; margin-inline: auto; padding-inline: 1rem; }
    .sccc-vendor-signup__eyebrow { color: var(--color-primary-500); }
    .sccc-vendor-signup__heading { font-size: var(--text-h3); line-height: var(--text-h3--line-height); margin: .35rem 0 .75rem; }
    .sccc-vendor-signup__intro { color: var(--color-muted); max-width: 44rem; }

    .sccc-vendor-signup__shows { display: grid; gap: .75rem; margin: 1.75rem 0; padding: 0; list-style: none; }
    .sccc-vendor-signup__show {
      display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem;
      padding: 1rem 1.25rem; border-radius: var(--radius-xl);
      background: var(--color-card-bg); border: 1px solid var(--color-card-border);
    }
    .sccc-vendor-signup__show-title { font-family: var(--font-display); font-weight: 700; }
    .sccc-vendor-signup__show-meta { color: var(--color-muted); font-size: var(--text-body-sm); }
    .sccc-vendor-signup__fee { margin-left: auto; font-weight: 700; color: var(--color-primary-500); }
    .sccc-vendor-signup__tag {
      font-size: .75rem; font-weight: 600; padding: .15rem .6rem; border-radius: 999px;
      background: color-mix(in srgb, #f97316 15%, transparent); color: #c2410c;
    }
    .dark .sccc-vendor-signup__tag { color: #fdba74; }

    .sccc-vendor-signup__question { font-family: var(--font-display); font-size: var(--text-h6); font-weight: 700; margin: 2rem 0 1rem; }
    .sccc-vendor-signup__choices { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); }
    .sccc-vendor-signup__choice {
      text-align: left; cursor: pointer; padding: 1.25rem; border-radius: var(--radius-xl);
      background: var(--color-card-bg); border: 2px solid var(--color-card-border); color: var(--color-text);
      transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }
    .sccc-vendor-signup__choice:hover { transform: translateY(-1px); border-color: color-mix(in srgb, var(--color-primary-500) 50%, var(--color-card-border)); }
    .sccc-vendor-signup__choice:focus-visible { outline: 2px solid var(--color-primary-500); outline-offset: 2px; }
    .sccc-vendor-signup__choice[aria-pressed="true"] { border-color: var(--color-primary-500); box-shadow: var(--shadow-primary-glow); }
    .sccc-vendor-signup__choice-label { display: block; font-weight: 700; font-size: 1.05rem; }
    .sccc-vendor-signup__choice-hint { display: block; color: var(--color-muted); font-size: var(--text-body-sm); margin-top: .25rem; }

    .sccc-vendor-signup__panel {
      margin-top: 1.5rem; padding: clamp(1.25rem, 3vw, 2rem); border-radius: var(--radius-xl);
      background: var(--color-card-bg); border: 1px solid var(--color-card-border);
    }
    .sccc-vendor-signup.is-enhanced .sccc-vendor-signup__panel[hidden] { display: none; }
    .sccc-vendor-signup__closed { margin-top: 1.5rem; color: var(--color-muted); }
    .sccc-vendor-message .sccc-vendor-button,
    .sccc-vendor-signup .sccc-vendor-button {
      display: inline-block; margin-top: .5rem; padding: .7rem 1.4rem; border-radius: 999px;
      background: var(--color-primary-500); color: #fff; font-weight: 600; text-decoration: none;
    }
    @media (prefers-reduced-motion: reduce) {
      .sccc-vendor-signup__choice { transition: none; }
      .sccc-vendor-signup__choice:hover { transform: none; }
    }
  </style>
@endonce

<section {{ $attributes->class(['sccc-vendor-signup']) }} id="vendor-signup" data-vendor-signup data-open="{{ esc_attr($open) }}">
  <div class="sccc-vendor-signup__inner">
    @if ($eyebrow)
      <p class="eyebrow sccc-vendor-signup__eyebrow">{{ $eyebrow }}</p>
    @endif

    @if ($heading)
      <h2 class="sccc-vendor-signup__heading">{{ $heading }}</h2>
    @endif

    @if ($intro)
      <div class="sccc-vendor-signup__intro">{!! wp_kses_post($intro) !!}</div>
    @endif

    @if (empty($shows))
      <p class="sccc-vendor-signup__closed">{{ $closedMessage }}</p>
    @else
      <ul class="sccc-vendor-signup__shows" role="list">
        @foreach ($shows as $show)
          <li class="sccc-vendor-signup__show">
            <div>
              <div class="sccc-vendor-signup__show-title">{{ $show['title'] }}</div>
              <div class="sccc-vendor-signup__show-meta">
                {{ $show['date'] }}@if ($show['venue']) · {{ $show['venue'] }}@endif
              </div>
            </div>

            @if ($show['noFood'])
              <span class="sccc-vendor-signup__tag">{{ __('No food vendors', 'sccc') }}</span>
            @endif

            @if ($show['fee'])
              <span class="sccc-vendor-signup__fee">{{ $show['fee'] }}</span>
            @endif
          </li>
        @endforeach
      </ul>

      @if (! $gfActive)
        <p class="sccc-vendor-signup__closed">{{ __('The vendor forms are not available right now. Please contact us.', 'sccc') }}</p>
      @else
        <p class="sccc-vendor-signup__question" id="vendor-signup-question">{{ $question }}</p>

        <div class="sccc-vendor-signup__choices" role="group" aria-labelledby="vendor-signup-question">
          <button type="button" class="sccc-vendor-signup__choice" data-vendor-choice="returning" aria-pressed="false" aria-controls="vendor-panel-returning">
            <span class="sccc-vendor-signup__choice-label">{{ $returningLabel }}</span>
            <span class="sccc-vendor-signup__choice-hint">{{ $returningHint }}</span>
          </button>

          <button type="button" class="sccc-vendor-signup__choice" data-vendor-choice="new" aria-pressed="false" aria-controls="vendor-panel-new">
            <span class="sccc-vendor-signup__choice-label">{{ $newLabel }}</span>
            <span class="sccc-vendor-signup__choice-hint">{{ $newHint }}</span>
          </button>
        </div>

        <div class="sccc-vendor-signup__panel" id="vendor-panel-returning" data-vendor-panel="returning">
          {!! $returningForm !!}
        </div>

        <div class="sccc-vendor-signup__panel" id="vendor-panel-new" data-vendor-panel="new">
          {!! $applicationForm !!}
        </div>
      @endif
    @endif
  </div>
</section>

@once
  <script>
    (function () {
      function init(root) {
        var choices = root.querySelectorAll('[data-vendor-choice]');
        var panels = root.querySelectorAll('[data-vendor-panel]');
        if (!choices.length) return;

        root.classList.add('is-enhanced');

        function show(name, focus) {
          panels.forEach(function (panel) { panel.hidden = panel.getAttribute('data-vendor-panel') !== name; });
          choices.forEach(function (btn) { btn.setAttribute('aria-pressed', btn.getAttribute('data-vendor-choice') === name ? 'true' : 'false'); });
          if (focus) {
            var target = root.querySelector('[data-vendor-panel="' + name + '"] input:not([type=hidden]), [data-vendor-panel="' + name + '"] select');
            if (target) target.focus({ preventScroll: false });
          }
        }

        choices.forEach(function (btn) {
          btn.addEventListener('click', function () { show(btn.getAttribute('data-vendor-choice'), true); });
        });

        var open = root.getAttribute('data-open');
        if (open === 'new' || open === 'returning') {
          show(open, false);
        } else {
          panels.forEach(function (panel) { panel.hidden = true; });
        }
      }

      function boot() { document.querySelectorAll('[data-vendor-signup]').forEach(init); }
      document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', boot) : boot();
    })();
  </script>
@endonce
