{{--
  <x-ui.chip> — small status or tag chip (resources/css/components/surfaces.css).

  Example
    <x-ui.chip>Founding Member</x-ui.chip>
--}}
<span {{ $attributes->class(['sccc-chip']) }}>{{ $slot }}</span>
