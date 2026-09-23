<?php

/**
 * ============================================================================
 * File path + filename: app/Blocks/GarageShowcase.php
 * ============================================================================
 *
 * Purpose:
 * - Register a dynamic ACF Composer Gutenberg block that renders a
 *   "Garage Showcase" section with:
 *   - one flexible InnerBlocks intro area
 *   - five random vehicles
 *   - no duplicated members in the same render
 *
 * Why this file exists:
 * - The user wants a reusable page block inspired by the "The Garage" section.
 * - The content should be mostly automatic:
 *   - no manual vehicle picker
 *   - no manual member picker
 *   - only a flexible intro area for richer copy when needed
 *
 * Data source:
 * - Member vehicles live on the WordPress user via the ACF repeater field
 *   `vehicles`.
 * - Each vehicle row can contain:
 *   - vehicle_id
 *   - year
 *   - make_raw
 *   - model
 *   - nickname
 *   - image
 *
 * Selection rules:
 * - Pull only club members.
 * - Each selected tile must come from a different member.
 * - If a member has multiple imaged vehicles, choose one random vehicle for
 *   that member.
 * - If fewer than five eligible members exist, render however many are found.
 *
 * Caching:
 * - Frontend selection is cached briefly so the gallery does not reshuffle on
 *   every page load.
 * - Admin/editor requests bypass the transient so previews reflect current data
 *   more immediately.
 *
 * Editorial UX:
 * - No ACF input fields are required.
 * - The block uses one InnerBlocks intro area with a starter Heading +
 *   Paragraph template.
 * - The template is not locked, so editors may remove or replace those starter
 *   blocks and add richer content later.
 *
 * Assumptions:
 * - ACF Composer block discovery is already wired up in the Sage theme.
 * - The project role slug for members is `sccc_member`, unless a site-specific
 *   override/filter is supplied.
 */

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;
use WP_User;

class GarageShowcase extends Block
{
    /**
     * Block name shown in the inserter.
     *
     * @var string
     */
    public $name = 'Garage Showcase';

    /**
     * Block description shown in the inserter.
     *
     * @var string
     */
    public $description = 'Automatic garage mosaic showing random vehicles from different members, with a flexible intro area.';

    /**
     * Stable slug for the block.
     *
     * @var string
     */
    public $slug = 'garage-showcase';

    /**
     * Gutenberg category.
     *
     * @var string
     */
    public $category = 'theme-blocks';

    /**
     * Dashicon shown in the inserter.
     *
     * @var string|array
     */
    public $icon = 'images-alt2';

    /**
     * Search keywords for the block inserter.
     *
     * @var array<int, string>
     */
    public $keywords = ['garage', 'vehicles', 'members', 'gallery', 'showcase'];

    /**
     * Blade view used to render the block.
     *
     * @var string
     */
    public $view = 'blocks.garage-showcase';

    /**
     * Keep preview visible in the editor.
     *
     * Why preview:
     * - The block has no traditional ACF content fields.
     * - The editable intro lives in InnerBlocks and should remain visible in
     *   the block canvas.
     *
     * @var string
     */
    public $mode = 'preview';

    /**
     * Block support settings.
     *
     * Important:
     * - JSX must be enabled for InnerBlocks.
     * - Mode switching is disabled so the block stays in preview behavior.
     *
     * @var array<string, mixed>
     */
    public $supports = [
        'anchor' => true,
        'multiple' => true,
        'jsx' => true,
        'mode' => false,
        'align' => false,
    ];

    /**
     * Maximum number of member vehicles to render.
     *
     * @var int
     */
    protected int $limit = 5;

    /**
     * Build the view data passed into the Blade template.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'items' => $this->garageItems(),
            'allowedBlocks' => $this->allowedBlocks(),
            'innerBlocksTemplate' => $this->innerBlocksTemplate(),
            'isPreview' => (bool) $this->preview,
        ];
    }

    /**
     * The block field group.
     *
     * Why this group is intentionally empty:
     * - This block is driven by live member vehicle data.
     * - The editable intro uses InnerBlocks instead of ACF fields.
     * - ACF Blocks support blocks without fields, which keeps this block
     *   cleaner for editors.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $fields = Builder::make('garage_showcase');

        return $fields->build();
    }

    /**
     * Allowed blocks for the intro area.
     *
     * Why this list exists:
     * - The user wanted richer copy flexibility.
     * - We allow a curated set that makes sense for a section intro.
     *
     * @return array<int, string>
     */
    protected function allowedBlocks(): array
    {
        return [
            'core/heading',
            'core/paragraph',
            'core/buttons',
            'core/list',
            'core/group',
            'core/spacer',
        ];
    }

    /**
     * Starter template for the intro area.
     *
     * Why this template exists:
     * - Gives editors a sensible starting point.
     * - Because templateLock is omitted, editors may remove or replace these
     *   starter blocks later.
     *
     * @return array<int, array<int|string, mixed>>
     */
    protected function innerBlocksTemplate(): array
    {
        return [
            [
                'core/heading',
                [
                    'level' => 2,
                    'placeholder' => 'Add section heading',
                    'content' => 'The Garage',
                ],
            ],
            [
                'core/paragraph',
                [
                    'placeholder' => 'Add supporting copy for this section...',
                    'content' => 'A glimpse into our diverse fleet.',
                ],
            ],
        ];
    }

    /**
     * Return the final showcase items for the gallery.
     *
     * Why this helper exists:
     * - Keeps selection, normalization, and short-lived caching out of the view.
     * - Ensures one member contributes at most one vehicle to the final render.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function garageItems(): array
    {
        if (! function_exists('get_field')) {
            return [];
        }

        if (! is_admin()) {
            $cached = get_transient($this->transientKey());

            if (is_array($cached)) {
                return $cached;
            }
        }

        $memberPools = $this->candidateMemberPools();

        if (empty($memberPools)) {
            return [];
        }

        shuffle($memberPools);

        $selectedMembers = array_slice($memberPools, 0, $this->limit);
        $items = [];

        foreach ($selectedMembers as $memberPool) {
            $vehicleKey = array_rand($memberPool['vehicles']);
            $items[] = $memberPool['vehicles'][$vehicleKey];
        }

        if (! is_admin()) {
            set_transient($this->transientKey(), $items, 15 * MINUTE_IN_SECONDS);
        }

        return $items;
    }

    /**
     * Build a per-member pool of eligible vehicles.
     *
     * Why this helper exists:
     * - We randomize at the member level first to avoid over-representing
     *   members who added many vehicles.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function candidateMemberPools(): array
    {
        $users = $this->candidateMembers();

        if (empty($users)) {
            return [];
        }

        $memberPools = [];

        foreach ($users as $user) {
            if (! $user instanceof WP_User) {
                continue;
            }

            $rows = get_field('vehicles', 'user_' . $user->ID);

            if (! is_array($rows) || empty($rows)) {
                continue;
            }

            $eligibleVehicles = [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $normalized = $this->normalizeVehicleRow($row, $user);

                if ($normalized) {
                    $eligibleVehicles[] = $normalized;
                }
            }

            if (! empty($eligibleVehicles)) {
                $memberPools[] = [
                    'user_id' => (int) $user->ID,
                    'vehicles' => $eligibleVehicles,
                ];
            }
        }

        return $memberPools;
    }

    /**
     * Query candidate club members.
     *
     * Strategy:
     * - First try a narrower query using the ACF repeater row count meta key
     *   `vehicles > 0`.
     * - If that returns nothing, fall back to a broader role-only query so the
     *   block remains resilient if repeater row count storage differs.
     *
     * @return array<int, \WP_User>
     */
    protected function candidateMembers(): array
    {
        $role = $this->memberRole();

        $users = get_users([
            'role' => $role,
            'fields' => 'all',
            'orderby' => 'ID',
            'order' => 'ASC',
            'count_total' => false,
            'meta_query' => [
                [
                    'key' => 'vehicles',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'NUMERIC',
                ],
            ],
        ]);

        if (is_array($users) && ! empty($users)) {
            return $users;
        }

        $fallbackUsers = get_users([
            'role' => $role,
            'fields' => 'all',
            'orderby' => 'ID',
            'order' => 'ASC',
            'count_total' => false,
        ]);

        return is_array($fallbackUsers) ? $fallbackUsers : [];
    }

    /**
     * Normalize a raw vehicle repeater row into a view-friendly item.
     *
     * A row is considered eligible only when:
     * - it has an image attachment ID
     * - that attachment resolves to a usable image URL
     *
     * @param  array<string, mixed>  $row
     * @param  \WP_User              $user
     * @return array<string, mixed>|null
     */
    protected function normalizeVehicleRow(array $row, WP_User $user): ?array
    {
        $imageId = isset($row['image']) ? (int) $row['image'] : 0;

        if (! $imageId) {
            return null;
        }

        if (! wp_get_attachment_image_url($imageId, 'large')) {
            return null;
        }

        $year = trim((string) ($row['year'] ?? ''));
        $make = trim((string) ($row['make_raw'] ?? $this->fallbackMake($row)));
        $model = trim((string) ($row['model'] ?? ''));
        $nickname = trim((string) ($row['nickname'] ?? ''));

        $titleParts = array_filter([$year, $make, $model]);
        $title = trim(implode(' ', $titleParts));

        if ($title === '') {
            $title = $nickname !== '' ? $nickname : 'Member Vehicle';
        }

        $subtitle = $nickname !== '' ? $nickname : $user->display_name;
        $fallbackAlt = $title . ' — ' . $user->display_name;

        return [
            'image_id' => $imageId,
            'title' => $title,
            'subtitle' => $subtitle,
            'member_name' => $user->display_name,
            'vehicle_id' => trim((string) ($row['vehicle_id'] ?? '')),
            'alt' => $fallbackAlt,
        ];
    }

    /**
     * Compute a make value when `make_raw` is missing.
     *
     * Why this helper exists:
     * - The MemberProfile field group already syncs `make_raw`, but this block
     *   should still be resilient if older data exists.
     *
     * @param  array<string, mixed>  $row
     * @return string
     */
    protected function fallbackMake(array $row): string
    {
        $makeSelect = trim((string) ($row['make_select'] ?? ''));
        $makeOther = trim((string) ($row['make_other'] ?? ''));

        if ($makeSelect === 'Other' && $makeOther !== '') {
            return $makeOther;
        }

        return $makeSelect;
    }

    /**
     * Resolve the member role slug for the garage showcase.
     *
     * Why this helper exists:
     * - The project has referenced `sccc_member` as the member role.
     * - A filter is provided so the role can be overridden centrally later
     *   without rewriting the block.
     *
     * @return string
     */
    protected function memberRole(): string
    {
        $role = 'sccc_member';

        if (defined('ROLE_KEY_CLUB_MEMBER') && is_string(constant('ROLE_KEY_CLUB_MEMBER'))) {
            $role = (string) constant('ROLE_KEY_CLUB_MEMBER');
        }

        /**
         * Allow project-level overrides without editing the block again.
         */
        $role = apply_filters('sccc_garage_showcase_member_role', $role);

        return is_string($role) && trim($role) !== '' ? trim($role) : 'sccc_member';
    }

    /**
     * Transient key for cached front-end selections.
     *
     * @return string
     */
    protected function transientKey(): string
    {
        return 'sccc_garage_showcase_selection_v1';
    }
}