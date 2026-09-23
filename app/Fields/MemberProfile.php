<?php

namespace App\Fields;

use Log1x\AcfComposer\Field;
use StoutLogic\AcfBuilder\FieldsBuilder;

/**
 * MemberProfile (ACF Composer Field Group)
 * File: app/Fields/MemberProfile.php
 * =======================================
 *
 * WHAT THIS FILE DOES
 * -------------------
 * 1) Adds "Member Profile" fields to WP Users (user_form = all)
 * 2) Adds a Vehicles repeater on the WP User (vehicles[*])
 * 3) Computes / syncs:
 *    - vehicles[*].make_raw     (canonical make for reporting/search)
 *    - vehicles[*].vehicle_id  (immutable per vehicle row; stable reference key)
 *    - user_meta vehicle_index (pipe-separated "make model" strings)
 * 4) Generates Membership Number + Issued At:
 *    - membership_number (immutable unless reset flag is set)
 *    - membership_issued_at (stored as mysql datetime, displayed as US format in admin)
 *
 * ADDITION (Member Contact + Directory Visibility)
 * -------------------------------------------------
 * Adds member-level fields that support the member dashboard and future member
 * directory:
 * - sccc_member_phone
 * - sccc_hide_in_member_directory
 * - sccc_member_website
 * - sccc_member_social_links
 *
 * The opt-out field is intentionally reversed:
 * "Don't show me in the Member Directory."
 * This keeps active members visible by default unless they choose not to appear.
 *
 * ADDITION (Business Directory fields on the USER)
 * -----------------------------------------------
 * Adds a "Business" tab to the same User field group so the admin UI lines up.
 *
 * IMPORTANT: This is NOT the public directory page.
 * This only adds user fields + a small sync to maintain a searchable index.
 *
 * Storage rules:
 * - These fields are stored on the user (user meta) by ACF automatically.
 * - Category/Service values store the OPTION KEYS (stable) not labels.
 * - Labels can change any time without breaking saved data.
 *
 * Business meta keys (ACF field names)
 * -----------------------------------
 * - sccc_business_opt_in            (true/false)
 * - sccc_business_logo_id           (image attachment ID)
 * - sccc_business_name
 * - sccc_business_city
 * - sccc_business_description
 * - sccc_business_website
 * - sccc_business_phone
 * - sccc_business_email
 * - sccc_business_categories        (array of keys)
 * - sccc_business_services          (array of keys)
 * - sccc_business_index             (computed text index for later search/filter)
 *
 * NOTE:
 * - Frontend entry still happens in AccountEditExtras (My Account).
 * - This file ensures the WP-Admin user screen has a clean, aligned "Business" tab.
 */
class MemberProfile extends Field
{
    /**
     * Guard to prevent duplicate hook registration if AcfComposer instantiates twice.
     */
    protected static bool $hooksRegistered = false;

    /**
     * You chose 5 digits (readable, realistic scale for a local club).
     */
    protected int $membershipTokenLength = 5;

    /**
     * When this flag is set to 1, the next time a PMPro membership is (re)assigned
     * we will "start over" by generating a NEW membership number + issued date.
     *
     * This is intended to be set by an admin lifecycle tool (bulk action),
     * not automatically, so we never unexpectedly reset a member.
     */
    protected string $metaResetIdentityRequired = 'sccc_reset_identity_required';

    /**
     * boot()
     * ------
     * AcfComposer calls this after the class is instantiated.
     * Safe place to register hooks.
     */
    public function boot(): void
    {
        $this->registerHooksOnce();
    }

    /**
     * Register all actions once.
     */
    protected function registerHooksOnce(): void
    {
        if (self::$hooksRegistered) {
            return;
        }
        self::$hooksRegistered = true;

        /**
         * ACF user save hook (post_id like "user_123")
         * - sync make_raw
         * - ensure vehicle_id per row
         * - rebuild vehicle_index
         */
        add_action('acf/save_post', [$this, 'syncVehicleRowsAndIndex'], 20);

        /**
         * Member public profile sync (user save)
         * - normalize phone
         * - normalize directory visibility opt-out
         * - normalize website + social profile rows
         */
        add_action('acf/save_post', [$this, 'syncMemberContactFields'], 22);

        /**
         * Business index sync (user save)
         * - compute / refresh sccc_business_index (simple search helper)
         * - if opt-out, clear business meta fields to avoid stale listings
         */
        add_action('acf/save_post', [$this, 'syncBusinessIndex'], 25);

        /**
         * PMPro membership assignment hooks:
         * - admin "Add Membership"
         * - checkout completion
         */
        add_action('pmpro_after_change_membership_level', [$this, 'maybeAssignMembershipNumber'], 20, 3);
        add_action('pmpro_after_checkout', [$this, 'maybeAssignMembershipNumberAfterCheckout'], 20, 2);

        /**
         * Admin fallbacks:
         * When viewing/editing a user, backfill missing values without requiring a manual save.
         */
        add_action('load-user-edit.php', [$this, 'maybeAssignMembershipNumberOnAdminUserScreen']);
        add_action('load-profile.php',   [$this, 'maybeAssignMembershipNumberOnAdminUserScreen']);

        add_action('load-user-edit.php', [$this, 'maybeBackfillVehicleIdsOnAdminUserScreen']);
        add_action('load-profile.php',   [$this, 'maybeBackfillVehicleIdsOnAdminUserScreen']);

        /**
         * Admin UI formatting:
         * Display the issued_at in US format (still stored as mysql datetime).
         */
        add_filter('acf/load_value/name=membership_issued_at', [$this, 'formatMembershipIssuedAtForAdmin'], 10, 3);

        /**
         * Feature block helper:
         * - AJAX endpoint returning vehicles for a given user
         * - Editor JS that populates a select named "vehicle_id" based on a user field named "member_user"
         */
        add_action('wp_ajax_sccc_user_vehicles', [$this, 'ajaxUserVehicles']);
        add_action('acf/input/admin_enqueue_scripts', [$this, 'enqueueVehiclePickerHelper']);
    }

    /**
     * Make dropdown options (alphabetical, includes dead/legacy US brands).
     */
    protected function makeOptions(): array
    {
        return [
            '' => 'Select make…',

            'Acura' => 'Acura',
            'AMC' => 'AMC',
            'Aston Martin' => 'Aston Martin',
            'Audi' => 'Audi',

            'Bentley' => 'Bentley',
            'BMW' => 'BMW',
            'Buick' => 'Buick',

            'Cadillac' => 'Cadillac',
            'Chevrolet' => 'Chevrolet',
            'Chrysler' => 'Chrysler',

            'DeLorean' => 'DeLorean',
            'Dodge' => 'Dodge',

            'Eagle' => 'Eagle',

            'Ferrari' => 'Ferrari',
            'Ford' => 'Ford',

            'GMC' => 'GMC',

            'Honda' => 'Honda',
            'Hummer' => 'Hummer',

            'Infiniti' => 'Infiniti',

            'Jeep' => 'Jeep',

            'Lamborghini' => 'Lamborghini',
            'Lexus' => 'Lexus',

            'Maserati' => 'Maserati',
            'Mazda' => 'Mazda',
            'McLaren' => 'McLaren',
            'Mercedes-Benz' => 'Mercedes-Benz',
            'Mercury' => 'Mercury',
            'MINI' => 'MINI',
            'Mitsubishi' => 'Mitsubishi',

            'Nissan' => 'Nissan',

            'Oldsmobile' => 'Oldsmobile',

            'Plymouth' => 'Plymouth',
            'Pontiac' => 'Pontiac',
            'Porsche' => 'Porsche',

            'Ram' => 'Ram',
            'Rolls-Royce' => 'Rolls-Royce',

            'Saturn' => 'Saturn',
            'Subaru' => 'Subaru',

            'Tesla' => 'Tesla',
            'Toyota' => 'Toyota',

            'Volkswagen' => 'Volkswagen',
            'Volvo' => 'Volvo',

            'Other' => 'Other / Not listed',
        ];
    }

    /**
     * Year dropdown options: 1900 -> current year + 1 (descending).
     */
    protected function yearOptions(): array
    {
        $max  = (int) date('Y') + 1;
        $opts = ['' => 'Select year…'];

        for ($y = $max; $y >= 1900; $y--) {
            $opts[(string) $y] = (string) $y;
        }

        return $opts;
    }

    /**
     * Approved member social platform options.
     *
     * These keys are stored in user meta. Labels are display-only and can change
     * later without breaking saved data.
     */
    protected function memberSocialPlatformOptions(): array
    {
        return [
            ''          => 'Select platform…',
            'facebook'  => 'Facebook',
            'instagram' => 'Instagram',
            'x'         => 'X',
            'tiktok'    => 'TikTok',
            'youtube'   => 'YouTube',
        ];
    }

    /**
     * fields()
     * --------
     * Defines ACF fields shown on WP user profile screens.
     */
    public function fields(): array
    {
        // extra safety for hook timing
        $this->registerHooksOnce();

        $fields = new FieldsBuilder('member_profile');
        $fields->setLocation('user_form', '==', 'all');

        // ----------------
        // Member Profile
        // ----------------
        $fields
            ->addTab('Member Profile')

            ->addText('membership_number', [
                'label' => 'Membership Number',
                'instructions' => 'Auto-generated when a PMPro membership level is assigned. Immutable.',
                'readonly' => 1,
                'disabled' => 0,
            ])

            ->addText('membership_issued_at', [
                'label' => 'Membership Issued At',
                'instructions' => 'Auto-generated timestamp for when the Membership Number was created.',
                'readonly' => 1,
                'disabled' => 0,
            ])

            ->addDatePicker('birth_date', [
                'label' => 'Birth Date',
                'instructions' => 'Used for birthday reports. Stored as YYYY-MM-DD.',
                'return_format' => 'Y-m-d',
                'display_format' => 'm/d/Y',
            ])

            ->addTrueFalse('is_veteran_first_responder', [
                'label' => 'Veteran / First Responder',
                'ui' => 1,
                'default_value' => 0,
            ])

            ->addSelect('veteran_type', [
                'label' => 'Veteran / First Responder Type',
                'choices' => [
                    'veteran' => 'Veteran',
                    'first_responder' => 'First Responder',
                    'both' => 'Both',
                ],
                'allow_null' => 1,
                'ui' => 1,
                'return_format' => 'value',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'is_veteran_first_responder',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ])

            ->addText('agency_branch', [
                'label' => 'Branch / Agency',
                'instructions' => 'Optional. Example: USMC, Army, Houston Fire, EMS…',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'is_veteran_first_responder',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ])

            ->addText('sccc_member_phone', [
                'label' => 'Phone Number',
                'instructions' => 'Club/admin use. This is not shown in the public/member directory by default.',
                'placeholder' => '(713) 555-1234',
            ]);

        // ----------------
        // Public Profile
        // ----------------
        // These fields support the future Member Directory and the existing
        // author/profile displays. Phone intentionally stays in Member Profile
        // because it is private/admin-facing unless we add a separate display
        // opt-in later.
        $fields
            ->addTab('Public Profile')

            ->addTrueFalse('sccc_hide_in_member_directory', [
                'label' => 'Don’t show me in the Member Directory',
                'instructions' => 'When enabled, this member will be excluded from the Member Directory even if they are active.',
                'ui' => 1,
                'default_value' => 0,
            ])

            ->addUrl('sccc_member_website', [
                'label' => 'Member Website',
                'instructions' => 'Optional personal/public website. This is separate from the Business Directory website.',
                'placeholder' => 'https://example.com',
            ])

            ->addRepeater('sccc_member_social_links', [
                'label' => 'Member Social Media',
                'instructions' => 'Optional. Add public profile links. Multiple accounts per platform are allowed because each row includes a username/handle.',
                'layout' => 'table',
                'button_label' => 'Add Social Profile',
                'collapsed' => 'username',
                'min' => 0,
                'max' => 15,
            ])
                ->addSelect('platform', [
                    'label' => 'Platform',
                    'choices' => $this->memberSocialPlatformOptions(),
                    'ui' => 1,
                    'allow_null' => 0,
                    'return_format' => 'value',
                    'wrapper' => [
                        'width' => 25,
                    ],
                ])

                ->addText('username', [
                    'label' => 'Username / Handle',
                    'instructions' => 'Enter without the leading @. Example: spacecitycarclub',
                    'placeholder' => 'username',
                    'prepend' => '@',
                    'wrapper' => [
                        'width' => 30,
                    ],
                ])

                ->addUrl('url', [
                    'label' => 'Profile URL',
                    'placeholder' => 'https://...',
                    'wrapper' => [
                        'width' => 45,
                    ],
                ])

            ->endRepeater();

        // ----------------
        // Business (NEW)
        // ----------------
        // NOTE: Field names are the same as the user_meta keys you already use in My Account.
        // This ensures WP-Admin and frontend stay perfectly aligned.
        $fields
            ->addTab('Business')

            ->addTrueFalse('sccc_business_opt_in', [
                'label' => 'Feature my business in the directory',
                'instructions' => 'Optional. Turn this on if you want your business listed.',
                'ui' => 1,
                'default_value' => 0,
            ])

            // Business Logo (attachment ID). Cropping happens on the frontend (My Account).
            ->addImage('sccc_business_logo_id', [
                'label' => 'Business Logo',
                'instructions' => 'Optional. Square logo works best.',
                'return_format' => 'id',
                'preview_size' => 'medium',
                'library' => 'all',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'sccc_business_opt_in',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ])

            ->addText('sccc_business_name', [
                'label' => 'Business Name',
                'instructions' => 'Required if opted in.',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'sccc_business_opt_in',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ])

            ->addText('sccc_business_city', [
                'label' => 'City',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'sccc_business_opt_in',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ])

            ->addTextarea('sccc_business_description', [
                'label' => 'Short Description',
                'instructions' => 'What do you do? What should members know?',
                'rows' => 4,
                'new_lines' => 'br',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'sccc_business_opt_in',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ])

            ->addUrl('sccc_business_website', [
                'label' => 'Website',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'sccc_business_opt_in',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ])

            ->addText('sccc_business_phone', [
                'label' => 'Phone',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'sccc_business_opt_in',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ])

            ->addEmail('sccc_business_email', [
                'label' => 'Email',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'sccc_business_opt_in',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ])

            /**
             * Category (Industry)
             * - This is the "line of business" bucket (IT, Legal, Real Estate, etc.)
             * - Stored values are keys (stable). Labels can change freely.
             */
            ->addSelect('sccc_business_categories', [
                'label' => 'Business Category (Industry)',
                'instructions' => 'Pick one or more industries. Used for filtering/search later.',
                'choices' => $this->businessCategoryOptions(),
                'ui' => 1,
                'multiple' => 1,
                'return_format' => 'value',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'sccc_business_opt_in',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ])

            /**
             * Services Offered
             * - More granular: what you actually do (Web Development, SEO, Detailing, etc.)
             * - Stored values are keys (stable). Labels can change freely.
             */
            ->addSelect('sccc_business_services', [
                'label' => 'Services Offered',
                'instructions' => 'Pick one or more services. Used for filtering/search later.',
                'choices' => $this->businessServiceOptions(),
                'ui' => 1,
                'multiple' => 1,
                'return_format' => 'value',
                'conditional_logic' => [
                    [
                        [
                            'field' => 'sccc_business_opt_in',
                            'operator' => '==',
                            'value' => '1',
                        ],
                    ],
                ],
            ])

            /**
             * Hidden computed search index.
             * - We compute this on save (syncBusinessIndex()) so the directory queries
             *   can be fast later without complex joins.
             */
            ->addText('sccc_business_index', [
                'label' => 'Business Index (computed)',
                'instructions' => 'Auto-computed search helper. Do not edit.',
                'readonly' => 1,
                'wrapper' => ['class' => 'hidden'],
            ]);

        // ---------
        // Vehicles
        // ---------
        $fields
            ->addTab('Vehicles')

            ->addRepeater('vehicles', [
                'label' => 'Vehicles',
                'layout' => 'row',
                'button_label' => 'Add Vehicle',
                'collapsed' => 'nickname',
                'min' => 0,
            ])

                /**
                 * Hidden stable ID per row.
                 * This is what your "feature" block should store when selecting a vehicle.
                 */
                ->addText('vehicle_id', [
                    'label' => 'Vehicle ID (auto)',
                    'instructions' => 'Auto-generated. Do not edit.',
                    'readonly' => 1,
                    'wrapper' => ['class' => 'hidden'],
                ])

                ->addSelect('year', [
                    'label' => 'Year',
                    'choices' => $this->yearOptions(),
                    'ui' => 1,
                    'allow_null' => 1,
                    'return_format' => 'value',
                ])

                ->addSelect('make_select', [
                    'label' => 'Make',
                    'choices' => $this->makeOptions(),
                    'ui' => 1,
                    'allow_null' => 1,
                    'return_format' => 'value',
                ])

                ->addText('make_other', [
                    'label' => 'Other Make',
                    'instructions' => 'If the make is not listed, enter it here.',
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'make_select',
                                'operator' => '==',
                                'value' => 'Other',
                            ],
                        ],
                    ],
                ])

                // Hidden computed canonical make (filled on save)
                ->addText('make_raw', [
                    'label' => 'Make (computed)',
                    'instructions' => 'Auto-computed canonical make used for reporting/search.',
                    'wrapper' => ['class' => 'hidden'],
                ])

                ->addText('model', [
                    'label' => 'Model',
                ])

                ->addText('nickname', [
                    'label' => 'Nickname',
                ])

                ->addTextarea('notes', [
                    'label' => 'Notes',
                    'rows' => 3,
                    'new_lines' => 'br',
                ])

                ->addImage('image', [
                    'label' => 'Photo',
                    'return_format' => 'id',
                    'preview_size' => 'medium',
                    'library' => 'all',
                ])

            ->endRepeater();

        return $fields->build();
    }

    /* ======================================================================
     * MEMBER PUBLIC PROFILE: phone + directory visibility + social media sync
     * ====================================================================== */

    /**
     * Normalize member contact/profile fields after an ACF user save.
     *
     * ACF already stores the submitted values. This method keeps the saved data
     * predictable for frontend templates and the future Member Directory query:
     * - phone is plain sanitized text
     * - directory opt-out is stored as 1 or 0
     * - website is stored as a sanitized URL
     * - social rows only keep approved platforms and clean profile URLs
     */
    public function syncMemberContactFields($post_id): void
    {
        if (!is_string($post_id) || strpos($post_id, 'user_') !== 0) {
            return;
        }

        $user_id = (int) str_replace('user_', '', $post_id);
        if (!$user_id) {
            return;
        }

        if (!function_exists('get_field') || !function_exists('update_field')) {
            return;
        }

        $acf_key = 'user_' . $user_id;

        $phone = (string) (get_field('sccc_member_phone', $acf_key) ?: '');
        $phone = sanitize_text_field($phone);
        update_field('sccc_member_phone', $phone, $acf_key);

        $hide_in_directory = (int) ((bool) get_field('sccc_hide_in_member_directory', $acf_key));
        update_field('sccc_hide_in_member_directory', $hide_in_directory, $acf_key);

        $website = (string) (get_field('sccc_member_website', $acf_key) ?: '');
        $website = trim($website) !== '' ? esc_url_raw($website) : '';
        update_field('sccc_member_website', $website, $acf_key);

        $rows = get_field('sccc_member_social_links', $acf_key);
        update_field(
            'sccc_member_social_links',
            $this->normalizeMemberSocialLinks(is_array($rows) ? $rows : []),
            $acf_key
        );
    }

    /**
     * Normalize member social media repeater rows.
     *
     * Rules:
     * - Only approved platform keys are accepted.
     * - Multiple accounts per platform are allowed.
     * - Username is stored without a leading @.
     * - If URL is missing but platform + username are present, build the common
     *   profile URL where possible.
     * - Exact duplicate platform + username + URL rows are ignored.
     */
    protected function normalizeMemberSocialLinks(array $rows): array
    {
        $allowed = array_filter(array_keys($this->memberSocialPlatformOptions()));
        $clean = [];
        $seen = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $platform = isset($row['platform']) ? sanitize_key((string) $row['platform']) : '';
            $username = isset($row['username']) ? sanitize_text_field((string) $row['username']) : '';
            $url = isset($row['url']) ? esc_url_raw((string) $row['url']) : '';

            $username = ltrim(trim($username), '@');

            if ($platform === '' && $url !== '') {
                $platform = $this->platformFromSocialUrl($url);
            }

            if ($platform === '' || !in_array($platform, $allowed, true)) {
                continue;
            }

            if ($username === '' && $url !== '') {
                $username = $this->usernameFromSocialUrl($url);
            }

            if ($url === '' && $username !== '') {
                $url = $this->profileUrlFromPlatformUsername($platform, $username);
            }

            if ($url === '') {
                continue;
            }

            $fingerprint = $platform . '|' . strtolower($username) . '|' . strtolower($url);
            if (isset($seen[$fingerprint])) {
                continue;
            }

            $clean[] = [
                'platform' => $platform,
                'username' => $username,
                'url' => $url,
            ];

            $seen[$fingerprint] = true;

            if (count($clean) >= 15) {
                break;
            }
        }

        return $clean;
    }

    /**
     * Best-effort username fallback for older rows that only have a URL.
     */
    protected function usernameFromSocialUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $path = trim($path, "/ \t\n\r\0\x0B");

        if ($path === '') {
            return '';
        }

        $parts = array_values(array_filter(explode('/', $path)));
        $candidate = end($parts);

        if (!is_string($candidate) || $candidate === '') {
            return '';
        }

        return ltrim(sanitize_text_field($candidate), '@');
    }

    /**
     * Best-effort platform fallback for older rows that only have a URL.
     */
    protected function platformFromSocialUrl(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?: $host;

        if (str_contains($host, 'facebook.com') || str_contains($host, 'fb.com')) {
            return 'facebook';
        }

        if (str_contains($host, 'instagram.com')) {
            return 'instagram';
        }

        if ($host === 'x.com' || str_contains($host, 'twitter.com')) {
            return 'x';
        }

        if (str_contains($host, 'tiktok.com')) {
            return 'tiktok';
        }

        if (str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be')) {
            return 'youtube';
        }

        return '';
    }

    /**
     * Build common profile URLs when a member enters platform + username only.
     */
    protected function profileUrlFromPlatformUsername(string $platform, string $username): string
    {
        $username = ltrim(trim($username), '@');

        if ($username === '') {
            return '';
        }

        $encoded = rawurlencode($username);

        return match ($platform) {
            'facebook'  => 'https://www.facebook.com/' . $encoded,
            'instagram' => 'https://www.instagram.com/' . $encoded,
            'x'         => 'https://x.com/' . $encoded,
            'tiktok'    => 'https://www.tiktok.com/@' . $encoded,
            'youtube'   => 'https://www.youtube.com/@' . $encoded,
            default     => '',
        };
    }

    /* ======================================================================
     * BUSINESS DIRECTORY: computed search index (admin/user saves)
     * ====================================================================== */

    /**
     * Keep a lightweight "business_index" updated for easy searching later.
     *
     * Runs on ACF user save (wp-admin user edit screens).
     * Frontend saves (My Account) already update meta in AccountEditExtras,
     * but this keeps WP-Admin edits consistent too.
     */
    public function syncBusinessIndex($post_id): void
    {
        if (!is_string($post_id) || strpos($post_id, 'user_') !== 0) {
            return;
        }

        $user_id = (int) str_replace('user_', '', $post_id);
        if (!$user_id) {
            return;
        }

        if (!function_exists('get_field') || !function_exists('update_field')) {
            return;
        }

        $opt_in = (int) ((bool) get_field('sccc_business_opt_in', 'user_' . $user_id));

        // If opted out, clear business fields to avoid stale listings.
        if ($opt_in !== 1) {
            // Clear ACF fields (also clears user meta).
            update_field('sccc_business_logo_id', 0, 'user_' . $user_id);
            update_field('sccc_business_name', '', 'user_' . $user_id);
            update_field('sccc_business_city', '', 'user_' . $user_id);
            update_field('sccc_business_description', '', 'user_' . $user_id);
            update_field('sccc_business_website', '', 'user_' . $user_id);
            update_field('sccc_business_phone', '', 'user_' . $user_id);
            update_field('sccc_business_email', '', 'user_' . $user_id);
            update_field('sccc_business_categories', [], 'user_' . $user_id);
            update_field('sccc_business_services', [], 'user_' . $user_id);
            update_field('sccc_business_index', '', 'user_' . $user_id);
            return;
        }

        // Read current values
        $name  = (string) (get_field('sccc_business_name', 'user_' . $user_id) ?: '');
        $city  = (string) (get_field('sccc_business_city', 'user_' . $user_id) ?: '');
        $desc  = (string) (get_field('sccc_business_description', 'user_' . $user_id) ?: '');

        $cats = get_field('sccc_business_categories', 'user_' . $user_id);
        $srvs = get_field('sccc_business_services', 'user_' . $user_id);

        $cats = is_array($cats) ? array_values($cats) : [];
        $srvs = is_array($srvs) ? array_values($srvs) : [];

        // Enforce "hardcoded options only" to prevent accidental drift.
        $allowed_cats = array_keys($this->businessCategoryOptions());
        $allowed_srvs = array_keys($this->businessServiceOptions());

        $cats = array_values(array_intersect(array_map('strval', $cats), $allowed_cats));
        $srvs = array_values(array_intersect(array_map('strval', $srvs), $allowed_srvs));

        // Persist cleaned arrays back into ACF
        update_field('sccc_business_categories', $cats, 'user_' . $user_id);
        update_field('sccc_business_services', $srvs, 'user_' . $user_id);

        // Build a plain-text index string (labels included for human keyword matching)
        $cat_labels = [];
        foreach ($cats as $k) {
            $cat_labels[] = $this->businessCategoryOptions()[$k] ?? $k;
        }

        $srv_labels = [];
        foreach ($srvs as $k) {
            $srv_labels[] = $this->businessServiceOptions()[$k] ?? $k;
        }

        $parts = array_filter([
            $name,
            $city,
            $desc,
            implode(' ', $cat_labels),
            implode(' ', $srv_labels),
        ]);

        $index = strtolower(trim(preg_replace('/\s+/', ' ', implode(' | ', $parts))));

        update_field('sccc_business_index', $index, 'user_' . $user_id);
    }

    /**
     * Business Category options (Industry / Line of business)
     * -------------------------------------------------------------------------
     * Store the KEYS. Keep keys stable once live.
     * Labels can change any time.
     */
    protected function businessCategoryOptions(): array
    {
        return [
            // Automotive
            'auto'              => 'Automotive',
            'auto_dealer'       => 'Auto Dealer',
            'auto_services'     => 'Auto Services / Repair',
            'auto_detailing'    => 'Auto Detailing',
            'auto_wrap_paint'   => 'Wrap / Paint / PPF',
            'auto_wheels_tires' => 'Wheels & Tires',
            'auto_audio'        => 'Car Audio / Electronics',
            'auto_performance'  => 'Performance / Tuning',
            'auto_body'         => 'Body Shop / Collision',

            // General / broad directory-style categories
            'it_tech'           => 'IT / Tech Services',
            'marketing'         => 'Marketing / Advertising',
            'photography_media' => 'Photography / Video / Media',
            'real_estate'       => 'Real Estate',
            'finance_insurance' => 'Finance / Insurance',
            'legal'             => 'Legal Services',
            'medical'           => 'Medical / Dental / Wellness',
            'beauty'            => 'Beauty / Spa / Salon',
            'fitness'           => 'Fitness / Training',
            'home_services'     => 'Home Services',
            'construction'      => 'Construction / Trades',
            'hospitality'       => 'Restaurants / Bars / Hospitality',
            'retail'            => 'Retail / Shopping',
            'events_venues'     => 'Events / Venues',
            'travel'            => 'Travel / Transportation',
            'education'         => 'Education / Lessons',
            'nonprofit'         => 'Non-Profit / Community',
            'professional'      => 'Professional Services (Other)',
            'other'             => 'Other',
        ];
    }

    /**
     * Business Service options (What you actually do)
     * -------------------------------------------------------------------------
     * Same rule: keep keys stable; labels can evolve.
     */
    protected function businessServiceOptions(): array
    {
        return [
            // IT / Tech
            'web_development'   => 'Web Development',
            'wordpress'         => 'WordPress Development',
            'web_maintenance'   => 'Website Maintenance',
            'hosting_domains'   => 'Hosting / Domains',
            'seo'               => 'SEO',
            'ppc_ads'           => 'PPC / Paid Ads',
            'social_media'      => 'Social Media Management',
            'graphic_design'    => 'Graphic Design',
            'it_support'        => 'IT Support',
            'managed_it'        => 'Managed IT Services',
            'networking_wifi'   => 'Networking / Wi-Fi',
            'cybersecurity'     => 'Cybersecurity',
            'app_development'   => 'App Development',
            'automation_ai'     => 'Automation / AI Workflows',

            // Automotive services
            'oil_change'        => 'Oil Change',
            'brakes'            => 'Brakes',
            'alignment'         => 'Alignment',
            'diagnostics'       => 'Diagnostics',
            'repair_maintenance'=> 'Repair / Maintenance',
            'detailing_interior'=> 'Interior Detailing',
            'detailing_exterior'=> 'Exterior Detailing',
            'ceramic_coating'   => 'Ceramic Coating',
            'ppf'               => 'Paint Protection Film (PPF)',
            'vinyl_wrap'        => 'Vinyl Wrap',
            'paint_bodywork'    => 'Paint / Bodywork',
            'wheels'            => 'Wheels',
            'tires'             => 'Tires',
            'audio_install'     => 'Audio Install',
            'dyno_tune'         => 'Dyno / Tuning',
            'performance_parts' => 'Performance Parts',
            'tint'              => 'Window Tint',

            // Home / trades
            'plumbing'          => 'Plumbing',
            'electrical'        => 'Electrical',
            'hvac'              => 'HVAC',
            'landscaping'       => 'Landscaping',
            'cleaning'          => 'Cleaning Services',
            'remodeling'        => 'Remodeling',
            'roofing'           => 'Roofing',

            // Professional services
            'accounting'        => 'Accounting / Bookkeeping',
            'insurance'         => 'Insurance',
            'legal_services'    => 'Legal Services',
            'real_estate_agent' => 'Real Estate Agent',
            'mortgage'          => 'Mortgage / Lending',

            // Events / media
            'photo_video'       => 'Photo / Video',
            'dj_music'          => 'DJ / Music',
            'event_space'       => 'Event Space',
            'catering'          => 'Catering',

            // General
            'delivery'          => 'Delivery',
            'consulting'        => 'Consulting',
            'training'          => 'Training / Coaching',
            'other'             => 'Other',
        ];
    }

    /* ======================================================================
     * MEMBERSHIP NUMBER
     * ====================================================================== */

    /**
     * PMPro hook: after membership level change (admin or checkout).
     */
    public function maybeAssignMembershipNumber($level_id, $user_id, $cancel_level): void
    {
        $user_id = (int) $user_id;
        if (!$user_id) {
            return;
        }

        // If PMPro says we're cancelling a level, do NOT remove/change the membership number.
        if (!empty($cancel_level)) {
            return;
        }

        if (!$this->userHasAnyPmproLevel($user_id)) {
            return;
        }

        $this->assignMembershipNumberIfMissing($user_id);
    }

    /**
     * PMPro hook: after checkout. Fallback/belt+suspenders.
     */
    public function maybeAssignMembershipNumberAfterCheckout($user_id, $morder): void
    {
        $user_id = (int) $user_id;
        if (!$user_id) {
            return;
        }

        if (!$this->userHasAnyPmproLevel($user_id)) {
            return;
        }

        $this->assignMembershipNumberIfMissing($user_id);
    }

    /**
     * Admin fallback:
     * When viewing/editing a user, if they already have a PMPro level but no membership number,
     * generate it.
     */
    public function maybeAssignMembershipNumberOnAdminUserScreen(): void
    {
        if (!is_admin()) {
            return;
        }

        $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

        // profile.php is "your profile"
        if (!$user_id && function_exists('get_current_user_id')) {
            $user_id = (int) get_current_user_id();
        }

        if (!$user_id) {
            return;
        }

        if (!$this->userHasAnyPmproLevel($user_id)) {
            return;
        }

        $this->assignMembershipNumberIfMissing($user_id);
    }

    /**
     * Create membership_number + membership_issued_at if they do not exist.
     * Uses update_user_meta directly so it works even if ACF isn't "saving" anything.
     */
    protected function assignMembershipNumberIfMissing(int $user_id): void
    {
        $reset_required = ((int) get_user_meta($user_id, $this->metaResetIdentityRequired, true) === 1);

        if ($reset_required) {
            delete_user_meta($user_id, 'membership_number');
            delete_user_meta($user_id, 'membership_issued_at');
        }

        $existing = (string) get_user_meta($user_id, 'membership_number', true);
        if (!$reset_required && trim($existing) !== '') {
            return; // immutable
        }

        $issued_at_existing = (string) get_user_meta($user_id, 'membership_issued_at', true);

        $id = $this->generateUniqueMembershipNumber();
        if (!$id) {
            return;
        }

        update_user_meta($user_id, 'membership_number', $id);

        if (trim($issued_at_existing) === '') {
            update_user_meta($user_id, 'membership_issued_at', current_time('mysql'));
        }

        if ($reset_required) {
            delete_user_meta($user_id, $this->metaResetIdentityRequired);
        }
    }

    /**
     * Generate a unique membership number.
     * Retry to avoid collisions.
     */
    protected function generateUniqueMembershipNumber(int $maxAttempts = 50): string
    {
        $yy = date('y'); // 2-digit year

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $token = $this->randomDigits($this->membershipTokenLength);

            // Luhn check is computed on YY + token (numeric only)
            $base  = $yy . $token;
            $check = $this->luhnCheckDigit($base);

            // Chunked + separated check digit
            $id = sprintf('SCCC-%s-%s-%s', $yy, $token, $check);

            if (!$this->membershipNumberExists($id)) {
                return $id;
            }
        }

        return '';
    }

    protected function membershipNumberExists(string $id): bool
    {
        $id = trim($id);
        if ($id === '') {
            return true;
        }

        $users = get_users([
            'fields'     => 'ID',
            'number'     => 1,
            'meta_key'   => 'membership_number',
            'meta_value' => $id,
        ]);

        return !empty($users);
    }

    protected function randomDigits(int $length): string
    {
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= (string) random_int(0, 9);
        }
        return $out;
    }

    /**
     * Compute Luhn check digit for a numeric string (no check digit included yet).
     * Returns 0-9.
     */
    protected function luhnCheckDigit(string $number): int
    {
        $number = preg_replace('/\D+/', '', $number);

        $sum = 0;
        $alt = true; // because check digit is appended to the right

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = (int) $number[$i];

            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }

            $sum += $n;
            $alt = !$alt;
        }

        return (10 - ($sum % 10)) % 10;
    }

    protected function userHasAnyPmproLevel(int $user_id): bool
    {
        if (!function_exists('pmpro_getMembershipLevelsForUser')) {
            return false;
        }

        $levels = pmpro_getMembershipLevelsForUser($user_id);

        return (is_array($levels) && !empty($levels));
    }

    /**
     * Display membership_issued_at in US format + 24hr time in admin,
     * while keeping stored value as mysql datetime.
     */
    public function formatMembershipIssuedAtForAdmin($value, $post_id, $field)
    {
        if (!is_admin()) {
            return $value;
        }

        $value = (string) $value;
        if (trim($value) === '') {
            return $value;
        }

        $ts = strtotime($value);
        if (!$ts) {
            return $value;
        }

        // US format + 24h time
        return date_i18n('m/d/Y H:i:s', $ts);
    }

    /* ======================================================================
     * VEHICLES: make_raw + vehicle_id + index
     * ====================================================================== */

    /**
     * Admin fallback:
     * When viewing/editing a user, ensure every vehicle row has a vehicle_id.
     */
    public function maybeBackfillVehicleIdsOnAdminUserScreen(): void
    {
        if (!is_admin() || !function_exists('get_field') || !function_exists('update_field')) {
            return;
        }

        $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
        if (!$user_id && function_exists('get_current_user_id')) {
            $user_id = (int) get_current_user_id();
        }
        if (!$user_id) {
            return;
        }

        $vehicles = get_field('vehicles', 'user_' . $user_id);
        if (!is_array($vehicles) || empty($vehicles)) {
            return;
        }

        $changed = $this->ensureVehicleIds($vehicles);
        if ($changed) {
            update_field('vehicles', $vehicles, 'user_' . $user_id);
        }
    }

    /**
     * On ACF user save:
     * - compute make_raw per vehicle row
     * - ensure vehicle_id per row
     * - rebuild vehicle_index user_meta
     */
    public function syncVehicleRowsAndIndex($post_id): void
    {
        if (!is_string($post_id) || strpos($post_id, 'user_') !== 0) {
            return;
        }

        static $running = false;
        if ($running) {
            return;
        }
        $running = true;

        $user_id = (int) str_replace('user_', '', $post_id);

        if (!$user_id || !function_exists('get_field') || !function_exists('update_field')) {
            $running = false;
            return;
        }

        $vehicles = get_field('vehicles', 'user_' . $user_id);

        if (!is_array($vehicles)) {
            update_user_meta($user_id, 'vehicle_index', '');
            $running = false;
            return;
        }

        $changed = false;
        $indexParts = [];

        // ensure IDs first (so they exist for any later tooling)
        if ($this->ensureVehicleIds($vehicles)) {
            $changed = true;
        }

        foreach ($vehicles as $i => $row) {
            $makeSelect = $row['make_select'] ?? '';
            $makeOther  = $row['make_other'] ?? '';
            $model      = $row['model'] ?? '';

            $makeRaw = ($makeSelect === 'Other' && !empty($makeOther)) ? $makeOther : $makeSelect;
            $makeRaw = preg_replace('/\s+/', ' ', trim((string) $makeRaw));

            if (($row['make_raw'] ?? '') !== $makeRaw) {
                $vehicles[$i]['make_raw'] = $makeRaw;
                $changed = true;
            }

            $idx = strtolower(trim($makeRaw . ' ' . (string) $model));
            if ($idx !== '') {
                $indexParts[] = $idx;
            }
        }

        if ($changed) {
            update_field('vehicles', $vehicles, 'user_' . $user_id);
        }

        $indexParts = array_values(array_unique($indexParts));
        update_user_meta($user_id, 'vehicle_index', implode(' | ', $indexParts));

        $running = false;
    }

    /**
     * Ensure each repeater row has a stable vehicle_id.
     * Returns true if modifications were made.
     */
    protected function ensureVehicleIds(array &$vehicles): bool
    {
        $changed = false;

        // prevent duplicates within the same user just in case
        $seen = [];

        foreach ($vehicles as $i => $row) {
            $existing = isset($row['vehicle_id']) ? (string) $row['vehicle_id'] : '';
            $existing = trim($existing);

            if ($existing !== '' && !isset($seen[$existing])) {
                $seen[$existing] = true;
                continue;
            }

            // missing or duplicate -> generate a new one
            $new = $this->generateVehicleId($seen);
            $vehicles[$i]['vehicle_id'] = $new;
            $seen[$new] = true;
            $changed = true;
        }

        return $changed;
    }

    /**
     * Generate vehicle id like: VH-8C1A4F92
     */
    protected function generateVehicleId(array $seen = [], int $maxAttempts = 25): string
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $hex = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars
            $id  = 'VH-' . $hex;

            if (!isset($seen[$id])) {
                return $id;
            }
        }

        // extremely unlikely fallback
        return 'VH-' . strtoupper(bin2hex(random_bytes(4)));
    }

    /* ======================================================================
     * FEATURE BLOCK HELPER (ADMIN): user -> vehicles dropdown
     * ====================================================================== */

    /**
     * Enqueue a tiny inline helper for ACF in wp-admin:
     * - Watches a user field named "member_user"
     * - Fills a select field named "vehicle_id" inside the same block scope
     */
    public function enqueueVehiclePickerHelper(): void
    {
        if (!is_admin()) {
            return;
        }

        // Ensure ACF input scripts are present
        if (!function_exists('wp_add_inline_script')) {
            return;
        }

        $nonce = wp_create_nonce('sccc_user_vehicles');

        $js = <<<JS
(function($){
  if (typeof acf === 'undefined') return;

  var CFG = window.SCCC_VEHICLES = window.SCCC_VEHICLES || {};
  CFG.nonce = {$this->jsString($nonce)};
  CFG.action = 'sccc_user_vehicles';

  function getBlockScope(\$fieldEl){
    // ACF blocks usually live under .acf-block-component
    var \$scope = \$fieldEl.closest('.acf-block-component');
    if (!\$scope.length) {
      // fallback
      \$scope = \$fieldEl.closest('.acf-fields');
    }
    return \$scope;
  }

  function setSelectOptions(vehicleField, options){
    var \$select = vehicleField.\$input();
    if (!\$select || !\$select.length) return;

    var current = \$select.val();

    \$select.empty();
    \$select.append($('<option/>').val('').text('Select vehicle…'));

    (options || []).forEach(function(opt){
      \$select.append($('<option/>').val(opt.value).text(opt.label));
    });

    // If current value is still present, keep it; otherwise clear.
    var exists = false;
    \$select.find('option').each(function(){
      if ($(this).val() === current) exists = true;
    });
    \$select.val(exists ? current : '');
    \$select.trigger('change');
  }

  function disableSelect(vehicleField, msg){
    var \$select = vehicleField.\$input();
    if (!\$select || !\$select.length) return;
    \$select.empty();
    \$select.append($('<option/>').val('').text(msg || 'Select vehicle…'));
    \$select.val('');
    \$select.trigger('change');
  }

  function fetchVehicles(userId, done){
    $.ajax({
      url: window.ajaxurl,
      method: 'POST',
      dataType: 'json',
      data: {
        action: CFG.action,
        nonce: CFG.nonce,
        user_id: userId
      }
    }).done(function(resp){
      if (!resp || !resp.success) return done([]);
      return done(resp.data || []);
    }).fail(function(){
      return done([]);
    });
  }

  function wireMemberField(memberField){
    var \$scope = getBlockScope(memberField.\$el);
    var vehicleFields = acf.getFields({ name: 'vehicle_id' }, \$scope);
    if (!vehicleFields || !vehicleFields.length) return;

    var vehicleField = vehicleFields[0];

    function refresh(){
      var userId = parseInt(memberField.val(), 10) || 0;

      if (!userId) {
        disableSelect(vehicleField, 'Select a member first…');
        return;
      }

      disableSelect(vehicleField, 'Loading vehicles…');

      fetchVehicles(userId, function(options){
        if (!options.length) {
          disableSelect(vehicleField, 'No vehicles found for this member');
          return;
        }
        setSelectOptions(vehicleField, options);
      });
    }

    // Initial + on change
    refresh();
    memberField.on('change', refresh);
  }

  acfscccInit = function(\$el){
    var memberFields = acf.getFields({ name: 'member_user' }, \$el);
    (memberFields || []).forEach(wireMemberField);
  };

  acf.addAction('ready', function(\$el){ RtlInit(\$el); });
  acf.addAction('append', function(\$el){ RtlInit(\$el); });

  function RtlInit(\$el){
    try { RtlInit = function(){}; } catch(e){}
    // actually init every time for appended blocks
    var memberFields = acf.getFields({ name: 'member_user' }, \$el);
    (memberFields || []).forEach(wireMemberField);
  }
})(jQuery);
JS;

        /**
         * Attach inline script to ACF input handle.
         * If for some reason this handle isn't present yet, WordPress will still queue it,
         * and the script will run once ACF loads.
         */
        wp_add_inline_script('acf-input', $js, 'after');
    }

    /**
     * AJAX: return the user's vehicles as [{value: vehicle_id, label: "..."}, ...]
     */
    public function ajaxUserVehicles(): void
    {
        if (!is_admin()) {
            wp_send_json_success([]);
        }

        // Basic capability gate (editors creating pages still need this)
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('forbidden', 403);
        }

        check_ajax_referer('sccc_user_vehicles', 'nonce');

        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        if (!$user_id) {
            wp_send_json_success([]);
        }

        if (!function_exists('get_field') || !function_exists('update_field')) {
            wp_send_json_success([]);
        }

        $vehicles = get_field('vehicles', 'user_' . $user_id);
        if (!is_array($vehicles) || empty($vehicles)) {
            wp_send_json_success([]);
        }

        // Backfill IDs if needed so the dropdown always has stable values.
        $changed = $this->ensureVehicleIds($vehicles);

        // Also ensure make_raw exists for labels
        foreach ($vehicles as $i => $row) {
            $makeSelect = $row['make_select'] ?? '';
            $makeOther  = $row['make_other'] ?? '';
            $makeRaw    = $row['make_raw'] ?? '';

            if (trim((string) $makeRaw) === '') {
                $computed = ($makeSelect === 'Other' && !empty($makeOther)) ? $makeOther : $makeSelect;
                $computed = preg_replace('/\s+/', ' ', trim((string) $computed));
                $vehicles[$i]['make_raw'] = $computed;
                $changed = true;
            }
        }

        if ($changed) {
            update_field('vehicles', $vehicles, 'user_' . $user_id);
        }

        $out = [];

        foreach ($vehicles as $row) {
            $vid = isset($row['vehicle_id']) ? trim((string) $row['vehicle_id']) : '';
            if ($vid === '') {
                continue;
            }

            $year  = trim((string) ($row['year'] ?? ''));
            $make  = trim((string) ($row['make_raw'] ?? ''));
            $model = trim((string) ($row['model'] ?? ''));
            $nick  = trim((string) ($row['nickname'] ?? ''));

            $labelParts = array_filter([$year, $make, $model]);
            $label = implode(' ', $labelParts);

            if ($nick !== '') {
                $label = $label !== '' ? ($label . ' (' . $nick . ')') : $nick;
            }

            if ($label === '') {
                $label = $vid;
            }

            $out[] = [
                'value' => $vid,
                'label' => $label,
            ];
        }

        wp_send_json_success($out);
    }

    /**
     * Small helper to safely embed a PHP string inside inline JS.
     */
    protected function jsString(string $s): string
    {
        return json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}