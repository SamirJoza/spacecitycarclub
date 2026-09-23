{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/blocks/discord-invite.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render a compact Discord/community invite block.
|
| Why this view exists:
| - This block is designed for sidebars and widget areas first.
| - It follows the simpler compact reference structure:
|   icon tile, small meta row, compact headline, short description, one CTA.
|
| Notes:
| - The icon is rendered via Material Symbols ligature text using the
|   `material-symbols-outlined` class.
| - This assumes the Material Symbols font is already loaded globally.
| - The CTA is intentionally styled as a darker glass pill with a neon border,
|   closer to the reference button treatment you shared.
|--------------------------------------------------------------------------
--}}

@php
  $anchor = isset($block->anchor) && is_string($block->anchor) ? trim($block->anchor) : '';
  $seed = isset($block->id) && is_string($block->id) ? $block->id : uniqid('discord-invite-', true);
  $uid = $anchor !== '' ? $anchor : 'sccc-discord-invite-' . substr(md5($seed), 0, 12);

  $blockClasses = isset($block->classes) && is_string($block->classes) ? trim($block->classes) : '';
  $wrapperClasses = trim('wp-block-sccc-discord-invite ' . $blockClasses);

  $buttonUrl = $buttonLink['url'] ?? '';
  $buttonTarget = $buttonLink['target'] ?? '';
@endphp

<div id="{{ $uid }}" class="{{ $wrapperClasses }}">
  <section class="sccc-di sccc-di--{{ $accentTheme }}">
    <div class="sccc-di__card">
      <span class="sccc-di__topline" aria-hidden="true"></span>
      <span class="sccc-di__glow sccc-di__glow--one" aria-hidden="true"></span>
      <span class="sccc-di__glow sccc-di__glow--two" aria-hidden="true"></span>
      <span class="sccc-di__dash" aria-hidden="true"></span>

      <div class="sccc-di__header">
        <div class="sccc-di__icon-tile" aria-hidden="true">
          <span class="material-symbols-outlined sccc-di__icon">{{ $iconName }}</span>
        </div>

        <div class="sccc-di__meta">
          @if (! empty($eyebrow))
            <p class="sccc-di__eyebrow">{{ $eyebrow }}</p>
          @endif

          @if (! empty($status))
            <p class="sccc-di__status">
              <span class="sccc-di__status-dot" aria-hidden="true"></span>
              {{ $status }}
            </p>
          @endif
        </div>
      </div>

      <div class="sccc-di__body">
        <h3 class="sccc-di__headline">{{ $headline }}</h3>

        @if (! empty($description))
          <p class="sccc-di__description">{!! nl2br(e($description)) !!}</p>
        @endif

        @if (! empty($buttonUrl) && ! empty($buttonText))
          <div class="sccc-di__actions">
            <a
              class="sccc-di__button"
              href="{{ $buttonUrl }}"
              @if (! empty($buttonTarget)) target="{{ $buttonTarget }}" @endif
              @if ($buttonTarget === '_blank') rel="noopener noreferrer" @endif
            >
              {{ $buttonText }}
            </a>
          </div>
        @endif
      </div>
    </div>
  </section>

  <style>
    /* ==========================================================================
       File path + filename: resources/views/blocks/discord-invite.blade.php
       Instance-scoped styles for the compact Discord Invite block
       ========================================================================== */

    #{{ $uid }} .sccc-di {
      --di-surface-light: rgba(255, 255, 255, 0.84);
      --di-border-light: rgba(15, 23, 42, 0.1);
      --di-text-light: #0f172a;
      --di-muted-light: #5b6472;
      --di-shadow-light:
        0 16px 34px rgba(15, 23, 42, 0.08),
        0 6px 14px rgba(15, 23, 42, 0.04);

      --di-surface-dark: rgba(16, 22, 38, 0.84);
      --di-border-dark: rgba(255, 255, 255, 0.08);
      --di-text-dark: #eef4ff;
      --di-muted-dark: #a8b4c8;
      --di-shadow-dark:
        0 22px 48px rgba(0, 0, 0, 0.38),
        0 8px 20px rgba(0, 0, 0, 0.22);

      --di-primary: #5fd3ff;
      --di-secondary: #8a72ff;
      --di-accent: #7ef6b6;

      /* Button tokens */
      --di-button-fill-light-top: rgba(10, 26, 48, 0.12);
      --di-button-fill-light-bottom: rgba(10, 26, 48, 0.06);
      --di-button-fill-dark-top: rgba(10, 18, 34, 0.74);
      --di-button-fill-dark-bottom: rgba(7, 14, 28, 0.54);
      --di-button-text-light: #17324d;
      --di-button-text-dark: #f5f9ff;
      --di-button-shadow-light:
        0 10px 20px rgba(15, 23, 42, 0.08),
        0 0 18px rgba(95, 211, 255, 0.08);
      --di-button-shadow-dark:
        0 12px 24px rgba(0, 0, 0, 0.32),
        0 0 20px rgba(95, 211, 255, 0.10);

      position: relative;
      width: 100%;
      color: var(--di-text-light);
      font-family: var(--font-sans, inherit);
    }

    html.dark #{{ $uid }} .sccc-di,
    [data-theme="dark"] #{{ $uid }} .sccc-di {
      color: var(--di-text-dark);
    }

    #{{ $uid }} .sccc-di--violet {
      --di-primary: #9b7cff;
      --di-secondary: #d96fff;
      --di-accent: #7ef6b6;
    }

    #{{ $uid }} .sccc-di--red {
      --di-primary: #ff647f;
      --di-secondary: #ff9a45;
      --di-accent: #ffd166;
    }

    #{{ $uid }} .sccc-di__card {
      position: relative;
      overflow: hidden;
      border-radius: 1.25rem;
      border: 1px solid var(--di-border-light);
      background:
        radial-gradient(120% 100% at 100% 0%, color-mix(in srgb, var(--di-primary) 12%, transparent), transparent 54%),
        radial-gradient(120% 100% at 0% 100%, color-mix(in srgb, var(--di-secondary) 12%, transparent), transparent 56%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(245, 248, 255, 0.82));
      box-shadow: var(--di-shadow-light);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      padding: 1rem;
      isolation: isolate;
    }

    html.dark #{{ $uid }} .sccc-di__card,
    [data-theme="dark"] #{{ $uid }} .sccc-di__card {
      border-color: var(--di-border-dark);
      background:
        radial-gradient(120% 100% at 100% 0%, color-mix(in srgb, var(--di-primary) 16%, transparent), transparent 54%),
        radial-gradient(120% 100% at 0% 100%, color-mix(in srgb, var(--di-secondary) 14%, transparent), transparent 56%),
        linear-gradient(180deg, rgba(11, 16, 30, 0.95), rgba(9, 13, 25, 0.88));
      box-shadow: var(--di-shadow-dark);
    }

    #{{ $uid }} .sccc-di__topline {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 2px;
      background: linear-gradient(
        90deg,
        transparent 0%,
        color-mix(in srgb, var(--di-primary) 96%, white) 24%,
        color-mix(in srgb, var(--di-secondary) 92%, white) 76%,
        transparent 100%
      );
      pointer-events: none;
    }

    #{{ $uid }} .sccc-di__glow {
      position: absolute;
      border-radius: 999px;
      filter: blur(34px);
      pointer-events: none;
      opacity: 0.5;
    }

    #{{ $uid }} .sccc-di__glow--one {
      top: -1.25rem;
      right: -0.8rem;
      width: 5.5rem;
      height: 5.5rem;
      background: color-mix(in srgb, var(--di-primary) 34%, transparent);
    }

    #{{ $uid }} .sccc-di__glow--two {
      bottom: -1.5rem;
      left: -1.2rem;
      width: 5rem;
      height: 5rem;
      background: color-mix(in srgb, var(--di-secondary) 26%, transparent);
    }

    #{{ $uid }} .sccc-di__dash {
      position: absolute;
      right: 0.75rem;
      bottom: 0.7rem;
      width: 4.5rem;
      height: 1.2rem;
      opacity: 0.14;
      background-image: repeating-linear-gradient(
        90deg,
        currentColor 0,
        currentColor 8px,
        transparent 8px,
        transparent 14px
      );
      pointer-events: none;
    }

    #{{ $uid }} .sccc-di__header {
      position: relative;
      z-index: 1;
      display: flex;
      align-items: center;
      gap: 0.85rem;
    }

    #{{ $uid }} .sccc-di__icon-tile {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 3rem;
      height: 3rem;
      flex-shrink: 0;
      border-radius: 0.95rem;
      border: 1px solid color-mix(in srgb, var(--di-primary) 24%, transparent);
      background:
        linear-gradient(180deg, color-mix(in srgb, var(--di-primary) 14%, white), color-mix(in srgb, var(--di-secondary) 12%, white));
      box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.3),
        0 10px 22px color-mix(in srgb, var(--di-primary) 14%, transparent);
      color: color-mix(in srgb, var(--di-primary) 72%, #0f172a);
    }

    html.dark #{{ $uid }} .sccc-di__icon-tile,
    [data-theme="dark"] #{{ $uid }} .sccc-di__icon-tile {
      background:
        linear-gradient(180deg, color-mix(in srgb, var(--di-primary) 12%, rgba(255,255,255,0.08)), color-mix(in srgb, var(--di-secondary) 10%, rgba(255,255,255,0.03)));
      color: color-mix(in srgb, var(--di-primary) 82%, white);
    }

    #{{ $uid }} .sccc-di__icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 1.45rem;
      height: 1.45rem;
      font-size: 1.45rem;
      line-height: 1;
      font-variation-settings:
        'FILL' 0,
        'wght' 400,
        'GRAD' 0,
        'opsz' 24;
    }

    #{{ $uid }} .sccc-di__meta {
      min-width: 0;
      display: grid;
      gap: 0.28rem;
    }

    #{{ $uid }} .sccc-di__eyebrow {
      margin: 0;
      font-size: 0.7rem;
      font-weight: 800;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: color-mix(in srgb, var(--di-primary) 76%, currentColor);
    }

    #{{ $uid }} .sccc-di__status {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      margin: 0;
      font-size: 0.8rem;
      font-weight: 600;
      line-height: 1.3;
      color: var(--di-muted-light);
    }

    html.dark #{{ $uid }} .sccc-di__status,
    [data-theme="dark"] #{{ $uid }} .sccc-di__status {
      color: var(--di-muted-dark);
    }

    #{{ $uid }} .sccc-di__status-dot {
      width: 0.46rem;
      height: 0.46rem;
      border-radius: 999px;
      flex-shrink: 0;
      background: var(--di-accent);
      box-shadow: 0 0 12px color-mix(in srgb, var(--di-accent) 56%, transparent);
    }

    #{{ $uid }} .sccc-di__body {
      position: relative;
      z-index: 1;
      margin-top: 0.95rem;
    }

    #{{ $uid }} .sccc-di__headline {
      margin: 0;
      font-family: var(--font-sans, inherit);
      font-size: clamp(1.28rem, 2.4vw, 1.62rem);
      line-height: 1.12;
      font-weight: 700;
      letter-spacing: -0.02em;
      text-wrap: balance;
      color: inherit;
    }

    #{{ $uid }} .sccc-di__description {
      margin: 0.76rem 0 0;
      font-size: 0.95rem;
      line-height: 1.72;
      font-weight: 400;
      color: var(--di-muted-light);
      text-wrap: pretty;
    }

    html.dark #{{ $uid }} .sccc-di__description,
    [data-theme="dark"] #{{ $uid }} .sccc-di__description {
      color: var(--di-muted-dark);
    }

    #{{ $uid }} .sccc-di__actions {
      margin-top: 1rem;
    }

    #{{ $uid }} .sccc-di__button {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 2.65rem;
      padding: 0.8rem 1.1rem;
      border-radius: 999px;
      text-decoration: none;
      font-family: var(--font-sans, inherit);
      font-size: 0.9rem;
      font-weight: 600;
      letter-spacing: 0;
      line-height: 1.1;
      color: var(--di-button-text-light);
      border: 1px solid transparent;
      background:
        linear-gradient(
          180deg,
          var(--di-button-fill-light-top),
          var(--di-button-fill-light-bottom)
        ) padding-box,
        linear-gradient(
          90deg,
          color-mix(in srgb, var(--di-primary) 94%, white),
          color-mix(in srgb, var(--di-secondary) 90%, white)
        ) border-box;
      box-shadow: var(--di-button-shadow-light);
      backdrop-filter: blur(16px) saturate(155%);
      -webkit-backdrop-filter: blur(16px) saturate(155%);
      text-shadow: none;
      transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        background 0.2s ease,
        color 0.2s ease,
        filter 0.2s ease;
    }

    #{{ $uid }} .sccc-di__button::before {
      content: "";
      position: absolute;
      inset: 1px;
      border-radius: inherit;
      pointer-events: none;
      background: linear-gradient(
        180deg,
        rgba(255, 255, 255, 0.14),
        rgba(255, 255, 255, 0)
      );
      opacity: 0.7;
    }

    html.dark #{{ $uid }} .sccc-di__button,
    [data-theme="dark"] #{{ $uid }} .sccc-di__button {
      color: var(--di-button-text-dark);
      background:
        linear-gradient(
          180deg,
          var(--di-button-fill-dark-top),
          var(--di-button-fill-dark-bottom)
        ) padding-box,
        linear-gradient(
          90deg,
          color-mix(in srgb, var(--di-primary) 94%, white),
          color-mix(in srgb, var(--di-secondary) 90%, white)
        ) border-box;
      box-shadow: var(--di-button-shadow-dark);
    }

    #{{ $uid }} .sccc-di__button:hover {
      transform: translateY(-1px);
      box-shadow:
        0 14px 28px color-mix(in srgb, var(--di-primary) 14%, transparent),
        0 8px 18px color-mix(in srgb, var(--di-secondary) 12%, transparent);
      filter: brightness(1.03);
    }

    html.dark #{{ $uid }} .sccc-di__button:hover,
    [data-theme="dark"] #{{ $uid }} .sccc-di__button:hover {
      box-shadow:
        0 14px 28px rgba(0, 0, 0, 0.34),
        0 0 20px color-mix(in srgb, var(--di-primary) 12%, transparent);
    }

    #{{ $uid }} .sccc-di__button:focus-visible {
      outline: 2px solid color-mix(in srgb, var(--di-primary) 58%, white);
      outline-offset: 2px;
    }
  </style>
</div>