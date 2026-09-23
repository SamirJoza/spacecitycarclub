{{-- resources/views/components/navi/footer.blade.php --}}
@props([
  'name'        => null,
  'columns'     => 2,
  'gap'         => 'gap-6',
  'listGap'     => 'space-y-2',
  'flatten'     => true,
  'hashAsSpan'  => true,
])

@php
  $menu = Navi::build($name);

  // Flatten to 3 levels if requested
  $flat = collect();
  if ($menu && $menu->isNotEmpty()) {
    foreach ($menu->all() as $parent) {
      if (! $flatten) { $flat->push($parent); continue; }
      $flat->push($parent);

      foreach ($parent->children ?? [] as $child) {
        $flat->push($child);
        foreach ($child->children ?? [] as $grand) {
          $flat->push($grand);
        }
      }
    }
  }

  $flat = $flat->filter(fn($n) => !empty($n->label));

  $cols   = max(1, (int) $columns);
  $chunks = $flat->chunk(ceil(max(1, $flat->count()) / $cols));

  $colsClass = match ($cols) {
    1 => 'md:grid-cols-1',
    2 => 'md:grid-cols-2',
    3 => 'md:grid-cols-3',
    4 => 'md:grid-cols-4',
    5 => 'md:grid-cols-5',
    6 => 'md:grid-cols-6',
    default => 'md:grid-cols-2',
  };
@endphp

@if ($menu && $menu->isNotEmpty())
  <div {{ $attributes->merge(['class' => "grid grid-cols-1 $colsClass $gap"]) }}>
    @foreach ($chunks as $chunk)
      <ul class="{{ $listGap }}">
        @foreach ($chunk as $item)
          @php
            $isActive = (bool)($item->active ?? false);
            $href     = $item->url ?? '#';
            $isHash   = $hashAsSpan && ($href === '#' || $href === '' || $href === null);
          @endphp

          <li>
            @if ($isHash)
              <span>{{ $item->label }}</span>
            @else
              <a href="{{ $href }}" @if($isActive) aria-current="page" @endif>
                {{ $item->label }}
              </a>
            @endif
          </li>
        @endforeach
      </ul>
    @endforeach
  </div>
@endif
