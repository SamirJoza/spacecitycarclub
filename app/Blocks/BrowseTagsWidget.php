<?php

/**
 * ============================================================================
 * File path + filename: app/Blocks/BrowseTagsWidget.php
 * ============================================================================
 *
 * Purpose:
 * - Register a dynamic ACF Composer Gutenberg block that renders a sidebar
 *   "Browse by Tag" widget based on the `post_tag` taxonomy.
 *
 * Why this file exists:
 * - The user wants a widget-style block that visually matches the provided
 *   sidebar HTML reference, but pulls real WordPress tags instead of static
 *   category links.
 * - Tags must be ordered from highest post count to lowest post count.
 * - The block should show the first 5 tags initially, then allow visitors to
 *   expand the widget and reveal the rest.
 *
 * Data rules:
 * - Source taxonomy: `post_tag`
 * - Only non-empty tags are shown.
 * - Tags are sorted by descending term count.
 * - The first 5 are shown by default.
 *
 * Styling approach:
 * - The block view contains fully scoped styles so this widget does not add
 *   bulk or side effects to the global stylesheet.
 *
 * Assumptions:
 * - ACF Composer block discovery is already working in the Sage 11 theme,
 *   just like the other custom blocks in this project.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;
use WP_Term;

class BrowseTagsWidget extends Block
{
    /**
     * Block label in the inserter.
     *
     * @var string
     */
    public $name = 'Browse Tags Widget';

    /**
     * Block description in the inserter.
     *
     * @var string
     */
    public $description = 'A sidebar tag browser widget that lists post tags by popularity and can expand to show all tags.';

    /**
     * Explicit slug for stable naming.
     *
     * @var string
     */
    public $slug = 'browse-tags-widget';

    /**
     * Gutenberg category.
     *
     * @var string
     */
    public $category = 'widgets';

    /**
     * Block icon.
     *
     * @var string|array
     */
    public $icon = 'tag';

    /**
     * Search keywords for the block inserter.
     *
     * @var array<int, string>
     */
    public $keywords = ['tags', 'taxonomy', 'sidebar', 'blog', 'widget'];

    /**
     * Blade view used for rendering.
     *
     * @var string
     */
    public $view = 'blocks.browse-tags-widget';

    /**
     * Default block mode.
     *
     * Why auto:
     * - It previews well in the editor.
     * - Editors can still click into the block and edit its fields naturally.
     *
     * @var string
     */
    public $mode = 'auto';

    /**
     * Block support flags.
     *
     * @var array<string, mixed>
     */
    public $supports = [
        'align' => false,
        'align_text' => false,
        'align_content' => false,
        'anchor' => true,
        'multiple' => true,
        'jsx' => false,
    ];

    /**
     * Data passed into the Blade view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $heading = $this->stringField('heading', 'Browse by Tag');
        $terms = $this->fetchTerms();
        $visibleTerms = array_slice($terms, 0, 5);
        $hiddenTerms = array_slice($terms, 5);

        return [
            'heading' => $heading,
            'visibleTerms' => $visibleTerms,
            'hiddenTerms' => $hiddenTerms,
            'hasMoreTerms' => ! empty($hiddenTerms),
            'toggleId' => wp_unique_id('sccc-tag-browser-'),
            'isPreview' => (bool) $this->preview,
        ];
    }

    /**
     * ACF field group definition.
     *
     * Why this is intentionally minimal:
     * - The user only asked for the heading to be configurable.
     * - All tag data should come directly from WordPress.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $fields = Builder::make('browse_tags_widget');

        $fields->addText('heading', [
            'label' => 'Heading',
            'default_value' => 'Browse by Tag',
            'required' => 0,
        ]);

        return $fields->build();
    }

    /**
     * Normalize a text field into a trimmed string with fallback.
     *
     * @param  string  $key
     * @param  string  $default
     * @return string
     */
    protected function stringField(string $key, string $default = ''): string
    {
        $value = function_exists('get_field') ? get_field($key) : null;

        if (! is_string($value)) {
            return $default;
        }

        $value = trim($value);

        return $value !== '' ? $value : $default;
    }

    /**
     * Fetch post tags sorted from highest post count to lowest.
     *
     * Why this helper exists:
     * - It keeps taxonomy query logic out of the view.
     * - It lets the Blade file work with a clean, link-ready view model.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchTerms(): array
    {
        $terms = get_terms([
            'taxonomy' => 'post_tag',
            'hide_empty' => true,
            'orderby' => 'count',
            'order' => 'DESC',
        ]);

        if (is_wp_error($terms) || ! is_array($terms)) {
            return [];
        }

        $items = [];

        foreach ($terms as $term) {
            if (! $term instanceof WP_Term) {
                continue;
            }

            $url = get_term_link((int) $term->term_id, 'post_tag');

            if (is_wp_error($url)) {
                continue;
            }

            $items[] = [
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'count' => (int) $term->count,
                'url' => $url,
            ];
        }

        return $items;
    }
}