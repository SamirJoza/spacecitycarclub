<?php

namespace App\Support\Security;

use WP_Error;
use WP_User;

/**
 * File: app/Support/Security/StrongPasswords.php
 *
 * Theme-contained password policy enforcement for Space City Car Club.
 *
 * This file prevents weak-password account creation across the main signup paths:
 * - WooCommerce account registration.
 * - WooCommerce checkout account creation.
 * - Paid Memberships Pro checkout/user creation.
 * - Native WordPress registration when a custom form supplies a password.
 * - Password reset and profile/account password updates.
 *
 * Current SCCC password policy:
 * - Minimum 8 characters.
 * - 12+ characters recommended (no maximum length).
 * - Must include at least one uppercase letter.
 * - Must include at least one lowercase letter.
 * - Must include at least one number.
 * - Must include at least one special character.
 * - Spaces are not allowed in this version.
 *
 * The frontend AJAX helper is only user experience. The real protection is the
 * server-side validation hooks below, which run before user creation or before a
 * password update is saved.
 */
defined('ABSPATH') || exit;

final class StrongPasswords
{
    /**
     * Old-school composition policy requested for the current membership audience.
     */
    private const MIN_LENGTH = 8;
    private const RECOMMENDED_LENGTH = 12;

    /**
     * AJAX action/nonce names are intentionally specific to this project.
     */
    private const AJAX_ACTION = 'sccc_check_password_strength';
    private const AJAX_NONCE_ACTION = 'sccc_strong_password_check';

    /**
     * Register all hooks used by this support file.
     */
    public static function boot(): void
    {
        /**
         * WooCommerce frontend meter settings.
         * These improve live feedback, but they are not trusted for enforcement.
         */
        add_filter('woocommerce_min_password_strength', [self::class, 'woocommerceMeterStrength'], 20);
        add_filter('woocommerce_enforce_password_strength_meter_on_checkout', '__return_true', 20);

        /**
         * WooCommerce hard validation points.
         * These block customer creation before wc_create_new_customer() reaches wp_insert_user().
         */
        add_filter('woocommerce_process_registration_errors', [self::class, 'validateWooAccountRegistration'], 20, 4);
        add_filter('woocommerce_registration_errors', [self::class, 'validateWooCreateCustomer'], 20, 3);
        add_action('woocommerce_after_checkout_validation', [self::class, 'validateWooCheckout'], 20, 2);
        add_action('woocommerce_save_account_details_errors', [self::class, 'validateWooAccountDetails'], 20, 2);

        /**
         * Some hosts/snippets/plugins add HTML maxlength on password inputs (e.g. 18).
         * Our policy has no upper bound — strip maxlength from Woo-rendered fields.
         */
        add_filter('woocommerce_form_field_args', [self::class, 'stripPasswordFieldMaxlength'], 999, 3);

        /**
         * Align core password hint with SCCC policy (WP/Woo still show this in several forms).
         */
        add_filter('password_hint', [self::class, 'passwordHintText']);

        /**
         * PMPro hard validation points.
         * The user-creation check is the critical hook for preventing weak-password
         * subscriber/customer ghost accounts from being created during membership checkout.
         */
        add_filter('pmpro_checkout_user_creation_checks', [self::class, 'validatePmproUserCreation'], 20, 1);
        add_filter('pmpro_registration_checks', [self::class, 'validatePmproRegistration'], 20, 1);

        /**
         * WordPress account lifecycle validation.
         * These are safety nets for native registration, password reset, and admin/profile updates.
         */
        add_filter('registration_errors', [self::class, 'validateWordPressRegistration'], 20, 3);
        add_action('validate_password_reset', [self::class, 'validatePasswordReset'], 20, 2);
        add_action('user_profile_update_errors', [self::class, 'validateProfilePasswordUpdate'], 20, 3);

        /**
         * Frontend AJAX helper for live feedback.
         * The nopriv action covers logged-out signup forms.
         */
        add_action('wp_ajax_' . self::AJAX_ACTION, [self::class, 'ajaxCheck']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [self::class, 'ajaxCheck']);
        add_action('wp_footer', [self::class, 'printFrontendHelper'], 50);
    }

    /**
     * Ask WooCommerce's built-in meter to use the strongest available threshold.
     *
     * This is only a visual WooCommerce meter setting. The actual SCCC policy is
     * enforced by the shared validator in this file.
     */
    public static function woocommerceMeterStrength(): int
    {
        return 4;
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public static function stripPasswordFieldMaxlength(array $args, string $key, mixed $value): array
    {
        if (($args['type'] ?? '') !== 'password') {
            return $args;
        }

        unset($args['maxlength']);

        if (! empty($args['custom_attributes']) && is_array($args['custom_attributes'])) {
            unset($args['custom_attributes']['maxlength'], $args['custom_attributes']['maxLength']);
        }

        return $args;
    }

    public static function passwordHintText(string $hint): string
    {
        unset($hint);

        return __(
            'Hint: use at least 8 characters (12 or more is recommended). There is no maximum length. ' .
            'Include upper and lower case letters, numbers, and symbols like ! " ? $ % ^ & ).',
            'sccc'
        );
    }

    /**
     * WooCommerce My Account registration form validation.
     */
    public static function validateWooAccountRegistration(WP_Error $errors, string $username, string $password, string $email): WP_Error
    {
        if ($password === '') {
            return $errors;
        }

        return self::addWpErrorIfInvalid($errors, $password);
    }

    /**
     * WooCommerce customer creation validation.
     *
     * This catches customer creation paths that call wc_create_new_customer()
     * and expose only the generic WooCommerce registration error filter.
     */
    public static function validateWooCreateCustomer(WP_Error $errors, string $username, string $email): WP_Error
    {
        $password = self::passwordFromRequest([
            'password',
            'account_password',
            'password_1',
        ]);

        if ($password === '') {
            return $errors;
        }

        return self::addWpErrorIfInvalid($errors, $password);
    }

    /**
     * WooCommerce checkout validation before customer/order processing continues.
     */
    public static function validateWooCheckout(array $data, WP_Error $errors): void
    {
        if (is_user_logged_in()) {
            return;
        }

        $isCreatingAccount = ! empty($data['createaccount']) || ! empty($data['account_password']);

        if (! $isCreatingAccount) {
            return;
        }

        $password = self::passwordFromRequest(['account_password']);

        if ($password === '' && isset($data['account_password']) && is_string($data['account_password'])) {
            $password = (string) $data['account_password'];
        }

        if ($password === '') {
            return;
        }

        self::addWpErrorIfInvalid($errors, $password);
    }

    /**
     * WooCommerce My Account > Account details password update validation.
     */
    public static function validateWooAccountDetails(WP_Error $errors, object $user): void
    {
        $password = self::passwordFromRequest(['password_1']);

        if ($password === '') {
            return;
        }

        self::addWpErrorIfInvalid($errors, $password);
    }

    /**
     * PMPro user creation gate.
     *
     * This is the most important hook for the reported issue because returning
     * false here prevents PMPro from moving into the user creation step.
     */
    public static function validatePmproUserCreation(bool $continue): bool
    {
        return self::validatePmproRequest($continue);
    }

    /**
     * PMPro registration continuation check.
     */
    public static function validatePmproRegistration(bool $continue): bool
    {
        return self::validatePmproRequest($continue);
    }

    /**
     * WordPress native registration validation for custom registration forms that include passwords.
     */
    public static function validateWordPressRegistration(WP_Error $errors, string $sanitizedUserLogin, string $userEmail): WP_Error
    {
        $password = self::passwordFromRequest([
            'password',
            'pass1',
            'user_pass',
        ]);

        if ($password === '') {
            return $errors;
        }

        return self::addWpErrorIfInvalid($errors, $password);
    }

    /**
     * WordPress and WooCommerce password reset validation.
     */
    public static function validatePasswordReset(WP_Error $errors, WP_User|WP_Error $user): void
    {
        $password = self::passwordFromRequest([
            'pass1',
            'password_1',
        ]);

        if ($password === '') {
            return;
        }

        self::addWpErrorIfInvalid($errors, $password);
    }

    /**
     * WordPress admin/profile password update validation.
     */
    public static function validateProfilePasswordUpdate(WP_Error $errors, bool $update, object $user): void
    {
        $password = self::passwordFromRequest(['pass1']);

        if ($password === '') {
            return;
        }

        self::addWpErrorIfInvalid($errors, $password);
    }

    /**
     * AJAX endpoint used by the frontend helper.
     */
    public static function ajaxCheck(): void
    {
        check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');

        $password = self::passwordFromRequest(['password']);
        $result   = self::validate($password);

        wp_send_json_success([
            'valid'    => $result['valid'],
            'messages' => $result['messages'],
            'warnings' => $result['warnings'],
            'label'    => self::statusLabel($result),
        ]);
    }

    /**
     * Print the frontend helper near the footer.
     *
     * This is intentionally inline to keep this first pass self-contained. Once
     * the behavior is confirmed, the JavaScript can be moved into a Vite-managed
     * Sage asset if you want it bundled with the theme scripts.
     */
    public static function printFrontendHelper(): void
    {
        if (is_admin()) {
            return;
        }

        $ajaxUrl = esc_url(admin_url('admin-ajax.php'));
        $nonce   = esc_js(wp_create_nonce(self::AJAX_NONCE_ACTION));
        $action  = esc_js(self::AJAX_ACTION);
        ?>
        <script>
          (() => {
            const ajaxUrl = '<?php echo $ajaxUrl; ?>';
            const nonce = '<?php echo $nonce; ?>';
            const action = '<?php echo $action; ?>';

            const policyText = 'Use at least 8 characters (12+ recommended; no maximum). Include uppercase, lowercase, a number, and a special character. No spaces.';

            const passwordNames = new Set([
              'password',
              'account_password',
              'password_1',
              'pass1',
              'user_pass'
            ]);

            const ignoredNames = new Set([
              'password_current',
              'current_password',
              'password_2',
              'pass2'
            ]);

            /** Snippets/plugins sometimes add HTML maxlength on password fields (e.g. 18). */
            const stripMaxlength = (el) => {
              if (!el || el.nodeName !== 'INPUT' || el.type !== 'password') return;
              if (ignoredNames.has(el.name)) return;
              if (!passwordNames.has(el.name) && el.name !== 'password2') return;
              el.removeAttribute('maxlength');
              el.removeAttribute('maxLength');
            };

            document.addEventListener('focusin', (e) => stripMaxlength(e.target), true);
            document.addEventListener('keydown', (e) => stripMaxlength(e.target), true);
            document.addEventListener('beforeinput', (e) => stripMaxlength(e.target), true);
            document.addEventListener('paste', (e) => stripMaxlength(e.target), true);

            const inputs = Array.from(document.querySelectorAll('input[type="password"]'))
              .filter((input) => passwordNames.has(input.name) && !ignoredNames.has(input.name));

            inputs.forEach(stripMaxlength);

            if (!inputs.length) {
              return;
            }

            const ensureStatus = (input) => {
              let status = input.parentElement?.querySelector('.sccc-password-strength-note');

              if (!status) {
                status = document.createElement('p');
                status.className = 'sccc-password-strength-note';
                status.setAttribute('aria-live', 'polite');
                status.style.margin = '0.45rem 0 0';
                status.style.fontSize = '0.875rem';
                status.style.lineHeight = '1.4';
                status.style.color = 'var(--color-muted, currentColor)';
                input.insertAdjacentElement('afterend', status);
              }

              return status;
            };

            const setSubmitState = (form, shouldDisable) => {
              if (!form) {
                return;
              }

              form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
                if (shouldDisable) {
                  button.dataset.scccPasswordDisabled = '1';
                  button.setAttribute('aria-disabled', 'true');
                  button.disabled = true;
                } else if (button.dataset.scccPasswordDisabled === '1') {
                  delete button.dataset.scccPasswordDisabled;
                  button.removeAttribute('aria-disabled');
                  button.disabled = false;
                }
              });
            };

            const debounce = (callback, wait = 250) => {
              let timeout;

              return (...args) => {
                window.clearTimeout(timeout);
                timeout = window.setTimeout(() => callback(...args), wait);
              };
            };

            const checkPassword = async (input) => {
              stripMaxlength(input);

              const form = input.closest('form');
              const status = ensureStatus(input);
              const password = input.value || '';

              input.dataset.scccPasswordValid = '';

              if (!password.length) {
                status.textContent = policyText;
                status.style.color = 'var(--color-muted, currentColor)';
                setSubmitState(form, false);
                return;
              }

              const body = new URLSearchParams();
              body.append('action', action);
              body.append('nonce', nonce);
              body.append('password', password);

              try {
                const response = await fetch(ajaxUrl, {
                  method: 'POST',
                  credentials: 'same-origin',
                  headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                  },
                  body
                });

                const json = await response.json();
                const data = json?.data || {};
                const valid = Boolean(data.valid);
                const messages = Array.isArray(data.messages) ? data.messages : [];
                const warnings = Array.isArray(data.warnings) ? data.warnings : [];

                input.dataset.scccPasswordValid = valid ? '1' : '0';

                if (!valid) {
                  status.textContent = messages.join(' ') || 'Please choose a stronger password.';
                  status.style.color = 'var(--color-danger, #dc2626)';
                  setSubmitState(form, true);
                  return;
                }

                status.textContent = warnings.length
                  ? warnings.join(' ')
                  : 'Strong password.';

                status.style.color = warnings.length
                  ? 'var(--color-warning, #b45309)'
                  : 'var(--color-success, #15803d)';

                setSubmitState(form, false);
              } catch (error) {
                status.textContent = 'Password strength will be checked when you submit the form.';
                status.style.color = 'var(--color-muted, currentColor)';
                setSubmitState(form, false);
              }
            };

            inputs.forEach((input) => {
              const runCheck = debounce(() => checkPassword(input));
              const form = input.closest('form');

              ensureStatus(input).textContent = policyText;

              input.addEventListener('input', runCheck);
              input.addEventListener('blur', () => checkPassword(input));

              form?.addEventListener('submit', (event) => {
                if (input.value && input.dataset.scccPasswordValid === '0') {
                  event.preventDefault();
                  ensureStatus(input).textContent = 'Please choose a stronger password before continuing.';
                  input.focus();
                }
              });
            });
          })();
        </script>
        <?php
    }

    /**
     * Shared PMPro validator.
     */
    private static function validatePmproRequest(bool $continue): bool
    {
        if (! $continue || is_user_logged_in()) {
            return $continue;
        }

        $password = self::passwordFromRequest(['password']);

        if ($password === '') {
            return $continue;
        }

        $result = self::validate($password);

        if ($result['valid']) {
            return $continue;
        }

        self::setPmproPasswordError($result['messages']);

        return false;
    }

    /**
     * Add a password policy failure to a WP_Error object.
     */
    private static function addWpErrorIfInvalid(WP_Error $errors, string $password): WP_Error
    {
        $result = self::validate($password);

        if (! $result['valid']) {
            $errors->add(
                'sccc_weak_password',
                self::formatMessage($result['messages'])
            );
        }

        return $errors;
    }

    /**
     * Central password policy.
     *
     * This version intentionally uses the familiar composition-rule pattern:
     * uppercase, lowercase, number, special character, plus minimum length (no max).
     */
    private static function validate(string $password): array
    {
        $messages = [];
        $warnings = [];
        $length   = self::length($password);

        if ($password === '') {
            $messages[] = __('Please create a password.', 'sccc');
        }

        if ($length < self::MIN_LENGTH) {
            $messages[] = sprintf(
                __('Use at least %d characters.', 'sccc'),
                self::MIN_LENGTH
            );
        }

        if ($password !== '' && preg_match('/\s/', $password)) {
            $messages[] = __('Do not use spaces in the password.', 'sccc');
        }

        if ($password !== '' && ! preg_match('/[A-Z]/', $password)) {
            $messages[] = __('Add at least one uppercase letter.', 'sccc');
        }

        if ($password !== '' && ! preg_match('/[a-z]/', $password)) {
            $messages[] = __('Add at least one lowercase letter.', 'sccc');
        }

        if ($password !== '' && ! preg_match('/[0-9]/', $password)) {
            $messages[] = __('Add at least one number.', 'sccc');
        }

        if ($password !== '' && ! preg_match('/[^A-Za-z0-9\s]/', $password)) {
            $messages[] = __('Add at least one special character.', 'sccc');
        }

        if ($password !== '' && preg_match('/^(.)\1+$/', $password)) {
            $messages[] = __('Do not use the same character repeated over and over.', 'sccc');
        }

        if ($password !== '' && self::containsBlockedTerm($password)) {
            $messages[] = __('Avoid obvious words, keyboard patterns, club initials, or common leaked passwords.', 'sccc');
        }

        if (empty($messages) && $length < self::RECOMMENDED_LENGTH) {
            $warnings[] = sprintf(
                /* translators: %d: recommended minimum length (e.g. 12) */
                __('This password meets the minimum; we recommend at least %d characters for a stronger account.', 'sccc'),
                self::RECOMMENDED_LENGTH
            );
        }

        return [
            'valid'    => empty($messages),
            'messages' => $messages,
            'warnings' => $warnings,
        ];
    }

    /**
     * Read a password field from the current request.
     *
     * Password fields are intentionally not sanitized, trimmed, or text-cleaned here
     * because those transformations can change the password the user typed. The value
     * is only inspected against the policy and is never printed back to the page.
     */
    private static function passwordFromRequest(array $fieldNames): string
    {
        foreach ($fieldNames as $fieldName) {
            if (isset($_POST[$fieldName]) && is_string($_POST[$fieldName])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
                return (string) $_POST[$fieldName]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
            }
        }

        return '';
    }

    /**
     * Set PMPro's global checkout error state.
     */
    private static function setPmproPasswordError(array $messages): void
    {
        global $pmpro_msg, $pmpro_msgt, $pmpro_error_fields;

        $pmpro_msg  = self::formatMessage($messages);
        $pmpro_msgt = 'pmpro_error';

        if (! is_array($pmpro_error_fields)) {
            $pmpro_error_fields = [];
        }

        $pmpro_error_fields[] = 'password';
        $pmpro_error_fields[] = 'password2';
        $pmpro_error_fields   = array_values(array_unique($pmpro_error_fields));
    }

    /**
     * Format validation details into one frontend-safe message.
     */
    private static function formatMessage(array $messages): string
    {
        return sprintf(
            '%s %s',
            __('Please choose a stronger password.', 'sccc'),
            implode(' ', array_map('wp_strip_all_tags', $messages))
        );
    }

    /**
     * Return a readable AJAX status label.
     */
    private static function statusLabel(array $result): string
    {
        if (! $result['valid']) {
            return __('Password needs work', 'sccc');
        }

        if (! empty($result['warnings'])) {
            return __('Password meets the minimum', 'sccc');
        }

        return __('Strong password', 'sccc');
    }

    /**
     * Check if the password contains project/common weak password patterns.
     */
    private static function containsBlockedTerm(string $password): bool
    {
        $normalizedPassword = self::normalizeForComparison($password);

        if ($normalizedPassword === '') {
            return false;
        }

        $blockedTerms = [
            'password',
            'passw0rd',
            'qwerty',
            'qwertyuiop',
            'letmein',
            'welcome',
            'admin',
            'administrator',
            'login',
            'iloveyou',
            'abc123',
            '123456',
            '123456789',
            'spacecity',
            'spacecitycarclub',
            'sccc',
        ];

        foreach ($blockedTerms as $term) {
            if ($term !== '' && str_contains($normalizedPassword, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize values for simple blocklist comparison.
     */
    private static function normalizeForComparison(string $value): string
    {
        $value = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        return (string) preg_replace('/[^a-z0-9]+/i', '', $value);
    }

    /**
     * Multibyte-aware length check with safe fallback.
     */
    private static function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}

StrongPasswords::boot();