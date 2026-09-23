{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/partials/entry-meta.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the compact meta strip for blog overview cards.
| - Show publish date, estimated reading time, and category/tag context in a
|   way that supports the new card design.
|
| Why this file exists:
| - The original blog card markup needs a dedicated meta row that can be
|   reused consistently anywhere `partials.entry-meta` is included.
| - Keeping meta in a separate partial avoids duplicating the date and term
|   formatting logic across templates.
|
| Notes:
| - Reading time is intentionally lightweight and based on a simple word count.
| - Only the first category is shown here because the card badge already keeps
|   the taxonomy treatment visually tight.
|--------------------------------------------------------------------------
--}}

@php
  /**
   * Build compact post meta values.
   *
   * Why this exists:
   * - A small, readable meta row gives the cards more editorial polish.
   * - We keep the logic local so the partial stays portable.
   */
  $categories = get_the_category();
  $primaryCategory = ! empty($categories) ? $categories[0] : null;
  $wordCount = str_word_count(wp_strip_all_tags((string) get_post_field('post_content', get_the_ID())));
  $readingMinutes = max(1, (int) ceil($wordCount / 220));
@endphp

<div class="entry-meta sccc-entry-meta">
  <time class="sccc-entry-meta__item" datetime="{{ esc_attr(get_the_date('c')) }}">
    {{ get_the_date('M j, Y') }}
  </time>

  <span class="sccc-entry-meta__dot" aria-hidden="true"></span>

  <span class="sccc-entry-meta__item">
    {{ sprintf(__('%d min read', 'sage'), $readingMinutes) }}
  </span>

  {{-- @if ($primaryCategory)
    <span class="sccc-entry-meta__dot" aria-hidden="true"></span>

    <a class="sccc-entry-meta__item sccc-entry-meta__item--taxonomy" href="{{ esc_url(get_category_link($primaryCategory)) }}">
      {{ $primaryCategory->name }}
    </a>
  @endif --}}
</div>