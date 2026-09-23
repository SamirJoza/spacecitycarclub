{{--
  File path + filename: resources/views/partials/directory/pagination.blade.php
  ---------------------------------------------------------------------------
  Purpose:
  - Render paginated directory navigation.

  Why this file exists:
  - Pagination markup often changes independently of the grid.
  - Keeping it separate avoids burying navigation logic inside the main results
    partial and makes future styling changes safer.

  Notes:
  - The Composer returns the links as an array so the template has full control
    of the wrapper markup.
--}}

@if (!empty($directoryPagination))
  <nav class="sccc-business-directory__pagination" aria-label="{{ esc_attr__('Business directory pagination', 'sage') }}">
    @foreach ($directoryPagination as $link)
      {!! $link !!}
    @endforeach
  </nav>
@endif