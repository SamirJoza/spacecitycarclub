{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/category.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the archive page for a WordPress category
|   (e.g. /category/club-news/).
| - No hero. Feed title shows the category name via single_term_title()
|   in the shared partial.
| - No pill is marked active — category archives are navigated via the
|   sidebar "Browse by Category" widget, not the content type pills.
--------------------------------------------------------------------------
--}}

@extends('layouts.blog')

@section('content')
  @include('partials.blog-archive-feed')
@endsection

@section('sidebar')
  @include('sections.sidebar')
@endsection