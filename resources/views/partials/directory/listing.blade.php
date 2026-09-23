{{--
  File path + filename: resources/views/partials/directory/listing.blade.php
  ---------------------------------------------------------------------------
  Purpose:
  - Render the dynamic member business directory shell.

  Why this file exists:
  - This partial is the single assembly point for the directory experience.
  - It keeps the page template simple while still letting the directory remain a
    composed feature made of smaller, reusable partials.

  Notes:
  - All data for this partial is prepared by `App\View\Composers\BusinessDirectory`.
  - The CTA partial is intentionally omitted for now and will be added later.
  - PMPro access is checked here because this partial renders member business
    data outside the normal `the_content()` filter.
--}}

@php
  /**
   * Gate the custom directory output with PMPro.
   *
   * Why:
   * PMPro protects normal page content through WordPress' content filter, but
   * this partial renders custom member data after that content. Checking the
   * current page here prevents business names, owner names, websites, emails,
   * filters, and pagination from being present in the HTML for non-members.
   *
   * Safe fallback:
   * If PMPro is unavailable for any reason, the directory keeps rendering
   * instead of causing a fatal error during local development.
   */
  $directoryHasMembershipAccess = true;
  $directoryPostId = get_queried_object_id();

  if (function_exists('pmpro_has_membership_access') && $directoryPostId > 0) {
      $directoryHasMembershipAccess = (bool) pmpro_has_membership_access($directoryPostId);
  }
@endphp

@if ($directoryHasMembershipAccess)
  <section class="{{ $directoryRootClass }}__shell" aria-labelledby="sccc-business-directory-heading">
    <div class="{{ $directoryRootClass }}__container">
      <div class="{{ $directoryRootClass }}__panel">
        <h2 id="sccc-business-directory-heading" class="sr-only">
          {{ __('Member Business Directory', 'sage') }}
        </h2>

        @include('partials.directory.filters')

        <div class="sccc-business-directory__results">
          @include('partials.directory.grid')

          @include('partials.directory.pagination')
        </div>
      </div>
    </div>
  </section>

  @include('partials.directory.modal')
@endif