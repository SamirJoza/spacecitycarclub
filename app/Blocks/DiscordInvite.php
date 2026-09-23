<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

use function get_field;
use function in_array;
use function is_array;
use function is_string;
use function trim;

/**
 * -----------------------------------------------------------------------------
 * File path + filename: app/Blocks/DiscordInvite.php
 * -----------------------------------------------------------------------------
 * Purpose:
 * - Register a compact Gutenberg block for the club's Discord/community invite.
 * - Keep the block visually close to the approved compact example while letting
 *   editors control the icon name and button text directly.
 *
 * Why this file exists:
 * - The FAQ sidebar needs a reusable community invite card that can also be
 *   reused in other widget/sidebar areas later.
 * - The block is primarily edited inside the block/widget editor sidebar, not
 *   inline in the preview.
 *
 * Notes:
 * - This block stays on the project's existing ACF Composer pattern.
 * - The corresponding Blade view is:
 *   resources/views/blocks/discord-invite.blade.php
 * - The icon is rendered using the Material Symbols ligature name provided by
 *   the editor. The font itself is expected to already be loaded globally.
 * -----------------------------------------------------------------------------
 */
class DiscordInvite extends Block
{
    /**
     * Block title shown in the editor.
     *
     * @var string
     */
    public $name = 'Discord Invite';

    /**
     * Short editor description.
     *
     * @var string
     */
    public $description = 'Compact neon-glass Discord/community invite card for sidebars and widget areas.';

    /**
     * Block category.
     *
     * @var string
     */
    public $category = 'widgets';

    /**
     * Block icon.
     *
     * @var string|array
     */
    public $icon = 'format-chat';

    /**
     * Block search keywords.
     *
     * @var array
     */
    public $keywords = [
        'discord',
        'community',
        'chat',
        'invite',
        'sidebar',
        'club',
    ];

    /**
     * Default editor mode.
     *
     * @var string
     */
    public $mode = 'preview';

    /**
     * WordPress block API version.
     *
     * @var int|null
     */
    public $apiVersion = 3;

    /**
     * ACF internal block version.
     *
     * @var int
     */
    public $blockVersion = 3;

    /**
     * Block supports.
     *
     * @var array
     */
    public $supports = [
        'align'         => false,
        'align_text'    => false,
        'align_content' => false,
        'full_height'   => false,
        'anchor'        => true,
        'mode'          => false,
        'multiple'      => true,
        'jsx'           => true,
    ];

    /**
     * Data passed to the Blade view.
     */
    public function with(): array
    {
        $legacyButton = $this->linkField('button', [
            'title'  => 'Join Waitlist',
            'url'    => '#',
            'target' => '',
        ]);

        $buttonLink = $this->linkField('button_link', [
            'title'  => '',
            'url'    => $legacyButton['url'] ?? '#',
            'target' => $legacyButton['target'] ?? '',
        ]);

        $buttonText = $this->stringField(
            'button_text',
            trim((string) ($legacyButton['title'] ?? '')) !== '' ? (string) $legacyButton['title'] : 'Join Waitlist'
        );

        return [
            'iconName'     => $this->iconName(),
            'eyebrow'      => $this->stringField('eyebrow', 'Club Chat'),
            'headline'     => $this->stringField('headline', 'Join the Discord Garage'),
            'description'  => $this->stringField(
                'description',
                'Get meet chatter, event alerts, and club talk in one clean spot.'
            ),
            'status'       => $this->stringField('status', 'Launching soon'),
            'accentTheme'  => $this->accentTheme(),
            'buttonText'   => $buttonText,
            'buttonLink'   => $buttonLink,
        ];
    }

    /**
     * Define the field group.
     */
    public function fields(): array
    {
        $fields = Builder::make('discord_invite');

        $fields
            ->addText('icon_name', [
                'label'         => 'Material Symbol Icon Name',
                'instructions'  => 'Enter a Material Symbols ligature name, for example: forum, groups, forum, campaign, chat, hub, or notifications.',
                'default_value' => 'forum',
                'wrapper'       => ['width' => '50'],
            ])
            ->addSelect('accent_theme', [
                'label'         => 'Accent Theme',
                'choices'       => [
                    'blue'   => 'Electric Blue',
                    'violet' => 'Neon Violet',
                    'red'    => 'Signal Red',
                ],
                'default_value' => 'blue',
                'ui'            => 1,
                'return_format' => 'value',
                'wrapper'       => ['width' => '50'],
            ])
            ->addText('eyebrow', [
                'label'         => 'Eyebrow',
                'default_value' => 'Club Chat',
                'wrapper'       => ['width' => '50'],
            ])
            ->addText('status', [
                'label'         => 'Status Pill',
                'default_value' => 'Launching soon',
                'wrapper'       => ['width' => '50'],
            ])
            ->addText('headline', [
                'label'         => 'Headline',
                'default_value' => 'Join the Discord Garage',
                'required'      => 1,
            ])
            ->addTextarea('description', [
                'label'         => 'Description',
                'rows'          => 3,
                'new_lines'     => 'br',
                'default_value' => 'Get meet chatter, event alerts, and club talk in one clean spot.',
            ])
            ->addText('button_text', [
                'label'         => 'Button Text',
                'default_value' => 'Join Waitlist',
                'wrapper'       => ['width' => '50'],
            ])
            ->addLink('button_link', [
                'label'         => 'Button Link',
                'instructions'  => 'Set the destination URL for the button.',
                'return_format' => 'array',
                'wrapper'       => ['width' => '50'],
            ]);

        return $fields->build();
    }

    /**
     * Resolve the icon name safely.
     */
    protected function iconName(): string
    {
        $value = $this->stringField('icon_name', 'forum');

        return $value !== '' ? $value : 'forum';
    }

    /**
     * Resolve the accent theme safely.
     */
    protected function accentTheme(): string
    {
        $value = $this->stringField('accent_theme', 'blue');
        $allowed = ['blue', 'violet', 'red'];

        return in_array($value, $allowed, true) ? $value : 'blue';
    }

    /**
     * Resolve a plain string field with fallback.
     */
    protected function stringField(string $name, string $fallback = ''): string
    {
        $value = get_field($name);

        if (! is_string($value)) {
            return $fallback;
        }

        $value = trim($value);

        return $value !== '' ? $value : $fallback;
    }

    /**
     * Resolve a link field with fallback.
     */
    protected function linkField(string $name, array $fallback): array
    {
        $value = get_field($name);

        if (! is_array($value)) {
            return $fallback;
        }

        $url = trim((string) ($value['url'] ?? ''));
        $title = trim((string) ($value['title'] ?? ''));
        $target = trim((string) ($value['target'] ?? ''));

        return [
            'title'  => $title !== '' ? $title : ($fallback['title'] ?? ''),
            'url'    => $url !== '' ? $url : ($fallback['url'] ?? ''),
            'target' => $target !== '' ? $target : ($fallback['target'] ?? ''),
        ];
    }
}