{{--
  resources/views/page.blade.php
  File: resources/views/page.blade.php

  What this file does
  -----------------------------------------------------------------------------
  - Master template for WordPress Pages.
  - “Bootstraps” a slug-specific partial first (same idea as your post.blade.php):
      1) partials.content-{slug}
      2) partials.content-page
      3) partials.content
  - This allows:
      resources/views/partials/content-membership-checkout.blade.php
    to load automatically when the page slug is: membership-checkout
--}}

@extends('layouts.app')

@section('content')
  @while (have_posts())
    @php(the_post())

    @php($slug = (string) get_post_field('post_name', get_post()))

    @includeFirst(['partials.content-' . $slug, 'partials.content-page', 'partials.content'])
  @endwhile
@endsection
