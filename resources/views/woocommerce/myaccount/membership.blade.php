{{--
  resources/views/woocommerce/myaccount/membership.blade.php
  File: resources/views/woocommerce/myaccount/membership.blade.php

  Purpose
  ------------------------------------------------------------------------------
  Presentation for the custom “Membership” endpoint.
  The endpoint logic builds HTML in PHP and passes it in here.
--}}

<div class="space-y-8">
    <section class="bg-white dark:bg-surface-dark border border-gray-200 dark:border-border-dark rounded-2xl p-6 md:p-8 shadow-sm">
      {!! $membership_html !!}
    </section>
  
    <section class="bg-white dark:bg-surface-dark border border-gray-200 dark:border-border-dark rounded-2xl p-6 md:p-8 shadow-sm">
      <h2 class="text-xl font-extrabold dark:text-white mb-4">Invoices</h2>
      {!! $invoice_html !!}
    </section>
  </div>
  