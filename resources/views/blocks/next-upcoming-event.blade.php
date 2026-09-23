{{--
==============================================================================
File path + filename: resources/views/blocks/next-upcoming-event.blade.php
==============================================================================

Purpose:
- Render a compact, fluid, sidebar-ready card for the custom
  "Next Upcoming Event" block.

Why this file exists:
- The block output is role-aware and data-driven, so the markup belongs in a
  server-rendered Blade view instead of static editor content.
- The visual target for this block is the user-provided sidebar HTML mockup:
  image band, centered event icon + "Next Meetup" text, compact date tile,
  title/location row, and a full-width outlined button.
- The CSS is intentionally scoped and embedded here so it stays contained
  within the block and does not add weight to the global theme stylesheet.

Styling notes:
- All selectors are scoped to `.sccc-next-upcoming-event`.
- Dark mode supports both project signals:
  - [data-theme="dark"]
  - .dark
- Light mode is the default/base layer.

Important:
- `$attributes` is provided by ACF Composer and carries block wrapper classes,
  anchor, and support attributes.
- `$card` is either a fully prepared event view model or `null`.
- The CTA text is intentionally rendered as "RSVP Now" to match the supplied
  HTML reference, but the URL still points to the event permalink.
--}}

@once
  <style>
    /* ==========================================================================
       Next Upcoming Event block
       Scoped styles only — intentionally not part of the global theme bundle.
       ========================================================================== */

    .sccc-next-upcoming-event {
      --sccc-next-card-bg: #ffffff;
      --sccc-next-card-border: rgba(226, 232, 240, 1);
      --sccc-next-card-shadow:
        0 20px 25px -5px rgba(0, 0, 0, 0.10),
        0 8px 10px -6px rgba(0, 0, 0, 0.10);
      --sccc-next-header-bg: #1313ec;
      --sccc-next-header-overlay: rgba(19, 19, 236, 0.80);
      --sccc-next-text: #0f172a;
      --sccc-next-muted: #64748b;
      --sccc-next-button-text: #334155;
      --sccc-next-button-border: rgba(203, 213, 225, 1);
      --sccc-next-button-hover: #f8fafc;
      --sccc-next-date-bg: #f1f5f9;
      --sccc-next-date-month: #64748b;
      --sccc-next-date-day: #0f172a;
      --sccc-next-radius-card: 1.5rem;
      --sccc-next-radius-inner: 1rem;
      --sccc-next-radius-button: 0.75rem;
      width: 100%;
      margin-block: 1rem;
    }

    [data-theme="dark"] .sccc-next-upcoming-event,
    .dark .sccc-next-upcoming-event {
      --sccc-next-card-bg: #1a1a2e;
      --sccc-next-card-border: rgba(255, 255, 255, 0.05);
      --sccc-next-card-shadow:
        0 20px 25px -5px rgba(0, 0, 0, 0.35),
        0 8px 10px -6px rgba(0, 0, 0, 0.22);
      --sccc-next-header-bg: #232348;
      --sccc-next-header-overlay: rgba(19, 19, 236, 0.78);
      --sccc-next-text: #ffffff;
      --sccc-next-muted: #94a3b8;
      --sccc-next-button-text: #ffffff;
      --sccc-next-button-border: rgba(255, 255, 255, 0.20);
      --sccc-next-button-hover: rgba(255, 255, 255, 0.05);
      --sccc-next-date-bg: rgba(255, 255, 255, 0.10);
      --sccc-next-date-month: #9ca3af;
      --sccc-next-date-day: #ffffff;
    }

    .sccc-next-upcoming-event *,
    .sccc-next-upcoming-event *::before,
    .sccc-next-upcoming-event *::after {
      box-sizing: border-box;
    }

    .sccc-next-upcoming-event__card {
      width: 100%;
      overflow: hidden;
      border: 1px solid var(--sccc-next-card-border);
      border-radius: var(--sccc-next-radius-card);
      background: var(--sccc-next-card-bg);
      box-shadow: var(--sccc-next-card-shadow);
    }

    .sccc-next-upcoming-event__header {
      position: relative;
      min-height: 8rem;
      overflow: hidden;
      background-color: var(--sccc-next-header-bg);
      background-position: center;
      background-repeat: no-repeat;
      background-size: cover;
    }

    .sccc-next-upcoming-event__header::before {
      content: '';
      position: absolute;
      inset: 0;
      background: var(--sccc-next-header-overlay);
      mix-blend-mode: multiply;
      pointer-events: none;
    }

    .sccc-next-upcoming-event__header-content {
      position: absolute;
      inset: 0;
      z-index: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      text-align: center;
      color: #ffffff;
    }

    .sccc-next-upcoming-event__hero-icon {
      width: 1.9rem;
      height: 1.9rem;
      margin-bottom: 0.35rem;
      display: block;
      color: #ffffff;
    }

    .sccc-next-upcoming-event__hero-title {
      margin: 0;
      color: #ffffff;
      font-size: 1.125rem;
      font-weight: 700;
      line-height: 1.2;
      letter-spacing: 0;
    }

    .sccc-next-upcoming-event__body {
      padding: 1.25rem;
    }

    .sccc-next-upcoming-event__summary {
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
      margin-bottom: 1rem;
    }

    .sccc-next-upcoming-event__date-box {
      flex: 0 0 auto;
      min-width: 3.125rem;
      padding: 0.5rem;
      border-radius: 0.75rem;
      background: var(--sccc-next-date-bg);
      text-align: center;
    }

    .sccc-next-upcoming-event__month {
      display: block;
      color: var(--sccc-next-date-month);
      font-size: 0.75rem;
      font-weight: 400;
      line-height: 1;
      text-transform: uppercase;
    }

    .sccc-next-upcoming-event__day {
      display: block;
      margin-top: 0.125rem;
      color: var(--sccc-next-date-day);
      font-size: 1.25rem;
      font-weight: 700;
      line-height: 1.1;
    }

    .sccc-next-upcoming-event__details {
      min-width: 0;
      flex: 1 1 auto;
    }

    .sccc-next-upcoming-event__title {
      margin: 0;
      color: var(--sccc-next-text);
      font-size: 0.98rem;
      font-weight: 700;
      line-height: 1.25;
    }

    .sccc-next-upcoming-event__title a {
      color: inherit;
      text-decoration: none;
    }

    .sccc-next-upcoming-event__title a:hover,
    .sccc-next-upcoming-event__title a:focus-visible {
      color: inherit;
      text-decoration: none;
    }

    .sccc-next-upcoming-event__meta {
      margin-top: 0.25rem;
      color: var(--sccc-next-muted);
      font-size: 0.75rem;
      line-height: 1.4;
    }

    .sccc-next-upcoming-event__location {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      max-width: 100%;
      color: var(--sccc-next-muted);
      font-size: 0.75rem;
      line-height: 1.4;
    }

    .sccc-next-upcoming-event__location-icon {
      width: 0.78rem;
      height: 0.78rem;
      flex: 0 0 auto;
      display: block;
    }

    .sccc-next-upcoming-event__button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      min-height: 2.75rem;
      padding: 0.625rem 1rem;
      border: 1px solid var(--sccc-next-button-border);
      border-radius: var(--sccc-next-radius-button);
      background: transparent;
      color: var(--sccc-next-button-text);
      font-size: 0.875rem;
      font-weight: 700;
      line-height: 1.2;
      text-decoration: none;
      transition:
        background-color 180ms ease,
        border-color 180ms ease,
        color 180ms ease;
    }

    .sccc-next-upcoming-event__button:hover,
    .sccc-next-upcoming-event__button:focus-visible {
      background: var(--sccc-next-button-hover);
      color: var(--sccc-next-button-text);
      text-decoration: none;
    }

    .sccc-next-upcoming-event__empty {
      padding: 1.25rem;
    }

    .sccc-next-upcoming-event__empty-title {
      margin: 0 0 0.35rem;
      color: var(--sccc-next-text);
      font-size: 1rem;
      font-weight: 700;
      line-height: 1.2;
    }

    .sccc-next-upcoming-event__empty-copy {
      margin: 0;
      color: var(--sccc-next-muted);
      font-size: 0.875rem;
      line-height: 1.5;
    }

    .sccc-next-upcoming-event__preview-note {
      margin-top: 0.75rem;
      color: var(--sccc-next-muted);
      font-size: 0.75rem;
      line-height: 1.45;
    }
  </style>
@endonce

<section {{ $attributes->class('sccc-next-upcoming-event') }}>
  @if ($card)
    <article class="sccc-next-upcoming-event__card">
      <div
        class="sccc-next-upcoming-event__header"
        @if ($card['imageUrl'])
          style="background-image: url('{{ esc_url($card['imageUrl']) }}');"
        @endif
      >
        <div class="sccc-next-upcoming-event__header-content">
          <svg class="sccc-next-upcoming-event__hero-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
              d="M7 3v2M17 3v2M4 8h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>

          <h3 class="sccc-next-upcoming-event__hero-title">Next Meetup</h3>
        </div>
      </div>

      <div class="sccc-next-upcoming-event__body">
        <div class="sccc-next-upcoming-event__summary">
          <div class="sccc-next-upcoming-event__date-box" aria-hidden="true">
            <span class="sccc-next-upcoming-event__month">{{ $card['monthText'] }}</span>
            <span class="sccc-next-upcoming-event__day">{{ $card['dayText'] }}</span>
          </div>

          <div class="sccc-next-upcoming-event__details">
            <h4 class="sccc-next-upcoming-event__title">
              <a href="{{ esc_url($card['url']) }}">
                {{ $card['title'] }}
              </a>
            </h4>

            @if ($card['locationText'])
              <p class="sccc-next-upcoming-event__meta">
                <span class="sccc-next-upcoming-event__location">
                  <svg class="sccc-next-upcoming-event__location-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path
                      d="M12 21s6-5.686 6-11a6 6 0 1 0-12 0c0 5.314 6 11 6 11Z"
                      stroke="currentColor"
                      stroke-width="1.8"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                    <circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.8" />
                  </svg>
                  <span>{{ $card['locationText'] }}</span>
                </span>
              </p>
            @elseif ($card['fullDateText'] || $card['timeText'])
              <p class="sccc-next-upcoming-event__meta">
                {{ $card['fullDateText'] }}@if($card['timeText']) &bull; {{ $card['timeText'] }}@endif
              </p>
            @endif
          </div>
        </div>

        <a class="sccc-next-upcoming-event__button" href="{{ esc_url($card['url']) }}">
          RSVP Now
        </a>

        @if ($isPreview)
          <p class="sccc-next-upcoming-event__preview-note">
            Live output is role-aware. Visitors without the <strong>sccc_member</strong> role will only see public events.
          </p>
        @endif
      </div>
    </article>
  @else
    <div class="sccc-next-upcoming-event__card">
      <div class="sccc-next-upcoming-event__header">
        <div class="sccc-next-upcoming-event__header-content">
          <svg class="sccc-next-upcoming-event__hero-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
              d="M7 3v2M17 3v2M4 8h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>

          <h3 class="sccc-next-upcoming-event__hero-title">Next Meetup</h3>
        </div>
      </div>

      <div class="sccc-next-upcoming-event__empty">
        <h4 class="sccc-next-upcoming-event__empty-title">No Upcoming Event</h4>
        <p class="sccc-next-upcoming-event__empty-copy">{{ $emptyMessage }}</p>

        @if ($isPreview)
          <p class="sccc-next-upcoming-event__preview-note">
            This block automatically populates when a future Mage EventPress event is available for the current viewer.
          </p>
        @endif
      </div>
    </div>
  @endif
</section>