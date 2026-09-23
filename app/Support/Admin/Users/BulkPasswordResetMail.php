<?php

/**
 * Custom Users bulk action: send password reset links using theme-defined
 * subject/body templates (Settings → Bulk password reset).
 *
 * Placeholders in subject and body:
 * {site_name}, {user_login}, {user_email}, {display_name}, {first_name}, {last_name},
 * {reset_url} — built with `wp_login_url()` so plugins (e.g. PMPro) can swap in
 * the membership login page; put this placeholder on its own line in the body
 * so email clients do not wrap the URL.
 *
 * Optional logo (media library) appears above the body; may be centered for email clients.
 * Email body: raw HTML in a textarea — <p>, <br>, <br />, <strong> only (filtered on save and send).
 * Subject line stays plain text.
 */

namespace App\Support\Admin\Users;

defined('ABSPATH') || exit;

final class BulkPasswordResetMail
{
    private const BULK_ACTION = 'sccc_send_password_reset';

    private const OPTION_SUBJECT = 'sccc_bulk_password_reset_subject';

    private const OPTION_BODY = 'sccc_bulk_password_reset_body';

    private const OPTION_LOGO_ID = 'sccc_bulk_password_reset_logo_id';

    private const OPTION_LOGO_CENTER = 'sccc_bulk_password_reset_logo_center';

    private const SETTINGS_GROUP = 'sccc_bulk_password_reset';

    private const OPTIONS_PAGE = 'sccc-bulk-password-reset';

    public static function register(): void
    {
        add_filter('bulk_actions-users', [self::class, 'registerBulkAction'], 11);
        add_filter('handle_bulk_actions-users', [self::class, 'handleBulkAction'], 25, 3);
        add_action('admin_notices', [self::class, 'maybeShowAdminNotice']);
        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('admin_menu', [self::class, 'registerOptionsPage']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueSettingsAssets']);
    }

    public static function registerBulkAction(array $actions): array
    {
        if (!current_user_can('edit_users')) {
            return $actions;
        }

        $actions[self::BULK_ACTION] = __('Send password reset (custom email)', 'sccc');

        return $actions;
    }

    /**
     * @param  string  $sendback  Referer URL for redirect.
     * @param  string  $action    Bulk action slug.
     * @param  int[]  $user_ids
     */
    public static function handleBulkAction(string $sendback, string $action, array $user_ids): string
    {
        if ($action !== self::BULK_ACTION) {
            return $sendback;
        }

        if (!current_user_can('edit_users')) {
            return $sendback;
        }

        /*
         * WordPress does not call check_admin_referer() before apply_filters( 'handle_bulk_actions-users' ).
         * The Users list submits via GET; long URLs (many selected users + _wp_http_referer) can be truncated
         * by proxies, which makes _wpnonce verification fail even for legitimate requests.
         * We rely on edit_users / per-user edit_user checks only (same practical gate as the rest of this screen).
         */

        $current_id = get_current_user_id();
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        $subjectTpl = (string) get_option(self::OPTION_SUBJECT, self::defaultSubject());
        $bodyTpl = (string) get_option(self::OPTION_BODY, self::defaultBody());
        $logoPrefix = self::buildLogoHtmlPrefix();

        foreach ($user_ids as $uid) {
            $user_id = (int) $uid;
            if ($user_id <= 0) {
                continue;
            }

            if ($user_id === $current_id) {
                $skipped++;
                continue;
            }

            if (!current_user_can('edit_user', $user_id)) {
                $skipped++;
                continue;
            }

            $user = get_userdata($user_id);
            if (!$user instanceof \WP_User) {
                $failed++;
                continue;
            }

            $email = (string) $user->user_email;
            if ($email === '') {
                $failed++;
                continue;
            }

            $key = get_password_reset_key($user);
            if (is_wp_error($key)) {
                $failed++;
                continue;
            }

            $replacements = self::buildReplacements($user, (string) $key, false);
            $subject = self::applyTemplate($subjectTpl, $replacements);
            $innerBody = self::prepareHtmlBody(self::applyTemplate($bodyTpl, self::buildReplacements($user, (string) $key, true)));
            $bodyHtml = $logoPrefix . $innerBody;

            $headers = ['Content-Type: text/html; charset=UTF-8'];

            if (!wp_mail($email, $subject, $bodyHtml, $headers)) {
                $failed++;
                continue;
            }

            $sent++;
        }

        return add_query_arg(
            [
                'sccc_pwreset' => '1',
                'sccc_sent' => $sent,
                'sccc_failed' => $failed,
                'sccc_skipped' => $skipped,
            ],
            $sendback
        );
    }

    public static function maybeShowAdminNotice(): void
    {
        if (!is_admin() || !isset($_GET['sccc_pwreset']) || (string) wp_unslash($_GET['sccc_pwreset']) !== '1') {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'users') {
            return;
        }

        if (!current_user_can('edit_users')) {
            return;
        }

        $sent = isset($_GET['sccc_sent']) ? (int) $_GET['sccc_sent'] : 0;
        $failed = isset($_GET['sccc_failed']) ? (int) $_GET['sccc_failed'] : 0;
        $skipped = isset($_GET['sccc_skipped']) ? (int) $_GET['sccc_skipped'] : 0;

        $parts = [];
        if ($sent > 0) {
            /* translators: %d: number of emails sent */
            $parts[] = sprintf(_n('%d reset email sent.', '%d reset emails sent.', $sent, 'sccc'), $sent);
        }
        if ($failed > 0) {
            /* translators: %d: number of failures */
            $parts[] = sprintf(_n('%d could not be sent (check mail configuration or user eligibility).', '%d could not be sent (check mail configuration or user eligibility).', $failed, 'sccc'), $failed);
        }
        if ($skipped > 0) {
            /* translators: %d: number skipped */
            $parts[] = sprintf(_n('%d user skipped (no permission or yourself).', '%d users skipped (no permission or yourself).', $skipped, 'sccc'), $skipped);
        }

        if ($parts === []) {
            $parts[] = __('No users were processed.', 'sccc');
        }

        $class = ($failed > 0 && $sent === 0) ? 'notice notice-warning' : 'notice notice-success';

        $link = '';
        if (current_user_can('manage_options')) {
            $link = ' ' . sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('options-general.php?page=' . self::OPTIONS_PAGE)),
                esc_html__('Edit email template', 'sccc')
            );
        }

        echo '<div class="' . esc_attr($class) . ' is-dismissible"><p>'
            . esc_html(implode(' ', $parts))
            . wp_kses_post($link)
            . '</p></div>';
    }

    public static function registerSettings(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_SUBJECT,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitizeSubject'],
                'default' => self::defaultSubject(),
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_BODY,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitizeBody'],
                'default' => self::defaultBody(),
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_LOGO_ID,
            [
                'type' => 'integer',
                'sanitize_callback' => [self::class, 'sanitizeLogoId'],
                'default' => 0,
            ]
        );

        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_LOGO_CENTER,
            [
                'type' => 'string',
                'sanitize_callback' => [self::class, 'sanitizeLogoCenter'],
                'default' => '1',
            ]
        );
    }

    public static function enqueueSettingsAssets(string $hook): void
    {
        if ($hook !== 'settings_page_' . self::OPTIONS_PAGE) {
            return;
        }

        wp_enqueue_media();

        wp_localize_script(
            'media-editor',
            'scccBprLogo',
            [
                'i18nTitle' => __('Email logo', 'sccc'),
                'i18nUse' => __('Use this image', 'sccc'),
            ]
        );

        wp_add_inline_script(
            'media-editor',
            <<<'JS'
(function ($) {
  $(function () {
    var frame;
    $('#sccc-bpr-logo-select').on('click', function (e) {
      e.preventDefault();
      if (frame) {
        frame.open();
        return;
      }
      frame = wp.media({
        title: scccBprLogo.i18nTitle,
        button: { text: scccBprLogo.i18nUse },
        library: { type: 'image' },
        multiple: false
      });
      frame.on('select', function () {
        var att = frame.state().get('selection').first().toJSON();
        $('#sccc-bpr-logo-id').val(att.id);
        if (att.url) {
          $('#sccc-bpr-logo-preview').attr('src', att.url).show();
        }
      });
      frame.open();
    });
    $('#sccc-bpr-logo-remove').on('click', function (e) {
      e.preventDefault();
      $('#sccc-bpr-logo-id').val('0');
      $('#sccc-bpr-logo-preview').hide().attr('src', '');
    });
  });
})(jQuery);
JS
            ,
            'after'
        );
    }

    public static function registerOptionsPage(): void
    {
        add_options_page(
            __('Bulk password reset email', 'sccc'),
            __('Bulk password reset', 'sccc'),
            'manage_options',
            self::OPTIONS_PAGE,
            [self::class, 'renderSettingsPage']
        );
    }

    public static function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $subject = (string) get_option(self::OPTION_SUBJECT, self::defaultSubject());
        $body = (string) get_option(self::OPTION_BODY, self::defaultBody());
        $logoId = (int) get_option(self::OPTION_LOGO_ID, 0);
        $logoCenter = (string) get_option(self::OPTION_LOGO_CENTER, '1') === '1';
        $logoPreviewUrl = ($logoId > 0 && wp_attachment_is_image($logoId))
            ? (string) wp_get_attachment_image_url($logoId, 'medium')
            : '';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(__('Bulk password reset email', 'sccc')); ?></h1>
            <p><?php
            echo wp_kses(
                sprintf(
                    /* translators: %s: allowed HTML tag names (wrapped in <code>). */
                    esc_html__('Used by the Users screen bulk action “Send password reset (custom email)”. Subject is plain text. Optional logo is added above the message. Type the body as HTML; allowed tags: %s. Put {reset_url} on its own line or inside a paragraph so the link is not broken.', 'sccc'),
                    '<code>p</code>, <code>br</code>, <code>strong</code> (<code>br /</code>)'
                ),
                [
                    'code' => [],
                ]
            );
            ?></p>
            <p><strong><?php esc_html_e('Placeholders', 'sccc'); ?></strong>
                <code>{site_name}</code>,
                <code>{user_login}</code>,
                <code>{user_email}</code>,
                <code>{display_name}</code>,
                <code>{first_name}</code>,
                <code>{last_name}</code>,
                <code>{reset_url}</code>
            </p>
            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Email logo', 'sccc'); ?></th>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr(self::OPTION_LOGO_ID); ?>" id="sccc-bpr-logo-id" value="<?php echo esc_attr((string) $logoId); ?>" />
                            <p>
                                <button type="button" class="button" id="sccc-bpr-logo-select"><?php esc_html_e('Select image', 'sccc'); ?></button>
                                <button type="button" class="button" id="sccc-bpr-logo-remove"><?php esc_html_e('Remove logo', 'sccc'); ?></button>
                            </p>
                            <p class="description"><?php esc_html_e('Shown at the top of the HTML email. Only image files from the media library.', 'sccc'); ?></p>
                            <p>
                                <img id="sccc-bpr-logo-preview" src="<?php echo esc_url($logoPreviewUrl); ?>"
                                    alt=""
                                    style="max-width: 240px; height: auto; <?php echo $logoPreviewUrl !== '' ? '' : 'display:none;'; ?>" />
                            </p>
                            <input type="hidden" name="<?php echo esc_attr(self::OPTION_LOGO_CENTER); ?>" value="0" />
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_LOGO_CENTER); ?>" value="1" <?php checked($logoCenter); ?> />
                                <?php esc_html_e('Center the logo in the email', 'sccc'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sccc-bpr-subject"><?php esc_html_e('Subject', 'sccc'); ?></label></th>
                        <td>
                            <input name="<?php echo esc_attr(self::OPTION_SUBJECT); ?>" type="text" id="sccc-bpr-subject"
                                class="large-text" value="<?php echo esc_attr($subject); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sccc-bpr-body"><?php esc_html_e('Email body (HTML)', 'sccc'); ?></label></th>
                        <td>
                            <textarea name="<?php echo esc_attr(self::OPTION_BODY); ?>" id="sccc-bpr-body" rows="18" class="large-text code"><?php
                            echo esc_textarea(self::decodeMarkupEntities((string) $body));
                            ?></textarea>
                            <p class="description"><?php esc_html_e('Write real HTML (not encoded entities). Allowed tags:', 'sccc'); ?>
                                <code>p</code>, <code>br</code>, <code>strong</code>.
                                <?php esc_html_e('Other tags are removed when you save. Placeholders are still replaced.', 'sccc'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save changes', 'sccc')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * @param  mixed  $value
     */
    public static function sanitizeSubject($value): string
    {
        $v = sanitize_text_field(is_string($value) ? $value : '');

        return $v !== '' ? $v : self::defaultSubject();
    }

    /**
     * @param  mixed  $value
     */
    public static function sanitizeBody($value): string
    {
        if (!is_string($value)) {
            return self::defaultBody();
        }

        $value = self::decodeMarkupEntities(wp_unslash($value));
        $clean = wp_kses($value, self::bodyAllowedTags());

        return trim($clean) !== '' ? $clean : self::defaultBody();
    }

    /**
     * Turn stored entity markup (&lt;p&gt; …) into real tags so wp_kses and email clients see HTML.
     */
    private static function decodeMarkupEntities(string $value): string
    {
        $charset = (string) get_bloginfo('charset');
        if ($charset === '') {
            $charset = 'UTF-8';
        }

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, $charset);
    }

    /**
     * @param  mixed  $value
     */
    public static function sanitizeLogoId($value): int
    {
        $id = absint($value);

        if ($id === 0) {
            return 0;
        }

        if (!wp_attachment_is_image($id)) {
            return 0;
        }

        return $id;
    }

    /**
     * @param  mixed  $value
     */
    public static function sanitizeLogoCenter($value): string
    {
        if ($value === true || $value === 1 || $value === '1') {
            return '1';
        }

        return '0';
    }

    /**
     * Safe HTML prefix for outbound mail (not merged into user-edited body).
     */
    private static function buildLogoHtmlPrefix(): string
    {
        $id = (int) get_option(self::OPTION_LOGO_ID, 0);
        if ($id <= 0 || !wp_attachment_is_image($id)) {
            return '';
        }

        $img = wp_get_attachment_image_src($id, 'medium');
        if ($img === false) {
            $img = wp_get_attachment_image_src($id, 'full');
        }
        if ($img === false || $img[0] === '') {
            return '';
        }

        $url = (string) $img[0];
        $iw = (int) $img[1];
        $ih = (int) $img[2];

        $displayW = $iw > 0 ? min($iw, 320) : 0;
        $displayH = ($iw > 0 && $ih > 0 && $displayW > 0)
            ? (int) max(1, round($ih * ($displayW / $iw)))
            : 0;

        $alt = wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES);

        $imgTag = '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '"'
            . ' style="max-width:320px;width:100%;height:auto;border:0;outline:none;text-decoration:none;display:inline-block;"'
            . ($displayW > 0 ? ' width="' . esc_attr((string) $displayW) . '"' : '')
            . ($displayH > 0 ? ' height="' . esc_attr((string) $displayH) . '"' : '')
            . ' />';

        $center = (string) get_option(self::OPTION_LOGO_CENTER, '1') === '1';
        $align = $center ? 'text-align:center;' : 'text-align:left;';

        return '<div style="' . esc_attr($align) . 'margin:0 0 24px 0;line-height:0;">' . $imgTag . '</div>';
    }

    /**
     * Tags allowed in the HTML email body (subject is always plain text).
     *
     * @return array<string, array<string, bool>>
     */
    private static function bodyAllowedTags(): array
    {
        return [
            'p' => [],
            'br' => [],
            'strong' => [],
        ];
    }

    /**
     * Escape placeholder values for HTML body; subject uses plain-text replacements.
     *
     * @return array<string, string>
     */
    private static function buildReplacements(\WP_User $user, string $plainKey, bool $escapeForHtml): array
    {
        $siteName = wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES);
        $resetUrl = self::passwordResetRedirectUrl($user, $plainKey);

        $login = (string) $user->user_login;
        $mail = (string) $user->user_email;
        $display = (string) $user->display_name;
        $first = (string) $user->first_name;
        $last = (string) $user->last_name;

        if ($escapeForHtml) {
            return [
                '{site_name}' => esc_html($siteName),
                '{user_login}' => esc_html($login),
                '{user_email}' => esc_html($mail),
                '{display_name}' => esc_html($display),
                '{first_name}' => esc_html($first),
                '{last_name}' => esc_html($last),
                '{reset_url}' => esc_html($resetUrl),
            ];
        }

        return [
            '{site_name}' => $siteName,
            '{user_login}' => $login,
            '{user_email}' => $mail,
            '{display_name}' => $display,
            '{first_name}' => $first,
            '{last_name}' => $last,
            '{reset_url}' => $resetUrl,
        ];
    }

    /**
     * Legacy plain-text templates (no block tags): wrap with paragraphs for HTML mail.
     */
    private static function prepareHtmlBody(string $body): string
    {
        $body = self::decodeMarkupEntities($body);
        $body = wp_kses($body, self::bodyAllowedTags());
        if (!preg_match('/<\s*(p|br|strong)\b/i', $body)) {
            $body = wpautop($body, true);
        }

        return wp_kses($body, self::bodyAllowedTags());
    }

    public static function defaultSubject(): string
    {
        return '[{site_name}] Password reset';
    }

    public static function defaultBody(): string
    {
        return '<p>' . esc_html__('Hi', 'sccc') . ' {display_name},</p>'
            . '<p>' . esc_html__('Someone requested a password reset for your account', 'sccc') . ' ({user_login}) '
            . esc_html__('on', 'sccc') . ' {site_name}.</p>'
            . '<p><strong>' . esc_html__('Set a new password', 'sccc') . '</strong> — ' . esc_html__('open this link:', 'sccc') . '</p>'
            . '<p>{reset_url}</p>'
            . '<p>' . esc_html__('If you did not request this, you can ignore this email.', 'sccc') . '</p>';
    }

    /**
     * Same strategy as core login URLs: `wp_login_url()` runs the `login_url`
     * filter so Paid Memberships Pro and similar stacks can use the front-end
     * login page for the reset hand-off.
     */
    private static function passwordResetRedirectUrl(\WP_User $user, string $plainKey): string
    {
        $base = wp_login_url('', false);

        return add_query_arg(
            [
                'action' => 'rp',
                'key' => $plainKey,
                'login' => $user->user_login,
            ],
            $base
        );
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private static function applyTemplate(string $template, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}

BulkPasswordResetMail::register();
