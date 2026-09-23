<?php
/**
 * SCCC - Membership Checkout Fields (WooCommerce)
 * ==============================================
 *
 * GOAL
 * ----
 * Collect "core member" info + ONE REQUIRED VEHICLE during checkout,
 * but ONLY when the cart contains a "membership plan" product.
 *
 * Membership products are identified by Woo product category slug:
 *   membership-plans  (taxonomy: product_cat)
 *
 * IMPORTANT:
 * - Normal customers buying merch should NOT see these fields.
 * - These fields should only appear on membership checkout flow.
 *
 * WHAT THIS ADDS TO CHECKOUT (membership-only)
 * --------------------------------------------
 * 1) Member Profile
 *    - Birth date (required)
 *    - Veteran / First Responder checkbox (optional)
 *      - If checked: show Type + Branch/Agency (both optional by default)
 *
 * 2) First Vehicle (required)
 *    - Year (dropdown 1900 -> current year + 1)
 *    - Make (dropdown of common makes, alphabetized, includes dead brands + "Other")
 *      - If Make = Other: show "Other Make" text field (required in that case)
 *    - Model (required text)
 *    - Nickname (optional)
 *    - Notes (optional)
 *
 * WHERE DATA IS SAVED
 * -------------------
 * A) Order meta (audit trail)
 * B) User meta (fallback + reporting)
 * C) ACF user fields + Vehicles repeater (if ACF is active)
 *
 * ACF FIELD EXPECTATIONS (must exist to save into ACF)
 * ---------------------------------------------------
 * User fields:
 *   - birth_date (date; Y-m-d)
 *   - is_veteran_first_responder (true/false)
 *   - veteran_type (select)
 *   - agency_branch (text)
 *
 * Vehicles repeater on user (field name: vehicles) with subfields:
 *   - year (select value e.g. "2024")
 *   - make_select (select value e.g. "Chevrolet" or "Other")
 *   - make_other (text when make_select == Other)
 *   - make_raw (computed canonical make, stored text)
 *   - model (text)
 *   - nickname (text)
 *   - notes (textarea)
 *   - image (image id)  <-- NOT collected at checkout
 *
 * UI/BEHAVIOR NOTES
 * -----------------
 * - Vet fields (Type/Agency) ONLY show if checkbox is checked (via JS).
 * - Make Other ONLY shows if Make == Other (via JS).
 * - Server-side save enforces that vet fields are blank when checkbox is unchecked.
 *
 * VERSION: 1.1.0
 */

if (!defined('ABSPATH')) exit;

/**
 * CONFIG: membership product category slug
 * Place all membership products inside this product category.
 */
const SCCC_MEMBERSHIP_PRODUCT_CAT = 'membership-plans';

/**
 * CONFIG: If true, "Vet Type" becomes required when the checkbox is checked.
 * (Optional: keep false for now.)
 */
const SCCC_REQUIRE_VET_TYPE_WHEN_CHECKED = false;

/**
 * Determine if cart contains a membership product.
 * We check if any cart item is in product_cat = membership-plans.
 */
function sccc_cart_has_membership_product(): bool
{
  if (!function_exists('WC') || !WC()->cart) return false;

  foreach (WC()->cart->get_cart() as $item) {
    $product_id = $item['product_id'] ?? 0;
    if ($product_id && has_term(SCCC_MEMBERSHIP_PRODUCT_CAT, 'product_cat', $product_id)) {
      return true;
    }
  }

  return false;
}

/**
 * Vehicle Make options (alphabetical), includes "dead/legacy" US brands.
 * This list MUST match the ACF Composer list to keep data consistent.
 */
function sccc_vehicle_make_options(): array
{
  return [
    '' => __('Select make…', 'sccc'),

    'Acura' => 'Acura',
    'AMC' => 'AMC',
    'Aston Martin' => 'Aston Martin',
    'Audi' => 'Audi',

    'Bentley' => 'Bentley',
    'BMW' => 'BMW',
    'Buick' => 'Buick',

    'Cadillac' => 'Cadillac',
    'Chevrolet' => 'Chevrolet',
    'Chrysler' => 'Chrysler',

    'DeLorean' => 'DeLorean',
    'Dodge' => 'Dodge',

    'Eagle' => 'Eagle',

    'Ferrari' => 'Ferrari',
    'Ford' => 'Ford',

    'GMC' => 'GMC',

    'Honda' => 'Honda',
    'Hummer' => 'Hummer',

    'Infiniti' => 'Infiniti',

    'Jeep' => 'Jeep',

    'Lamborghini' => 'Lamborghini',
    'Lexus' => 'Lexus',

    'Maserati' => 'Maserati',
    'Mazda' => 'Mazda',
    'McLaren' => 'McLaren',
    'Mercedes-Benz' => 'Mercedes-Benz',
    'Mercury' => 'Mercury',
    'MINI' => 'MINI',
    'Mitsubishi' => 'Mitsubishi',

    'Nissan' => 'Nissan',

    'Oldsmobile' => 'Oldsmobile',

    'Plymouth' => 'Plymouth',
    'Pontiac' => 'Pontiac',
    'Porsche' => 'Porsche',

    'Ram' => 'Ram',
    'Rolls-Royce' => 'Rolls-Royce',

    'Saturn' => 'Saturn',
    'Subaru' => 'Subaru',

    'Tesla' => 'Tesla',
    'Toyota' => 'Toyota',

    'Volkswagen' => 'Volkswagen',
    'Volvo' => 'Volvo',

    'Other' => __('Other / Not listed', 'sccc'),
  ];
}

/**
 * Vehicle Year options:
 * 1900 -> current year + 1 (descending so newest is at top).
 */
function sccc_vehicle_year_options(): array
{
  $max = (int) date('Y') + 1;
  $opts = ['' => __('Select year…', 'sccc')];

  for ($y = $max; $y >= 1900; $y--) {
    $opts[(string) $y] = (string) $y;
  }

  return $opts;
}

/**
 * Add checkout fields (membership-only).
 */
add_filter('woocommerce_checkout_fields', function (array $fields): array {
  if (!sccc_cart_has_membership_product()) return $fields;

  /**
   * Member Profile fields
   */
  $fields['billing']['sccc_birth_date'] = [
    'type'     => 'date',
    'label'    => __('Birth date', 'sccc'),
    'required' => true,
    'priority' => 120,
    'class'    => ['form-row-first'],
  ];

  $fields['billing']['sccc_vet_fr'] = [
    'type'     => 'checkbox',
    'label'    => __('I am a Veteran / First Responder', 'sccc'),
    'required' => false,
    'priority' => 121,
    'class'    => ['form-row-last'],
  ];

  // These two should only show when checkbox checked (handled by JS below).
  $fields['billing']['sccc_vet_fr_type'] = [
    'type'     => 'select',
    'label'    => __('Which applies?', 'sccc'),
    'required' => false,
    'options'  => [
      '' => __('Select one (optional)', 'sccc'),
      'veteran' => __('Veteran', 'sccc'),
      'first_responder' => __('First Responder', 'sccc'),
      'both' => __('Both', 'sccc'),
    ],
    'priority' => 122,
    'class'    => ['form-row-first', 'sccc-vet-details'],
  ];

  $fields['billing']['sccc_agency_branch'] = [
    'type'        => 'text',
    'label'       => __('Branch / Agency (optional)', 'sccc'),
    'required'    => false,
    'priority'    => 123,
    'class'       => ['form-row-last', 'sccc-vet-details'],
    'placeholder' => __('e.g., USMC, Army, Houston Fire, EMS…', 'sccc'),
  ];

  /**
   * First Vehicle fields (required)
   */
  $fields['billing']['sccc_vehicle_year'] = [
    'type'     => 'select',
    'label'    => __('Vehicle Year', 'sccc'),
    'required' => true,
    'priority' => 130,
    'class'    => ['form-row-first'],
    'options'  => sccc_vehicle_year_options(),
  ];

  $fields['billing']['sccc_vehicle_make_select'] = [
    'type'     => 'select',
    'label'    => __('Vehicle Make', 'sccc'),
    'required' => true,
    'priority' => 131,
    'class'    => ['form-row-last'],
    'options'  => sccc_vehicle_make_options(),
  ];

  // Only shown if Make == Other
  $fields['billing']['sccc_vehicle_make_other'] = [
    'type'        => 'text',
    'label'       => __('Other Make (if not listed)', 'sccc'),
    'required'    => false, // conditionally required in validation
    'priority'    => 132,
    'class'       => ['form-row-first', 'sccc-make-other'],
    'placeholder' => __('Example: Holden, Renault, Koenigsegg…', 'sccc'),
  ];

  $fields['billing']['sccc_vehicle_model'] = [
    'type'        => 'text',
    'label'       => __('Vehicle Model', 'sccc'),
    'required'    => true,
    'priority'    => 133,
    'class'       => ['form-row-last'],
    'placeholder' => __('e.g., Corvette, Wrangler…', 'sccc'),
  ];

  $fields['billing']['sccc_vehicle_nickname'] = [
    'type'        => 'text',
    'label'       => __('Vehicle Nickname (optional)', 'sccc'),
    'required'    => false,
    'priority'    => 134,
    'class'       => ['form-row-first'],
  ];

  $fields['billing']['sccc_vehicle_notes'] = [
    'type'     => 'textarea',
    'label'    => __('Vehicle Notes (optional)', 'sccc'),
    'required' => false,
    'priority' => 135,
    'class'    => ['form-row-wide'],
  ];

  return $fields;
}, 20);

/**
 * Validate membership-only checkout fields.
 */
add_action('woocommerce_checkout_process', function (): void {
  if (!sccc_cart_has_membership_product()) return;

  // Birth date required
  if (empty($_POST['sccc_birth_date'])) {
    wc_add_notice(__('Please enter your birth date for your membership profile.', 'sccc'), 'error');
  }

  // Vehicle required fields
  foreach (['sccc_vehicle_year', 'sccc_vehicle_make_select', 'sccc_vehicle_model'] as $key) {
    if (empty($_POST[$key])) {
      wc_add_notice(__('Please enter your vehicle year, make, and model for your membership profile.', 'sccc'), 'error');
      break;
    }
  }

  // Conditional: "Other Make" required if make_select == Other
  $make_select = sanitize_text_field($_POST['sccc_vehicle_make_select'] ?? '');
  $make_other  = sanitize_text_field($_POST['sccc_vehicle_make_other'] ?? '');

  if ($make_select === 'Other' && $make_other === '') {
    wc_add_notice(__('Please enter your vehicle make (Other).', 'sccc'), 'error');
  }

  // Optional: require vet type when checkbox checked
  $is_vet = !empty($_POST['sccc_vet_fr']);
  if ($is_vet && SCCC_REQUIRE_VET_TYPE_WHEN_CHECKED && empty($_POST['sccc_vet_fr_type'])) {
    wc_add_notice(__('Please select your Veteran / First Responder type.', 'sccc'), 'error');
  }
});

/**
 * Save the membership fields to Order meta + User meta + ACF (if available).
 */
add_action('woocommerce_checkout_create_order', function ($order): void {
  if (!sccc_cart_has_membership_product()) return;

  /**
   * Sanitize input
   */
  $birth = sanitize_text_field($_POST['sccc_birth_date'] ?? '');

  $vet = !empty($_POST['sccc_vet_fr']) ? '1' : '0';
  // Only keep these when vet checkbox is checked
  $vet_type = ($vet === '1') ? sanitize_text_field($_POST['sccc_vet_fr_type'] ?? '') : '';
  $agency   = ($vet === '1') ? sanitize_text_field($_POST['sccc_agency_branch'] ?? '') : '';

  $year       = sanitize_text_field($_POST['sccc_vehicle_year'] ?? '');
  $make_sel   = sanitize_text_field($_POST['sccc_vehicle_make_select'] ?? '');
  $make_other = sanitize_text_field($_POST['sccc_vehicle_make_other'] ?? '');
  $model      = sanitize_text_field($_POST['sccc_vehicle_model'] ?? '');
  $nickname   = sanitize_text_field($_POST['sccc_vehicle_nickname'] ?? '');
  $notes      = sanitize_textarea_field($_POST['sccc_vehicle_notes'] ?? '');

  // Compute canonical make_raw (for reporting/search)
  $make_raw = ($make_sel === 'Other' && $make_other !== '') ? $make_other : $make_sel;
  $make_raw = preg_replace('/\s+/', ' ', trim((string) $make_raw));

  /**
   * Save to ORDER META (audit trail)
   */
  $order->update_meta_data('_sccc_birth_date', $birth);
  $order->update_meta_data('_sccc_vet_fr', $vet);
  $order->update_meta_data('_sccc_vet_fr_type', $vet_type);
  $order->update_meta_data('_sccc_agency_branch', $agency);

  $order->update_meta_data('_sccc_vehicle_year', $year);
  $order->update_meta_data('_sccc_vehicle_make_select', $make_sel);
  $order->update_meta_data('_sccc_vehicle_make_other', $make_other);
  $order->update_meta_data('_sccc_vehicle_make_raw', $make_raw);
  $order->update_meta_data('_sccc_vehicle_model', $model);

  /**
   * Save to USER META and ACF (if we have a user)
   */
  $user_id = $order->get_user_id();
  if (!$user_id) return;

  // Plain user meta (fallback/reporting)
  update_user_meta($user_id, 'birth_date', $birth);
  update_user_meta($user_id, 'is_veteran_first_responder', $vet);
  update_user_meta($user_id, 'veteran_type', $vet_type);
  update_user_meta($user_id, 'agency_branch', $agency);

  // Vehicle index meta for later reporting ("members with corvette", etc.)
  $idx = strtolower(trim($make_raw . ' ' . $model));
  update_user_meta($user_id, 'vehicle_index', $idx);

  /**
   * ACF saving (if active)
   * We insert the FIRST vehicle into the vehicles repeater ONLY if they have no vehicles yet.
   */
  if (function_exists('get_field') && function_exists('update_field')) {
    // User-level fields
    update_field('birth_date', $birth, 'user_' . $user_id);
    update_field('is_veteran_first_responder', ($vet === '1'), 'user_' . $user_id);
    update_field('veteran_type', $vet_type, 'user_' . $user_id);
    update_field('agency_branch', $agency, 'user_' . $user_id);

    // Vehicles repeater row aligned to MemberProfile.php schema
    $vehicle_row = [
      'year'        => $year,
      'make_select' => $make_sel,
      'make_other'  => $make_other,
      'make_raw'    => $make_raw, // computed now, but MemberProfile save-hook also maintains it
      'model'       => $model,
      'nickname'    => $nickname,
      'notes'       => $notes,
      // image intentionally omitted at checkout
    ];

    $existing = get_field('vehicles', 'user_' . $user_id);
    if (!is_array($existing)) $existing = [];

    if (count($existing) === 0) {
      $existing[] = $vehicle_row;
      update_field('vehicles', $existing, 'user_' . $user_id);
    }
  }
}, 20);

/**
 * Front-end checkout UI behavior (JS)
 * - Toggle "Other Make" field when make_select == Other
 * - Toggle vet details fields when vet checkbox checked
 *
 * This is UI only; server-side save still enforces rules.
 */
add_action('wp_enqueue_scripts', function (): void {
  if (!function_exists('is_checkout') || !is_checkout()) return;
  if (!sccc_cart_has_membership_product()) return;

  $js = <<<JS
(function() {
  function toggleOtherMake() {
    var makeSelect = document.getElementById('sccc_vehicle_make_select');
    var otherWrap  = document.getElementById('sccc_vehicle_make_other_field');
    if (!makeSelect || !otherWrap) return;

    var isOther = (makeSelect.value === 'Other');
    otherWrap.style.display = isOther ? '' : 'none';

    if (!isOther) {
      var otherInput = document.getElementById('sccc_vehicle_make_other');
      if (otherInput) otherInput.value = '';
    }
  }

  function toggleVetDetails() {
    var vetCheckbox = document.getElementById('sccc_vet_fr');
    var typeWrap    = document.getElementById('sccc_vet_fr_type_field');
    var agencyWrap  = document.getElementById('sccc_agency_branch_field');
    if (!vetCheckbox) return;

    var show = !!vetCheckbox.checked;

    if (typeWrap)   typeWrap.style.display   = show ? '' : 'none';
    if (agencyWrap) agencyWrap.style.display = show ? '' : 'none';

    if (!show) {
      var typeInput   = document.getElementById('sccc_vet_fr_type');
      var agencyInput = document.getElementById('sccc_agency_branch');
      if (typeInput) typeInput.value = '';
      if (agencyInput) agencyInput.value = '';
    }
  }

  function init() {
    toggleOtherMake();
    toggleVetDetails();
  }

  document.addEventListener('change', function(e) {
    if (!e.target) return;
    if (e.target.id === 'sccc_vehicle_make_select') toggleOtherMake();
    if (e.target.id === 'sccc_vet_fr') toggleVetDetails();
  });

  document.addEventListener('DOMContentLoaded', init);
})();
JS;

  // Attach to Woo checkout JS
  wp_add_inline_script('wc-checkout', $js);
});
