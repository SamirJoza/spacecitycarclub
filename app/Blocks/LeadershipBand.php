<?php

/**
 * --------------------------------------------------------------------------
 * File path + filename: app/Blocks/LeadershipBand.php
 * --------------------------------------------------------------------------
 * Purpose:
 * - Register a flexible Gutenberg block for displaying SCCC Leadership entries.
 * - Let editors display all leadership categories or selected categories.
 * - Query the Leadership CPT and group people by Leadership Category when needed.
 * - Prepare clean card/modal data for the Blade template.
 *
 * Why this file exists:
 * - The Leadership CPT is intentionally simple:
 *   - one linked WordPress user
 *   - one Leadership Category taxonomy
 *   - optional avatar/photo override
 *   - optional bio override
 *   - custom text-based position titles
 *   - optional numeric display order
 *
 * Display rules:
 * - "All categories" groups output in this order:
 *   1) Leadership Team
 *   2) Officer
 *   3) Board Member
 *   4) Committee
 * - Empty categories are skipped.
 * - Leadership Team and Officer entries sort by `leadership_display_order`.
 * - Board Member and Committee entries sort alphabetically by visible name.
 *
 * Section width rules:
 * - No block alignment selected: section becomes container-width and rounded.
 * - Full width selected: section stretches edge-to-edge and has square corners.
 *
 * Data priority:
 * - Photo override from the Leadership CPT wins over the linked user avatar.
 * - Bio override from the Leadership CPT wins over the linked user bio.
 * - If no avatar exists, WordPress returns an automatic initials avatar.
 *
 * Dependencies:
 * - WordPress
 * - Advanced Custom Fields Pro
 * - log1x/acf-composer
 * - Roots Sage / Blade
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;
use WP_Post;
use WP_Query;
use WP_Term;

defined('ABSPATH') || exit;

class LeadershipBand extends Block
{
    /**
     * The block name shown in Gutenberg.
     *
     * @var string
     */
    public $name = 'Leadership Band';

    /**
     * The block description shown in Gutenberg.
     *
     * @var string
     */
    public $description = 'Display Space City Car Club leadership by category with profile cards and bio modals.';

    /**
     * The Gutenberg category.
     *
     * @var string
     */
    public $category = 'design';

    /**
     * Dashicon used in the block inserter.
     *
     * @var string|array
     */
    public $icon = 'groups';

    /**
     * Search keywords for the block inserter.
     *
     * @var array<int, string>
     */
    public $keywords = [
        'leadership',
        'team',
        'board',
        'officers',
        'committee',
        'members',
    ];

    /**
     * Default block preview/edit mode.
     *
     * @var string
     */
    public $mode = 'preview';

    /**
     * Default alignment.
     *
     * Why this is intentionally blank:
     * - No alignment selected should render the section at container width.
     * - Editors can choose Full Width when they want the background to stretch
     *   edge-to-edge.
     *
     * @var string
     */
    public $align = '';

    /**
     * Native Gutenberg block supports.
     *
     * Why this matters:
     * - Only the Full Width option is exposed.
     * - The default/no-alignment state is used for the rounded container-width
     *   section layout.
     * - Anchor support lets editors link directly to the leadership section.
     *
     * @var array<string, mixed>
     */
    public $supports = [
        'align'  => ['full'],
        'anchor' => true,
    ];

    /**
     * Preferred display order for Leadership Category terms.
     *
     * @var array<string, string>
     */
    private const CATEGORY_ORDER = [
        'leadership-team' => 'Leadership Team',
        'officer'         => 'Officer',
        'board-member'    => 'Board Member',
        'committee'       => 'Committee',
    ];

    /**
     * Categories that should use numeric `leadership_display_order`.
     *
     * All other categories fall back to alphabetical person-name sorting.
     *
     * @var array<int, string>
     */
    private const DISPLAY_ORDERED_CATEGORY_SLUGS = [
        'leadership-team',
        'officer',
    ];

    /**
     * Spacing choices shared by the block fields and Blade CSS classes.
     *
     * @var array<string, string>
     */
    private const SPACING_CHOICES = [
        'default' => 'Default',
        'none'    => 'None',
        'sm'      => 'Small',
        'md'      => 'Medium',
        'lg'      => 'Large',
        'xl'      => 'Extra Large',
    ];

    /**
     * Visual theme choices.
     *
     * @var array<string, string>
     */
    private const THEME_CHOICES = [
        'noir'     => 'Noir Glass',
        'electric' => 'Electric Blue',
        'chrome'   => 'Clean Chrome',
    ];

    /**
     * Intro text alignment choices.
     *
     * @var array<string, string>
     */
    private const TEXT_ALIGN_CHOICES = [
        'left'   => 'Left',
        'center' => 'Center',
    ];

    /**
     * Pass prepared data to the Blade view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $groups = $this->leadershipGroups();

        return [
            'headline'      => $this->headline(),
            'subtext'       => $this->subtext(),
            'displayMode'   => $this->displayMode(),
            'groups'        => $groups,
            'isGrouped'     => $this->shouldRenderGrouped($groups),
            'isPreview'     => (bool) ($this->preview ?? false),
            'theme'         => $this->theme(),
            'textAlign'     => $this->textAlign(),
            'paddingTop'    => $this->spacingValue('leadership_band_padding_top'),
            'paddingBottom' => $this->spacingValue('leadership_band_padding_bottom'),
            'marginTop'     => $this->spacingValue('leadership_band_margin_top', 'none'),
            'marginBottom'  => $this->spacingValue('leadership_band_margin_bottom', 'none'),
        ];
    }

    /**
     * Define the block's ACF fields.
     *
     * @return array
     */
    public function fields(): array
    {
        $fields = Builder::make('leadership_band');

        $fields
            ->addText('leadership_band_headline', [
                'key'           => 'field_sccc_leadership_band_headline',
                'label'         => __('Headline', 'sccc'),
                'instructions'  => __('Optional heading displayed above the leadership band.', 'sccc'),
                'default_value' => __('Meet the Leadership Team', 'sccc'),
                'wrapper'       => ['width' => '50'],
            ])
            ->addTextarea('leadership_band_subtext', [
                'key'           => 'field_sccc_leadership_band_subtext',
                'label'         => __('Subtext', 'sccc'),
                'instructions'  => __('Optional supporting text displayed under the headline.', 'sccc'),
                'rows'          => 3,
                'new_lines'     => 'br',
                'wrapper'       => ['width' => '50'],
            ]);

        $fields
            ->addSelect('leadership_band_display_mode', [
                'key'           => 'field_sccc_leadership_band_display_mode',
                'label'         => __('Display Which Categories?', 'sccc'),
                'instructions'  => __('Choose all leadership categories or select one/more categories manually.', 'sccc'),
                'choices'       => [
                    'all'      => __('All Leadership Categories', 'sccc'),
                    'selected' => __('Selected Leadership Categories', 'sccc'),
                ],
                'default_value' => 'all',
                'return_format' => 'value',
                'ui'            => 1,
                'wrapper'       => ['width' => '50'],
            ]);

        $fields
            ->addTaxonomy('leadership_band_categories', [
                'key'               => 'field_sccc_leadership_band_categories',
                'label'             => __('Leadership Categories', 'sccc'),
                'instructions'      => __('Select one or more categories to display. Leave this hidden field alone when using “All Leadership Categories.”', 'sccc'),
                'taxonomy'          => $this->leadershipCategoryTaxonomy(),
                'field_type'        => 'checkbox',
                'add_term'          => 0,
                'save_terms'        => 0,
                'load_terms'        => 0,
                'return_format'     => 'id',
                'multiple'          => 1,
                'allow_null'        => 1,
                'conditional_logic' => [
                    [
                        [
                            'field'    => 'field_sccc_leadership_band_display_mode',
                            'operator' => '==',
                            'value'    => 'selected',
                        ],
                    ],
                ],
                'wrapper'           => ['width' => '50'],
            ]);

        /**
         * Visual theme.
         *
         * Why this exists:
         * - The same leadership layout may appear on different page designs.
         * - The theme choice controls the section background and card contrast
         *   while staying readable in both light and dark site modes.
         */
        $fields
            ->addSelect('leadership_band_theme', [
                'key'           => 'field_sccc_leadership_band_theme',
                'label'         => __('Theme', 'sccc'),
                'instructions'  => __('Choose the visual background treatment for this Leadership section.', 'sccc'),
                'choices'       => self::THEME_CHOICES,
                'default_value' => 'noir',
                'return_format' => 'value',
                'ui'            => 1,
                'wrapper'       => ['width' => '50'],
            ]);

        /**
         * Headline/subtext alignment.
         *
         * Why this exists:
         * - Some page sections work better with centered intro copy.
         * - Others need left-aligned intro copy to match editorial layouts.
         */
        $fields
            ->addSelect('leadership_band_text_align', [
                'key'           => 'field_sccc_leadership_band_text_align',
                'label'         => __('Headline/Subtext Alignment', 'sccc'),
                'instructions'  => __('Choose how the headline and subtext should align.', 'sccc'),
                'choices'       => self::TEXT_ALIGN_CHOICES,
                'default_value' => 'left',
                'return_format' => 'value',
                'ui'            => 1,
                'wrapper'       => ['width' => '50'],
            ]);

        /**
         * Section spacing controls.
         *
         * Why these are block fields instead of native spacing supports:
         * - Editors get simple preset choices.
         * - The spacing is applied directly to the section wrapper, not the inner
         *   content container.
         */
        $fields
            ->addSelect('leadership_band_padding_top', [
                'key'           => 'field_sccc_leadership_band_padding_top',
                'label'         => __('Padding Top', 'sccc'),
                'choices'       => self::SPACING_CHOICES,
                'default_value' => 'default',
                'return_format' => 'value',
                'ui'            => 1,
                'wrapper'       => ['width' => '25'],
            ])
            ->addSelect('leadership_band_padding_bottom', [
                'key'           => 'field_sccc_leadership_band_padding_bottom',
                'label'         => __('Padding Bottom', 'sccc'),
                'choices'       => self::SPACING_CHOICES,
                'default_value' => 'default',
                'return_format' => 'value',
                'ui'            => 1,
                'wrapper'       => ['width' => '25'],
            ])
            ->addSelect('leadership_band_margin_top', [
                'key'           => 'field_sccc_leadership_band_margin_top',
                'label'         => __('Margin Top', 'sccc'),
                'choices'       => self::SPACING_CHOICES,
                'default_value' => 'none',
                'return_format' => 'value',
                'ui'            => 1,
                'wrapper'       => ['width' => '25'],
            ])
            ->addSelect('leadership_band_margin_bottom', [
                'key'           => 'field_sccc_leadership_band_margin_bottom',
                'label'         => __('Margin Bottom', 'sccc'),
                'choices'       => self::SPACING_CHOICES,
                'default_value' => 'none',
                'return_format' => 'value',
                'ui'            => 1,
                'wrapper'       => ['width' => '25'],
            ]);

        return $fields->build();
    }

    /* ====================================================================== */
    /* Field values                                                            */
    /* ====================================================================== */

    private function headline(): string
    {
        return trim((string) get_field('leadership_band_headline'));
    }

    private function subtext(): string
    {
        return trim((string) get_field('leadership_band_subtext'));
    }

    private function displayMode(): string
    {
        $mode = (string) get_field('leadership_band_display_mode');

        return in_array($mode, ['all', 'selected'], true) ? $mode : 'all';
    }

    private function theme(): string
    {
        $theme = (string) get_field('leadership_band_theme');

        return array_key_exists($theme, self::THEME_CHOICES) ? $theme : 'noir';
    }

    private function textAlign(): string
    {
        $align = (string) get_field('leadership_band_text_align');

        return array_key_exists($align, self::TEXT_ALIGN_CHOICES) ? $align : 'left';
    }

    private function spacingValue(string $fieldName, string $default = 'default'): string
    {
        $value = (string) get_field($fieldName);

        if (!array_key_exists($value, self::SPACING_CHOICES)) {
            return $default;
        }

        return $value;
    }

    /**
     * Decide whether the Blade view should display category headings.
     *
     * @param array<int, array<string, mixed>> $groups
     */
    private function shouldRenderGrouped(array $groups): bool
    {
        if ($this->displayMode() === 'all') {
            return true;
        }

        return count($groups) > 1;
    }

    /* ====================================================================== */
    /* Leadership query preparation                                            */
    /* ====================================================================== */

    /**
     * Build grouped leadership data for the Blade view.
     *
     * @return array<int, array<string, mixed>>
     */
    private function leadershipGroups(): array
    {
        $terms = $this->selectedDisplayTerms();

        if (empty($terms)) {
            return [];
        }

        $groups = [];

        foreach ($terms as $term) {
            $leaders = $this->leadersForTerm($term);

            if (empty($leaders)) {
                continue;
            }

            $groups[] = [
                'term'    => $term,
                'slug'    => $term->slug,
                'label'   => $term->name,
                'leaders' => $leaders,
            ];
        }

        return $groups;
    }

    /**
     * Return the terms the block should display, already ordered.
     *
     * @return array<int, WP_Term>
     */
    private function selectedDisplayTerms(): array
    {
        $taxonomy = $this->leadershipCategoryTaxonomy();

        if (!taxonomy_exists($taxonomy)) {
            return [];
        }

        if ($this->displayMode() === 'all') {
            return $this->orderedKnownTerms();
        }

        $selectedIds = $this->selectedCategoryIds();

        if (empty($selectedIds)) {
            return [];
        }

        $terms = [];
        $handledIds = [];

        foreach ($this->orderedKnownTerms() as $term) {
            $termId = (int) $term->term_id;

            if (!in_array($termId, $selectedIds, true)) {
                continue;
            }

            $terms[] = $term;
            $handledIds[] = $termId;
        }

        foreach ($selectedIds as $termId) {
            if (in_array($termId, $handledIds, true)) {
                continue;
            }

            $term = get_term($termId, $taxonomy);

            if ($term instanceof WP_Term && !is_wp_error($term)) {
                $terms[] = $term;
            }
        }

        return $terms;
    }

    /**
     * Return the known leadership terms in the requested visual order.
     *
     * @return array<int, WP_Term>
     */
    private function orderedKnownTerms(): array
    {
        $taxonomy = $this->leadershipCategoryTaxonomy();
        $terms = [];

        foreach (array_keys(self::CATEGORY_ORDER) as $slug) {
            $term = get_term_by('slug', $slug, $taxonomy);

            if ($term instanceof WP_Term && !is_wp_error($term)) {
                $terms[] = $term;
            }
        }

        return $terms;
    }

    /**
     * Return selected category IDs from the block field.
     *
     * @return array<int, int>
     */
    private function selectedCategoryIds(): array
    {
        $value = get_field('leadership_band_categories', false, false);

        if (empty($value)) {
            return [];
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        $ids = array_map(static fn($item) => (int) $item, $value);
        $ids = array_filter($ids, static fn($id) => $id > 0);

        return array_values(array_unique($ids));
    }

    /**
     * Query leadership posts for one category term.
     *
     * Sorting is intentionally finalized in PHP.
     *
     * Why:
     * - Leadership Team and Officer use numeric custom order.
     * - Board Member and Committee use alphabetical person-name order.
     * - PHP sorting keeps blank order values clean and avoids fragile title text
     *   matching.
     *
     * @return array<int, array<string, mixed>>
     */
    private function leadersForTerm(WP_Term $term): array
    {
        $query = new WP_Query([
            'post_type'              => $this->leadershipPostType(),
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
            'tax_query'              => [
                [
                    'taxonomy' => $this->leadershipCategoryTaxonomy(),
                    'field'    => 'term_id',
                    'terms'    => [(int) $term->term_id],
                ],
            ],
        ]);

        if (!$query->have_posts()) {
            wp_reset_postdata();
            return [];
        }

        $leaders = [];

        foreach ($query->posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            $leaders[] = $this->leaderData($post);
        }

        wp_reset_postdata();

        return $this->sortLeadersForTerm($leaders, $term);
    }

    /**
     * Sort leaders for the current category.
     *
     * @param array<int, array<string, mixed>> $leaders
     * @return array<int, array<string, mixed>>
     */
    private function sortLeadersForTerm(array $leaders, WP_Term $term): array
    {
        $useDisplayOrder = in_array($term->slug, self::DISPLAY_ORDERED_CATEGORY_SLUGS, true);

        usort($leaders, function (array $a, array $b) use ($useDisplayOrder): int {
            if ($useDisplayOrder) {
                $aOrder = $a['displayOrder'];
                $bOrder = $b['displayOrder'];

                $aHasOrder = is_int($aOrder);
                $bHasOrder = is_int($bOrder);

                if ($aHasOrder && $bHasOrder && $aOrder !== $bOrder) {
                    return $aOrder <=> $bOrder;
                }

                if ($aHasOrder && !$bHasOrder) {
                    return -1;
                }

                if (!$aHasOrder && $bHasOrder) {
                    return 1;
                }
            }

            return strcasecmp(
                (string) ($a['sortName'] ?? $a['name'] ?? ''),
                (string) ($b['sortName'] ?? $b['name'] ?? '')
            );
        });

        return $leaders;
    }

    /**
     * Prepare one leadership CPT post for the Blade card/modal.
     *
     * @return array<string, mixed>
     */
    private function leaderData(WP_Post $post): array
    {
        $userId = $this->linkedUserId($post->ID);
        $user = $userId > 0 ? get_userdata($userId) : false;

        $name = $user ? (string) $user->display_name : '';
        $name = trim($name) !== '' ? $name : get_the_title($post);

        $avatarUrl = $this->avatarUrl($post->ID, $userId);
        $positions = $this->positionTitles($post->ID);
        $bio = $this->bio($post->ID, $userId);

        return [
            'postId'       => (int) $post->ID,
            'userId'       => $userId,
            'name'         => $name,
            'sortName'     => $this->sortName($name),
            'positions'    => $positions,
            'avatarUrl'    => $avatarUrl,
            'bio'          => $bio,
            'displayOrder' => $this->displayOrder($post->ID),
        ];
    }

    private function linkedUserId(int $postId): int
    {
        if (!function_exists('get_field')) {
            return 0;
        }

        $value = get_field('leadership_user', $postId, false);

        if (is_array($value)) {
            $value = $value['ID'] ?? $value['id'] ?? 0;
        }

        return max(0, (int) $value);
    }

    /**
     * Resolve display order.
     *
     * Returns:
     * - int when a valid order exists
     * - null when blank
     */
    private function displayOrder(int $postId): ?int
    {
        $value = function_exists('get_field')
            ? get_field('leadership_display_order', $postId, false)
            : get_post_meta($postId, 'leadership_display_order', true);

        if ($value === '' || $value === null) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Resolve avatar URL with CPT override priority.
     *
     * Priority:
     * 1) Leadership CPT image override
     * 2) Linked user avatar / Gravatar / local avatar
     * 3) WordPress initials avatar fallback
     */
    private function avatarUrl(int $postId, int $userId): string
    {
        $override = function_exists('get_field')
            ? get_field('leadership_photo_override', $postId)
            : null;

        $overrideUrl = $this->imageValueToUrl($override);

        if ($overrideUrl !== '') {
            return $overrideUrl;
        }

        if ($userId > 0) {
            $avatar = get_avatar_url($userId, [
                'size'    => 192,
                'default' => 'initials',
            ]);

            if (is_string($avatar) && $avatar !== '') {
                return $avatar;
            }
        }

        $fallback = get_avatar_url(0, [
            'size'          => 192,
            'default'       => 'initials',
            'force_default' => true,
        ]);

        return is_string($fallback) ? $fallback : '';
    }

    /**
     * Convert an ACF image value into a URL.
     */
    private function imageValueToUrl($image): string
    {
        if (is_array($image)) {
            if (!empty($image['sizes']['thumbnail'])) {
                return esc_url_raw((string) $image['sizes']['thumbnail']);
            }

            if (!empty($image['url'])) {
                return esc_url_raw((string) $image['url']);
            }

            if (!empty($image['ID'])) {
                $url = wp_get_attachment_image_url((int) $image['ID'], 'thumbnail');

                return is_string($url) ? $url : '';
            }

            if (!empty($image['id'])) {
                $url = wp_get_attachment_image_url((int) $image['id'], 'thumbnail');

                return is_string($url) ? $url : '';
            }

            return '';
        }

        if (is_numeric($image)) {
            $url = wp_get_attachment_image_url((int) $image, 'thumbnail');

            return is_string($url) ? $url : '';
        }

        if (is_string($image) && filter_var($image, FILTER_VALIDATE_URL)) {
            return esc_url_raw($image);
        }

        return '';
    }

    /**
     * Resolve bio with CPT override priority.
     */
    private function bio(int $postId, int $userId): string
    {
        $override = function_exists('get_field')
            ? get_field('leadership_bio_override', $postId)
            : '';

        if (is_string($override) && trim($override) !== '') {
            return trim($override);
        }

        if ($userId <= 0) {
            return '';
        }

        $bio = trim((string) get_the_author_meta('description', $userId));

        if ($bio === '') {
            return '';
        }

        return wpautop(esc_html($bio));
    }

    /**
     * Resolve custom position titles.
     *
     * @return array<int, string>
     */
    private function positionTitles(int $postId): array
    {
        $value = function_exists('get_field')
            ? get_field('leadership_position_titles', $postId, false)
            : get_post_meta($postId, 'leadership_position_titles', true);

        $positions = $this->normalizePositionValue($value);

        if (!empty($positions)) {
            return $positions;
        }

        $legacyValue = function_exists('get_field')
            ? get_field('leadership_positions', $postId, false)
            : get_post_meta($postId, 'leadership_positions', true);

        $positions = $this->normalizePositionValue($legacyValue);

        if (!empty($positions)) {
            return $positions;
        }

        $terms = get_the_terms($postId, $this->legacyPositionTaxonomy());

        if (!is_array($terms) || empty($terms)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn($term) => $term instanceof WP_Term ? trim((string) $term->name) : '',
            $terms
        ))));
    }

    /**
     * Normalize position values into a clean list of titles.
     *
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalizePositionValue($value): array
    {
        if (empty($value)) {
            return [];
        }

        $items = [];

        if (is_string($value)) {
            $value = str_replace(['<br />', '<br/>', '<br>'], "\n", $value);

            if (str_contains($value, "\n")) {
                $items = preg_split('/\r\n|\r|\n/', $value) ?: [];
            } else {
                $items = preg_split('/\s*,\s*/', $value) ?: [];
            }
        } elseif (is_array($value)) {
            foreach ($value as $item) {
                if ($item instanceof WP_Term) {
                    $items[] = $item->name;
                    continue;
                }

                if (is_numeric($item)) {
                    $term = get_term((int) $item, $this->legacyPositionTaxonomy());

                    if ($term instanceof WP_Term && !is_wp_error($term)) {
                        $items[] = $term->name;
                        continue;
                    }

                    $items[] = (string) $item;
                    continue;
                }

                if (is_array($item)) {
                    $items[] = (string) ($item['label'] ?? $item['name'] ?? '');
                    continue;
                }

                $items[] = (string) $item;
            }
        } else {
            $items[] = (string) $value;
        }

        $items = array_map(
            static fn($item) => trim(wp_strip_all_tags((string) $item)),
            $items
        );

        $items = array_filter($items, static fn($item) => $item !== '');

        return array_values(array_unique($items));
    }

    /**
     * Normalize a display name for alphabetical sorting.
     */
    private function sortName(string $name): string
    {
        $name = trim(wp_strip_all_tags($name));

        if ($name === '') {
            return '';
        }

        return mb_strtolower($name);
    }

    /* ====================================================================== */
    /* Slug helpers                                                            */
    /* ====================================================================== */

    private function leadershipPostType(): string
    {
        return defined('App\\LEADERSHIP_CPT')
            ? (string) constant('App\\LEADERSHIP_CPT')
            : 'leadership';
    }

    private function leadershipCategoryTaxonomy(): string
    {
        return defined('App\\LEADERSHIP_GROUP_TAX')
            ? (string) constant('App\\LEADERSHIP_GROUP_TAX')
            : 'leadership_group';
    }

    private function legacyPositionTaxonomy(): string
    {
        return defined('App\\LEADERSHIP_POS_TAX')
            ? (string) constant('App\\LEADERSHIP_POS_TAX')
            : 'leadership_position';
    }
}