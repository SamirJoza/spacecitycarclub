{{--
|--------------------------------------------------------------------------
| File path + filename: resources/views/single-sponsor.blade.php
|--------------------------------------------------------------------------
| Purpose:
| - Provide a dedicated single template for the Sponsor CPT so sponsor posts
|   do not inherit any blog-specific single-post presentation.
|
| Why this file exists:
| - WordPress will resolve `single-sponsor` before the generic `single`
|   template, which is the cleanest way to give Sponsors their own layout.
| - This wrapper stays intentionally thin and delegates all sponsor-specific
|   markup to a dedicated partial, which matches the Sage pattern of keeping
|   reusable or focused view markup inside `resources/views/partials`.
|
| Notes:
| - Assumes the default non-blog site wrapper is `layouts.app`.
| - If your project uses a different global layout for CPT singles, only the
|   `@extends()` line should need to change.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('content')
  @while(have_posts())
    @php(the_post())
    @include('partials.content-single-sponsor')
  @endwhile
@endsection