{{--
  Template Name: Video Background Page

  File: resources/views/template-video-background.blade.php
  Purpose: Page template that uses the dedicated video background layout.
--}}

@extends('layouts.video-background')

@section('content')
  @while(have_posts())
    @php(the_post())

    @includeFirst(['partials.content-page', 'partials.content'])
  @endwhile
@endsection