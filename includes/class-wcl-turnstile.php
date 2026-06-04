<?php
/**
 * Cloudflare Turnstile + honeypot + rate-limit bot protection
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCL_Turnstile {

    const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    const HONEYPOT_FIELD = 'wcl_hp_website';
    const TOKEN_FIELD = 'cf-turnstile-response';

    /**
     * Is Turnstile fully configured (keys + enabled toggle)?
     */
    public static function is_enabled() {
        if (!get_option('wcl_turnstile_enabled', 0)) {
            return false;
        }
        return self::get_site_key() !== '' && self::get_secret_key() !== '';
    }

    public static function get_site_key() {
        return trim((string) get_option('wcl_turnstile_site_key', ''));
    }

    public static function get_secret_key() {
        return trim((string) get_option('wcl_turnstile_secret_key', ''));
    }

    /**
     * Render the Turnstile widget div (no-op if disabled).
     */
    public static function render_widget($action = '') {
        if (!self::is_enabled()) {
            return;
        }
        printf(
            '<div class="wcl-turnstile cf-turnstile" data-sitekey="%s" data-action="%s" data-theme="auto"></div>',
            esc_attr(self::get_site_key()),
            esc_attr($action)
        );
    }

    /**
     * Render the hidden honeypot field. Real users don't fill it; bots do.
     * Placed off-screen so screen readers still can warn, but visually hidden.
     */
    public static function render_honeypot() {
        echo '<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;overflow:hidden;">';
        printf(
            '<label>Website<input type="text" name="%s" tabindex="-1" autocomplete="off" value="" /></label>',
            esc_attr(self::HONEYPOT_FIELD)
        );
        echo '</div>';
    }

    /**
     * Enqueue the Turnstile JS lib (only when widget is on the page).
     */
    public static function enqueue_script() {
        if (!self::is_enabled()) {
            return;
        }
        wp_enqueue_script(
            'wcl-turnstile',
            self::SCRIPT_URL,
            array(),
            null,
            true
        );
    }

    /**
     * Honeypot check: returns true if request looks like a bot.
     * Bots fill every visible field they find.
     */
    public static function honeypot_tripped() {
        return isset($_POST[self::HONEYPOT_FIELD]) && trim((string) $_POST[self::HONEYPOT_FIELD]) !== '';
    }

    /**
     * Rate-limit by IP. Returns true if the IP is over its budget.
     * Uses transients; 5 attempts per hour per action by default.
     */
    public static function rate_limited($action, $max = 5, $window = HOUR_IN_SECONDS) {
        $ip = self::get_client_ip();
        if ($ip === '') {
            return false; // can't identify, don't block legit users
        }
        $key = 'wcl_rl_' . $action . '_' . md5($ip);
        $count = (int) get_transient($key);
        if ($count >= $max) {
            return true;
        }
        set_transient($key, $count + 1, $window);
        return false;
    }

    /**
     * Verify a Turnstile token against Cloudflare. Returns true on success.
     * Returns true if Turnstile is disabled (so caller doesn't have to branch).
     */
    public static function verify_token($token, $expected_action = '') {
        if (!self::is_enabled()) {
            return true;
        }
        if (empty($token)) {
            return false;
        }

        $response = wp_remote_post(self::VERIFY_URL, array(
            'timeout' => 10,
            'body' => array(
                'secret'   => self::get_secret_key(),
                'response' => $token,
                'remoteip' => self::get_client_ip(),
            ),
        ));

        if (is_wp_error($response)) {
            error_log('WCL Turnstile: verify HTTP error - ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['success'])) {
            if (is_array($body) && !empty($body['error-codes'])) {
                error_log('WCL Turnstile: verify failed - ' . implode(',', $body['error-codes']));
            }
            return false;
        }

        if ($expected_action !== '' && isset($body['action']) && $body['action'] !== $expected_action) {
            error_log('WCL Turnstile: action mismatch - expected ' . $expected_action . ', got ' . $body['action']);
            return false;
        }

        return true;
    }

    /**
     * One-shot guard: honeypot + rate limit + Turnstile.
     * Returns null on pass; a WP_Error on fail (with a generic, non-leaking message).
     */
    public static function guard($action) {
        if (self::honeypot_tripped()) {
            return new WP_Error('wcl_bot', __('Security check failed.', 'wp-content-locker'));
        }

        if (self::rate_limited($action)) {
            return new WP_Error('wcl_rate_limited', __('Too many attempts. Please try again later.', 'wp-content-locker'));
        }

        if (self::is_enabled()) {
            $token = isset($_POST[self::TOKEN_FIELD]) ? sanitize_text_field($_POST[self::TOKEN_FIELD]) : '';
            if (!self::verify_token($token, $action)) {
                return new WP_Error('wcl_turnstile', __('Verification failed. Please complete the security check and try again.', 'wp-content-locker'));
            }
        }

        return null;
    }

    /**
     * Best-effort client IP (respects common proxies but doesn't trust them blindly).
     */
    private static function get_client_ip() {
        $candidates = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($candidates as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '';
    }
}
