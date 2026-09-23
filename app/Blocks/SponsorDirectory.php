<?php

/**
 * File path + filename: app/Blocks/SponsorDirectory.php
 *
 * Purpose:
 * - Register a dynamic Sponsor Directory Gutenberg block.
 * - Query active, published Sponsor CPT entries.
 * - Group sponsors by tier and pass normalized data into the Blade view.
 *
 * Why this file exists:
 * - The user wants the grouped sponsor section to be placeable anywhere on a
 *   page instead of being locked into a dedicated page template.
 * - A dynamic block is the cleanest fit because sponsor output depends on live
 *   CPT data, not static saved HTML.
 *
 * Data rules:
 * - Only published Sponsor posts are eligible.
 * - Only sponsors whose `sponsor_status` field is `active` are eligible.
 * - Sponsor groups are driven by the `sponsor_tier` taxonomy.
 * - Logo output prefers light/dark ACF logo assets and falls back to the
 *   featured image when needed.
 *
 * Notes:
 * - The view intentionally handles presentation only.
 * - The heavier data normalization stays here so the Blade template remains
 *   readable and easier to maintain.
 * - This version removes editor instructions for a cleaner block UI and gives
 *   the top/bottom padding controls more room so the button groups do not feel
 *   cramped.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;
use WP_Query;

class SponsorDirectory extends Block
{
    /**
     * Block editor label.
     *
     * @var string
     */
    public $name = 'Sponsor Directory';

    /**
     * Block editor description.
     *
     * @var string
     */
    public $description = 'Display active sponsors grouped by tier in a flexible directory layout.';

    /**
     * Gutenberg category.
     *
     * @var string
     */
    public $category = 'widgets';

    /**
     * Dashicon slug used in the inserter.
     *
     * @var string
     */
    public $icon = 'groups';

    /**
     * Search keywords inside the block inserter.
     *
     * @var array<int, string>
     */
    public $keywords = ['sponsor', 'directory', 'partners', 'tiers'];

    /**
     * Pass normalized data into the Blade view.
     *
     * Why this exists:
     * - Keeps the Blade file focused on markup.
     * - Lets the block stay dynamic without scattering query logic across the
     *   view.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $groups = $this->groups();
        $hasSponsors = false;

        foreach ($groups as $group) {
            if (!empty($group['items'])) {
                $hasSponsors = true;
                break;
            }
        }

        return [
            'eyebrow' => $this->eyebrow(),
            'heading' => $this->heading(),
            'introText' => $this->introText(),
            'topPadding' => $this->topPadding(),
            'bottomPadding' => $this->bottomPadding(),
            'groups' => $groups,
            'hasSponsors' => $hasSponsors,
        ];
    }

    /**
     * Register the editor fields for this block.
     *
     * Why these fields exist:
     * - Keep the block flexible enough to be reused on multiple pages.
     * - Let editors choose which tiers appear without hardcoding one layout for
     *   every future sponsor page.
     * - Allow editors to control section spacing without touching code.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $fields = Builder::make('sponsor_directory');

        $fields
            ->addText('eyebrow', [
                'label' => 'Eyebrow',
                'wrapper' => ['width' => '33'],
            ])
            ->addText('heading', [
                'label' => 'Heading',
                'default_value' => 'Our Sponsors',
                'wrapper' => ['width' => '67'],
            ])
            ->addTextarea('intro_text', [
                'label' => 'Intro Text',
                'rows' => 3,
                'new_lines' => 'br',
                'wrapper' => ['width' => '100'],
            ])
            ->addCheckbox('tier_filter', [
                'label' => 'Show Tier(s)',
                'choices' => $this->tierChoices(),
                'default_value' => ['platinum', 'gold', 'silver', 'bronze'],
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => ['width' => '35'],
            ])
            ->addTrueFalse('hide_empty_tiers', [
                'label' => 'Hide Empty Tiers',
                'ui' => 1,
                'default_value' => 1,
                'wrapper' => ['width' => '15'],
            ])
            ->addButtonGroup('section_top_padding', [
                'label' => 'Top Padding',
                'choices' => $this->spacingChoices(),
                'default_value' => 'md',
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => ['width' => '25'],
            ])
            ->addButtonGroup('section_bottom_padding', [
                'label' => 'Bottom Padding',
                'choices' => $this->spacingChoices(),
                'default_value' => 'md',
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => ['width' => '25'],
            ]);

        return $fields->build();
    }

    /**
     * Optional eyebrow text.
     */
    protected function eyebrow(): string
    {
        return trim((string) get_field('eyebrow'));
    }

    /**
     * Main heading text.
     */
    protected function heading(): string
    {
        return trim((string) get_field('heading')) ?: 'Our Sponsors';
    }

    /**
     * Optional intro copy.
     */
    protected function introText(): string
    {
        return trim((string) get_field('intro_text'));
    }

    /**
     * Validated top padding choice.
     */
    protected function topPadding(): string
    {
        return $this->validatedSpacingChoice((string) get_field('section_top_padding'), 'md');
    }

    /**
     * Validated bottom padding choice.
     */
    protected function bottomPadding(): string
    {
        return $this->validatedSpacingChoice((string) get_field('section_bottom_padding'), 'md');
    }

    /**
     * Whether empty tier groups should be hidden.
     */
    protected function hideEmptyTiers(): bool
    {
        return (bool) get_field('hide_empty_tiers');
    }

    /**
     * Build the final grouped data structure used by the view.
     *
     * Why this exists:
     * - The attached design treats each tier differently.
     * - Grouping here makes the Blade view straightforward.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function groups(): array
    {
        $tiers = $this->selectedTierSlugs();
        $meta = $this->tierMeta();
        $groupedItems = [];

        foreach (array_keys($meta) as $slug) {
            $groupedItems[$slug] = [];
        }

        foreach ($this->sponsors($tiers) as $sponsor) {
            $slug = $sponsor['tier_slug'];

            if (!isset($groupedItems[$slug])) {
                $groupedItems[$slug] = [];
            }

            $groupedItems[$slug][] = $sponsor;
        }

        $groups = [];

        foreach ($tiers as $slug) {
            if (!isset($meta[$slug])) {
                continue;
            }

            $items = $groupedItems[$slug] ?? [];

            if ($this->hideEmptyTiers() && empty($items)) {
                continue;
            }

            $groups[$slug] = array_merge($meta[$slug], [
                'items' => $items,
            ]);
        }

        return $groups;
    }

    /**
     * Query all sponsors eligible for this block.
     *
     * Important filters:
     * - post_type = sponsor
     * - post_status = publish
     * - sponsor_status meta = active
     *
     * @param array<int, string> $tiers
     * @return array<int, array<string, mixed>>
     */
    protected function sponsors(array $tiers): array
    {
        $queryArgs = [
            'post_type' => 'sponsor',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => 'sponsor_status',
                    'value' => 'active',
                    'compare' => '=',
                ],
            ],
        ];

        if (!empty($tiers)) {
            $queryArgs['tax_query'] = [
                [
                    'taxonomy' => 'sponsor_tier',
                    'field' => 'slug',
                    'terms' => $tiers,
                ],
            ];
        }

        $query = new WP_Query($queryArgs);

        if (!$query->have_posts()) {
            return [];
        }

        $items = [];

        foreach ($query->posts as $post) {
            $postId = (int) $post->ID;
            $tier = $this->primaryTier($postId);
            $logo = $this->logoSet($postId, $this->normalizedTitle($postId));

            /**
             * Why logo-less sponsors are skipped:
             * - This block is meant to be visual first.
             * - A sponsor directory without any logo asset tends to look broken,
             *   especially for Platinum / Gold / Silver tiers.
             */
            if (!$logo['has_logo']) {
                continue;
            }

            $items[] = [
                'id' => $postId,
                'title' => $this->normalizedTitle($postId),
                'permalink' => (string) get_permalink($postId),
                'tagline' => trim((string) get_field('sponsor_company_tagline', $postId)),
                'summary' => $this->summary($postId),
                'since_year' => (int) get_field('sponsor_since_year', $postId),
                'tier_slug' => $tier['slug'],
                'tier_name' => $tier['name'],
                'has_dual_logos' => $logo['has_dual_logos'],
                'light_logo_id' => $logo['light_logo_id'],
                'dark_logo_id' => $logo['dark_logo_id'],
                'single_logo_id' => $logo['single_logo_id'],
                'logo_alt' => $logo['alt'],
            ];
        }

        wp_reset_postdata();

        return $items;
    }

    /**
     * Build a short sponsor summary.
     *
     * Why this exists:
     * - Platinum cards need richer copy than just the company name.
     * - Use the manual excerpt first, then fall back to trimmed post content.
     */
    protected function summary(int $postId): string
    {
        $excerpt = trim((string) get_the_excerpt($postId));

        if ($excerpt !== '') {
            return wp_specialchars_decode(wp_strip_all_tags($excerpt), ENT_QUOTES);
        }

        $content = trim((string) get_post_field('post_content', $postId));
        $content = strip_shortcodes($content);
        $content = wp_strip_all_tags($content);

        if ($content === '') {
            return '';
        }

        return wp_specialchars_decode(wp_trim_words($content, 22, '…'), ENT_QUOTES);
    }

    /**
     * Normalize the title for safe display.
     *
     * Why this exists:
     * - Imported titles may contain HTML entities such as &amp;.
     * - Decode once here, then let Blade safely escape when rendering.
     */
    protected function normalizedTitle(int $postId): string
    {
        $title = get_the_title($postId);

        if (!is_string($title) || trim($title) === '') {
            return __('Sponsor', 'sccc');
        }

        return wp_specialchars_decode($title, ENT_QUOTES);
    }

    /**
     * Resolve the best available logo combination for a sponsor.
     *
     * Priority:
     * - Use the explicit light/dark logo pair when both are present.
     * - Otherwise fall back to the featured image.
     * - Otherwise use whichever logo asset exists.
     *
     * @return array<string, mixed>
     */
    protected function logoSet(int $postId, string $fallbackAlt): array
    {
        $lightLogoId = (int) get_field('sponsor_logo_light', $postId);
        $darkLogoId = (int) get_field('sponsor_logo_dark', $postId);
        $featuredId = (int) get_post_thumbnail_id($postId);

        $darkLogoId = $darkLogoId ?: ($featuredId ?: $lightLogoId);
        $lightLogoId = $lightLogoId ?: ($featuredId ?: $darkLogoId);

        $hasDualLogos = $darkLogoId > 0 && $lightLogoId > 0 && $darkLogoId !== $lightLogoId;
        $singleLogoId = $hasDualLogos ? 0 : ($darkLogoId ?: $lightLogoId);
        $resolvedLogoId = $hasDualLogos ? ($darkLogoId ?: $lightLogoId) : $singleLogoId;

        return [
            'has_logo' => $hasDualLogos || $singleLogoId > 0,
            'has_dual_logos' => $hasDualLogos,
            'light_logo_id' => $hasDualLogos ? $lightLogoId : 0,
            'dark_logo_id' => $hasDualLogos ? $darkLogoId : 0,
            'single_logo_id' => $singleLogoId,
            'alt' => $this->logoAlt($resolvedLogoId, $fallbackAlt),
        ];
    }

    /**
     * Resolve a usable image alt string.
     */
    protected function logoAlt(int $attachmentId, string $fallback): string
    {
        if ($attachmentId < 1) {
            return $fallback;
        }

        $alt = trim((string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true));

        return $alt !== '' ? $alt : $fallback;
    }

    /**
     * Resolve the sponsor's primary tier.
     *
     * Why this exists:
     * - The Sponsor field uses a radio-style taxonomy selection, but this stays
     *   defensive in case multiple terms are attached manually later.
     *
     * @return array{slug:string, name:string}
     */
    protected function primaryTier(int $postId): array
    {
        $terms = get_the_terms($postId, 'sponsor_tier');

        if (!is_array($terms) || empty($terms)) {
            return [
                'slug' => 'supporter',
                'name' => 'Supporter',
            ];
        }

        $priority = array_flip(array_keys($this->tierMeta()));

        usort($terms, static function ($left, $right) use ($priority): int {
            $leftRank = $priority[(string) $left->slug] ?? 99;
            $rightRank = $priority[(string) $right->slug] ?? 99;

            return $leftRank <=> $rightRank;
        });

        $term = $terms[0];

        return [
            'slug' => (string) ($term->slug ?: 'supporter'),
            'name' => (string) ($term->name ?: 'Supporter'),
        ];
    }

    /**
     * Return the selected tiers from the editor.
     *
     * Why this exists:
     * - Keeps the block flexible for different pages and campaigns.
     * - Defaults to the main four business-facing tiers if editors leave the
     *   field empty.
     *
     * @return array<int, string>
     */
    protected function selectedTierSlugs(): array
    {
        $selected = get_field('tier_filter');
        $selected = is_array($selected) ? $selected : [];
        $selected = array_values(array_filter(array_map('strval', $selected)));

        if (empty($selected)) {
            $selected = ['platinum', 'gold', 'silver', 'bronze'];
        }

        $allowed = array_keys($this->tierChoices());

        $selected = array_values(array_filter($selected, static function (string $slug) use ($allowed): bool {
            return in_array($slug, $allowed, true);
        }));

        return array_values(array_unique($selected));
    }

    /**
     * Editor choices for tier filtering.
     *
     * Supporter is kept for compatibility because the taxonomy can still exist
     * in the project, even if the visible business tiers are mainly the core
     * four.
     *
     * @return array<string, string>
     */
    protected function tierChoices(): array
    {
        return [
            'platinum' => 'Platinum',
            'gold' => 'Gold',
            'silver' => 'Silver',
            'bronze' => 'Bronze',
            'supporter' => 'Supporter',
        ];
    }

    /**
     * Editor choices for top/bottom section spacing.
     *
     * @return array<string, string>
     */
    protected function spacingChoices(): array
    {
        return [
            'none' => 'None',
            'sm' => 'S',
            'md' => 'M',
            'lg' => 'L',
            'xl' => 'XL',
        ];
    }

    /**
     * Validate spacing values coming from the editor.
     */
    protected function validatedSpacingChoice(string $value, string $fallback = 'md'): string
    {
        $allowed = array_keys($this->spacingChoices());

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    /**
     * Tier display metadata.
     *
     * Why this exists:
     * - The attached mockup gives each tier a different visual treatment.
     * - The user also wants the tier colors to align with the existing
     *   sponsorship palette used elsewhere in the project.
     *
     * @return array<string, array<string, string>>
     */
    protected function tierMeta(): array
    {
        return [
            'platinum' => [
                'slug' => 'platinum',
                'title' => 'Platinum Partners',
                'style' => 'feature',
                'accent_color' => '#7cc7ff',
                'accent_soft' => '#dff3ff',
                'empty_text' => 'No active Platinum sponsors yet.',
            ],
            'gold' => [
                'slug' => 'gold',
                'title' => 'Gold Partners',
                'style' => 'cards',
                'accent_color' => '#d4af37',
                'accent_soft' => '#ffe7a0',
                'empty_text' => 'No active Gold sponsors yet.',
            ],
            'silver' => [
                'slug' => 'silver',
                'title' => 'Silver Partners',
                'style' => 'logos',
                'accent_color' => '#b8c1cc',
                'accent_soft' => '#eef3f8',
                'empty_text' => 'No active Silver sponsors yet.',
            ],
            'bronze' => [
                'slug' => 'bronze',
                'title' => 'Bronze Allies',
                'style' => 'list',
                'accent_color' => '#cd7f32',
                'accent_soft' => '#f5c48d',
                'empty_text' => 'No active Bronze sponsors yet.',
            ],
            'supporter' => [
                'slug' => 'supporter',
                'title' => 'Supporters',
                'style' => 'list',
                'accent_color' => '#cbd5e1',
                'accent_soft' => '#e8eef5',
                'empty_text' => 'No active Supporters yet.',
            ],
        ];
    }
}