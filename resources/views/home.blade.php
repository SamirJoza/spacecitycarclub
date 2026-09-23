{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/home.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the blog posts index with the featured hero above the feed.
| - The feed itself (toolbar + pills + grid + pagination) is shared with all
|   blog archive templates via partials/blog-archive-feed.blade.php.
|
| Why this file is kept separate from the archive templates:
| - Only the blog index has the hero section. All other archive pages omit
|   it. The hero query logic belongs here, not in the shared partial.
--------------------------------------------------------------------------
--}}

@extends('layouts.blog')

{{-- ─────────────────────────────────────────────────────────────────────────
     HERO SECTION
───────────────────────────────────────────────────────────────────────────── --}}
@section('hero')
  @php
    $stickyIds = get_option('sticky_posts', []);

    $heroArgs = ! empty($stickyIds)
      ? [
          'post_status'         => 'publish',
          'posts_per_page'      => 1,
          'post__in'            => $stickyIds,
          'ignore_sticky_posts' => 1,
          'orderby'             => 'date',
          'order'               => 'DESC',
        ]
      : [
          'post_status'    => 'publish',
          'posts_per_page' => 1,
          'orderby'        => 'date',
          'order'          => 'DESC',
        ];

    $heroQuery   = new WP_Query($heroArgs);
    $heroPost    = null;
    $heroTitle   = '';
    $heroUrl     = '#';
    $heroExcerpt = '';
    $heroCat     = null;
    $heroBgStyle = '';

    if ($heroQuery->have_posts()) {
      $heroQuery->the_post();

      $heroPost    = get_post();
      $heroTitle   = get_the_title();
      $heroUrl     = get_permalink();
      $heroExcerpt = get_the_excerpt();

      $heroContentTypes = get_the_terms(get_the_ID(), 'content_type');
      $heroCategories   = get_the_category();

      if (! empty($heroContentTypes) && ! is_wp_error($heroContentTypes)) {
        $heroCat = $heroContentTypes[0]->name;
      } elseif (! empty($heroCategories)) {
        $heroCat = $heroCategories[0]->name;
      }

      if (has_post_thumbnail()) {
        $heroImg     = get_the_post_thumbnail_url(null, 'full');
        $heroBgStyle = 'background-image: url(' . esc_url($heroImg) . ');';
      }

      wp_reset_postdata();
    }
  @endphp

  @if ($heroPost)
    <section
      class="sccc-blog-hero"
      aria-label="{{ esc_attr__('Featured story', 'sage') }}"
    >
      <div
        class="sccc-blog-hero__media"
        @if($heroBgStyle) style="{{ $heroBgStyle }}" @endif
        role="img"
        aria-label="{{ esc_attr($heroTitle) }}"
      ></div>

      <div class="sccc-blog-hero__overlay" aria-hidden="true"></div>

      <div class="sccc-blog-hero__content">

        <span class="sccc-blog-hero__badge">
          {{ $heroCat ?? __('Featured Story', 'sage') }}
        </span>

        <h1 class="sccc-blog-hero__title">
          <a href="{{ esc_url($heroUrl) }}">{{ $heroTitle }}</a>
        </h1>

        @if (! empty($heroExcerpt))
          <p class="sccc-blog-hero__excerpt">{{ $heroExcerpt }}</p>
        @endif

        <a href="{{ esc_url($heroUrl) }}" class="sccc-blog-hero__cta">
          {{ __('Read Full Story', 'sage') }}
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
            <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </a>

      </div>
    </section>
  @endif
@endsection


{{-- ─────────────────────────────────────────────────────────────────────────
     CONTENT — shared feed partial
───────────────────────────────────────────────────────────────────────────── --}}
@section('content')
  @include('partials.blog-archive-feed')
@endsection


@section('sidebar')
  @include('sections.sidebar')
@endsection