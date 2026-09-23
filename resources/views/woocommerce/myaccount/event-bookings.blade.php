{{-- resources/views/woocommerce/myaccount/event-bookings.blade.php --}}
{{--
  File: resources/views/woocommerce/myaccount/event-bookings.blade.php

  Purpose
  ------------------------------------------------------------------------------
  Blade wrapper for the Event Booking Manager for WooCommerce "event-bookings"
  My Account endpoint.

  Important
  ------------------------------------------------------------------------------
  - This view does NOT replace plugin behavior.
  - It receives the plugin’s rendered HTML as $plugin_html and only wraps/styles it.
  - Styling is scoped to .sccc-event-bookings inside .sccc-myaccount-shell.

  Goal
  ------------------------------------------------------------------------------
  Fix dark-mode readability + “too white” panels/cards in the Event Bookings UI
  without breaking the plugin’s markup or JS.
--}}

<style>
 /* ==========================================================================
   SCCC Event Bookings — MPWEM (mage-eventpress) endpoint styling (FULL)
   File: resources/views/woocommerce/myaccount/event-bookings.blade.php

   Goals
   ----------------------------------------------------------------------------
   - Match SCCC “dashboard card” UI inside My Account
   - Light + Dark theme supported (html.dark OR html[data-theme="dark"])
   - Fix plugin/Woo defaults that cause:
       • white-on-white / unreadable text
       • white table header strip in dark mode
       • stats tiles overflowing off-screen
   - Restore colored stat numbers (blue/amber/green)
   - Scoped strictly to: .sccc-myaccount-shell .sccc-event-bookings

   Notes
   ----------------------------------------------------------------------------
   - MPWEM uses Woo table classes (shop_table / woocommerce-orders-table).
     We override those only within this endpoint wrapper.
   - We use !important where plugin/Woo CSS typically wins.
   ========================================================================== */

/* --------------------------------------------------------------------------
   Scope wrapper
   -------------------------------------------------------------------------- */
.sccc-myaccount-shell .sccc-event-bookings{
  width: 100%;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-event-bookings-dashboard {
  width: 100%;
}

/* --------------------------------------------------------------------------
   Header typography (shared)
   -------------------------------------------------------------------------- */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-dashboard-header{
  margin: 0 0 14px !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-dashboard-header h2{
  margin: 0 0 6px !important;
  font-weight: 950 !important;
  letter-spacing: -.02em !important;
  line-height: 1.15 !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-dashboard-description{
  margin: 0 !important;
  font-weight: 750 !important;
  line-height: 1.35 !important;
}

/* ==========================================================================
   LIGHT THEME
   ========================================================================== */

/* Header colors */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-dashboard-header h2{
  color: rgba(10,12,24,.94) !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-dashboard-description{
  color: rgba(10,12,24,.70) !important;
  opacity: 1 !important;
}

/* Filters panel (search + buttons + stats) */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-dashboard-filters{
  background: rgba(255,255,255,.85) !important;
  border: 1px solid rgba(0,0,0,.08) !important;
  border-radius: 16px !important;
  padding: 14px !important;
  box-shadow: 0 12px 30px rgba(0,0,0,.06) !important;
}

/* Search area layout */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-filter-group{
  display: flex !important;
  flex-wrap: wrap !important;
  gap: 10px !important;
  align-items: center !important;
}

/* Search input */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-search-input{
  flex: 1 1 320px !important;
  min-width: 220px !important;
  max-width: 100% !important;

  background: rgba(0,0,0,.02) !important;
  border: 1px solid rgba(0,0,0,.10) !important;
  color: rgba(10,12,24,.92) !important;

  border-radius: 12px !important;
  padding: 10px 12px !important;
  box-shadow: none !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-search-input::placeholder{
  color: rgba(10,12,24,.45) !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-search-input:focus{
  outline: none !important;
  border-color: rgba(19,91,236,.45) !important;
  box-shadow: 0 0 0 4px rgba(19,91,236,.12) !important;
}

/* Buttons */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-btn{
  border-radius: 12px !important;
  border: 1px solid rgba(0,0,0,.10) !important;
  background: rgba(0,0,0,.03) !important;
  color: rgba(10,12,24,.88) !important;
  box-shadow: none !important;

  font-weight: 900 !important;
  padding: 10px 12px !important;
  line-height: 1 !important;
  cursor: pointer !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-btn:hover{
  filter: brightness(1.03);
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-btn-search{
  border-color: rgba(19,91,236,.25) !important;
  background: rgba(19,91,236,.95) !important;
  color: #fff !important;
  box-shadow: 0 12px 26px rgba(19,91,236,.18) !important;
}

/* Stats row (tiles) */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-stats{
  display: grid !important;
  grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  gap: 12px !important;
  margin-top: 12px !important;

  width: 100% !important;
  max-width: 100% !important;
}

/* Each tile */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-stat-item{
  background: rgba(255,255,255,.85) !important;
  border: 1px solid rgba(0,0,0,.08) !important;
  border-radius: 16px !important;
  padding: 14px !important;
  box-shadow: 0 12px 30px rgba(0,0,0,.06) !important;

  text-align: center !important;

  /* overflow safety */
  min-width: 0 !important;
  max-width: 100% !important;
  box-sizing: border-box !important;
  width: auto !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-stat-number{
  display: block !important;
  font-weight: 950 !important;
  font-size: 30px !important;
  line-height: 1.1 !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-stat-label{
  display: block !important;
  color: rgba(10,12,24,.62) !important;
  font-weight: 900 !important;
  font-size: 12px !important;
  letter-spacing: .06em !important;
  text-transform: uppercase !important;
  margin-top: 6px !important;
}

/* Active tile */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-stat-item.active{
  background: rgba(19,91,236,.10) !important;
  border-color: rgba(19,91,236,.18) !important;
}

/* Stat number color coding (light) */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-stat-item[data-filter="all"] .mpwem-stat-number{
  color: rgba(19,91,236,.95) !important; /* blue */
}
.sccc-myaccount-shell .sccc-event-bookings .mpwem-stat-item[data-filter="upcoming"] .mpwem-stat-number{
  color: rgba(245,158,11,.95) !important; /* amber */
}
.sccc-myaccount-shell .sccc-event-bookings .mpwem-stat-item[data-filter="completed"] .mpwem-stat-number{
  color: rgba(34,197,94,.95) !important; /* green */
}

/* Bookings table wrapper */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-bookings-table-wrapper{
  margin-top: 14px !important;
  background: rgba(255,255,255,.85) !important;
  border: 1px solid rgba(0,0,0,.08) !important;
  border-radius: 16px !important;
  padding: 12px !important;
  box-shadow: 0 12px 30px rgba(0,0,0,.06) !important;
}

/* Table */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-bookings-table{
  width: 100% !important;
  background: transparent !important;
  border: none !important;
  border-radius: 14px !important;
  overflow: hidden !important;
  border-collapse: separate !important;
  border-spacing: 0 !important;
}

/* Table head */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-bookings-table thead th{
  background: rgba(0,0,0,.03) !important;
  color: rgba(10,12,24,.70) !important;
  border-bottom: 1px solid rgba(0,0,0,.08) !important;

  font-size: 12px !important;
  font-weight: 950 !important;
  letter-spacing: .06em !important;
  text-transform: uppercase !important;

  padding: 12px 12px !important;
  white-space: nowrap !important;
}

/* Table body cells */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-bookings-table tbody td{
  color: rgba(10,12,24,.86) !important;
  border-bottom: 1px solid rgba(0,0,0,.06) !important;
  padding: 12px 12px !important;
  font-weight: 750 !important;
  vertical-align: middle !important;
}

.sccc-myaccount-shell .sccc-event-bookings .mpwem-bookings-table tbody tr:last-child td{
  border-bottom: none !important;
}

/* Empty row */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-no-bookings{
  color: rgba(10,12,24,.70) !important;
  font-style: italic !important;
  text-align: center !important;
  padding: 22px 12px !important;
}

/* Modals (light-safe) */
.sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content{
  background: rgba(255,255,255,.95) !important;
  border: 1px solid rgba(0,0,0,.10) !important;
  border-radius: 16px !important;
  color: rgba(10,12,24,.90) !important;
}

/* ==========================================================================
   DARK THEME
   ========================================================================== */
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-dashboard-header h2{
  color: rgba(255,255,255,.96) !important;
}

:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-dashboard-description{
  color: rgba(255,255,255,.76) !important;
  opacity: 1 !important;
}

/* Filters panel (dark) */
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-dashboard-filters{
  background: rgba(255,255,255,.06) !important;
  border: 1px solid rgba(255,255,255,.10) !important;
  box-shadow: none !important;
}

/* Search input (dark) */
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-search-input{
  background: rgba(255,255,255,.06) !important;
  border: 1px solid rgba(255,255,255,.12) !important;
  color: rgba(255,255,255,.92) !important;
}

:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-search-input::placeholder{
  color: rgba(255,255,255,.55) !important;
}

:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-search-input:focus{
  outline: none !important;
  border-color: rgba(127,178,255,.55) !important;
  box-shadow: 0 0 0 4px rgba(127,178,255,.14) !important;
}

/* Buttons (dark) */
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-btn{
  border: 1px solid rgba(255,255,255,.12) !important;
  background: rgba(255,255,255,.06) !important;
  color: rgba(255,255,255,.90) !important;
  box-shadow: none !important;
}

:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-btn-search{
  border-color: rgba(19,91,236,.26) !important;
  background: rgba(19,91,236,.95) !important;
  color: #fff !important;
  box-shadow: 0 12px 26px rgba(19,91,236,.18) !important;
}

/* Stats tiles (dark) */
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-stat-item{
  background: rgba(255,255,255,.06) !important;
  border: 1px solid rgba(255,255,255,.10) !important;
  box-shadow: none !important;
}

:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-stat-label{
  color: rgba(255,255,255,.70) !important;
}

/* Active tile (dark) */
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-stat-item.active{
  background: rgba(19,91,236,.18) !important;
  border-color: rgba(19,91,236,.28) !important;
}

/* Stat number color coding (dark) */
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-stat-item[data-filter="all"] .mpwem-stat-number{
  color: rgba(127,178,255,.98) !important;
}
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-stat-item[data-filter="upcoming"] .mpwem-stat-number{
  color: rgba(253,186,116,.98) !important;
}
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-stat-item[data-filter="completed"] .mpwem-stat-number{
  color: rgba(134,239,172,.98) !important;
}

/* Table wrapper (dark) */
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-bookings-table-wrapper{
  background: rgba(255,255,255,.06) !important;
  border: 1px solid rgba(255,255,255,.10) !important;
  box-shadow: none !important;
}

/* Kill the white strip in dark mode: Woo shop_table thead/th bleed */
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings table.shop_table,
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings table.shop_table thead,
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings table.shop_table thead tr,
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings table.shop_table thead th{
  background: rgba(255,255,255,.06) !important;
}

/* Table head (dark) */
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-bookings-table thead th{
  background: rgba(255,255,255,.06) !important;
  color: rgba(255,255,255,.78) !important;
  border-bottom: 1px solid rgba(255,255,255,.10) !important;
}

/* Ensure cells don’t get forced white backgrounds */
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings table.shop_table td,
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings table.shop_table th{
  background-color: transparent !important;
}

/* Table body (dark) */
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-bookings-table tbody td{
  color: rgba(255,255,255,.88) !important;
  border-bottom: 1px solid rgba(255,255,255,.08) !important;
}

/* Empty row (dark) */
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-no-bookings{
  color: rgba(255,255,255,.82) !important;
}

/* Modals (dark-safe) */
:is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-event-bookings .mpwem-modal-content{
  background: rgba(10,12,24,.92) !important;
  border: 1px solid rgba(255,255,255,.10) !important;
  color: rgba(255,255,255,.90) !important;
}

/* --------------------------------------------------------------------------
   Responsive: stack stats tiles and keep filters tidy
   -------------------------------------------------------------------------- */
@media (max-width: 900px){
  .sccc-myaccount-shell .sccc-event-bookings .mpwem-stats{
    grid-template-columns: 1fr !important;
  }

  .sccc-myaccount-shell .sccc-event-bookings .mpwem-search-input{
    flex-basis: 100% !important;
  }
}

  </style>
  
  <div class="sccc-event-bookings" data-sccc-endpoint="event-bookings">
    {!! $plugin_html !!}
  </div>
  