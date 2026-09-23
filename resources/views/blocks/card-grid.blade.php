{{--
  File: resources/views/blocks/card-grid.blade.php
  Full path: resources/views/blocks/card-grid.blade.php

  Why this file exists:
  - Renders the Card Grid block output for all supported card layouts.
  - Keeps block styling scoped to the block instance so the styles do not leak.

  Change note:
  - Applies the per-card icon size and icon color settings to all card layouts.
  - Keeps larger icon circles compact so the glyph takes visual priority.
  - Adds container-safe grid CSS so the block keeps its intended layout when
    rendered as a nested InnerBlock inside the custom Container block.
  - Fixes formatted card text so existing <br> tags from ACF render as line
    breaks instead of being printed as visible text.
--}}
@php
  $uid = 'sccc-card-grid-' . uniqid();

  /**
   * Grid gap values.
   *
   * Why this exists:
   * - The original block used Tailwind gap utilities generated from PHP.
   * - The scoped CSS below now owns the grid layout so the card grid behaves
   *   consistently inside and outside nested Gutenberg/ACF containers.
   */
  $gapValue = match($gap ?? 'md') {
    'sm' => '1rem',
    'lg' => '2rem',
    default => '1.5rem',
  };

  $colsD = max(1, min(4, (int) ($columns_d ?? 3)));
  $colsT = max(1, min(3, (int) ($columns_t ?? 2)));
  $colsM = max(1, min(2, (int) ($columns_m ?? 1)));

  $cardLayout = $card_layout ?? 'default';
  $cardStyle = $card_style ?? 'standard';
  $equal = !empty($equal_height);

  $ha = $header_align ?? '';
  $headRowClass = match($ha) {
    'center' => 'justify-center text-center',
    'right'  => 'justify-end text-right',
    'left'   => 'justify-start text-left',
    default  => 'justify-start text-left',
  };

  $headInnerAlign = match($ha) {
    'center' => 'items-center',
    'right'  => 'items-end',
    'left'   => 'items-start',
    default  => 'items-start',
  };

  $hs = $headline_highlight ?? 'club-blue';

  /**
   * Format card body text safely for frontend display.
   *
   * Why this exists:
   * - ACF Text Area fields may already return HTML line breaks when the field's
   *   New Lines setting is set to automatically add <br> tags.
   * - The old output escaped the whole string first, which caused existing
   *   <br /> markup to print visibly on the frontend.
   * - This helper decodes existing entities, applies WordPress paragraph/line
   *   break formatting, then sanitizes the final HTML using the normal allowed
   *   post-content tag list before Blade prints it unescaped.
   */
  $formatCardText = static function ($value): string {
    $value = trim((string) ($value ?? ''));

    if ($value === '') {
      return '';
    }

    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return wp_kses_post(wpautop($value));
  };
@endphp

<div id="{{ $uid }}" class="{{ $block->classes }} sccc-cg-layout--{{ $cardLayout }}">
  <div class="sccc-cg__wrap">
    @if (!empty($eyebrow) || !empty($headline) || !empty($description))
      <div class="sccc-cg__head-row {{ $headRowClass }}">
        <div class="sccc-cg__head-inner {{ $headInnerAlign }}">
          @if (!empty($eyebrow))
            <p class="sccc-cg__eyebrow">
              @if ($cardLayout === 'decorative_info')
                <span class="material-symbols-outlined sccc-cg__eyebrow-icon" aria-hidden="true">event_available</span>
              @endif
              <span>{{ $eyebrow }}</span>
            </p>
          @endif

          @if (!empty($headline))
            <h2 class="sccc-cg__headline">{!! $headline_rendered !!}</h2>
          @endif

          @if (!empty($description))
            <p class="sccc-cg__desc">{{ $description }}</p>
          @endif
        </div>
      </div>
    @endif

    <div class="sccc-cg__grid">
      @foreach(($cards ?? []) as $card)
        @php
          $hasImg = !empty($card['image_url']);
          $hasBadge = !empty($card['badge']);
          $tone = $card['badge_tone'] ?? 'primary';

          $href = $card['link']['url'] ?? '';
          $label = $card['link']['title'] ?? 'Learn more';
          $target = $card['link']['target'] ?? '';
          $isMeta = ($card['variant'] ?? 'standard') === 'meta';
          $cardIcon = $card['icon'] ?? '';
          $iconSize = $card['icon_size'] ?? 'default';
          $iconColor = $card['icon_color'] ?? 'card';
        @endphp

        @if ($cardLayout === 'icon_feature')
          <article class="sccc-card sccc-card--icon-feature sccc-card--tone-{{ $tone }} sccc-card--icon-size-{{ $iconSize }} sccc-card--icon-color-{{ $iconColor }} {{ $equal ? 'sccc-card--equal' : '' }}">
            <div class="sccc-card__feature-body">
              @if (!empty($cardIcon))
                <span class="sccc-card__feature-icon material-symbols-outlined" aria-hidden="true">{{ $cardIcon }}</span>
              @endif

              @if ($hasBadge)
                <p class="sccc-card__feature-label">{{ $card['badge'] }}</p>
              @endif

              @if (!empty($card['title']))
                <h3 class="sccc-card__feature-title">{{ $card['title'] }}</h3>
              @endif

              @if (!empty($card['text']))
                <div class="sccc-card__feature-text">{!! $formatCardText($card['text']) !!}</div>
              @endif

              @if (!empty($href))
                <a class="sccc-card__link" href="{{ esc_url($href) }}" @if($target) target="{{ esc_attr($target) }}" rel="noopener" @endif>
                  <span>{{ $label }}</span>
                  <span class="material-symbols-outlined sccc-card__arrow" aria-hidden="true">arrow_forward</span>
                </a>
              @endif
            </div>
          </article>
        @elseif ($cardLayout === 'decorative_info')
          <article class="sccc-card sccc-card--decorative-info sccc-card--tone-{{ $tone }} sccc-card--icon-size-{{ $iconSize }} sccc-card--icon-color-{{ $iconColor }} {{ $equal ? 'sccc-card--equal' : '' }}">
            <span class="sccc-card__decorative-corner" aria-hidden="true"></span>

            <div class="sccc-card__info-body">
              @if (!empty($cardIcon))
                <span class="sccc-card__info-icon material-symbols-outlined" aria-hidden="true">{{ $cardIcon }}</span>
              @endif

              @if (!empty($card['title']))
                <h3 class="sccc-card__info-title">{{ $card['title'] }}</h3>
              @endif

              @if (!empty($card['text']))
                <div class="sccc-card__info-text">{!! $formatCardText($card['text']) !!}</div>
              @endif

              @if (!empty($href))
                <a class="sccc-card__link" href="{{ esc_url($href) }}" @if($target) target="{{ esc_attr($target) }}" rel="noopener" @endif>
                  <span>{{ $label }}</span>
                  <span class="material-symbols-outlined sccc-card__arrow" aria-hidden="true">arrow_forward</span>
                </a>
              @endif
            </div>
          </article>
        @else
          <article class="sccc-card {{ $equal ? 'sccc-card--equal' : '' }} sccc-card--{{ $cardStyle }} sccc-card--tone-{{ $tone }} sccc-card--icon-size-{{ $iconSize }} sccc-card--icon-color-{{ $iconColor }}">
            @if ($hasImg)
              <div class="sccc-card__media {{ $hasBadge ? 'has-chip' : '' }}" style="background-image:url('{{ esc_url($card['image_url']) }}')">
                <span class="sr-only">{{ $card['image_alt'] ?? '' }}</span>

                @if ($hasBadge)
                  {{-- Solid triangular cutout (no transparency) --}}
                  <span class="sccc-card__cutout" aria-hidden="true"></span>

                  {{-- Badge inside the cutout --}}
                  <span class="sccc-card__chip-corner" aria-hidden="true">
                    <span class="sccc-card__badge sccc-card__badge--{{ $tone }}">{{ $card['badge'] }}</span>
                  </span>
                @endif
              </div>
            @elseif ($hasBadge)
              <div class="sccc-card__badge-row">
                <span class="sccc-card__badge sccc-card__badge--{{ $tone }}">{{ $card['badge'] }}</span>
              </div>
            @endif

            <div class="sccc-card__body">
              @if ($isMeta && (!empty($card['icon']) || !empty($card['meta_label']) || !empty($card['meta_value'])))
                <div class="sccc-card__meta">
                  @if (!empty($card['icon']))
                    <span class="material-symbols-outlined sccc-card__icon" aria-hidden="true">{{ $card['icon'] }}</span>
                  @endif

                  @if (!empty($card['meta_label']) || !empty($card['meta_value']))
                    <span class="sccc-card__meta-text">
                      @if (!empty($card['meta_label']))
                        <span class="sccc-card__meta-label">{{ $card['meta_label'] }}</span>
                      @endif
                      @if (!empty($card['meta_value']))
                        <span class="sccc-card__meta-value">{{ $card['meta_value'] }}</span>
                      @endif
                    </span>
                  @endif
                </div>
              @endif

              @if (!empty($card['title']))
                <h3 class="sccc-card__title">{{ $card['title'] }}</h3>
              @endif

              @if (!empty($card['text']))
                <div class="sccc-card__text">{!! $formatCardText($card['text']) !!}</div>
              @endif

              @if (!empty($href))
                <a class="sccc-card__link" href="{{ esc_url($href) }}" @if($target) target="{{ esc_attr($target) }}" rel="noopener" @endif>
                  <span>{{ $label }}</span>
                  <span class="material-symbols-outlined sccc-card__arrow" aria-hidden="true">arrow_forward</span>
                </a>
              @endif
            </div>
          </article>
        @endif
      @endforeach
    </div>
  </div>

  <style>
    /*
     * Instance scope and container-safe layout reset.
     *
     * Why this exists:
     * - When this block is placed directly on the page, WordPress/theme align
     *   classes can still control wide/full behavior.
     * - When this block is nested inside the custom Container block, the grid
     *   should respect the container width instead of shrinking, inheriting odd
     *   layout constraints, or relying on Tailwind utility generation.
     */
    #{{ $uid }}{
      display:block;
      box-sizing:border-box;
      min-width:0;
      justify-self:stretch;
      align-self:stretch;

      --sccc-gradient-club-blue: linear-gradient(90deg, #135bec 0%, #1f8fff 52%, #71d7ff 100%);
      --sccc-gradient-signal-red: linear-gradient(90deg, #b31324 0%, #ff1744 50%, #ff7a59 100%);
      --sccc-gradient-space-city: linear-gradient(90deg, #135bec 0%, #43beff 42%, #ae71ff 72%, #ff1744 100%);
      --sccc-color-club-blue: #135bec;
      --sccc-color-signal-red: #e53935;
      --sccc-color-deep-purple: #ae71ff;
    }

    #{{ $uid }}:not(.alignwide):not(.alignfull){
      width:100%;
      max-width:100%;
    }

    .sccc-container #{{ $uid }},
    .sccc-container #{{ $uid }}.alignwide,
    .sccc-container #{{ $uid }}.alignfull,
    .wp-block-container #{{ $uid }},
    .wp-block-container #{{ $uid }}.alignwide,
    .wp-block-container #{{ $uid }}.alignfull{
      width:100%;
      max-width:100%;
      margin-left:0;
      margin-right:0;
    }

    #{{ $uid }},
    #{{ $uid }} *,
    #{{ $uid }} *::before,
    #{{ $uid }} *::after{
      box-sizing:border-box;
    }

    #{{ $uid }} .sccc-cg__wrap{
      width:100%;
      max-width:100%;
      min-width:0;
    }

    #{{ $uid }} .sccc-cg__grid{
      width:100%;
      max-width:100%;
      min-width:0;
      display:grid;
      grid-template-columns:repeat({{ $colsM }}, minmax(0, 1fr));
      gap:{{ $gapValue }};
    }

    @media (min-width: 768px){
      #{{ $uid }} .sccc-cg__grid{
        grid-template-columns:repeat({{ $colsT }}, minmax(0, 1fr));
      }
    }

    @media (min-width: 1024px){
      #{{ $uid }} .sccc-cg__grid{
        grid-template-columns:repeat({{ $colsD }}, minmax(0, 1fr));
      }
    }

    /*
     * Shared headline highlight options.
     * These names match the ACF editor labels in app/Blocks/CardGrid.php.
     * The custom property values live on the block root above so the same
     * instance-level scope also controls grid behavior.
     */

    /* Header */
    #{{ $uid }} .sccc-cg__head-row{ width:100%; display:flex; margin:0 0 1.5rem; }
    #{{ $uid }} .sccc-cg__head-inner{ max-width:75ch; width:100%; display:flex; flex-direction:column; gap:.35rem; }

    #{{ $uid }} .sccc-cg__eyebrow{
      font-family: var(--font-headline, inherit);
      font-size:.75rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase;
      color: var(--color-primary-500, var(--primary));
      margin:0;
    }

    #{{ $uid }}.sccc-cg-layout--decorative_info .sccc-cg__eyebrow{
      display:inline-flex;
      align-items:center;
      gap:.65rem;
      width:100%;
      padding-bottom:.9rem;
      border-bottom:1px solid color-mix(in srgb, var(--color-primary-500, var(--primary)) 24%, transparent);
      color: var(--color-text, var(--text, currentColor));
      font-size:1.45rem;
      letter-spacing:0;
      text-transform:none;
    }

    #{{ $uid }} .sccc-cg__eyebrow-icon{
      color: var(--color-primary-500, var(--primary));
      font-size:1.25rem;
      line-height:1;
      font-variation-settings:'FILL' 0,'wght' 650,'GRAD' 0,'opsz' 24;
    }

    #{{ $uid }} .sccc-cg__headline{
      font-family: var(--font-headline, inherit);
      font-weight:800; line-height:1.1;
      font-size: clamp(1.6rem, 3.2vw, 2.6rem);
      margin:0;
      color: var(--color-text, var(--text, currentColor));
    }

    #{{ $uid }} .sccc-cg__desc{
      margin:0;
      line-height:1.65;
      color: var(--color-muted, var(--muted, rgba(255,255,255,.72)));
    }

    /* Highlight [[...]] */
    #{{ $uid }} [data-sccc-highlight],
    #{{ $uid }} .sccc-text-gradient{
      background-image: var(--sccc-gradient-club-blue);
      -webkit-background-clip:text;
      background-clip:text;
      color:transparent;
      -webkit-text-fill-color:transparent;
      white-space:normal;
    }

    #{{ $uid }} [data-sccc-highlight] strong,
    #{{ $uid }} .sccc-text-gradient strong{
      font-weight:900;
    }

    #{{ $uid }} .sccc-text-gradient--club-blue{
      background-image: var(--sccc-gradient-club-blue);
    }

    #{{ $uid }} .sccc-text-gradient--signal-red{
      background-image: var(--sccc-gradient-signal-red);
    }

    #{{ $uid }} .sccc-text-gradient--space-city{
      background-image: var(--sccc-gradient-space-city);
    }

    #{{ $uid }} .sccc-text-gradient--club-blue-solid,
    #{{ $uid }} .sccc-text-gradient--signal-red-solid,
    #{{ $uid }} .sccc-text-gradient--deep-purple-solid{
      background:none;
      -webkit-background-clip:initial;
      background-clip:initial;
      -webkit-text-fill-color:currentColor;
    }

    #{{ $uid }} .sccc-text-gradient--club-blue-solid{
      color: var(--sccc-color-club-blue);
    }

    #{{ $uid }} .sccc-text-gradient--signal-red-solid{
      color: var(--sccc-color-signal-red);
    }

    #{{ $uid }} .sccc-text-gradient--deep-purple-solid{
      color: var(--sccc-color-deep-purple);
    }

    /* Card base */
    #{{ $uid }} .sccc-card{
      border-radius: 1rem;
      overflow:hidden;
      border:1px solid color-mix(in srgb, var(--color-line, var(--line, #2a3448)) 85%, transparent);
      background: color-mix(in srgb, var(--color-surface, var(--surface, #111722)) 92%, transparent);
      display:flex;
      flex-direction:column;
      position:relative;

      /*
       * Shared icon sizing/color tokens.
       * Default values preserve the original card feel while allowing individual
       * cards to override the icon size and color safely.
       */
      --sccc-card-meta-icon-font: 1.15rem;
      --sccc-card-feature-icon-box: 2.65rem;
      --sccc-card-feature-icon-font: 1.55rem;
      --sccc-card-info-icon-box: 2.45rem;
      --sccc-card-info-icon-font: 1.4rem;
      --sccc-card-icon-color: var(--chip, var(--color-primary-500, var(--primary)));
    }

    #{{ $uid }} .sccc-card--equal{ height:100%; }

    /*
     * Icon color options.
     * These are intentionally mapped to theme-safe values instead of allowing
     * arbitrary colors from the editor.
     */
    #{{ $uid }} .sccc-card--icon-color-card{
      --sccc-card-icon-color: var(--chip, var(--color-primary-500, var(--primary)));
    }

    #{{ $uid }} .sccc-card--icon-color-primary{
      --sccc-card-icon-color: var(--color-primary-500, var(--primary));
    }

    #{{ $uid }} .sccc-card--icon-color-signal{
      --sccc-card-icon-color: var(--color-signal-red, #e53935);
    }

    #{{ $uid }} .sccc-card--icon-color-accent{
      --sccc-card-icon-color: var(--color-accent-500, var(--secondary));
    }

    #{{ $uid }} .sccc-card--icon-color-success{
      --sccc-card-icon-color: #00FF6A;
    }

    #{{ $uid }} .sccc-card--icon-color-warning{
      --sccc-card-icon-color: #FFB020;
    }

    #{{ $uid }} .sccc-card--icon-color-neutral{
      --sccc-card-icon-color: color-mix(in srgb, var(--color-text, var(--text, currentColor)) 55%, transparent);
    }

    #{{ $uid }} .sccc-card--icon-color-text{
      --sccc-card-icon-color: var(--color-text, var(--text, currentColor));
    }

    #{{ $uid }} .sccc-card--icon-color-muted{
      --sccc-card-icon-color: var(--color-muted, var(--muted, rgba(255,255,255,.72)));
    }

    /*
     * Icon size options.
     * The circle is intentionally compact while the glyph does the visual work.
     * Mobile values are slightly smaller and step up at the md breakpoint.
     */
    #{{ $uid }} .sccc-card--icon-size-medium{
      --sccc-card-meta-icon-font: 1.3rem;
      --sccc-card-feature-icon-box: 2.8rem;
      --sccc-card-feature-icon-font: 1.75rem;
      --sccc-card-info-icon-box: 2.6rem;
      --sccc-card-info-icon-font: 1.6rem;
    }

    #{{ $uid }} .sccc-card--icon-size-large{
      --sccc-card-meta-icon-font: 1.5rem;
      --sccc-card-feature-icon-box: 3rem;
      --sccc-card-feature-icon-font: 2rem;
      --sccc-card-info-icon-box: 2.75rem;
      --sccc-card-info-icon-font: 1.82rem;
    }

    #{{ $uid }} .sccc-card--icon-size-x-large{
      --sccc-card-meta-icon-font: 1.72rem;
      --sccc-card-feature-icon-box: 3.25rem;
      --sccc-card-feature-icon-font: 2.28rem;
      --sccc-card-info-icon-box: 2.95rem;
      --sccc-card-info-icon-font: 2.06rem;
    }

    #{{ $uid }} .sccc-card--icon-size-xx-large{
      --sccc-card-meta-icon-font: 1.92rem;
      --sccc-card-feature-icon-box: 3.5rem;
      --sccc-card-feature-icon-font: 2.58rem;
      --sccc-card-info-icon-box: 3.2rem;
      --sccc-card-info-icon-font: 2.32rem;
    }

    @media (min-width: 768px){
      #{{ $uid }} .sccc-card--icon-size-medium{
        --sccc-card-meta-icon-font: 1.36rem;
        --sccc-card-feature-icon-box: 2.9rem;
        --sccc-card-feature-icon-font: 1.88rem;
        --sccc-card-info-icon-box: 2.7rem;
        --sccc-card-info-icon-font: 1.72rem;
      }

      #{{ $uid }} .sccc-card--icon-size-large{
        --sccc-card-meta-icon-font: 1.58rem;
        --sccc-card-feature-icon-box: 3.15rem;
        --sccc-card-feature-icon-font: 2.12rem;
        --sccc-card-info-icon-box: 2.9rem;
        --sccc-card-info-icon-font: 1.94rem;
      }

      #{{ $uid }} .sccc-card--icon-size-x-large{
        --sccc-card-meta-icon-font: 1.82rem;
        --sccc-card-feature-icon-box: 3.4rem;
        --sccc-card-feature-icon-font: 2.42rem;
        --sccc-card-info-icon-box: 3.1rem;
        --sccc-card-info-icon-font: 2.18rem;
      }

      #{{ $uid }} .sccc-card--icon-size-xx-large{
        --sccc-card-meta-icon-font: 2.05rem;
        --sccc-card-feature-icon-box: 3.65rem;
        --sccc-card-feature-icon-font: 2.72rem;
        --sccc-card-info-icon-box: 3.35rem;
        --sccc-card-info-icon-font: 2.46rem;
      }
    }

    /* Image */
    #{{ $uid }} .sccc-card__media{
      position:relative;
      aspect-ratio:16/10;
      background-size:cover;
      background-position:center;
      background-repeat:no-repeat;

      /* Adjust these two if you want bigger/smaller triangle */
      --chip-cut: 11.25rem;
      --chip-pad: 1.05rem;
    }

    /* Simple triangular cutout overlay (solid, inherits body/bg color) */
    #{{ $uid }} .sccc-card__cutout{
      position:absolute;
      top:0; right:0;
      width: var(--chip-cut);
      height: var(--chip-cut);
      background: var(--bg, var(--color-bg, Canvas));
      z-index: 1;
      pointer-events:none;

      /* triangle pointing inward */
      clip-path: polygon(100% 0, 100% 100%, 0 0);
    }

    /* Badge inside the cutout */
    #{{ $uid }} .sccc-card__chip-corner{
      position:absolute;
      top: var(--chip-pad);
      right: var(--chip-pad);
      z-index: 2;
      pointer-events:none;
      display:inline-flex;
      align-items:flex-start;
      justify-content:flex-end;
    }

    /* Badge: no wrap, no truncation */
    #{{ $uid }} .sccc-card__badge{
      padding:.30rem .78rem;
      border-radius:999px;
      font-size:.7rem;
      font-weight:800;
      letter-spacing:.08em;
      text-transform:uppercase;
      display:inline-flex;
      align-items:center;
      justify-content:center;

      white-space:nowrap;
      overflow:visible;
      text-overflow:clip;
      max-width:none;

      --chip: var(--color-primary-500, var(--primary));
      color: var(--chip);
      background: color-mix(in srgb, var(--chip) 18%, transparent);
      border: 1px solid color-mix(in srgb, var(--chip) 30%, transparent);
      backdrop-filter: blur(6px);
    }

    #{{ $uid }} .sccc-card__badge--primary,
    #{{ $uid }} .sccc-card--tone-primary { --chip: var(--color-primary-500, var(--primary)); }

    #{{ $uid }} .sccc-card__badge--signal,
    #{{ $uid }} .sccc-card--tone-signal { --chip: var(--color-signal-red, #e53935); }

    #{{ $uid }} .sccc-card__badge--accent,
    #{{ $uid }} .sccc-card--tone-accent { --chip: var(--color-accent-500, var(--secondary)); }

    #{{ $uid }} .sccc-card__badge--success,
    #{{ $uid }} .sccc-card--tone-success { --chip: #00FF6A; }

    #{{ $uid }} .sccc-card__badge--warning,
    #{{ $uid }} .sccc-card--tone-warning { --chip: #FFB020; }

    #{{ $uid }} .sccc-card__badge--neutral,
    #{{ $uid }} .sccc-card--tone-neutral { --chip: color-mix(in srgb, var(--color-text, var(--text)) 55%, transparent); }

    /* Body */
    #{{ $uid }} .sccc-card__badge-row{ padding:.9rem .9rem 0; }

    #{{ $uid }} .sccc-card__body{
      padding: 1rem 1rem 1.05rem;
      display:flex;
      flex-direction:column;
      gap:.55rem;
    }

    #{{ $uid }} .sccc-card__meta{
      display:inline-flex;
      align-items:center;
      gap:.5rem;
      color: var(--color-muted, var(--muted, rgba(255,255,255,.72)));
      font-size:.875rem;
    }

    #{{ $uid }} .sccc-card__icon{
      font-size:var(--sccc-card-meta-icon-font);
      line-height:1;
      color: var(--sccc-card-icon-color);
      font-variation-settings:'FILL' 0,'wght' 500,'GRAD' 0,'opsz' 24;
    }

    #{{ $uid }} .sccc-card__meta-label{ opacity:.9; margin-right:.25rem; }
    #{{ $uid }} .sccc-card__meta-value{ font-weight:700; }

    #{{ $uid }} .sccc-card__title{
      font-family: var(--font-headline, inherit);
      font-weight:800;
      line-height:1.15;
      font-size:1.15rem;
      margin:0;
      color: var(--color-text, var(--text, currentColor));
    }

    #{{ $uid }} .sccc-card__text{
      margin:0;
      line-height:1.6;
      color: var(--color-muted, var(--muted, rgba(255,255,255,.72)));
      font-size:.95rem;
    }

    /*
     * Formatted text spacing.
     * The Blade markup now allows safe paragraph and <br> output, so these rules
     * keep generated <p> tags visually identical to the previous single-text
     * version while still allowing intentional line breaks.
     */
    #{{ $uid }} .sccc-card__text p,
    #{{ $uid }} .sccc-card__feature-text p,
    #{{ $uid }} .sccc-card__info-text p{
      margin:0;
    }

    #{{ $uid }} .sccc-card__text p + p,
    #{{ $uid }} .sccc-card__feature-text p + p,
    #{{ $uid }} .sccc-card__info-text p + p{
      margin-top:.75rem;
    }

    #{{ $uid }} .sccc-card__link{
      margin-top:.25rem;
      display:inline-flex;
      align-items:center;
      gap:.35rem;
      text-decoration:none;
      font-weight:800;
      color: var(--color-primary-500, var(--primary));
    }

    #{{ $uid }} .sccc-card__arrow{ font-size:1.1rem; line-height:1; }

    /*
     * Icon Feature Cards
     * This mode matches the larger icon-led layout without changing the default mode.
     */
    #{{ $uid }} .sccc-card--icon-feature{
      min-height:11.25rem;
      border-radius:2.75rem;
      background:
        linear-gradient(180deg, color-mix(in srgb, var(--color-surface, var(--surface, #111722)) 94%, transparent), color-mix(in srgb, var(--color-surface, var(--surface, #111722)) 88%, transparent));
      border-color: color-mix(in srgb, var(--chip, var(--color-primary-500, var(--primary))) 35%, var(--color-line, var(--line, #2a3448)));
      box-shadow: inset 0 1px 0 rgba(255,255,255,.035);
    }

    #{{ $uid }} .sccc-card__feature-body{
      min-height:100%;
      padding:1.55rem 1.55rem 1.65rem;
      display:flex;
      flex-direction:column;
      align-items:flex-start;
      justify-content:flex-start;
      gap:.65rem;
    }

    #{{ $uid }} .sccc-card__feature-icon{
      width:var(--sccc-card-feature-icon-box);
      height:var(--sccc-card-feature-icon-box);
      display:inline-flex;
      align-items:center;
      justify-content:center;
      border-radius:999px;
      color: var(--sccc-card-icon-color);
      background: color-mix(in srgb, var(--sccc-card-icon-color) 16%, transparent);
      font-size:var(--sccc-card-feature-icon-font);
      line-height:1;
      font-variation-settings:'FILL' 1,'wght' 650,'GRAD' 0,'opsz' 24;
    }

    #{{ $uid }} .sccc-card__feature-label{
      margin:.65rem 0 0;
      font-family: var(--font-headline, inherit);
      font-size:.78rem;
      line-height:1.1;
      font-weight:800;
      letter-spacing:.08em;
      text-transform:uppercase;
      color: color-mix(in srgb, var(--color-muted, var(--muted, rgba(255,255,255,.72))) 82%, var(--color-text, var(--text, currentColor)));
    }

    #{{ $uid }} .sccc-card__feature-title{
      margin:0;
      font-family: var(--font-headline, inherit);
      font-size:clamp(1.35rem, 2vw, 1.55rem);
      line-height:1.05;
      font-weight:900;
      color: var(--color-text, var(--text, currentColor));
    }

    #{{ $uid }} .sccc-card__feature-text{
      margin:.2rem 0 0;
      max-width:38rem;
      font-size:.95rem;
      line-height:1.6;
      color: var(--color-muted, var(--muted, rgba(255,255,255,.72)));
    }

    /*
     * Decorative Info Cards
     * This mode matches the compact info-card layout with a subtle corner accent.
     */
    #{{ $uid }} .sccc-card--decorative-info{
      min-height:13rem;
      border-radius:2.35rem;
      background:
        radial-gradient(18rem 18rem at calc(100% + 4.5rem) -4.5rem, color-mix(in srgb, var(--chip, var(--color-primary-500, var(--primary))) 16%, transparent) 0%, transparent 58%),
        color-mix(in srgb, var(--color-surface, var(--surface, #111722)) 92%, transparent);
      border-color: color-mix(in srgb, var(--chip, var(--color-primary-500, var(--primary))) 32%, var(--color-line, var(--line, #2a3448)));
      box-shadow: inset 0 1px 0 rgba(255,255,255,.035);
    }

    #{{ $uid }} .sccc-card__decorative-corner{
      position:absolute;
      top:-5.25rem;
      right:-5.25rem;
      width:12.75rem;
      height:12.75rem;
      border-radius:999px;
      background: color-mix(in srgb, var(--chip, var(--color-primary-500, var(--primary))) 13%, transparent);
      pointer-events:none;
    }

    #{{ $uid }} .sccc-card__info-body{
      position:relative;
      z-index:1;
      min-height:100%;
      padding:1.55rem 1.55rem 1.6rem;
      display:flex;
      flex-direction:column;
      align-items:flex-start;
      justify-content:flex-start;
      gap:.85rem;
    }

    #{{ $uid }} .sccc-card__info-icon{
      width:var(--sccc-card-info-icon-box);
      height:var(--sccc-card-info-icon-box);
      display:inline-flex;
      align-items:center;
      justify-content:center;
      border-radius:999px;
      color: var(--sccc-card-icon-color);
      background: color-mix(in srgb, var(--sccc-card-icon-color) 18%, transparent);
      font-size:var(--sccc-card-info-icon-font);
      line-height:1;
      font-variation-settings:'FILL' 1,'wght' 650,'GRAD' 0,'opsz' 24;
    }

    #{{ $uid }} .sccc-card__info-title{
      margin:.25rem 0 0;
      font-family: var(--font-headline, inherit);
      font-size:1.18rem;
      line-height:1.15;
      font-weight:900;
      color: var(--color-text, var(--text, currentColor));
    }

    #{{ $uid }} .sccc-card__info-text{
      margin:0;
      max-width:38rem;
      font-size:.95rem;
      line-height:1.55;
      color: color-mix(in srgb, var(--color-muted, var(--muted, rgba(255,255,255,.72))) 88%, var(--color-text, var(--text, currentColor)));
    }

    /*
     * Light theme protection.
     * Keeps the two new layouts readable when the site is in light mode.
     */
    html[data-theme="light"] #{{ $uid }} .sccc-card--icon-feature,
    html[data-theme="light"] #{{ $uid }} .sccc-card--decorative-info{
      background:
        radial-gradient(18rem 18rem at calc(100% + 4.5rem) -4.5rem, color-mix(in srgb, var(--chip, var(--color-primary-500, var(--primary))) 10%, transparent) 0%, transparent 58%),
        color-mix(in srgb, var(--color-surface, var(--surface, #fff)) 98%, white);
      border-color: color-mix(in srgb, var(--chip, var(--color-primary-500, var(--primary))) 34%, var(--color-line, rgba(0,0,0,.12)));
    }

    @media (max-width: 767px){
      #{{ $uid }} .sccc-card--icon-feature,
      #{{ $uid }} .sccc-card--decorative-info{
        border-radius:1.75rem;
      }

      #{{ $uid }} .sccc-card__feature-body,
      #{{ $uid }} .sccc-card__info-body{
        padding:1.25rem;
      }

      #{{ $uid }}.sccc-cg-layout--decorative_info .sccc-cg__eyebrow{
        font-size:1.2rem;
      }
    }
  </style>
</div>