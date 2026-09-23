{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/partials/content.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render a single blog card inside the index/archive feed.
| - Move the default plain article markup toward the premium card layout in
|   the mockup while still using normal WordPress post data.
|
| Why this file exists in this form:
| - The blog overview styling should stay reusable for post archives without
|   introducing custom queries or hard-coded content.
| - The article keeps semantic markup while the visual treatment comes from
|   the scoped blog-list CSS file.
|
| Notes:
| - The post meta row is delegated to `partials.entry-meta` so that the date,
|   taxonomy labels, and reading-time styling can be controlled separately.
| - The featured image is optional; a fallback badge renders when no thumbnail
|   is set so the card still feels intentional.
|--------------------------------------------------------------------------
--}}

@php
  /**
   * Resolve display helpers for the current post.
   *
   * Why this exists:
   * - We need a safe featured image URL, category label, and fallback state
   *   for each card without assuming every post has all of that data.
   */
  $thumbnailUrl = has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(), 'large') : '';
  $categories = get_the_category();
  $primaryCategory = ! empty($categories) ? $categories[0] : null;
@endphp

<article @php(post_class('sccc-post-card'))>
  <a class="sccc-post-card__media" href="{{ get_permalink() }}" aria-label="{{ esc_attr($title) }}">
    @if ($thumbnailUrl)
      <img
        class="sccc-post-card__image"
        src="{{ esc_url($thumbnailUrl) }}"
        alt="{{ esc_attr(get_the_title()) }}"
        loading="lazy"
      >
    @else
      <div class="sccc-post-card__image sccc-post-card__image--fallback" aria-hidden="true">
        <span>Space City</span>
      </div>
    @endif

    @if ($primaryCategory)
      <span class="sccc-post-card__badge">
        {{ $primaryCategory->name }}
      </span>
    @endif
  </a>

  <div class="sccc-post-card__body">
    @include('partials.entry-meta')

    <header class="sccc-post-card__header">
      <h2 class="entry-title sccc-post-card__title h3">
        <a href="{{ get_permalink() }}">
          {!! $title !!}
        </a>
      </h2>
    </header>

    <div class="entry-summary sccc-post-card__summary">
      @php(the_excerpt())
    </div>

    <div class="sccc-post-card__footer">
      <a class="sccc-post-card__link" href="{{ get_permalink() }}">
        {{ __('Read More', 'sage') }}
        <span aria-hidden="true">→</span>
      </a>
    </div>
  </div>
</article>