{{--
  File path + filename: resources/views/partials/directory/grid.blade.php
  ---------------------------------------------------------------------------
  Purpose:
  - Render either the business card grid or the empty state.

  Why this file exists:
  - Separating the branching logic keeps the outer listing partial easy to scan.
  - The single-card partial stays fully reusable for future spotlight or related
    directory surfaces.
--}}

@if ($directoryHasResults)
  <div class="sccc-business-directory__grid">
    @foreach ($directoryCards as $card)
      @include('partials.directory.card', ['card' => $card])
    @endforeach
  </div>
@else
  @include('partials.directory.empty')
@endif