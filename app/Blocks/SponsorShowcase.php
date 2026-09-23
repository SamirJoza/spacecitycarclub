<?php

/**
 * File path + filename: app/Blocks/SponsorShowcase.php
 *
 * Purpose:
 * - Register the Sponsor Showcase Gutenberg block.
 * - Query published sponsors whose custom sponsor status is active.
 * - Render either a static sponsor rail or a weighted scroller.
 * - Organize editor controls into clear tabs so content, sponsor display,
 *   section styling, and bottom CTA settings are easier to manage.
 * - Optionally render a bottom CTA with lead-in copy and one link button.
 *
 * Why this file exists:
 * - Sponsors already live in a dedicated Sponsor CPT with tier and status data.
 * - The block keeps the editor simple while letting tier affect both logo size
 *   and, in scroller mode, relative display frequency.
 * - Section-level style options allow this block to work inside a container
 *   without forcing a boxed background, border, or rounded outer wrapper.
 *
 * Current v1 assumptions:
 * - Sponsor logo fields are checked first: sponsor_logo_light and sponsor_logo_dark.
 * - The Featured Image is used as the fallback logo when theme-specific logos
 *   are not available.
 * - A sponsor must be published, active, and have at least one logo source.
 * - Sponsor logo clicks always go to the Sponsor CPT single post page.
 * - Sponsor logo clicks always open in the same window/tab.
 * - The bottom CTA uses one ACF Link field for URL, button text, and target.
 *
 * Change note:
 * - Adds a Header Alignment field for the block heading and intro description.
 * - Keeps the value controlled and validated before it reaches the Blade view.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;
use WP_Query;

class SponsorShowcase extends Block
{
    /**
     * Block editor label.
     *
     * @var string
     */
    public $name = 'Sponsor Showcase';

    /**
     * Block editor description.
     *
     * @var string
     */
    public $description = 'Display active sponsors as a static rail or weighted scroller.';

    /**
     * Gutenberg category.
     *
     * @var string
     */
    public $category = 'widgets';

    /**
     * Gutenberg icon.
     *
     * @var string|array
     */
    public $icon = 'awards';

    /**
     * Search keywords inside the block inserter.
     *
     * @var array<int, string>
     */
    public $keywords = ['sponsor', 'partners', 'logos', 'showcase'];

    /**
     * Data passed into the Blade view before render.
     *
     * Why style values are passed here:
     * - Blade should not contain direct get_field() calls for presentation flags.
     * - Keeping validation in PHP prevents unexpected class names from reaching
     *   the front end.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $layout = $this->layout();
        $sponsors = $this->sponsors();
        $track = $layout === 'scroller' ? $this->buildWeightedTrack($sponsors) : $sponsors;

        return [
            'heading' => $this->heading(),
            'introText' => $this->introText(),
            'headerAlign' => $this->headerAlign(),
            'layout' => $layout,
            'maxItems' => $this->maxItems(),
            'showCompanyNames' => $this->showCompanyNames(),
            'backgroundTreatment' => $this->backgroundTreatment(),
            'contentWidth' => $this->contentWidth(),
            'verticalSpacing' => $this->verticalSpacing(),
            'tileStyle' => $this->tileStyle(),
            'showBottomCta' => $this->showBottomCta(),
            'bottomCtaLeadIn' => $this->bottomCtaLeadIn(),
            'bottomCtaLink' => $this->bottomCtaLink(),
            'sponsors' => $sponsors,
            'track' => $track,
            'marqueeDuration' => $this->marqueeDuration($track),
        ];
    }

    /**
     * Block field registration.
     *
     * Why tabs are used:
     * - ACF tabs group all following fields until the next tab.
     * - This keeps the block editor focused and avoids one long settings panel.
     * - The field names stay stable, so existing block content keeps working.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $fields = Builder::make('sponsor_showcase');

        $fields
            /**
             * Content tab:
             * - Human-facing copy only.
             * - These fields control the heading area above the logos.
             * - Header Alignment affects only the heading and intro description;
             *   it does not change the sponsor rail or CTA layout.
             */
            ->addTab('content_tab', [
                'label' => 'Content',
                'placement' => 'top',
            ])
            ->addText('heading', [
                'label' => 'Heading',
                'default_value' => 'Our Sponsors',
                'wrapper' => ['width' => '50'],
            ])
            ->addTextarea('intro_text', [
                'label' => 'Intro Text',
                'instructions' => 'Optional short copy above the sponsor logos.',
                'rows' => 2,
                'new_lines' => 'br',
                'wrapper' => ['width' => '50'],
            ])
            ->addButtonGroup('header_align', [
                'label' => 'Header Alignment',
                'instructions' => 'Aligns the heading and intro text only. Sponsor logos keep their selected rail layout.',
                'choices' => [
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right',
                ],
                'default_value' => 'left',
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => ['width' => '100'],
            ])

            /**
             * Sponsor Display tab:
             * - Controls what sponsor items appear and how the rail behaves.
             * - Does not change the Sponsor CPT source of truth.
             */
            ->addTab('sponsor_display_tab', [
                'label' => 'Sponsor Display',
                'placement' => 'top',
            ])
            ->addButtonGroup('layout', [
                'label' => 'Layout',
                'choices' => [
                    'static' => 'Static Rail',
                    'scroller' => 'Scroller',
                ],
                'default_value' => 'static',
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => ['width' => '34'],
            ])
            ->addNumber('max_items', [
                'label' => 'Max Unique Sponsors',
                'instructions' => 'How many unique sponsors should be eligible for this block.',
                'default_value' => 12,
                'min' => 1,
                'max' => 24,
                'step' => 1,
                'wrapper' => ['width' => '33'],
            ])
            ->addTrueFalse('show_company_names', [
                'label' => 'Show Company Names',
                'instructions' => 'Display the sponsor company name underneath each logo.',
                'ui' => 1,
                'default_value' => 0,
                'wrapper' => ['width' => '33'],
            ])
            ->addCheckbox('tier_filter', [
                'label' => 'Limit to Tier(s)',
                'instructions' => 'Leave empty to allow all sponsor tiers.',
                'choices' => $this->tierChoices(),
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => ['width' => '100'],
            ])

            /**
             * Section Style tab:
             * - Lets the block live comfortably inside another container.
             * - The outer section can be transparent, while the inner content
             *   remains bound to the standard content width.
             */
            ->addTab('section_style_tab', [
                'label' => 'Section Style',
                'placement' => 'top',
            ])
            ->addButtonGroup('background_treatment', [
                'label' => 'Background Treatment',
                'instructions' => 'Use None when this block is already inside a designed container.',
                'choices' => [
                    'none' => 'None',
                    'subtle_glow' => 'Subtle Glow',
                    'glass_panel' => 'Glass Panel',
                    'neon_band' => 'Neon Band',
                ],
                'default_value' => 'none',
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => ['width' => '50'],
            ])
            ->addButtonGroup('content_width', [
                'label' => 'Content Width',
                'instructions' => 'Controls the inner alignment for the heading, sponsor rail, and CTA.',
                'choices' => [
                    'wide' => 'Wide',
                    'standard' => 'Standard',
                    'narrow' => 'Narrow',
                    'full' => 'Full',
                ],
                'default_value' => 'wide',
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => ['width' => '50'],
            ])
            ->addButtonGroup('vertical_spacing', [
                'label' => 'Vertical Spacing',
                'instructions' => 'Controls top and bottom breathing room for the section.',
                'choices' => [
                    'none' => 'None',
                    'compact' => 'Compact',
                    'normal' => 'Normal',
                    'generous' => 'Generous',
                ],
                'default_value' => 'normal',
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => ['width' => '50'],
            ])
            ->addButtonGroup('tile_style', [
                'label' => 'Sponsor Tile Style',
                'instructions' => 'Glass Neon gives the sponsor logo tiles the SCCC-style glow without boxing in the whole block.',
                'choices' => [
                    'glass_neon' => 'Glass Neon',
                    'minimal' => 'Minimal',
                ],
                'default_value' => 'glass_neon',
                'return_format' => 'value',
                'layout' => 'horizontal',
                'wrapper' => ['width' => '50'],
            ])

            /**
             * Bottom CTA tab:
             * - Keeps conversion copy separate from the sponsor/logo controls.
             * - Existing optional behavior remains intact.
             */
            ->addTab('bottom_cta_tab', [
                'label' => 'Bottom CTA',
                'placement' => 'top',
            ])
            ->addTrueFalse('show_bottom_cta', [
                'label' => 'Show Bottom CTA',
                'instructions' => 'Turn this on to display a small call-to-action area below the sponsor logos.',
                'ui' => 1,
                'default_value' => 0,
                'wrapper' => ['width' => '33'],
            ])
            ->addTextarea('bottom_cta_lead_in', [
                'label' => 'Bottom CTA Lead-in Text',
                'instructions' => 'Optional supporting copy that appears before the CTA button.',
                'rows' => 2,
                'new_lines' => 'br',
                'wrapper' => ['width' => '34'],
            ])
            ->addLink('bottom_cta_link', [
                'label' => 'Bottom CTA Link',
                'instructions' => 'Use the link text as the button label. Leave empty to show lead-in text only.',
                'return_format' => 'array',
                'wrapper' => ['width' => '33'],
            ]);

        return $fields->build();
    }

    /**
     * Editor heading.
     */
    protected function heading(): string
    {
        return trim((string) get_field('heading')) ?: 'Our Sponsors';
    }

    /**
     * Optional editor intro copy.
     */
    protected function introText(): string
    {
        return trim((string) get_field('intro_text'));
    }

    /**
     * Header alignment selected by the editor.
     *
     * Why this is validated:
     * - The value becomes part of a frontend class name in Blade.
     * - Only known alignment values should be allowed.
     */
    protected function headerAlign(): string
    {
        $value = trim((string) get_field('header_align'));
        $allowed = ['left', 'center', 'right'];

        return in_array($value, $allowed, true) ? $value : 'left';
    }

    /**
     * Current layout mode.
     */
    protected function layout(): string
    {
        $layout = trim((string) get_field('layout'));

        return in_array($layout, ['static', 'scroller'], true) ? $layout : 'static';
    }

    /**
     * Editor-configured unique sponsor cap.
     */
    protected function maxItems(): int
    {
        $value = (int) get_field('max_items');

        if ($value < 1) {
            $value = 12;
        }

        return max(1, min(24, $value));
    }

    /**
     * Whether to show company names under each logo.
     */
    protected function showCompanyNames(): bool
    {
        return (bool) get_field('show_company_names');
    }

    /**
     * Section background treatment selected by the editor.
     *
     * Why this is validated:
     * - The value becomes part of a front-end class name.
     * - Only known values should be allowed to prevent accidental broken styles.
     */
    protected function backgroundTreatment(): string
    {
        $value = trim((string) get_field('background_treatment'));
        $allowed = ['none', 'subtle_glow', 'glass_panel', 'neon_band'];

        return in_array($value, $allowed, true) ? $value : 'none';
    }

    /**
     * Inner content width selected by the editor.
     *
     * Why this exists:
     * - The outer section may provide full-width background treatment.
     * - The inner wrapper keeps the heading, description, rail, and CTA aligned
     *   to the site content rhythm.
     */
    protected function contentWidth(): string
    {
        $value = trim((string) get_field('content_width'));
        $allowed = ['wide', 'standard', 'narrow', 'full'];

        return in_array($value, $allowed, true) ? $value : 'wide';
    }

    /**
     * Section vertical spacing selected by the editor.
     */
    protected function verticalSpacing(): string
    {
        $value = trim((string) get_field('vertical_spacing'));
        $allowed = ['none', 'compact', 'normal', 'generous'];

        return in_array($value, $allowed, true) ? $value : 'normal';
    }

    /**
     * Sponsor tile style selected by the editor.
     */
    protected function tileStyle(): string
    {
        $value = trim((string) get_field('tile_style'));
        $allowed = ['glass_neon', 'minimal'];

        return in_array($value, $allowed, true) ? $value : 'glass_neon';
    }

    /**
     * Whether the optional bottom CTA should be considered for rendering.
     *
     * Why this exists:
     * - The block can be used as a plain sponsor logo rail without extra copy.
     * - Editors can intentionally enable/disable the CTA without deleting fields.
     */
    protected function showBottomCta(): bool
    {
        return (bool) get_field('show_bottom_cta');
    }

    /**
     * Optional lead-in copy shown above/next to the bottom CTA button.
     */
    protected function bottomCtaLeadIn(): string
    {
        return trim((string) get_field('bottom_cta_lead_in'));
    }

    /**
     * Normalize the optional bottom CTA link.
     *
     * Why this method exists:
     * - ACF Link fields return URL, title, and target as one array.
     * - The title becomes the visible button text.
     * - The target is locked to known safe values before Blade renders it.
     *
     * @return array{url:string, title:string, target:string, rel:string}|array{}
     */
    protected function bottomCtaLink(): array
    {
        $link = get_field('bottom_cta_link');

        if (!is_array($link) || empty($link['url'])) {
            return [];
        }

        $title = trim((string) ($link['title'] ?? ''));

        if ($title === '') {
            $title = __('Become a Sponsor', 'sccc');
        }

        $target = trim((string) ($link['target'] ?? '_self'));
        $allowedTargets = ['_self', '_blank', '_parent', '_top'];

        if (!in_array($target, $allowedTargets, true)) {
            $target = '_self';
        }

        return [
            'url' => esc_url_raw((string) $link['url']),
            'title' => html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'target' => $target,
            'rel' => $target === '_blank' ? 'noopener noreferrer' : '',
        ];
    }

    /**
     * Query and normalize the sponsors for front-end rendering.
     *
     * Important logic:
     * - post_status must be publish
     * - custom sponsor_status must be active
     * - light/dark sponsor logo fields are preferred when available
     * - featured image is used as the fallback logo
     * - at least one logo source must exist
     * - the logo links to the sponsor CPT single page permalink
     * - the logo link always opens in the same tab/window
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sponsors(): array
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

        $selectedTiers = $this->selectedTierSlugs();

        if (!empty($selectedTiers)) {
            $queryArgs['tax_query'] = [
                [
                    'taxonomy' => 'sponsor_tier',
                    'field' => 'slug',
                    'terms' => $selectedTiers,
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

            if (!empty($selectedTiers) && !in_array($tier['slug'], $selectedTiers, true)) {
                continue;
            }

            $title = $this->normalizedTitle($postId);
            $logoSet = $this->sponsorLogoSet($postId, $title);

            if (empty($logoSet['single_id'])) {
                continue;
            }

            $permalink = get_permalink($postId);

            if (!$permalink) {
                continue;
            }

            $items[] = [
                'id' => $postId,
                'title' => $title,
                'logo_id' => $logoSet['single_id'],
                'logo_alt' => $logoSet['single_alt'],
                'logo_light_id' => $logoSet['light_id'],
                'logo_light_alt' => $logoSet['light_alt'],
                'logo_dark_id' => $logoSet['dark_id'],
                'logo_dark_alt' => $logoSet['dark_alt'],
                'has_dual_logos' => $logoSet['has_dual_logos'],
                'link_url' => $permalink,
                'link_target' => '_self',
                'link_rel' => '',
                'tier_slug' => $tier['slug'],
                'tier_name' => $tier['name'],
                'tier_rank' => $this->tierRank($tier['slug']),
                'tier_weight' => $this->tierWeight($tier['slug']),
            ];
        }

        wp_reset_postdata();

        if (empty($items)) {
            return [];
        }

        usort($items, function (array $left, array $right): int {
            $rankCompare = $left['tier_rank'] <=> $right['tier_rank'];

            if ($rankCompare !== 0) {
                return $rankCompare;
            }

            return strcasecmp((string) $left['title'], (string) $right['title']);
        });

        return array_slice($items, 0, $this->maxItems());
    }

    /**
     * Build a weighted track for scroller mode.
     *
     * Why the algorithm works this way:
     * - Higher tiers get repeated more often.
     * - The same sponsor should not appear back-to-back when avoidable.
     * - A hard cap keeps the marquee length from becoming excessive.
     *
     * @param array<int, array<string, mixed>> $sponsors
     * @return array<int, array<string, mixed>>
     */
    protected function buildWeightedTrack(array $sponsors): array
    {
        if (count($sponsors) <= 1) {
            return $sponsors;
        }

        $remaining = [];
        foreach ($sponsors as $index => $sponsor) {
            $remaining[$index] = (int) ($sponsor['tier_weight'] ?? 1);
        }

        $track = [];
        $lastSponsorId = 0;
        $guard = 0;
        $maxTrackItems = 48;

        while (array_sum($remaining) > 0 && count($track) < $maxTrackItems && $guard < 200) {
            $addedThisPass = false;

            foreach ($sponsors as $index => $sponsor) {
                if (($remaining[$index] ?? 0) < 1) {
                    continue;
                }

                if ((int) $sponsor['id'] === $lastSponsorId) {
                    continue;
                }

                $track[] = $sponsor;
                $remaining[$index]--;
                $lastSponsorId = (int) $sponsor['id'];
                $addedThisPass = true;

                if (count($track) >= $maxTrackItems) {
                    break;
                }
            }

            if (!$addedThisPass) {
                foreach ($sponsors as $index => $sponsor) {
                    if (($remaining[$index] ?? 0) < 1) {
                        continue;
                    }

                    $track[] = $sponsor;
                    $remaining[$index]--;
                    $lastSponsorId = (int) $sponsor['id'];
                    $addedThisPass = true;
                    break;
                }
            }

            if (!$addedThisPass) {
                break;
            }

            $guard++;
        }

        return !empty($track) ? $track : $sponsors;
    }

    /**
     * Marquee duration scales with track length.
     */
    protected function marqueeDuration(array $track): int
    {
        $itemCount = count($track);

        if ($itemCount <= 1) {
            return 0;
        }

        return max(28, min(90, $itemCount * 5));
    }

    /**
     * Tier filter choices shown in the editor.
     *
     * Supporter is kept for compatibility because the current sponsor taxonomy
     * creation code includes it as a default term.
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
     * Selected tiers from the block editor.
     *
     * @return array<int, string>
     */
    protected function selectedTierSlugs(): array
    {
        $selected = get_field('tier_filter');
        $selected = is_array($selected) ? $selected : [];
        $allowed = array_keys($this->tierChoices());

        $selected = array_values(array_filter(array_map('strval', $selected), function (string $slug) use ($allowed): bool {
            return in_array($slug, $allowed, true);
        }));

        return array_values(array_unique($selected));
    }

    /**
     * Return the sponsor's primary tier.
     *
     * ACF uses a radio control for tier selection, so one tier is expected.
     * This method still sorts by priority to stay safe if multiple terms ever
     * get attached manually.
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

        usort($terms, function ($left, $right): int {
            return $this->tierRank((string) $left->slug) <=> $this->tierRank((string) $right->slug);
        });

        $term = $terms[0];

        return [
            'slug' => (string) ($term->slug ?: 'supporter'),
            'name' => (string) ($term->name ?: 'Supporter'),
        ];
    }

    /**
     * Lower rank = higher visual priority.
     */
    protected function tierRank(string $slug): int
    {
        $map = [
            'platinum' => 1,
            'gold' => 2,
            'silver' => 3,
            'bronze' => 4,
            'supporter' => 5,
        ];

        return $map[$slug] ?? 99;
    }

    /**
     * Higher weight = more appearances in scroller mode.
     */
    protected function tierWeight(string $slug): int
    {
        $map = [
            'platinum' => 4,
            'gold' => 3,
            'silver' => 2,
            'bronze' => 1,
            'supporter' => 1,
        ];

        return $map[$slug] ?? 1;
    }

    /**
     * Normalize the sponsor title for display.
     *
     * Why this exists:
     * - Some imported titles may already contain HTML entities such as `&amp;`.
     * - Blade will escape output again in the view.
     * - So we decode once here, then let Blade handle the final safe output.
     */
    protected function normalizedTitle(int $postId): string
    {
        $title = get_the_title($postId);

        if (!is_string($title) || trim($title) === '') {
            return __('Sponsor', 'sccc');
        }

        return html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Resolve the sponsor logo fields using the same fallback order as the
     * single Sponsor template.
     *
     * Fallback order:
     * - Dark display logo: sponsor_logo_dark, then featured image, then sponsor_logo_light.
     * - Light display logo: sponsor_logo_light, then featured image, then sponsor_logo_dark.
     * - Single fallback logo: whichever resolved logo exists first.
     *
     * @return array{
     *   light_id:int,
     *   light_alt:string,
     *   dark_id:int,
     *   dark_alt:string,
     *   single_id:int,
     *   single_alt:string,
     *   has_dual_logos:bool
     * }
     */
    protected function sponsorLogoSet(int $postId, string $fallbackAlt): array
    {
        $logoLightId = $this->attachmentIdFromField(get_field('sponsor_logo_light', $postId));
        $logoDarkId = $this->attachmentIdFromField(get_field('sponsor_logo_dark', $postId));
        $featuredLogoId = (int) get_post_thumbnail_id($postId);

        $darkLogoId = $logoDarkId ?: ($featuredLogoId ?: $logoLightId);
        $lightLogoId = $logoLightId ?: ($featuredLogoId ?: $logoDarkId);
        $singleLogoId = $darkLogoId ?: $lightLogoId;

        return [
            'light_id' => $lightLogoId,
            'light_alt' => $lightLogoId ? $this->logoAlt($lightLogoId, $fallbackAlt) : $fallbackAlt,
            'dark_id' => $darkLogoId,
            'dark_alt' => $darkLogoId ? $this->logoAlt($darkLogoId, $fallbackAlt) : $fallbackAlt,
            'single_id' => $singleLogoId,
            'single_alt' => $singleLogoId ? $this->logoAlt($singleLogoId, $fallbackAlt) : $fallbackAlt,
            'has_dual_logos' => (bool) ($darkLogoId && $lightLogoId && $darkLogoId !== $lightLogoId),
        ];
    }

    /**
     * Normalize an ACF image field value to an attachment ID.
     *
     * Why this is defensive:
     * - Current Sponsor logo fields are expected to return an ID.
     * - If that return format changes to array later, the block should not
     *   accidentally treat the array as attachment ID 1.
     */
    protected function attachmentIdFromField(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_array($value) && isset($value['ID']) && is_numeric($value['ID'])) {
            return (int) $value['ID'];
        }

        if (is_array($value) && isset($value['id']) && is_numeric($value['id'])) {
            return (int) $value['id'];
        }

        return 0;
    }

    /**
     * Build a reliable image alt string.
     */
    protected function logoAlt(int $logoId, string $fallback): string
    {
        $alt = trim((string) get_post_meta($logoId, '_wp_attachment_image_alt', true));

        return $alt !== '' ? html_entity_decode($alt, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $fallback;
    }
}