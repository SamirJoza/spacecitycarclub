{{--
  <x-ui.pill> — utility pill button or link (resources/css/components/surfaces.css).
  Call-to-action buttons keep using .btn / .btn-primary from buttons.css.

  Props
  - href    renders an <a> when set, otherwise a <button>
  - accent  blue-edged variant for the main action of a group
  - type    button type when no href (default "button")

  Example
    <x-ui.pill accent type="submit">Filter</x-ui.pill>
    <x-ui.pill :href="$resetUrl">Reset</x-ui.pill>
--}}
@props(['href' => null, 'accent' => false, 'type' => 'button'])

@if ($href)
  <a href="{{ esc_url($href) }}" {{ $attributes->class(['sccc-pill', 'sccc-pill--accent' => $accent]) }}>{{ $slot }}</a>
@else
  <button type="{{ $type }}" {{ $attributes->class(['sccc-pill', 'sccc-pill--accent' => $accent]) }}>{{ $slot }}</button>
@endif
