<?php

/**
 * app/Fields/Sponsor.php
 * File: app/Fields/Sponsor.php
 */

namespace App\Fields;

use Log1x\AcfComposer\Field;
use StoutLogic\AcfBuilder\FieldsBuilder;

class Sponsor extends Field
{
  public function fields(): array
  {
    $fields = new FieldsBuilder('sponsor');

    $fields->setLocation('post_type', '==', 'sponsor');

    // =========================
    // Tab: Company
    // =========================
    $fields
      ->addTab('Company')
      
      ->addText('sponsor_company_tagline', [
        'key' => 'field_sponsor_company_tagline',
        'label' => 'Company Tagline',
        'instructions' => '',
        'wrapper' => ['width' => '50'],
      ])

      ->addText('sponsor_company_website', [
        'key' => 'field_sponsor_company_website',
        'label' => 'Company Website',
        'instructions' => '',
        'placeholder' => 'Full URL (https://...)',
        'wrapper' => ['width' => '50'],
      ])

      // Address row 1
      ->addText('sponsor_company_address_1', [
        'key' => 'field_sponsor_company_address_1',
        'label' => 'Address',
        'wrapper' => ['width' => '50'],
      ])
      ->addText('sponsor_company_address_2', [
        'key' => 'field_sponsor_company_address_2',
        'label' => 'Suite, Unit, etc.',
        'instructions' => '',
        'wrapper' => ['width' => '50'],
      ])

      // Address row 2
      ->addText('sponsor_company_city', [
        'key' => 'field_sponsor_company_city',
        'label' => 'City',
        'wrapper' => ['width' => '40'],
      ])
      ->addSelect('sponsor_company_state', [
        'key' => 'field_sponsor_company_state',
        'label' => 'State',
        'choices' => $this->usStates(),
        'default_value' => 'TX',
        'ui' => 1,
        'return_format' => 'value',
        'wrapper' => ['width' => '30'],
      ])
      ->addText('sponsor_company_zip', [
        'key' => 'field_sponsor_company_zip',
        'label' => 'Zip',
        'wrapper' => ['width' => '30'],
      ])

      // Phone/Email one line
      ->addText('sponsor_company_phone', [
        'key' => 'field_sponsor_company_phone',
        'label' => 'Company Phone',
        'wrapper' => ['width' => '50'],
      ])
      ->addEmail('sponsor_company_email', [
        'key' => 'field_sponsor_company_email',
        'label' => 'Company Email',
        'wrapper' => ['width' => '50'],
      ]);

    // =========================
    // Tab: Primary Contact
    // =========================
    $fields
      ->addTab('Primary Contact')

      ->addText('sponsor_contact_first_name', [
        'key' => 'field_sponsor_contact_first_name',
        'label' => 'Contact First Name',
        'wrapper' => ['width' => '50'],
      ])
      ->addText('sponsor_contact_last_name', [
        'key' => 'field_sponsor_contact_last_name',
        'label' => 'Contact Last Name',
        'wrapper' => ['width' => '50'],
      ])

      // Address row 1
      ->addText('sponsor_contact_address_1', [
        'key' => 'field_sponsor_contact_address_1',
        'label' => 'Address',
        'wrapper' => ['width' => '50'],
      ])
      ->addText('sponsor_contact_address_2', [
        'key' => 'field_sponsor_contact_address_2',
        'label' => 'Suite, Unit, etc.',
        'instructions' => '',
        'wrapper' => ['width' => '50'],
      ])

      // Address row 2
      ->addText('sponsor_contact_city', [
        'key' => 'field_sponsor_contact_city',
        'label' => 'City',
        'wrapper' => ['width' => '40'],
      ])
      ->addSelect('sponsor_contact_state', [
        'key' => 'field_sponsor_contact_state',
        'label' => 'State',
        'choices' => $this->usStates(),
        'default_value' => 'TX',
        'ui' => 1,
        'return_format' => 'value',
        'wrapper' => ['width' => '30'],
      ])
      ->addText('sponsor_contact_zip', [
        'key' => 'field_sponsor_contact_zip',
        'label' => 'Zip',
        'wrapper' => ['width' => '30'],
      ])

      // Phone/Email one line
      ->addText('sponsor_contact_phone', [
        'key' => 'field_sponsor_contact_phone',
        'label' => 'Contact Phone',
        'wrapper' => ['width' => '50'],
      ])
      ->addEmail('sponsor_contact_email', [
        'key' => 'field_sponsor_contact_email',
        'label' => 'Contact Email',
        'wrapper' => ['width' => '50'],
      ]);

    // =========================
    // Tab: Sponsorship
    // =========================
    $fields
      ->addTab('Sponsorship')

      ->addText('sponsor_source', [
        'key' => 'field_sponsor_source',
        'label' => 'Source',
        'instructions' => 'Read-only. Populated from the sponsor Gravity Form (referral / tracking hidden field) when applicable. Manual saves default to Direct.',
        'default_value' => 'Direct',
        'wrapper' => ['width' => '100'],
      ])

      ->addTaxonomy('sponsor_tier', [
        'key' => 'field_sponsor_tier',
        'label'         => 'Tier',
        'taxonomy'      => 'sponsor_tier',
        'field_type'    => 'radio',
        'add_term'      => 0,
        'save_terms'    => 1,
        'load_terms'    => 1,
        'return_format' => 'id',
        'wrapper'       => ['width' => '100'],
      ])

      ->addButtonGroup('sponsor_status', [
        'key' => 'field_sponsor_status',
        'label' => 'Status',
        'choices' => [
          'active'   => 'Active',
          'inactive' => 'Inactive',
          'pending' => 'Pending',
        ],
        'default_value' => 'pending',
        'return_format' => 'value',
        'layout' => 'horizontal',
        'wrapper' => ['width' => '100'],
      ])

      // Dates in one row
      ->addDatePicker('sponsor_start_date', [
        'key' => 'field_sponsor_start_date',
        'label' => 'Sponsor Start Date',
        'display_format' => 'F j, Y',
        'return_format'  => 'Y-m-d',
        'wrapper' => ['width' => '33'],
      ])
      ->addDatePicker('sponsor_end_date', [
        'key' => 'field_sponsor_end_date',
        'label' => 'Sponsor End Date',
        'instructions' => 'Auto-calculated as Start Date + 1 year if left empty.',
        'display_format' => 'F j, Y',
        'return_format'  => 'Y-m-d',
        'wrapper' => ['width' => '33'],
      ])
      ->addDatePicker('renewed_on', [
        'key' => 'field_renewed_on',
        'label' => 'Renewed On (optional)',
        'display_format' => 'F j, Y',
        'return_format'  => 'Y-m-d',
        'wrapper' => ['width' => '34'],
      ])

      ->addNumber('sponsor_since_year', [
        'key' => 'field_sponsor_since_year',
        'label' => 'Sponsor Since (year)',
        'instructions' => 'Optional. For display/history only.',
        'min' => 1900,
        'max' => 2100,
        'step' => 1,
        'wrapper' => ['width' => '33'],
      ]);

    // =========================
    // Tab: Assets
    // =========================
    $fields
      ->addTab('Assets')
      ->addImage('sponsor_logo_light', [
        'key' => 'field_sponsor_logo_light',
        'label' => 'Logo (Light variant)',
        'instructions' => 'Optional. Use when CSS auto-tint is not suitable.',
        'return_format' => 'id',
        'preview_size'  => 'medium',
        'wrapper' => ['width' => '50'],
      ])
      ->addImage('sponsor_logo_dark', [
        'key' => 'field_sponsor_logo_dark',
        'label' => 'Logo (Dark variant)',
        'instructions' => 'Optional. Use when CSS auto-tint is not suitable.',
        'return_format' => 'id',
        'preview_size'  => 'medium',
        'wrapper' => ['width' => '50'],
      ]);

    // =========================
    // Tab: Internal Notes
    // =========================
    $fields
      ->addTab('sponsor_Internal Notes')
      ->addTextarea('notes', [
        'key' => 'field_sponsor_notes',
        'label' => 'Notes',
        'rows'  => 5,
        'wrapper' => ['width' => '100'],
      ]);

    return $fields->build();
  }

  /**
   * US States for selects (value = abbreviation).
   */
  private function usStates(): array
  {
    return [
      'AL' => 'Alabama',
      'AK' => 'Alaska',
      'AZ' => 'Arizona',
      'AR' => 'Arkansas',
      'CA' => 'California',
      'CO' => 'Colorado',
      'CT' => 'Connecticut',
      'DE' => 'Delaware',
      'FL' => 'Florida',
      'GA' => 'Georgia',
      'HI' => 'Hawaii',
      'ID' => 'Idaho',
      'IL' => 'Illinois',
      'IN' => 'Indiana',
      'IA' => 'Iowa',
      'KS' => 'Kansas',
      'KY' => 'Kentucky',
      'LA' => 'Louisiana',
      'ME' => 'Maine',
      'MD' => 'Maryland',
      'MA' => 'Massachusetts',
      'MI' => 'Michigan',
      'MN' => 'Minnesota',
      'MS' => 'Mississippi',
      'MO' => 'Missouri',
      'MT' => 'Montana',
      'NE' => 'Nebraska',
      'NV' => 'Nevada',
      'NH' => 'New Hampshire',
      'NJ' => 'New Jersey',
      'NM' => 'New Mexico',
      'NY' => 'New York',
      'NC' => 'North Carolina',
      'ND' => 'North Dakota',
      'OH' => 'Ohio',
      'OK' => 'Oklahoma',
      'OR' => 'Oregon',
      'PA' => 'Pennsylvania',
      'RI' => 'Rhode Island',
      'SC' => 'South Carolina',
      'SD' => 'South Dakota',
      'TN' => 'Tennessee',
      'TX' => 'Texas',
      'UT' => 'Utah',
      'VT' => 'Vermont',
      'VA' => 'Virginia',
      'WA' => 'Washington',
      'WV' => 'West Virginia',
      'WI' => 'Wisconsin',
      'WY' => 'Wyoming',
      'DC' => 'District of Columbia',
    ];
  }
}