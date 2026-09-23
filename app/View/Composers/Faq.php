<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Support\Content\Faq as FaqContent;
use Roots\Acorn\View\Composer;
use WP_Post;
use WP_Term;

use function function_exists;
use function get_field;
use function get_permalink;
use function get_posts;
use function get_terms;
use function get_the_title;
use function is_admin;
use function is_array;
use function is_string;
use function is_wp_error;
use function sanitize_title;
use function trim;
use function usort;
use function wp_strip_all_tags;

/**
 * -----------------------------------------------------------------------------
 * File path + filename: app/View/Composers/Faq.php
 * -----------------------------------------------------------------------------
 * Purpose:
 * - Prepare all data needed by the reusable FAQ page template.
 * - Keep query and data normalization out of Blade so the template and
 *   partials stay focused on layout and presentation.
 *
 * Why this file exists:
 * - The FAQ page is a reusable selectable page template.
 * - The page structure is split into:
 *   1) normal editor content from the page itself,
 *   2) the FAQ module,
 *   3) a closing help/CTA partial fed from theme settings.
 *
 * Important note:
 * - This composer is intentionally named `Faq`.
 * - The FAQ content registration class is imported as `FaqContent` to avoid
 *   a class name collision inside this file.
 */
class Faq extends Composer
{
    /**
     * Bind this composer to the reusable FAQ page template view.
     */
    protected static $views = [
        'template-faq',
    ];

    /**
     * Expose prepared data to the Blade template.
     */
    public function with(): array
    {
        $sections = $this->sections();

        return [
            'faqSections'          => $sections,
            'faqHasContent'        => ! empty($sections),
            'faqSearchPlaceholder' => "Search for 'membership', 'events', 'refunds'...",
            'faqHelp'              => $this->helpBlock(),
        ];
    }

    /**
     * Build the grouped FAQ sections used by the sidebar and accordion lists.
     *
     * Why this method exists:
     * - The page layout needs already-grouped sections so the template can stay
     *   declarative and not perform WordPress queries inline.
     * - Empty terms are filtered out so the page only shows useful categories.
     */
    protected function sections(): array
    {
        if (is_admin()) {
            return [];
        }

        $terms = get_terms([
            'taxonomy'   => FaqContent::TAXONOMY,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || ! is_array($terms) || empty($terms)) {
            return [];
        }

        $sections = [];

        foreach ($terms as $term) {
            if (! $term instanceof WP_Term) {
                continue;
            }

            $items = $this->itemsForTerm($term);

            if (empty($items)) {
                continue;
            }

            $sections[] = [
                'term_id'     => (int) $term->term_id,
                'slug'        => (string) $term->slug,
                'anchor'      => sanitize_title((string) $term->slug),
                'label'       => (string) $term->name,
                'description' => (string) $term->description,
                'count'       => count($items),
                'items'       => $items,
            ];
        }

        return $this->sortSections($sections);
    }

    /**
     * Load FAQ items for one taxonomy term.
     *
     * Why these query settings were chosen:
     * - `menu_order` is first so editors can manually curate display order.
     * - `title` is the fallback so items remain predictable when menu_order ties.
     * - All published FAQs are loaded because the design is a complete reference
     *   page, not a paginated archive.
     */
    protected function itemsForTerm(WP_Term $term): array
    {
        $posts = get_posts([
            'post_type'              => FaqContent::POST_TYPE,
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'orderby'                => [
                'menu_order' => 'ASC',
                'title'      => 'ASC',
            ],
            'tax_query'              => [
                [
                    'taxonomy' => FaqContent::TAXONOMY,
                    'field'    => 'term_id',
                    'terms'    => [$term->term_id],
                ],
            ],
            'suppress_filters'       => false,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ]);

        if (! is_array($posts) || empty($posts)) {
            return [];
        }

        $items = [];

        foreach ($posts as $post) {
            if (! $post instanceof WP_Post) {
                continue;
            }

            $question   = trim((string) get_the_title($post->ID));
            $answerHtml = $this->answerHtml($post->ID);
            $answerText = trim(wp_strip_all_tags($answerHtml));

            if ($question === '' || $answerText === '') {
                continue;
            }

            $items[] = [
                'id'           => (int) $post->ID,
                'question'     => $question,
                'question_key' => sanitize_title($question),
                'answer_html'  => $answerHtml,
                'answer_text'  => $answerText,
                'permalink'    => get_permalink($post->ID),
            ];
        }

        return $items;
    }

    /**
     * Resolve the HTML answer from ACF.
     *
     * Why this is centralized:
     * - The template should not need to know where the answer is stored.
     * - This keeps the ACF dependency isolated and easy to change later.
     */
    protected function answerHtml(int $postId): string
    {
        if (! function_exists('get_field')) {
            return '';
        }

        $answer = get_field('faq_answer', $postId);

        return is_string($answer) ? trim($answer) : '';
    }

    /**
     * Build the closing help/CTA block data.
     *
     * Why this method exists:
     * - The closing partial is fed from the dedicated FAQ Help CTA Theme
     *   Settings page.
     * - These field names intentionally match app/Options/FaqHelpCta.php.
     *
     * Important behavior:
     * - The heading and copy still receive safe text fallbacks.
     * - The buttons do not receive fallback link values because they are optional.
     *   If an editor leaves a button field empty, Blade receives null and should
     *   skip rendering that button.
     */
    protected function helpBlock(): array
    {
        $heading = $this->optionString('faq_help_heading', 'Still have questions?');
        $copy = $this->optionString(
            'faq_help_copy',
            'Can’t find the answer you’re looking for? Reach out to our team directly and we’ll point you in the right direction.'
        );

        $primary = $this->optionLink('faq_help_primary_button');
        $secondary = $this->optionLink('faq_help_secondary_button');

        return [
            'heading'   => $heading,
            'copy'      => $copy,
            'primary'   => $primary,
            'secondary' => $secondary,
        ];
    }

    /**
     * Read a plain string option with fallback.
     *
     * Why this method keeps a fallback:
     * - Empty heading/copy fields should not break the closing CTA section.
     * - Text fallbacks do not force optional button markup to render.
     */
    protected function optionString(string $fieldName, string $fallback = ''): string
    {
        if (! function_exists('get_field')) {
            return $fallback;
        }

        $value = get_field($fieldName, 'option');
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : $fallback;
    }

    /**
     * Read an optional ACF Link field style option.
     *
     * Why this method returns null when empty:
     * - The FAQ CTA buttons are intentionally optional.
     * - Returning default link arrays here would make Blade think a button was
     *   configured, which causes buttons to render even when the editor left
     *   the link fields blank.
     *
     * Supported ACF return formats:
     * - Link Array: ['title' => '', 'url' => '', 'target' => '']
     * - Link URL:   'https://example.com'
     *
     * Project preference:
     * - Link Array is preferred for these CTA button fields because it lets the
     *   editor control the button label, URL, and target from one field.
     */
    protected function optionLink(string $fieldName): ?array
    {
        if (! function_exists('get_field')) {
            return null;
        }

        $value = get_field($fieldName, 'option');

        if (is_string($value)) {
            $url = trim($value);

            return $url !== ''
                ? [
                    'title'  => 'Learn More',
                    'url'    => $url,
                    'target' => '',
                ]
                : null;
        }

        if (! is_array($value)) {
            return null;
        }

        $url    = isset($value['url']) && is_string($value['url']) ? trim($value['url']) : '';
        $title  = isset($value['title']) && is_string($value['title']) ? trim($value['title']) : '';
        $target = isset($value['target']) && is_string($value['target']) ? trim($value['target']) : '';

        if ($url === '' || $title === '') {
            return null;
        }

        return [
            'title'  => $title,
            'url'    => $url,
            'target' => $target,
        ];
    }

    /**
     * Apply a deliberate display order to known FAQ sections.
     *
     * Why this method exists:
     * - Terms do not yet have dedicated ordering UI/metadata.
     * - This preserves a sensible editorial order when matching slugs exist.
     */
    protected function sortSections(array $sections): array
    {
        $preferredOrder = [
            'membership'   => 10,
            'events'       => 20,
            'events-meets' => 20,
            'rules'        => 30,
            'club-rules'   => 30,
            'merch'        => 40,
            'merchandise'  => 40,
        ];

        usort($sections, static function (array $left, array $right) use ($preferredOrder): int {
            $leftWeight  = $preferredOrder[$left['slug']] ?? 999;
            $rightWeight = $preferredOrder[$right['slug']] ?? 999;

            if ($leftWeight === $rightWeight) {
                return strcasecmp((string) $left['label'], (string) $right['label']);
            }

            return $leftWeight <=> $rightWeight;
        });

        return $sections;
    }
}