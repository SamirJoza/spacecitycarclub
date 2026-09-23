<?php

/**
 * app/Support/GravityForms/SponsorApcSync.php
 * File: app/Support/GravityForms/SponsorApcSync.php
 *
 * Gravity Forms APC -> Sponsor CPT sync
 *
 * What this file does
 * -----------------------------------------------------------------------------
 * 1) Replaces the APC post title with the submitted Company Name.
 * 2) Replaces post_content with the submitted Notes field.
 * 3) After the sponsor post is created, writes selected Gravity Forms values
 *    into the matching ACF sponsor fields using explicit ACF field keys.
 *
 * IMPORTANT
 * -----------------------------------------------------------------------------
 * - Adjust ONLY the CONFIG section if the form ID changes later.
 * - This file assumes your Sponsor ACF field class defines explicit field keys:
 *   field_sponsor_company_website
 *   field_sponsor_contact_first_name
 *   field_sponsor_contact_last_name
 *   field_sponsor_contact_phone
 *   field_sponsor_contact_email
 *   field_sponsor_source (optional; Gravity Forms hidden field ID via {@see referralSourceFieldId()})
 * - Taxonomy handling is intentionally omitted because you said the taxonomy
 *   already comes in correctly.
 */

namespace App\Support\GravityForms;

class SponsorApcSync
{
    /**
     * -------------------------------------------------------------------------
     * CONFIG
     * -------------------------------------------------------------------------
     */
    private const FORM_ID   = 2;
    private const POST_TYPE = 'sponsor';

    /**
     * Optional Gravity Forms field ID (number) for the hidden referral/source field.
     * When set (>0), {@see syncReferralSourceFromEntry()} writes into ACF `sponsor_source`.
     * Leave 0 to use filter {@see sccc_sponsor_gf_source_field_id} or auto-detect a hidden/text
     * field whose parameter name is `referral_source` (same as {@see GravityFormsCookiePopulation}).
     */
    private const GF_FIELD_SPONSOR_SOURCE_ID = 0;

    /**
     * Cookie set by {@see \App\Support\Marketing\ReferralSourceTracker} / read by
     * {@see \App\Support\Marketing\GravityFormsCookiePopulation} when the GF hidden field is empty in the entry.
     */
    private const REFERRAL_SOURCE_COOKIE = 'sccc_referral_source';

    /**
     * Turn on debug logging in wp-content/debug.log when WP_DEBUG is enabled.
     * Set to false once you've verified everything works.
     */
    private const DEBUG = true;

    /**
     * Gravity Forms field IDs
     */
    private const GF_FIELD_COMPANY_NAME        = '1';
    private const GF_FIELD_EMAIL               = '4';
    private const GF_FIELD_PHONE               = '5';
    private const GF_FIELD_COMPANY_WEBSITE     = '6';
    private const GF_FIELD_NOTES               = '8';
    private const GF_FIELD_CONTACT_FIRST_NAME  = '9';
    private const GF_FIELD_CONTACT_LAST_NAME   = '10';

    /**
     * GF field ID => ACF target
     * Company Name is NOT included here because it becomes the post title.
     * Notes are NOT included here because they become post_content.
     */
    private const ACF_FIELD_MAP = [
        self::GF_FIELD_COMPANY_WEBSITE => [
            'name' => 'sponsor_company_website',
            'key'  => 'field_sponsor_company_website',
        ],
        self::GF_FIELD_CONTACT_FIRST_NAME => [
            'name' => 'sponsor_contact_first_name',
            'key'  => 'field_sponsor_contact_first_name',
        ],
        self::GF_FIELD_CONTACT_LAST_NAME => [
            'name' => 'sponsor_contact_last_name',
            'key'  => 'field_sponsor_contact_last_name',
        ],
        self::GF_FIELD_PHONE => [
            'name' => 'sponsor_contact_phone',
            'key'  => 'field_sponsor_contact_phone',
        ],
        self::GF_FIELD_EMAIL => [
            'name' => 'sponsor_contact_email',
            'key'  => 'field_sponsor_contact_email',
        ],
    ];

    public static function boot(): void
    {
        add_filter('gform_advancedpostcreation_post', [self::class, 'filterApcPost'], 10, 4);
        add_action('gform_advancedpostcreation_post_after_creation', [self::class, 'syncAcfFields'], 10, 4);
        /**
         * Feeds often run on shutdown via loopback; that request has no browser cookies.
         * Stash first-party referral cookie while the public submission request still has it.
         */
        add_action('gform_after_submission', [self::class, 'stashReferralForAsyncFeedProcessing'], 5, 2);
    }

    /**
     * Public handler: stores referral attribution for {@see consumeStashedReferralForEntry()} when APC runs async.
     * Prefers {@see REFERRAL_SOURCE_COOKIE} over the GF hidden field when both are set (marketing cookie is canonical).
     *
     * @param array $lead
     * @param array $form
     */
    public static function stashReferralForAsyncFeedProcessing($lead, $form): void
    {
        if (!is_array($lead) || !is_array($form) || !self::isTargetForm($form)) {
            return;
        }

        $entryId = (int) ($lead['id'] ?? 0);
        if ($entryId <= 0) {
            return;
        }

        $formResolved = self::formWithFieldsResolved($form);
        $fieldId      = self::resolvedReferralSourceFieldId($formResolved, $lead);

        $fromCookie = self::sourceFromReferralCookie();
        $fromEntry  = '';
        if ($fieldId !== '') {
            $fromEntry = self::sanitizeText(self::rawReferralSourceFromEntry($lead, $formResolved, $fieldId));
        }

        $v = $fromCookie !== '' ? $fromCookie : $fromEntry;
        if ($v === '') {
            return;
        }

        set_transient(self::transientEntryReferralKey($entryId), $v, HOUR_IN_SECONDS);
    }

    /**
     * Replace APC title with Company Name and post_content with Notes.
     *
     * @param array $post
     * @param array $feed
     * @param array $entry
     * @param array $form
     * @return array
     */
    public static function filterApcPost($post, $feed, $entry, $form): array
    {
        if (!self::isTargetForm($form) || !is_array($post)) {
            return is_array($post) ? $post : [];
        }

        $postType = (string) ($post['post_type'] ?? '');
        if ($postType !== self::POST_TYPE) {
            return $post;
        }

        $companyName = self::sanitizeText(self::getEntryValue($entry, self::GF_FIELD_COMPANY_NAME));
        $notes       = self::sanitizeContent(self::getEntryValue($entry, self::GF_FIELD_NOTES));

        if ($companyName !== '') {
            $post['post_title'] = $companyName;
            $post['post_name']  = sanitize_title($companyName);
        }

        $post['post_content'] = $notes;

        self::log('filterApcPost', [
            'form_id'       => self::extractFormId($form),
            'post_type'     => $postType,
            'company_name'  => $companyName,
            'post_title'    => $post['post_title'] ?? '',
            'notes_length'  => strlen($notes),
        ]);

        return $post;
    }

    /**
     * After APC creates the sponsor post, sync selected entry values into ACF.
     *
     * @param int   $post_id
     * @param array $feed
     * @param array $entry
     * @param array $form
     * @return void
     */
    public static function syncAcfFields($post_id, $feed, $entry, $form): void
    {
        if (!self::isTargetForm($form)) {
            return;
        }

        $postId = (int) $post_id;
        if ($postId <= 0) {
            return;
        }

        if (get_post_type($postId) !== self::POST_TYPE) {
            return;
        }

        foreach (self::ACF_FIELD_MAP as $gfFieldId => $acfField) {
            $acfName = (string) ($acfField['name'] ?? '');
            $acfKey  = (string) ($acfField['key'] ?? '');

            if ($acfName === '' || $acfKey === '') {
                continue;
            }

            $rawValue = self::getEntryValue($entry, (string) $gfFieldId);
            $value    = self::normalizeValueForAcfField($acfName, $rawValue);

            self::log('syncAcfFields.before_update', [
                'post_id'      => $postId,
                'gf_field_id'  => $gfFieldId,
                'acf_name'     => $acfName,
                'acf_key'      => $acfKey,
                'raw_value'    => $rawValue,
                'value'        => $value,
            ]);

            self::updateAcfField($postId, $acfName, $acfKey, $value);

            self::log('syncAcfFields.after_update', [
                'post_id'       => $postId,
                'acf_name'      => $acfName,
                'acf_key'       => $acfKey,
                'stored_meta'   => get_post_meta($postId, $acfName, true),
                'reference_key' => get_post_meta($postId, '_' . $acfName, true),
            ]);
        }

        self::syncReferralSourceFromEntry($postId, $entry, $form);
    }

    /**
     * Copy GF hidden “referral source” into ACF {@see sponsor_source} when a field ID is configured.
     *
     * @param array $form Gravity Forms form array (used with {@see GFFormsModel::get_lead_field_value}).
     */
    private static function syncReferralSourceFromEntry(int $postId, array $entry, array $form): void
    {
        $form    = self::formWithFieldsResolved($form);
        $fieldId = self::resolvedReferralSourceFieldId($form, $entry);

        /**
         * When true (default), `sccc_referral_source` wins over the GF hidden field when both are non-empty.
         * Set to false for GF → cookie → transient order.
         *
         * @param bool $prefer
         */
        $preferCookie = (bool) apply_filters('sccc_sponsor_source_prefer_marketing_cookie', true);

        $fromCookie = self::sourceFromReferralCookie();

        $loadGf = $fieldId !== '' && (!$preferCookie || $fromCookie === '');
        $fromGf = '';
        if ($loadGf) {
            $fromGf = self::rawReferralSourceFromEntry($entry, $form, $fieldId);
            if ($fromGf === '' && !empty($entry['id']) && class_exists('GFAPI')) {
                $fresh = \GFAPI::get_entry((int) $entry['id']);
                if (!is_wp_error($fresh) && is_array($fresh)) {
                    $fromGf = self::rawReferralSourceFromEntry($fresh, $form, $fieldId);
                }
            }
        }

        $fromStash = '';
        if ($preferCookie) {
            if ($fromCookie === '' && !empty($entry['id'])) {
                $fromStash = self::consumeStashedReferralForEntry((int) $entry['id']);
            }
            $raw = $fromCookie !== '' ? $fromCookie : ($fromStash !== '' ? $fromStash : $fromGf);
        } else {
            if ($fromGf === '' && $fromCookie === '' && !empty($entry['id'])) {
                $fromStash = self::consumeStashedReferralForEntry((int) $entry['id']);
            }
            $raw = $fromGf !== '' ? $fromGf : ($fromCookie !== '' ? $fromCookie : $fromStash);
        }

        $val = self::sanitizeText((string) $raw);
        if ($val === '') {
            $val = 'Direct';
        }

        $winner = 'direct';
        if ($raw !== '') {
            if ($preferCookie) {
                if ($fromCookie !== '' && self::sanitizeText($fromCookie) === $val) {
                    $winner = 'cookie';
                } elseif ($fromStash !== '' && self::sanitizeText($fromStash) === $val) {
                    $winner = 'transient';
                } elseif ($fromGf !== '' && self::sanitizeText($fromGf) === $val) {
                    $winner = 'gf';
                }
            } else {
                if ($fromGf !== '' && self::sanitizeText($fromGf) === $val) {
                    $winner = 'gf';
                } elseif ($fromCookie !== '' && self::sanitizeText($fromCookie) === $val) {
                    $winner = 'cookie';
                } elseif ($fromStash !== '' && self::sanitizeText($fromStash) === $val) {
                    $winner = 'transient';
                }
            }
        }

        self::log('syncReferralSourceFromEntry.result', [
            'post_id'    => $postId,
            'entry_id'   => (int) ($entry['id'] ?? 0),
            'field_id'   => $fieldId,
            'raw_len'    => strlen((string) $raw),
            'value'      => $val,
            'winner'     => $winner,
            'prefer_ck'  => $preferCookie,
        ]);

        self::updateAcfField($postId, 'sponsor_source', 'field_sponsor_source', $val);

        self::log('syncReferralSourceFromEntry.persisted', [
            'post_id'  => $postId,
            'stored'   => get_post_meta($postId, 'sponsor_source', true),
            'ref'      => get_post_meta($postId, '_sponsor_source', true),
        ]);

        if (!empty($entry['id'])) {
            delete_transient(self::transientEntryReferralKey((int) $entry['id']));
        }
    }

    /**
     * GF sometimes passes a slim $form to hooks; load full form so field `inputName` / types exist for detection.
     */
    private static function formWithFieldsResolved(array $form): array
    {
        $id = (int) ($form['id'] ?? 0);
        if ($id <= 0 || !class_exists('GFAPI')) {
            return $form;
        }

        $full = \GFAPI::get_form($id);
        if (is_wp_error($full) || !is_array($full) || empty($full['fields'])) {
            return $form;
        }

        return $full;
    }

    private static function transientEntryReferralKey(int $entryId): string
    {
        return 'sccc_sp_src_' . $entryId;
    }

    /**
     * One-shot value from {@see stashReferralForAsyncFeedProcessing()} for loopback feed requests.
     */
    private static function consumeStashedReferralForEntry(int $entryId): string
    {
        if ($entryId <= 0) {
            return '';
        }

        $key = self::transientEntryReferralKey($entryId);
        $raw = get_transient($key);
        delete_transient($key);

        if (!is_string($raw) || trim($raw) === '') {
            return '';
        }

        return trim($raw);
    }

    /**
     * Same cookie family as ReferralSourceTracker / GravityFormsCookiePopulation (first-party marketing).
     */
    private static function sourceFromReferralCookie(): string
    {
        if (empty($_COOKIE[self::REFERRAL_SOURCE_COOKIE]) || !is_string($_COOKIE[self::REFERRAL_SOURCE_COOKIE])) {
            return '';
        }

        return self::sanitizeText(wp_unslash($_COOKIE[self::REFERRAL_SOURCE_COOKIE]));
    }

    /**
     * Prefer GF’s lead field resolver (handles input keys / filters); fall back to {@see getEntryValue()}.
     */
    private static function rawReferralSourceFromEntry(array $entry, array $form, string $fieldId): string
    {
        $numericId = (int) $fieldId;
        if ($numericId <= 0) {
            return '';
        }

        if (class_exists('GFAPI') && class_exists('GFFormsModel')) {
            $field = \GFAPI::get_field($form, $numericId);
            if ($field) {
                $v = \GFFormsModel::get_lead_field_value($entry, $field);
                if (is_array($v)) {
                    foreach ($v as $piece) {
                        $t = trim((string) $piece);
                        if ($t !== '') {
                            return $t;
                        }
                    }

                    return '';
                }
                if ($v !== null && $v !== false && (string) $v !== '') {
                    return trim((string) $v);
                }
            }
        }

        return self::getEntryValue($entry, $fieldId);
    }

    /**
     * Parameter names (GF “Allow field to be populated dynamically”) treated as referral source.
     *
     * @return string[]
     */
    private static function sourceFieldInputNames(): array
    {
        $names = ['referral_source'];

        /**
         * Extra GF dynamic-parameter names to map into sponsor_source (lowercase trimmed).
         *
         * @param string[] $names
         */
        $filtered = apply_filters('sccc_sponsor_gf_source_input_names', $names);
        if (!is_array($filtered)) {
            return $names;
        }

        $out = [];
        foreach ($filtered as $n) {
            $n = strtolower(trim((string) $n));
            if ($n !== '' && !in_array($n, $out, true)) {
                $out[] = $n;
            }
        }

        return $out !== [] ? $out : $names;
    }

    /**
     * GF field IDs already mapped to other ACF columns (or title/content) — never use for source guess.
     *
     * @return string[]
     */
    private static function gfFieldIdsExcludedFromSourceGuess(): array
    {
        $ids = array_map('strval', array_keys(self::ACF_FIELD_MAP));
        $ids[] = self::GF_FIELD_COMPANY_NAME;
        $ids[] = self::GF_FIELD_NOTES;

        return array_values(array_unique($ids));
    }

    /**
     * Explicit GF field ID from constant/filter, else match dynamic parameter name(s), else single-candidate hidden heuristic.
     */
    private static function resolvedReferralSourceFieldId(array $form, array $entry): string
    {
        $explicit = self::referralSourceFieldId();
        if ($explicit !== '') {
            return $explicit;
        }

        $wantNames = self::sourceFieldInputNames();
        $typesOk  = ['hidden', 'text', 'textarea'];

        foreach ($form['fields'] ?? [] as $field) {
            if ($field instanceof \GF_Field) {
                $type       = (string) $field->type;
                $fid        = (int) $field->id;
                $inputName  = self::normalizeDynamicParamName((string) $field->inputName);
                $allowsPre  = (bool) $field->allowsPrepopulate;
            } elseif (is_array($field)) {
                $type       = (string) ($field['type'] ?? '');
                $fid        = (int) ($field['id'] ?? 0);
                $inputName  = self::normalizeDynamicParamName((string) ($field['inputName'] ?? ''));
                $allowsPre  = !empty($field['allowsPrepopulate']);
            } else {
                continue;
            }

            if ($fid <= 0 || !in_array($type, $typesOk, true)) {
                continue;
            }

            if ($inputName === '' || !in_array($inputName, $wantNames, true)) {
                continue;
            }

            // Prefer standard prepopulated fields; allow hidden-only match when the flag is missing from exported form JSON.
            if (!$allowsPre && $type !== 'hidden') {
                continue;
            }

            return (string) $fid;
        }

        return self::resolvedReferralSourceFieldIdHeuristic($form, $entry);
    }

    /**
     * If exactly one prepopulated hidden field (not used elsewhere) has a submitted value, treat it as source.
     */
    private static function resolvedReferralSourceFieldIdHeuristic(array $form, array $entry): string
    {
        $excluded = self::gfFieldIdsExcludedFromSourceGuess();
        $hits     = [];

        foreach ($form['fields'] ?? [] as $field) {
            if ($field instanceof \GF_Field) {
                $type      = (string) $field->type;
                $fid       = (int) $field->id;
                $allowsPre = (bool) $field->allowsPrepopulate;
            } elseif (is_array($field)) {
                $type      = (string) ($field['type'] ?? '');
                $fid       = (int) ($field['id'] ?? 0);
                $allowsPre = !empty($field['allowsPrepopulate']);
            } else {
                continue;
            }

            if ($type !== 'hidden' || !$allowsPre || $fid <= 0) {
                continue;
            }

            $idStr = (string) $fid;
            if (in_array($idStr, $excluded, true)) {
                continue;
            }

            if (self::getEntryValue($entry, $idStr) === '') {
                continue;
            }

            $hits[] = $idStr;
        }

        if (count($hits) === 1) {
            return $hits[0];
        }

        return '';
    }

    private static function normalizeDynamicParamName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[\s-]+/', '_', $name) ?? $name;

        return $name;
    }

    /**
     * Gravity Forms field ID (string) for the hidden referral/source field, or empty to rely on auto-detect.
     *
     * Filter: `sccc_sponsor_gf_source_field_id` — return e.g. `'12'` for field 12.
     */
    private static function referralSourceFieldId(): string
    {
        if (self::GF_FIELD_SPONSOR_SOURCE_ID > 0) {
            return (string) (int) self::GF_FIELD_SPONSOR_SOURCE_ID;
        }

        $id = trim((string) apply_filters('sccc_sponsor_gf_source_field_id', ''));

        return preg_replace('/[^0-9]/', '', $id);
    }

    /**
     * Confirm this is the configured form.
     *
     * @param mixed $form
     * @return bool
     */
    private static function isTargetForm($form): bool
    {
        return self::extractFormId($form) === self::FORM_ID;
    }

    /**
     * Extract form ID from array/object.
     *
     * @param mixed $form
     * @return int
     */
    private static function extractFormId($form): int
    {
        if (is_array($form)) {
            return (int) ($form['id'] ?? 0);
        }

        if (is_object($form) && isset($form->id)) {
            return (int) $form->id;
        }

        return 0;
    }

    /**
     * Read a raw Gravity Forms entry value by field ID.
     *
     * @param array  $entry
     * @param string $fieldId
     * @return string
     */
    private static function getEntryValue(array $entry, string $fieldId): string
    {
        if (function_exists('rgar')) {
            return trim((string) rgar($entry, $fieldId));
        }

        return trim((string) ($entry[$fieldId] ?? ''));
    }

    /**
     * Normalize/sanitize values before saving to ACF.
     *
     * @param string $acfFieldName
     * @param string $value
     * @return string
     */
    private static function normalizeValueForAcfField(string $acfFieldName, string $value): string
    {
        return match ($acfFieldName) {
            'sponsor_company_website' => self::sanitizeUrl($value),
            'sponsor_contact_email'   => sanitize_email($value),
            default                   => self::sanitizeText($value),
        };
    }

    /**
     * Update a field via ACF key so ACF stores both value and reference meta.
     *
     * @param int    $postId
     * @param string $fieldName
     * @param string $fieldKey
     * @param mixed  $value
     * @return void
     */
    private static function updateAcfField(int $postId, string $fieldName, string $fieldKey, $value): void
    {
        if (function_exists('update_field')) {
            update_field($fieldKey, $value, $postId);
        } else {
            update_post_meta($postId, $fieldName, $value);
            update_post_meta($postId, '_' . $fieldName, $fieldKey);

            return;
        }

        // New posts + APC/async: WP object cache can still return empty meta in the same request, and ACF
        // may omit `_fieldname` references. Ensure value + reference match what ACF expects in the editor.
        wp_cache_delete($postId, 'post_meta');

        $stored = get_post_meta($postId, $fieldName, true);
        if ((string) $stored !== (string) $value) {
            update_post_meta($postId, $fieldName, $value);
        }

        $ref = get_post_meta($postId, '_' . $fieldName, true);
        if ($ref === '' || $ref === false) {
            update_post_meta($postId, '_' . $fieldName, $fieldKey);
        }
    }

    /**
     * Basic text sanitization.
     *
     * @param string $value
     * @return string
     */
    private static function sanitizeText(string $value): string
    {
        return sanitize_text_field($value);
    }

    /**
     * Sanitize post content while preserving line breaks.
     *
     * @param string $value
     * @return string
     */
    private static function sanitizeContent(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        return sanitize_textarea_field($value);
    }

    /**
     * URL sanitization.
     *
     * @param string $value
     * @return string
     */
    private static function sanitizeUrl(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return esc_url_raw($value);
    }

    /**
     * Debug logger.
     *
     * @param string $context
     * @param array  $data
     * @return void
     */
    private static function log(string $context, array $data = []): void
    {
        if (!self::DEBUG || !defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        error_log('[SponsorApcSync][' . $context . '] ' . wp_json_encode($data));
    }
}

SponsorApcSync::boot();