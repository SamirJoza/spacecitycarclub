{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/blocks/featured-vendors.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render confirmed vendors (active + OK to feature) for a show as a logo
|   grid or a scrolling rail.
|
| Notes:
| - The rail duplicates the list once for a seamless loop; the duplicate is
|   aria-hidden and pauses on hover / focus / reduced motion.
| - Type pills use each vendor type's color (Vendors → Vendor Types).
| - In the editor an empty state explains why nothing shows yet.
--}}

@php
  $hasVendors = ! empty($vendors);
  $renderItem = static function (array $vendor, bool $decorative) use ($showNames, $showTypes, $linkVendors): string {
      $img = wp_get_attachment_image((int) $vendor['logo'], 'medium', false, [
          'class' => 'sccc-featured-vendors__logo',
          'loading' => 'lazy',
          'alt' => $decorative ? '' : $vendor['name'],
      ]);

      $inner = '<span class="sccc-featured-vendors__logo-wrap">'.$img.'</span>';

      if ($showNames) {
          $inner .= '<span class="sccc-featured-vendors__name">'.esc_html($vendor['name']).'</span>';
      }

      if ($showTypes && $vendor['type'] !== '') {
          $inner .= '<span class="sccc-featured-vendors__type" style="--sccc-type:'.esc_attr($vendor['color']).'">'.esc_html($vendor['type']).'</span>';
      }

      if ($linkVendors && $vendor['url'] !== '') {
          return '<a class="sccc-featured-vendors__item" href="'.esc_url($vendor['url']).'" target="_blank" rel="noopener"'.($decorative ? ' tabindex="-1"' : '').'>'.$inner.'</a>';
      }

      return '<div class="sccc-featured-vendors__item">'.$inner.'</div>';
  };
@endphp

@once
  <style>
    .sccc-featured-vendors { padding-block: clamp(2rem, 5vw, 4rem); }
    .sccc-featured-vendors__inner { padding-inline: 1rem; }
    .sccc-featured-vendors__header { text-align: center; max-width: 44rem; margin: 0 auto 2rem; }
    .sccc-featured-vendors__heading { font-size: var(--text-h4); line-height: var(--text-h4--line-height); margin: 0; }
    .sccc-featured-vendors__show { color: var(--color-primary-500); font-weight: 600; margin-top: .35rem; }
    .sccc-featured-vendors__intro { color: var(--color-muted); margin-top: .5rem; }

    .sccc-featured-vendors__grid {
      display: grid; gap: 1rem; list-style: none; margin: 0; padding: 0;
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    @media (min-width: 768px) {
      .sccc-featured-vendors__grid { grid-template-columns: repeat(var(--sccc-cols, 4), minmax(0, 1fr)); }
    }

    .sccc-featured-vendors__item {
      display: flex; flex-direction: column; align-items: center; gap: .5rem; height: 100%;
      padding: 1.25rem 1rem; border-radius: var(--radius-xl); text-align: center; text-decoration: none;
      background: var(--color-card-bg); border: 1px solid var(--color-card-border); color: var(--color-text);
      transition: transform .15s ease, border-color .15s ease;
    }
    a.sccc-featured-vendors__item:hover { transform: translateY(-2px); border-color: color-mix(in srgb, var(--color-primary-500) 45%, var(--color-card-border)); }
    a.sccc-featured-vendors__item:focus-visible { outline: 2px solid var(--color-primary-500); outline-offset: 2px; }

    .sccc-featured-vendors__logo-wrap { display: flex; align-items: center; justify-content: center; width: 100%; aspect-ratio: 3 / 2; }
    .sccc-featured-vendors__logo { max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; }
    .sccc-featured-vendors__name { font-weight: 600; font-size: var(--text-body-sm); }
    .sccc-featured-vendors__type {
      font-size: .72rem; font-weight: 600; padding: .15rem .6rem; border-radius: 999px;
      background: color-mix(in srgb, var(--sccc-type) 16%, transparent);
      color: color-mix(in srgb, var(--sccc-type) 75%, var(--color-text));
      border: 1px solid color-mix(in srgb, var(--sccc-type) 40%, transparent);
    }

    .sccc-featured-vendors__rail { overflow: hidden; mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent); }
    .sccc-featured-vendors__track {
      display: flex; gap: 1rem; width: max-content; list-style: none; margin: 0; padding: 0;
      animation: scccVendorRail var(--sccc-duration, 30s) linear infinite;
    }
    .sccc-featured-vendors__track > li { width: 12rem; flex: 0 0 auto; }
    .sccc-featured-vendors__rail:hover .sccc-featured-vendors__track,
    .sccc-featured-vendors__rail:focus-within .sccc-featured-vendors__track { animation-play-state: paused; }
    @keyframes scccVendorRail { to { transform: translateX(-50%); } }

    .sccc-featured-vendors__empty { text-align: center; color: var(--color-muted); }

    @media (prefers-reduced-motion: reduce) {
      .sccc-featured-vendors__track { animation: none; flex-wrap: wrap; width: auto; justify-content: center; }
      .sccc-featured-vendors__track [aria-hidden="true"] { display: none; }
      .sccc-featured-vendors__item { transition: none; }
    }
  </style>
@endonce

@if ($hasVendors || $emptyMessage !== '' || $isPreview)
  <section {{ $attributes->class(['sccc-featured-vendors']) }}>
    <div class="sccc-featured-vendors__inner container mx-auto">
      @if ($heading || ($showLine && $showTitle) || $intro)
        <div class="sccc-featured-vendors__header">
          @if ($heading)
            <h2 class="sccc-featured-vendors__heading">{{ $heading }}</h2>
          @endif

          @if ($showLine && $showTitle)
            <p class="sccc-featured-vendors__show">{{ $showTitle }}@if ($showDate) · {{ $showDate }}@endif</p>
          @endif

          @if ($intro)
            <div class="sccc-featured-vendors__intro">{!! wp_kses_post($intro) !!}</div>
          @endif
        </div>
      @endif

      @if (! $hasVendors)
        <p class="sccc-featured-vendors__empty">
          {{ $emptyMessage !== '' ? $emptyMessage : __('No confirmed vendors with feature permission for this show yet. (Only shown in the editor.)', 'sccc') }}
        </p>
      @elseif ($layout === 'rail')
        <div class="sccc-featured-vendors__rail" style="--sccc-duration: {{ (int) $duration }}s;">
          <ul class="sccc-featured-vendors__track" role="list">
            @foreach ($vendors as $vendor)
              <li>{!! $renderItem($vendor, false) !!}</li>
            @endforeach
            @foreach ($vendors as $vendor)
              <li aria-hidden="true">{!! $renderItem($vendor, true) !!}</li>
            @endforeach
          </ul>
        </div>
      @else
        <ul class="sccc-featured-vendors__grid" role="list" style="--sccc-cols: {{ (int) $columns }};">
          @foreach ($vendors as $vendor)
            <li>{!! $renderItem($vendor, false) !!}</li>
          @endforeach
        </ul>
      @endif
    </div>
  </section>
@endif
