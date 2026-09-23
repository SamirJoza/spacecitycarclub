{{--
  resources/views/single.blade.php

  Template for single blog posts.
  Extends layouts/single.blade.php (outer shell + container).

  Sidebar strategy:
  - dynamic_sidebar('blog-sidebar') is captured here at template level,
    before the partial is included, via ob_start/ob_get_clean.
  - The captured HTML is passed to the partial as $sidebarHtml.
  - The partial uses $sidebarHtml to conditionally render the two-column
    grid (body 2/3 + sidebar 1/3). When empty, the article is full-width.
  - Capturing here (not in a Composer) keeps dynamic_sidebar() at the
    correct template layer — the same safe context used in blog.blade.php.
  - Capturing into a variable (not echoing directly into @section) avoids
    dynamic_sidebar()'s output disrupting Blade's section buffer.
--}}

@extends('layouts.single')

@section('content')
  @php
    // Capture the sidebar widget area before the partial is included.
    // ob_start prevents dynamic_sidebar() from echoing into Blade's buffer.
    $sidebarHtml = '';
    if (is_active_sidebar('blog-sidebar')) {
        ob_start();
        dynamic_sidebar('blog-sidebar');
        $sidebarHtml = ob_get_clean() ?: '';
    }
  @endphp

  @while(have_posts()) @php(the_post())
    @includeFirst(
      ['partials.content-single-' . get_post_type(), 'partials.content-single'],
      ['sidebarHtml' => $sidebarHtml]
    )
  @endwhile
@endsection