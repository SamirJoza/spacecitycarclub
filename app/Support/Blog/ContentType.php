<?php
/**
 * File path + filename: app/ContentType.php
 *
 * Purpose:
 * - Register the Content Type taxonomy for standard WordPress posts.
 * - Prevent editors from creating new terms (admin-only term management).
 * - Enforce single-term selection in the block editor (client-side JS)
 *   and at the REST API boundary (server-side PHP).
 * - Enforce mandatory term selection before a post can be published,
 *   in both the block editor (REST API) and the classic editor.
 * - Display an admin notice when the classic editor blocks a publish.
 *
 * Why a separate file and not setup.php:
 * - setup.php handles theme bootstrapping (menus, image sizes, supports,
 *   asset enqueueing). Taxonomy registration and validation logic is
 *   feature code, not theme setup.
 *
 * Included from:
 *   app/setup.php — require_once get_theme_file_path('app/ContentType.php');
 *
 * Sections:
 *   1. Taxonomy registration
 *   2. Single-term enforcement — block editor (JS)
 *   3. Single-term enforcement — REST API (PHP hard cap)
 *   4. Mandatory validation — block editor (REST API)
 *   5. Mandatory validation — classic editor
 *   6. Admin notice — classic editor
 */

declare(strict_types=1);

namespace App;

// ============================================================================
// 1. TAXONOMY REGISTRATION
// ============================================================================

add_action('init', function () {
    register_extended_taxonomy(
        'content_type',
        'post',
        [
            'public'            => true,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,

            /*
             * meta_box: 'radio' is intentionally omitted.
             * See previous documentation — it breaks REST term saving.
             * Single-term enforcement is handled by sections 2 and 3.
             */

            /*
             * Explicit capabilities.
             *
             * Why these values:
             * - manage_terms / edit_terms / delete_terms → manage_options
             *   Only administrators can create, rename, or delete terms.
             *   Gutenberg checks the user's REST API create permission for
             *   the taxonomy before rendering the "Add Content Type" input.
             *   When create_terms requires manage_options, the input is
             *   hidden for all non-admin roles automatically — no JS needed.
             *
             * - assign_terms → edit_posts
             *   Any role that can edit posts (author, editor, admin) can
             *   still assign existing terms to their posts.
             */
            'capabilities' => [
                'manage_terms' => 'manage_options',
                'edit_terms'   => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'edit_posts',
            ],

            'rewrite' => [
                'slug'       => 'content-type',
                'with_front' => false,
            ],
        ],
        [
            'singular' => 'Content Type',
            'plural'   => 'Content Types',
            'slug'     => 'content-type',
        ]
    );
});


// ============================================================================
// 2. SINGLE-TERM ENFORCEMENT — Block Editor (client-side JS)
//
// Why the previous implementation did not work:
// - The inline script was attached to the 'wp-data' handle, which loads
//   very early — before the 'core/editor' store is registered.
// - wp.data.select('core/editor') returned null, so the subscriber
//   found no terms to act on and exited immediately every time.
//
// Fix:
// - Attach the script to 'wp-edit-post' instead. This handle loads the
//   main block editor bundle, by which point all editor stores including
//   'core/editor' are registered.
// - Wrap in wp.domReady() so the subscriber is set up only after the
//   editor DOM and stores are fully initialised.
// - Defer the editPost() dispatch via requestAnimationFrame so we are
//   not dispatching synchronously inside a subscribe callback, which can
//   cause stack issues in some Gutenberg versions.
//
// How it works:
// - wp.data.subscribe fires on every store change.
// - When content_type has more than one term, we immediately strip back
//   to the most recently selected one (last element), making the
//   checkbox panel behave like a radio group.
// - The 'selecting' flag prevents the subscriber from triggering itself
//   when it dispatches the corrective editPost() call.
// ============================================================================

add_action('enqueue_block_editor_assets', function () {

    // Only inject on the standard post editing screen.
    $screen = get_current_screen();
    if (! $screen || $screen->post_type !== 'post') {
        return;
    }

    wp_add_inline_script(
        'wp-edit-post', // ← Attached here so core/editor store is ready.
        <<<'JS'
        ( function () {
            'use strict';

            /*
             * wp.domReady is provided by the wp-dom-ready package, which is
             * a dependency of wp-edit-post. It ensures the DOM is ready and
             * all editor stores are registered before we set up the subscriber.
             */
            wp.domReady( function () {
                if ( ! window.wp || ! window.wp.data ) {
                    return;
                }

                var selecting = false;

                wp.data.subscribe( function () {
                    if ( selecting ) {
                        return;
                    }

                    var store = wp.data.select( 'core/editor' );
                    if ( ! store ) {
                        return;
                    }

                    var terms = store.getEditedPostAttribute( 'content_type' );

                    /*
                     * Nothing to do when zero or one term is selected.
                     * Also guard against non-array values during initial
                     * page load before post data is hydrated.
                     */
                    if ( ! Array.isArray( terms ) || terms.length <= 1 ) {
                        return;
                    }

                    /*
                     * More than one term — strip to the most recent selection.
                     * requestAnimationFrame defers the dispatch to the next
                     * paint cycle, keeping it out of the synchronous subscriber
                     * call stack and avoiding potential recursion issues.
                     */
                    selecting = true;

                    window.requestAnimationFrame( function () {
                        wp.data.dispatch( 'core/editor' ).editPost( {
                            content_type: [ terms[ terms.length - 1 ] ]
                        } );

                        /*
                         * Small timeout before clearing the flag so the
                         * corrective editPost dispatch and its resulting
                         * subscribe notifications have fully settled.
                         */
                        setTimeout( function () {
                            selecting = false;
                        }, 50 );
                    } );
                } );
            } );
        } )();
        JS,
        'after'
    );
});


// ============================================================================
// 3. SINGLE-TERM ENFORCEMENT — REST API (server-side hard cap)
//
// Why this is needed alongside the JS (section 2):
// - The client-side subscriber can be bypassed by direct API calls,
//   WP-CLI, plugins, or any context that does not load block editor JS.
// - This filter runs on every REST save and enforces the single-term
//   rule at the database boundary regardless of how the save was made.
//
// Priority 9 — runs before the mandatory validation filter (priority 10)
// so the term array is already normalised to one element when section 4
// checks for presence.
// ============================================================================

add_filter('rest_pre_insert_post', function ($prepared_post, $request) {

    $incomingTerms = $request->get_param('content_type');

    if (! is_array($incomingTerms) || count($incomingTerms) <= 1) {
        return $prepared_post;
    }

    // Strip to the first submitted term.
    $request->set_param('content_type', [ (int) reset($incomingTerms) ]);

    return $prepared_post;

}, 9, 2);


// ============================================================================
// 4. MANDATORY VALIDATION — Block Editor / REST API
//
// Blocks publishing when no content_type term is assigned.
// Drafts and auto-saves are intentionally allowed through.
// ============================================================================

add_filter('rest_pre_insert_post', function ($prepared_post, $request) {

    $targetStatus = $prepared_post->post_status ?? '';

    if (! in_array($targetStatus, ['publish', 'future'], true)) {
        return $prepared_post;
    }

    $incomingTerms = $request->get_param('content_type');

    // Update request where the taxonomy panel was not touched — check
    // whether a term is already stored on the post.
    if (is_null($incomingTerms) && ! empty($prepared_post->ID)) {
        $existingTerms = wp_get_post_terms(
            $prepared_post->ID,
            'content_type',
            ['fields' => 'ids']
        );

        if (! is_wp_error($existingTerms) && ! empty($existingTerms)) {
            return $prepared_post;
        }
    }

    if (empty($incomingTerms)) {
        return new \WP_Error(
            'content_type_required',
            __('Please select a Content Type before publishing. Go to the Content Type panel in the sidebar.', 'sage'),
            ['status' => 400]
        );
    }

    return $prepared_post;

}, 10, 2);


// ============================================================================
// 5. MANDATORY VALIDATION — Classic Editor
// ============================================================================

add_filter('wp_insert_post_data', function ($data, $postarr) {

    if ($data['post_type'] !== 'post') {
        return $data;
    }

    if (! in_array($data['post_status'], ['publish', 'future'], true)) {
        return $data;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $data;
    }

    $terms = isset($postarr['tax_input']['content_type'])
        ? array_filter((array) $postarr['tax_input']['content_type'])
        : [];

    if (empty($terms) && ! empty($postarr['ID'])) {
        $existing = wp_get_post_terms(
            $postarr['ID'],
            'content_type',
            ['fields' => 'ids']
        );

        if (! is_wp_error($existing) && ! empty($existing)) {
            return $data;
        }
    }

    if (empty($terms)) {
        $data['post_status'] = 'draft';

        set_transient(
            'sccc_content_type_missing_' . get_current_user_id(),
            true,
            60
        );
    }

    return $data;

}, 10, 2);


// ============================================================================
// 6. ADMIN NOTICE — Classic Editor
// ============================================================================

add_action('admin_notices', function () {

    $screen = get_current_screen();

    if (! $screen || $screen->base !== 'post' || $screen->post_type !== 'post') {
        return;
    }

    $transientKey = 'sccc_content_type_missing_' . get_current_user_id();

    if (get_transient($transientKey)) {
        delete_transient($transientKey);
        printf(
            '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
            esc_html__(
                'This post was saved as a Draft because no Content Type was selected. Please choose a Content Type before publishing.',
                'sage'
            )
        );
    }
});

// ============================================================================
// 1b. HIDE "ADD CONTENT TYPE" IN THE BLOCK EDITOR
//
// Why map_meta_cap and not CSS or JS:
// - Gutenberg checks create_terms permission via a REST OPTIONS request
//   before deciding whether to render the "Add" input in the taxonomy panel.
// - Returning ['do_not_allow'] for create_terms when inside a REST request
//   causes Gutenberg to receive a false permission and suppress the input
//   entirely — no DOM manipulation needed.
//
// Why REST_REQUEST scope and not globally:
// - We only want to hide the Add input in the block editor.
// - The wp-admin taxonomy management screen (Posts → Content Types) submits
//   via a traditional PHP form — REST_REQUEST is not defined there, so
//   admins can still create and manage terms normally from that screen.
// - Scoping to REST_REQUEST means this denial never affects the taxonomy
//   management page, WP-CLI, or any non-editor context.
// ============================================================================

add_filter('map_meta_cap', function ($caps, $cap, $user_id, $args) {

    if (
        $cap === 'create_terms'
        && isset($args[0])
        && $args[0] === 'content_type'
        && defined('REST_REQUEST')
        && REST_REQUEST
    ) {
        return ['do_not_allow'];
    }

    return $caps;

}, 10, 4);