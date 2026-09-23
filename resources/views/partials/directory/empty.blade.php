{{--
  File path + filename: resources/views/partials/directory/empty.blade.php
  ---------------------------------------------------------------------------
  Purpose:
  - Render the no-results state for the business directory.

  Why this file exists:
  - Empty states deserve their own partial so they can evolve independently from
    the result grid and remain easy to reuse or restyle.
--}}

<div class="sccc-business-directory__empty">
    <h3 class="sccc-business-directory__empty-title">{{ __('No matching businesses found', 'sage') }}</h3>
    <p class="sccc-business-directory__empty-text">
      {{ __('Try adjusting your search, choosing a different industry, or clearing the current service filter to see more member-owned businesses.', 'sage') }}
    </p>
  </div>