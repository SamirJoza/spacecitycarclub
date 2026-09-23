{{--
  File path + filename: resources/views/partials/directory/filters.blade.php
  ---------------------------------------------------------------------------
  Purpose:
  - Render the directory search and filter controls.

  Why this file exists:
  - Search, category, and service filters are part of the directory feature, but
    they are distinct enough to keep out of the outer listing shell.
  - Splitting them into their own partial makes future refinements easier,
    especially if filter UI changes independently of the results grid.

  Notes:
  - GET is used intentionally so URLs are shareable and pagination-friendly.
  - The reset link strips only the directory's own query parameters.
  - Button markup uses an outer shell + inner face so the outer shell can carry
    the neon border while the inner face provides the glass effect.
--}}

<form class="sccc-business-directory__filters" method="get" action="{{ esc_url($directoryResetUrl) }}">
    <div class="sccc-business-directory__filters-grid">
      <div>
        <label class="sccc-business-directory__search-label" for="sccc-business-directory-q">
          {{ __('Search', 'sage') }}
        </label>
        <div class="sccc-business-directory__search-wrap">
          <span class="sccc-business-directory__search-icon" aria-hidden="true">⌕</span>
          <input
            class="sccc-business-directory__search-input"
            id="sccc-business-directory-q"
            name="q"
            type="search"
            value="{{ esc_attr($directorySearchTerm) }}"
            placeholder="{{ esc_attr__('Search businesses, keywords, or owners…', 'sage') }}"
          >
        </div>
      </div>
  
      <div>
        <label class="sccc-business-directory__select-label" for="sccc-business-directory-category">
          {{ __('Industry', 'sage') }}
        </label>
        <select class="sccc-business-directory__select" id="sccc-business-directory-category" name="category">
          <option value="">{{ __('All industries', 'sage') }}</option>
          @foreach ($directoryCategories as $value => $label)
            <option value="{{ esc_attr($value) }}" @selected($directoryCategory === $value)>{{ esc_html($label) }}</option>
          @endforeach
        </select>
      </div>
  
      <div>
        <label class="sccc-business-directory__select-label" for="sccc-business-directory-service">
          {{ __('Service', 'sage') }}
        </label>
        <select class="sccc-business-directory__select" id="sccc-business-directory-service" name="service">
          <option value="">{{ __('All services', 'sage') }}</option>
          @foreach ($directoryServices as $value => $label)
            <option value="{{ esc_attr($value) }}" @selected($directoryService === $value)>{{ esc_html($label) }}</option>
          @endforeach
        </select>
      </div>
    </div>
  
    <div class="sccc-business-directory__controls">
      <p class="sccc-business-directory__summary">{{ esc_html($directorySummary) }}</p>
  
      <div class="sccc-business-directory__actions">
        <button
          class="sccc-business-directory__neo-button sccc-business-directory__neo-button--primary"
          type="submit"
        >
          <span class="sccc-business-directory__neo-button-face">
            <span class="sccc-business-directory__neo-button-label">{{ __('Apply Filters', 'sage') }}</span>
          </span>
        </button>
  
        <a
          class="sccc-business-directory__neo-button sccc-business-directory__neo-button--ghost"
          href="{{ esc_url($directoryResetUrl) }}"
        >
          <span class="sccc-business-directory__neo-button-face">
            <span class="sccc-business-directory__neo-button-label">{{ __('Reset', 'sage') }}</span>
          </span>
        </a>
      </div>
    </div>
  </form>