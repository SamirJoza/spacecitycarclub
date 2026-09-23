{{-- resources/views/page-members.blade.php --}}
{{--
  File: resources/views/page-members.blade.php

  Purpose
  ------------------------------------------------------------------------------
  Page template that uses the Members layout. Assign this template to the
  WooCommerce "My Account" page (or any members-only page that needs footer DOM).

  How to use
  ------------------------------------------------------------------------------
  WP Admin → Pages → My Account → Template: "Members Page"

  Template Name: Members Page
--}}

@extends('layouts.members')

@section('content')
  @while(have_posts()) @php(the_post())
    @includeFirst(['partials.content-page', 'partials.content'])
  @endwhile
@endsection
