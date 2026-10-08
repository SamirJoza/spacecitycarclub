{{--
  <x-ui.control-label> — small uppercase field label, pairs with .sccc-control inputs
  (resources/css/components/surfaces.css).

  Example
    <x-ui.control-label for="member_search">Search</x-ui.control-label>
    <input id="member_search" class="sccc-control" type="search">
--}}
<label {{ $attributes->class(['sccc-control-label']) }}>{{ $slot }}</label>
