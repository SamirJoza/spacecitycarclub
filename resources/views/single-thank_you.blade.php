{{--
|--------------------------------------------------------------------------
| single-thank_you.blade.php
|--------------------------------------------------------------------------
| Thank You CPT — same site chrome as other public pages (`layouts.app`).
| Hero / background styling lives in `partials/content-single-thank-you`.
|--------------------------------------------------------------------------
--}}

@extends('layouts.app')

@section('content')
  @while(have_posts())
    @php(the_post())
    @include('partials.content-single-thank-you')
  @endwhile
@endsection
