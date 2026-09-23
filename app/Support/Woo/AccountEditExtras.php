<?php
/**
 * app/Support/Woo/AccountEditExtras.php
 * File: app/Support/Woo/AccountEditExtras.php
 *
 * What this file does
 * -----------------------------------------------------------------------------
 * Adds Space City Car Club member profile fields to WooCommerce → My Account →
 * Account details, while keeping WooCommerce as the authority for the account
 * form and save action.
 *
 * Current behavior kept
 * -----------------------------------------------------------------------------
 * - Keeps the existing WooCommerce account fields and password fields.
 * - Adds multipart form support for profile/logo uploads.
 * - Stores profile image attachment ID as: sccc_profile_image_id.
 * - Loads CropperJS only on My Account → edit-account.
 * - Keeps the password autofill guard so browser autofill does not accidentally
 *   trigger WooCommerce password-change validation.
 *
 * Member profile fields for TRUE members
 * -----------------------------------------------------------------------------
 * - Phone Number: sccc_member_phone
 * - Directory opt-out: sccc_hide_in_member_directory
 * - Member Website: sccc_member_website
 * - Member Social Media: sccc_member_social_links
 *   - platform: facebook|instagram|x|tiktok|youtube
 *   - username: stored without leading @
 *   - url: full profile URL
 * - Birthdate: birth_date
 * - Service / Responder Status:
 *   - is_veteran_first_responder
 *   - veteran_type
 *   - agency_branch
 *
 * Phone storage + validation rule
 * -----------------------------------------------------------------------------
 * Phone numbers are stored as digits only:
 *   7135551234
 *
 * Formatting happens only on display:
 *   (713) 555-1234
 *
 * Only 10-digit US/NANP-style numbers are accepted. We intentionally do NOT
 * accept +1 or any other country prefix because the club is Greater Houston /
 * US-focused and accepting prefixes would open up international numbering rules.
 *
 * Validation rules:
 * - Empty is allowed.
 * - Non-digits are stripped before validation.
 * - Final stored value must be exactly 10 digits.
 * - Area code cannot start with 0 or 1.
 * - Exchange/prefix cannot start with 0 or 1.
 * - Exchange/prefix cannot be an N11 service code like 211, 311, 411, 911.
 * - Repeated junk numbers like 1111111111 are rejected.
 *
 * Directory visibility rule
 * -----------------------------------------------------------------------------
 * The checkbox is intentionally reversed:
 * “Don’t show me in the Member Directory.”
 *
 * That means active members are visible by default later, unless this stored meta
 * key is set to 1:
 *   sccc_hide_in_member_directory = 1
 *
 * Phone privacy rule
 * -----------------------------------------------------------------------------
 * The phone number is collected for club/admin use and dashboard display. It is
 * not intended to be shown in the future Member Directory unless we add a
 * separate explicit phone-display opt-in later.
 *
 * Business Directory fields
 * -----------------------------------------------------------------------------
 * Adds an optional “Business” section for TRUE members only.
 * - Each member can have exactly ONE business listing stored on the user.
 * - Opt-in controls whether the listing is eligible to be shown.
 * - Industry and Services Offered are hardcoded, stable option keys.
 * - Business data is still separate from personal/member profile data.
 */

namespace App\Support\Woo;

use App\Support\Members\MemberContext;

class AccountEditExtras
{
    public static function boot(): void
    {
        add_action('woocommerce_edit_account_form_tag', [static::class, 'addMultipartEnctypeAndDataAttr']);
        add_action('wp_enqueue_scripts', [static::class, 'enqueueCropperAssets']);
        add_action('wp_footer', [static::class, 'renderCropperModal'], 30);

        add_action('woocommerce_edit_account_form_start', [static::class, 'openCoreCardWrapper'], 5);
        add_action('woocommerce_edit_account_form', [static::class, 'closeCoreCardWrapper'], 5);

        add_action('woocommerce_edit_account_form', [static::class, 'renderFields'], 10);

        add_action('woocommerce_edit_account_form', [static::class, 'openActionsCardWrapper'], 20);
        add_action('woocommerce_edit_account_form_end', [static::class, 'closeActionsCardWrapper'], 20);

        /**
         * Validate custom account fields before WooCommerce saves account data.
         *
         * This is the right place for phone validation because WooCommerce will
         * stop the save when this hook adds errors.
         */
        add_action('woocommerce_save_account_details_errors', [static::class, 'validateFields'], 10, 2);

        /**
         * Rendered after WooCommerce password fields exist.
         * This does not change WooCommerce password behavior. It only clears a
         * browser-autofilled current password when the user did not enter a new
         * password.
         */
        add_action('woocommerce_edit_account_form_end', [static::class, 'renderPasswordAutofillGuard'], 30);

        add_action('woocommerce_save_account_details', [static::class, 'saveFields'], 10, 1);
    }

    /**
     * Add enctype for uploads and a data attribute used by the My Account CSS/JS.
     */
    public static function addMultipartEnctypeAndDataAttr(): void
    {
        echo ' enctype="multipart/form-data" data-sccc-edit-account="1"';
    }

    /**
     * Load CropperJS only on the WooCommerce Account Details screen.
     *
     * The cropper modal markup and wiring live in the My Account Blade layout.
     * This class only makes sure the required library is available.
     */
    public static function enqueueCropperAssets(): void
    {
        if (! function_exists('is_account_page') || ! function_exists('is_wc_endpoint_url')) {
            return;
        }

        if (! is_account_page() || ! is_wc_endpoint_url('edit-account')) {
            return;
        }

        wp_enqueue_style(
            'sccc-cropperjs',
            'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css',
            [],
            '1.6.2'
        );

        wp_enqueue_script(
            'sccc-cropperjs',
            'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js',
            [],
            '1.6.2',
            true
        );
    }

    /**
     * Determine whether the shared CropperJS modal should render.
     *
     * This is intentionally limited to WooCommerce → My Account → Account details.
     * The file inputs already live on that endpoint, and CropperJS is enqueued on
     * that endpoint by enqueueCropperAssets().
     */
    protected static function shouldRenderCropperModal(): bool
    {
        if (! is_user_logged_in()) {
            return false;
        }

        if (! function_exists('is_account_page') || ! function_exists('is_wc_endpoint_url')) {
            return false;
        }

        return is_account_page() && is_wc_endpoint_url('edit-account');
    }

    /**
     * Render the shared avatar / business logo crop modal near the end of <body>.
     *
     * Why this is rendered from wp_footer:
     * - The modal should not be trapped inside the account shell or #app.
     * - Fixed positioning can center against the real viewport.
     * - The same modal can serve the profile image and business logo inputs.
     *
     * This method only adds footer DOM/CSS/JS. It does not change the save logic,
     * upload handling, validation, field markup, or existing boot registration.
     */
    public static function renderCropperModal(): void
    {
        if (! static::shouldRenderCropperModal()) {
            return;
        }
        ?>
        <style id="sccc-account-cropper-modal-css">
            /* ======================================================================
               Account crop modal
               File: app/Support/Woo/AccountEditExtras.php
               ====================================================================== */

            html.sccc-avatar-modal-open {
                overflow: hidden;
                overscroll-behavior: none;
            }

            body.woocommerce-edit-account .sccc-avatar-modal {
                position: fixed !important;
                inset: 0 !important;
                z-index: 999999 !important;

                display: none;
                align-items: center;
                justify-content: center;

                width: 100vw;
                height: 100vh;
                height: 100dvh;

                padding: 16px !important;
                background: rgba(0, 0, 0, .60);
                backdrop-filter: blur(6px);
                -webkit-backdrop-filter: blur(6px);

                transform: none !important;
                overscroll-behavior: contain;
            }

            body.woocommerce-edit-account .sccc-avatar-modal.is-open {
                display: flex !important;
            }

            body.woocommerce-edit-account .sccc-avatar-modal__panel {
                position: relative !important;
                inset: auto !important;
                transform: none !important;

                width: min(820px, 92vw);
                max-height: calc(100dvh - 32px);
                overflow: hidden;

                border-radius: 18px;
                border: 1px solid rgba(255, 255, 255, .10);
                background: rgba(10, 12, 24, .92);
                box-shadow: 0 30px 80px rgba(0, 0, 0, .55);

                display: flex;
                flex-direction: column;
            }

            body.woocommerce-edit-account .sccc-avatar-modal__head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;

                padding: 14px 14px 10px;
                border-bottom: 1px solid rgba(255, 255, 255, .08);
            }

            body.woocommerce-edit-account .sccc-avatar-modal__title {
                margin: 0;
                font-size: 18px;
                font-weight: 950;
                letter-spacing: -.02em;
                color: #fff;
                line-height: 1.2;
            }

            body.woocommerce-edit-account .sccc-avatar-modal__sub {
                margin: 6px 0 0;
                font-size: 13px;
                font-weight: 800;
                color: rgba(255, 255, 255, .78);
            }

            body.woocommerce-edit-account .sccc-avatar-modal__close {
                appearance: none;

                width: 36px;
                height: 36px;

                display: inline-flex;
                align-items: center;
                justify-content: center;

                border-radius: 12px;
                border: 1px solid rgba(255, 255, 255, .10);
                background: rgba(255, 255, 255, .06);
                color: rgba(255, 255, 255, .92);

                cursor: pointer;
                font-weight: 950;
                line-height: 1;
            }

            body.woocommerce-edit-account .sccc-avatar-modal__close:hover {
                filter: brightness(1.08);
            }

            body.woocommerce-edit-account .sccc-avatar-modal__body {
                display: grid;
                grid-template-columns: 1fr 240px;
                gap: 12px;

                padding: 12px;
                min-height: 0;
            }

            body.woocommerce-edit-account .sccc-avatar-modal__stage {
                min-height: 0;

                display: flex;
                align-items: center;
                justify-content: center;

                overflow: hidden;
                border-radius: 16px;
                border: 1px solid rgba(255, 255, 255, .10);
                background: rgba(255, 255, 255, .04);
            }

            body.woocommerce-edit-account .sccc-avatar-modal__stage img {
                display: block;
                max-width: 100%;
            }

            body.woocommerce-edit-account .sccc-avatar-modal__side {
                min-height: 0;

                display: flex;
                flex-direction: column;
                gap: 12px;

                padding: 12px;
                border-radius: 16px;
                border: 1px solid rgba(255, 255, 255, .10);
                background: rgba(255, 255, 255, .04);
            }

            body.woocommerce-edit-account .sccc-avatar-modal__foot {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 10px;

                padding: 12px 14px 14px;
                border-top: 1px solid rgba(255, 255, 255, .08);
            }

            body.woocommerce-edit-account .sccc-avatar-btn {
                padding: 8px 12px;

                border-radius: 12px;
                border: 1px solid rgba(255, 255, 255, .12);
                background: rgba(255, 255, 255, .06);
                color: rgba(255, 255, 255, .92);

                cursor: pointer;
                user-select: none;

                font-weight: 900;
                font-size: 13px;
                line-height: 1;
            }

            body.woocommerce-edit-account .sccc-avatar-btn:hover {
                filter: brightness(1.06);
            }

            body.woocommerce-edit-account .sccc-avatar-btn--primary {
                border-color: rgba(19, 91, 236, .26);
                background: rgba(19, 91, 236, .95);
                box-shadow: 0 12px 26px rgba(19, 91, 236, .18);
                color: #fff;
            }

            body.woocommerce-edit-account .sccc-avatar-tip {
                font-size: 12px;
                font-weight: 800;
                color: rgba(255, 255, 255, .72);
                line-height: 1.35;
            }

            body.woocommerce-edit-account .sccc-avatar-preview {
                width: 84px;
                height: 84px;

                overflow: hidden;
                border-radius: 999px;
                border: 2px solid rgba(19, 91, 236, .35);
                background: rgba(255, 255, 255, .06);
            }

            body.woocommerce-edit-account .sccc-avatar-preview.is-square {
                border-radius: 16px;
            }

            @media (max-width: 860px) {
                body.woocommerce-edit-account .sccc-avatar-modal {
                    padding: 12px !important;
                }

                body.woocommerce-edit-account .sccc-avatar-modal__panel {
                    width: min(520px, 94vw);
                    max-height: calc(100dvh - 24px);
                }

                body.woocommerce-edit-account .sccc-avatar-modal__body {
                    grid-template-columns: 1fr;
                }

                body.woocommerce-edit-account .sccc-avatar-modal__side {
                    flex-direction: row;
                    flex-wrap: wrap;
                    align-items: flex-start;
                }

                body.woocommerce-edit-account .sccc-avatar-modal__foot {
                    justify-content: space-between;
                }
            }
        </style>

        <div class="sccc-avatar-modal" data-sccc-crop-modal="account-edit-extras" aria-hidden="true">
            <div class="sccc-avatar-modal__panel" role="dialog" aria-modal="true" aria-label="Crop image">
                <div class="sccc-avatar-modal__head">
                    <div style="min-width:0;">
                        <div class="sccc-avatar-modal__title" data-sccc-crop-title="account-edit-extras">Crop your image</div>
                        <div class="sccc-avatar-modal__sub" data-sccc-crop-sub="account-edit-extras">Adjust and save.</div>
                    </div>

                    <button type="button"
                            class="sccc-avatar-modal__close"
                            data-sccc-crop-close="account-edit-extras"
                            aria-label="Close">
                        ×
                    </button>
                </div>

                <div class="sccc-avatar-modal__body">
                    <div class="sccc-avatar-modal__stage">
                        <img data-sccc-crop-img="account-edit-extras" alt="Crop source" />
                    </div>

                    <aside class="sccc-avatar-modal__side" aria-label="Preview and tools">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
                            <div class="sccc-avatar-preview" data-sccc-crop-preview="account-edit-extras" aria-label="Preview"></div>

                            <div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
                                <button type="button" class="sccc-avatar-btn" data-sccc-zoom-out="account-edit-extras">Zoom out</button>
                                <button type="button" class="sccc-avatar-btn" data-sccc-zoom-in="account-edit-extras">Zoom in</button>
                            </div>
                        </div>

                        <div class="sccc-avatar-tip" data-sccc-crop-tip="account-edit-extras">
                            Tip: drag to reposition. Use zoom buttons for framing.
                        </div>
                    </aside>
                </div>

                <div class="sccc-avatar-modal__foot">
                    <button type="button" class="sccc-avatar-btn" data-sccc-crop-cancel="account-edit-extras">Cancel</button>
                    <button type="button" class="sccc-avatar-btn sccc-avatar-btn--primary" data-sccc-crop-apply="account-edit-extras">Use this image</button>
                </div>
            </div>
        </div>

        <script id="sccc-account-cropper-modal-js">
            (function () {
                function qs(sel, root) {
                    return (root || document).querySelector(sel);
                }

                function revoke(url) {
                    try {
                        if (url) {
                            URL.revokeObjectURL(url);
                        }
                    } catch (e) {}
                }

                function safeFocus(el) {
                    try {
                        if (el && typeof el.focus === 'function') {
                            el.focus({ preventScroll: true });
                        }
                    } catch (e) {}
                }

                var modal = qs('[data-sccc-crop-modal="account-edit-extras"]');

                if (!modal) {
                    return;
                }

                var modalImg = qs('[data-sccc-crop-img="account-edit-extras"]', modal);
                var previewEl = qs('[data-sccc-crop-preview="account-edit-extras"]', modal);

                var titleEl = qs('[data-sccc-crop-title="account-edit-extras"]', modal);
                var subEl = qs('[data-sccc-crop-sub="account-edit-extras"]', modal);
                var tipEl = qs('[data-sccc-crop-tip="account-edit-extras"]', modal);

                var btnZoomIn = qs('[data-sccc-zoom-in="account-edit-extras"]', modal);
                var btnZoomOut = qs('[data-sccc-zoom-out="account-edit-extras"]', modal);
                var btnCancel = qs('[data-sccc-crop-cancel="account-edit-extras"]', modal);
                var btnApply = qs('[data-sccc-crop-apply="account-edit-extras"]', modal);
                var btnClose = qs('[data-sccc-crop-close="account-edit-extras"]', modal);

                if (!modalImg || !btnApply) {
                    return;
                }

                var cropper = null;
                var objectUrl = null;
                var croppedUrl = null;
                var lastFocusEl = null;
                var ACTIVE = null;

                var sidebarAvatar = qs('.sccc-myaccount-mini .sccc-myaccount-avatar');

                var CONFIGS = [
                    {
                        key: 'profile',
                        inputId: 'sccc_profile_image',
                        filenamePillSelector: '[data-sccc-filename="1"]',
                        localPreviewSelector: '[data-sccc-avatar-row="1"] .sccc-editaccount-avatar',
                        extraPreviewEl: sidebarAvatar,
                        title: 'Crop your profile picture',
                        sub: 'Circle preview, saved as square 768×768.',
                        tip: 'Tip: drag to reposition. Use zoom buttons for framing.',
                        previewShape: 'circle',
                        outputSize: 768,
                        outputSuffix: 'cropped',
                        outputExt: 'jpg',
                        outputMime: 'image/jpeg',
                        outputQuality: 0.92
                    },
                    {
                        key: 'bizlogo',
                        inputId: 'sccc_business_logo',
                        filenamePillSelector: '[data-sccc-filename="bizlogo"]',
                        localPreviewSelector: '[data-sccc-business-logo-preview="1"]',
                        extraPreviewEl: null,
                        title: 'Crop your business logo',
                        sub: 'Square preview, saved as square 768×768.',
                        tip: 'Tip: keep important text away from edges.',
                        previewShape: 'square',
                        outputSize: 768,
                        outputSuffix: 'logo',
                        outputExt: 'jpg',
                        outputMime: 'image/jpeg',
                        outputQuality: 0.92
                    }
                ];

                function openModal() {
                    lastFocusEl = document.activeElement;

                    document.documentElement.classList.add('sccc-avatar-modal-open');
                    modal.classList.add('is-open');
                    modal.setAttribute('aria-hidden', 'false');

                    window.setTimeout(function () {
                        safeFocus(btnApply || btnCancel || btnClose);
                    }, 0);
                }

                function destroyCropper() {
                    if (cropper) {
                        cropper.destroy();
                        cropper = null;
                    }
                }

                function closeModal() {
                    destroyCropper();

                    if (document.activeElement && modal.contains(document.activeElement)) {
                        try {
                            document.activeElement.blur();
                        } catch (e) {}
                    }

                    safeFocus(lastFocusEl);
                    lastFocusEl = null;

                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                    document.documentElement.classList.remove('sccc-avatar-modal-open');
                }

                function setModalText(cfg) {
                    if (titleEl) {
                        titleEl.textContent = cfg.title || 'Crop your image';
                    }

                    if (subEl) {
                        subEl.textContent = cfg.sub || '';
                    }

                    if (tipEl) {
                        tipEl.textContent = cfg.tip || '';
                    }
                }

                function setPreviewShape(cfg) {
                    if (!previewEl) {
                        return;
                    }

                    previewEl.classList.remove('is-square');

                    if (cfg.previewShape === 'square') {
                        previewEl.classList.add('is-square');
                    }
                }

                function setFilename(cfg, name, hasFile) {
                    var pill = cfg.filenamePillSelector ? qs(cfg.filenamePillSelector) : null;

                    if (!pill) {
                        return;
                    }

                    pill.textContent = name || 'No file chosen';
                    pill.setAttribute('data-has-file', hasFile ? '1' : '0');
                }

                function setPreviewBackground(el, url) {
                    if (!el || !url) {
                        return;
                    }

                    el.style.backgroundImage = "url('" + url + "')";
                }

                function applyLivePreview(cfg, url) {
                    if (!url) {
                        return;
                    }

                    var localEl = cfg.localPreviewSelector ? qs(cfg.localPreviewSelector) : null;

                    if (localEl) {
                        setPreviewBackground(localEl, url);
                    }

                    if (cfg.extraPreviewEl) {
                        setPreviewBackground(cfg.extraPreviewEl, url);
                    }
                }

                function captureExistingBackgrounds(cfg) {
                    var out = {
                        local: '',
                        extra: ''
                    };

                    var localEl = cfg.localPreviewSelector ? qs(cfg.localPreviewSelector) : null;

                    if (localEl) {
                        out.local = localEl.style.backgroundImage || '';
                    }

                    if (cfg.extraPreviewEl) {
                        out.extra = cfg.extraPreviewEl.style.backgroundImage || '';
                    }

                    return out;
                }

                function restoreExistingBackgrounds(cfg, captured) {
                    var localEl = cfg.localPreviewSelector ? qs(cfg.localPreviewSelector) : null;

                    if (localEl && captured && typeof captured.local === 'string') {
                        localEl.style.backgroundImage = captured.local;
                    }

                    if (cfg.extraPreviewEl && captured && typeof captured.extra === 'string') {
                        cfg.extraPreviewEl.style.backgroundImage = captured.extra;
                    }
                }

                function bindCropInput(cfg) {
                    var input = document.getElementById(cfg.inputId);

                    if (!input) {
                        return;
                    }

                    if (input.getAttribute('data-sccc-crop-bound') === 'account-edit-extras') {
                        return;
                    }

                    input.setAttribute('data-sccc-crop-bound', 'account-edit-extras');

                    var capturedBg = captureExistingBackgrounds(cfg);

                    input.addEventListener('change', function () {
                        var file = input.files && input.files[0] ? input.files[0] : null;

                        if (!file) {
                            setFilename(cfg, 'No file chosen', false);
                            restoreExistingBackgrounds(cfg, capturedBg);
                            return;
                        }

                        setFilename(cfg, file.name, true);

                        revoke(objectUrl);
                        objectUrl = URL.createObjectURL(file);

                        if (!window.Cropper) {
                            applyLivePreview(cfg, objectUrl);
                            return;
                        }

                        ACTIVE = {
                            cfg: cfg,
                            input: input,
                            capturedBg: captureExistingBackgrounds(cfg)
                        };

                        setModalText(cfg);
                        setPreviewShape(cfg);

                        modalImg.src = objectUrl;
                        openModal();

                        modalImg.onload = function () {
                            destroyCropper();

                            cropper = new window.Cropper(modalImg, {
                                aspectRatio: 1,
                                viewMode: 1,
                                dragMode: 'move',
                                autoCropArea: 1,
                                background: false,
                                guides: false,
                                center: true,
                                highlight: false,
                                cropBoxMovable: false,
                                cropBoxResizable: false,
                                toggleDragModeOnDblclick: false,
                                preview: previewEl ? previewEl : undefined
                            });
                        };
                    });
                }

                function cancel() {
                    if (!ACTIVE || !ACTIVE.cfg || !ACTIVE.input) {
                        closeModal();
                        return;
                    }

                    ACTIVE.input.value = '';

                    setFilename(ACTIVE.cfg, 'No file chosen', false);
                    restoreExistingBackgrounds(ACTIVE.cfg, ACTIVE.capturedBg);

                    closeModal();
                    ACTIVE = null;
                }

                if (btnZoomIn) {
                    btnZoomIn.addEventListener('click', function () {
                        if (cropper) {
                            cropper.zoom(0.1);
                        }
                    });
                }

                if (btnZoomOut) {
                    btnZoomOut.addEventListener('click', function () {
                        if (cropper) {
                            cropper.zoom(-0.1);
                        }
                    });
                }

                if (btnCancel) {
                    btnCancel.addEventListener('click', cancel);
                }

                if (btnClose) {
                    btnClose.addEventListener('click', function (e) {
                        e.preventDefault();
                        cancel();
                    });
                }

                modal.addEventListener('click', function (e) {
                    if (e.target === modal) {
                        cancel();
                    }
                });

                document.addEventListener('keydown', function (e) {
                    if (modal.getAttribute('aria-hidden') === 'true') {
                        return;
                    }

                    if (e.key === 'Escape') {
                        e.preventDefault();
                        cancel();
                    }
                });

                btnApply.addEventListener('click', function () {
                    if (!ACTIVE || !ACTIVE.cfg || !ACTIVE.input) {
                        closeModal();
                        return;
                    }

                    if (!cropper) {
                        closeModal();
                        return;
                    }

                    var cfg = ACTIVE.cfg;
                    var input = ACTIVE.input;

                    var canvas = cropper.getCroppedCanvas({
                        width: cfg.outputSize || 768,
                        height: cfg.outputSize || 768,
                        imageSmoothingEnabled: true,
                        imageSmoothingQuality: 'high'
                    });

                    canvas.toBlob(function (blob) {
                        if (!blob) {
                            closeModal();
                            return;
                        }

                        var original = (input.files && input.files[0] && input.files[0].name)
                            ? input.files[0].name
                            : 'image.jpg';

                        var base = original.replace(/\.[^.]+$/, '');
                        var outName = base + '-' + (cfg.outputSuffix || 'cropped') + '.' + (cfg.outputExt || 'jpg');

                        var croppedFile = new File([blob], outName, {
                            type: cfg.outputMime || 'image/jpeg'
                        });

                        var dt = new DataTransfer();
                        dt.items.add(croppedFile);
                        input.files = dt.files;

                        setFilename(cfg, outName, true);

                        revoke(croppedUrl);
                        croppedUrl = URL.createObjectURL(blob);
                        applyLivePreview(cfg, croppedUrl);

                        closeModal();
                        ACTIVE = null;
                    }, cfg.outputMime || 'image/jpeg', (typeof cfg.outputQuality === 'number' ? cfg.outputQuality : 0.92));
                });

                CONFIGS.forEach(bindCropInput);
            })();
        </script>
        <?php
    }

    /**
     * Open the visual card around WooCommerce's native account fields.
     *
     * This is only presentation structure. WooCommerce still owns the native
     * first name, last name, display name, email, and password fields.
     */
    public static function openCoreCardWrapper(): void
    {
        if (! is_user_logged_in()) {
            return;
        }
        ?>
        <section class="sccc-editaccount-card sccc-editaccount-card--core" aria-label="Account details">
            <header class="sccc-editaccount-card__head">
                <h2 class="sccc-editaccount-card__title">Account details</h2>
                <p class="sccc-editaccount-card__sub">Update your name, email, and password.</p>
            </header>

            <div class="sccc-editaccount-grid">
        <?php
    }

    /**
     * Close the visual card around WooCommerce's native account fields.
     */
    public static function closeCoreCardWrapper(): void
    {
        if (! is_user_logged_in()) {
            return;
        }
        ?>
            </div>
        </section>
        <?php
    }

    /**
     * Open the save-actions card around WooCommerce's native save button.
     */
    public static function openActionsCardWrapper(): void
    {
        if (! is_user_logged_in()) {
            return;
        }
        ?>
        <section class="sccc-editaccount-card sccc-editaccount-card--actions" aria-label="Save changes">
            <header class="sccc-editaccount-card__head">
                <p class="sccc-editaccount-card__sub">When you’re done, save your changes.</p>
            </header>
        <?php
    }

    /**
     * Close the save-actions card.
     */
    public static function closeActionsCardWrapper(): void
    {
        if (! is_user_logged_in()) {
            return;
        }
        ?>
        </section>
        <?php
    }

    /**
     * Validate custom WooCommerce account fields before WooCommerce saves.
     *
     * Important:
     * - This method adds errors to the WP_Error object.
     * - WooCommerce then converts those errors into notices and blocks the save.
     * - The actual saving stays in saveFields().
     */
    public static function validateFields(\WP_Error $errors, $user): void
    {
        if (! is_user_logged_in() || ! static::isAccountDetailsPost()) {
            return;
        }

        $user_id = (int) get_current_user_id();

        if ($user_id <= 0 || ! MemberContext::isTrueMember($user_id)) {
            return;
        }

        $raw_phone = isset($_POST['sccc_member_phone'])
            ? sanitize_text_field(wp_unslash($_POST['sccc_member_phone']))
            : '';

        $raw_phone = trim($raw_phone);

        if ($raw_phone === '') {
            return;
        }

        $digits = static::normalizePhoneDigits($raw_phone);

        if (! static::isValidTenDigitPhone($digits)) {
            $errors->add(
                'sccc_member_phone_invalid',
                __('Please enter a valid 10-digit US phone number, like (713) 555-1234.', 'sccc')
            );
        }
    }

    /**
     * Prevent browser password-manager autofill from accidentally triggering
     * WooCommerce password-change validation.
     */
    public static function renderPasswordAutofillGuard(): void
    {
        if (! is_user_logged_in()) {
            return;
        }
        ?>
        <script>
            /**
             * SCCC password autofill guard
             * ------------------------------------------------------------------
             * WooCommerce password changes require:
             * - current password
             * - new password
             * - confirm new password
             *
             * Some password managers autofill only the current password field.
             * That makes WooCommerce think the member is changing their password.
             *
             * If the member did not enter a new password, clear the current
             * password field right before submit.
             */
            (function () {
                var form = document.querySelector('form.woocommerce-EditAccountForm.edit-account');
                if (!form || form.__scccPasswordGuard) return;

                form.__scccPasswordGuard = true;

                function fields() {
                    return {
                        current: form.querySelector('#password_current'),
                        next: form.querySelector('#password_1'),
                        confirm: form.querySelector('#password_2')
                    };
                }

                function clearCurrentIfNotChangingPassword() {
                    var f = fields();

                    if (!f.current || !f.next || !f.confirm) {
                        return;
                    }

                    var hasCurrent = String(f.current.value || '').length > 0;
                    var hasNew = String(f.next.value || '').length > 0;
                    var hasConfirm = String(f.confirm.value || '').length > 0;

                    if (hasCurrent && !hasNew && !hasConfirm) {
                        f.current.value = '';
                    }
                }

                // Password managers sometimes fill after DOM ready.
                window.setTimeout(clearCurrentIfNotChangingPassword, 350);
                window.setTimeout(clearCurrentIfNotChangingPassword, 1200);

                form.addEventListener('submit', clearCurrentIfNotChangingPassword);
            })();
        </script>
        <?php
    }

    /**
     * Render custom Space City Car Club account fields.
     */
    public static function renderFields(): void
    {
        if (! is_user_logged_in()) {
            return;
        }

        $user_id = (int) get_current_user_id();
        $acf_user_key = 'user_' . $user_id;
        $is_true_member = MemberContext::isTrueMember($user_id);
        $is_account_details_post = static::isAccountDetailsPost();

        /* ------------------------------------------------------------------
         * Profile image
         * ------------------------------------------------------------------ */
        $profile_image_id = (int) get_user_meta($user_id, 'sccc_profile_image_id', true);

        $avatar_url = '';
        if ($profile_image_id > 0) {
            $avatar_url = (string) wp_get_attachment_image_url($profile_image_id, 'thumbnail');
        }
        if ($avatar_url === '') {
            $avatar_url = (string) get_avatar_url($user_id, ['size' => 96]);
        }

        /* ------------------------------------------------------------------
         * Member profile fields
         * ------------------------------------------------------------------ */
        $birth_date = '';
        $is_veteran = 0;
        $veteran_type = '';
        $agency_branch = '';
        $member_phone = '';
        $member_phone_display = '';
        $hide_in_member_directory = 0;
        $member_website = '';
        $member_social_links = [];

        if ($is_true_member) {
            if (function_exists('get_field')) {
                $birth_date = (string) (get_field('birth_date', $acf_user_key) ?: '');
                $is_veteran = (int) ((bool) get_field('is_veteran_first_responder', $acf_user_key));
                $veteran_type = (string) (get_field('veteran_type', $acf_user_key) ?: '');
                $agency_branch = (string) (get_field('agency_branch', $acf_user_key) ?: '');
                $member_phone = (string) (get_field('sccc_member_phone', $acf_user_key) ?: '');
                $hide_in_member_directory = (int) ((bool) get_field('sccc_hide_in_member_directory', $acf_user_key));
                $member_website = (string) (get_field('sccc_member_website', $acf_user_key) ?: '');

                $acf_social = get_field('sccc_member_social_links', $acf_user_key);
                $member_social_links = is_array($acf_social) ? $acf_social : [];
            } else {
                $birth_date = (string) get_user_meta($user_id, 'birth_date', true);
                $is_veteran = (int) ((bool) get_user_meta($user_id, 'is_veteran_first_responder', true));
                $veteran_type = (string) get_user_meta($user_id, 'veteran_type', true);
                $agency_branch = (string) get_user_meta($user_id, 'agency_branch', true);
                $member_phone = (string) get_user_meta($user_id, 'sccc_member_phone', true);
                $hide_in_member_directory = (int) ((bool) get_user_meta($user_id, 'sccc_hide_in_member_directory', true));
                $member_website = (string) get_user_meta($user_id, 'sccc_member_website', true);

                $meta_social = get_user_meta($user_id, 'sccc_member_social_links', true);
                $member_social_links = is_array($meta_social) ? $meta_social : [];
            }

            $birth_date = static::normalizeDateYmdDash($birth_date);
            $veteran_type = static::normalizeVeteranType($veteran_type);
            $member_phone = static::normalizePhoneDigits($member_phone);
            $member_phone_display = static::formatPhoneForDisplay($member_phone);
            $hide_in_member_directory = $hide_in_member_directory === 1 ? 1 : 0;
            $member_website = esc_url_raw($member_website);
            $member_social_links = static::normalizeMemberSocialLinks($member_social_links);
        }

        /* ------------------------------------------------------------------
         * Preserve POST values when WooCommerce blocks save with validation.
         * ------------------------------------------------------------------ */
        if ($is_true_member && $is_account_details_post) {
            if (isset($_POST['sccc_birth_date'])) {
                $birth_date = static::normalizeDateYmdDash(
                    sanitize_text_field(wp_unslash($_POST['sccc_birth_date']))
                );
            }

            $is_veteran = ! empty($_POST['sccc_is_veteran']) ? 1 : 0;

            if (isset($_POST['sccc_veteran_type'])) {
                $veteran_type = static::normalizeVeteranType(
                    sanitize_text_field(wp_unslash($_POST['sccc_veteran_type']))
                );
            }

            if (isset($_POST['sccc_member_phone'])) {
                $posted_phone_raw = sanitize_text_field(wp_unslash($_POST['sccc_member_phone']));
                $posted_phone_digits = static::normalizePhoneDigits($posted_phone_raw);

                $member_phone = $posted_phone_digits;

                /**
                 * If the submitted phone is invalid, keep the user-entered value
                 * visible so they can correct it. If it is valid, show the clean
                 * formatted version.
                 */
                $member_phone_display = (
                    trim($posted_phone_raw) !== ''
                    && ! static::isValidTenDigitPhone($posted_phone_digits)
                )
                    ? $posted_phone_raw
                    : static::formatPhoneForDisplay($posted_phone_digits);
            }

            $hide_in_member_directory = ! empty($_POST['sccc_hide_in_member_directory']) ? 1 : 0;

            if (isset($_POST['sccc_member_website'])) {
                $member_website = esc_url_raw(wp_unslash($_POST['sccc_member_website']));
            }

            if (isset($_POST['sccc_member_social_links'])) {
                $member_social_links = static::normalizeMemberSocialLinks(
                    (array) wp_unslash($_POST['sccc_member_social_links'])
                );
            }

            $posted_branch_key = isset($_POST['sccc_branch_key'])
                ? sanitize_text_field(wp_unslash($_POST['sccc_branch_key']))
                : '';

            $posted_branch_other = isset($_POST['sccc_branch_other'])
                ? sanitize_text_field(wp_unslash($_POST['sccc_branch_other']))
                : '';

            if ($posted_branch_key === 'other') {
                $agency_branch = $posted_branch_other;
            }
        }

        $branch_options = static::branchOptions();

        $selected_branch_key = '';
        $other_branch_text = '';

        if ($agency_branch !== '') {
            $matched_key = array_search($agency_branch, $branch_options, true);
            if ($matched_key !== false) {
                $selected_branch_key = (string) $matched_key;
            } else {
                $selected_branch_key = 'other';
                $other_branch_text = $agency_branch;
            }
        }

        if ($is_true_member && $is_account_details_post && isset($_POST['sccc_branch_key'])) {
            $selected_branch_key = sanitize_text_field(wp_unslash($_POST['sccc_branch_key']));

            if ($selected_branch_key === 'other' && isset($_POST['sccc_branch_other'])) {
                $other_branch_text = sanitize_text_field(wp_unslash($_POST['sccc_branch_other']));
            }
        }

        /* ------------------------------------------------------------------
         * Business profile fields
         * ------------------------------------------------------------------ */
        $biz_opt_in = (int) get_user_meta($user_id, 'sccc_business_opt_in', true);

        $biz = [
            'name' => (string) get_user_meta($user_id, 'sccc_business_name', true),
            'description' => (string) get_user_meta($user_id, 'sccc_business_description', true),
            'website' => (string) get_user_meta($user_id, 'sccc_business_website', true),
            'phone' => (string) get_user_meta($user_id, 'sccc_business_phone', true),
            'email' => (string) get_user_meta($user_id, 'sccc_business_email', true),
            'city' => (string) get_user_meta($user_id, 'sccc_business_city', true),
        ];

        $biz_categories = get_user_meta($user_id, 'sccc_business_categories', true);
        $biz_services = get_user_meta($user_id, 'sccc_business_services', true);

        $biz_categories = is_array($biz_categories) ? $biz_categories : [];
        $biz_services = is_array($biz_services) ? $biz_services : [];

        if ($is_true_member && $is_account_details_post) {
            $biz_opt_in = ! empty($_POST['sccc_business_opt_in']) ? 1 : 0;

            $biz['name'] = isset($_POST['sccc_business_name'])
                ? sanitize_text_field(wp_unslash($_POST['sccc_business_name']))
                : $biz['name'];

            $biz['description'] = isset($_POST['sccc_business_description'])
                ? sanitize_textarea_field(wp_unslash($_POST['sccc_business_description']))
                : $biz['description'];

            $biz['website'] = isset($_POST['sccc_business_website'])
                ? esc_url_raw(wp_unslash($_POST['sccc_business_website']))
                : $biz['website'];

            $biz['phone'] = isset($_POST['sccc_business_phone'])
                ? sanitize_text_field(wp_unslash($_POST['sccc_business_phone']))
                : $biz['phone'];

            $biz['email'] = isset($_POST['sccc_business_email'])
                ? sanitize_email(wp_unslash($_POST['sccc_business_email']))
                : $biz['email'];

            $biz['city'] = isset($_POST['sccc_business_city'])
                ? sanitize_text_field(wp_unslash($_POST['sccc_business_city']))
                : $biz['city'];

            $biz_categories = isset($_POST['sccc_business_categories'])
                ? array_values(array_filter(array_map('sanitize_text_field', (array) wp_unslash($_POST['sccc_business_categories']))))
                : $biz_categories;

            $biz_services = isset($_POST['sccc_business_services'])
                ? array_values(array_filter(array_map('sanitize_text_field', (array) wp_unslash($_POST['sccc_business_services']))))
                : $biz_services;
        }

        $biz_category_choices = static::businessCategoryOptions();
        $biz_service_choices = static::businessServiceOptions();

        $biz_logo_id = (int) get_user_meta($user_id, 'sccc_business_logo_id', true);
        $biz_logo_url = '';
        if ($biz_logo_id > 0) {
            $biz_logo_url = (string) wp_get_attachment_image_url($biz_logo_id, 'thumbnail');
        }

        $member_social_options = static::memberSocialPlatformOptions();
        $member_social_rows = ! empty($member_social_links)
            ? $member_social_links
            : [
                [
                    'platform' => '',
                    'username' => '',
                    'url' => '',
                ],
            ];
        ?>

        <!-- ==========================================================
             PROFILE
             ========================================================== -->
        <section class="sccc-editaccount-card sccc-editaccount-card--extras" aria-label="Profile & member info">
            <header class="sccc-editaccount-card__head">
                <h2 class="sccc-editaccount-card__title">Profile</h2>
                <p class="sccc-editaccount-card__sub">Profile photo and member details.</p>
            </header>

            <div class="sccc-editaccount-grid">

                <div class="sccc-editaccount-grid__full sccc-editaccount-avatarrow" data-sccc-avatar-row="1">
                    <span
                        class="sccc-editaccount-avatar"
                        aria-hidden="true"
                        style="background-image:url('<?php echo esc_url($avatar_url); ?>')"
                    ></span>

                    <div class="sccc-editaccount-avatarrow__body">
                        <label for="sccc_profile_image" class="sccc-editaccount-label">Profile picture</label>

                        <div class="sccc-editaccount-upload">
                            <input
                                type="file"
                                name="sccc_profile_image"
                                id="sccc_profile_image"
                                class="sccc-editaccount-fileinput"
                                accept="image/*"
                            />

                            <label for="sccc_profile_image" class="sccc-editaccount-filebtn" role="button">
                                Choose
                            </label>

                            <span class="sccc-editaccount-filename" data-sccc-filename="1" data-has-file="0">
                                No file chosen
                            </span>
                        </div>

                        <small class="sccc-editaccount-help">
                            Optional. JPG/PNG/WebP recommended.
                            <span class="sccc-editaccount-help__em">Your photo updates after you click “Save changes”.</span>
                        </small>
                    </div>
                </div>

                <?php if ($is_true_member) : ?>
                    <p class="woocommerce-form-row form-row form-row-first sccc-editaccount-field">
                        <label for="sccc_birth_date" class="sccc-editaccount-label">Birthdate</label>
                        <input
                            type="date"
                            class="woocommerce-Input woocommerce-Input--text input-text"
                            name="sccc_birth_date"
                            id="sccc_birth_date"
                            value="<?php echo esc_attr($birth_date); ?>"
                        />
                    </p>

                    <p class="woocommerce-form-row form-row form-row-last sccc-editaccount-field">
                        <label for="sccc_member_phone" class="sccc-editaccount-label">Phone number</label>
                        <input
                            type="tel"
                            class="woocommerce-Input woocommerce-Input--text input-text"
                            name="sccc_member_phone"
                            id="sccc_member_phone"
                            value="<?php echo esc_attr($member_phone_display); ?>"
                            placeholder="Example: (713) 555-1234"
                            autocomplete="tel"
                            inputmode="tel"
                        />
                        <small class="sccc-editaccount-help">
                            Club/admin use. Enter a 10-digit US phone number. It is not shown in the Member Directory by default.
                        </small>
                    </p>

                    <p class="woocommerce-form-row form-row form-row-first sccc-editaccount-field">
                        <span class="sccc-editaccount-label">Service / Responder Status</span>

                        <label class="sccc-editaccount-check" for="sccc_is_veteran">
                            <input type="checkbox" name="sccc_is_veteran" id="sccc_is_veteran" value="1" <?php checked((int) $is_veteran, 1); ?> />
                            <span>Yes</span>
                        </label>
                    </p>

                    <p class="woocommerce-form-row form-row form-row-last sccc-editaccount-field">
                        <span class="sccc-editaccount-label">Member Directory</span>

                        <label class="sccc-editaccount-check" for="sccc_hide_in_member_directory">
                            <input type="checkbox" name="sccc_hide_in_member_directory" id="sccc_hide_in_member_directory" value="1" <?php checked((int) $hide_in_member_directory, 1); ?> />
                            <span>Don’t show me in the Member Directory</span>
                        </label>

                        <small class="sccc-editaccount-help">
                            Active members are visible by default unless this is checked.
                        </small>
                    </p>

                    <div
                        id="sccc_veteran_details_wrap"
                        class="sccc-editaccount-grid__full sccc-editaccount-cond"
                        style="display: <?php echo ((int) $is_veteran === 1) ? 'block' : 'none'; ?>;"
                    >
                        <div class="sccc-editaccount-subgrid">
                            <p class="woocommerce-form-row form-row form-row-first sccc-editaccount-field">
                                <label for="sccc_veteran_type" class="sccc-editaccount-label">Type <span class="sccc-editaccount-muted">(required)</span></label>
                                <select class="woocommerce-Input woocommerce-Input--select input-text" name="sccc_veteran_type" id="sccc_veteran_type">
                                    <option value="">Select…</option>
                                    <option value="veteran" <?php selected($veteran_type, 'veteran'); ?>>Veteran</option>
                                    <option value="first_responder" <?php selected($veteran_type, 'first_responder'); ?>>First Responder</option>
                                    <option value="both" <?php selected($veteran_type, 'both'); ?>>Both</option>
                                </select>
                            </p>

                            <p class="woocommerce-form-row form-row form-row-last sccc-editaccount-field">
                                <label for="sccc_branch_key" class="sccc-editaccount-label">Branch / Service <span class="sccc-editaccount-muted">(required)</span></label>
                                <select class="woocommerce-Input woocommerce-Input--select input-text" name="sccc_branch_key" id="sccc_branch_key">
                                    <option value="">Select…</option>

                                    <?php foreach ($branch_options as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($selected_branch_key, (string) $key); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>

                                    <option value="other" <?php selected($selected_branch_key, 'other'); ?>>Other</option>
                                </select>

                                <small class="sccc-editaccount-help">If you’re not military, choose “Other”.</small>
                            </p>

                            <p class="woocommerce-form-row form-row form-row-wide sccc-editaccount-field" id="sccc_branch_other_wrap" style="display: <?php echo ($selected_branch_key === 'other') ? 'block' : 'none'; ?>;">
                                <label for="sccc_branch_other" class="sccc-editaccount-label">Other Branch / Agency <span class="sccc-editaccount-muted">(required if Other)</span></label>
                                <input
                                    type="text"
                                    class="woocommerce-Input woocommerce-Input--text input-text"
                                    name="sccc_branch_other"
                                    id="sccc_branch_other"
                                    value="<?php echo esc_attr($other_branch_text); ?>"
                                    placeholder="Example: Houston Police Department, EMS, etc…"
                                />
                            </p>
                        </div>
                    </div>

                    <p class="woocommerce-form-row form-row form-row-wide sccc-editaccount-field">
                        <label for="sccc_member_website" class="sccc-editaccount-label">Website</label>
                        <input
                            type="url"
                            class="woocommerce-Input woocommerce-Input--text input-text"
                            name="sccc_member_website"
                            id="sccc_member_website"
                            value="<?php echo esc_attr($member_website); ?>"
                            placeholder="https://"
                        />
                        <small class="sccc-editaccount-help">
                            Optional. This is your member profile website and is separate from the Business Directory website.
                        </small>
                    </p>

                    <div class="sccc-editaccount-grid__full sccc-editaccount-field" data-sccc-social-repeater="1">
                        <label class="sccc-editaccount-label">Social Media</label>

                        <div data-sccc-social-list="1">
                            <?php foreach ($member_social_rows as $index => $row) : ?>
                                <?php
                                    $row_platform = isset($row['platform']) ? (string) $row['platform'] : '';
                                    $row_username = isset($row['username']) ? (string) $row['username'] : '';
                                    $row_url = isset($row['url']) ? (string) $row['url'] : '';
                                ?>
                                <div
                                    data-sccc-social-row="1"
                                    style="display:grid; grid-template-columns:minmax(0, .75fr) minmax(0, 1fr) minmax(0, 1.35fr) auto; gap:10px; align-items:end; margin-top:10px;"
                                >
                                    <p class="woocommerce-form-row form-row sccc-editaccount-field" style="margin:0;">
                                        <label class="sccc-editaccount-label" for="sccc_member_social_platform_<?php echo esc_attr((string) $index); ?>">Platform</label>
                                        <select
                                            class="woocommerce-Input woocommerce-Input--select input-text"
                                            name="sccc_member_social_links[<?php echo esc_attr((string) $index); ?>][platform]"
                                            id="sccc_member_social_platform_<?php echo esc_attr((string) $index); ?>"
                                        >
                                            <option value="">Select…</option>
                                            <?php foreach ($member_social_options as $platform_key => $platform_label) : ?>
                                                <option value="<?php echo esc_attr($platform_key); ?>" <?php selected($row_platform, (string) $platform_key); ?>>
                                                    <?php echo esc_html($platform_label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </p>

                                    <p class="woocommerce-form-row form-row sccc-editaccount-field" style="margin:0;">
                                        <label class="sccc-editaccount-label" for="sccc_member_social_username_<?php echo esc_attr((string) $index); ?>">Username</label>
                                        <input
                                            type="text"
                                            class="woocommerce-Input woocommerce-Input--text input-text"
                                            name="sccc_member_social_links[<?php echo esc_attr((string) $index); ?>][username]"
                                            id="sccc_member_social_username_<?php echo esc_attr((string) $index); ?>"
                                            value="<?php echo esc_attr($row_username); ?>"
                                            placeholder="username"
                                        />
                                    </p>

                                    <p class="woocommerce-form-row form-row sccc-editaccount-field" style="margin:0;">
                                        <label class="sccc-editaccount-label" for="sccc_member_social_url_<?php echo esc_attr((string) $index); ?>">Profile URL</label>
                                        <input
                                            type="url"
                                            class="woocommerce-Input woocommerce-Input--text input-text"
                                            name="sccc_member_social_links[<?php echo esc_attr((string) $index); ?>][url]"
                                            id="sccc_member_social_url_<?php echo esc_attr((string) $index); ?>"
                                            value="<?php echo esc_attr($row_url); ?>"
                                            placeholder="https://"
                                        />
                                    </p>

                                    <button
                                        type="button"
                                        class="sccc-editaccount-filebtn"
                                        data-sccc-social-remove="1"
                                        style="height:42px;"
                                    >
                                        Remove
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button
                            type="button"
                            class="sccc-editaccount-filebtn"
                            data-sccc-social-add="1"
                            style="margin-top:12px;"
                        >
                            Add Social Profile
                        </button>

                        <small class="sccc-editaccount-help">
                            Optional. Approved platforms: Facebook, Instagram, X, TikTok, and YouTube.
                            Add a username/handle. If Profile URL is blank, we’ll build the common profile URL where possible.
                        </small>

                        <template data-sccc-social-template="1">
                            <div
                                data-sccc-social-row="1"
                                style="display:grid; grid-template-columns:minmax(0, .75fr) minmax(0, 1fr) minmax(0, 1.35fr) auto; gap:10px; align-items:end; margin-top:10px;"
                            >
                                <p class="woocommerce-form-row form-row sccc-editaccount-field" style="margin:0;">
                                    <label class="sccc-editaccount-label" for="sccc_member_social_platform___INDEX__">Platform</label>
                                    <select
                                        class="woocommerce-Input woocommerce-Input--select input-text"
                                        name="sccc_member_social_links[__INDEX__][platform]"
                                        id="sccc_member_social_platform___INDEX__"
                                    >
                                        <option value="">Select…</option>
                                        <?php foreach ($member_social_options as $platform_key => $platform_label) : ?>
                                            <option value="<?php echo esc_attr($platform_key); ?>">
                                                <?php echo esc_html($platform_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>

                                <p class="woocommerce-form-row form-row sccc-editaccount-field" style="margin:0;">
                                    <label class="sccc-editaccount-label" for="sccc_member_social_username___INDEX__">Username</label>
                                    <input
                                        type="text"
                                        class="woocommerce-Input woocommerce-Input--text input-text"
                                        name="sccc_member_social_links[__INDEX__][username]"
                                        id="sccc_member_social_username___INDEX__"
                                        value=""
                                        placeholder="username"
                                    />
                                </p>

                                <p class="woocommerce-form-row form-row sccc-editaccount-field" style="margin:0;">
                                    <label class="sccc-editaccount-label" for="sccc_member_social_url___INDEX__">Profile URL</label>
                                    <input
                                        type="url"
                                        class="woocommerce-Input woocommerce-Input--text input-text"
                                        name="sccc_member_social_links[__INDEX__][url]"
                                        id="sccc_member_social_url___INDEX__"
                                        value=""
                                        placeholder="https://"
                                    />
                                </p>

                                <button
                                    type="button"
                                    class="sccc-editaccount-filebtn"
                                    data-sccc-social-remove="1"
                                    style="height:42px;"
                                >
                                    Remove
                                </button>
                            </div>
                        </template>

                        <script>
                            /**
                             * Member social media repeater.
                             * Stores platform + username + URL rows so frontend
                             * templates can render icon + @username links.
                             */
                            (function () {
                                var root = document.querySelector('[data-sccc-social-repeater="1"]');
                                if (!root || root.__scccSocialInit) return;
                                root.__scccSocialInit = true;

                                var list = root.querySelector('[data-sccc-social-list="1"]');
                                var tpl = root.querySelector('[data-sccc-social-template="1"]');
                                var add = root.querySelector('[data-sccc-social-add="1"]');
                                var maxRows = 15;

                                if (!list || !tpl || !add) return;

                                function rows() {
                                    return Array.prototype.slice.call(list.querySelectorAll('[data-sccc-social-row="1"]'));
                                }

                                function syncAddButton() {
                                    add.disabled = rows().length >= maxRows;
                                    add.style.opacity = add.disabled ? '.55' : '1';
                                    add.style.cursor = add.disabled ? 'not-allowed' : 'pointer';
                                }

                                function nextIndex() {
                                    return String(Date.now());
                                }

                                function addRow() {
                                    if (rows().length >= maxRows) return;

                                    var html = tpl.innerHTML.replaceAll('__INDEX__', nextIndex());
                                    var wrap = document.createElement('div');
                                    wrap.innerHTML = html.trim();

                                    if (wrap.firstElementChild) {
                                        list.appendChild(wrap.firstElementChild);
                                    }

                                    syncAddButton();
                                }

                                function removeRow(button) {
                                    var row = button.closest('[data-sccc-social-row="1"]');
                                    if (!row) return;

                                    row.remove();

                                    if (rows().length === 0) {
                                        addRow();
                                    }

                                    syncAddButton();
                                }

                                add.addEventListener('click', addRow);

                                root.addEventListener('click', function (event) {
                                    var button = event.target.closest('[data-sccc-social-remove="1"]');
                                    if (!button) return;
                                    event.preventDefault();
                                    removeRow(button);
                                });

                                syncAddButton();
                            })();
                        </script>
                    </div>
                <?php endif; ?>

            </div>

            <script>
                /**
                 * Service/Responder conditional UI.
                 */
                (function () {
                    var vetCheckbox  = document.getElementById('sccc_is_veteran');
                    var detailsWrap  = document.getElementById('sccc_veteran_details_wrap');
                    var branchSelect = document.getElementById('sccc_branch_key');
                    var otherWrap    = document.getElementById('sccc_branch_other_wrap');

                    if (!vetCheckbox || !detailsWrap) return;

                    var syncDetails = function () {
                        detailsWrap.style.display = vetCheckbox.checked ? 'block' : 'none';
                    };

                    var syncOther = function () {
                        if (!branchSelect || !otherWrap) return;
                        otherWrap.style.display = (branchSelect.value === 'other') ? 'block' : 'none';
                    };

                    vetCheckbox.addEventListener('change', syncDetails);
                    if (branchSelect) branchSelect.addEventListener('change', syncOther);

                    syncDetails();
                    syncOther();
                })();
            </script>
        </section>

        <?php if ($is_true_member) : ?>
            <!-- ==========================================================
                 BUSINESS
                 ========================================================== -->
            <section class="sccc-editaccount-card sccc-editaccount-card--business" aria-label="Business Directory">
                <header class="sccc-editaccount-card__head">
                    <h2 class="sccc-editaccount-card__title">Business</h2>
                    <p class="sccc-editaccount-card__sub">Optional. Add one business listing for the member directory.</p>
                </header>

                <div class="sccc-editaccount-grid">

                    <div class="sccc-editaccount-grid__full sccc-editaccount-field">
                        <span class="sccc-editaccount-label">Business Directory</span>

                        <label class="sccc-editaccount-check" for="sccc_business_opt_in">
                            <input type="checkbox" name="sccc_business_opt_in" id="sccc_business_opt_in" value="1" <?php checked($biz_opt_in, 1); ?> />
                            <span>Feature my business in the directory</span>
                        </label>

                        <small class="sccc-editaccount-help">
                            If enabled, your listing is eligible to be shown automatically.
                        </small>
                    </div>

                    <div
                        id="sccc_business_wrap"
                        class="sccc-editaccount-grid__full sccc-editaccount-cond"
                        style="display: <?php echo ($biz_opt_in === 1) ? 'block' : 'none'; ?>;"
                    >
                        <div class="sccc-editaccount-subgrid">

                            <div class="sccc-editaccount-grid__full sccc-editaccount-avatarrow">
                                <span
                                    class="sccc-editaccount-avatar"
                                    data-sccc-business-logo-preview="1"
                                    aria-hidden="true"
                                    style="background-image:url('<?php echo esc_url($biz_logo_url); ?>')"
                                ></span>

                                <div class="sccc-editaccount-avatarrow__body">
                                    <label for="sccc_business_logo" class="sccc-editaccount-label">Business logo</label>

                                    <div class="sccc-editaccount-upload">
                                        <input
                                            type="file"
                                            name="sccc_business_logo"
                                            id="sccc_business_logo"
                                            class="sccc-editaccount-fileinput"
                                            accept="image/*"
                                        />

                                        <label for="sccc_business_logo" class="sccc-editaccount-filebtn" role="button">
                                            Choose
                                        </label>

                                        <span class="sccc-editaccount-filename" data-sccc-filename="bizlogo" data-has-file="0">
                                            No file chosen
                                        </span>
                                    </div>

                                    <small class="sccc-editaccount-help">
                                        Optional. Square works best.
                                        <span class="sccc-editaccount-help__em">Your logo updates after you click “Save changes”.</span>
                                    </small>
                                </div>
                            </div>

                            <p class="woocommerce-form-row form-row form-row-first sccc-editaccount-field">
                                <label for="sccc_business_name" class="sccc-editaccount-label">Business name <span class="sccc-editaccount-muted">(required)</span></label>
                                <input
                                    type="text"
                                    class="woocommerce-Input woocommerce-Input--text input-text"
                                    name="sccc_business_name"
                                    id="sccc_business_name"
                                    value="<?php echo esc_attr($biz['name']); ?>"
                                    placeholder="Example: Joe’s Auto Detailing"
                                />
                            </p>

                            <p class="woocommerce-form-row form-row form-row-last sccc-editaccount-field">
                                <label for="sccc_business_city" class="sccc-editaccount-label">City</label>
                                <input
                                    type="text"
                                    class="woocommerce-Input woocommerce-Input--text input-text"
                                    name="sccc_business_city"
                                    id="sccc_business_city"
                                    value="<?php echo esc_attr($biz['city']); ?>"
                                    placeholder="Example: Houston"
                                />
                            </p>

                            <p class="woocommerce-form-row form-row form-row-wide sccc-editaccount-field">
                                <label for="sccc_business_description" class="sccc-editaccount-label">Short description</label>
                                <textarea
                                    class="woocommerce-Input woocommerce-Input--textarea input-text"
                                    name="sccc_business_description"
                                    id="sccc_business_description"
                                    rows="3"
                                    placeholder="What do you do? What should members know?"
                                ><?php echo esc_textarea($biz['description']); ?></textarea>
                            </p>

                            <p class="woocommerce-form-row form-row form-row-first sccc-editaccount-field">
                                <label for="sccc_business_website" class="sccc-editaccount-label">Website</label>
                                <input
                                    type="url"
                                    class="woocommerce-Input woocommerce-Input--text input-text"
                                    name="sccc_business_website"
                                    id="sccc_business_website"
                                    value="<?php echo esc_attr($biz['website']); ?>"
                                    placeholder="https://"
                                />
                            </p>

                            <p class="woocommerce-form-row form-row form-row-last sccc-editaccount-field">
                                <label for="sccc_business_phone" class="sccc-editaccount-label">Phone</label>
                                <input
                                    type="tel"
                                    class="woocommerce-Input woocommerce-Input--text input-text"
                                    name="sccc_business_phone"
                                    id="sccc_business_phone"
                                    value="<?php echo esc_attr($biz['phone']); ?>"
                                    placeholder="Example: (713) 555-1234"
                                />
                            </p>

                            <p class="woocommerce-form-row form-row form-row-first sccc-editaccount-field">
                                <label for="sccc_business_email" class="sccc-editaccount-label">Email</label>
                                <input
                                    type="email"
                                    class="woocommerce-Input woocommerce-Input--text input-text"
                                    name="sccc_business_email"
                                    id="sccc_business_email"
                                    value="<?php echo esc_attr($biz['email']); ?>"
                                    placeholder="Example: hello@yourbiz.com"
                                />
                            </p>

                            <p class="woocommerce-form-row form-row form-row-last sccc-editaccount-field">
                                <label for="sccc_business_categories" class="sccc-editaccount-label">Industry <span class="sccc-editaccount-muted">(pick one or more)</span></label>
                                <select
                                    class="woocommerce-Input woocommerce-Input--select input-text"
                                    name="sccc_business_categories[]"
                                    id="sccc_business_categories"
                                    multiple
                                >
                                    <?php foreach ($biz_category_choices as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php echo in_array((string) $key, $biz_categories, true) ? 'selected' : ''; ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="sccc-editaccount-help">Hold Ctrl (Windows) / Cmd (Mac) to select multiple.</small>
                            </p>

                            <p class="woocommerce-form-row form-row form-row-wide sccc-editaccount-field">
                                <label for="sccc_business_services" class="sccc-editaccount-label">Services Offered <span class="sccc-editaccount-muted">(pick one or more)</span></label>
                                <select
                                    class="woocommerce-Input woocommerce-Input--select input-text"
                                    name="sccc_business_services[]"
                                    id="sccc_business_services"
                                    multiple
                                >
                                    <?php foreach ($biz_service_choices as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php echo in_array((string) $key, $biz_services, true) ? 'selected' : ''; ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="sccc-editaccount-help">These drive filtering/search in the directory later.</small>
                            </p>
                        </div>
                    </div>

                </div>

                <script>
                    /**
                     * Business conditional UI.
                     */
                    (function () {
                        var optIn = document.getElementById('sccc_business_opt_in');
                        var wrap  = document.getElementById('sccc_business_wrap');

                        if (!optIn || !wrap) return;

                        var sync = function () {
                            wrap.style.display = optIn.checked ? 'block' : 'none';
                        };

                        optIn.addEventListener('change', sync);
                        sync();
                    })();
                </script>
            </section>
        <?php endif; ?>
        <?php
    }

    /**
     * Save custom fields when WooCommerce saves account details.
     *
     * Phone validation happens earlier in validateFields().
     * If validation fails, WooCommerce does not run this save callback.
     */
    public static function saveFields(int $user_id): void
    {
        $user_id = (int) $user_id;

        if ($user_id <= 0 || ! is_user_logged_in() || $user_id !== (int) get_current_user_id()) {
            return;
        }

        /* ------------------------------------------------------------------
         * Profile avatar
         * ------------------------------------------------------------------ */
        if (! empty($_FILES['sccc_profile_image']) && ! empty($_FILES['sccc_profile_image']['name'])) {
            $attachment_id = static::handleImageUpload('sccc_profile_image');

            if (is_wp_error($attachment_id)) {
                wc_add_notice($attachment_id->get_error_message(), 'error');
            } elseif (is_int($attachment_id) && $attachment_id > 0) {
                update_user_meta($user_id, 'sccc_profile_image_id', $attachment_id);
            }
        }

        if (! MemberContext::isTrueMember($user_id)) {
            return;
        }

        $acf_user_key = 'user_' . $user_id;

        /* ------------------------------------------------------------------
         * Member profile fields
         * ------------------------------------------------------------------ */
        $birth = isset($_POST['sccc_birth_date'])
            ? sanitize_text_field(wp_unslash($_POST['sccc_birth_date']))
            : '';
        $birth = static::normalizeDateYmdDash($birth);

        $member_phone_raw = isset($_POST['sccc_member_phone'])
            ? sanitize_text_field(wp_unslash($_POST['sccc_member_phone']))
            : '';

        $member_phone = static::normalizePhoneDigits($member_phone_raw);

        /**
         * Defensive safety:
         * validateFields() should block invalid phone values before saveFields()
         * runs. If this method is ever called directly, only store valid 10-digit
         * values or an empty string.
         */
        if ($member_phone !== '' && ! static::isValidTenDigitPhone($member_phone)) {
            $member_phone = '';
        }

        $hide_in_directory = ! empty($_POST['sccc_hide_in_member_directory']) ? 1 : 0;

        $member_website = isset($_POST['sccc_member_website'])
            ? esc_url_raw(wp_unslash($_POST['sccc_member_website']))
            : '';

        $member_social_raw = isset($_POST['sccc_member_social_links'])
            ? (array) wp_unslash($_POST['sccc_member_social_links'])
            : [];

        $member_social_links = static::normalizeMemberSocialLinks($member_social_raw);

        if (function_exists('update_field')) {
            update_field('birth_date', $birth, $acf_user_key);
            update_field('sccc_member_phone', $member_phone, $acf_user_key);
            update_field('sccc_hide_in_member_directory', $hide_in_directory, $acf_user_key);
            update_field('sccc_member_website', $member_website, $acf_user_key);
            update_field('sccc_member_social_links', $member_social_links, $acf_user_key);
        } else {
            update_user_meta($user_id, 'birth_date', $birth);
            update_user_meta($user_id, 'sccc_member_phone', $member_phone);
            update_user_meta($user_id, 'sccc_hide_in_member_directory', $hide_in_directory);
            update_user_meta($user_id, 'sccc_member_website', $member_website);
            update_user_meta($user_id, 'sccc_member_social_links', $member_social_links);
        }

        /* ------------------------------------------------------------------
         * Service / Responder
         * ------------------------------------------------------------------ */
        $is_vet = ! empty($_POST['sccc_is_veteran']) ? 1 : 0;

        $vet_type_raw = isset($_POST['sccc_veteran_type'])
            ? sanitize_text_field(wp_unslash($_POST['sccc_veteran_type']))
            : '';
        $vet_type = static::normalizeVeteranType($vet_type_raw);

        $branch_key = isset($_POST['sccc_branch_key'])
            ? sanitize_text_field(wp_unslash($_POST['sccc_branch_key']))
            : '';
        $branch_other = isset($_POST['sccc_branch_other'])
            ? sanitize_text_field(wp_unslash($_POST['sccc_branch_other']))
            : '';
        $branch_other = trim($branch_other);

        $branch_options = static::branchOptions();

        $resolved_branch = '';
        if ($branch_key !== '') {
            if ($branch_key === 'other') {
                $resolved_branch = $branch_other;
            } elseif (isset($branch_options[$branch_key])) {
                $resolved_branch = (string) $branch_options[$branch_key];
            }
        }

        if ($is_vet === 1) {
            if ($vet_type === '') {
                wc_add_notice(__('Please select a Veteran / First Responder type.', 'sccc'), 'error');
            }

            if ($branch_key === '') {
                wc_add_notice(__('Please select a Branch / Service.', 'sccc'), 'error');
            } elseif ($branch_key === 'other' && $resolved_branch === '') {
                wc_add_notice(__('Please enter your Branch / Agency.', 'sccc'), 'error');
            }
        }

        if (function_exists('update_field')) {
            update_field('is_veteran_first_responder', (int) $is_vet, $acf_user_key);
            update_field('veteran_type', $is_vet ? $vet_type : '', $acf_user_key);
            update_field('agency_branch', $is_vet ? $resolved_branch : '', $acf_user_key);
        } else {
            update_user_meta($user_id, 'is_veteran_first_responder', (int) $is_vet);
            update_user_meta($user_id, 'veteran_type', $is_vet ? $vet_type : '');
            update_user_meta($user_id, 'agency_branch', $is_vet ? $resolved_branch : '');
        }

        /* ------------------------------------------------------------------
         * Business profile
         * ------------------------------------------------------------------ */
        $opt_in = ! empty($_POST['sccc_business_opt_in']) ? 1 : 0;
        update_user_meta($user_id, 'sccc_business_opt_in', (int) $opt_in);

        if ($opt_in !== 1) {
            delete_user_meta($user_id, 'sccc_business_logo_id');
            delete_user_meta($user_id, 'sccc_business_name');
            delete_user_meta($user_id, 'sccc_business_description');
            delete_user_meta($user_id, 'sccc_business_website');
            delete_user_meta($user_id, 'sccc_business_phone');
            delete_user_meta($user_id, 'sccc_business_email');
            delete_user_meta($user_id, 'sccc_business_city');
            delete_user_meta($user_id, 'sccc_business_categories');
            delete_user_meta($user_id, 'sccc_business_services');
            delete_user_meta($user_id, 'sccc_business_index');
            return;
        }

        if (! empty($_FILES['sccc_business_logo']) && ! empty($_FILES['sccc_business_logo']['name'])) {
            $logo_attachment_id = static::handleImageUpload('sccc_business_logo');

            if (is_wp_error($logo_attachment_id)) {
                wc_add_notice($logo_attachment_id->get_error_message(), 'error');
            } elseif (is_int($logo_attachment_id) && $logo_attachment_id > 0) {
                update_user_meta($user_id, 'sccc_business_logo_id', $logo_attachment_id);
            }
        }

        $name = isset($_POST['sccc_business_name'])
            ? sanitize_text_field(wp_unslash($_POST['sccc_business_name']))
            : '';
        $city = isset($_POST['sccc_business_city'])
            ? sanitize_text_field(wp_unslash($_POST['sccc_business_city']))
            : '';
        $desc = isset($_POST['sccc_business_description'])
            ? sanitize_textarea_field(wp_unslash($_POST['sccc_business_description']))
            : '';

        $website = isset($_POST['sccc_business_website'])
            ? esc_url_raw(wp_unslash($_POST['sccc_business_website']))
            : '';
        $phone = isset($_POST['sccc_business_phone'])
            ? sanitize_text_field(wp_unslash($_POST['sccc_business_phone']))
            : '';
        $email = isset($_POST['sccc_business_email'])
            ? sanitize_email(wp_unslash($_POST['sccc_business_email']))
            : '';

        $cats = isset($_POST['sccc_business_categories'])
            ? (array) wp_unslash($_POST['sccc_business_categories'])
            : [];
        $srvs = isset($_POST['sccc_business_services'])
            ? (array) wp_unslash($_POST['sccc_business_services'])
            : [];

        $cats = array_values(array_filter(array_map('sanitize_text_field', $cats)));
        $srvs = array_values(array_filter(array_map('sanitize_text_field', $srvs)));

        $allowed_cats = array_keys(static::businessCategoryOptions());
        $allowed_srvs = array_keys(static::businessServiceOptions());

        $cats = array_values(array_intersect($cats, $allowed_cats));
        $srvs = array_values(array_intersect($srvs, $allowed_srvs));

        if (trim($name) === '') {
            wc_add_notice(__('Please enter your Business name (required).', 'sccc'), 'error');
        }

        update_user_meta($user_id, 'sccc_business_name', $name);
        update_user_meta($user_id, 'sccc_business_city', $city);
        update_user_meta($user_id, 'sccc_business_description', $desc);
        update_user_meta($user_id, 'sccc_business_website', $website);
        update_user_meta($user_id, 'sccc_business_phone', $phone);
        update_user_meta($user_id, 'sccc_business_email', $email);
        update_user_meta($user_id, 'sccc_business_categories', $cats);
        update_user_meta($user_id, 'sccc_business_services', $srvs);

        $cat_labels = [];
        foreach ($cats as $k) {
            $cat_labels[] = static::businessCategoryOptions()[$k] ?? $k;
        }

        $srv_labels = [];
        foreach ($srvs as $k) {
            $srv_labels[] = static::businessServiceOptions()[$k] ?? $k;
        }

        $index_parts = array_filter([
            $name,
            $city,
            $desc,
            implode(' ', $cat_labels),
            implode(' ', $srv_labels),
        ]);

        $index = strtolower(trim(preg_replace('/\s+/', ' ', implode(' | ', $index_parts))));
        update_user_meta($user_id, 'sccc_business_index', $index);
    }

    /**
     * Detect the WooCommerce account-details POST.
     */
    protected static function isAccountDetailsPost(): bool
    {
        if (empty($_POST)) {
            return false;
        }

        $action = isset($_POST['action'])
            ? sanitize_text_field(wp_unslash($_POST['action']))
            : '';

        return $action === 'save_account_details';
    }

    /**
     * Upload an image field into the WordPress Media Library.
     */
    protected static function handleImageUpload(string $file_key)
    {
        if (! function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $type = ! empty($_FILES[$file_key]['type']) ? (string) $_FILES[$file_key]['type'] : '';
        if ($type && strpos($type, 'image/') !== 0) {
            return new \WP_Error('sccc_image_type', __('Please upload a valid image file.', 'sccc'));
        }

        $attachment_id = media_handle_upload($file_key, 0);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        if (! wp_attachment_is_image($attachment_id)) {
            wp_delete_attachment($attachment_id, true);
            return new \WP_Error('sccc_image_not_image', __('Please upload a valid image file.', 'sccc'));
        }

        return (int) $attachment_id;
    }

    /**
     * Strip all non-digits from a phone number.
     *
     * This is the only value we store:
     * - "(713) 555-1234" becomes "7135551234"
     * - "+1 713 555 1234" becomes "17135551234" and will be rejected because
     *   the final value is not exactly 10 digits.
     */
    protected static function normalizePhoneDigits(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return is_string($digits) ? $digits : '';
    }

    /**
     * Validate the stored 10-digit phone format.
     *
     * This is a plausibility check, not carrier verification. It prevents junk
     * input while keeping the solution PHP-only.
     */
    protected static function isValidTenDigitPhone(string $digits): bool
    {
        $digits = static::normalizePhoneDigits($digits);

        if (! preg_match('/^\d{10}$/', $digits)) {
            return false;
        }

        // Reject repeated junk numbers like 1111111111 or 9999999999.
        if (preg_match('/^(\d)\1{9}$/', $digits)) {
            return false;
        }

        $area = substr($digits, 0, 3);
        $exchange = substr($digits, 3, 3);

        // NANP-style area code and exchange cannot start with 0 or 1.
        if (preg_match('/^[01]/', $area) || preg_match('/^[01]/', $exchange)) {
            return false;
        }

        // Reject exchange service codes like 211, 311, 411, 511, 611, 711, 811, 911.
        if (preg_match('/^\d11$/', $exchange)) {
            return false;
        }

        return true;
    }

    /**
     * Format stored digits for frontend/admin display.
     *
     * Valid stored value:
     * - 7135551234 -> (713) 555-1234
     *
     * Invalid legacy value:
     * - Return digits as-is so admins/users can still see and correct it.
     */
    protected static function formatPhoneForDisplay(string $digits): string
    {
        $digits = static::normalizePhoneDigits($digits);

        if ($digits === '') {
            return '';
        }

        if (! static::isValidTenDigitPhone($digits)) {
            return $digits;
        }

        return sprintf(
            '(%s) %s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 4)
        );
    }

    /**
     * Approved member social platforms.
     */
    protected static function memberSocialPlatformOptions(): array
    {
        return [
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'x' => 'X',
            'tiktok' => 'TikTok',
            'youtube' => 'YouTube',
        ];
    }

    /**
     * Normalize social rows before saving or rendering.
     */
    protected static function normalizeMemberSocialLinks($links): array
    {
        if (! is_array($links)) {
            return [];
        }

        $allowed = array_keys(static::memberSocialPlatformOptions());
        $clean = [];
        $seen = [];

        foreach ($links as $row) {
            if (! is_array($row)) {
                continue;
            }

            $platform = isset($row['platform']) ? sanitize_key((string) $row['platform']) : '';
            $username = isset($row['username']) ? sanitize_text_field((string) $row['username']) : '';
            $username = ltrim(trim($username), '@');
            $url = isset($row['url']) ? esc_url_raw((string) $row['url']) : '';

            if ($platform === '' && $url !== '') {
                $platform = static::platformFromSocialUrl($url);
            }

            if ($platform === '' || ! in_array($platform, $allowed, true)) {
                continue;
            }

            if ($username === '' && $url !== '') {
                $username = static::usernameFromSocialUrl($url);
            }

            if ($url === '' && $username !== '') {
                $url = static::profileUrlFromPlatformUsername($platform, $username);
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
     * Build common profile URLs when platform + username are present.
     */
    protected static function profileUrlFromPlatformUsername(string $platform, string $username): string
    {
        $username = ltrim(trim($username), '@');

        if ($username === '') {
            return '';
        }

        $encoded = rawurlencode($username);

        return match ($platform) {
            'facebook' => 'https://www.facebook.com/' . $encoded,
            'instagram' => 'https://www.instagram.com/' . $encoded,
            'x' => 'https://x.com/' . $encoded,
            'tiktok' => 'https://www.tiktok.com/@' . $encoded,
            'youtube' => 'https://www.youtube.com/@' . $encoded,
            default => '',
        };
    }

    /**
     * Best-effort platform fallback for older rows that only have a URL.
     */
    protected static function platformFromSocialUrl(string $url): string
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
     * Best-effort username fallback for older rows that only have a URL.
     */
    protected static function usernameFromSocialUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $path = trim($path, "/ \t\n\r\0\x0B");

        if ($path === '') {
            return '';
        }

        $parts = array_values(array_filter(explode('/', $path)));
        $candidate = end($parts);

        if (! is_string($candidate) || $candidate === '') {
            return '';
        }

        return ltrim(sanitize_text_field($candidate), '@');
    }

    /**
     * Normalize date values to YYYY-MM-DD for ACF/date input compatibility.
     */
    protected static function normalizeDateYmdDash(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        if (preg_match('/^\d{8}$/', $raw)) {
            $dt = \DateTime::createFromFormat('Ymd', $raw);
            return $dt instanceof \DateTime ? $dt->format('Y-m-d') : '';
        }

        $ts = strtotime($raw);
        if (! $ts) {
            return '';
        }

        return gmdate('Y-m-d', $ts);
    }

    /**
     * Normalize old/new Veteran Type values to stable stored keys.
     */
    protected static function normalizeVeteranType(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        $allowed = ['veteran', 'first_responder', 'both'];
        if (in_array($raw, $allowed, true)) {
            return $raw;
        }

        $map = [
            'Veteran' => 'veteran',
            'veteran' => 'veteran',
            'First Responder' => 'first_responder',
            'first responder' => 'first_responder',
            'first_responder' => 'first_responder',
            'Both' => 'both',
            'both' => 'both',
        ];

        return $map[$raw] ?? '';
    }

    /**
     * Branch/service dropdown choices.
     */
    protected static function branchOptions(): array
    {
        return [
            'army' => 'U.S. Army',
            'navy' => 'U.S. Navy',
            'air_force' => 'U.S. Air Force',
            'marine_corps' => 'U.S. Marine Corps',
            'coast_guard' => 'U.S. Coast Guard',
            'space_force' => 'U.S. Space Force',
            'army_guard' => 'Army National Guard',
            'air_guard' => 'Air National Guard',
            'reserves' => 'Reserves (any branch)',
        ];
    }

    /**
     * Business Categories / Industry options.
     *
     * Keys are stored values. Keep keys stable once members start using them.
     */
    protected static function businessCategoryOptions(): array
    {
        return [
            'auto_sales' => 'Auto Sales (Dealer)',
            'auto_repair' => 'Auto Repair / Maintenance',
            'auto_detailing' => 'Auto Detailing',
            'auto_body' => 'Auto Body / Collision',
            'wrap_paint_ppf' => 'Wrap / Paint / PPF',
            'wheels_tires' => 'Wheels & Tires',
            'performance_tuning' => 'Performance / Tuning',
            'auto_glass' => 'Auto Glass',
            'towing_recovery' => 'Towing / Roadside',
            'auto_rental' => 'Car Rental / Fleet',
            'insurance' => 'Insurance',
            'finance' => 'Finance / Lending',
            'transport_shipping' => 'Transport / Shipping',
            'home_services' => 'Home Services',
            'hvac' => 'HVAC',
            'plumbing' => 'Plumbing',
            'electrical' => 'Electrical',
            'roofing' => 'Roofing',
            'landscaping' => 'Landscaping',
            'remodeling' => 'Remodeling / Construction',
            'legal' => 'Legal',
            'accounting' => 'Accounting / Tax',
            'real_estate' => 'Real Estate',
            'marketing' => 'Marketing / Advertising',
            'photography_media' => 'Photography / Video / Media',
            'it_tech' => 'IT / Tech Services',
            'printing_signage' => 'Printing / Signage',
            'security' => 'Security Services',
            'health_wellness' => 'Health / Wellness',
            'fitness' => 'Fitness / Training',
            'beauty_grooming' => 'Beauty / Grooming',
            'food_drink' => 'Food / Drink',
            'restaurants' => 'Restaurant / Cafe',
            'catering' => 'Catering',
            'events_venues' => 'Events / Venues',
            'retail' => 'Retail',
            'apparel' => 'Apparel / Merch',
            'pets' => 'Pet Services',
            'education' => 'Education / Lessons',
            'nonprofit' => 'Nonprofit / Community',
            'other' => 'Other',
        ];
    }

    /**
     * Business Services Offered options.
     *
     * Keys are stored values. Keep keys stable once members start using them.
     */
    protected static function businessServiceOptions(): array
    {
        return [
            'oil_change' => 'Oil Change',
            'brakes' => 'Brakes',
            'alignment' => 'Alignment',
            'tires' => 'Tires',
            'wheels' => 'Wheels',
            'diagnostics' => 'Diagnostics',
            'ac_service' => 'A/C Service',
            'transmission' => 'Transmission',
            'engine' => 'Engine Work',
            'custom_fabrication' => 'Custom Fabrication',
            'dyno_tune' => 'Dyno / Tuning',
            'ceramic_coating' => 'Ceramic Coating',
            'paint_correction' => 'Paint Correction',
            'interior_detail' => 'Interior Detailing',
            'exterior_detail' => 'Exterior Detailing',
            'ppf' => 'Paint Protection Film (PPF)',
            'vinyl_wrap' => 'Vinyl Wrap',
            'window_tint' => 'Window Tint',
            'audio_install' => 'Audio Install',
            'alarm_security' => 'Alarm / Vehicle Security',
            'tow' => 'Towing',
            'roadside' => 'Roadside Assistance',
            'vehicle_transport' => 'Vehicle Transport',
            'consulting' => 'Consulting',
            'tax_prep' => 'Tax Prep',
            'bookkeeping' => 'Bookkeeping',
            'legal_consult' => 'Legal Consultation',
            'notary' => 'Notary',
            'web_design' => 'Website / Web Design',
            'graphic_design' => 'Graphic Design',
            'photo_video' => 'Photo / Video',
            'printing' => 'Printing',
            'signs_wraps' => 'Signs / Banners',
            'social_media' => 'Social Media',
            'seo' => 'SEO',
            'hvac_repair' => 'HVAC Repair',
            'hvac_install' => 'HVAC Install',
            'plumbing_repair' => 'Plumbing Repair',
            'electrical_repair' => 'Electrical Repair',
            'roof_repair' => 'Roof Repair',
            'landscaping_service' => 'Landscaping',
            'remodeling_service' => 'Remodeling',
            'catering_service' => 'Catering',
            'event_space' => 'Event Space',
            'dj' => 'DJ / Entertainment',
            'personal_training' => 'Personal Training',
            'massage' => 'Massage',
            'barber' => 'Barber / Hair',
            'other' => 'Other',
        ];
    }
}

add_action('after_setup_theme', [AccountEditExtras::class, 'boot']);