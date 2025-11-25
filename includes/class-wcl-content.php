<?php
/**
 * Content handling class - truncation and display
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCL_Content {

    /**
     * Check if post has paywall enabled
     */
    public static function has_paywall($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        // Get global default mode
        $default_mode = get_option('wcl_default_paywall_mode', 'disabled');

        // Get post-specific setting
        $post_setting = get_post_meta($post_id, '_wcl_enable_paywall', true);

        // If post has explicit setting, use it
        if ($post_setting === 'yes') {
            return true;
        } elseif ($post_setting === 'no') {
            return false;
        }

        // Otherwise, use global default
        // 'enabled' means paywall is on by default
        // 'disabled' means paywall is off by default
        return $default_mode === 'enabled';
    }

    /**
     * Check if current user can access full content
     */
    public static function user_can_access($post_id = null) {
        // Logged in users with active subscription can access
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            return WCL_Subscription::has_active_subscription($user_id);
        }
        return false;
    }

    /**
     * Truncate content to percentage based on character count
     */
    public static function truncate_content($content, $percentage = 30) {
        // Strip shortcodes and tags for accurate character count
        $plain_text = wp_strip_all_tags(strip_shortcodes($content));
        $total_chars = mb_strlen($plain_text);
        $target_chars = (int) ($total_chars * ($percentage / 100));

        if ($target_chars >= $total_chars) {
            return $content;
        }

        // We need to truncate the HTML content while preserving tags
        return self::truncate_html($content, $target_chars);
    }

    /**
     * Truncate HTML content while preserving tag structure
     */
    private static function truncate_html($html, $max_chars) {
        $dom = new DOMDocument();
        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding('<div>' . $html . '</div>', 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $char_count = 0;
        $truncated = false;

        self::truncate_node($dom->documentElement, $max_chars, $char_count, $truncated);

        $result = $dom->saveHTML($dom->documentElement);
        // Remove wrapper div
        $result = preg_replace('/^<div>|<\/div>$/i', '', $result);

        return $result;
    }

    /**
     * Recursively truncate DOM nodes
     */
    private static function truncate_node($node, $max_chars, &$char_count, &$truncated) {
        if ($truncated) {
            return;
        }

        if ($node->nodeType === XML_TEXT_NODE) {
            $text = $node->nodeValue;
            $text_length = mb_strlen($text);

            if ($char_count + $text_length > $max_chars) {
                // Find a good breaking point (word boundary)
                $remaining = $max_chars - $char_count;
                $break_pos = self::find_word_boundary($text, $remaining);
                $node->nodeValue = mb_substr($text, 0, $break_pos) . '...';
                $truncated = true;
            }
            $char_count += $text_length;
        } elseif ($node->nodeType === XML_ELEMENT_NODE) {
            // Skip script and style tags
            if (in_array(strtolower($node->nodeName), array('script', 'style'))) {
                return;
            }

            $children_to_remove = array();

            foreach ($node->childNodes as $child) {
                if ($truncated) {
                    $children_to_remove[] = $child;
                } else {
                    self::truncate_node($child, $max_chars, $char_count, $truncated);
                }
            }

            // Remove children that come after truncation point
            foreach ($children_to_remove as $child) {
                $node->removeChild($child);
            }
        }
    }

    /**
     * Find word boundary for clean truncation
     */
    private static function find_word_boundary($text, $position) {
        if ($position >= mb_strlen($text)) {
            return mb_strlen($text);
        }

        // Look for space, period, comma, or newline near the position
        $search_range = min(50, $position);
        $start = max(0, $position - $search_range);
        $substring = mb_substr($text, $start, $search_range * 2);

        // Find the last space before or near position
        $last_space = mb_strrpos(mb_substr($substring, 0, $search_range + 10), ' ');
        if ($last_space !== false) {
            return $start + $last_space;
        }

        return $position;
    }

    /**
     * Get the paywall HTML
     */
    public static function get_paywall_html($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        $title = get_option('wcl_paywall_title', __('Premium Content', 'wp-content-locker'));
        $description = get_option('wcl_paywall_description', __('Subscribe to read the full article.', 'wp-content-locker'));
        $button_text = get_option('wcl_subscribe_button_text', __('Subscribe Now', 'wp-content-locker'));
        $monthly_price = get_option('wcl_monthly_price', '9.99');
        $yearly_price = get_option('wcl_yearly_price', '99.99');

        ob_start();
        include WCL_PLUGIN_DIR . 'templates/paywall.php';
        return ob_get_clean();
    }

    /**
     * Apply paywall to content if needed
     */
    public static function apply_paywall($content, $post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        // Check if paywall is enabled for this post
        if (!self::has_paywall($post_id)) {
            return $content;
        }

        // Check if user can access
        if (self::user_can_access($post_id)) {
            return $content;
        }

        // Truncate content and add paywall
        // Check for post-specific percentage first, then fall back to global setting
        $post_percentage = get_post_meta($post_id, '_wcl_preview_percentage', true);
        $percentage = !empty($post_percentage) ? (int) $post_percentage : (int) get_option('wcl_preview_percentage', 30);
        $truncated_content = self::truncate_content($content, $percentage);
        $paywall_html = self::get_paywall_html($post_id);

        return '<div class="wcl-content-wrapper">' .
               '<div class="wcl-preview-content">' . $truncated_content . '</div>' .
               '<div class="wcl-fade-overlay"></div>' .
               $paywall_html .
               '</div>';
    }
}
