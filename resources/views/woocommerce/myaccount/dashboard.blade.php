{{--
  resources/views/woocommerce/myaccount/dashboard.blade.php
  File: resources/views/woocommerce/myaccount/dashboard.blade.php

  Purpose
  ------------------------------------------------------------------------------
  Dashboard landing page styled like your comp.

  IMPORTANT
  ------------------------------------------------------------------------------
  - DO NOT change existing layout/sections/styling outside the targeted member
    profile display updates.
  - Garage CRUD (ADD/EDIT/DELETE) happens via modal UI in this Blade file.
  - Server-side garage handling stays in:
      app/Support/Members/Garage.php

  Separation
  ------------------------------------------------------------------------------
  Data comes from WooMyAccountComposer where available. This file remains mostly
  presentation-only. The actual garage save happens in the Garage handler.

  PATCH: Member phone + directory visibility
  ------------------------------------------------------------------------------
  - Reads sccc_member_phone from the current user.
  - Reads sccc_hide_in_member_directory from the current user.
  - Displays phone under Personal Information.
  - Strips non-digits from the saved phone value and formats clear US numbers
    before display.
  - Displays whether the member is visible or hidden in the future Member
    Directory.
  - Phone is displayed in the private member dashboard only. It should not be
    shown in the future Member Directory unless we add a separate explicit
    phone-display opt-in later.

  PATCH: Member website + social links
  ------------------------------------------------------------------------------
  - Reads sccc_member_website and sccc_member_social_links from the current user.
  - Social links render as icon + @username frontend links.
  - Allows multiple accounts per platform because each row is identified by
    username.
  - Allowed social platforms: Facebook, Instagram, X, TikTok, YouTube.
--}}

<style>
  /* (UNCHANGED CSS — keeping your current working styles) */
  .sccc-mdash{ color: rgba(10,12,24,.92); }

  .sccc-row{
    display:grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap:14px;
    margin-bottom:22px;
  }
  @media (max-width: 900px){
    .sccc-row{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }

  .sccc-card{
    background: rgba(255,255,255,.85);
    border:1px solid rgba(0,0,0,.08);
    border-radius:16px;
    padding:16px;
    box-shadow: 0 12px 30px rgba(0,0,0,.06);
  }

  .sccc-card--bright{
    background: linear-gradient(135deg, rgba(19,91,236,.95), rgba(59,130,246,.80));
    border:none;
    color:#fff;
    position:relative;
    overflow:hidden;
    box-shadow: 0 14px 34px rgba(19,91,236,.20);
  }

  .sccc-card__k{
    font-size:13px;
    opacity:.70;
    margin-bottom:6px;
    font-weight:800;
  }

  .sccc-card__v{
    font-size:26px;
    font-weight:900;
    letter-spacing:-.02em;
  }

  .sccc-card__v--rank{ color: rgba(19,91,236,.95); }

  .sccc-section{ margin-top:22px; }

  .sccc-section__head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:12px;
  }

  .sccc-section__title{
    font-size:22px;
    font-weight:900;
    letter-spacing:-.02em;
  }

  .sccc-link{
    color: rgba(19,91,236,.95);
    text-decoration:none;
    font-weight:900;
    font-size:13px;
  }
  .sccc-link:hover{ text-decoration:underline; }

  .sccc-panel{
    background: rgba(255,255,255,.85);
    border:1px solid rgba(0,0,0,.08);
    border-radius:18px;
    padding:18px;
    box-shadow: 0 12px 30px rgba(0,0,0,.06);
  }

  .sccc-form{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap:14px;
  }
  @media (max-width: 900px){
    .sccc-form{ grid-template-columns:1fr; }
  }

  .sccc-field label{
    display:block;
    font-size:13px;
    opacity:.70;
    margin-bottom:8px;
    font-weight:900;
  }

  .sccc-input{
    width:100%;
    padding:12px 12px;
    border-radius:12px;
    background: rgba(0,0,0,.02);
    border:1px solid rgba(0,0,0,.10);
    color: rgba(10,12,24,.92);
  }

  .sccc-toggle{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:12px 12px;
    border-radius:12px;
    background: rgba(0,0,0,.02);
    border:1px solid rgba(0,0,0,.10);
  }

  .sccc-toggle small{
    display:block;
    opacity:.65;
    font-weight:800;
    margin-top:2px;
  }

  /* --------------------------------------------------------------------------
     Member website + social links
     -------------------------------------------------------------------------- */
  .sccc-social-panel{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:10px;
    padding:12px;
    border-radius:12px;
    background: rgba(0,0,0,.02);
    border:1px solid rgba(0,0,0,.10);
  }

  .sccc-social-website{
    display:inline-flex;
    align-items:center;
    gap:8px;
    min-height:38px;
    padding:8px 12px;
    border-radius:999px;
    background: rgba(19,91,236,.10);
    border:1px solid rgba(19,91,236,.18);
    color: rgba(19,91,236,.95);
    font-size:13px;
    font-weight:900;
    line-height:1;
    text-decoration:none;
  }

  .sccc-social-website:hover{
    background: rgba(19,91,236,.14);
    text-decoration:none;
  }

  .sccc-social-icons{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:8px;
  }

  .sccc-social-icon{
    min-height:38px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    border-radius:999px;
    padding:8px 11px;
    background: rgba(255,255,255,.85);
    border:1px solid rgba(0,0,0,.10);
    color: rgba(10,12,24,.88);
    font-size:13px;
    font-weight:900;
    line-height:1;
    text-decoration:none;
    transition: transform .16s ease, background .16s ease, color .16s ease, box-shadow .16s ease;
  }

  .sccc-social-icon:hover{
    transform: translateY(-1px);
    background: rgba(19,91,236,.95);
    color:#fff;
    box-shadow: 0 12px 26px rgba(19,91,236,.20);
    text-decoration:none;
  }

  .sccc-social-icon svg{
    width:16px;
    height:16px;
    display:block;
    flex:0 0 auto;
    fill: currentColor;
  }

  .sccc-social-icon__handle{
    display:inline-block;
    max-width:150px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
  }

  .sccc-switch{
    width:46px;
    height:26px;
    border-radius:999px;
    background:rgba(0,0,0,.10);
    position:relative;
    flex:0 0 auto;
  }
  .sccc-switch::after{
    content:"";
    width:22px;
    height:22px;
    border-radius:999px;
    background:#fff;
    position:absolute;
    top:2px;
    left:2px;
    transition:transform .2s ease;
    box-shadow: 0 8px 18px rgba(0,0,0,.12);
  }
  .sccc-switch.is-on{ background: rgba(19,91,236,.95); }
  .sccc-switch.is-on::after{ transform: translateX(20px); }

  .sccc-garage-grid{
    display:grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap:14px;
  }
  @media (max-width: 1200px){
    .sccc-garage-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 900px){
    .sccc-garage-grid{ grid-template-columns: 1fr; }
  }

  .sccc-car{
    background: rgba(255,255,255,.85);
    border:1px solid rgba(0,0,0,.08);
    border-radius:18px;
    overflow:hidden;
    box-shadow: 0 12px 30px rgba(0,0,0,.06);
    position:relative;
  }

  .sccc-car__img{
    height:180px;
    background:#eaeaea;
    background-size:cover !important;
    background-position:center !important;
  }

  .sccc-car__body{ padding:14px; }

  .sccc-car__title{
    font-weight:900;
    font-size:18px;
    letter-spacing:-.02em;
    margin-top:4px;
  }

  .sccc-car__meta{
    opacity:.75;
    font-size:13px;
    margin-top:6px;
    line-height:1.35;
  }

  .sccc-addcar{
    border:2px dashed rgba(0,0,0,.16);
    border-radius:18px;
    min-height:270px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:10px;
    background: rgba(0,0,0,.02);
    text-decoration:none;
    color: inherit;
    cursor:pointer;
  }

  .sccc-addcar__btn{
    width:58px;
    height:58px;
    border-radius:999px;
    background: rgba(255,255,255,.95);
    border:1px solid rgba(0,0,0,.10);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:26px;
    color: rgba(19,91,236,.95);
  }

  .sccc-addcar__t{ font-weight:900; }
  .sccc-addcar__d{
    opacity:.70;
    font-size:13px;
    text-align:center;
    max-width:220px;
  }

  /* --------------------------------------------------------------------------
     Garage-only: card actions (Edit / Remove)
     -------------------------------------------------------------------------- */
  .sccc-car__actions{
    display:flex;
    gap:10px;
    margin-top:12px;
  }

  .sccc-car__action{
    border-radius:12px;
    padding:9px 10px;
    font-weight:900;
    border:1px solid rgba(0,0,0,.10);
    background: rgba(0,0,0,.03);
    cursor:pointer;
    font-size:12px;
    line-height:1;
  }

  .sccc-car__action--primary{
    background: rgba(19,91,236,.95);
    border-color: rgba(19,91,236,.95);
    color:#fff;
  }

  .sccc-car__action--danger{
    border-color: rgba(220,38,38,.25);
    background: rgba(220,38,38,.06);
    color: rgba(170,24,24,.95);
  }

  /* --------------------------------------------------------------------------
     Garage-only: Modal (Add + Edit share the same modal shell)
     -------------------------------------------------------------------------- */
  .sccc-modal{
    position:fixed;
    inset:0;
    z-index:9999;
    display:none;
    padding: clamp(16px, 4vw, 32px);
    overflow-y:auto;
  }
  .sccc-modal[aria-hidden="false"]{
    display:grid;
    place-items:center;
  }

  .sccc-modal__backdrop{
    position:absolute;
    inset:0;
    background: rgba(0,0,0,.55);
  }

  .sccc-modal__panel{
    position:relative;
    width:min(760px, calc(100vw - 32px));
    max-height:calc(100dvh - 32px);
    margin:0;
    background: rgba(255,255,255,.96);
    border: 1px solid rgba(0,0,0,.10);
    border-radius: 18px;
    box-shadow: 0 30px 90px rgba(0,0,0,.25);
    overflow:auto;
  }

  .sccc-modal__head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:14px 16px;
    border-bottom:1px solid rgba(0,0,0,.08);
  }

  .sccc-modal__title{
    font-weight:900;
    font-size:16px;
    letter-spacing:-.01em;
  }

  .sccc-modal__close{
    border:1px solid rgba(0,0,0,.12);
    background: rgba(0,0,0,.03);
    border-radius:10px;
    padding:8px 10px;
    font-weight:900;
    font-size:12px;
    cursor:pointer;
  }

  .sccc-modal__body{
    padding:16px;
  }

  .sccc-modal__footer{
    display:flex;
    gap:10px;
    justify-content:flex-end;
    padding:14px 16px;
    border-top:1px solid rgba(0,0,0,.08);
    background: rgba(0,0,0,.02);
  }

  .sccc-modal__btn{
    border-radius:12px;
    padding:10px 12px;
    font-weight:900;
    border:1px solid rgba(0,0,0,.10);
    background: rgba(0,0,0,.03);
    cursor:pointer;
  }

  .sccc-modal__btn--primary{
    background: rgba(19,91,236,.95);
    border-color: rgba(19,91,236,.95);
    color:#fff;
  }

  .sccc-modal__btn--danger{
    border-color: rgba(220,38,38,.25);
    background: rgba(220,38,38,.06);
    color: rgba(170,24,24,.95);
  }

  .sccc-help{
    margin-top:10px;
    font-size:12px;
    opacity:.75;
    line-height:1.35;
  }

  .sccc-char-count{
    margin-top:8px;
    font-size:12px;
    font-weight:900;
    opacity:.75;
    text-align:right;
  }

  .sccc-char-count.is-over{
    color: rgba(170,24,24,.95);
    opacity:1;
  }

  .sccc-field-error{
    display:none;
    margin-top:6px;
    font-size:12px;
    font-weight:900;
    color: rgba(170,24,24,.95);
    line-height:1.35;
  }

  .sccc-field-error.is-visible{
    display:block;
  }

  .sccc-input.is-invalid{
    border-color: rgba(220,38,38,.60);
    box-shadow: 0 0 0 3px rgba(220,38,38,.12);
  }

  .sccc-thumb{
    display:flex;
    gap:12px;
    align-items:center;
    margin-top:10px;
    padding:10px 10px;
    border-radius:12px;
    border:1px solid rgba(0,0,0,.08);
    background: rgba(0,0,0,.02);
  }

  .sccc-thumb__img{
    width:70px;
    height:54px;
    border-radius:10px;
    background: #eaeaea;
    background-size:cover;
    background-position:center;
    flex:0 0 auto;
  }

  .sccc-thumb__meta{
    font-size:12px;
    opacity:.80;
    line-height:1.35;
  }

  /* Dark theme overrides */
  :is(html.dark, html[data-theme="dark"]) .sccc-mdash{ color:#fff; }

  :is(html.dark, html[data-theme="dark"]) .sccc-card{
    background: rgba(22, 22, 42, .70);
    border-color: rgba(255,255,255,.08);
    box-shadow: 0 10px 30px rgba(0,0,0,.12);
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-card__v--rank{
    color:#fbbf24;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-panel{
    background: rgba(22, 22, 42, .55);
    border-color: rgba(255,255,255,.08);
    box-shadow: 0 10px 30px rgba(0,0,0,.12);
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-link{
    color:#7fb2ff;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-input{
    background: rgba(10,12,24,.55);
    border-color: rgba(255,255,255,.10);
    color:#fff;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-toggle{
    background: rgba(10,12,24,.55);
    border-color: rgba(255,255,255,.10);
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-switch{
    background: rgba(255,255,255,.18);
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-switch::after{
    box-shadow:none;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-car{
    background: rgba(22, 22, 42, .55);
    border-color: rgba(255,255,255,.08);
    box-shadow:none;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-car__img{
    background:#0b0d1a;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-addcar{
    border-color: rgba(255,255,255,.18);
    background: rgba(10,12,24,.35);
    color:#fff;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-addcar__btn{
    background: rgba(22,22,42,.80);
    border-color: rgba(255,255,255,.10);
    color:#7fb2ff;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-car__action{
    border-color: rgba(255,255,255,.12);
    background: rgba(255,255,255,.06);
    color:#fff;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-car__action--primary{
    background: rgba(19,91,236,.95);
    border-color: rgba(19,91,236,.95);
    color:#fff;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-car__action--danger{
    border-color: rgba(239,68,68,.35);
    background: rgba(239,68,68,.10);
    color:#fecaca;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-modal__panel{
    background: rgba(22, 22, 42, .92);
    border-color: rgba(255,255,255,.10);
  }
  :is(html.dark, html[data-theme="dark"]) .sccc-modal__head{
    border-bottom-color: rgba(255,255,255,.10);
  }
  :is(html.dark, html[data-theme="dark"]) .sccc-modal__footer{
    border-top-color: rgba(255,255,255,.10);
    background: rgba(255,255,255,.04);
  }
  :is(html.dark, html[data-theme="dark"]) .sccc-modal__close{
    border-color: rgba(255,255,255,.12);
    background: rgba(255,255,255,.06);
    color:#fff;
  }
  :is(html.dark, html[data-theme="dark"]) .sccc-modal__btn{
    border-color: rgba(255,255,255,.12);
    background: rgba(255,255,255,.06);
    color:#fff;
  }
  :is(html.dark, html[data-theme="dark"]) .sccc-modal__btn--primary{
    background: rgba(19,91,236,.95);
    border-color: rgba(19,91,236,.95);
    color:#fff;
  }
  :is(html.dark, html[data-theme="dark"]) .sccc-modal__btn--danger{
    border-color: rgba(239,68,68,.35);
    background: rgba(239,68,68,.10);
    color:#fecaca;
  }
  :is(html.dark, html[data-theme="dark"]) .sccc-thumb{
    border-color: rgba(255,255,255,.10);
    background: rgba(255,255,255,.06);
  }
  :is(html.dark, html[data-theme="dark"]) .sccc-thumb__img{
    background:#0b0d1a;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-char-count.is-over,
  :is(html.dark, html[data-theme="dark"]) .sccc-field-error{
    color:#fecaca;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-input.is-invalid{
    border-color: rgba(248,113,113,.70);
    box-shadow: 0 0 0 3px rgba(248,113,113,.16);
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-social-panel{
    background: rgba(255,255,255,.04);
    border-color: rgba(255,255,255,.10);
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-social-website{
    background: rgba(19,91,236,.14);
    border-color: rgba(19,91,236,.28);
    color:#7fb2ff;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-social-icon{
    background: rgba(255,255,255,.06);
    border-color: rgba(255,255,255,.10);
    color: rgba(255,255,255,.84);
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-social-icon:hover{
    background: rgba(19,91,236,.95);
    color:#fff;
    box-shadow: 0 12px 26px rgba(19,91,236,.22);
  }
</style>

<div class="sccc-mdash">

  @if ($is_true_member)

    {{-- Member-only: Quick Stats Row --}}
    <div class="sccc-row">
      <div class="sccc-card">
        <div class="sccc-card__k">Member Since</div>
        <div class="sccc-card__v">{{ $member_since_year }}</div>
      </div>

      <div class="sccc-card">
        <div class="sccc-card__k">Events Attended</div>
        <div class="sccc-card__v">{{ $events_attended }}</div>
      </div>

      <div class="sccc-card">
        <div class="sccc-card__k">Current Rank</div>
        <div class="sccc-card__v sccc-card__v--rank">{{ $rank }}</div>
      </div>

      <div class="sccc-card sccc-card--bright">
        <div class="sccc-card__k" style="opacity:.85;">Member ID</div>
        <div class="sccc-card__v" style="font-size:20px;">
          {{ '#'.$member_id_display }}
        </div>
        <div style="position:absolute; right:12px; top:10px; opacity:.18; font-size:44px;">▦</div>
      </div>
    </div>

    @php
      /*
       * Member phone + directory visibility + social links.
       *
       * These are stored as user meta / ACF user fields:
       * - sccc_member_phone
       * - sccc_hide_in_member_directory
       * - sccc_member_website
       * - sccc_member_social_links
       *
       * Phone is shown only inside the member dashboard.
       * Directory visibility is the reversed opt-out:
       * - 0/empty = visible in future Member Directory
       * - 1 = hidden from future Member Directory
       */
      $sccc_social_user_id = get_current_user_id();
      $sccc_social_acf_key = 'user_' . $sccc_social_user_id;

      $sccc_member_phone = '';
      $sccc_hide_in_member_directory = 0;
      $sccc_member_website = '';

      if (function_exists('get_field')) {
        $sccc_member_phone = (string) (get_field('sccc_member_phone', $sccc_social_acf_key) ?: '');
        $sccc_hide_in_member_directory = (int) ((bool) get_field('sccc_hide_in_member_directory', $sccc_social_acf_key));
        $sccc_member_website = (string) (get_field('sccc_member_website', $sccc_social_acf_key) ?: '');
      }

      if ($sccc_member_phone === '') {
        $sccc_member_phone = (string) get_user_meta($sccc_social_user_id, 'sccc_member_phone', true);
      }

      if ($sccc_hide_in_member_directory !== 1) {
        $sccc_hide_meta = get_user_meta($sccc_social_user_id, 'sccc_hide_in_member_directory', true);
        $sccc_hide_in_member_directory = ((string) $sccc_hide_meta === '1') ? 1 : $sccc_hide_in_member_directory;
      }

      if ($sccc_member_website === '') {
        $sccc_member_website = (string) get_user_meta($sccc_social_user_id, 'sccc_member_website', true);
      }

      /*
       * Normalize the member phone before display.
       *
       * Rules:
       * - Strip all non-digits from whatever was saved.
       * - Format 10-digit US numbers as: (555) 123-4567
       * - Format 11-digit US numbers beginning with 1 as: +1 (555) 123-4567
       * - Format 7-digit local numbers as: 123-4567
       * - If the digit count is unusual, show digits only instead of guessing.
       */
      $sccc_format_us_phone = static function (string $phone): string {
        $digits = preg_replace('/\D+/', '', sanitize_text_field($phone));
        $digits = is_string($digits) ? $digits : '';

        if ($digits === '') {
          return '';
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
          return sprintf(
            '+1 (%s) %s-%s',
            substr($digits, 1, 3),
            substr($digits, 4, 3),
            substr($digits, 7, 4)
          );
        }

        if (strlen($digits) === 10) {
          return sprintf(
            '(%s) %s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 4)
          );
        }

        if (strlen($digits) === 7) {
          return sprintf(
            '%s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 4)
          );
        }

        return $digits;
      };

      $sccc_member_phone = $sccc_format_us_phone($sccc_member_phone);
      $sccc_member_website = esc_url($sccc_member_website);

      $sccc_social_raw = [];

      if (function_exists('get_field')) {
        $sccc_social_from_acf = get_field('sccc_member_social_links', $sccc_social_acf_key);
        if (is_array($sccc_social_from_acf)) {
          $sccc_social_raw = $sccc_social_from_acf;
        }
      }

      if (empty($sccc_social_raw)) {
        $sccc_social_from_meta = get_user_meta($sccc_social_user_id, 'sccc_member_social_links', true);
        $sccc_social_raw = is_array($sccc_social_from_meta) ? $sccc_social_from_meta : [];
      }

      $sccc_social_platforms = [
        'facebook' => [
          'label' => 'Facebook',
          'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.2 8.4V6.7c0-.8.6-1 1-1h2.6V2.1L14.4 2c-3.4 0-4.9 2-4.9 4.8v1.6H6.4v3.8h3.1V22h4.1v-9.8h3.3l.5-3.8h-3.2Z"/></svg>',
        ],
        'instagram' => [
          'label' => 'Instagram',
          'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9a5.5 5.5 0 0 1-5.5 5.5h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2Zm0 2A3.5 3.5 0 0 0 4 7.5v9A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5v-9A3.5 3.5 0 0 0 16.5 4h-9Zm4.5 3.4A4.6 4.6 0 1 1 12 16.6a4.6 4.6 0 0 1 0-9.2Zm0 2A2.6 2.6 0 1 0 12 14.6a2.6 2.6 0 0 0 0-5.2Zm5-2.45a1.05 1.05 0 1 1 0 2.1 1.05 1.05 0 0 1 0-2.1Z"/></svg>',
        ],
        'x' => [
          'label' => 'X',
          'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.7 3h3.1l-6.8 7.8L22 21h-6.4l-5-6.5L4.9 21H1.8l7.3-8.4L1.4 3H8l4.5 5.9L17.7 3Zm-1.1 16.2h1.7L7.1 4.7H5.3l11.3 14.5Z"/></svg>',
        ],
        'tiktok' => [
          'label' => 'TikTok',
          'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16.2 2c.3 2.4 1.7 4 4 4.2v3.5a7.4 7.4 0 0 1-4-1.2v6.7a6.3 6.3 0 1 1-6.3-6.3c.4 0 .8 0 1.2.1v3.7a2.6 2.6 0 1 0 1.8 2.5V2h3.3Z"/></svg>',
        ],
        'youtube' => [
          'label' => 'YouTube',
          'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.6 7.2s-.2-1.6-.8-2.3c-.8-.9-1.7-.9-2.1-1C15.8 3.7 12 3.7 12 3.7h0s-3.8 0-6.7.2c-.4.1-1.3.1-2.1 1-.6.7-.8 2.3-.8 2.3S2.2 9.1 2.2 11v1.8c0 1.9.2 3.8.2 3.8s.2 1.6.8 2.3c.8.9 1.9.9 2.4 1 1.7.2 6.4.2 6.4.2s3.8 0 6.7-.2c.4-.1 1.3-.1 2.1-1 .6-.7.8-2.3.8-2.3s.2-1.9.2-3.8V11c0-1.9-.2-3.8-.2-3.8ZM10.1 14.9V8.4l6.1 3.2-6.1 3.3Z"/></svg>',
        ],
      ];

      $sccc_member_social_links = [];
      $sccc_seen_social = [];

      foreach ($sccc_social_raw as $row) {
        if (! is_array($row)) {
          continue;
        }

        $platform = isset($row['platform']) ? sanitize_key((string) $row['platform']) : '';
        $url = isset($row['url']) ? esc_url((string) $row['url']) : '';
        $username = isset($row['username']) ? sanitize_text_field((string) $row['username']) : '';
        $username = ltrim(trim($username), '@');

        if ($platform === '' || $url === '' || ! isset($sccc_social_platforms[$platform])) {
          continue;
        }

        if ($username === '') {
          $path = (string) parse_url($url, PHP_URL_PATH);
          $path = trim($path, "/ \t\n\r\0\x0B");
          $parts = array_values(array_filter(explode('/', $path)));
          $candidate = end($parts);
          $username = is_string($candidate) ? ltrim(sanitize_text_field($candidate), '@') : '';
        }

        $fingerprint = $platform . '|' . strtolower($username) . '|' . strtolower($url);

        if (isset($sccc_seen_social[$fingerprint])) {
          continue;
        }

        $sccc_member_social_links[] = [
          'platform' => $platform,
          'url' => $url,
          'username' => $username,
          'label' => $sccc_social_platforms[$platform]['label'],
          'icon' => $sccc_social_platforms[$platform]['icon'],
        ];

        $sccc_seen_social[$fingerprint] = true;

        if (count($sccc_member_social_links) >= 15) {
          break;
        }
      }
    @endphp

    {{-- Personal Information --}}
    <section class="sccc-section">
      <div class="sccc-section__head">
        <h2 class="sccc-section__title">Personal Information</h2>
        <a class="sccc-link" href="{{ e(wc_get_account_endpoint_url('edit-account')) }}">Edit details</a>
      </div>

      <div class="sccc-panel">
        <div class="sccc-form">

          <div class="sccc-field">
            <label>Full Name</label>
            <input class="sccc-input" type="text" readonly value="{{ e($user_display_name) }}">
          </div>

          <div class="sccc-field">
            <label>Email Address</label>
            <input class="sccc-input" type="email" readonly value="{{ e($user_email) }}">
          </div>

          <div class="sccc-field">
            <label>Phone Number</label>
            <input
              class="sccc-input"
              type="text"
              readonly
              value="{{ $sccc_member_phone !== '' ? e($sccc_member_phone) : 'Not added yet' }}"
            >
          </div>

          <div class="sccc-field">
            <label>Member Directory</label>
            <div class="sccc-toggle">
              <div>
                <strong>{{ $sccc_hide_in_member_directory === 1 ? 'Hidden' : 'Visible' }}</strong>
                <small>
                  {{ $sccc_hide_in_member_directory === 1
                    ? 'You opted out of appearing in the Member Directory.'
                    : 'You are eligible to appear in the Member Directory.'
                  }}
                </small>
              </div>
              <div class="sccc-switch {{ $sccc_hide_in_member_directory === 1 ? '' : 'is-on' }}" aria-hidden="true"></div>
            </div>
          </div>

          <div class="sccc-field" style="grid-column: 1 / -1;">
            <label>Website & Social Media</label>

            <div class="sccc-social-panel">
              @if ($sccc_member_website)
                <a
                  class="sccc-social-website"
                  href="{{ $sccc_member_website }}"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <span aria-hidden="true">↗</span>
                  <span>Website</span>
                </a>
              @endif

              @if (! empty($sccc_member_social_links))
                <div class="sccc-social-icons" aria-label="Social media profile links">
                  @foreach ($sccc_member_social_links as $social)
                    <a
                      class="sccc-social-icon"
                      href="{{ $social['url'] }}"
                      target="_blank"
                      rel="noopener noreferrer"
                      aria-label="{{ $social['label'] }} profile{{ ! empty($social['username']) ? ': @'.$social['username'] : '' }}"
                      title="{{ $social['label'] }}{{ ! empty($social['username']) ? ': @'.$social['username'] : '' }}"
                    >
                      {!! $social['icon'] !!}

                      @if (! empty($social['username']))
                        <span class="sccc-social-icon__handle">{{ '@' . $social['username'] }}</span>
                      @endif
                    </a>
                  @endforeach
                </div>
              @endif

              @if (! $sccc_member_website && empty($sccc_member_social_links))
                <span style="font-size:13px; opacity:.70; font-weight:800;">
                  Add your website or social profiles from Edit details.
                </span>
              @endif
            </div>
          </div>

          <div class="sccc-field">
            <label>Birthdate</label>
            <input class="sccc-input" type="text" readonly value="{{ e($birth_date) }}">
          </div>

          {{-- display only --}}
          <div class="sccc-field">
            <label>Service / Responder Status</label>
            <div class="sccc-toggle">
              <div>
                <strong>{{ $service_responder_display ?? ($is_veteran ? 'Yes (details missing)' : 'No') }}</strong>
              </div>
            </div>
          </div>

          {{-- ======================================================================
               SCCC ADDITION: Biographical Info (inline edit + AJAX save)
               - Plain text only (no HTML)
               - 1500 char limit (client + server)
               - Save button un-ghosts only when changed
               - Tiny "Saved ✓" status near buttons
               - Auto-collapse back to view mode with a quick fade
               - Edit/Add button is below the bio block (less clunky)
               ====================================================================== --}}
          @php
            $sccc_bio_user_id = get_current_user_id();
            $sccc_bio_value_raw = (string) get_user_meta($sccc_bio_user_id, 'description', true);
            $sccc_bio_value = preg_replace("/\r\n?/", "\n", $sccc_bio_value_raw) ?? $sccc_bio_value_raw;

            $sccc_bio_action = 'sccc_member_bio_save';
            $sccc_bio_nonce = wp_create_nonce($sccc_bio_action);

            // Root-relative URL helps avoid subtle domain mismatches (www vs non-www).
            $sccc_bio_ajax_url = function_exists('wp_make_link_relative')
              ? wp_make_link_relative(admin_url('admin-ajax.php'))
              : admin_url('admin-ajax.php');

            $sccc_bio_max = 1500;

            $sccc_bio_len = function_exists('mb_strlen')
              ? (int) mb_strlen($sccc_bio_value)
              : (int) strlen($sccc_bio_value);
          @endphp

          <div
            class="sccc-field"
            style="grid-column: 1 / -1;"
            data-sccc-bio="1"
            data-ajax-url="{{ e($sccc_bio_ajax_url) }}"
            data-nonce="{{ e($sccc_bio_nonce) }}"
            data-max="{{ e((string) $sccc_bio_max) }}"
          >
            <label>Biographical Info</label>

            {{-- View mode --}}
            <div data-sccc-bio-view>
              <div class="sccc-toggle" style="align-items:flex-start;">
                <div style="flex:1 1 auto; min-width:0;">
                  <div data-sccc-bio-preview style="white-space:pre-wrap; line-height:1.35; opacity:.85;">
                    @if (trim($sccc_bio_value) !== '')
                      {{ $sccc_bio_value }}
                    @else
                      <span style="opacity:.75;">Add a short bio so members can get to know you.</span>
                    @endif
                  </div>
                </div>
              </div>

              {{-- Actions BELOW the bio block (per request) --}}
              <div style="margin-top:10px; display:flex; align-items:center; justify-content:space-between; gap:10px;">
                <div style="font-size:12px; opacity:.70;">
                  <span data-sccc-bio-count-view>{{ e((string) $sccc_bio_len) }}</span> / {{ e((string) $sccc_bio_max) }}
                </div>

                <div style="display:flex; align-items:center; gap:10px;">
                  <span style="font-size:12px; font-weight:900; opacity:.80;" data-sccc-bio-status-view aria-live="polite"></span>
                  <button type="button" class="sccc-modal__btn" data-sccc-bio-edit>
                    {{ trim($sccc_bio_value) !== '' ? 'Edit' : 'Add' }}
                  </button>
                </div>
              </div>
            </div>

            {{-- Edit mode --}}
            <div data-sccc-bio-editpanel style="display:none;">
              <textarea
                class="sccc-input"
                rows="6"
                maxlength="{{ e((string) $sccc_bio_max) }}"
                data-sccc-bio-textarea
                placeholder="Tell the club about you (plain text only)…"
              >{{ $sccc_bio_value }}</textarea>

              <div style="margin-top:10px; display:flex; align-items:center; justify-content:space-between; gap:10px;">
                <div style="font-size:12px; opacity:.70;">
                  <span data-sccc-bio-count-edit>{{ e((string) $sccc_bio_len) }}</span> / {{ e((string) $sccc_bio_max) }}
                </div>

                <div style="display:flex; align-items:center; gap:10px;">
                  <span style="font-size:12px; font-weight:900; opacity:.80;" data-sccc-bio-status-edit aria-live="polite"></span>
                  <button type="button" class="sccc-modal__btn" data-sccc-bio-cancel>Cancel</button>
                  <button
                    type="button"
                    class="sccc-modal__btn sccc-modal__btn--primary"
                    data-sccc-bio-save
                    disabled
                    style="opacity:.55; cursor:not-allowed;"
                  >Save</button>
                </div>
              </div>
            </div>
          </div>

          <script>
            /**
             * SCCC ADDITION: Member bio inline editor
             * - Plain text only
             * - Enables "Save" only when changed
             * - Saves via wp-admin AJAX (admin-ajax.php)
             * - Quick fade between view/edit panels
             * - Tiny "Saved ✓" status near the buttons
             */
            (function () {
              var root = document.querySelector('[data-sccc-bio="1"]');
              if (!root || root.__scccBioInit) return;
              root.__scccBioInit = true;

              var ajaxUrl = root.getAttribute('data-ajax-url');
              var nonce = root.getAttribute('data-nonce');
              var max = parseInt(root.getAttribute('data-max') || '1500', 10);

              var view = root.querySelector('[data-sccc-bio-view]');
              var editPanel = root.querySelector('[data-sccc-bio-editpanel]');
              var preview = root.querySelector('[data-sccc-bio-preview]');
              var editBtn = root.querySelector('[data-sccc-bio-edit]');
              var textarea = root.querySelector('[data-sccc-bio-textarea]');
              var saveBtn = root.querySelector('[data-sccc-bio-save]');
              var cancelBtn = root.querySelector('[data-sccc-bio-cancel]');

              var countView = root.querySelector('[data-sccc-bio-count-view]');
              var countEdit = root.querySelector('[data-sccc-bio-count-edit]');
              var statusView = root.querySelector('[data-sccc-bio-status-view]');
              var statusEdit = root.querySelector('[data-sccc-bio-status-edit]');

              if (!view || !editPanel || !preview || !editBtn || !textarea || !saveBtn || !cancelBtn) return;

              function normalize(s) { return (s || '').replace(/\r\n?/g, '\n'); }

              function setStatus(el, msg, isError) {
                if (!el) return;
                el.textContent = msg || '';
                el.style.color = isError ? 'rgba(170,24,24,.95)' : '';
              }

              function clearStatusSoon() {
                window.setTimeout(function () {
                  setStatus(statusView, '', false);
                  setStatus(statusEdit, '', false);
                }, 1600);
              }

              function setSaveEnabled(enabled) {
                saveBtn.disabled = !enabled;
                saveBtn.style.opacity = enabled ? '1' : '.55';
                saveBtn.style.cursor = enabled ? 'pointer' : 'not-allowed';
              }

              function updateCounts() {
                var val = normalize(textarea.value);
                var len = val.length;

                if (len > max) {
                  textarea.value = val.slice(0, max);
                  len = max;
                }

                if (countEdit) countEdit.textContent = String(len);
                if (countView) countView.textContent = String(len);
              }

              function fadeOut(el, done) {
                el.style.transition = 'opacity 160ms ease';
                el.style.opacity = '1';
                void el.offsetHeight;
                el.style.opacity = '0';
                window.setTimeout(function () {
                  el.style.display = 'none';
                  el.style.opacity = '';
                  el.style.transition = '';
                  if (typeof done === 'function') done();
                }, 170);
              }

              function fadeIn(el) {
                el.style.display = 'block';
                el.style.opacity = '0';
                el.style.transition = 'opacity 160ms ease';
                void el.offsetHeight;
                el.style.opacity = '1';
                window.setTimeout(function () {
                  el.style.opacity = '';
                  el.style.transition = '';
                }, 170);
              }

              function showEdit() {
                setStatus(statusEdit, '', false);
                setStatus(statusView, '', false);

                fadeOut(view, function () {
                  fadeIn(editPanel);
                  textarea.focus();
                });
              }

              function showView() {
                fadeOut(editPanel, function () {
                  fadeIn(view);
                });
              }

              var initial = normalize(textarea.value);

              updateCounts();
              setSaveEnabled(false);

              editBtn.addEventListener('click', function () {
                showEdit();
              });

              cancelBtn.addEventListener('click', function () {
                textarea.value = initial;
                updateCounts();
                setSaveEnabled(false);
                showView();
              });

              textarea.addEventListener('input', function () {
                updateCounts();

                var now = normalize(textarea.value);
                setSaveEnabled(now !== initial);
                setStatus(statusEdit, '', false);
              });

              saveBtn.addEventListener('click', function () {
                var now = normalize(textarea.value);

                if (now === initial) {
                  setSaveEnabled(false);
                  return;
                }

                setSaveEnabled(false);
                setStatus(statusEdit, 'Saving…', false);

                var form = new FormData();
                form.append('action', '{{ $sccc_bio_action }}');
                form.append('nonce', nonce);
                form.append('bio', textarea.value);

                fetch(ajaxUrl, {
                  method: 'POST',
                  credentials: 'same-origin',
                  body: form
                })
                .then(function (r) { return r.text(); })
                .then(function (raw) {
                  var json = null;
                  try { json = JSON.parse(raw); } catch (e) { json = null; }

                  if (json === 0) {
                    setStatus(statusEdit, 'Save handler not available. Please reload.', true);
                    setSaveEnabled(true);
                    return;
                  }

                  if (json === -1) {
                    setStatus(statusEdit, 'Security check failed. Please reload and try again.', true);
                    setSaveEnabled(true);
                    return;
                  }

                  if (!json || !json.success) {
                    var msg = (json && json.data && json.data.message) ? json.data.message : 'Could not save. Please try again.';
                    setStatus(statusEdit, msg, true);
                    setSaveEnabled(true);
                    return;
                  }

                  var saved = (json.data && typeof json.data.bio === 'string') ? normalize(json.data.bio) : now;

                  textarea.value = saved;
                  initial = saved;
                  updateCounts();

                  if (saved.trim()) {
                    preview.textContent = saved;
                    editBtn.textContent = 'Edit';
                  } else {
                    preview.innerHTML = '<span style="opacity:.75;">Add a short bio so members can get to know you.</span>';
                    editBtn.textContent = 'Add';
                  }

                  setStatus(statusEdit, 'Saved ✓', false);
                  setStatus(statusView, 'Saved ✓', false);
                  clearStatusSoon();

                  showView();
                })
                .catch(function () {
                  setStatus(statusEdit, 'Save failed. Please try again.', true);
                  setSaveEnabled(true);
                });
              });
            })();
          </script>
          {{-- ======================================================================
               /SCCC ADDITION: Biographical Info
               ====================================================================== --}}

          {{-- Pause Membership toggle (UI only for now) --}}
          <div class="sccc-field" style="grid-column: 1 / -1;">
            <label>Pause Membership</label>
            <div class="sccc-toggle" style="opacity:.85;">
              <div>
                <strong>Coming soon</strong>
                <small>UI placeholder — we’ll wire pause logic next.</small>
              </div>
              <div class="sccc-switch" aria-hidden="true"></div>
            </div>
          </div>

        </div>
      </div>
    </section>

    {{-- My Garage --}}
    <section class="sccc-section" id="sccc-garage">
      <div class="sccc-section__head">
        <h2 class="sccc-section__title">My Garage</h2>
      </div>

      <div class="sccc-garage-grid">
        @foreach ($vehicles as $row)
          @php
            $vehicle_id = (string) ($row['vehicle_id'] ?? '');
            $year  = (string) ($row['year'] ?? '');
            $make_select = (string) ($row['make_select'] ?? '');
            $make_other  = (string) ($row['make_other'] ?? '');
            $make  = (string) ($row['make_raw'] ?? '');
            $model = (string) ($row['model'] ?? '');
            $nick  = (string) ($row['nickname'] ?? '');
            $notes = (string) ($row['notes'] ?? '');
            $notes = html_entity_decode($notes, ENT_QUOTES | ENT_HTML5, get_option('blog_charset'));
            $notes = preg_replace('/<br\s*\/?>/i', "\n", $notes) ?? $notes;
            $notes = preg_replace("/\r\n?|\n/", "\n", $notes) ?? $notes;
            $notes = preg_replace("/\n{3,}/", "\n\n", $notes) ?? $notes;
            $notes = trim($notes);
            $img   = (int) ($row['image'] ?? 0);

            $title = trim($year . ' ' . $make . ' ' . $model) ?: 'Vehicle';
            $img_url = $img ? wp_get_attachment_image_url($img, 'large') : '';
            $img_thumb = $img ? wp_get_attachment_image_url($img, 'medium') : '';
          @endphp

          <article class="sccc-car">
            <div class="sccc-car__img" style="background-image:url('{{ e($img_url) }}')"></div>
            <div class="sccc-car__body">
              @if ($nick)
                <div style="opacity:.75; font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:.08em;">
                  {{ $nick }}
                </div>
              @endif

              <div class="sccc-car__title">{{ $title }}</div>

              @if ($notes)
                <div class="sccc-car__meta">{!! nl2br(e($notes)) !!}</div>
              @endif

              <div class="sccc-car__actions">
                <button
                  type="button"
                  class="sccc-car__action sccc-car__action--primary"
                  data-sccc-vehicle-edit="1"
                  data-vehicle-id="{{ e($vehicle_id) }}"
                  data-year="{{ e($year) }}"
                  data-make-select="{{ e($make_select) }}"
                  data-make-other="{{ e($make_other) }}"
                  data-model="{{ e($model) }}"
                  data-nickname="{{ e($nick) }}"
                  data-notes="{{ e($notes) }}"
                  data-image-id="{{ e((string) $img) }}"
                  data-image-url="{{ e((string) $img_thumb) }}"
                >Edit</button>

                <button
                  type="button"
                  class="sccc-car__action sccc-car__action--danger"
                  data-sccc-vehicle-delete="1"
                  data-vehicle-id="{{ e($vehicle_id) }}"
                  data-title="{{ e($title) }}"
                >Remove</button>
              </div>
            </div>
          </article>
        @endforeach

        {{-- Add Vehicle card opens the modal --}}
        <div class="sccc-addcar" role="button" tabindex="0" data-sccc-addcar="1">
          <div class="sccc-addcar__btn">+</div>
          <div class="sccc-addcar__t">Add Vehicle</div>
          <div class="sccc-addcar__d">Add a vehicle to your Garage.</div>
        </div>
      </div>

      {{-- Modal (shared for Add + Edit) --}}
      <div class="sccc-modal" id="sccc-garage-modal" aria-hidden="true" aria-modal="true" role="dialog">
        <div class="sccc-modal__backdrop" data-sccc-modal-close="1"></div>

        <div class="sccc-modal__panel" role="document">
          <div class="sccc-modal__head">
            <div class="sccc-modal__title" id="sccc-garage-modal-title">Add Vehicle</div>
            <button type="button" class="sccc-modal__close" data-sccc-modal-close="1">Close</button>
          </div>

          <div class="sccc-modal__body">
            <form method="post" enctype="multipart/form-data" id="sccc-garage-form">
              {{-- action + nonce injected via JS --}}
              <input type="hidden" name="sccc_garage_action" id="sccc_garage_action" value="add_vehicle">
              <input type="hidden" name="sccc_garage_nonce" id="sccc_garage_nonce" value="{{ wp_create_nonce('sccc_garage_add_vehicle') }}">
              <input type="hidden" name="sccc_vehicle_id" id="sccc_vehicle_id" value="">

              <div class="sccc-form">
                <div class="sccc-field">
                  <label for="sccc_vehicle_year">Year</label>
                  <input class="sccc-input" type="text" name="sccc_vehicle_year" id="sccc_vehicle_year" placeholder="Example: 2023">
                </div>

                <div class="sccc-field">
                  <label for="sccc_vehicle_make_select">Make</label>
                  <select class="sccc-input" name="sccc_vehicle_make_select" id="sccc_vehicle_make_select">
                    <option value="">Select make…</option>
                    <option value="Acura">Acura</option>
                    <option value="AMC">AMC</option>
                    <option value="Aston Martin">Aston Martin</option>
                    <option value="Audi">Audi</option>
                    <option value="Bentley">Bentley</option>
                    <option value="BMW">BMW</option>
                    <option value="Buick">Buick</option>
                    <option value="Cadillac">Cadillac</option>
                    <option value="Chevrolet">Chevrolet</option>
                    <option value="Chrysler">Chrysler</option>
                    <option value="DeLorean">DeLorean</option>
                    <option value="Dodge">Dodge</option>
                    <option value="Eagle">Eagle</option>
                    <option value="Ferrari">Ferrari</option>
                    <option value="Ford">Ford</option>
                    <option value="GMC">GMC</option>
                    <option value="Honda">Honda</option>
                    <option value="Hummer">Hummer</option>
                    <option value="Infiniti">Infiniti</option>
                    <option value="Jeep">Jeep</option>
                    <option value="Lamborghini">Lamborghini</option>
                    <option value="Lexus">Lexus</option>
                    <option value="Maserati">Maserati</option>
                    <option value="Mazda">Mazda</option>
                    <option value="McLaren">McLaren</option>
                    <option value="Mercedes-Benz">Mercedes-Benz</option>
                    <option value="Mercury">Mercury</option>
                    <option value="MINI">MINI</option>
                    <option value="Mitsubishi">Mitsubishi</option>
                    <option value="Nissan">Nissan</option>
                    <option value="Oldsmobile">Oldsmobile</option>
                    <option value="Plymouth">Plymouth</option>
                    <option value="Pontiac">Pontiac</option>
                    <option value="Porsche">Porsche</option>
                    <option value="Ram">Ram</option>
                    <option value="Rolls-Royce">Rolls-Royce</option>
                    <option value="Saturn">Saturn</option>
                    <option value="Subaru">Subaru</option>
                    <option value="Tesla">Tesla</option>
                    <option value="Toyota">Toyota</option>
                    <option value="Volkswagen">Volkswagen</option>
                    <option value="Volvo">Volvo</option>
                    <option value="Other">Other / Not listed</option>
                  </select>
                </div>

                <div class="sccc-field" id="sccc_vehicle_make_other_wrap" style="display:none;">
                  <label for="sccc_vehicle_make_other">Other Make</label>
                  <input class="sccc-input" type="text" name="sccc_vehicle_make_other" id="sccc_vehicle_make_other" placeholder="Enter make…">
                </div>

                <div class="sccc-field">
                  <label for="sccc_vehicle_model">Model</label>
                  <input class="sccc-input" type="text" name="sccc_vehicle_model" id="sccc_vehicle_model" placeholder="Example: Corvette C8">
                </div>

                <div class="sccc-field">
                  <label for="sccc_vehicle_nickname">Nickname</label>
                  <input class="sccc-input" type="text" name="sccc_vehicle_nickname" id="sccc_vehicle_nickname" placeholder="Optional">
                </div>

                <div class="sccc-field">
                  <label for="sccc_vehicle_image">Photo</label>
                  <input class="sccc-input" type="file" name="sccc_vehicle_image" id="sccc_vehicle_image" accept="image/*">
                  <div class="sccc-help">Tip: uploading a new photo replaces the current one.</div>
                </div>

                <div class="sccc-field" style="grid-column: 1 / -1;">
                  <label for="sccc_vehicle_notes">Description</label>
                  <textarea
                    class="sccc-input"
                    name="sccc_vehicle_notes"
                    id="sccc_vehicle_notes"
                    rows="5"
                    placeholder="Tell us about the vehicle, build, mods, and what makes it special."
                    data-sccc-vehicle-notes="1"
                    data-max="1000"
                    aria-describedby="sccc_vehicle_notes_help sccc_vehicle_notes_count sccc_vehicle_notes_error"
                  ></textarea>
                  <div class="sccc-help" id="sccc_vehicle_notes_help">Plain text only. Maximum 1,000 characters.</div>
                  <div class="sccc-char-count" id="sccc_vehicle_notes_count" data-sccc-vehicle-notes-count="1">0/1000</div>
                  <div class="sccc-field-error" id="sccc_vehicle_notes_error" data-sccc-vehicle-notes-error="1" aria-live="polite"></div>
                </div>

                {{-- Edit-only: remove image --}}
                <div class="sccc-field" id="sccc_vehicle_image_remove_wrap" style="grid-column: 1 / -1; display:none;">
                  <label>
                    <input type="checkbox" name="sccc_vehicle_image_remove" id="sccc_vehicle_image_remove" value="1">
                    Remove current photo
                  </label>

                  <div class="sccc-thumb" id="sccc_vehicle_thumb" style="display:none;">
                    <div class="sccc-thumb__img" id="sccc_vehicle_thumb_img"></div>
                    <div class="sccc-thumb__meta" id="sccc_vehicle_thumb_meta"></div>
                  </div>
                </div>
              </div>

              <div class="sccc-modal__footer">
                <button type="button" class="sccc-modal__btn" data-sccc-modal-close="1">Cancel</button>
                <button type="submit" class="sccc-modal__btn sccc-modal__btn--primary" id="sccc_modal_submit_btn">Add Vehicle</button>
              </div>
            </form>

            {{-- Separate delete form (so remove is a clean POST) --}}
            <form method="post" id="sccc-garage-delete-form" style="display:none;">
              <input type="hidden" name="sccc_garage_action" value="delete_vehicle">
              <input type="hidden" name="sccc_garage_nonce" value="{{ wp_create_nonce('sccc_garage_delete_vehicle') }}">
              <input type="hidden" name="sccc_vehicle_id" id="sccc_delete_vehicle_id" value="">
            </form>
          </div>
        </div>
      </div>

      <script>
        /**
         * Garage only:
         * - Modal-based Add/Edit
         * - Remove vehicle (confirm -> POST delete)
         * - Other make toggle
         *
         * Keep Garage.php as-is. This blade only posts the correct action + nonce + id.
         */
        (function () {
          var modal = document.getElementById('sccc-garage-modal');
          var modalTitle = document.getElementById('sccc-garage-modal-title');
          var form = document.getElementById('sccc-garage-form');

          var actionEl = document.getElementById('sccc_garage_action');
          var nonceEl  = document.getElementById('sccc_garage_nonce');
          var vehicleIdEl = document.getElementById('sccc_vehicle_id');

          var submitBtn = document.getElementById('sccc_modal_submit_btn');

          var addCard = document.querySelector('[data-sccc-addcar="1"]');
          var editBtns = document.querySelectorAll('[data-sccc-vehicle-edit="1"]');
          var delBtns  = document.querySelectorAll('[data-sccc-vehicle-delete="1"]');

          var deleteForm = document.getElementById('sccc-garage-delete-form');
          var deleteVehicleId = document.getElementById('sccc_delete_vehicle_id');

          var makeSelect = document.getElementById('sccc_vehicle_make_select');
          var otherWrap  = document.getElementById('sccc_vehicle_make_other_wrap');

          var imageRemoveWrap = document.getElementById('sccc_vehicle_image_remove_wrap');
          var imageRemove = document.getElementById('sccc_vehicle_image_remove');
          var thumb = document.getElementById('sccc_vehicle_thumb');
          var thumbImg = document.getElementById('sccc_vehicle_thumb_img');
          var thumbMeta = document.getElementById('sccc_vehicle_thumb_meta');

          var notesEl = document.getElementById('sccc_vehicle_notes');
          var notesCount = document.querySelector('[data-sccc-vehicle-notes-count="1"]');
          var notesError = document.querySelector('[data-sccc-vehicle-notes-error="1"]');
          var VEHICLE_NOTES_MAX = notesEl ? parseInt(notesEl.getAttribute('data-max') || '1000', 10) : 1000;

          // Move the dialog to <body> so fixed centering is not affected by the
          // My Account shell / mobile-menu push wrapper stacking context.
          if (modal && modal.parentNode !== document.body) {
            document.body.appendChild(modal);
          }

          // Nonces are rendered server-side. We swap the value depending on mode.
          var NONCES = {
            add: "{{ wp_create_nonce('sccc_garage_add_vehicle') }}",
            update: "{{ wp_create_nonce('sccc_garage_update_vehicle') }}",
          };

          function syncOther() {
            if (!makeSelect || !otherWrap) return;
            otherWrap.style.display = (makeSelect.value === 'Other') ? 'block' : 'none';
          }

          function normalizeVehicleNotes(value) {
            return (value || '').replace(/\r\n?/g, '\n');
          }

          function syncVehicleNotesCounter(showMessage) {
            if (!notesEl) return true;

            var len = normalizeVehicleNotes(notesEl.value).length;
            var over = len > VEHICLE_NOTES_MAX;
            var message = 'Vehicle description is ' + len + '/' + VEHICLE_NOTES_MAX + ' characters. Please shorten it before saving.';

            if (notesCount) {
              notesCount.textContent = String(len) + '/' + String(VEHICLE_NOTES_MAX);
              notesCount.classList.toggle('is-over', over);
            }

            notesEl.classList.toggle('is-invalid', over);
            notesEl.setAttribute('aria-invalid', over ? 'true' : 'false');

            if (notesError) {
              notesError.textContent = (over && showMessage) ? message : '';
              notesError.classList.toggle('is-visible', over && showMessage);
            }

            if (submitBtn) {
              submitBtn.disabled = over;
              submitBtn.style.opacity = over ? '.55' : '';
              submitBtn.style.cursor = over ? 'not-allowed' : '';
            }

            return !over;
          }

          function openModal() {
            if (!modal) return;
            modal.setAttribute('aria-hidden', 'false');
            document.documentElement.style.overflow = 'hidden';
          }

          function closeModal() {
            if (!modal) return;
            modal.setAttribute('aria-hidden', 'true');
            document.documentElement.style.overflow = '';
          }

          function resetFormToAdd() {
            if (!form) return;

            form.reset();

            modalTitle.textContent = 'Add Vehicle';
            submitBtn.textContent = 'Add Vehicle';

            actionEl.value = 'add_vehicle';
            nonceEl.value  = NONCES.add;
            vehicleIdEl.value = '';

            if (imageRemoveWrap) imageRemoveWrap.style.display = 'none';
            if (imageRemove) imageRemove.checked = false;

            if (thumb) thumb.style.display = 'none';
            if (thumbImg) thumbImg.style.backgroundImage = '';
            if (thumbMeta) thumbMeta.textContent = '';

            syncOther();
            syncVehicleNotesCounter(false);
          }

          function fillFormForEdit(btn) {
            if (!form || !btn) return;

            form.reset();

            var vehicleId = btn.getAttribute('data-vehicle-id') || '';
            var year = btn.getAttribute('data-year') || '';
            var makeSelectVal = btn.getAttribute('data-make-select') || '';
            var makeOtherVal  = btn.getAttribute('data-make-other') || '';
            var model = btn.getAttribute('data-model') || '';
            var nick = btn.getAttribute('data-nickname') || '';
            var notes = btn.getAttribute('data-notes') || '';
            var imageId = btn.getAttribute('data-image-id') || '0';
            var imageUrl = btn.getAttribute('data-image-url') || '';

            modalTitle.textContent = 'Edit Vehicle';
            submitBtn.textContent = 'Save Changes';

            actionEl.value = 'update_vehicle';
            nonceEl.value  = NONCES.update;
            vehicleIdEl.value = vehicleId;

            var yearEl = document.getElementById('sccc_vehicle_year');
            var modelEl = document.getElementById('sccc_vehicle_model');
            var nickEl = document.getElementById('sccc_vehicle_nickname');
            var notesEl = document.getElementById('sccc_vehicle_notes');
            var makeOtherEl = document.getElementById('sccc_vehicle_make_other');

            if (yearEl) yearEl.value = year;
            if (makeSelect) makeSelect.value = makeSelectVal;
            if (makeOtherEl) makeOtherEl.value = makeOtherVal;
            if (modelEl) modelEl.value = model;
            if (nickEl) nickEl.value = nick;
            if (notesEl) notesEl.value = notes;

            syncOther();
            syncVehicleNotesCounter(false);

            if (imageRemoveWrap) imageRemoveWrap.style.display = 'block';
            if (imageRemove) imageRemove.checked = false;

            if (thumb && thumbImg && thumbMeta && parseInt(imageId, 10) > 0 && imageUrl) {
              thumb.style.display = 'flex';
              thumbImg.style.backgroundImage = "url('" + imageUrl.replace(/'/g, "\\'") + "')";
              thumbMeta.textContent = 'Current photo is set. Uploading a new one will replace it.';
            } else if (thumb) {
              thumb.style.display = 'none';
            }
          }

          if (notesEl) {
            notesEl.addEventListener('input', function () {
              syncVehicleNotesCounter(true);
            });
          }

          if (form) {
            form.addEventListener('submit', function (e) {
              if (!syncVehicleNotesCounter(true)) {
                e.preventDefault();
                notesEl.focus();
              }
            });
          }

          if (addCard) {
            addCard.addEventListener('click', function () {
              resetFormToAdd();
              openModal();
            });

            addCard.addEventListener('keydown', function (e) {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                resetFormToAdd();
                openModal();
              }
            });
          }

          if (editBtns && editBtns.length) {
            editBtns.forEach(function (btn) {
              btn.addEventListener('click', function () {
                fillFormForEdit(btn);
                openModal();
              });
            });
          }

          if (delBtns && delBtns.length) {
            delBtns.forEach(function (btn) {
              btn.addEventListener('click', function () {
                var vehicleId = btn.getAttribute('data-vehicle-id') || '';
                var title = btn.getAttribute('data-title') || 'this vehicle';

                if (!vehicleId || !deleteForm || !deleteVehicleId) return;

                var ok = window.confirm('Remove ' + title + ' from your Garage? This cannot be undone.');
                if (!ok) return;

                deleteVehicleId.value = vehicleId;
                deleteForm.submit();
              });
            });
          }

          document.addEventListener('click', function (e) {
            var t = e.target;
            if (!t) return;

            if (t.matches('[data-sccc-modal-close="1"]')) {
              e.preventDefault();
              closeModal();
            }
          });

          document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && modal.getAttribute('aria-hidden') === 'false') {
              closeModal();
            }
          });

          if (makeSelect) {
            makeSelect.addEventListener('change', syncOther);
            syncOther();
          }

          syncVehicleNotesCounter(false);
        })();
      </script>
    </section>

  @else

    {{-- Customer/non-member: keep it simple for now --}}
    <div class="sccc-card">
      <div class="sccc-card__v" style="font-size:22px;">Welcome back</div>
      <div class="sccc-card__k" style="margin-top:8px;">
        Manage your orders and account details from here.
      </div>
    </div>

  @endif

</div>