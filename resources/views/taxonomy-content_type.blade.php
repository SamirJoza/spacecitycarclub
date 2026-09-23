{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/taxonomy-content_type.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the archive page for a single Content Type term
|   (e.g. /content-type/event-recaps/).
| - Identical to the blog index layout but without the hero section.
| - The active pill is automatically highlighted by the shared partial
|   because is_tax('content_type') returns true and get_queried_object()
|   returns the current term.
--------------------------------------------------------------------------
--}}

@extends('layouts.blog')

@section('content')
  @include('partials.blog-archive-feed')
@endsection

@section('sidebar')
  @include('sections.sidebar')
@endsection