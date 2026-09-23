<?php

/**
 * ============================================================================
 * File path + filename: app/Blocks/JourneyTimeline.php
 * ============================================================================
 *
 * Purpose:
 * - Register a flexible ACF Composer timeline block for page content.
 *
 * Why this file exists:
 * - The user wants a true page/content block rather than a sidebar widget.
 * - The visual target is the "OUR JOURNEY" timeline section from the provided
 *   HTML example, but the content must be fully editable in Gutenberg.
 * - The block uses a repeater so editors can add, remove, and reorder timeline
 *   entries without touching code.
 *
 * Editor experience goals:
 * - Keep the block easy to use:
 *   - section heading
 *   - optional intro
 *   - repeater of timeline items
 * - Each item has:
 *   - date/label
 *   - title
 *   - description
 *   - curated Material Symbol picker
 *   - optional custom icon override
 *   - optional emphasis toggle
 *
 * Why the icon field is built this way:
 * - The site already uses `material-symbols-outlined`.
 * - Material Symbols are referenced by glyph name, so the block stores the
 *   actual symbol name.
 * - A curated select list is easier for editors than typing icon names from
 *   memory, while the custom override still exists for edge cases.
 *
 * Assumptions:
 * - ACF Composer block discovery is already working in the Sage theme.
 * - The Material Symbols font is already loaded globally by the site/theme.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class JourneyTimeline extends Block
{
    /**
     * The block name shown in the editor inserter.
     *
     * @var string
     */
    public $name = 'Journey Timeline';

    /**
     * The block description shown in the editor inserter.
     *
     * @var string
     */
    public $description = 'A flexible page timeline block with a curated Material Symbols icon picker.';

    /**
     * Stable slug for the block.
     *
     * @var string
     */
    public $slug = 'journey-timeline';

    /**
     * Block category.
     *
     * Why "layout":
     * - This is a full page/content section, not a small utility widget.
     *
     * @var string
     */
    public $category = 'layout';

    /**
     * Dashicon for the block picker.
     *
     * @var string|array
     */
    public $icon = 'clock';

    /**
     * Keywords to help users find the block quickly.
     *
     * @var array<int, string>
     */
    public $keywords = ['timeline', 'journey', 'history', 'milestones', 'about'];

    /**
     * Blade view used to render the block.
     *
     * @var string
     */
    public $view = 'blocks.journey-timeline';

    /**
     * Default block mode.
     *
     * Why auto:
     * - Editors see a preview by default.
     * - Clicking the block still gives a more natural editing experience.
     *
     * @var string
     */
    public $mode = 'auto';

    /**
     * Block support configuration.
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
        return [
            'heading' => $this->stringField('heading', 'Our Journey'),
            'intro' => $this->stringField('intro', ''),
            'items' => $this->timelineItems(),
            'isPreview' => (bool) $this->preview,
        ];
    }

    /**
     * The ACF field group for this block.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $fields = Builder::make('journey_timeline');

        $fields
            ->addText('heading', [
                'label' => 'Section Heading',
                'default_value' => 'Our Journey',
                'required' => 0,
            ])
            ->addTextarea('intro', [
                'label' => 'Intro Text',
                'instructions' => 'Optional short intro above the timeline.',
                'rows' => 3,
                'new_lines' => 'br',
                'required' => 0,
            ])
            ->addRepeater('items', [
                'label' => 'Timeline Items',
                'instructions' => 'Add, remove, and reorder milestones for the timeline.',
                'layout' => 'block',
                'button_label' => 'Add Timeline Item',
                'min' => 1,
                'required' => 1,
            ])
                ->addText('date_label', [
                    'label' => 'Date / Label',
                    'instructions' => 'Examples: 2018, Spring 2022, Q4 2024, Website Launch.',
                    'required' => 1,
                    'wrapper' => [
                        'width' => '25',
                    ],
                ])
                ->addText('title', [
                    'label' => 'Title',
                    'required' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ])
                ->addTrueFalse('is_emphasized', [
                    'label' => 'Emphasize this item?',
                    'instructions' => 'Adds a slightly stronger visual emphasis to this milestone.',
                    'ui' => 1,
                    'default_value' => 0,
                    'wrapper' => [
                        'width' => '25',
                    ],
                ])
                ->addSelect('icon', [
                    'label' => 'Timeline Icon',
                    'instructions' => 'Choose a Material Symbol from the curated list below.',
                    'choices' => $this->iconChoices(),
                    'default_value' => 'flag',
                    'ui' => 1,
                    'allow_null' => 0,
                    'required' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ])
                ->addText('custom_icon', [
                    'label' => 'Custom Material Symbol Override',
                    'instructions' => 'Optional. Leave empty to use the selected icon above. Example: language',
                    'required' => 0,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ])
                ->addTextarea('description', [
                    'label' => 'Description',
                    'rows' => 4,
                    'new_lines' => 'br',
                    'required' => 1,
                ])
            ->endRepeater();

        return $fields->build();
    }

    /**
     * Build the timeline rows for the view.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function timelineItems(): array
    {
        $rows = function_exists('get_field') ? get_field('items') : null;

        if (! is_array($rows)) {
            return [];
        }

        $items = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $dateLabel = trim((string) ($row['date_label'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $selectedIcon = trim((string) ($row['icon'] ?? 'flag'));
            $customIcon = trim((string) ($row['custom_icon'] ?? ''));
            $isEmphasized = ! empty($row['is_emphasized']);

            if ($dateLabel === '' && $title === '' && $description === '') {
                continue;
            }

            $items[] = [
                'dateLabel' => $dateLabel,
                'title' => $title,
                'description' => $description,
                'icon' => $customIcon !== '' ? $customIcon : $selectedIcon,
                'isEmphasized' => $isEmphasized,
            ];
        }

        return $items;
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
     * Curated Material Symbols choices for the timeline picker.
     *
     * Why this list is intentionally broad:
     * - The user asked for an extensive curated set so editors can almost
     *   always find something suitable without needing a custom override.
     * - Labels are written in plain English so the dropdown remains usable.
     *
     * Includes a specific website-launch-friendly icon:
     * - `language`
     *
     * @return array<string, string>
     */
    protected function iconChoices(): array
    {
        return [
            'flag' => 'Flag — Founding / Milestone',
            'rocket_launch' => 'Rocket Launch — Launch / Big Step',
            'language' => 'Language — Website Launch / Online Presence',
            'public' => 'Public — Visibility / Public Presence',
            'groups' => 'Groups — Community Growth',
            'group_add' => 'Group Add — Membership Growth',
            'diversity_3' => 'Diversity 3 — Community / Inclusion',
            'handshake' => 'Handshake — Partnership / Collaboration',
            'volunteer_activism' => 'Volunteer Activism — Charity / Giving Back',
            'favorite' => 'Favorite — Appreciation / Support',
            'event' => 'Event — Event / Meetup',
            'calendar_month' => 'Calendar Month — Recurring Milestone / Schedule',
            'celebration' => 'Celebration — Party / Achievement',
            'emoji_events' => 'Emoji Events — Award / Recognition',
            'trophy' => 'Trophy — Competition / Win',
            'workspace_premium' => 'Workspace Premium — Recognition / Featured Status',
            'military_tech' => 'Military Tech — Badge / Achievement',
            'stars' => 'Stars — Highlight / Special Moment',
            'auto_awesome' => 'Auto Awesome — Big Moment / Shine',
            'history' => 'History — Historic Milestone',
            'timeline' => 'Timeline — Progress / Sequence',
            'update' => 'Update — New Phase / Revision',
            'lightbulb' => 'Lightbulb — Idea / Origin Story',
            'forum' => 'Forum — Community Voice / Discussion',
            'campaign' => 'Campaign — Announcement / Outreach',
            'newspaper' => 'Newspaper — Press / News',
            'photo_camera' => 'Photo Camera — Media / Photography',
            'videocam' => 'Videocam — Video / Content',
            'build' => 'Build — Build / Project',
            'engineering' => 'Engineering — Technical / Development',
            'precision_manufacturing' => 'Precision Manufacturing — Garage / Shop / Build',
            'settings' => 'Settings — Operations / Systems',
            'security' => 'Security — Safety / Responsibility',
            'school' => 'School — Learning / Workshop',
            'speed' => 'Speed — Performance / Momentum',
            'directions_car' => 'Directions Car — Car Culture / Vehicles',
            'route' => 'Route — Cruise / Road Trip',
            'map' => 'Map — Route Planning / Expansion',
            'location_on' => 'Location On — Venue / New Home',
            'place' => 'Place — Destination / Landmark',
            'apartment' => 'Apartment — Headquarters / Venue',
            'storefront' => 'Storefront — Sponsor / Vendor',
            'paid' => 'Paid — Fundraising / Donation',
            'inventory_2' => 'Inventory 2 — Merch / Product Drop',
            'support_agent' => 'Support Agent — Member Support / Help',
        ];
    }
}