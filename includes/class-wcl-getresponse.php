<?php
/**
 * GetResponse API integration — verify if an email is a contact in a configured campaign (list).
 *
 * Stage 1 scope: a single configured campaignId to check the current WP user's email against,
 * surface ✅/❌ on the account page. Caches results per (email, campaign) in a transient so
 * we don't pound GR's API on every page load.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCL_GetResponse {

    const API_BASE = 'https://api.getresponse.com/v3';
    const CACHE_PREFIX = 'wcl_gr_';
    const DEFAULT_TTL = HOUR_IN_SECONDS;

    /**
     * Integration is "ready" only with both an API key AND a campaignId set.
     */
    public static function is_enabled() {
        return self::get_api_key() !== '' && self::get_campaign_id() !== '';
    }

    public static function get_api_key() {
        return trim((string) get_option('wcl_gr_api_key', ''));
    }

    public static function get_campaign_id() {
        return trim((string) get_option('wcl_gr_campaign_id', ''));
    }

    public static function get_campaign_label() {
        $label = trim((string) get_option('wcl_gr_campaign_label', ''));
        return $label !== '' ? $label : __('Newsletter subscription', 'wp-content-locker');
    }

    public static function get_subscribe_url() {
        return trim((string) get_option('wcl_gr_subscribe_url', ''));
    }

    /**
     * Check whether an email is a contact in the configured (or given) campaign.
     * Returns true / false / null (null = call failed, treat as "unknown" — don't break page).
     */
    public static function is_email_in_campaign($email, $campaign_id = null) {
        $email = trim((string) $email);
        if ($email === '' || !is_email($email)) {
            return false;
        }
        if ($campaign_id === null) {
            $campaign_id = self::get_campaign_id();
        }
        if ($campaign_id === '' || !self::get_api_key()) {
            return null;
        }

        $cache_key = self::CACHE_PREFIX . 'check_' . md5(strtolower($email) . '|' . $campaign_id);
        $cached = get_transient($cache_key);
        if ($cached === 'yes') return true;
        if ($cached === 'no')  return false;

        $resp = self::request('contacts', array(
            'query[email]'      => $email,
            'query[campaignId]' => $campaign_id,
        ));

        if (is_wp_error($resp)) {
            // Don't cache failures — try again next time.
            return null;
        }

        $in = is_array($resp) && count($resp) > 0;
        set_transient($cache_key, $in ? 'yes' : 'no', self::DEFAULT_TTL);
        return $in;
    }

    /**
     * Force re-check by busting the cached entry for (email, campaign).
     */
    public static function bust_cache($email, $campaign_id = null) {
        if ($campaign_id === null) {
            $campaign_id = self::get_campaign_id();
        }
        $cache_key = self::CACHE_PREFIX . 'check_' . md5(strtolower($email) . '|' . $campaign_id);
        delete_transient($cache_key);
    }

    /**
     * For the admin "Test connection" button. Returns array on success, WP_Error on fail.
     */
    public static function ping_account() {
        return self::request('accounts');
    }

    /**
     * For the admin campaign dropdown. Returns array of {campaignId, name} on success.
     */
    public static function list_campaigns() {
        $resp = self::request('campaigns');
        if (is_wp_error($resp) || !is_array($resp)) {
            return array();
        }
        $out = array();
        foreach ($resp as $c) {
            if (isset($c['campaignId'], $c['name'])) {
                $out[] = array(
                    'campaignId' => $c['campaignId'],
                    'name'       => $c['name'],
                );
            }
        }
        return $out;
    }

    /**
     * Raw GET request. Returns decoded JSON (usually array), or WP_Error on transport/HTTP error.
     */
    private static function request($endpoint, $query = array()) {
        $key = self::get_api_key();
        if ($key === '') {
            return new WP_Error('wcl_gr_no_key', __('GetResponse API key not configured.', 'wp-content-locker'));
        }

        $url = self::API_BASE . '/' . ltrim($endpoint, '/');
        if (!empty($query)) {
            $url = add_query_arg($query, $url);
        }

        $resp = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'X-Auth-Token' => 'api-key ' . $key,
                'Content-Type' => 'application/json',
            ),
        ));

        if (is_wp_error($resp)) {
            error_log('WCL GetResponse: ' . $resp->get_error_message());
            return $resp;
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);

        if ($code < 200 || $code >= 300) {
            $msg = is_array($body) && isset($body['message']) ? $body['message'] : 'HTTP ' . $code;
            error_log('WCL GetResponse: ' . $endpoint . ' failed - ' . $msg);
            return new WP_Error('wcl_gr_http', $msg);
        }

        return $body;
    }
}
