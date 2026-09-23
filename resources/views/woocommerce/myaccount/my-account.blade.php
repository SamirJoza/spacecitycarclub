{{--
  resources/views/woocommerce/myaccount/my-account.blade.php
  File: resources/views/woocommerce/myaccount/my-account.blade.php

  Purpose
  ------------------------------------------------------------------------------
  Unified Woo "My Account" shell (sidebar + content) for shoppers and members.
  Presentation only. Logic/data is provided via WooMyAccountComposer.

  Theme handling (your setup)
  ------------------------------------------------------------------------------
  - Light theme: <html data-theme="light">
  - Dark theme:  <html data-theme="dark" class="dark">
  We support BOTH signals to avoid relying on just one.

  Scoping
  ------------------------------------------------------------------------------
  All styling is scoped to .sccc-myaccount-shell to avoid affecting other pages.

  IMPORTANT NOTE (logout fix)
  ------------------------------------------------------------------------------
  Blade's {{ }} already escapes output for HTML.
  If we ALSO call e() inside {{ }}, the URL gets double-escaped and we end up
  with &amp;amp; in the href, which breaks the _wpnonce query param and causes
  the WordPress logout confirmation screen.

  Therefore: use {{ $logout_url }} (escaped once) for the Sign Out link.

  PATCH (crop modal moved to AccountEditExtras)
  ------------------------------------------------------------------------------
  The avatar / business logo crop modal no longer lives in this My Account shell.
  It is rendered by app/Support/Woo/AccountEditExtras.php through wp_footer on
  the WooCommerce Account Details endpoint.

  Why:
  - Keeps the modal outside #app and outside the account shell
  - Restores viewport-safe centering for fixed-position modal UI
  - Avoids production issues where pushed footer content was not rendering
  - Prevents duplicate crop scripts from binding the same file inputs

  IMPORTANT:
  - This file owns the My Account shell, sidebar, content area, and endpoint CSS.
  - The crop modal DOM, modal CSS, and crop JavaScript are owned by
    AccountEditExtras so the modal can live directly near the end of <body>.
--}}

<style>
  /* ==========================================================================
     Theme selector helper (supports both dark signals)
     ========================================================================== */
  :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell { /* dark scope */ }

  /* ==========================================================================
     Base (Light theme) — clean and readable
     ========================================================================== */
  .sccc-myaccount-shell{
    display:flex;
    gap:0;
    min-height:70vh;
    border:1px solid rgba(0,0,0,.08);
    border-radius:18px;
    overflow:hidden;
    background: rgba(255,255,255,.75);
    box-shadow: 0 20px 50px rgba(0,0,0,.06);
  }

  .sccc-myaccount-sidebar{
    width:280px;
    padding:28px 18px;
    background: rgba(255,255,255,.80);
    border-right:1px solid rgba(0,0,0,.08);
  }

  .sccc-myaccount-content{
    flex:1;
    padding:28px 28px 80px;
    background: rgba(255,255,255,.55);
  }

  @media (max-width: 1024px){
    .sccc-myaccount-shell{ flex-direction:column; }
    .sccc-myaccount-sidebar{
      width:100%;
      border-right:none;
      border-bottom:1px solid rgba(0,0,0,.08);
    }
  }

  .sccc-myaccount-mini{
    display:flex;
    gap:12px;
    align-items:center;
    padding:0 8px 18px;
    margin-bottom:10px;
  }

  .sccc-myaccount-avatar{
    width:52px;
    height:52px;
    border-radius:999px;
    background:#eee;
    border:2px solid rgba(19,91,236,.35);
    background-size:cover;
    background-position:center;
  }

  .sccc-myaccount-name{
    font-weight:900;
    line-height:1.2;
    color: rgba(10,12,24,.95);
  }

  .sccc-myaccount-pill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:4px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:900;
    margin-top:6px;
    border:1px solid rgba(0,0,0,.10);
    background: rgba(0,0,0,.03);
    color: rgba(10,12,24,.75);
  }

  .sccc-myaccount-pill--member{
    background: rgba(19,91,236,.10);
    border-color: rgba(19,91,236,.22);
    color: rgba(19,91,236,.95);
  }

  .sccc-myaccount-nav{
    display:flex;
    flex-direction:column;
    gap:6px;
    margin-top:10px;
  }

  .sccc-myaccount-link{
    display:flex;
    align-items:center;
    gap:10px;
    padding:10px 12px;
    border-radius:12px;
    color: rgba(10,12,24,.70);
    text-decoration:none;
    transition: background .15s ease, color .15s ease, box-shadow .15s ease;
  }

  .sccc-myaccount-link:hover{
    background: rgba(0,0,0,.04);
    color: rgba(10,12,24,.92);
  }

  .sccc-myaccount-link.is-active{
    background: rgba(19,91,236,.95);
    color:#fff;
    box-shadow: 0 12px 28px rgba(19,91,236,.25);
  }

  .sccc-myaccount-link__icon{
    width:18px;
    display:inline-flex;
    justify-content:center;
    opacity:.9;
  }

  .sccc-myaccount-divider{
    margin-top:18px;
    padding-top:18px;
    border-top:1px solid rgba(0,0,0,.08);
  }

  .sccc-myaccount-logout{
    color: rgba(10,12,24,.65);
  }
  .sccc-myaccount-logout:hover{
    color: rgba(220,38,38,.95);
    background: rgba(220,38,38,.06);
  }

  /* Woo notices - light */
  .sccc-myaccount-shell .woocommerce-message,
  .sccc-myaccount-shell .woocommerce-info,
  .sccc-myaccount-shell .woocommerce-error{
    border-radius:14px;
    border:1px solid rgba(0,0,0,.08);
    background: rgba(255,255,255,.85);
    color: rgba(10,12,24,.85);
  }

  /* ==========================================================================
     Dark theme overrides (supports html.dark OR html[data-theme="dark"])
     ========================================================================== */
  :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell{
    border-color: rgba(255,255,255,.08);
    background:
      radial-gradient(1200px 500px at 18% -10%, rgba(19,91,236,.16), transparent 60%),
      rgba(10,12,24,.55);
    box-shadow: none;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-sidebar{
    background: rgba(10,12,24,.60);
    border-right-color: rgba(255,255,255,.08);
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-content{
    background: transparent;
  }

  /* IMPORTANT:
     Use background-color (not background shorthand) so inline background-image
     never gets wiped out in dark mode. */
  :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-avatar{
    border-color: rgba(19, 91, 236, .65);
    background-color: #111;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-name{
    color:#fff;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-pill{
    border-color: rgba(255,255,255,.10);
    background: rgba(255,255,255,.06);
    color: rgba(255,255,255,.82);
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-pill--member{
    background: rgba(19,91,236,.14);
    border-color: rgba(19,91,236,.26);
    color:#7fb2ff;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-link{
    color: rgba(255,255,255,.70);
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-link:hover{
    background: rgba(255,255,255,.06);
    color:#fff;
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-divider{
    border-top-color: rgba(255,255,255,.08);
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-logout{
    color: rgba(255,255,255,.70);
  }

  :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-logout:hover{
    color:#ff6b6b;
    background: rgba(255,107,107,.10);
  }

  /* Woo notices - dark */
  :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .woocommerce-message,
  :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .woocommerce-info,
  :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .woocommerce-error{
    border-color: rgba(255,255,255,.10);
    background: rgba(255,255,255,.06);
    color: rgba(255,255,255,.86);
  }

  /* ==========================================================================
     Edit Account (Woo endpoint: edit-account)
     ========================================================================== */

  body.woocommerce-edit-account .sccc-myaccount-shell form[data-sccc-edit-account="1"]{ margin-top: 4px; }

  /* Card base (light) */
  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-card{
    background: rgba(255,255,255,.85);
    border: 1px solid rgba(0,0,0,.08);
    border-radius: 18px;
    padding: 18px;
    box-shadow: 0 12px 30px rgba(0,0,0,.06);
    margin-bottom: 16px;
  }

  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-card__head{
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin: 0 0 14px;
  }

  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-card__title{
    font-size: 20px;
    font-weight: 900;
    letter-spacing: -.02em;
    margin: 0;
    color: rgba(10,12,24,.92);
  }

  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-card__sub{
    margin: 6px 0 0;
    font-size: 13px;
    font-weight: 800;
    opacity: .72;
    color: rgba(10,12,24,.82);
  }

  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid{
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
  }
  @media (max-width: 900px){
    body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid{ grid-template-columns: 1fr; }
  }

  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid .woocommerce-form-row{
    float: none;
    width: auto;
    margin: 0;
  }
  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid .clear{ display: none; }
  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid .form-row-wide{ grid-column: 1 / -1; }

  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-card--core label{
    display: block;
    font-size: 13px;
    font-weight: 900;
    opacity: .70;
    margin-bottom: 8px;
  }

  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-label{
    display: block;
    font-size: 13px;
    font-weight: 900;
    opacity: .70;
    margin-bottom: 8px;
  }

  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid .woocommerce-Input,
  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid input.input-text,
  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid select,
  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid textarea{
    width: 100%;
    padding: 12px 12px;
    border-radius: 12px;
    background: rgba(0,0,0,.02);
    border: 1px solid rgba(0,0,0,.10);
    color: rgba(10,12,24,.92);
    box-shadow: none;
  }

  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid .woocommerce-Input:focus,
  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid input.input-text:focus,
  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid select:focus,
  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid textarea:focus{
    outline: none;
    border-color: rgba(19,91,236,.55);
    box-shadow: 0 0 0 4px rgba(19,91,236,.12);
  }

  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid__full{ grid-column: 1 / -1; }

  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-avatarrow{
    display: flex;
    gap: 14px;
    align-items: center;
    flex-wrap: wrap;
    padding: 14px;
    border-radius: 14px;
    border: 1px solid rgba(0,0,0,.08);
    background: rgba(0,0,0,.02);
  }
  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-avatar{
    width: 64px;
    height: 64px;
    border-radius: 999px;
    border: 2px solid rgba(19,91,236,.35);
    background: #eee;
    background-size: cover;
    background-position: center;
    flex: 0 0 auto;
  }
  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-help{
    display: block;
    font-size: 12px;
    font-weight: 800;
    opacity: .70;
    margin-top: 6px;
  }

  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-upload{
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }

  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-fileinput{
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
  }

  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-filebtn{
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    padding: 7px 10px;
    font-size: 13px;
    line-height: 1;
    border-radius: 10px;
    font-weight: 900;
    cursor: pointer !important;
    user-select: none;
    background: rgba(19,91,236,.95);
    color: #fff;
    border: 1px solid rgba(19,91,236,.25);
    box-shadow: 0 10px 22px rgba(19,91,236,.18);
    margin: 0 !important;
    text-decoration: none;
  }

  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-filename{
    display: inline-flex;
    align-items: center;
    padding: 7px 10px;
    font-size: 13px;
    line-height: 1;
    border-radius: 10px;
    border: 1px solid rgba(0,0,0,.10);
    background: rgba(255,255,255,.60);
    font-weight: 900;
    color: rgba(10,12,24,.80);
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-filename[data-has-file="1"]{
    border-color: rgba(19,91,236,.22);
    background: rgba(19,91,236,.08);
    color: rgba(19,91,236,.95);
  }

  body.woocommerce-edit-account .sccc-myaccount-shell form[data-sccc-edit-account="1"] .woocommerce-Button{
    margin-top: 14px;
    padding: 12px 16px;
    border-radius: 14px;
    background: rgba(19,91,236,.95);
    color: #fff;
    font-weight: 900;
    border: none;
    box-shadow: 0 14px 34px rgba(19,91,236,.20);
  }

  :is(html.dark, html[data-theme="dark"]) body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-card{
    background: rgba(255,255,255,.06);
    border-color: rgba(255,255,255,.10);
    box-shadow: none;
  }

  :is(html.dark, html[data-theme="dark"]) body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-card__title{ color:#fff; }
  :is(html.dark, html[data-theme="dark"]) body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-card__sub{ color: rgba(255,255,255,.86); }

  :is(html.dark, html[data-theme="dark"]) body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid .woocommerce-Input,
  :is(html.dark, html[data-theme="dark"]) body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid input.input-text,
  :is(html.dark, html[data-theme="dark"]) body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid select,
  :is(html.dark, html[data-theme="dark"]) body.woocommerce-edit-account .sccc-myaccount-shell .sccc-editaccount-grid textarea{
    background: rgba(255,255,255,.06);
    border-color: rgba(255,255,255,.10);
    color: rgba(255,255,255,.92);
  }

  /* ==========================================================================
   SCCC Orders — CSS only (NO template override)
   Scope: inside My Account shell + Orders endpoint body class
   ========================================================================== */

body.woocommerce-orders .sccc-myaccount-shell .woocommerce-MyAccount-content{
  min-width: 0;
}

/* ==========================================================================
   SCCC Orders — EMPTY STATE ALIGNMENT FIX
   Scope: Orders endpoint only
   ========================================================================== */

/* The notice row becomes a clean flex bar */
body.woocommerce-orders .sccc-myaccount-shell .woocommerce-info{
  position: relative;
  display:flex;
  align-items:center;
  gap: 12px;

  /* important: keep the message + button from colliding */
  justify-content: flex-start;

  padding: 16px 16px !important;
  border-radius: 16px !important;
}

/* Woo injects an icon via :before — reserve space and prevent overlap */
body.woocommerce-orders .sccc-myaccount-shell .woocommerce-info::before{
  position: static !important;     /* stops absolute-ish behavior some themes add */
  flex: 0 0 auto !important;
  margin: 0 6px 0 0 !important;    /* space between icon and text */
  align-self: flex-start;          /* matches the baseline better */
  top: auto !important;
  left: auto !important;
}

/* The message text should wrap nicely and not shove the button */
body.woocommerce-orders .sccc-myaccount-shell .woocommerce-info{
  /* give room for long text + keep button right */
}

body.woocommerce-orders .sccc-myaccount-shell .woocommerce-info{
  /* no-op – kept for readability */
}

/* Make sure the text node area can shrink and wrap */
body.woocommerce-orders .sccc-myaccount-shell .woocommerce-info{
  min-width: 0;
}

/* Button on the far right */
body.woocommerce-orders .sccc-myaccount-shell .woocommerce-info .button,
body.woocommerce-orders .sccc-myaccount-shell .woocommerce-info a.button{
  margin-left: auto !important;   /* pushes it to the right */
  flex: 0 0 auto !important;
  white-space: nowrap !important;
}

/* Mobile: stack button below so it never looks cramped */
@media (max-width: 640px){
  body.woocommerce-orders .sccc-myaccount-shell .woocommerce-info{
    flex-wrap: wrap;
    align-items: flex-start;
  }

  body.woocommerce-orders .sccc-myaccount-shell .woocommerce-info .button,
  body.woocommerce-orders .sccc-myaccount-shell .woocommerce-info a.button{
    margin-left: 0 !important;
    margin-top: 10px !important;
    width: 100% !important;
    justify-content: center !important;
  }
}

/* Dark mode keeps same alignment (colors handled elsewhere) */
:is(html.dark, html[data-theme="dark"]) body.woocommerce-orders .sccc-myaccount-shell .woocommerce-info::before{
  opacity: .95;
}


/* Button (Browse products / View / etc.) */
body.woocommerce-orders .sccc-myaccount-shell a.button,
body.woocommerce-orders .sccc-myaccount-shell .woocommerce-button,
body.woocommerce-orders .sccc-myaccount-shell .woocommerce-Button,
body.woocommerce-orders .sccc-myaccount-shell .button{
  display:inline-flex !important;
  align-items:center !important;
  justify-content:center !important;

  padding: 10px 14px !important;
  border-radius: 12px !important;

  font-weight: 950 !important;
  text-decoration: none !important;

  border: 1px solid rgba(19,91,236,.26) !important;
  background: rgba(19,91,236,.95) !important;
  color: #fff !important;

  box-shadow: 0 12px 26px rgba(19,91,236,.18) !important;
  transition: filter .15s ease, transform .15s ease, box-shadow .15s ease;
}

body.woocommerce-orders .sccc-myaccount-shell a.button:hover,
body.woocommerce-orders .sccc-myaccount-shell .button:hover{
  filter: brightness(1.05);
  transform: translateY(-1px);
}

/* Orders table (when orders exist) */
body.woocommerce-orders .sccc-myaccount-shell table.woocommerce-orders-table{
  width: 100% !important;
  max-width: 100% !important;
  border-collapse: separate !important;
  border-spacing: 0 !important;

  border-radius: 16px !important;
  overflow: hidden !important;

  border: 1px solid rgba(0,0,0,.10) !important;
  background: rgba(255,255,255,.85) !important;
  box-shadow: 0 12px 30px rgba(0,0,0,.06) !important;
}

body.woocommerce-orders .sccc-myaccount-shell table.woocommerce-orders-table thead th{
  background: rgba(0,0,0,.03) !important;
  color: rgba(10,12,24,.72) !important;
  border-bottom: 1px solid rgba(0,0,0,.08) !important;

  font-size: 12px !important;
  font-weight: 950 !important;
  letter-spacing: .06em !important;
  text-transform: uppercase !important;
  white-space: nowrap !important;

  padding: 12px 12px !important;
}

body.woocommerce-orders .sccc-myaccount-shell table.woocommerce-orders-table tbody td{
  color: rgba(10,12,24,.86) !important;
  border-bottom: 1px solid rgba(0,0,0,.06) !important;
  padding: 12px 12px !important;
  font-weight: 750 !important;
  vertical-align: middle !important;
}

body.woocommerce-orders .sccc-myaccount-shell table.woocommerce-orders-table tbody tr:last-child td{
  border-bottom: none !important;
}

/* Dark mode */
:is(html.dark, html[data-theme="dark"]) body.woocommerce-orders .sccc-myaccount-shell .woocommerce-info,
:is(html.dark, html[data-theme="dark"]) body.woocommerce-orders .sccc-myaccount-shell .woocommerce-message{
  border-color: rgba(255,255,255,.10) !important;
  background: rgba(255,255,255,.06) !important;
  color: rgba(255,255,255,.86) !important;
  box-shadow: none !important;
}

:is(html.dark, html[data-theme="dark"]) body.woocommerce-orders .sccc-myaccount-shell table.woocommerce-orders-table{
  border-color: rgba(255,255,255,.10) !important;
  background: rgba(255,255,255,.06) !important;
  box-shadow: none !important;
}

:is(html.dark, html[data-theme="dark"]) body.woocommerce-orders .sccc-myaccount-shell table.woocommerce-orders-table thead th{
  background: rgba(255,255,255,.06) !important;
  color: rgba(255,255,255,.78) !important;
  border-bottom-color: rgba(255,255,255,.10) !important;
}

:is(html.dark, html[data-theme="dark"]) body.woocommerce-orders .sccc-myaccount-shell table.woocommerce-orders-table tbody td{
  color: rgba(255,255,255,.88) !important;
  border-bottom-color: rgba(255,255,255,.08) !important;
}

/* Responsive overflow safety */
@media (max-width: 900px){
  body.woocommerce-orders .sccc-myaccount-shell table.woocommerce-orders-table{
    display:block !important;
    overflow-x:auto !important;
    -webkit-overflow-scrolling: touch !important;
  }
}

/* ==========================================================================
   SCCC — Woo My Account: View Order (Endpoint)
   Scope:
   - Only inside the members shell
   - Only on the "view order" endpoint
   Targets:
   - Order summary notice line
   - Order details table
   - Customer details + billing/shipping blocks
   - Link colors + spacing
   ========================================================================== */

   body.woocommerce-view-order .sccc-myaccount-shell .sccc-myaccount-content{
  /* give the endpoint content a little breathing room */
  min-width: 0;
}

/* --------------------------------------------------------------------------
   Page / headings
   -------------------------------------------------------------------------- */
body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-order{
  width: 100%;
}

body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-order h2,
body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-order h3{
  margin: 0 0 14px;
  font-weight: 950;
  letter-spacing: -.02em;
}

body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-order p{
  margin: 0 0 14px;
  font-weight: 700;
  line-height: 1.55;
}

/* The “Order #X was placed on …” line */
body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-order-overview,
body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-order > p:first-of-type{
  display: block;
  padding: 14px 16px;
  border-radius: 16px;
  border: 1px solid rgba(255,255,255,.10);
  background: rgba(255,255,255,.06);
  color: rgba(255,255,255,.86);
}

/* --------------------------------------------------------------------------
   Links (fix purple default)
   -------------------------------------------------------------------------- */
body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-order a{
  color: rgba(127,178,255,.95);
  text-decoration: none;
  font-weight: 800;
}
body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-order a:hover{
  text-decoration: underline;
}

/* Light mode link */
html:not(.dark)[data-theme="light"] body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-order a,
html:not(.dark):not([data-theme="dark"]) body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-order a{
  color: rgba(19,91,236,.95);
}

/* --------------------------------------------------------------------------
   Card surfaces (Order details + Customer details)
   -------------------------------------------------------------------------- */
body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-order-details,
body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-customer-details{
  margin-top: 14px;
  padding: 14px 14px 16px;
  border-radius: 18px;
  border: 1px solid rgba(255,255,255,.10);
  background: rgba(255,255,255,.06);
}

html:not(.dark)[data-theme="light"] body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-order-details,
html:not(.dark):not([data-theme="dark"]) body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-order-details,
html:not(.dark)[data-theme="light"] body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-customer-details,
html:not(.dark):not([data-theme="dark"]) body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-customer-details{
  border-color: rgba(0,0,0,.08);
  background: rgba(255,255,255,.85);
  box-shadow: 0 12px 30px rgba(0,0,0,.06);
}

/* Titles inside cards */
body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-order-details__title,
body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-column__title{
  margin: 0 0 12px;
  font-size: 18px;
  font-weight: 950;
  letter-spacing: -.02em;
}

/* --------------------------------------------------------------------------
   Order details table
   -------------------------------------------------------------------------- */
body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details{
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  overflow: hidden;
  border-radius: 14px;
  border: 1px solid rgba(255,255,255,.10);
  background: rgba(255,255,255,.04);
}

body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details thead th{
  padding: 12px 12px;
  font-size: 12px;
  font-weight: 950;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: rgba(255,255,255,.78);
  background: rgba(255,255,255,.06);
  border-bottom: 1px solid rgba(255,255,255,.10);
  white-space: nowrap;
}

body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details tbody td,
body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details tfoot th,
body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details tfoot td{
  padding: 12px 12px;
  border-bottom: 1px solid rgba(255,255,255,.08);
  color: rgba(255,255,255,.90);
  font-weight: 750;
  vertical-align: middle;
}

body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details tr:last-child td,
body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details tr:last-child th{
  border-bottom: none;
}

/* Light mode table */
html:not(.dark)[data-theme="light"] body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details,
html:not(.dark):not([data-theme="dark"]) body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details{
  border-color: rgba(0,0,0,.08);
  background: rgba(0,0,0,.01);
}
html:not(.dark)[data-theme="light"] body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details thead th,
html:not(.dark):not([data-theme="dark"]) body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details thead th{
  color: rgba(10,12,24,.70);
  background: rgba(0,0,0,.03);
  border-bottom-color: rgba(0,0,0,.08);
}
html:not(.dark)[data-theme="light"] body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details tbody td,
html:not(.dark):not([data-theme="dark"]) body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details tbody td,
html:not(.dark)[data-theme="light"] body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details tfoot th,
html:not(.dark):not([data-theme="dark"]) body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details tfoot th,
html:not(.dark)[data-theme="light"] body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details tfoot td,
html:not(.dark):not([data-theme="dark"]) body.woocommerce-view-order .sccc-myaccount-shell table.woocommerce-table--order-details tfoot td{
  color: rgba(10,12,24,.88);
  border-bottom-color: rgba(0,0,0,.06);
}

/* --------------------------------------------------------------------------
   Customer details columns (Billing / Shipping)
   -------------------------------------------------------------------------- */
body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-customer-details .woocommerce-columns{
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-top: 10px;
}

@media (max-width: 900px){
  body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-customer-details .woocommerce-columns{
    grid-template-columns: 1fr;
  }
}

body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-customer-details .woocommerce-column{
  border-radius: 16px;
  border: 1px solid rgba(255,255,255,.10);
  background: rgba(255,255,255,.04);
  padding: 12px 12px 14px;
}

html:not(.dark)[data-theme="light"] body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-customer-details .woocommerce-column,
html:not(.dark):not([data-theme="dark"]) body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-customer-details .woocommerce-column{
  border-color: rgba(0,0,0,.08);
  background: rgba(0,0,0,.01);
}

body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-customer-details address{
  margin: 10px 0 0;
  font-style: normal;
  line-height: 1.6;
  color: rgba(255,255,255,.86);
}

html:not(.dark)[data-theme="light"] body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-customer-details address,
html:not(.dark):not([data-theme="dark"]) body.woocommerce-view-order .sccc-myaccount-shell .woocommerce-customer-details address{
  color: rgba(10,12,24,.86);
}
/* ==========================================================================
   SCCC Event Bookings — Dark row hover (less harsh than white)
   Scope: .sccc-myaccount-shell .sccc-event-bookings
   ========================================================================== */

   :is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-bookings-table tbody tr{
  transition: background-color .15s ease;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-bookings-table tbody tr:hover{
  background: rgba(255,255,255,.04) !important; /* subtle lift */
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-bookings-table tbody tr:hover td{
  background: transparent !important; /* prevent per-cell white fills */
}
/* ==========================================================================
   SCCC Event Bookings — MPWEM Modal Polish (Light + Dark)
   Scope: .sccc-myaccount-shell .sccc-event-bookings
   ========================================================================== */

   .sccc-myaccount-shell .sccc-event-bookings .mpwem-modal{
  position: fixed !important;
  inset: 0 !important;
  z-index: 999999 !important;
  display: none; /* plugin toggles inline display */
  padding: 18px !important;
  overflow: auto !important;
  -webkit-overflow-scrolling: touch;
  background: rgba(0,0,0,.55) !important;
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal[style*="display:block"],
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal[style*="display: block"]{
  display: flex !important;
  align-items: flex-start;
  justify-content: center;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content{
  width: min(980px, 100%) !important;
  margin: 60px auto 24px !important;
  border-radius: 18px !important;
  overflow: hidden !important;
  position: relative !important;
  box-shadow: 0 30px 90px rgba(0,0,0,.45) !important;
}

/* Close button */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-close{
  position: absolute !important;
  top: 14px !important;
  right: 14px !important;
  width: 38px !important;
  height: 38px !important;
  border-radius: 12px !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  font-size: 22px !important;
  line-height: 1 !important;
  cursor: pointer !important;
  user-select: none !important;
  border: 1px solid transparent !important;
  background: rgba(0,0,0,.06) !important;
  color: rgba(10,12,24,.85) !important;
}
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-close:hover{
  filter: brightness(1.05);
}

/* Modal body padding + typography */
.sccc-myaccount-shell .sccc-event-bookings #mpwem-booking-details-content{
  padding: 18px 18px 20px !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content h1,
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content h2,
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content h3{
  margin: 0 0 10px !important;
  font-weight: 950 !important;
  letter-spacing: -.02em !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content p{
  margin: 0 0 10px !important;
  font-weight: 700 !important;
}

/* Make “sections” inside the modal feel like cards */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content :is(
  .mpwem-booking-details,
  .mpwem-order-info,
  .mpwem-attendee-details,
  .mpwem-section,
  .mpwem-box,
  .mpwem-panel
){
  border-radius: 16px !important;
  padding: 14px !important;
  margin: 12px 0 !important;
}

/* Fix the inner white attendee cards (the culprit in your screenshot) */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content :is(
  .mpwem-attendee,
  .mpwem-attendee-item,
  .mpwem-attendee-card,
  .mpwem-attendee-details > div,
  .mpwem-attendee-details .mpwem-box
){
  border-radius: 14px !important;
  padding: 14px !important;
  border: 1px solid rgba(0,0,0,.08) !important;
  background: rgba(255,255,255,.92) !important;
  color: rgba(10,12,24,.88) !important;
}

/* Buttons inside modal */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content :is(a, button).mpwem-btn,
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content :is(a, button).button{
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  padding: 10px 14px !important;
  border-radius: 12px !important;
  font-weight: 900 !important;
  text-decoration: none !important;
  border: 1px solid rgba(0,0,0,.10) !important;
  background: rgba(0,0,0,.03) !important;
  color: rgba(10,12,24,.92) !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content :is(a, button).mpwem-btn:hover,
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content :is(a, button).button:hover{
  filter: brightness(1.04);
}

/* ==========================================================================
   DARK THEME
   ========================================================================== */

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal{
  background: rgba(0,0,0,.68) !important;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content{
  background:
    radial-gradient(1200px 500px at 18% -10%, rgba(19,91,236,.16), transparent 60%),
    rgba(10,12,24,.94) !important;
  border: 1px solid rgba(255,255,255,.10) !important;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-close{
  background: rgba(255,255,255,.06) !important;
  border-color: rgba(255,255,255,.10) !important;
  color: rgba(255,255,255,.92) !important;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings #mpwem-booking-details-content{
  color: rgba(255,255,255,.88) !important;
}

/* Section cards (dark) */
:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content :is(
  .mpwem-booking-details,
  .mpwem-order-info,
  .mpwem-attendee-details,
  .mpwem-section,
  .mpwem-box,
  .mpwem-panel
){
  border: 1px solid rgba(255,255,255,.10) !important;
  background: rgba(255,255,255,.06) !important;
}

/* Inner attendee cards (dark) — remove the bright white */
:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content :is(
  .mpwem-attendee,
  .mpwem-attendee-item,
  .mpwem-attendee-card,
  .mpwem-attendee-details > div,
  .mpwem-attendee-details .mpwem-box
){
  border: 1px solid rgba(255,255,255,.10) !important;
  background: rgba(255,255,255,.05) !important;
  color: rgba(255,255,255,.86) !important;
}

/* Buttons (dark) */
:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content :is(a, button).mpwem-btn,
:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content :is(a, button).button{
  border-color: rgba(255,255,255,.12) !important;
  background: rgba(255,255,255,.06) !important;
  color: rgba(255,255,255,.92) !important;
}

/* ==========================================================================
   SCCC Event Bookings — MPWEM Booking Details Modal (FULL BLOCK)
   Scope: .sccc-myaccount-shell .sccc-event-bookings
   Supports: html.dark OR html[data-theme="dark"]
   ========================================================================== */

/* --- Overlay container --------------------------------------------------- */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal{
  position: fixed !important;
  inset: 0 !important;
  z-index: 999999 !important;

  /* plugin toggles inline display; we normalize layout when open */
  padding: 18px !important;
  overflow: auto !important;
  -webkit-overflow-scrolling: touch;

  background: rgba(0,0,0,.55) !important;
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}

/* When plugin sets display:block, treat it like a flex overlay */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal[style*="display: block"],
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal[style*="display:block"]{
  display: flex !important;
  align-items: flex-start !important;
  justify-content: center !important;
}

/* --- Modal panel --------------------------------------------------------- */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content{
  width: min(980px, 100%) !important;
  margin: 64px auto 24px !important;

  border-radius: 18px !important;
  overflow: hidden !important;
  position: relative !important;

  background: rgba(255,255,255,.96) !important;
  border: 1px solid rgba(0,0,0,.10) !important;
  box-shadow: 0 30px 90px rgba(0,0,0,.35) !important;
}

/* Make inner content scroll instead of the whole page (better UX) */
.sccc-myaccount-shell .sccc-event-bookings #mpwem-booking-details-content{
  max-height: calc(100dvh - 140px) !important;
  overflow: auto !important;
  padding: 18px !important;
}

/* --- Close button -------------------------------------------------------- */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-close{
  position: absolute !important;
  top: 12px !important;
  right: 12px !important;

  width: 38px !important;
  height: 38px !important;
  border-radius: 12px !important;

  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;

  font-size: 22px !important;
  line-height: 1 !important;
  cursor: pointer !important;
  user-select: none !important;

  background: rgba(0,0,0,.06) !important;
  border: 1px solid rgba(0,0,0,.10) !important;
  color: rgba(10,12,24,.88) !important;
}
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-close:hover{
  filter: brightness(1.05);
}

/* --- Booking header block ------------------------------------------------ */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-booking-details{
  border-radius: 16px !important;
}

/* Title row */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-booking-header{
  padding: 14px 14px 12px !important;
  border-radius: 16px !important;
  border: 1px solid rgba(0,0,0,.08) !important;
  background: rgba(0,0,0,.02) !important;
  margin-bottom: 12px !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-booking-header h3{
  margin: 0 0 10px !important;
  font-weight: 950 !important;
  letter-spacing: -.02em !important;
  color: rgba(10,12,24,.95) !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-booking-meta{
  display: flex !important;
  flex-wrap: wrap !important;
  gap: 10px 16px !important;
  align-items: center !important;
  color: rgba(10,12,24,.80) !important;
  font-weight: 750 !important;
}

/* Status pill */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-status{
  display: inline-flex !important;
  align-items: center !important;
  padding: 6px 10px !important;
  border-radius: 999px !important;
  font-weight: 900 !important;
  font-size: 12px !important;
  border: 1px solid rgba(0,0,0,.10) !important;
  background: rgba(0,0,0,.03) !important;
}

/* Example: processing */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-status-processing{
  border-color: rgba(245,158,11,.30) !important;
  background: rgba(245,158,11,.14) !important;
  color: rgba(146,64,14,.95) !important;
}

/* --- Sections ------------------------------------------------------------ */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-section{
  border-radius: 16px !important;
  border: 1px solid rgba(0,0,0,.08) !important;
  background: rgba(255,255,255,.85) !important;
  padding: 14px !important;
  margin: 12px 0 !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-section h4{
  margin: 0 0 10px !important;
  font-weight: 950 !important;
  color: rgba(10,12,24,.92) !important;
}

/* Attendee cards */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-attendee-card{
  border-radius: 14px !important;
  border: 1px solid rgba(0,0,0,.08) !important;
  background: rgba(0,0,0,.02) !important;
  padding: 12px !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-attendee-header h5{
  margin: 0 0 8px !important;
  font-weight: 950 !important;
  color: rgba(10,12,24,.90) !important;
}

/* KILL inline grey + italic in attendee info */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-attendee-info p[style]{
  color: rgba(10,12,24,.72) !important;
  font-style: italic !important;
  margin: 0 !important;
  font-weight: 750 !important;
}

/* Order info table */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-order-table{
  width: 100% !important;
  border-collapse: collapse !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-order-table td{
  padding: 10px 0 !important;
  border-bottom: 1px solid rgba(0,0,0,.08) !important;
  color: rgba(10,12,24,.86) !important;
  font-weight: 750 !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-order-table tr:last-child td{
  border-bottom: none !important;
}

/* Footer button */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-booking-footer{
  margin-top: 12px !important;
  display: flex !important;
  justify-content: flex-start !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-booking-footer .mpwem-btn{
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  padding: 10px 14px !important;
  border-radius: 12px !important;
  font-weight: 900 !important;
  text-decoration: none !important;

  border: 1px solid rgba(0,0,0,.12) !important;
  background: rgba(0,0,0,.03) !important;
  color: rgba(10,12,24,.92) !important;
}
.sccc-myaccount-shell .sccc-event-bookings .mpwem-booking-footer .mpwem-btn:hover{
  filter: brightness(1.05);
}

/* ==========================================================================
   DARK MODE
   ========================================================================== */

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal{
  background: rgba(0,0,0,.70) !important;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content{
  background:
    radial-gradient(1200px 500px at 18% -10%, rgba(19,91,236,.16), transparent 60%),
    rgba(10,12,24,.94) !important;
  border-color: rgba(255,255,255,.10) !important;
  box-shadow: 0 30px 90px rgba(0,0,0,.55) !important;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-close{
  background: rgba(255,255,255,.06) !important;
  border-color: rgba(255,255,255,.12) !important;
  color: rgba(255,255,255,.92) !important;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-booking-header{
  border-color: rgba(255,255,255,.10) !important;
  background: rgba(255,255,255,.05) !important;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-booking-header h3{
  color: rgba(255,255,255,.96) !important;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-booking-meta{
  color: rgba(255,255,255,.82) !important;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-status{
  border-color: rgba(255,255,255,.12) !important;
  background: rgba(255,255,255,.06) !important;
  color: rgba(255,255,255,.90) !important;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-status-processing{
  border-color: rgba(245,158,11,.35) !important;
  background: rgba(245,158,11,.18) !important;
  color: rgba(253,230,138,.95) !important;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-section{
  border-color: rgba(255,255,255,.10) !important;
  background: rgba(255,255,255,.06) !important;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-section h4{
  color: rgba(255,255,255,.92) !important;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-attendee-card{
  border-color: rgba(255,255,255,.10) !important;
  background: rgba(255,255,255,.05) !important;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-attendee-header h5{
  color: rgba(255,255,255,.90) !important;
}

/* Beat the inline style inside attendee message in dark mode */
:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-attendee-info p[style]{
  color: rgba(255,255,255,.78) !important;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-order-table td{
  border-bottom-color: rgba(255,255,255,.10) !important;
  color: rgba(255,255,255,.88) !important;
}

:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-booking-footer .mpwem-btn{
  border-color: rgba(255,255,255,.12) !important;
  background: rgba(255,255,255,.06) !important;
  color: rgba(255,255,255,.92) !important;
}

/* Make meta labels ("Order Date:", "Status:") readable */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-booking-meta strong{
  color: rgba(10,12,24,.92) !important;
  font-weight: 950 !important;
}

/* Dark mode version */
:is(html.dark, html[data-theme="dark"])
.sccc-myaccount-shell .sccc-event-bookings .mpwem-booking-meta strong{
  color: rgba(255,255,255,.92) !important;
}
</style>

@php
  $active = fn(string $key) => ($current_endpoint === $key) ? 'is-active' : '';

  $icons = [
    'dashboard'     => '▦',
    'membership'    => '★',
    'orders'        => '🛍',
    'edit-account'  => '👤',
    'addresses'     => '🏠',
    'customer-logout' => '↩',
  ];
@endphp

<div class="sccc-myaccount-shell">

  {{-- Sidebar --}}
  <aside class="sccc-myaccount-sidebar">
    <div class="sccc-myaccount-mini">
      <div class="sccc-myaccount-avatar" style="background-image:url('{{ $user_avatar_url }}')"></div>

      <div style="min-width:0;">
        <div class="sccc-myaccount-name" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
          {{ $user_display_name }}
        </div>

        <div class="sccc-myaccount-pill {{ $is_true_member ? 'sccc-myaccount-pill--member' : '' }}">
          {{ $is_true_member ? $rank : 'Account' }}
        </div>
      </div>
    </div>

    <nav class="sccc-myaccount-nav" aria-label="My Account Navigation">
      @foreach ($menu as $item)
        @php $icon = $icons[$item['key']] ?? '▦'; @endphp

        <a href="{{ $item['url'] }}"
           class="sccc-myaccount-link {{ $active($item['key']) }}">
          <span class="sccc-myaccount-link__icon" aria-hidden="true">{{ $icon }}</span>
          <span style="font-weight:800;">{{ $item['label'] }}</span>
        </a>
      @endforeach
    </nav>

    <div class="sccc-myaccount-divider">
      <a class="sccc-myaccount-link sccc-myaccount-logout" href="{{ $logout_url }}">
        <span class="sccc-myaccount-link__icon" aria-hidden="true">↩</span>
        <span style="font-weight:800;">Sign Out</span>
      </a>
    </div>
  </aside>

  {{-- Content --}}
  <main class="sccc-myaccount-content">
    @php do_action('woocommerce_account_content'); @endphp
  </main>

</div>