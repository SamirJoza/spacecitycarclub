{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/tag.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Render the archive page for a WordPress tag (e.g. /tag/mustang/).
| - No hero. Feed title shows the tag name via single_term_title()
|   in the shared partial.
--------------------------------------------------------------------------
--}}

@extends('layouts.blog')

@section('content')
  @include('partials.blog-archive-feed')
@endsection

@section('sidebar')
  @include('sections.sidebar')
@endsection