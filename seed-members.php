<?php
/**
 * seed-members.php
 * ============================================================================
 * SCCC Local Seeder (WP-CLI) — Create / Rollback Dummy Members for Testing
 *
 * RUN (positional args after `--`)
 *   Seed 150:
 *     wp eval-file seed-members.php -- seed 150
 *
 *   Seed default 150:
 *     wp eval-file seed-members.php -- seed
 *
 *   Rollback latest run:
 *     wp eval-file seed-members.php -- rollback
 *
 *   Rollback specific run:
 *     wp eval-file seed-members.php -- rollback <run_id>
 *
 * FINALIZATION (GOOD STANDING RULE)
 * - Most seeded members are ACTIVE (enddate in the future).
 * - A smaller group is RECENTLY LAPSED:
 *     - enddate between Dec 1, 2025 and "now"
 *     - status set to 'expired'
 * - No one is overdue before Dec 1, 2025.
 */

if (!defined('WP_CLI') || !WP_CLI) {
  return;
}

const SCCC_SEED_OPTION_KEY = 'sccc_seed_member_runs';

// PMPro level IDs (from your local.sql)
const SCCC_LEVEL_MEMBER     = 3;
const SCCC_LEVEL_VETERAN_FR = 4;
const SCCC_LEVEL_JUNIOR     = 5;

// Roles
const SCCC_ROLE_SUBSCRIBER = 'subscriber';
const SCCC_ROLE_MEMBER     = 'sccc_member';

// Member meta / ACF field names (match your reports)
const SCCC_META_BIRTH_DATE    = 'birth_date';
const SCCC_META_ISSUED_AT     = 'membership_issued_at'; // used by your anniversary report
const SCCC_META_VET_ENABLED   = 'is_veteran_first_responder';
const SCCC_META_VET_TYPE      = 'veteran_type';
const SCCC_META_VET_BRANCH    = 'agency_branch';
const SCCC_ACF_VEHICLES       = 'vehicles';
const SCCC_META_VEHICLE_INDEX = 'vehicle_index';

// ---------------------------------------------
// Arg parsing: args after `--` using $_SERVER['argv']
// ---------------------------------------------
$argv = $_SERVER['argv'] ?? [];
$dashDashIndex = array_search('--', $argv, true);
$userArgs = $dashDashIndex !== false ? array_slice($argv, $dashDashIndex + 1) : [];

$mode = strtolower((string) ($userArgs[0] ?? 'seed')); // seed | rollback

if ($mode === 'rollback') {
  $runId = (string) ($userArgs[1] ?? '');
} else {
  $count = (int) ($userArgs[1] ?? 150);
  if ($count < 1) $count = 1;
  if ($count > 500) $count = 500;
}

// ---------------------------------------------
// Helpers
// ---------------------------------------------
function sccc_seed_load_runs(): array
{
  $runs = get_option(SCCC_SEED_OPTION_KEY, []);
  return is_array($runs) ? $runs : [];
}

function sccc_seed_save_runs(array $runs): void
{
  update_option(SCCC_SEED_OPTION_KEY, $runs, false);
}

function sccc_seed_find_run(array $runs, string $runId): ?array
{
  foreach ($runs as $r) {
    if (!empty($r['run_id']) && $r['run_id'] === $runId) {
      return $r;
    }
  }
  return null;
}

function sccc_seed_remove_run(array $runs, string $runId): array
{
  return array_values(array_filter($runs, function ($r) use ($runId) {
    return empty($r['run_id']) || $r['run_id'] !== $runId;
  }));
}

function sccc_seed_rand_dt(int $minTs, int $maxTs): DateTime
{
  $ts = random_int($minTs, $maxTs);
  $dt = new DateTime();
  $dt->setTimestamp($ts);
  return $dt;
}

function sccc_seed_pick(array $arr)
{
  return $arr[array_rand($arr)];
}

function sccc_seed_fake_name(): array
{
  $first = [
    'Alex','Jordan','Taylor','Casey','Morgan','Riley','Cameron','Drew','Avery','Peyton',
    'Jamie','Quinn','Dakota','Skyler','Reese','Rowan','Hayden','Elliot','Charlie','Emerson',
    'Noah','Liam','Mason','Ethan','Aiden','Lucas','Logan','Levi','Owen','Jack',
    'Olivia','Emma','Ava','Sophia','Mia','Amelia','Harper','Evelyn','Abigail','Ella',
  ];
  $last = [
    'Carter','Mitchell','Reed','Bennett','Hayes','Parker','Brooks','Foster','Sullivan','Coleman',
    'Turner','Morris','Murphy','Howard','Jenkins','Baker','Ward','Powell','Barnes','Hughes',
    'Gibson','Fisher','Bryant','Stone','Woods','West','Jordan','Holland','Hunt','Fox',
  ];
  return [sccc_seed_pick($first), sccc_seed_pick($last)];
}

function sccc_seed_vehicle_pool(): array
{
  return [
    'Chevrolet' => ['Corvette', 'Camaro', 'Silverado', 'Tahoe', 'Suburban', 'Malibu'],
    'Ford'      => ['Mustang', 'F-150', 'Bronco', 'Explorer', 'Focus', 'Fusion'],
    'Dodge'     => ['Challenger', 'Charger', 'Durango', 'Ram 1500'],
    'Tesla'     => ['Model 3', 'Model S', 'Model Y', 'Model X'],
    'Toyota'    => ['Supra', 'Camry', 'Corolla', 'Tacoma', '4Runner'],
    'Honda'     => ['Civic', 'Accord', 'CR-V', 'S2000'],
    'Nissan'    => ['370Z', 'GT-R', 'Altima', 'Frontier'],
    'BMW'       => ['M3', 'M4', '330i', 'X5'],
    'Mercedes-Benz' => ['C300', 'E350', 'AMG GT', 'GLC 300'],
    'Porsche'   => ['911', 'Cayman', 'Boxster', 'Macan'],
    'Jeep'      => ['Wrangler', 'Grand Cherokee', 'Gladiator'],
    'Cadillac'  => ['CTS-V', 'Escalade', 'CT5-V'],
    'Lexus'     => ['IS 350', 'RC F', 'GX 460'],
  ];
}

function sccc_seed_branch_pool(): array
{
  return [
    'US Army','US Navy','USMC','US Air Force','US Coast Guard','Texas National Guard',
    'Houston Fire Department','Harris County Sheriff','Houston Police Department','EMS / Paramedic',
    'Volunteer Fire','Constable','Border Patrol',
  ];
}

function sccc_seed_build_vehicles(int $min = 1, int $max = 3): array
{
  $pool  = sccc_seed_vehicle_pool();
  $makes = array_keys($pool);

  $num = random_int($min, $max);
  $rows = [];

  for ($i = 0; $i < $num; $i++) {
    $make  = sccc_seed_pick($makes);
    $model = sccc_seed_pick($pool[$make]);
    $year  = random_int(1995, (int) date('Y'));

    $rows[] = [
      'vehicle_id'  => '',
      'year'        => (string) $year,
      'make_select' => $make,
      'make_other'  => '',
      'make_raw'    => '',
      'model'       => $model,
      'nickname'    => (random_int(1, 100) <= 25) ? sccc_seed_pick(['Zora','Sting','Ghost','Rocket','Viper','Nova','Midnight']) : '',
      'notes'       => '',
      'image'       => '',
    ];
  }

  return $rows;
}

function sccc_seed_trigger_acf_save_for_user(int $userId): void
{
  do_action('acf/save_post', 'user_' . $userId);
}

function sccc_seed_force_roles(int $userId): void
{
  if (!get_role(SCCC_ROLE_MEMBER)) {
    add_role(SCCC_ROLE_MEMBER, 'SCCC Member', ['read' => true]);
  }

  $u = get_user_by('id', $userId);
  if (!$u) return;

  $u->add_role(SCCC_ROLE_SUBSCRIBER);
  $u->add_role(SCCC_ROLE_MEMBER);
}

function sccc_seed_assign_pmpro_level(int $userId, int $levelId): bool
{
  if (!function_exists('pmpro_changeMembershipLevel')) {
    WP_CLI::error('PMPro not loaded: pmpro_changeMembershipLevel() missing.');
  }

  // Canonical: (level_id, user_id)
  return (bool) pmpro_changeMembershipLevel($levelId, $userId);
}

/**
 * Update PMPro membership row dates + status to match our “good standing” rules.
 */
function sccc_seed_set_pmpro_row(int $userId, int $levelId, string $startMysql, string $endMysql, string $status): void
{
  global $wpdb;
  $table = $wpdb->pmpro_memberships_users ?? ($wpdb->prefix . 'pmpro_memberships_users');

  $wpdb->update(
    $table,
    [
      'startdate' => $startMysql,
      'enddate'   => $endMysql,
      'status'    => $status,
    ],
    [
      'user_id'       => $userId,
      'membership_id' => $levelId,
    ],
    ['%s','%s','%s'],
    ['%d','%d']
  );
}

/**
 * Compute membership dates:
 * - Active: enddate in the future.
 * - Lapsed: enddate between Dec 1, 2025 and now (and status 'expired').
 *
 * We also set startdate to one year before enddate (annual membership feel).
 */
function sccc_seed_membership_dates(int $nowTs): array
{
  // Fixed floor: nobody overdue before Dec 1, 2025.
  $lapseFloorTs = strtotime('2025-12-01 00:00:00');

  // Distribution:
  // - 85% active
  // - 15% recently lapsed (for later renewal testing)
  $isLapsed = (random_int(1, 100) <= 15);

  if ($isLapsed) {
    // enddate: between Dec 1, 2025 and yesterday (or now-1h if same-day)
    $max = max($lapseFloorTs, $nowTs - 3600);
    $endTs = ($max > $lapseFloorTs) ? random_int($lapseFloorTs, $max) : $lapseFloorTs;

    $end = (new DateTime())->setTimestamp($endTs);
    $start = (clone $end)->modify('-1 year');

    return [
      'status' => 'expired',
      'start'  => $start->format('Y-m-d H:i:s'),
      'end'    => $end->format('Y-m-d H:i:s'),
      'end_ts' => $endTs,
    ];
  }

  // Active members:
  // enddate: 1–365 days in the future
  $futureEndTs = $nowTs + random_int(86400, 86400 * 365);
  $end = (new DateTime())->setTimestamp($futureEndTs);
  $start = (clone $end)->modify('-1 year');

  return [
    'status' => 'active',
    'start'  => $start->format('Y-m-d H:i:s'),
    'end'    => $end->format('Y-m-d H:i:s'),
    'end_ts' => $futureEndTs,
  ];
}

// ---------------------------------------------
// Rollback
// ---------------------------------------------
function sccc_seed_rollback(string $runId = ''): void
{
  $runs = sccc_seed_load_runs();

  if (empty($runs)) {
    WP_CLI::warning('No seed runs found. Nothing to rollback.');
    return;
  }

  if ($runId === '') {
    $last = end($runs);
    $runId = (string) ($last['run_id'] ?? '');
  }

  if ($runId === '') {
    WP_CLI::warning('Could not determine run id to rollback.');
    return;
  }

  $run = sccc_seed_find_run($runs, $runId);
  if (!$run) {
    WP_CLI::warning("Run id not found: {$runId}");
    return;
  }

  $ids = $run['user_ids'] ?? [];
  if (!is_array($ids) || empty($ids)) {
    WP_CLI::warning("Run {$runId} has no stored user IDs.");
    $runs = sccc_seed_remove_run($runs, $runId);
    sccc_seed_save_runs($runs);
    return;
  }

  WP_CLI::log("Rolling back run: {$runId} (users: " . count($ids) . ')');

  $deleted = 0;

  foreach ($ids as $userId) {
    $userId = (int) $userId;
    if ($userId <= 0) continue;

    if (function_exists('pmpro_changeMembershipLevel')) {
      pmpro_changeMembershipLevel(0, $userId);
    }

    if (function_exists('wp_delete_user')) {
      $ok = wp_delete_user($userId, 1);
      if ($ok) $deleted++;
    }
  }

  $runs = sccc_seed_remove_run($runs, $runId);
  sccc_seed_save_runs($runs);

  WP_CLI::success("Rollback complete. Deleted users: {$deleted}");
}

// ---------------------------------------------
// Execute
// ---------------------------------------------
if ($mode === 'rollback') {
  sccc_seed_rollback((string) ($runId ?? ''));
  return;
}

if (!function_exists('wp_insert_user')) {
  WP_CLI::error('WordPress not fully loaded. Run via wp eval-file inside your WP install.');
}
if (!function_exists('pmpro_changeMembershipLevel')) {
  WP_CLI::error('PMPro not loaded. Make sure plugins are not skipped.');
}

$runId = 'seed_' . gmdate('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);

WP_CLI::log("Starting seed run: {$runId}");
WP_CLI::log("Creating users: {$count}");

$nowTs   = (int) current_time('timestamp'); // WP timezone aware
$birthMin = strtotime('1950-01-01');
$birthMax = strtotime('2006-12-31');

// Membership “issued at” should be spread across years for anniversaries.
$issuedMin = strtotime('2017-01-01');
$issuedMax = max($issuedMin, $nowTs - 86400 * 30);

$createdUserIds = [];
$progress = method_exists('WP_CLI', 'make_progress_bar') ? WP_CLI::make_progress_bar('Seeding members', $count) : null;

for ($i = 1; $i <= $count; $i++) {
  [$first, $last] = sccc_seed_fake_name();

  $login = sanitize_user("sccc_{$runId}_{$i}");
  $email = "member{$i}.{$runId}@example.test";

  $userId = wp_insert_user([
    'user_login'   => $login,
    'user_pass'    => wp_generate_password(14, true, true),
    'user_email'   => $email,
    'display_name' => "{$first} {$last}",
    'first_name'   => $first,
    'last_name'    => $last,
    'role'         => SCCC_ROLE_SUBSCRIBER,
  ]);

  if (is_wp_error($userId)) {
    WP_CLI::warning('Failed to create user #' . $i . ': ' . $userId->get_error_message());
    if ($progress) $progress->tick();
    continue;
  }

  $userId = (int) $userId;
  $createdUserIds[] = $userId;

  // Roles (subscriber + sccc_member)
  sccc_seed_force_roles($userId);

  // Birthday
  $birth = sccc_seed_rand_dt($birthMin, $birthMax);
  update_user_meta($userId, SCCC_META_BIRTH_DATE, $birth->format('Y-m-d'));

  // Anniversary source meta: membership_issued_at spread across years
  $issued = sccc_seed_rand_dt($issuedMin, $issuedMax);
  update_user_meta($userId, SCCC_META_ISSUED_AT, $issued->format('Y-m-d H:i:s'));

  // Pick membership level category distribution
  $roll = random_int(1, 100);
  $levelId = SCCC_LEVEL_MEMBER;
  $isVetLevel = false;

  if ($roll <= 15) {
    $levelId = SCCC_LEVEL_JUNIOR;
  } elseif ($roll <= 35) {
    $levelId = SCCC_LEVEL_VETERAN_FR;
    $isVetLevel = true;
  }

  // Assign PMPro membership
  $ok = sccc_seed_assign_pmpro_level($userId, $levelId);

  if ($ok) {
    // Apply “good standing” membership dates/status rule
    $m = sccc_seed_membership_dates($nowTs);
    sccc_seed_set_pmpro_row($userId, $levelId, $m['start'], $m['end'], $m['status']);
  } else {
    WP_CLI::warning("User {$userId}: PMPro assignment failed.");
  }

  // Veteran fields
  $sprinkleVet = (random_int(1, 100) <= 6);
  $isVet = $isVetLevel || $sprinkleVet;

  update_user_meta($userId, SCCC_META_VET_ENABLED, $isVet ? '1' : '0');

  if ($isVet) {
    update_user_meta($userId, SCCC_META_VET_TYPE, sccc_seed_pick(['veteran', 'first_responder', 'both']));
    update_user_meta($userId, SCCC_META_VET_BRANCH, sccc_seed_pick(sccc_seed_branch_pool()));
  } else {
    delete_user_meta($userId, SCCC_META_VET_TYPE);
    delete_user_meta($userId, SCCC_META_VET_BRANCH);
  }

  // Vehicles
  $vehicles = sccc_seed_build_vehicles(1, 3);

  if (function_exists('update_field')) {
    update_field(SCCC_ACF_VEHICLES, $vehicles, 'user_' . $userId);
    sccc_seed_trigger_acf_save_for_user($userId);
  } else {
    // fallback vehicle_index
    $idxParts = [];
    foreach ($vehicles as $v) {
      $mk = strtolower(trim((string) ($v['make_select'] ?? '')));
      $md = strtolower(trim((string) ($v['model'] ?? '')));
      $idx = trim($mk . ' ' . $md);
      if ($idx !== '') $idxParts[] = $idx;
    }
    $idxParts = array_values(array_unique($idxParts));
    update_user_meta($userId, SCCC_META_VEHICLE_INDEX, implode(' | ', $idxParts));
  }

  if ($progress) $progress->tick();
}

if ($progress) $progress->finish();

// Save run
$runs = sccc_seed_load_runs();
$runs[] = [
  'run_id'     => $runId,
  'created_at' => gmdate('c'),
  'count'      => count($createdUserIds),
  'user_ids'   => $createdUserIds,
];
sccc_seed_save_runs($runs);

WP_CLI::success("Seed complete. Created users: " . count($createdUserIds));
WP_CLI::log("Run ID: {$runId}");
WP_CLI::log("Rollback latest:");
WP_CLI::log("  wp eval-file seed-members.php -- rollback");
WP_CLI::log("Rollback this run:");
WP_CLI::log("  wp eval-file seed-members.php -- rollback {$runId}");
