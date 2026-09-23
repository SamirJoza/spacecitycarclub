<?php
/**
 * app/Support/Taxonomies/BusinessDirectoryTaxonomies.php
 * File: app/Support/Taxonomies/BusinessDirectoryTaxonomies.php
 *
 * What this file does
 * -----------------------------------------------------------------------------
 * Defines the "admin/dev-managed" vocabulary lists for the Member Business feature:
 * - Business Categories (broad directory buckets)
 * - Business Services (searchable service tags)
 *
 * IMPORTANT
 * -----------------------------------------------------------------------------
 * - These are NOT WordPress taxonomies in the DB.
 * - They are controlled lists (keys + labels) used by ACF user fields.
 * - Users can pick from these values, but cannot create new ones.
 *
 * Why we do it this way
 * -----------------------------------------------------------------------------
 * - You want business data stored on the USER (user meta) — one business per member.
 * - You still want directory-like filtering (Category + Services).
 * - Keeping the lists here makes them reusable across:
 *   - Member edit forms (ACF / My Account later)
 *   - Directory display pages later
 *   - Reports / widgets later
 *
 * Extensibility
 * -----------------------------------------------------------------------------
 * We provide filters so you can extend/override lists without editing this file:
 * - sccc_business_directory_categories
 * - sccc_business_directory_services
 */

namespace App\Support\Taxonomies;

class BusinessDirectoryTaxonomies
{
    /**
     * Business Categories
     * -----------------------------------------------------------------------------
     * Broad buckets for directory filtering.
     *
     * RULE: Don't change keys once in production (labels are safe to change).
     */
    public static function categories(): array
    {
        $cats = [
            // Automotive
            'auto_sales'              => 'Automotive Sales',
            'auto_repair'             => 'Auto Repair & Maintenance',
            'auto_detailing'          => 'Auto Detailing',
            'auto_body_paint'         => 'Body, Paint & Collision',
            'auto_wrap_ppf'           => 'Wrap, Tint & PPF',
            'auto_performance'        => 'Performance & Tuning',
            'auto_parts_accessories'  => 'Parts & Accessories',
            'auto_wheels_tires'       => 'Wheels & Tires',
            'auto_audio_electronics'  => 'Car Audio & Electronics',
            'auto_towing'             => 'Towing & Roadside',
            'auto_transport'          => 'Vehicle Transport',
            'auto_rental'             => 'Rental, Fleet & Leasing',

            // Home & Property
            'home_services'           => 'Home Services',
            'home_remodeling'         => 'Remodeling & Renovation',
            'home_trades'             => 'Trades (Plumbing, Electrical, HVAC)',
            'home_landscaping'        => 'Landscaping & Outdoor',
            'home_cleaning'           => 'Cleaning Services',
            'home_security'           => 'Security & Smart Home',

            // Business & Professional
            'professional_services'   => 'Professional Services',
            'legal'                   => 'Legal Services',
            'accounting_finance'      => 'Accounting & Finance',
            'insurance'               => 'Insurance',
            'real_estate'             => 'Real Estate',
            'marketing_media'         => 'Marketing, Design & Media',
            'it_tech'                 => 'IT & Tech Services',
            'consulting'              => 'Consulting & Coaching',
            'printing_signage'        => 'Printing & Signage',

            // Health & Wellness
            'health_medical'          => 'Health & Medical',
            'fitness'                 => 'Fitness & Training',
            'beauty_spa'              => 'Beauty, Spa & Grooming',
            'mental_health'           => 'Mental Health & Counseling',
            'senior_care'             => 'Senior Care & Assisted Living',

            // Food, Hospitality & Events
            'restaurants_food'        => 'Restaurants & Food',
            'bars_nightlife'          => 'Bars & Nightlife',
            'catering'                => 'Catering',
            'events_venues'           => 'Events & Venues',
            'photography_video'       => 'Photography & Video',
            'travel_hospitality'      => 'Travel & Hospitality',

            // Retail & Lifestyle
            'retail'                  => 'Retail',
            'apparel'                 => 'Apparel & Fashion',
            'jewelry_gifts'           => 'Jewelry & Gifts',
            'pets_animals'            => 'Pets & Animals',
            'sports_hobby'            => 'Sports & Hobby',
            'kids_family'             => 'Kids & Family',

            // Trades / Construction / Industrial
            'construction'            => 'Construction',
            'manufacturing'           => 'Manufacturing',
            'logistics'               => 'Logistics & Shipping',

            // Community
            'nonprofit'               => 'Nonprofit / Community',
            'education'               => 'Education',

            // Catch-all
            'other'                   => 'Other',
        ];

        /**
         * Filter hook (dev/admin code can override/extend lists).
         * Example: add_filter('sccc_business_directory_categories', fn($cats) => $cats);
         */
        $cats = (array) apply_filters('sccc_business_directory_categories', $cats);

        return $cats;
    }

    /**
     * Business Services
     * -----------------------------------------------------------------------------
     * More specific tags used for search and filtering.
     *
     * RULE: Don't change keys once in production (labels are safe to change).
     */
    public static function services(): array
    {
        $services = [
            // Automotive services
            'oil_change'            => 'Oil Change',
            'brakes'                => 'Brakes',
            'alignment'             => 'Alignment',
            'tires'                 => 'Tires',
            'wheels'                => 'Wheels',
            'diagnostics'           => 'Diagnostics',
            'ac_repair'             => 'A/C Repair',
            'engine_work'           => 'Engine Work',
            'transmission'          => 'Transmission',
            'battery_electrical'    => 'Battery & Electrical',
            'ceramic_coating'       => 'Ceramic Coating',
            'interior_detail'       => 'Interior Detailing',
            'exterior_detail'       => 'Exterior Detailing',
            'paint_correction'      => 'Paint Correction',
            'tint'                  => 'Window Tint',
            'ppf'                   => 'Paint Protection Film (PPF)',
            'vinyl_wrap'            => 'Vinyl Wrap',
            'body_repair'           => 'Body Repair',
            'paint'                 => 'Paint',
            'custom_fabrication'    => 'Custom Fabrication',
            'dyno_tune'             => 'Dyno / Tuning',
            'performance_parts'     => 'Performance Parts',
            'audio_install'         => 'Audio Install',
            'remote_start'          => 'Remote Start / Security',
            'towing'                => 'Towing',
            'roadside'              => 'Roadside Assistance',
            'vehicle_transport'     => 'Vehicle Transport',
            'auto_sales'            => 'Vehicle Sales',
            'auto_financing'        => 'Auto Financing',

            // Home services
            'plumbing'              => 'Plumbing',
            'electrical'            => 'Electrical',
            'hvac'                  => 'HVAC',
            'roofing'               => 'Roofing',
            'flooring'              => 'Flooring',
            'painting_home'         => 'Painting (Home)',
            'remodeling'            => 'Remodeling',
            'handyman'              => 'Handyman',
            'landscaping'           => 'Landscaping',
            'tree_service'          => 'Tree Service',
            'pest_control'          => 'Pest Control',
            'pool_service'          => 'Pool Service',
            'house_cleaning'        => 'House Cleaning',
            'pressure_washing'      => 'Pressure Washing',
            'moving'                => 'Moving',
            'smart_home'            => 'Smart Home',
            'home_security'         => 'Home Security',

            // Professional services
            'legal_services'        => 'Legal Services',
            'tax'                   => 'Tax Prep',
            'bookkeeping'           => 'Bookkeeping',
            'financial_planning'    => 'Financial Planning',
            'insurance_auto'        => 'Insurance (Auto)',
            'insurance_home'        => 'Insurance (Home)',
            'insurance_life'        => 'Insurance (Life)',
            'real_estate_buy_sell'  => 'Real Estate (Buy/Sell)',
            'real_estate_rentals'   => 'Real Estate (Rentals)',
            'mortgage'              => 'Mortgage / Lending',
            'notary'                => 'Notary',
            'marketing'             => 'Marketing',
            'graphic_design'        => 'Graphic Design',
            'web_design'            => 'Web Design / Development',
            'seo'                   => 'SEO',
            'social_media'          => 'Social Media Management',
            'photo_video'           => 'Photo / Video',
            'printing'              => 'Printing',
            'signage'               => 'Signage',
            'it_support'            => 'IT Support',
            'cybersecurity'         => 'Cybersecurity',
            'software_dev'          => 'Software Development',
            'consulting'            => 'Consulting',
            'coaching'              => 'Coaching',

            // Health & wellness
            'primary_care'          => 'Primary Care',
            'dental'                => 'Dental',
            'chiropractic'          => 'Chiropractic',
            'physical_therapy'      => 'Physical Therapy',
            'massage'               => 'Massage Therapy',
            'mental_health'         => 'Mental Health',
            'fitness_training'      => 'Fitness Training',
            'nutrition'             => 'Nutrition Coaching',
            'beauty_hair'           => 'Hair',
            'beauty_nails'          => 'Nails',
            'beauty_skin'           => 'Skincare',
            'spa'                   => 'Spa Services',

            // Food & events
            'restaurant'            => 'Restaurant',
            'food_truck'            => 'Food Truck',
            'bar'                   => 'Bar / Lounge',
            'catering'              => 'Catering',
            'bakery'                => 'Bakery',
            'event_venue'           => 'Event Venue',
            'event_planning'        => 'Event Planning',
            'dj_music'              => 'DJ / Music',
            'photobooth'            => 'Photo Booth',

            // Retail & lifestyle
            'apparel'               => 'Apparel',
            'gifts'                 => 'Gifts',
            'pet_services'          => 'Pet Services',
            'pet_grooming'          => 'Pet Grooming',

            // Trades / industrial
            'construction'          => 'Construction',
            'welding'               => 'Welding',
            'machining'             => 'Machining',
            'logistics'             => 'Logistics / Shipping',

            // Catch-all
            'other'                 => 'Other',
        ];

        /**
         * Filter hook (dev/admin code can override/extend lists).
         * Example: add_filter('sccc_business_directory_services', fn($services) => $services);
         */
        $services = (array) apply_filters('sccc_business_directory_services', $services);

        return $services;
    }
}
