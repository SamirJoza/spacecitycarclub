<?php

/**
 * Leadership: position exclusivity validation
 *
 * Requirement:
 * - If a position has been assigned to a user (Leadership post),
 *   it cannot be assigned to another user.
 *
 * Implementation:
 * - Hook into ACF field validation for the taxonomy field "leadership_positions".
 * - For each selected term, check if another Leadership post already has that term.
 * - If yes, block the save with a clear message.
 */

namespace App;

defined('ABSPATH') || exit;

add_filter('acf/validate_value/name=leadership_positions', __NAMESPACE__ . '\\leadership_validate_positions_unique', 10, 4);

function leadership_validate_positions_unique($valid, $value, array $field, string $input)
{
  // Respect previous failures.
  if ($valid !== true) return $valid;

  // If nothing selected, no problem.
  if (empty($value)) return $valid;

  $post_id = isset($_POST['post_ID']) ? (int) $_POST['post_ID'] : 0;
  if ($post_id <= 0) return $valid;

  $post_type = LEADERSHIP_CPT ?? 'leadership';
  $tax       = LEADERSHIP_POS_TAX ?? 'leadership_position';

  // Normalize selected term IDs.
  $term_ids = is_array($value) ? $value : [$value];
  $term_ids = array_values(array_filter(array_map('intval', $term_ids)));

  if (!$term_ids) return $valid;

  $conflicts = [];

  foreach ($term_ids as $term_id) {
    // Find any OTHER leadership post that already has this term.
    $q = new \WP_Query([
      'post_type'      => $post_type,
      'post_status'    => ['publish', 'draft', 'pending', 'private'],
      'posts_per_page' => 1,
      'fields'         => 'ids',
      'post__not_in'   => [$post_id],
      'tax_query'      => [[
        'taxonomy' => $tax,
        'field'    => 'term_id',
        'terms'    => [$term_id],
      ]],
    ]);

    if (!empty($q->posts)) {
      $other_id = (int) $q->posts[0];
      $term = get_term($term_id, $tax);
      $term_name = ($term && !is_wp_error($term)) ? $term->name : ('Term #' . $term_id);

      $conflicts[] = $term_name . ' → already assigned to "' . get_the_title($other_id) . '"';
    }
  }

  if (!empty($conflicts)) {
    return __("One or more selected positions are already assigned:\n\n- " . implode("\n- ", $conflicts), 'sccc');
  }

  return $valid;
}
