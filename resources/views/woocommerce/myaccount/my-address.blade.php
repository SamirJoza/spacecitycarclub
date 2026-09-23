{{-- resources/views/woocommerce/myaccount/my-address.blade.php --}}
{{--
  File: resources/views/woocommerce/myaccount/my-address.blade.php

  Purpose
  ------------------------------------------------------------------------------
  Blade override for WooCommerce: templates/myaccount/my-address.php

  Goals (styling-only)
  ------------------------------------------------------------------------------
  - Keep Woo’s address logic intact (billing/shipping detection + filters).
  - Display addresses in a clean SCCC card layout inside the account shell.
  - Scope styles to .sccc-myaccount-shell.

  Notes
  ------------------------------------------------------------------------------
  - Woo uses wc_get_account_formatted_address($name) to render each address.
--}}

<style>
    /* ==========================================================================
       SCCC: Addresses endpoint styling (scoped)
       ========================================================================== */
  
    .sccc-myaccount-shell .sccc-address-intro{
      margin: 0 0 14px;
      font-size: 13px;
      font-weight: 800;
      opacity: .78;
      color: rgba(10,12,24,.86);
    }
  
    .sccc-myaccount-shell .sccc-address-grid{
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }
  
    @media (max-width: 900px){
      .sccc-myaccount-shell .sccc-address-grid{
        grid-template-columns: 1fr;
      }
    }
  
    .sccc-myaccount-shell .woocommerce-Address{
      border-radius: 18px;
      border: 1px solid rgba(0,0,0,.08);
      background: rgba(255,255,255,.85);
      box-shadow: 0 12px 30px rgba(0,0,0,.06);
      overflow: hidden;
    }
  
    .sccc-myaccount-shell .woocommerce-Address-title{
      padding: 14px 16px;
      border-bottom: 1px solid rgba(0,0,0,.08);
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap: 12px;
    }
  
    .sccc-myaccount-shell .woocommerce-Address-title h2{
      margin: 0;
      font-size: 16px;
      font-weight: 950;
      letter-spacing: -.01em;
      color: rgba(10,12,24,.92);
    }
  
    .sccc-myaccount-shell .woocommerce-Address-title .edit{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding: 7px 10px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 900;
      text-decoration:none;
      border: 1px solid rgba(0,0,0,.10);
      background: rgba(0,0,0,.02);
      color: rgba(10,12,24,.86);
      white-space: nowrap;
    }
  
    .sccc-myaccount-shell .woocommerce-Address-title .edit:hover{
      background: rgba(0,0,0,.04);
    }
  
    .sccc-myaccount-shell .woocommerce-Address address{
      padding: 14px 16px 16px;
      margin: 0;
      font-size: 14px;
      line-height: 1.55;
      color: rgba(10,12,24,.86);
      font-weight: 700;
    }
  
    .sccc-myaccount-shell .woocommerce-Address address .woocommerce-Address-title{
      border: none;
    }
  
    /* Dark theme */
    :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .sccc-address-intro{
      color: rgba(255,255,255,.86);
      opacity: .75;
    }
  
    :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .woocommerce-Address{
      border-color: rgba(255,255,255,.10);
      background: rgba(255,255,255,.06);
      box-shadow: none;
    }
  
    :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .woocommerce-Address-title{
      border-bottom-color: rgba(255,255,255,.10);
    }
  
    :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .woocommerce-Address-title h2{
      color: rgba(255,255,255,.96);
    }
  
    :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .woocommerce-Address-title .edit{
      border-color: rgba(255,255,255,.12);
      background: rgba(255,255,255,.06);
      color: rgba(255,255,255,.90);
    }
  
    :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .woocommerce-Address-title .edit:hover{
      background: rgba(255,255,255,.08);
    }
  
    :is(html.dark, html[data-theme="dark"]) .sccc-myaccount-shell .woocommerce-Address address{
      color: rgba(255,255,255,.88);
    }
  </style>
  
  @php
    $customer_id = get_current_user_id();
  
    if (!wc_ship_to_billing_address_only() && wc_shipping_enabled()) {
      $get_addresses = apply_filters(
        'woocommerce_my_account_get_addresses',
        [
          'billing'  => __('Billing address', 'woocommerce'),
          'shipping' => __('Shipping address', 'woocommerce'),
        ],
        $customer_id
      );
    } else {
      $get_addresses = apply_filters(
        'woocommerce_my_account_get_addresses',
        [
          'billing' => __('Billing address', 'woocommerce'),
        ],
        $customer_id
      );
    }
  @endphp
  
  <p class="sccc-address-intro">
    @php
      // Keep Woo’s filter for this description text.
      echo apply_filters(
        'woocommerce_my_account_my_address_description',
        esc_html__('The following addresses will be used on the checkout page by default.', 'woocommerce')
      );
    @endphp
  </p>
  
  <div class="sccc-address-grid woocommerce-Addresses addresses">
    @foreach ($get_addresses as $name => $address_title)
      @php $address = wc_get_account_formatted_address($name); @endphp
  
      <div class="woocommerce-Address">
        <header class="woocommerce-Address-title title">
          <h2>{{ esc_html($address_title) }}</h2>
  
          <a href="{{ esc_url(wc_get_endpoint_url('edit-address', $name)) }}" class="edit">
            @php
              printf(
                $address ? esc_html__('Edit %s', 'woocommerce') : esc_html__('Add %s', 'woocommerce'),
                esc_html($address_title)
              );
            @endphp
          </a>
        </header>
  
        <address>
          @if ($address)
            {!! wp_kses_post($address) !!}
          @else
            {{ esc_html__('You have not set up this type of address yet.', 'woocommerce') }}
          @endif
        </address>
      </div>
    @endforeach
  </div>
  