{{-- ========================================================================================
File: resources/views/blocks/feature.blade.php

Feature block – vehicle-only frontend template
----------------------------------------------

PURPOSE
-------
Render a single vehicle feature card with:
- centered card shell
- mobile stacked layout
- nickname pill + auto month/year
- clickable owner name
- instance-scoped owner modal fed by Feature.php / archive repository

IMPORTANT
---------
This template keeps the existing card + modal behavior, but improves:
1) card centering on large screens
2) mobile stacking/readability
3) a more legible glassy neon pill
4) theme-aware owner label styling
5) member tenure in the owner modal
6) optional per-block section background image
7) optional parallax effect for that background layer
8) editor-controlled section top/bottom padding
9) editor-controlled parallax speed
10) centered max-w-7xl content container
11) a single subtle section overlay for headline readability
12) a more transparent, neon-reflective glass treatment for the right text column
13) editor-safe owner output so the frontend owner modal cannot open in Gutenberg

WHY THE MODAL CSS IS SPLIT
--------------------------
The modal node is moved to <body> with appendChild() when the page loads.
That helps prevent fixed-position issues caused by transformed ancestors.

Because the modal is no longer nested inside this block after that move,
its CSS must target the modal by its own unique ID, not by the block wrapper ID.
======================================================================================== --}}
@php
  $uid        = $uid ?? ('sccc-feature-' . uniqid());
  $vehicle    = $vehicleData ?? null;
  $pos        = $mediaPos ?? 'left';
  $textAlign  = $textAlign ?? 'left';
  $ownerModal = $ownerModal ?? null;

  // Month + year in the site timezone.
  $monthYear = !empty($monthYear)
    ? (string) $monthYear
    : (function_exists('date_i18n') ? date_i18n('F Y') : date('F Y'));

  // Headline highlight style selector coming from Feature.php.
  $hs = $hs ?? ($headline_highlight ?? 'club-blue');

  // Resolve image URL robustly (supports ID, array, or raw URL string).
  $imageUrl = '';
  $resolvedId = 0;

  if (!empty($imageId)) {
    if (is_numeric($imageId)) {
      $resolvedId = (int) $imageId;
    } elseif (is_array($imageId)) {
      $resolvedId = (int) ($imageId['ID'] ?? $imageId['id'] ?? 0);
      if (!$resolvedId && !empty($imageId['url'])) {
        $imageUrl = (string) $imageId['url'];
      }
    } elseif (is_string($imageId) && preg_match('#^https?://#i', $imageId)) {
      $imageUrl = $imageId;
    }
  }

  if (!$imageUrl && $resolvedId) {
    $imageUrl = wp_get_attachment_image_url($resolvedId, 'full') ?: '';
  }

  $hasImage = !empty($imageUrl);

  // Resolve optional section background image from the new block field.
  $sectionBgImageUrl = '';
  $sectionBgResolvedId = 0;

  if (!empty($sectionBackgroundImage)) {
    if (is_numeric($sectionBackgroundImage)) {
      $sectionBgResolvedId = (int) $sectionBackgroundImage;
    } elseif (is_array($sectionBackgroundImage)) {
      $sectionBgResolvedId = (int) ($sectionBackgroundImage['ID'] ?? $sectionBackgroundImage['id'] ?? 0);
      if (!$sectionBgResolvedId && !empty($sectionBackgroundImage['url'])) {
        $sectionBgImageUrl = (string) $sectionBackgroundImage['url'];
      }
    } elseif (is_string($sectionBackgroundImage) && preg_match('#^https?://#i', $sectionBackgroundImage)) {
      $sectionBgImageUrl = $sectionBackgroundImage;
    }
  }

  if (!$sectionBgImageUrl && $sectionBgResolvedId) {
    $sectionBgImageUrl = wp_get_attachment_image_url($sectionBgResolvedId, 'full') ?: '';
  }

  $parallaxEnabled = !empty($enableParallax);

  // Section spacing + parallax speed from Feature.php.
  // These values are clamped again here so the Blade view stays safe even if
  // cached field data or older block data is missing/malformed.
  $sectionPaddingTop = is_numeric($sectionPaddingTop ?? null) ? (int) $sectionPaddingTop : 96;
  $sectionPaddingTop = max(0, min(240, $sectionPaddingTop));

  $sectionPaddingBottom = is_numeric($sectionPaddingBottom ?? null) ? (int) $sectionPaddingBottom : 96;
  $sectionPaddingBottom = max(0, min(240, $sectionPaddingBottom));

  $parallaxSpeed = is_numeric($parallaxSpeed ?? null) ? (float) $parallaxSpeed : 24.0;
  $parallaxSpeed = max(0.0, min(80.0, $parallaxSpeed));

  // Vehicle-specific display values.
  $vehNickname = '';
  if (!empty($vehicle) && !empty($vehicle['nickname'])) {
    $vehNickname = (string) $vehicle['nickname'];
  }

  $vehicleTitle = '';
  if (!empty($vehicle)) {
    $vehicleTitle = trim(
      ((string) ($vehicle['year'] ?? '')) . ' ' .
      ((string) ($vehicle['make_raw'] ?? '')) . ' ' .
      ((string) ($vehicle['model'] ?? ''))
    );
  }

  $memberName = '';
  if (!empty($member)) {
    if (is_array($member)) {
      $memberName = (string) ($member['name'] ?? $member['display_name'] ?? $member['user_login'] ?? '');
    } elseif (is_object($member)) {
      $memberName = (string) ($member->display_name ?? $member->name ?? $member->user_login ?? '');
    }
    $memberName = trim($memberName);
  }

  $ownerModalId = $uid . '-owner-modal';
  $ownerModalTitleId = $uid . '-owner-modal-title';

  /*
   * Editor / preview detection.
   *
   * Why this exists:
   * - This block is rendered inside Gutenberg through ACF's backend preview.
   * - The owner modal is a frontend interaction only.
   * - If the modal markup and JavaScript are allowed to mount in the editor,
   *   clicking the owner name can open the frontend-style modal while editing.
   *
   * Safe behavior:
   * - Frontend: render clickable owner trigger + modal.
   * - Editor/admin/REST/JSON preview: render the owner name as plain text and
   *   skip the modal markup/script entirely.
   */
  $isAcfPreview =
    (isset($is_preview) && (bool) $is_preview)
    || (isset($isPreview) && (bool) $isPreview)
    || (!empty($block) && is_object($block) && !empty($block->preview))
    || (!empty($block) && is_array($block) && !empty($block['preview']));

  $isJsonRequest = function_exists('wp_is_json_request') && wp_is_json_request();
  $isRestRequest = defined('REST_REQUEST') && REST_REQUEST;
  $isEditorLikeRequest = is_admin() || $isAcfPreview || $isJsonRequest || $isRestRequest;

  $shouldRenderOwnerModal = !empty($ownerModal) && !$isEditorLikeRequest;

  $textAlignStyle =
      $textAlign === 'center' ? 'text-align:center;'
    : ($textAlign === 'right' ? 'text-align:right;' : 'text-align:left;');

  $sectionStyleParts = [
    '--sccc-feature-section-padding-top: ' . $sectionPaddingTop . 'px',
    '--sccc-feature-section-padding-bottom: ' . $sectionPaddingBottom . 'px',
    '--sccc-feature-parallax-speed: ' . $parallaxSpeed,
  ];

  if (!empty($sectionBgImageUrl)) {
    $sectionStyleParts[] = "--sccc-feature-bg-image: url('" . esc_url($sectionBgImageUrl) . "')";
  }

  $sectionStyle = implode('; ', $sectionStyleParts) . ';';
@endphp

<section
  id="{{ $uid }}"
  {{ $attributes->merge(['class' => 'sccc-feature-block']) }}
  style="{{ $sectionStyle }}"
>
  <style>
    /* ============================
       Instance-scoped: Feature
       ============================ */

    #{{ $uid }}{
      --sccc-feature-shell-max: 80rem;
      --sccc-feature-section-padding-top: 96px;
      --sccc-feature-section-padding-bottom: 96px;
      --sccc-feature-parallax-speed: 24;
      --sccc-feature-bg-image: none;
      --sccc-gradient-club-blue: linear-gradient(90deg, #135bec 0%, #1f8fff 52%, #71d7ff 100%);
      --sccc-gradient-signal-red: linear-gradient(90deg, #b31324 0%, #ff1744 50%, #ff7a59 100%);
      --sccc-gradient-space-city: linear-gradient(90deg, #135bec 0%, #43beff 42%, #ae71ff 72%, #ff1744 100%);
      --sccc-color-club-blue: #135bec;
      --sccc-color-signal-red: #e53935;
      --sccc-color-deep-purple: #ae71ff;
      --feat-head-grad: var(--sccc-gradient-club-blue);
      position: relative;
      overflow: hidden;
      isolation: isolate;
      display:flex;
      align-items:center;
      justify-content:center;
      padding-top:var(--sccc-feature-section-padding-top);
      padding-bottom:var(--sccc-feature-section-padding-bottom);

      @if ($hs === 'signal-red')
        --feat-head-grad: var(--sccc-gradient-signal-red);
      @elseif ($hs === 'space-city')
        --feat-head-grad: var(--sccc-gradient-space-city);
      @elseif ($hs === 'club-blue-solid')
        --feat-head-solid: var(--sccc-color-club-blue);
      @elseif ($hs === 'signal-red-solid')
        --feat-head-solid: var(--sccc-color-signal-red);
      @elseif ($hs === 'deep-purple-solid')
        --feat-head-solid: var(--sccc-color-deep-purple);
      @else
        --feat-head-grad: var(--sccc-gradient-club-blue);
      @endif
    }

    /* ===== Section background layer ===== */
    #{{ $uid }} .sccc-feature__bg{
      position:absolute;
      inset:-24% 0;
      z-index:0;
      pointer-events:none;
      will-change:transform;
      transform:translate3d(0, 0, 0) scale(1.18);
      background-repeat:no-repeat;
      background-position:center center;
      background-size:cover;
      background-image: var(--sccc-feature-bg-image);
    }

    /* Single subtle overlay only */
    #{{ $uid }} .sccc-feature__bg::after{
      content:"";
      position:absolute;
      inset:0;
      pointer-events:none;
      background:
        linear-gradient(
          180deg,
          rgba(3, 8, 18, .65) 0%,
          rgba(3, 8, 18, .75) 42%,
          rgba(3, 8, 18, .10) 100%
        );
    }

    html[data-theme="light"] #{{ $uid }} .sccc-feature__bg::after{
      background:
        linear-gradient(
          180deg,
          rgba(255,255,255,.75) 0%,
          rgba(255,255,255,.75) 42%,
          rgba(255,255,255,.05) 100%
        );
    }

    #{{ $uid }} .sccc-feature__wrap{
      width:100%;
      min-height:100%;
      margin:0;
      padding:0;
      max-width:none;
      position:relative;
      z-index:1;
      display:flex;
      align-items:center;
      justify-content:center;
    }

    #{{ $uid }} .sccc-feature__container{
      width:100%;
      max-width:var(--sccc-feature-shell-max);
      margin-inline:auto;
      position:relative;
      z-index:1;
    }

    /* ===== Header above card ===== */
    #{{ $uid }} .sccc-feature__head{
      width:100%;
      margin:0 0 1.25rem;
      display:flex;
      flex-direction:column;
      gap:.35rem;
      max-width:var(--sccc-feature-shell-max);
    }

    #{{ $uid }} .sccc-feature__tag{
      font-family: var(--font-headline, inherit);
      font-size:.75rem;
      font-weight:700;
      letter-spacing:.12em;
      text-transform:uppercase;
      color: var(--color-primary-500, var(--primary));
      margin:0;
      text-shadow: 0 4px 14px rgba(0,0,0,.28);
    }

    #{{ $uid }} .sccc-feature__headline{
      font-family: var(--font-headline, inherit);
      font-weight:800;
      line-height:1.1;
      font-size: clamp(1.6rem, 3.2vw, 2.6rem);
      margin:0;
      color: var(--color-text, var(--text, currentColor));
      text-shadow:
        0 10px 24px rgba(0,0,0,.34),
        0 2px 6px rgba(0,0,0,.20);
    }

    #{{ $uid }} [data-sccc-highlight],
    #{{ $uid }} .sccc-text-gradient{
      background-image: var(--feat-head-grad);
      -webkit-background-clip:text;
      background-clip:text;
      color:transparent;
      -webkit-text-fill-color:transparent;
      white-space:nowrap;
    }

    #{{ $uid }} [data-sccc-highlight] strong,
    #{{ $uid }} .sccc-text-gradient strong{
      font-weight:900;
    }

    #{{ $uid }} .sccc-text-gradient--club-blue{
      background-image: var(--sccc-gradient-club-blue);
    }

    #{{ $uid }} .sccc-text-gradient--signal-red{
      background-image: var(--sccc-gradient-signal-red);
    }

    #{{ $uid }} .sccc-text-gradient--space-city{
      background-image: var(--sccc-gradient-space-city);
    }

    #{{ $uid }} .sccc-text-gradient--club-blue-solid,
    #{{ $uid }} .sccc-text-gradient--signal-red-solid,
    #{{ $uid }} .sccc-text-gradient--deep-purple-solid{
      background:none;
      -webkit-background-clip:initial;
      background-clip:initial;
      color:var(--feat-head-solid);
      -webkit-text-fill-color:currentColor;
    }

    #{{ $uid }} .sccc-text-gradient--club-blue-solid{
      --feat-head-solid: var(--sccc-color-club-blue);
    }

    #{{ $uid }} .sccc-text-gradient--signal-red-solid{
      --feat-head-solid: var(--sccc-color-signal-red);
    }

    #{{ $uid }} .sccc-text-gradient--deep-purple-solid{
      --feat-head-solid: var(--sccc-color-deep-purple);
    }

    /* ===== Card layout ===== */
    #{{ $uid }} .sccc-feature__card{
      width:100%;
      margin-inline:auto;
      border-radius:1.25rem;
      overflow:hidden;
      position:relative;
      border:1px solid rgba(100, 152, 255, .16);
      background: color-mix(in srgb, var(--color-surface, var(--surface, #fff)) 12%, transparent);
      box-shadow:
        0 28px 72px rgba(0,0,0,.20),
        0 0 0 1px rgba(255,255,255,.03);
    }

    #{{ $uid }} .sccc-feature__grid{
      display:grid;
      grid-template-columns:1fr;
      position:relative;
      z-index:1;
    }

    @media (min-width: 1024px){
      #{{ $uid }} .sccc-feature__grid{
        grid-template-columns: 7fr 5fr;
        min-height: 420px;
      }

      #{{ $uid }} .sccc-feature__card.is-right .sccc-feature__media{ order:2; }
      #{{ $uid }} .sccc-feature__card.is-right .sccc-feature__body{ order:1; }
    }

    @media (max-width: 1023.98px){
      #{{ $uid }} .sccc-feature__grid{
        grid-template-columns:1fr;
      }

      #{{ $uid }} .sccc-feature__meta-row{
        flex-wrap:wrap;
        align-items:flex-start;
      }

      #{{ $uid }} .sccc-feature__date{
        margin-left:auto;
      }
    }

    /* ===== Media ===== */
    #{{ $uid }} .sccc-feature__media{
      position:relative;
      min-height:280px;
      background-size:cover;
      background-position:center;
      background-repeat:no-repeat;
    }

    @media (min-width: 1024px){
      #{{ $uid }} .sccc-feature__media{ min-height:100%; }
    }

    @media (max-width: 767.98px){
      #{{ $uid }} .sccc-feature__media{
        min-height:240px;
      }
    }

    #{{ $uid }} .sccc-feature__media::after{
      content:"";
      position:absolute;
      inset:0;
      background: rgba(0,0,0,.08);
      pointer-events:none;
    }

    /* ===== Text column ===== */
    #{{ $uid }} .sccc-feature__body{
      display:flex;
      align-items:center;
      padding: clamp(1.25rem, 3vw, 2.5rem);
      position:relative;
      overflow:hidden;
    }

    /* Neon reflective glass sheet */
    html.dark[data-theme="dark"] #{{ $uid }} .sccc-feature__body{
      background:
        linear-gradient(
          180deg,
          rgba(3, 23, 62, .42) 0%,
          rgba(2, 18, 50, .54) 100%
        );
      border-left:1px solid rgba(106, 200, 255, .38);
      backdrop-filter: blur(30px) saturate(185%);
      -webkit-backdrop-filter: blur(30px) saturate(185%);
      box-shadow:
        inset 1px 0 0 rgba(255,255,255,.12),
        inset 0 1px 0 rgba(255,255,255,.08),
        inset 0 0 46px rgba(58, 150, 255, .08),
        -18px 0 40px rgba(0, 128, 255, .08);
    }

    html[data-theme="light"] #{{ $uid }} .sccc-feature__body{
      background:
        linear-gradient(
          180deg,
          rgba(255,255,255,.48) 0%,
          rgba(248,251,255,.58) 100%
        );
      border-left:1px solid rgba(84, 158, 255, .22);
      backdrop-filter: blur(24px) saturate(168%);
      -webkit-backdrop-filter: blur(24px) saturate(168%);
      box-shadow:
        inset 1px 0 0 rgba(255,255,255,.72),
        inset 0 1px 0 rgba(255,255,255,.46),
        inset 0 0 38px rgba(84, 158, 255, .06),
        -12px 0 28px rgba(84, 158, 255, .06);
    }

    /* reflective sheen */
    #{{ $uid }} .sccc-feature__body::before{
      content:"";
      position:absolute;
      inset:0;
      pointer-events:none;
      background:
        linear-gradient(
          135deg,
          rgba(255,255,255,.24) 0%,
          rgba(255,255,255,.10) 14%,
          rgba(255,255,255,0) 32%
        ),
        radial-gradient(
          26rem 14rem at 8% 8%,
          rgba(120, 210, 255, .12) 0%,
          rgba(120, 210, 255, 0) 62%
        );
      mix-blend-mode:screen;
      opacity:.82;
    }

    /* neon border / glow treatment */
    #{{ $uid }} .sccc-feature__body::after{
      content:"";
      position:absolute;
      inset:0;
      pointer-events:none;
      border-left:1px solid rgba(132, 220, 255, .48);
      box-shadow:
        inset 0 0 0 1px rgba(255,255,255,.04),
        inset 0 0 40px rgba(60, 155, 255, .08),
        -1px 0 0 rgba(120, 210, 255, .22),
        -10px 0 22px rgba(60, 155, 255, .10);
    }

    #{{ $uid }} .sccc-feature__body-inner{
      width:100%;
      position:relative;
      z-index:1;
    }

    #{{ $uid }} .sccc-feature__meta-row{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:.75rem;
      margin:0 0 1rem;
    }

    /* ===== Vehicle nickname pill ===== */
    #{{ $uid }} .sccc-feature__pill{
      display:inline-flex;
      align-items:center;
      padding:.44rem .9rem;
      border-radius:999px;
      font-size:.78rem;
      font-weight:900;
      letter-spacing:.11em;
      text-transform:uppercase;
      white-space:nowrap;
      backdrop-filter: blur(12px) saturate(170%);
      -webkit-backdrop-filter: blur(12px) saturate(170%);
      transition:
        transform .18s ease,
        box-shadow .18s ease,
        border-color .18s ease,
        background .18s ease,
        color .18s ease;
    }

    #{{ $uid }} .sccc-feature__pill:hover{
      transform: translateY(-1px);
    }

    html.dark[data-theme="dark"] #{{ $uid }} .sccc-feature__pill{
      color: color-mix(in srgb, white 84%, var(--color-primary-500, var(--primary)) 16%);
      background:
        linear-gradient(
          180deg,
          color-mix(in srgb, var(--color-primary-500, var(--primary)) 26%, rgba(255,255,255,.10)) 0%,
          color-mix(in srgb, var(--color-primary-500, var(--primary)) 12%, rgba(8,15,28,.42)) 100%
        );
      border:1px solid color-mix(
        in srgb,
        var(--color-primary-500, var(--primary)) 52%,
        rgba(255,255,255,.22)
      );
      box-shadow:
        inset 0 1px 0 rgba(255,255,255,.16),
        0 10px 24px color-mix(in srgb, var(--color-primary-500, var(--primary)) 20%, transparent),
        0 0 22px color-mix(in srgb, var(--color-primary-500, var(--primary)) 18%, transparent);
      text-shadow: 0 0 12px color-mix(in srgb, var(--color-primary-500, var(--primary)) 26%, transparent);
    }

    html[data-theme="light"] #{{ $uid }} .sccc-feature__pill{
      color: color-mix(in srgb, var(--color-primary-500, var(--primary)) 78%, #0f172a 22%);
      background:
        linear-gradient(
          180deg,
          rgba(255,255,255,.92) 0%,
          color-mix(in srgb, white 82%, var(--color-primary-500, var(--primary)) 18%) 100%
        );
      border:1px solid color-mix(
        in srgb,
        var(--color-primary-500, var(--primary)) 34%,
        rgba(15,23,42,.12)
      );
      box-shadow:
        inset 0 1px 0 rgba(255,255,255,.86),
        0 8px 18px rgba(15,23,42,.08),
        0 0 0 1px rgba(255,255,255,.34);
      text-shadow:none;
    }

    #{{ $uid }} .sccc-feature__date{
      font-size:.95rem;
      color: var(--color-muted, var(--muted, rgba(0,0,0,.6)));
      white-space:nowrap;
      text-shadow: 0 6px 18px rgba(0,0,0,.18);
    }

    #{{ $uid }} .sccc-feature__title{
      font-family: var(--font-headline, inherit);
      font-weight:900;
      line-height:1.15;
      font-size: clamp(1.35rem, 2.2vw, 2rem);
      margin:0 0 .5rem;
      color: var(--color-text, var(--text, currentColor));
      text-shadow: 0 10px 24px rgba(0,0,0,.18);
    }

    #{{ $uid }} .sccc-feature__owner{
      margin:0 0 1.25rem;
      color: var(--color-muted, var(--muted, rgba(0,0,0,.65)));
      font-size:1.05rem;
      text-shadow: 0 6px 16px rgba(0,0,0,.16);
    }

    #{{ $uid }} .sccc-feature__owner-label{
      font-weight:800;
      color: currentColor;
    }

    html.dark[data-theme="dark"] #{{ $uid }} .sccc-feature__owner-label{
      color: color-mix(in srgb, var(--color-text, var(--text, #fff)) 82%, white 18%);
    }

    html[data-theme="light"] #{{ $uid }} .sccc-feature__owner-label{
      color: currentColor;
    }

    #{{ $uid }} .sccc-feature__owner-trigger{
      appearance:none;
      background:none;
      border:0;
      padding:0;
      margin:0;
      color: var(--color-primary-500, var(--primary));
      font-weight:800;
      cursor:pointer;
      text-decoration:none;
      border-bottom:1px solid transparent;
      transition: border-color .15s ease, opacity .15s ease;
    }

    #{{ $uid }} .sccc-feature__owner-trigger:hover,
    #{{ $uid }} .sccc-feature__owner-trigger:focus-visible{
      border-bottom-color: currentColor;
      outline:none;
      opacity:.92;
    }

    #{{ $uid }} .sccc-feature__subhead{
      margin:0 0 .85rem;
      line-height:1.6;
      color: var(--color-muted, var(--muted, rgba(0,0,0,.7)));
      text-shadow: 0 6px 16px rgba(0,0,0,.14);
    }

    #{{ $uid }} .sccc-feature__prose{
      line-height:1.7;
      color: var(--color-text, var(--text, currentColor));
      text-shadow: 0 4px 12px rgba(0,0,0,.12);
    }

    #{{ $uid }} .sccc-feature__prose :where(p){ margin:0 0 1rem; }
    #{{ $uid }} .sccc-feature__prose :where(p:last-child){ margin-bottom:0; }

    /* ===== CTA ===== */
    #{{ $uid }} .sccc-feature__cta{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      margin-top:1.25rem;
      padding:.75rem 1.05rem;
      border-radius:.75rem;
      font-weight:800;
      text-decoration:none;
      background: var(--color-primary-500, var(--primary));
      color:#fff;
      transition: transform .15s ease, filter .15s ease;
      box-shadow:
        0 8px 22px rgba(0,0,0,.18),
        0 0 18px color-mix(in srgb, var(--color-primary-500, var(--primary)) 16%, transparent);
    }

    #{{ $uid }} .sccc-feature__cta:hover,
    #{{ $uid }} .sccc-feature__cta:focus-visible{
      transform: translateY(-1px);
      filter: brightness(1.05);
    }

    /* =====================================================================
       Owner modal
       IMPORTANT:
       These selectors target the modal by its own unique ID because the modal
       is moved to <body> after page load.
       ===================================================================== */
    #{{ $ownerModalId }}[hidden]{
      display:none !important;
    }

    #{{ $ownerModalId }}{
      position:fixed !important;
      inset:0 !important;
      z-index:9999;
      display:grid;
      place-items:center;
      width:100vw;
      min-height:100dvh;
      padding:1.25rem;
      box-sizing:border-box;
      isolation:isolate;
    }

    #{{ $ownerModalId }} .sccc-feature__modal-backdrop{
      position:absolute;
      inset:0;
      background: rgba(8, 15, 28, .68);
      backdrop-filter: blur(4px);
    }

    #{{ $ownerModalId }} .sccc-feature__modal-panel{
      position:relative;
      z-index:1;
      width:min(100%, 38rem);
      max-height:calc(100dvh - 2.5rem);
      margin:auto;
      border-radius:1.25rem;
      border:1px solid color-mix(in srgb, var(--color-line, var(--line, #000)) 35%, transparent);
      background: var(--color-surface, var(--surface, #fff));
      color: var(--color-text, var(--text, currentColor));
      box-shadow: 0 24px 80px rgba(0,0,0,.30);
      overflow:auto;
    }

    #{{ $ownerModalId }} .sccc-feature__modal-inner{
      padding: clamp(1.25rem, 3vw, 2rem);
    }

    #{{ $ownerModalId }} .sccc-feature__modal-close{
      position:absolute;
      top:.85rem;
      right:.85rem;
      width:2.25rem;
      height:2.25rem;
      border:0;
      border-radius:999px;
      cursor:pointer;
      font-size:1.1rem;
      line-height:1;
      background: color-mix(in srgb, var(--color-line, var(--line, #000)) 12%, transparent);
      color: var(--color-text, var(--text, currentColor));
    }

    #{{ $ownerModalId }} .sccc-feature__modal-head{
      display:flex;
      align-items:center;
      gap:1rem;
      margin:0 0 1.25rem;
      padding-right:2.75rem;
    }

    #{{ $ownerModalId }} .sccc-feature__modal-avatar-wrap{
      flex:0 0 auto;
    }

    #{{ $ownerModalId }} .sccc-feature__modal-avatar{
      display:block;
      width:5.5rem;
      height:5.5rem;
      object-fit:cover;
      border-radius:999px;
      border:1px solid color-mix(in srgb, var(--color-line, var(--line, #000)) 25%, transparent);
      background: color-mix(in srgb, var(--color-line, var(--line, #000)) 8%, transparent);
    }

    #{{ $ownerModalId }} .sccc-feature__modal-kicker{
      margin:0 0 .2rem;
      font-size:.78rem;
      font-weight:800;
      letter-spacing:.1em;
      text-transform:uppercase;
      color: var(--color-primary-500, var(--primary));
    }

    #{{ $ownerModalId }} .sccc-feature__modal-title{
      margin:0 0 .25rem;
      font-family: var(--font-headline, inherit);
      font-size: clamp(1.2rem, 2vw, 1.7rem);
      font-weight:800;
      line-height:1.15;
    }

    #{{ $ownerModalId }} .sccc-feature__modal-username{
      margin:0;
      color: var(--color-muted, var(--muted, rgba(0,0,0,.65)));
      font-size:.98rem;
    }

    #{{ $ownerModalId }} .sccc-feature__modal-meta{
      margin:.35rem 0 0;
      color: var(--color-muted, var(--muted, rgba(0,0,0,.65)));
      font-size:.98rem;
    }

    #{{ $ownerModalId }} .sccc-feature__modal-bio{
      line-height:1.7;
      color: var(--color-text, var(--text, currentColor));
    }

    #{{ $ownerModalId }} .sccc-feature__modal-bio :where(p){
      margin:0 0 1rem;
    }

    #{{ $ownerModalId }} .sccc-feature__modal-bio :where(p:last-child){
      margin-bottom:0;
    }

    #{{ $ownerModalId }} .sccc-feature__modal-empty{
      color: var(--color-muted, var(--muted, rgba(0,0,0,.65)));
      font-style:italic;
    }

    /* ===== Editor-only notes panel ===== */
    #{{ $uid }} .sccc-feature__editor-notes{
      width:100%;
      max-width:100%;
      margin:1.25rem 0 0;
    }

    #{{ $uid }} .sccc-feature__editor-notes-label{
      font-weight:800;
      margin:0 0 .5rem;
    }

    #{{ $uid }} .sccc-feature__editor-notes-textarea{
      width:100%;
      max-width:100%;
      box-sizing:border-box;
      resize:vertical;
      min-height:7.5rem;
      padding:.85rem 1rem;
      border-radius:.75rem;
      border:1px dashed rgba(0,0,0,.25);
      background: rgba(0,0,0,.03);
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      font-size:.9rem;
      line-height:1.35;
    }
  </style>

  <div
    class="sccc-feature__bg"
    data-feature-parallax-bg
    aria-hidden="true"
  ></div>

  <div class="sccc-feature__wrap">
    <div class="sccc-feature__container mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">

      @if(!empty($headlineHtml))
        <header class="sccc-feature__head">
          <p class="sccc-feature__tag">Monthly Feature</p>
          <h2 class="sccc-feature__headline">{!! $headlineHtml !!}</h2>
        </header>
      @endif

      <div class="sccc-feature__card {{ $pos === 'right' ? 'is-right' : '' }}">
      <div class="sccc-feature__grid">

        @if($hasImage)
          <div class="sccc-feature__media"
               style="background-image:url('{{ esc_url($imageUrl) }}');"
               aria-hidden="true"></div>
        @endif

        <div class="sccc-feature__body" style="{{ $textAlignStyle }}">
          <div class="sccc-feature__body-inner">

            <div class="sccc-feature__meta-row">
              <div>
                @if($vehNickname)
                  <span class="sccc-feature__pill">{{ $vehNickname }}</span>
                @endif
              </div>
              <div class="sccc-feature__date">{{ $monthYear }}</div>
            </div>

            @if($vehicleTitle)
              <h3 class="sccc-feature__title">{{ $vehicleTitle }}</h3>
            @endif

            @if(!empty($memberName))
              <p class="sccc-feature__owner">
                <span class="sccc-feature__owner-label">Owner:</span>
                @if($shouldRenderOwnerModal)
                  <button type="button"
                          class="sccc-feature__owner-trigger"
                          data-feature-owner-trigger
                          aria-haspopup="dialog"
                          aria-controls="{{ $ownerModalId }}"
                          aria-expanded="false">
                    {{ $memberName }}
                  </button>
                @else
                  <strong>{{ $memberName }}</strong>
                @endif
              </p>
            @endif

            @if(!empty($subhead))
              <p class="sccc-feature__subhead">{!! nl2br(e($subhead)) !!}</p>
            @endif

            @if(!empty($body))
              <div class="sccc-feature__prose">
                {!! $body !!}
              </div>
            @endif

            @if(!empty($cta) && !empty($cta['url']))
              <a class="sccc-feature__cta"
                 href="{{ esc_url($cta['url']) }}"
                 target="{{ esc_attr($cta['target'] ?? '_self') }}">
                {{ esc_html($cta['title'] ?? 'Learn more') }}
              </a>
            @endif

          </div>
        </div>
      </div>
    </div>

    @if($shouldRenderOwnerModal)
      <div class="sccc-feature__modal" id="{{ $ownerModalId }}" hidden>
        <div class="sccc-feature__modal-backdrop" data-feature-modal-close></div>

        <div class="sccc-feature__modal-panel"
             role="dialog"
             aria-modal="true"
             aria-labelledby="{{ $ownerModalTitleId }}">
          <button type="button"
                  class="sccc-feature__modal-close"
                  data-feature-modal-close
                  aria-label="Close owner profile modal">
            ×
          </button>

          <div class="sccc-feature__modal-inner">
            <div class="sccc-feature__modal-head">
              @if(!empty($ownerModal['profile_image']))
                <div class="sccc-feature__modal-avatar-wrap">
                  <img class="sccc-feature__modal-avatar"
                       src="{{ esc_url($ownerModal['profile_image']) }}"
                       alt="{{ esc_attr(($ownerModal['display_name'] ?? 'Member') . ' profile photo') }}">
                </div>
              @endif

              <div class="sccc-feature__modal-copy">
                <p class="sccc-feature__modal-kicker">Member Profile</p>
                <h4 class="sccc-feature__modal-title" id="{{ $ownerModalTitleId }}">
                  {{ $ownerModal['display_name'] ?? $memberName }}
                </h4>

                @if(!empty($ownerModal['username']))
                  <p class="sccc-feature__modal-username">
                    <strong>Username:</strong> {{ $ownerModal['username'] }}
                  </p>
                @endif

                @if(!empty($ownerModal['member_since_year']))
                  <p class="sccc-feature__modal-meta">
                    <strong>Member since:</strong> {{ $ownerModal['member_since_year'] }}
                  </p>
                @endif
              </div>
            </div>

            @if(!empty($ownerModal['bio']))
              <div class="sccc-feature__modal-bio">
                {!! wpautop(e($ownerModal['bio'])) !!}
              </div>
            @else
              <p class="sccc-feature__modal-empty">No member bio has been added yet.</p>
            @endif
          </div>
        </div>
      </div>

      <script>
        (() => {
          const scope = document.getElementById(@json($uid));
          if (!scope) return;

          const trigger = scope.querySelector('[data-feature-owner-trigger]');
          const modal = scope.querySelector('#' + @json($ownerModalId));
          if (!trigger || !modal) return;

          if (!modal.dataset.featureMounted) {
            document.body.appendChild(modal);
            modal.dataset.featureMounted = 'true';
          }

          const closeButtons = modal.querySelectorAll('[data-feature-modal-close]');
          const closeButton = modal.querySelector('.sccc-feature__modal-close');
          let lastFocused = null;

          const openModal = () => {
            lastFocused = document.activeElement;
            modal.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            document.documentElement.style.overflow = 'hidden';
            document.body.style.overflow = 'hidden';

            if (closeButton) {
              closeButton.focus();
            }
          };

          const closeModal = () => {
            modal.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            document.documentElement.style.overflow = '';
            document.body.style.overflow = '';

            if (lastFocused && typeof lastFocused.focus === 'function') {
              lastFocused.focus();
            } else {
              trigger.focus();
            }
          };

          trigger.addEventListener('click', openModal);

          closeButtons.forEach((button) => {
            button.addEventListener('click', closeModal);
          });

          document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) {
              closeModal();
            }
          });
        })();
        </script>
      @endif

      @if($parallaxEnabled && !empty($sectionBgImageUrl))
        <script>
        (() => {
          const scope = document.getElementById(@json($uid));
          if (!scope) return;

          const bg = scope.querySelector('[data-feature-parallax-bg]');
          if (!bg) return;

          const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
          if (prefersReduced) return;

          const speed = Number.parseFloat(scope.style.getPropertyValue('--sccc-feature-parallax-speed')) || 24;
          const fallbackTravel = speed * 7.5;

          if (window.gsap && window.ScrollTrigger) {
            window.gsap.registerPlugin(window.ScrollTrigger);

            window.gsap.to(bg, {
              yPercent: speed,
              ease: 'none',
              scrollTrigger: {
                trigger: scope,
                start: 'top bottom',
                end: 'bottom top',
                scrub: true,
              },
            });

            return;
          }

          let ticking = false;

          const update = () => {
            const rect = scope.getBoundingClientRect();
            const viewportHeight = window.innerHeight || document.documentElement.clientHeight;

            const progress = (viewportHeight - rect.top) / (viewportHeight + rect.height);
            const clamped = Math.max(0, Math.min(1, progress));
            const y = (clamped - 0.5) * fallbackTravel;

            bg.style.transform = `translate3d(0, ${y}px, 0) scale(1.18)`;
            ticking = false;
          };

          const requestUpdate = () => {
            if (!ticking) {
              window.requestAnimationFrame(update);
              ticking = true;
            }
          };

          update();
          window.addEventListener('scroll', requestUpdate, { passive: true });
          window.addEventListener('resize', requestUpdate);
        })();
      </script>
      @endif
    </div>
  </div>

  @if (!empty($block) && $block->preview && !empty($vehicleData['notes']))
    <div class="sccc-feature__editor-notes">
      <div class="sccc-feature__editor-notes-label">Vehicle Notes (read-only)</div>
      <textarea class="sccc-feature__editor-notes-textarea" readonly>{{ trim($vehicleData['notes']) }}</textarea>
    </div>
  @endif

</section>