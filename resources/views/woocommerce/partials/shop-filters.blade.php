{{--
  File path + filename: resources/views/woocommerce/partials/shop-filters.blade.php

  Purpose:
  - Render the SCCC shop archive filter sidebar.
  - Provide filtering controls for:
    - product search
    - top-level product categories/departments
    - price range using min_price and max_price
    - WooCommerce global Colors/Sizes attributes when available
    - Printify variation-meta Colors/Sizes fallback when global attributes are
      not available

  Why this file exists:
  - Printify can push products as real WooCommerce variable products while Colors
    and Sizes may still be stored as variation attributes.
  - WooCommerce global attribute filters are preferred.
  - The fallback keeps the sidebar useful when imported attribute values live in
    variation meta.

  Current update:
  - Keeps Colors/Sizes as checkbox filters with one Apply button.
  - Shows the first 3 options in each attribute group.
  - Places remaining options inside a native disclosure.
  - Moves the Show more / Show less control visually to the bottom of the expanded
    list so it behaves more like a retail sidebar.
  - Keeps Department as direct links because departments are path/category based.

  Important:
  - This file does not change prices.
  - This file does not hide member-only products.
  - The custom variation-meta query vars require:
    app/Support/Woo/ShopVariationAttributeFilters.php
--}}

@php
  /*
   * Build the current archive URL and a clean base URL.
   *
   * Why:
   * Filter forms should submit back to the current shop/category/tag archive,
   * but the action URL should not carry duplicated query strings.
   */
  $requestUri = isset($_SERVER['REQUEST_URI'])
      ? (string) wp_unslash($_SERVER['REQUEST_URI'])
      : '';

  $currentUrl = $requestUri !== ''
      ? home_url($requestUri)
      : (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/'));

  $baseArchiveUrl = strtok($currentUrl, '?');

  if ($baseArchiveUrl === false || $baseArchiveUrl === '') {
      $baseArchiveUrl = $currentUrl;
  }

  /*
   * Decode display labels before Blade escapes them.
   *
   * Why:
   * Some imported terms can be stored as "Home &amp; Living". If we output that
   * directly with Blade's {{ }} escaping, it can display as "Home &amp; Living"
   * instead of "Home & Living".
   */
  $displayName = static function ($name): string {
      return html_entity_decode(
          (string) $name,
          ENT_QUOTES | ENT_HTML5,
          get_bloginfo('charset') ?: 'UTF-8'
      );
  };

  /*
   * Normalize current query params.
   *
   * Why:
   * The checkbox UI submits arrays like sccc_filter_colors[]=black.
   * For link building and hidden fields, it is easier to normalize array values
   * into comma-separated strings.
   */
  $currentParams = [];

  foreach ($_GET as $rawKey => $rawValue) {
      $key = sanitize_key((string) $rawKey);

      if ($key === '') {
          continue;
      }

      if (in_array($key, ['paged', 'product-page', 'add-to-cart'], true)) {
          continue;
      }

      if (is_array($rawValue)) {
          $values = array_map(static function ($value): string {
              return sanitize_title(sanitize_text_field(wp_unslash((string) $value)));
          }, $rawValue);

          $values = array_values(array_unique(array_filter(
              $values,
              static fn ($value): bool => $value !== ''
          )));

          if (! empty($values)) {
              $currentParams[$key] = implode(',', $values);
          }

          continue;
      }

      $value = sanitize_text_field(wp_unslash((string) $rawValue));

      if ($value !== '') {
          $currentParams[$key] = $value;
      }
  }

  /*
   * Read normalized GET values safely.
   */
  $getValue = static function (string $key, string $default = '') use ($currentParams): string {
      if (! isset($currentParams[$key])) {
          return $default;
      }

      return sanitize_text_field((string) $currentParams[$key]);
  };

  $getCsvValues = static function (string $key) use ($getValue): array {
      $rawValue = $getValue($key);

      if ($rawValue === '') {
          return [];
      }

      $values = array_map('sanitize_title', explode(',', $rawValue));
      $values = array_filter($values, static fn ($value): bool => $value !== '');

      return array_values(array_unique($values));
  };

  /*
   * Build a URL while preserving the current context.
   *
   * Why:
   * Active filter pills need to remove only one value while preserving the rest.
   */
  $buildUrl = static function (array $set = [], array $remove = []) use ($baseArchiveUrl, $currentParams): string {
      $params = $currentParams;

      foreach (array_values(array_unique($remove)) as $removeKey) {
          $removeKey = sanitize_key((string) $removeKey);

          if ($removeKey === '') {
              continue;
          }

          unset($params[$removeKey]);
      }

      foreach ($set as $key => $value) {
          $key = sanitize_key((string) $key);

          if ($key === '') {
              continue;
          }

          if ($value === null || $value === '' || $value === []) {
              unset($params[$key]);
              continue;
          }

          $params[$key] = sanitize_text_field((string) $value);
      }

      foreach (['paged', 'product-page', 'add-to-cart'] as $paginationKey) {
          unset($params[$paginationKey]);
      }

      if (empty($params)) {
          return $baseArchiveUrl;
      }

      return add_query_arg($params, $baseArchiveUrl);
  };

  /*
   * Hidden fields for forms.
   *
   * Why:
   * Search, price, and checkbox filter forms are separate, so each form needs to
   * preserve the current values from the other forms.
   */
  $hiddenFields = static function (array $exclude = []) use ($currentParams): array {
      $exclude = array_merge($exclude, [
          'paged',
          'product-page',
          'add-to-cart',
      ]);

      $hidden = [];

      foreach ($currentParams as $key => $value) {
          if (in_array($key, $exclude, true)) {
              continue;
          }

          if ($value === '') {
              continue;
          }

          $hidden[$key] = $value;
      }

      return $hidden;
  };

  /*
   * Resolve a WooCommerce global product attribute taxonomy.
   *
   * Why:
   * Printify may create plural attributes like Colors/Sizes, but depending on
   * import history or manual edits, the actual taxonomy could be pa_colors,
   * pa_sizes, pa_color, or pa_size.
   */
  $resolveAttributeTaxonomy = static function (array $candidateSlugs, array $candidateLabels = []): ?array {
      $candidateSlugs = array_values(array_unique(array_filter(array_map('sanitize_title', $candidateSlugs))));
      $candidateLabels = array_values(array_unique(array_filter(array_map('sanitize_title', $candidateLabels))));

      foreach ($candidateSlugs as $candidateSlug) {
          $taxonomy = function_exists('wc_attribute_taxonomy_name')
              ? wc_attribute_taxonomy_name($candidateSlug)
              : 'pa_' . $candidateSlug;

          if (! taxonomy_exists($taxonomy)) {
              continue;
          }

          $filterSlug = function_exists('wc_attribute_taxonomy_slug')
              ? wc_attribute_taxonomy_slug($taxonomy)
              : preg_replace('/^pa_/', '', $taxonomy);

          return [
              'taxonomy' => $taxonomy,
              'filter_slug' => $filterSlug,
          ];
      }

      if (! function_exists('wc_get_attribute_taxonomies') || ! function_exists('wc_attribute_taxonomy_name')) {
          return null;
      }

      $registeredAttributes = wc_get_attribute_taxonomies();

      if (empty($registeredAttributes)) {
          return null;
      }

      foreach ($registeredAttributes as $registeredAttribute) {
          $registeredSlug = isset($registeredAttribute->attribute_name)
              ? sanitize_title((string) $registeredAttribute->attribute_name)
              : '';

          $registeredLabel = isset($registeredAttribute->attribute_label)
              ? sanitize_title((string) $registeredAttribute->attribute_label)
              : '';

          $matchesSlug = $registeredSlug !== '' && in_array($registeredSlug, $candidateSlugs, true);
          $matchesLabel = $registeredLabel !== '' && in_array($registeredLabel, $candidateLabels, true);

          if (! $matchesSlug && ! $matchesLabel) {
              continue;
          }

          $taxonomy = wc_attribute_taxonomy_name($registeredSlug);

          if (! taxonomy_exists($taxonomy)) {
              continue;
          }

          $filterSlug = function_exists('wc_attribute_taxonomy_slug')
              ? wc_attribute_taxonomy_slug($taxonomy)
              : preg_replace('/^pa_/', '', $taxonomy);

          return [
              'taxonomy' => $taxonomy,
              'filter_slug' => $filterSlug,
          ];
      }

      return null;
  };

  /*
   * Read Printify variation meta options as fallback.
   *
   * Why:
   * Some Printify products are real variable products, but their Colors/Sizes are
   * stored directly as variation meta instead of global attribute taxonomy terms.
   */
  $variationMetaOptions = static function (array $metaKeys, string $type = 'text') use ($displayName): array {
      global $wpdb;

      $metaKeys = array_values(array_unique(array_filter(array_map('sanitize_key', $metaKeys))));

      if (empty($metaKeys)) {
          return [];
      }

      $placeholders = implode(', ', array_fill(0, count($metaKeys), '%s'));

      $sql = $wpdb->prepare(
          "
          SELECT
              p.post_parent,
              m.meta_key,
              m.meta_value
          FROM {$wpdb->posts} p
          INNER JOIN {$wpdb->postmeta} m
              ON p.ID = m.post_id
          WHERE p.post_type = 'product_variation'
              AND p.post_status IN ('publish', 'private')
              AND p.post_parent > 0
              AND m.meta_key IN ({$placeholders})
              AND m.meta_value <> ''
          ",
          $metaKeys
      );

      $rows = $wpdb->get_results($sql);

      if (! is_array($rows) || empty($rows)) {
          return [];
      }

      $options = [];

      foreach ($rows as $row) {
          $rawValue = isset($row->meta_value) ? trim((string) $row->meta_value) : '';

          if ($rawValue === '') {
              continue;
          }

          $slug = sanitize_title($rawValue);

          if ($slug === '') {
              continue;
          }

          $label = $displayName($rawValue);
          $metaKey = isset($row->meta_key) ? (string) $row->meta_key : '';

          if (str_starts_with($metaKey, 'attribute_pa_')) {
              $taxonomy = str_replace('attribute_', '', $metaKey);
              $term = get_term_by('slug', $rawValue, $taxonomy);

              if ($term instanceof \WP_Term) {
                  $label = $displayName($term->name);
              }
          } else {
              $label = ucwords(str_replace(['-', '_'], ' ', $label));
          }

          if (! isset($options[$slug])) {
              $options[$slug] = [
                  'slug' => $slug,
                  'label' => $label,
                  'count' => 0,
                  'parents' => [],
              ];
          }

          $parentId = isset($row->post_parent) ? (int) $row->post_parent : 0;

          if ($parentId > 0) {
              $options[$slug]['parents'][$parentId] = true;
          }
      }

      foreach ($options as $slug => $option) {
          $options[$slug]['count'] = count($option['parents']);
          unset($options[$slug]['parents']);
      }

      uasort($options, static function (array $left, array $right) use ($type): int {
          if ($type === 'size') {
              $weights = [
                  'xxs' => 10,
                  'xs' => 20,
                  's' => 30,
                  'small' => 30,
                  'm' => 40,
                  'medium' => 40,
                  'l' => 50,
                  'large' => 50,
                  'xl' => 60,
                  'x-large' => 60,
                  'xxl' => 70,
                  '2xl' => 70,
                  '2x-large' => 70,
                  'xxxl' => 80,
                  '3xl' => 80,
                  '3x-large' => 80,
                  '4xl' => 90,
                  '4x-large' => 90,
                  '5xl' => 100,
                  '5x-large' => 100,
              ];

              $leftWeight = $weights[(string) $left['slug']] ?? 1000;
              $rightWeight = $weights[(string) $right['slug']] ?? 1000;

              if ($leftWeight !== $rightWeight) {
                  return $leftWeight <=> $rightWeight;
              }
          }

          return strcasecmp((string) $left['label'], (string) $right['label']);
      });

      return $options;
  };

  /*
   * Current active values.
   */
  $searchValue = $getValue('s');
  $minPrice = $getValue('min_price');
  $maxPrice = $getValue('max_price');

  /*
   * Shop URL for clearing filters.
   */
  $shopUrl = function_exists('wc_get_page_permalink')
      ? wc_get_page_permalink('shop')
      : home_url('/shop/');

  /*
   * Top-level product categories.
   */
  $categoryTerms = get_terms([
      'taxonomy' => 'product_cat',
      'hide_empty' => true,
      'parent' => 0,
      'orderby' => 'name',
      'order' => 'ASC',
      'exclude' => array_filter([
          get_option('default_product_cat') ? (int) get_option('default_product_cat') : 0,
      ]),
  ]);

  if (is_wp_error($categoryTerms)) {
      $categoryTerms = [];
  }

  $queriedObject = get_queried_object();
  $currentCategoryId = $queriedObject instanceof \WP_Term && $queriedObject->taxonomy === 'product_cat'
      ? (int) $queriedObject->term_id
      : 0;

  /*
   * Attribute setup.
   *
   * Why:
   * Taxonomy filters are preferred when available. Variation-meta filters are
   * used only when taxonomy terms are missing.
   */
  $attributeGroupConfigs = [
      [
          'candidate_slugs' => ['colors', 'color'],
          'candidate_labels' => ['colors', 'color'],
          'label' => __('Colors', 'sage'),
          'type' => 'swatch',
          'query_type' => 'or',
          'fallback_filter_name' => 'sccc_filter_colors',
          'fallback_meta_keys' => [
              'attribute_colors',
              'attribute_color',
              'attribute_pa_colors',
              'attribute_pa_color',
          ],
      ],
      [
          'candidate_slugs' => ['sizes', 'size'],
          'candidate_labels' => ['sizes', 'size'],
          'label' => __('Sizes', 'sage'),
          'type' => 'pill',
          'query_type' => 'or',
          'fallback_filter_name' => 'sccc_filter_sizes',
          'fallback_meta_keys' => [
              'attribute_sizes',
              'attribute_size',
              'attribute_pa_sizes',
              'attribute_pa_size',
          ],
      ],
  ];

  $attributeGroups = [];

  foreach ($attributeGroupConfigs as $attributeGroupConfig) {
      $resolvedAttribute = $resolveAttributeTaxonomy(
          $attributeGroupConfig['candidate_slugs'],
          $attributeGroupConfig['candidate_labels']
      );

      if ($resolvedAttribute !== null) {
          $filterName = 'filter_' . $resolvedAttribute['filter_slug'];
          $queryTypeName = 'query_type_' . $resolvedAttribute['filter_slug'];

          $terms = get_terms([
              'taxonomy' => $resolvedAttribute['taxonomy'],
              'hide_empty' => true,
              'orderby' => 'name',
              'order' => 'ASC',
          ]);

          if (! is_wp_error($terms) && ! empty($terms)) {
              $options = [];

              foreach ($terms as $term) {
                  if (! $term instanceof \WP_Term) {
                      continue;
                  }

                  $options[$term->slug] = [
                      'slug' => $term->slug,
                      'label' => $displayName($term->name),
                      'count' => (int) $term->count,
                  ];
              }

              if (! empty($options)) {
                  $attributeGroups[] = array_merge($attributeGroupConfig, [
                      'source' => 'taxonomy',
                      'taxonomy' => $resolvedAttribute['taxonomy'],
                      'filter_slug' => $resolvedAttribute['filter_slug'],
                      'filter_name' => $filterName,
                      'query_type_name' => $queryTypeName,
                      'options' => $options,
                      'selected_values' => $getCsvValues($filterName),
                  ]);

                  continue;
              }
          }
      }

      $fallbackOptions = $variationMetaOptions(
          $attributeGroupConfig['fallback_meta_keys'],
          $attributeGroupConfig['fallback_filter_name'] === 'sccc_filter_sizes' ? 'size' : 'text'
      );

      if (empty($fallbackOptions)) {
          continue;
      }

      $attributeGroups[] = array_merge($attributeGroupConfig, [
          'source' => 'variation_meta',
          'filter_name' => $attributeGroupConfig['fallback_filter_name'],
          'query_type_name' => '',
          'options' => $fallbackOptions,
          'selected_values' => $getCsvValues($attributeGroupConfig['fallback_filter_name']),
      ]);
  }

  /*
   * Basic color map for common Printify/Woo color terms.
   */
  $colorMap = [
      'black' => '#111827',
      'white' => '#f8fafc',
      'gray' => '#9ca3af',
      'grey' => '#9ca3af',
      'heather-gray' => '#9ca3af',
      'heather-grey' => '#9ca3af',
      'silver' => '#cbd5e1',
      'red' => '#ef4444',
      'blue' => '#2563eb',
      'navy' => '#172554',
      'royal-blue' => '#1d4ed8',
      'green' => '#16a34a',
      'olive' => '#4d7c0f',
      'yellow' => '#facc15',
      'gold' => '#f59e0b',
      'orange' => '#f97316',
      'purple' => '#7c3aed',
      'pink' => '#ec4899',
      'brown' => '#92400e',
      'tan' => '#d6b58a',
      'beige' => '#d6b58a',
      'cream' => '#fff7ed',
      'natural' => '#f5e6c8',
  ];

  /*
   * Active-filter tracking.
   */
  $activeFilters = [];
  $clearFilterKeys = [
      's',
      'post_type',
      'min_price',
      'max_price',
  ];

  foreach ($attributeGroups as $attributeGroup) {
      $clearFilterKeys[] = $attributeGroup['filter_name'];

      if (! empty($attributeGroup['query_type_name'])) {
          $clearFilterKeys[] = $attributeGroup['query_type_name'];
      }
  }

  if ($searchValue !== '') {
      $activeFilters[] = [
          'label' => sprintf(__('Search: %s', 'sage'), $searchValue),
          'url' => $buildUrl([], ['s', 'post_type']),
      ];
  }

  if ($minPrice !== '' || $maxPrice !== '') {
      $priceLabel = trim(sprintf(
          __('Price: %s%s%s', 'sage'),
          $minPrice !== '' ? '$' . $minPrice : __('Any', 'sage'),
          __(' – ', 'sage'),
          $maxPrice !== '' ? '$' . $maxPrice : __('Any', 'sage')
      ));

      $activeFilters[] = [
          'label' => $priceLabel,
          'url' => $buildUrl([], ['min_price', 'max_price']),
      ];
  }

  foreach ($attributeGroups as $attributeGroup) {
      foreach ($attributeGroup['selected_values'] as $selectedValue) {
          $option = $attributeGroup['options'][$selectedValue] ?? null;

          if (empty($option)) {
              continue;
          }

          $newSelection = array_values(array_filter(
              $attributeGroup['selected_values'],
              static fn ($value): bool => $value !== $selectedValue
          ));

          $setArgs = [];

          if (! empty($newSelection)) {
              $setArgs[$attributeGroup['filter_name']] = implode(',', $newSelection);

              if (! empty($attributeGroup['query_type_name'])) {
                  $setArgs[$attributeGroup['query_type_name']] = (string) $attributeGroup['query_type'];
              }
          }

          $removeArgs = empty($newSelection)
              ? array_filter([$attributeGroup['filter_name'], $attributeGroup['query_type_name'] ?? ''])
              : [];

          $activeFilters[] = [
              'label' => sprintf(
                  '%s: %s',
                  (string) $attributeGroup['label'],
                  $displayName($option['label'] ?? '')
              ),
              'url' => empty($newSelection)
                  ? $buildUrl([], $removeArgs)
                  : $buildUrl($setArgs),
          ];
      }
  }

  $hasActiveFilters = ! empty($activeFilters) || $currentCategoryId > 0;
@endphp

@once
  <style>
    /*
     * File path + filename: resources/views/woocommerce/partials/shop-filters.blade.php
     *
     * Scoped shop filter layout + panel styling.
     *
     * Why:
     * - Keeps the sidebar self-contained.
     * - Avoids touching the finished header/shop nav styles.
     * - Supports Amazon-style checkbox filters with a single apply action.
     */

    .sccc-shop-archive__layout {
      display: grid;
      grid-template-columns: minmax(15rem, 18rem) minmax(0, 1fr);
      align-items: start;
      gap: 2rem;
    }

    .sccc-shop-archive__filters,
    .sccc-shop-archive__results {
      min-width: 0;
    }

    .sccc-shop-filters {
      --sccc-filter-bg:
        radial-gradient(
          460px 240px at 8% 0%,
          color-mix(in oklab, var(--color-primary-500) 11%, transparent),
          transparent 62%
        ),
        radial-gradient(
          360px 220px at 100% 0%,
          color-mix(in oklab, var(--color-accent-500) 8%, transparent),
          transparent 64%
        ),
        color-mix(in oklab, var(--color-surface) 88%, transparent);
      --sccc-filter-border: color-mix(in oklab, var(--color-line) 86%, transparent);
      --sccc-filter-shadow:
        0 18px 44px rgb(0 0 0 / 0.16),
        inset 0 1px 0 rgb(255 255 255 / 0.06);
      --sccc-filter-control-bg: color-mix(in oklab, var(--color-surface) 74%, transparent);
      --sccc-filter-control-border-gradient: linear-gradient(
        135deg,
        rgba(113, 215, 255, 0.82) 0%,
        rgba(67, 190, 255, 0.52) 42%,
        rgba(174, 113, 255, 0.74) 100%
      );

      position: sticky;
      top: calc(var(--wp-admin--admin-bar--height, 0px) + 9.5rem);
      border: 1px solid var(--sccc-filter-border);
      border-radius: 1.25rem;
      background: var(--sccc-filter-bg);
      box-shadow: var(--sccc-filter-shadow);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      overflow: hidden;
    }

    html[data-theme="light"] .sccc-shop-filters {
      --sccc-filter-bg:
        radial-gradient(
          460px 240px at 8% 0%,
          color-mix(in oklab, var(--color-primary-500) 11%, transparent),
          transparent 62%
        ),
        radial-gradient(
          360px 220px at 100% 0%,
          color-mix(in oklab, var(--color-accent-500) 9%, transparent),
          transparent 64%
        ),
        rgba(255, 255, 255, 0.92);
      --sccc-filter-border: color-mix(in oklab, var(--color-primary-500) 24%, var(--color-line));
      --sccc-filter-shadow:
        0 18px 44px rgba(15, 23, 42, 0.1),
        0 0 22px color-mix(in oklab, var(--color-primary-500) 9%, transparent),
        inset 0 1px 0 rgba(255, 255, 255, 0.82);
      --sccc-filter-control-bg:
        linear-gradient(
          135deg,
          color-mix(in oklab, #ffffff 78%, var(--color-primary-500) 22%),
          color-mix(in oklab, #ffffff 76%, var(--color-accent-500) 24%)
        );
    }

    .sccc-shop-filters,
    .sccc-shop-filters * {
      box-sizing: border-box;
    }

    .sccc-shop-filters a {
      text-decoration: none;
    }

    .sccc-shop-filters__summary {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      min-height: 3.4rem;
      padding: 1rem;
      cursor: pointer;
      list-style: none;
      border-bottom: 1px solid var(--sccc-filter-border);
    }

    .sccc-shop-filters__summary::-webkit-details-marker {
      display: none;
    }

    .sccc-shop-filters__summary-text {
      display: grid;
      gap: 0.22rem;
    }

    .sccc-shop-filters__eyebrow {
      color: var(--color-accent-500);
      font-size: 0.66rem;
      line-height: 1;
      font-weight: 900;
      letter-spacing: 0.16em;
      text-transform: uppercase;
    }

    .sccc-shop-filters__title {
      color: var(--color-text);
      font-size: 1rem;
      line-height: 1.1;
      font-weight: 900;
    }

    .sccc-shop-filters__chevron {
      width: 1rem;
      height: 1rem;
      color: var(--color-muted);
      transition: transform 0.18s ease, color 0.18s ease;
    }

    .sccc-shop-filters[open] .sccc-shop-filters__chevron {
      color: var(--color-accent-500);
      transform: rotate(180deg);
    }

    .sccc-shop-filters__body {
      display: grid;
      gap: 1.05rem;
      padding: 1rem;
    }

    .sccc-shop-filters__active {
      display: grid;
      gap: 0.65rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid var(--sccc-filter-border);
    }

    .sccc-shop-filters__active-list {
      display: flex;
      flex-wrap: wrap;
      gap: 0.45rem;
    }

    .sccc-shop-filters__active-pill,
    .sccc-shop-filters__clear {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid transparent;
      border-radius: 999px;
      padding: 0.48rem 0.7rem;
      color: var(--color-text);
      background:
        linear-gradient(var(--sccc-filter-control-bg), var(--sccc-filter-control-bg)) padding-box,
        var(--sccc-filter-control-border-gradient) border-box;
      box-shadow:
        0 10px 22px rgb(0 0 0 / 0.12),
        inset 0 1px 0 rgb(255 255 255 / 0.08);
      font-size: 0.76rem;
      line-height: 1;
      font-weight: 800;
    }

    .sccc-shop-filters__clear {
      color: var(--color-muted);
    }

    .sccc-shop-filters__clear:hover,
    .sccc-shop-filters__clear:focus-visible,
    .sccc-shop-filters__active-pill:hover,
    .sccc-shop-filters__active-pill:focus-visible {
      color: #fff;
      background: linear-gradient(
        135deg,
        color-mix(in oklab, var(--color-primary-500) 84%, transparent),
        color-mix(in oklab, var(--color-accent-500) 56%, var(--color-primary-500))
      );
      outline: none;
    }

    .sccc-shop-filters__group {
      display: grid;
      gap: 0.65rem;
    }

    .sccc-shop-filters__group + .sccc-shop-filters__group {
      padding-top: 1rem;
      border-top: 1px solid var(--sccc-filter-border);
    }

    .sccc-shop-filters__group-title {
      margin: 0;
      color: var(--color-text);
      font-size: 0.78rem;
      line-height: 1.1;
      font-weight: 900;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    .sccc-shop-filters__form {
      display: grid;
      gap: 0.65rem;
    }

    .sccc-shop-filters__search-row,
    .sccc-shop-filters__price-row {
      display: grid;
      gap: 0.55rem;
    }

    .sccc-shop-filters__price-row {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sccc-shop-filters__field {
      width: 100%;
      min-height: 2.6rem;
      border: 1px solid transparent;
      border-radius: 0.9rem;
      padding: 0 0.82rem;
      color: var(--color-text);
      background:
        linear-gradient(color-mix(in oklab, var(--color-surface) 78%, transparent), color-mix(in oklab, var(--color-surface) 78%, transparent)) padding-box,
        linear-gradient(135deg, color-mix(in oklab, var(--color-primary-500) 38%, transparent), color-mix(in oklab, var(--color-accent-500) 30%, transparent)) border-box;
      box-shadow:
        0 10px 22px rgb(0 0 0 / 0.1),
        inset 0 1px 0 rgb(255 255 255 / 0.06);
      outline: none;
    }

    html[data-theme="light"] .sccc-shop-filters__field {
      background:
        linear-gradient(rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.9)) padding-box,
        linear-gradient(135deg, color-mix(in oklab, var(--color-primary-500) 44%, transparent), color-mix(in oklab, var(--color-accent-500) 34%, transparent)) border-box;
    }

    .sccc-shop-filters__field:focus {
      box-shadow:
        0 14px 28px color-mix(in oklab, var(--color-primary-500) 16%, transparent),
        0 0 0 3px color-mix(in oklab, var(--color-primary-500) 20%, transparent);
    }

    .sccc-shop-filters__button {
      min-height: 2.6rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid transparent;
      border-radius: 999px;
      padding: 0.72rem 1rem;
      color: var(--color-text);
      background:
        linear-gradient(var(--sccc-filter-control-bg), var(--sccc-filter-control-bg)) padding-box,
        var(--sccc-filter-control-border-gradient) border-box;
      box-shadow:
        0 12px 26px rgb(0 0 0 / 0.14),
        inset 0 1px 0 rgb(255 255 255 / 0.08);
      font-size: 0.82rem;
      line-height: 1;
      font-weight: 900;
      cursor: pointer;
      transition:
        color 0.18s ease,
        background 0.18s ease,
        box-shadow 0.18s ease,
        transform 0.18s ease;
    }

    .sccc-shop-filters__button:hover,
    .sccc-shop-filters__button:focus-visible {
      color: #fff;
      background: linear-gradient(
        135deg,
        color-mix(in oklab, var(--color-primary-500) 84%, transparent),
        color-mix(in oklab, var(--color-accent-500) 56%, var(--color-primary-500))
      );
      box-shadow:
        0 16px 34px color-mix(in oklab, var(--color-primary-500) 24%, transparent),
        0 0 24px color-mix(in oklab, var(--color-accent-500) 16%, transparent);
      transform: translateY(-1px);
      outline: none;
    }

    .sccc-shop-filters__list {
      display: grid;
      gap: 0.35rem;
      margin: 0;
      padding: 0;
      list-style: none;
    }

    .sccc-shop-filters__link {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      min-height: 2.35rem;
      border-radius: 0.78rem;
      padding: 0.55rem 0.65rem 0.55rem 0.9rem;
      color: var(--color-muted);
      font-size: 0.84rem;
      line-height: 1.2;
      font-weight: 700;
      transition:
        color 0.18s ease,
        background 0.18s ease,
        transform 0.18s ease;
    }

    .sccc-shop-filters__link::before {
      content: "";
      position: absolute;
      left: 0.42rem;
      top: 0.62rem;
      bottom: 0.62rem;
      width: 2px;
      border-radius: 999px;
      background: var(--sccc-header-submenu-indicator-gradient, linear-gradient(180deg, #71d7ff, #ae71ff));
      box-shadow: var(--sccc-header-submenu-indicator-shadow, 0 0 10px rgba(113, 215, 255, 0.36));
      opacity: 0;
      transform: scaleY(0.35);
      transition: opacity 0.18s ease, transform 0.18s ease;
    }

    .sccc-shop-filters__link:hover,
    .sccc-shop-filters__link:focus-visible,
    .sccc-shop-filters__link.is-active {
      color: var(--color-accent-500);
      background: var(--sccc-header-submenu-row-hover-bg, color-mix(in oklab, var(--color-line) 45%, transparent));
      transform: translateX(2px);
      outline: none;
    }

    .sccc-shop-filters__link:hover::before,
    .sccc-shop-filters__link:focus-visible::before,
    .sccc-shop-filters__link.is-active::before {
      opacity: 1;
      transform: scaleY(1);
    }

    .sccc-shop-filters__link-count {
      color: var(--color-muted);
      font-size: 0.76rem;
      font-weight: 800;
      opacity: 0.78;
    }

    .sccc-shop-filters__checks {
      display: grid;
      gap: 0.42rem;
    }

    .sccc-shop-filters__check {
      position: relative;
      display: flex;
      align-items: center;
      gap: 0.55rem;
      min-height: 2.18rem;
      border-radius: 0.8rem;
      padding: 0.42rem 0.5rem;
      color: var(--color-muted);
      font-size: 0.82rem;
      line-height: 1.2;
      font-weight: 800;
      cursor: pointer;
      transition:
        color 0.18s ease,
        background 0.18s ease,
        transform 0.18s ease;
    }

    .sccc-shop-filters__check:hover,
    .sccc-shop-filters__check:has(.sccc-shop-filters__checkbox:focus-visible),
    .sccc-shop-filters__check:has(.sccc-shop-filters__checkbox:checked) {
      color: var(--color-accent-500);
      background: var(--sccc-header-submenu-row-hover-bg, color-mix(in oklab, var(--color-line) 45%, transparent));
      transform: translateX(2px);
    }

    .sccc-shop-filters__checkbox {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .sccc-shop-filters__box {
      width: 1rem;
      height: 1rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: 0 0 auto;
      border: 1px solid color-mix(in oklab, var(--color-line) 90%, transparent);
      border-radius: 0.3rem;
      background:
        linear-gradient(color-mix(in oklab, var(--color-surface) 72%, transparent), color-mix(in oklab, var(--color-surface) 72%, transparent)) padding-box,
        linear-gradient(135deg, color-mix(in oklab, var(--color-primary-500) 34%, transparent), color-mix(in oklab, var(--color-accent-500) 26%, transparent)) border-box;
      box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.08);
    }

    .sccc-shop-filters__box::after {
      content: "";
      width: 0.45rem;
      height: 0.45rem;
      border-radius: 0.14rem;
      background: linear-gradient(135deg, var(--color-primary-500), var(--color-accent-500));
      opacity: 0;
      transform: scale(0.45);
      transition: opacity 0.16s ease, transform 0.16s ease;
    }

    .sccc-shop-filters__checkbox:checked + .sccc-shop-filters__box {
      border-color: color-mix(in oklab, var(--color-primary-500) 66%, transparent);
      box-shadow:
        0 0 14px color-mix(in oklab, var(--color-primary-500) 22%, transparent),
        inset 0 1px 0 rgb(255 255 255 / 0.12);
    }

    .sccc-shop-filters__checkbox:checked + .sccc-shop-filters__box::after {
      opacity: 1;
      transform: scale(1);
    }

    .sccc-shop-filters__check-text {
      min-width: 0;
      flex: 1 1 auto;
    }

    .sccc-shop-filters__swatch {
      width: 0.9rem;
      height: 0.9rem;
      display: inline-block;
      flex: 0 0 auto;
      border-radius: 999px;
      border: 1px solid rgb(255 255 255 / 0.45);
      background:
        linear-gradient(135deg, color-mix(in oklab, var(--color-primary-500) 55%, transparent), color-mix(in oklab, var(--color-accent-500) 45%, transparent));
      box-shadow: 0 0 0 1px rgb(0 0 0 / 0.1);
    }

    /*
     * Show more / show less disclosure.
     *
     * Why:
     * - The summary remains first in the markup for valid <details> behavior.
     * - CSS grid ordering moves the control visually below the expanded items.
     * - This prevents the confusing middle-of-list placement shown in the prior
     *   screenshot.
     */
    .sccc-shop-filters__more {
      display: grid;
      gap: 0.42rem;
      margin-top: 0.15rem;
    }

    .sccc-shop-filters__more-summary {
      order: 2;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      width: fit-content;
      margin-top: 0.25rem;
      color: var(--color-accent-500);
      cursor: pointer;
      list-style: none;
      font-size: 0.78rem;
      line-height: 1;
      font-weight: 900;
    }

    .sccc-shop-filters__more-summary::-webkit-details-marker {
      display: none;
    }

    .sccc-shop-filters__more-label--open {
      display: none;
    }

    .sccc-shop-filters__more[open] .sccc-shop-filters__more-label--closed {
      display: none;
    }

    .sccc-shop-filters__more[open] .sccc-shop-filters__more-label--open {
      display: inline;
    }

    .sccc-shop-filters__more-summary::after {
      content: "+";
      font-weight: 900;
    }

    .sccc-shop-filters__more[open] .sccc-shop-filters__more-summary::after {
      content: "–";
    }

    .sccc-shop-filters__more-body {
      order: 1;
      display: grid;
      gap: 0.42rem;
      margin-top: 0.1rem;
    }

    .sccc-shop-filters__apply-group {
      padding-top: 1rem;
      border-top: 1px solid var(--sccc-filter-border);
    }

    @media (max-width: 1180px) {
      .sccc-shop-archive__layout {
        grid-template-columns: 1fr;
      }

      .sccc-shop-filters {
        position: relative;
        top: auto;
      }
    }

    @media (min-width: 1181px) {
      .sccc-shop-filters__summary {
        cursor: default;
      }

      .sccc-shop-filters__chevron {
        display: none;
      }
    }
  </style>
@endonce

<details class="sccc-shop-filters" open>
  <summary class="sccc-shop-filters__summary">
    <span class="sccc-shop-filters__summary-text">
      <span class="sccc-shop-filters__eyebrow">
        {{ __('Refine', 'sage') }}
      </span>

      <span class="sccc-shop-filters__title">
        {{ __('Shop Filters', 'sage') }}
      </span>
    </span>

    <svg class="sccc-shop-filters__chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true">
      <path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
  </summary>

  <div class="sccc-shop-filters__body">
    @if ($hasActiveFilters)
      <div class="sccc-shop-filters__active">
        <h3 class="sccc-shop-filters__group-title">
          {{ __('Active Filters', 'sage') }}
        </h3>

        <div class="sccc-shop-filters__active-list">
          @if ($currentCategoryId > 0)
            <a href="{{ esc_url($shopUrl) }}" class="sccc-shop-filters__active-pill">
              {{ __('Department', 'sage') }} ×
            </a>
          @endif

          @foreach ($activeFilters as $activeFilter)
            <a href="{{ esc_url($activeFilter['url']) }}" class="sccc-shop-filters__active-pill">
              {{ $activeFilter['label'] }} ×
            </a>
          @endforeach

          <a
            href="{{ esc_url($currentCategoryId > 0 ? $shopUrl : $buildUrl([], $clearFilterKeys)) }}"
            class="sccc-shop-filters__clear"
          >
            {{ __('Clear all', 'sage') }}
          </a>
        </div>
      </div>
    @endif

    <div class="sccc-shop-filters__group">
      <h3 class="sccc-shop-filters__group-title">
        {{ __('Search', 'sage') }}
      </h3>

      <form class="sccc-shop-filters__form" method="get" action="{{ esc_url($baseArchiveUrl) }}">
        <div class="sccc-shop-filters__search-row">
          <label class="sr-only" for="sccc-shop-filter-search">
            {{ __('Search products', 'sage') }}
          </label>

          <input
            id="sccc-shop-filter-search"
            class="sccc-shop-filters__field"
            type="search"
            name="s"
            value="{{ esc_attr($searchValue) }}"
            placeholder="{{ esc_attr__('Search merchandise…', 'sage') }}"
          >

          <input type="hidden" name="post_type" value="product">

          @foreach ($hiddenFields(['s', 'post_type']) as $key => $value)
            <input type="hidden" name="{{ esc_attr($key) }}" value="{{ esc_attr($value) }}">
          @endforeach

          <button class="sccc-shop-filters__button" type="submit">
            {{ __('Search', 'sage') }}
          </button>
        </div>
      </form>
    </div>

    @if (!empty($categoryTerms))
      <div class="sccc-shop-filters__group">
        <h3 class="sccc-shop-filters__group-title">
          {{ __('Department', 'sage') }}
        </h3>

        <ul class="sccc-shop-filters__list">
          <li>
            <a
              href="{{ esc_url($shopUrl) }}"
              class="sccc-shop-filters__link {{ $currentCategoryId === 0 ? 'is-active' : '' }}"
            >
              <span>{{ __('All Departments', 'sage') }}</span>
            </a>
          </li>

          @foreach ($categoryTerms as $categoryTerm)
            @php
              if (! $categoryTerm instanceof \WP_Term) {
                  continue;
              }

              $categoryUrl = get_term_link($categoryTerm);

              if (is_wp_error($categoryUrl)) {
                  continue;
              }

              $isCurrentCategory = $currentCategoryId === (int) $categoryTerm->term_id;
            @endphp

            <li>
              <a
                href="{{ esc_url($categoryUrl) }}"
                class="sccc-shop-filters__link {{ $isCurrentCategory ? 'is-active' : '' }}"
                @if ($isCurrentCategory) aria-current="page" @endif
              >
                <span>{{ $displayName($categoryTerm->name) }}</span>
                <span class="sccc-shop-filters__link-count">{{ (int) $categoryTerm->count }}</span>
              </a>
            </li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="sccc-shop-filters__group">
      <h3 class="sccc-shop-filters__group-title">
        {{ __('Price Range', 'sage') }}
      </h3>

      <form class="sccc-shop-filters__form" method="get" action="{{ esc_url($baseArchiveUrl) }}">
        <div class="sccc-shop-filters__price-row">
          <label>
            <span class="sr-only">{{ __('Minimum price', 'sage') }}</span>
            <input
              class="sccc-shop-filters__field"
              type="number"
              min="0"
              step="1"
              name="min_price"
              value="{{ esc_attr($minPrice) }}"
              placeholder="{{ esc_attr__('Min', 'sage') }}"
            >
          </label>

          <label>
            <span class="sr-only">{{ __('Maximum price', 'sage') }}</span>
            <input
              class="sccc-shop-filters__field"
              type="number"
              min="0"
              step="1"
              name="max_price"
              value="{{ esc_attr($maxPrice) }}"
              placeholder="{{ esc_attr__('Max', 'sage') }}"
            >
          </label>
        </div>

        @foreach ($hiddenFields(['min_price', 'max_price']) as $key => $value)
          <input type="hidden" name="{{ esc_attr($key) }}" value="{{ esc_attr($value) }}">
        @endforeach

        <button class="sccc-shop-filters__button" type="submit">
          {{ __('Apply Price', 'sage') }}
        </button>
      </form>
    </div>

    @if (!empty($attributeGroups))
      <form class="sccc-shop-filters__form" method="get" action="{{ esc_url($baseArchiveUrl) }}">
        @foreach ($hiddenFields($clearFilterKeys) as $key => $value)
          <input type="hidden" name="{{ esc_attr($key) }}" value="{{ esc_attr($value) }}">
        @endforeach

        @foreach ($attributeGroups as $attributeGroup)
          @php
            $visibleOptions = array_slice($attributeGroup['options'], 0, 3, true);
            $extraOptions = array_slice($attributeGroup['options'], 3, null, true);

            $extraHasSelected = ! empty(array_intersect(
                array_keys($extraOptions),
                $attributeGroup['selected_values']
            ));

            $attributeLabelLower = strtolower((string) $attributeGroup['label']);
          @endphp

          <div class="sccc-shop-filters__group">
            <h3 class="sccc-shop-filters__group-title">
              {{ $attributeGroup['label'] }}
            </h3>

            @if (!empty($attributeGroup['query_type_name']))
              <input
                type="hidden"
                name="{{ esc_attr($attributeGroup['query_type_name']) }}"
                value="{{ esc_attr($attributeGroup['query_type']) }}"
              >
            @endif

            <div class="sccc-shop-filters__checks">
              @foreach ($visibleOptions as $option)
                @php
                  $optionSlug = (string) ($option['slug'] ?? '');
                  $optionLabel = $displayName($option['label'] ?? '');
                  $optionCount = (int) ($option['count'] ?? 0);
                  $isChecked = in_array($optionSlug, $attributeGroup['selected_values'], true);
                  $optionId = 'sccc-filter-' . sanitize_title($attributeGroup['filter_name'] . '-' . $optionSlug);
                  $swatchColor = $colorMap[$optionSlug] ?? '';
                @endphp

                @if ($optionSlug !== '' && $optionLabel !== '')
                  <label class="sccc-shop-filters__check" for="{{ esc_attr($optionId) }}">
                    <input
                      id="{{ esc_attr($optionId) }}"
                      class="sccc-shop-filters__checkbox"
                      type="checkbox"
                      name="{{ esc_attr($attributeGroup['filter_name']) }}[]"
                      value="{{ esc_attr($optionSlug) }}"
                      @if ($isChecked) checked @endif
                    >

                    <span class="sccc-shop-filters__box" aria-hidden="true"></span>

                    @if (($attributeGroup['type'] ?? '') === 'swatch')
                      <span
                        class="sccc-shop-filters__swatch"
                        @if ($swatchColor) style="background: {{ esc_attr($swatchColor) }};" @endif
                        aria-hidden="true"
                      ></span>
                    @endif

                    <span class="sccc-shop-filters__check-text">{{ $optionLabel }}</span>

                    @if ($optionCount > 0)
                      <span class="sccc-shop-filters__link-count">{{ $optionCount }}</span>
                    @endif
                  </label>
                @endif
              @endforeach

              @if (!empty($extraOptions))
                <details class="sccc-shop-filters__more" @if ($extraHasSelected) open @endif>
                  <summary class="sccc-shop-filters__more-summary">
                    <span class="sccc-shop-filters__more-label sccc-shop-filters__more-label--closed">
                      {{ sprintf(__('Show more %s', 'sage'), $attributeLabelLower) }}
                    </span>

                    <span class="sccc-shop-filters__more-label sccc-shop-filters__more-label--open">
                      {{ sprintf(__('Show less %s', 'sage'), $attributeLabelLower) }}
                    </span>
                  </summary>

                  <div class="sccc-shop-filters__more-body">
                    @foreach ($extraOptions as $option)
                      @php
                        $optionSlug = (string) ($option['slug'] ?? '');
                        $optionLabel = $displayName($option['label'] ?? '');
                        $optionCount = (int) ($option['count'] ?? 0);
                        $isChecked = in_array($optionSlug, $attributeGroup['selected_values'], true);
                        $optionId = 'sccc-filter-' . sanitize_title($attributeGroup['filter_name'] . '-' . $optionSlug);
                        $swatchColor = $colorMap[$optionSlug] ?? '';
                      @endphp

                      @if ($optionSlug !== '' && $optionLabel !== '')
                        <label class="sccc-shop-filters__check" for="{{ esc_attr($optionId) }}">
                          <input
                            id="{{ esc_attr($optionId) }}"
                            class="sccc-shop-filters__checkbox"
                            type="checkbox"
                            name="{{ esc_attr($attributeGroup['filter_name']) }}[]"
                            value="{{ esc_attr($optionSlug) }}"
                            @if ($isChecked) checked @endif
                          >

                          <span class="sccc-shop-filters__box" aria-hidden="true"></span>

                          @if (($attributeGroup['type'] ?? '') === 'swatch')
                            <span
                              class="sccc-shop-filters__swatch"
                              @if ($swatchColor) style="background: {{ esc_attr($swatchColor) }};" @endif
                              aria-hidden="true"
                            ></span>
                          @endif

                          <span class="sccc-shop-filters__check-text">{{ $optionLabel }}</span>

                          @if ($optionCount > 0)
                            <span class="sccc-shop-filters__link-count">{{ $optionCount }}</span>
                          @endif
                        </label>
                      @endif
                    @endforeach
                  </div>
                </details>
              @endif
            </div>
          </div>
        @endforeach

        <div class="sccc-shop-filters__apply-group">
          <button class="sccc-shop-filters__button" type="submit">
            {{ __('Apply Selected Filters', 'sage') }}
          </button>
        </div>
      </form>
    @endif
  </div>
</details>