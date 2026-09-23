{{--
  Template Name: Business Directory

  File path + filename: resources/views/template-business-directory.blade.php
  ---------------------------------------------------------------------------
  Purpose:
  - Render the business directory page as a hybrid page:
    1) normal WordPress page content at the top for Gutenberg-managed content,
       including the masthead block
    2) a dynamic member business directory below the page content
    3) a global CTA section below the directory listing, sourced from Theme
       Settings options

  Why this file exists:
  - The page needs editor-managed content and a dynamic user-based listing on
    the same screen.
  - Keeping the listing and CTA outside Gutenberg preserves predictable
    filtering, query-string pagination, and reusable partials.

  Notes:
  - The directory and CTA markup/styles are fully scoped under the
    `.sccc-business-directory-template` root class.
  - The CTA partial reads option-page values directly with `get_field(..., 'option')`.
--}}

@extends('layouts.app')

@section('content')
  @while (have_posts())
    @php(the_post())

    <div @php(post_class($directoryRootClass))>
      @include('partials.directory.styles')

      <article class="{{ $directoryRootClass }}__page-content">
        {!! apply_filters('the_content', get_the_content()) !!}
      </article>

      @include('partials.directory.listing')

      @include('partials.directory.cta')
    </div>
  @endwhile
@endsection