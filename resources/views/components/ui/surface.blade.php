{{--
  <x-ui.surface> — tinted card or panel (resources/css/components/surfaces.css).

  Props
  - as      HTML tag (default "div")
  - panel   quieter fill and shadow, for toolbars and notices
  - accent  2px gradient line along the top edge
  - lift    hover lift and glow, for clickable cards

  Example
    <x-ui.surface as="article" accent lift class="my-card">…</x-ui.surface>
--}}
@props(['as' => 'div', 'panel' => false, 'accent' => false, 'lift' => false])

<{{ $as }} {{ $attributes->class([
  'sccc-surface',
  'sccc-surface--panel' => $panel,
  'sccc-surface--accent' => $accent,
  'sccc-surface--lift' => $lift,
]) }}>{{ $slot }}</{{ $as }}>
