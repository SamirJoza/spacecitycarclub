<?php
/**
 * File path + filename: app/Options/CarShowSettings.php
 *
 * Purpose:
 * - Register the Car Show Settings child page under Theme Settings.
 *
 * Why this file exists:
 * - Car registration needs a few site-level settings: who gets the staff copy
 *   of each registration, an optional note for the owner's confirmation email,
 *   and the closed / full messages shown on the event page.
 * - The status box at the top (CarRegistrationForm::renderStatus) shows whether
 *   the Car Registration form, the Stripe feed and any car shows exist, and
 *   offers "Sync car registration form from code".
 *
 * Important notes:
 * - Per-show settings (fee, deadline, max cars) live on each event, in the
 *   "Club Show" box / Modern editor step — not here.
 * - Field names are read by App\Support\CarShows\CarShows::option().
 */

declare(strict_types=1);

namespace App\Options;

use Log1x\AcfComposer\Builder;
use Log1x\AcfComposer\Options as Field;

class CarShowSettings extends Field
{
    /**
     * The child page menu label.
     *
     * @var string
     */
    public $name = 'Car Show Settings';

    /**
     * The child page document title.
     *
     * @var string
     */
    public $title = 'Car Show Settings | Theme Settings';

    /**
     * Stable child page slug (used by the Theme Settings side navigation).
     *
     * @var string
     */
    public $slug = 'theme-settings-car-show-settings';

    /**
     * Attach this page under the Theme Settings parent page.
     *
     * @var string
     */
    public $parent = 'theme-settings';

    /**
     * The option page field group.
     *
     * @return array
     */
    public function fields(): array
    {
        $fields = Builder::make('car_show_settings');

        $fields
            ->addMessage('How car shows are recognised', 'An event is a car show when its Category is "Car Show" and its Organizer is "Space City Car Club". On those events the MagePeople ticket box and seat counts are replaced by the Car Registration form and "Cars registered". Prices, early-bird windows and the number of cars come from the event\'s own Ticket & Pricing tickets.', [
                'new_lines' => 'wpautop',
            ])
            ->addEmail('car_show_staff_email', [
                'label' => 'Staff notification email',
                'instructions' => 'Gets a copy of every car registration and is the Reply-To on owner emails. Defaults to the vendor staff email, then the site admin email.',
                'wrapper' => ['width' => '50'],
            ])
            ->addTextarea('car_show_email_note', [
                'label' => 'Extra text for the owner confirmation email',
                'instructions' => 'Optional, e.g. arrival time, parking or what to bring.',
                'rows' => 3,
                'new_lines' => '',
                'wrapper' => ['width' => '50'],
            ])
            ->addText('car_show_closed_message', [
                'label' => 'Message when registration is closed',
                'default_value' => 'Car registration for this show is closed.',
                'wrapper' => ['width' => '50'],
            ])
            ->addText('car_show_full_message', [
                'label' => 'Message when the show is full',
                'default_value' => 'This show is full — every car spot has been taken. Follow us for the next one!',
                'wrapper' => ['width' => '50'],
            ]);

        return $fields->build();
    }
}
