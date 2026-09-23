{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/partials/blog-archive-feed.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Shared feed partial used by all blog archive templates:
|     home.blade.php             (blog index / latest news)
|     taxonomy-content_type.blade.php
|     category.blade.php
|     tag.blade.php
|
| Why a partial and not repeated code:
| - The toolbar (title + search + pills), post grid, and pagination are
|   identical across all blog archive contexts. A single partial means any
|   future change only needs to happen here.
|
| Feed title logic:
| - Determined by the current WordPress query context so the toolbar heading
|   always reflects what the visitor is browsing.
|
| Pills:
| - "All Posts" is always unconditional.
| - Content type pills activate when is_tax('content_type') matches the term.
| - Events chip activates on the events post type archive.
| - Category/tag/author archives show no chip as active (they are browsed
|   via the sidebar "Browse by Category" widget, not the pills).
--------------------------------------------------------------------------
--}}

@php
  /**
   * Feed title — context-aware.
   */
  if (is_search()) {
      $feedTitle = sprintf(__('Search Results for "%s"', 'sage'), get_search_query());
  } elseif (is_tax('content_type')) {
      $feedTitle = single_term_title('', false);
  } elseif (is_category()) {
      $feedTitle = single_term_title('', false);
  } elseif (is_tag()) {
      $feedTitle = single_term_title('', false);
  } elseif (is_author()) {
      $feedTitle = get_the_author_meta('display_name', get_queried_object_id());
  } elseif (is_date()) {
      $feedTitle = get_the_date('F Y');
  } else {
      $feedTitle = __('Latest News', 'sage');
  }

  /**
   * "All Posts" pill URL.
   */
  $postsPageId = (int) get_option('page_for_posts');
  $allPostsUrl = $postsPageId ? get_permalink($postsPageId) : home_url('/');

  /**
   * Content type terms for pills.
   * hide_empty: true so only types that have posts appear.
   */
  $contentTypeTerms = get_terms([
    'taxonomy'   => 'content_type',
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 0,
  ]);

  if (is_wp_error($contentTypeTerms)) {
    $contentTypeTerms = [];
  }

  /**
   * Currently active content type term (used to highlight the correct pill).
   */
  $activeContentTypeTerm = (is_tax('content_type') && get_queried_object() instanceof WP_Term)
    ? get_queried_object()
    : null;

  /**
   * Optional Events archive chip.
   * Assumption: events post type slug is 'events'.
   */
  $eventsPostTypeSlug = 'events';
  $eventsChip         = null;

  if (post_type_exists($eventsPostTypeSlug)) {
    $eventsPostTypeObject = get_post_type_object($eventsPostTypeSlug);
    $eventsArchiveUrl     = get_post_type_archive_link($eventsPostTypeSlug);

    if ($eventsPostTypeObject && ! empty($eventsPostTypeObject->has_archive) && $eventsArchiveUrl) {
      if ((int) wp_count_posts($eventsPostTypeSlug)->publish > 0) {
        $eventsChip = [
          'label' => $eventsPostTypeObject->labels->name ?? __('Events', 'sage'),
          'url'   => $eventsArchiveUrl,
        ];
      }
    }
  }
@endphp

<section class="sccc-blog-list" aria-label="{{ esc_attr($feedTitle) }}">

  <header class="sccc-blog-list__toolbar">

    <div class="sccc-blog-list__toolbar-top">

      {{-- h2 — h1 belongs to the hero on the blog index, or is the page
           title on archive pages (rendered by the browser/SEO layer). --}}
      <h2 class="sccc-blog-list__title">{{ $feedTitle }}</h2>

      <form
        class="sccc-blog-list__search"
        action="{{ esc_url(home_url('/')) }}"
        method="get"
        role="search"
      >
        <label class="screen-reader-text" for="sccc-blog-search">
          {{ __('Search articles', 'sage') }}
        </label>

        <span class="sccc-blog-list__search-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <circle cx="11" cy="11" r="6"></circle>
            <path d="m20 20-4.2-4.2" stroke-linecap="round"></path>
          </svg>
        </span>

        <input
          id="sccc-blog-search"
          type="search"
          name="s"
          value="{{ esc_attr(get_search_query()) }}"
          placeholder="{{ esc_attr__('Search articles…', 'sage') }}"
        >
      </form>

    </div>{{-- /.sccc-blog-list__toolbar-top --}}

    <nav
      class="sccc-blog-list__chips"
      aria-label="{{ esc_attr__('Filter by content type', 'sage') }}"
    >

      {{-- All Posts — always present, active only on the blog index --}}
      
       <a href="{{ esc_url($allPostsUrl) }}"
        class="sccc-blog-list__chip {{ (is_home() && ! is_paged() && ! is_search()) ? 'is-active' : '' }}"
      >
        {{ __('All Posts', 'sage') }}
      </a>

      {{-- Events archive chip --}}
      @if (! empty($eventsChip))
        
         <a href="{{ esc_url($eventsChip['url']) }}"
          class="sccc-blog-list__chip {{ is_post_type_archive('events') ? 'is-active' : '' }}"
        >
          {{ $eventsChip['label'] }}
        </a>
      @endif

      {{-- Content type pills --}}
      @foreach ($contentTypeTerms as $term)
        
         <a href="{{ esc_url(get_term_link($term)) }}"
          class="sccc-blog-list__chip {{ (! empty($activeContentTypeTerm) && $activeContentTypeTerm->term_id === $term->term_id) ? 'is-active' : '' }}"
        >
          {{ $term->name }}
        </a>
      @endforeach

    </nav>

  </header>{{-- /.sccc-blog-list__toolbar --}}

  @if (! have_posts())
    <div class="sccc-blog-list__empty">
      <h2>{{ __('No posts found', 'sage') }}</h2>
      <p>{{ __('Sorry, no results were found. Try a different search or browse one of the content types above.', 'sage') }}</p>
    </div>
  @else
    <div class="sccc-blog-list__grid">
      @while (have_posts())
        @php(the_post())
        @includeFirst(['partials.content-' . get_post_type(), 'partials.content'])
      @endwhile
    </div>

    <div class="sccc-blog-list__pagination">
      {!! get_the_posts_navigation() !!}
    </div>
  @endif

</section>