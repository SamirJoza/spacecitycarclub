{{--
  File: resources/views/blocks/latest-posts-grid.blade.php

  Latest Posts Grid block view.

  Why this file exists:
  - Renders the latest 3 posts as a 3-column card grid.
  - Keeps all styling scoped to this block only.
  - Uses the project's existing dark/light theme variables.
  - Works safely inside a standard content container.

  Requested adjustments implemented here:
  - Featured image stretches edge-to-edge within the card.
  - All cards reserve top eyebrow space, even if empty.
  - Entire card is clickable.
  - Neon top border only appears on hover / focus.
  - Button styling remains visually unchanged.
--}}

@php
  /**
   * Normalize block data so the template is resilient in both
   * editor and frontend rendering contexts.
   */
  $blockData = $block ?? null;

  $blockAnchor = is_object($blockData)
    ? ($blockData->anchor ?? '')
    : (is_array($blockData) ? ($blockData['anchor'] ?? '') : '');

  $blockClassName = is_object($blockData)
    ? ($blockData->className ?? '')
    : (is_array($blockData) ? ($blockData['className'] ?? '') : '');

  $blockGeneratedId = is_object($blockData)
    ? ($blockData->id ?? '')
    : (is_array($blockData) ? ($blockData['id'] ?? '') : '');

  /**
   * Respect a custom anchor if the editor sets one.
   */
  $blockId = $blockAnchor !== ''
    ? $blockAnchor
    : ($blockGeneratedId !== ''
      ? 'sccc-latest-posts-grid-' . sanitize_title($blockGeneratedId)
      : wp_unique_id('sccc-latest-posts-grid-'));

  /**
   * Preserve Gutenberg custom classes.
   */
  $classes = trim('sccc-latest-posts-grid ' . $blockClassName);
@endphp

@once
  <style>
    /*
      ==========================================================================
      Latest Posts Grid Block Styles
      ==========================================================================
      These styles are fully scoped to this block.

      Design goals:
      - Match the project's glassy / neon visual system.
      - Keep the layout stable inside a container.
      - Avoid affecting other blocks or global components.
    */

    .sccc-latest-posts-grid {
      --sccc-post-card-surface: var(--color-surface, #141414);
      --sccc-post-card-text: var(--color-text, #ffffff);
      --sccc-post-card-muted: var(--color-muted, #a0a7b2);
      --sccc-post-card-line: var(--color-line, rgba(255, 255, 255, 0.12));
      --sccc-post-card-primary: var(--color-primary-500, #43beff);
      --sccc-post-card-accent: var(--color-accent-500, #ae71ff);
      --sccc-post-card-radius: var(--radius-card, 1rem);
      --sccc-post-card-gradient: var(
        --sccc-header-underline-gradient,
        linear-gradient(
          90deg,
          rgba(113, 215, 255, 0) 0%,
          rgba(113, 215, 255, 0.96) 18%,
          rgba(67, 190, 255, 0.98) 48%,
          rgba(174, 113, 255, 0.92) 82%,
          rgba(174, 113, 255, 0) 100%
        )
      );

      width: 100%;
      margin-block: clamp(1rem, 2vw, 1.75rem);
      color: var(--sccc-post-card-text);
    }

    .sccc-latest-posts-grid,
    .sccc-latest-posts-grid * {
      box-sizing: border-box;
    }

    /*
      Remove underlines within this block only.
    */
    .sccc-latest-posts-grid a,
    .sccc-latest-posts-grid a:hover,
    .sccc-latest-posts-grid a:focus,
    .sccc-latest-posts-grid a:focus-visible {
      text-decoration: none !important;
    }

    /*
      Grid:
      - 3 columns on larger screens
      - stacked on mobile
    */
    .sccc-latest-posts-grid__items {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: clamp(1rem, 2vw, 1.5rem);
      align-items: stretch;
      width: 100%;
    }

    /*
      Article wrapper stays semantic.
    */
    .sccc-latest-posts-grid__card {
      min-width: 0;
    }

    /*
      Entire card is clickable by making the main anchor the card shell.
      This avoids JS and keeps interaction simple and reliable.
    */
    .sccc-latest-posts-grid__card-link {
      position: relative;
      display: flex;
      flex-direction: column;
      height: 100%;
      min-width: 0;
      overflow: hidden;
      border: 1px solid var(--sccc-post-card-line);
      border-radius: var(--sccc-post-card-radius);
      background:
        radial-gradient(
          520px 260px at 10% -10%,
          color-mix(in oklab, var(--sccc-post-card-primary) 14%, transparent) 0%,
          transparent 60%
        ),
        radial-gradient(
          420px 240px at 100% -10%,
          color-mix(in oklab, var(--sccc-post-card-accent) 14%, transparent) 0%,
          transparent 62%
        ),
        var(--sccc-post-card-surface);
      box-shadow: 0 14px 34px rgba(0, 0, 0, 0.22);
      cursor: pointer;
      transition:
        border-color 180ms ease,
        box-shadow 180ms ease,
        transform 180ms ease;
    }

    /*
      Fallback if color-mix is not supported.
    */
    @supports not (background: color-mix(in oklab, white, black)) {
      .sccc-latest-posts-grid__card-link {
        background: var(--sccc-post-card-surface);
      }
    }

    /*
      Neon top border:
      Hidden by default.
      Shown only on hover / keyboard focus, per request.
    */
    .sccc-latest-posts-grid__card-link::before {
      content: "";
      position: absolute;
      inset: 0 0 auto;
      z-index: 1;
      height: 2px;
      background: var(--sccc-post-card-gradient);
      opacity: 0;
      pointer-events: none;
      transition: opacity 180ms ease;
    }

    .sccc-latest-posts-grid__card-link:hover,
    .sccc-latest-posts-grid__card-link:focus-visible {
      transform: translateY(-4px);
      border-color: color-mix(in oklab, var(--sccc-post-card-primary) 46%, var(--sccc-post-card-line));
      box-shadow:
        0 18px 44px rgba(0, 0, 0, 0.28),
        0 0 30px color-mix(in oklab, var(--sccc-post-card-primary) 18%, transparent);
    }

    .sccc-latest-posts-grid__card-link:hover::before,
    .sccc-latest-posts-grid__card-link:focus-visible::before {
      opacity: 0.95;
    }

    .sccc-latest-posts-grid__card-link:focus-visible {
      outline: 2px solid var(--sccc-post-card-primary);
      outline-offset: 3px;
    }

    /*
      Top eyebrow area:
      Always reserved so all cards align, even when no taxonomy term exists.
    */
    .sccc-latest-posts-grid__top {
      display: flex;
      align-items: center;
      min-height: 3.2rem;
      padding: 1rem 1rem 0.75rem;
    }

    .sccc-latest-posts-grid__eyebrow {
      display: inline-flex;
      align-items: center;
      min-height: 1.85rem;
      padding: 0.28rem 0.65rem;
      border: 1px solid color-mix(in oklab, var(--sccc-post-card-accent) 58%, transparent);
      border-radius: 999px;
      background: color-mix(in oklab, var(--sccc-post-card-accent) 14%, transparent);
      color: var(--sccc-post-card-text);
      font-size: 0.72rem;
      font-weight: 800;
      line-height: 1;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      white-space: nowrap;
    }

    /*
      Empty eyebrow placeholder:
      Keeps the reserved top spacing without showing anything visually.
    */
    .sccc-latest-posts-grid__eyebrow--empty {
      visibility: hidden;
    }

    /*
      Full-width media:
      Side-to-side within the card as requested.
    */
    .sccc-latest-posts-grid__media {
      display: block;
      width: 100%;
      overflow: hidden;
      border-top: 1px solid var(--sccc-post-card-line);
      border-bottom: 1px solid var(--sccc-post-card-line);
      background:
        linear-gradient(
          135deg,
          color-mix(in oklab, var(--sccc-post-card-primary) 16%, transparent),
          color-mix(in oklab, var(--sccc-post-card-accent) 16%, transparent)
        );
    }

    .sccc-latest-posts-grid__image {
      display: block;
      width: 100%;
      height: auto;
      aspect-ratio: 16 / 9;
      object-fit: cover;
      transition: transform 220ms ease;
    }

    .sccc-latest-posts-grid__card-link:hover .sccc-latest-posts-grid__image,
    .sccc-latest-posts-grid__card-link:focus-visible .sccc-latest-posts-grid__image {
      transform: scale(1.035);
    }

    /*
      Placeholder when no featured image exists.
      Still spans full width to preserve structure.
    */
    .sccc-latest-posts-grid__placeholder {
      display: grid;
      width: 100%;
      aspect-ratio: 16 / 9;
      place-items: center;
      padding: 1rem;
      color: var(--sccc-post-card-muted);
      font-family: var(--font-display, var(--font-headline, system-ui, sans-serif));
      font-size: 0.9rem;
      font-weight: 800;
      letter-spacing: 0.16em;
      text-align: center;
      text-transform: uppercase;
    }

    /*
      Main content area.
    */
    .sccc-latest-posts-grid__body {
      display: flex;
      flex: 1;
      flex-direction: column;
      padding: 0.95rem 1rem 1rem;
    }

    .sccc-latest-posts-grid__date {
      margin: 0 0 0.55rem;
      color: var(--sccc-post-card-muted);
      font-size: 0.8rem;
      font-weight: 700;
      line-height: 1.35;
    }

    .sccc-latest-posts-grid__title {
      margin: 0;
      color: var(--sccc-post-card-text);
      font-family: var(--font-display, var(--font-headline, system-ui, sans-serif));
      font-size: clamp(1.25rem, 1.6vw, 1.55rem);
      font-weight: 800;
      line-height: 1.12;
      letter-spacing: 0.01em;
    }

    .sccc-latest-posts-grid__excerpt {
      flex: 1;
      margin: 0.75rem 0 1rem;
      color: var(--sccc-post-card-muted);
      font-size: 0.95rem;
      line-height: 1.6;
    }

    /*
      Button styling:
      Kept visually the same, but rendered as a span because the entire card
      is already the link.
    */
    .sccc-latest-posts-grid__link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.45rem;
      width: fit-content;
      min-height: 2.65rem;
      margin-top: auto;
      padding: 0.72rem 1rem;
      border: 1px solid color-mix(in oklab, var(--sccc-post-card-primary) 62%, var(--sccc-post-card-line));
      border-radius: 999px;
      background: color-mix(in oklab, var(--sccc-post-card-primary) 10%, transparent);
      color: var(--sccc-post-card-text);
      font-size: 0.88rem;
      font-weight: 900;
      line-height: 1;
      box-shadow:
        0 0 0 1px color-mix(in oklab, var(--sccc-post-card-primary) 10%, transparent),
        0 10px 24px color-mix(in oklab, var(--sccc-post-card-primary) 12%, transparent);
      transition:
        background-color 180ms ease,
        border-color 180ms ease,
        box-shadow 180ms ease,
        color 180ms ease,
        transform 180ms ease;
    }

    .sccc-latest-posts-grid__link::after {
      content: "→";
      font-size: 1rem;
      line-height: 1;
      transform: translateY(-1px);
      transition: transform 180ms ease;
    }

    .sccc-latest-posts-grid__card-link:hover .sccc-latest-posts-grid__link,
    .sccc-latest-posts-grid__card-link:focus-visible .sccc-latest-posts-grid__link {
      transform: translateY(-1px);
      border-color: color-mix(in oklab, var(--sccc-post-card-primary) 82%, white);
      background: color-mix(in oklab, var(--sccc-post-card-primary) 18%, transparent);
      color: var(--sccc-post-card-text);
      box-shadow:
        0 0 0 1px color-mix(in oklab, var(--sccc-post-card-primary) 18%, transparent),
        0 14px 30px color-mix(in oklab, var(--sccc-post-card-primary) 22%, transparent);
    }

    .sccc-latest-posts-grid__card-link:hover .sccc-latest-posts-grid__link::after,
    .sccc-latest-posts-grid__card-link:focus-visible .sccc-latest-posts-grid__link::after {
      transform: translate(3px, -1px);
    }

    /*
      Light theme refinement for readability.
    */
    html[data-theme="light"] .sccc-latest-posts-grid__link {
      color: var(--color-primary-700, var(--sccc-post-card-text));
    }

    html[data-theme="light"] .sccc-latest-posts-grid__card-link:hover .sccc-latest-posts-grid__link,
    html[data-theme="light"] .sccc-latest-posts-grid__card-link:focus-visible .sccc-latest-posts-grid__link {
      color: var(--color-text, #111827);
    }

    /*
      Empty state when there are no published posts.
    */
    .sccc-latest-posts-grid__empty {
      width: 100%;
      padding: 1rem;
      border: 1px dashed var(--sccc-post-card-line);
      border-radius: var(--sccc-post-card-radius);
      color: var(--sccc-post-card-muted);
      background: color-mix(in oklab, var(--sccc-post-card-surface) 84%, transparent);
      text-align: center;
    }

    .sccc-latest-posts-grid__empty p {
      margin: 0;
    }

    /*
      Mobile stacking.
    */
    @media (max-width: 767px) {
      .sccc-latest-posts-grid__items {
        grid-template-columns: 1fr;
      }
    }

    /*
      Reduced motion support.
    */
    @media (prefers-reduced-motion: reduce) {
      .sccc-latest-posts-grid__card-link,
      .sccc-latest-posts-grid__card-link::before,
      .sccc-latest-posts-grid__image,
      .sccc-latest-posts-grid__link,
      .sccc-latest-posts-grid__link::after {
        transition: none;
      }

      .sccc-latest-posts-grid__card-link:hover,
      .sccc-latest-posts-grid__card-link:focus-visible,
      .sccc-latest-posts-grid__card-link:hover .sccc-latest-posts-grid__link,
      .sccc-latest-posts-grid__card-link:focus-visible .sccc-latest-posts-grid__link {
        transform: none;
      }

      .sccc-latest-posts-grid__card-link:hover .sccc-latest-posts-grid__image,
      .sccc-latest-posts-grid__card-link:focus-visible .sccc-latest-posts-grid__image {
        transform: none;
      }
    }
  </style>
@endonce

<section
  id="{{ $blockId }}"
  class="{{ $classes }}"
  aria-label="{{ __('Latest posts', 'sage') }}"
>
  @if (! empty($posts))
    <div class="sccc-latest-posts-grid__items" role="list">
      @foreach ($posts as $card)
        @php
          $readLabel = ! empty($card['title'])
            ? sprintf(__('Read %s', 'sage'), $card['title'])
            : __('Read article', 'sage');
        @endphp

        <article class="sccc-latest-posts-grid__card" role="listitem">
          <a
            class="sccc-latest-posts-grid__card-link"
            href="{!! esc_url($card['permalink']) !!}"
            aria-label="{!! esc_attr($readLabel) !!}"
          >
            <div class="sccc-latest-posts-grid__top">
              @if (! empty($card['eyebrow']))
                <span class="sccc-latest-posts-grid__eyebrow">
                  {{ $card['eyebrow'] }}
                </span>
              @else
                <span class="sccc-latest-posts-grid__eyebrow sccc-latest-posts-grid__eyebrow--empty" aria-hidden="true">
                  {{ __('No Content Type', 'sage') }}
                </span>
              @endif
            </div>

            <div class="sccc-latest-posts-grid__media" aria-hidden="true">
              @if (! empty($card['image']))
                {!! $card['image'] !!}
              @else
                <div class="sccc-latest-posts-grid__placeholder">
                  <span>{{ __('Space City Car Club', 'sage') }}</span>
                </div>
              @endif
            </div>

            <div class="sccc-latest-posts-grid__body">
              @if (! empty($card['published']))
                <p class="sccc-latest-posts-grid__date">
                  {{ __('Published:', 'sage') }}
                  <time datetime="{{ $card['datetime'] }}">
                    {{ $card['published'] }}
                  </time>
                </p>
              @endif

              @if (! empty($card['title']))
                <h3 class="sccc-latest-posts-grid__title">
                  {{ $card['title'] }}
                </h3>
              @endif

              @if (! empty($card['excerpt']))
                <p class="sccc-latest-posts-grid__excerpt">
                  {{ $card['excerpt'] }}
                </p>
              @endif

              <span class="sccc-latest-posts-grid__link">
                <span>{{ __('Read More', 'sage') }}</span>
              </span>
            </div>
          </a>
        </article>
      @endforeach
    </div>
  @else
    <div class="sccc-latest-posts-grid__empty">
      <p>{{ __('No published posts are available yet.', 'sage') }}</p>
    </div>
  @endif
</section>