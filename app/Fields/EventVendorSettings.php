<?php

/**
 * File path + filename: app/Fields/EventVendorSettings.php
 *
 * Purpose:
 * - Adds a "Club Show" box to the sidebar of MagePeople events (`mep_events`):
 *   a car-registration note and the vendor settings.
 *
 * Car registration:
 * - Only used on car shows (Category "Car Show" + Organizer "Space City Car
 *   Club" — see App\Support\CarShows\CarShows). Prices and capacity come from
 *   MagePeople's own ticket types, so this box only explains that.
 *
 * Why this file exists:
 * - Events are both our own club shows and other organizations' shows we
 *   promote. Only events with "Club show: accept vendor applications" switched
 *   on take part in the vendor feature — everything else is ignored.
 * - Per show: the vendor fee, whether food vendors are allowed, an optional
 *   application deadline, and optional booth-type pricing for the future.
 * - This box shows in MagePeople's Classic editor. The Modern editor doesn't
 *   render meta boxes, so the same fields appear there as a "Club Show" step
 *   (app/Support/Vendors/EventVendorModernEditor.php).
 */

namespace App\Fields;

use App\Support\Vendors\Vendors;
use Log1x\AcfComposer\Field;
use StoutLogic\AcfBuilder\FieldsBuilder;

class EventVendorSettings extends Field
{
    public function fields(): array
    {
        $fields = new FieldsBuilder('event_vendor_settings', [
            'title' => 'Club Show',
            'position' => 'side',
            'style' => 'default',
        ]);

        $fields->setLocation('post_type', '==', Vendors::EVENT_POST_TYPE);

        $fields
            ->addMessage('Car registration', 'Used when the event\'s Category is "Car Show" and its Organizer is "Space City Car Club". Prices come from the Ticket & Pricing tickets: each ticket type is a registration option (e.g. "Early Bird" with a sale end date, and "Regular"), and its quantity is the number of cars it allows.', [
                'key' => 'field_event_car_registration_message',
            ])
            ->addMessage('Vendors', 'Vendor applications for our own shows.', [
                'key' => 'field_event_vendor_message',
            ])
            ->addTrueFalse(Vendors::EVENT_ACCEPTING, [
                'key' => 'field_event_vendors_accepting',
                'label' => 'Club show: accept vendor applications',
                'instructions' => 'Only for our own shows. Leave off for other organizations\' events — they never appear in the vendor forms or blocks.',
                'default_value' => 0,
                'ui' => 1,
            ])
            ->addNumber(Vendors::EVENT_FEE, [
                'key' => 'field_event_vendor_fee_default',
                'label' => 'Vendor fee',
                'instructions' => 'Price per vendor for this show.',
                'prepend' => '$',
                'min' => 0,
                'step' => 0.01,
            ])
                ->conditional(Vendors::EVENT_ACCEPTING, '==', '1')
            ->addTrueFalse(Vendors::EVENT_FOOD_ALLOWED, [
                'key' => 'field_event_vendors_food_allowed',
                'label' => 'Food vendors allowed',
                'instructions' => 'When off, food vendors cannot apply or sign up for this show and are told why.',
                'default_value' => 1,
                'ui' => 1,
            ])
                ->conditional(Vendors::EVENT_ACCEPTING, '==', '1')
            ->addDatePicker(Vendors::EVENT_DEADLINE, [
                'key' => 'field_event_vendor_application_deadline',
                'label' => 'Application deadline',
                'instructions' => 'Optional. The show closes to vendors after this day.',
                'display_format' => 'M j, Y',
                'return_format' => 'Y-m-d',
            ])
                ->conditional(Vendors::EVENT_ACCEPTING, '==', '1')
            ->addSelect(Vendors::EVENT_PRICING_MODE, [
                'key' => 'field_event_vendor_pricing_mode',
                'label' => 'Pricing',
                'choices' => [
                    'flat' => 'Flat fee (vendor fee above)',
                    'booth' => 'By booth type',
                ],
                'default_value' => 'flat',
                'return_format' => 'value',
            ])
                ->conditional(Vendors::EVENT_ACCEPTING, '==', '1')
            ->addRepeater(Vendors::EVENT_BOOTH_PRICES, [
                'key' => 'field_event_vendor_booth_prices',
                'label' => 'Booth prices',
                'instructions' => 'Booth types not listed use the vendor fee above.',
                'layout' => 'table',
                'button_label' => 'Add booth price',
                // Set directly: conditional() on a repeater would target the repeater's sub-fields.
                'conditional_logic' => [[
                    ['field' => 'field_event_vendor_pricing_mode', 'operator' => '==', 'value' => 'booth'],
                    ['field' => 'field_event_vendors_accepting', 'operator' => '==', 'value' => '1'],
                ]],
            ])
                ->addSelect('booth_type', [
                    'key' => 'field_event_vendor_booth_type',
                    'label' => 'Booth',
                    'choices' => Vendors::boothTypes(),
                    'return_format' => 'value',
                ])
                ->addNumber('price', [
                    'key' => 'field_event_vendor_booth_price',
                    'label' => 'Price',
                    'prepend' => '$',
                    'min' => 0,
                    'step' => 0.01,
                ])
            ->endRepeater();

        return $fields->build();
    }
}
