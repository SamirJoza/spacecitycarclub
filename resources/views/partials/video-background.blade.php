{{--
  File: resources/views/partials/video-background.blade.php
  Purpose: Outputs the selected page background video from ACF, including contained styles.
--}}

@php
  $video = function_exists('get_field') ? get_field('sccc_page_background_video') : null;
  $poster = function_exists('get_field') ? get_field('sccc_page_background_video_poster') : null;
  $overlayOpacity = function_exists('get_field') ? get_field('sccc_page_background_video_overlay_opacity') : null;

  $videoUrl = '';
  $videoMime = 'video/mp4';

  if (is_array($video)) {
    $videoUrl = $video['url'] ?? '';
    $videoMime = $video['mime_type'] ?? 'video/mp4';
  } elseif (is_numeric($video)) {
    $videoUrl = wp_get_attachment_url((int) $video) ?: '';
    $videoMime = get_post_mime_type((int) $video) ?: 'video/mp4';
  } elseif (is_string($video)) {
    $videoUrl = $video;
    $videoFileType = wp_check_filetype($videoUrl);
    $videoMime = $videoFileType['type'] ?? 'video/mp4';
  }

  $posterUrl = '';

  if (is_array($poster)) {
    $posterUrl = $poster['url'] ?? '';
  } elseif (is_numeric($poster)) {
    $posterUrl = wp_get_attachment_image_url((int) $poster, 'full') ?: '';
  } elseif (is_string($poster)) {
    $posterUrl = $poster;
  }

  $overlayOpacity = is_numeric($overlayOpacity)
    ? max(0, min(90, (int) $overlayOpacity)) / 100
    : 0.45;
@endphp

@if(!empty($videoUrl))
  <style>
    .sccc-has-video-background {
      min-height: 100vh;
      background-color: #000;
    }

    .sccc-has-video-background #app {
      position: relative;
      z-index: 2;
      min-height: 100vh;
    }

    .sccc-has-video-background #main {
      background: transparent !important;
      background-image: none !important;
      background-color: transparent !important;
    }

    html[data-theme="light"] body.sccc-has-video-background #main {
      background: transparent !important;
      background-image: none !important;
      background-color: transparent !important;
    }

    @supports (color: color-mix(in lab, red, red)) {
      html[data-theme="light"] body.sccc-has-video-background #main {
        background: transparent !important;
        background-image: none !important;
        background-color: transparent !important;
      }
    }

    .sccc-page-video-bg {
      position: fixed;
      inset: 0;
      z-index: 0;
      overflow: hidden;
      pointer-events: none;
      background-color: #000;
      background-image: var(--sccc-page-video-bg-poster, none);
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
    }

    .sccc-page-video-bg__video {
      width: 100vw;
      height: 100vh;
      object-fit: cover;
      display: block;
    }

    .sccc-page-video-bg__overlay {
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, var(--sccc-page-video-bg-overlay-opacity, 0.45));
    }

    html[data-theme="light"] .sccc-page-video-bg__overlay {
      background: rgba(255, 255, 255, 0.16);
    }

    @media (prefers-reduced-motion: reduce) {
      .sccc-page-video-bg__video {
        display: none;
      }
    }
  </style>

  <div
    class="sccc-page-video-bg"
    aria-hidden="true"
    style="
      --sccc-page-video-bg-overlay-opacity: {{ $overlayOpacity }};
      @if(!empty($posterUrl))
        --sccc-page-video-bg-poster: url('{{ esc_url($posterUrl) }}');
      @endif
    "
  >
    <video
      class="sccc-page-video-bg__video"
      autoplay
      muted
      loop
      playsinline
      preload="metadata"
      @if(!empty($posterUrl)) poster="{{ esc_url($posterUrl) }}" @endif
    >
      <source src="{{ esc_url($videoUrl) }}" type="{{ esc_attr($videoMime) }}">
    </video>

    <div class="sccc-page-video-bg__overlay"></div>
  </div>
@endif