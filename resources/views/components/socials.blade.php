{{-- resources/views/components/socials.blade.php --}}
@props([
  'rows'        => null,
  'showLabels'  => true,     // set false for icons-only
  'layout'      => 'col',    // 'row' for inline row
  'gap'         => 'gap-2',
])

@php
  $source = $rows;
  if ($source === null && function_exists('get_field')) {
    $source = get_field('socials', 'option') ?: [];
  }
  $source = is_array($source) ? $source : [];

  // Map to Blade component aliases (without the leading "x-")
  $map = [
    'fb'     => ['label' => 'Facebook', 'icon' => 'fab-facebook-f'],
    'ig'     => ['label' => 'Instagram','icon' => 'fab-instagram'],
    'yt'     => ['label' => 'YouTube',  'icon' => 'fab-youtube'],
    'tiktok' => ['label' => 'TikTok',   'icon' => 'fab-tiktok'],
    'x'      => ['label' => 'X (Twitter)','icon' => 'fab-x-twitter'],
  ];

  $items = collect($source)->map(function ($row) use ($map) {
    $key = strtolower(trim($row['social'] ?? ''));
    $url = trim((string)($row['socialURL'] ?? ''));
    if ($url === '' || !isset($map[$key])) return null;
    return [
      'url'   => $url,
      'label' => $map[$key]['label'],
      'icon'  => $map[$key]['icon'], // e.g. 'fab-instagram'
    ];
  })->filter();

  $wrapClass = $layout === 'row'
    ? "flex flex-row items-center {$gap} flex-wrap"
    : "flex flex-col {$gap}";
@endphp

@if($items->isNotEmpty())
  @once
    <style>
      .footer-socials a { display:inline-flex; align-items:center; gap:.5rem; }
      .footer-socials a svg { width: 1rem; height: 1rem; }
      .footer-socials a svg, .footer-socials a svg * { fill: currentColor; stroke: currentColor; }
    </style>
  @endonce

  <ul {{ $attributes->merge(['class' => "footer-socials {$wrapClass}"]) }}>
    @foreach($items as $item)
      <li>
        <a href="{{ esc_url($item['url']) }}" target="_blank" rel="noopener" aria-label="{{ $item['label'] }}">
          <x-dynamic-component :component="$item['icon']" />
          @if($showLabels)
            <span>{{ $item['label'] }}</span>
          @else
            <span class="sr-only">{{ $item['label'] }}</span>
          @endif
        </a>
      </li>
    @endforeach
  </ul>
@endif
