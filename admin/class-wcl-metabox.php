<?php
/**
 * Post meta box for enabling paywall on individual posts
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCL_Metabox {

    /**
     * Add meta box to post editor
     */
    public function add_meta_box() {
        add_meta_box(
            'wcl_paywall_metabox',
            __('Content Locker', 'wp-content-locker'),
            array($this, 'render_meta_box'),
            'post',
            'side',
            'high'
        );
    }

    /**
     * Render meta box content
     */
    public function render_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('wcl_save_metabox', 'wcl_metabox_nonce');

        $enabled = get_post_meta($post->ID, '_wcl_enable_paywall', true);
        $preview_percentage = get_post_meta($post->ID, '_wcl_preview_percentage', true);
        $default_mode = get_option('wcl_default_paywall_mode', 'disabled');

        // Use global setting if not set
        if ($preview_percentage === '') {
            $preview_percentage = get_option('wcl_preview_percentage', 30);
        }

        // Is paywall currently active?
        $is_checked = ($enabled === 'yes');
        ?>
        <div class="wcl-metabox-content">
            <p class="wcl-default-notice" style="background:#f0f0f1;padding:8px 10px;border-left:3px solid #2271b1;margin:0 0 10px;">
                <?php if ($default_mode === 'enabled') : ?>
                    <em><?php _e('Global default: Enabled', 'wp-content-locker'); ?></em>
                <?php else : ?>
                    <em><?php _e('Global default: Disabled', 'wp-content-locker'); ?></em>
                <?php endif; ?>
            </p>

            <p>
                <label>
                    <input type="checkbox"
                           name="wcl_enable_paywall"
                           id="wcl_enable_paywall"
                           value="yes"
                           <?php checked($is_checked, true); ?> />
                    <strong><?php _e('Enable paywall for this post', 'wp-content-locker'); ?></strong>
                </label>
            </p>

            <p>
                <label for="wcl_preview_percentage">
                    <?php _e('Preview percentage:', 'wp-content-locker'); ?>
                </label>
                <input type="number"
                       name="wcl_preview_percentage"
                       id="wcl_preview_percentage"
                       value="<?php echo esc_attr($preview_percentage); ?>"
                       min="10" max="90" style="width: 60px;" /> %
                <br>
                <span class="description" style="color:#646970;font-size:12px;">
                    <?php _e('How much content to show before paywall.', 'wp-content-locker'); ?>
                </span>
            </p>

            <?php if ($enabled === 'yes') : ?>
            <p style="color:green;">
                <strong><?php _e('Current status: Paywall is ON', 'wp-content-locker'); ?></strong>
            </p>
            <?php else : ?>
            <p style="color:#666;">
                <?php _e('Current status: Paywall is OFF', 'wp-content-locker'); ?>
            </p>
            <?php endif; ?>

            <?php
            // Show debug info
            $debug = get_option('wcl_debug_save', array());
            if (!empty($debug)) :
            ?>
            <hr>
            <p><strong>Debug (last save attempt):</strong></p>
            <pre style="font-size:11px;background:#f5f5f5;padding:5px;overflow:auto;"><?php print_r($debug); ?></pre>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_meta_box($post_id) {
        // Debug log
        $debug = array();
        $debug['post_id'] = $post_id;
        $debug['nonce_isset'] = isset($_POST['wcl_metabox_nonce']);
        $debug['checkbox_isset'] = isset($_POST['wcl_enable_paywall']);
        $debug['checkbox_value'] = isset($_POST['wcl_enable_paywall']) ? $_POST['wcl_enable_paywall'] : 'not set';

        // Check nonce
        if (!isset($_POST['wcl_metabox_nonce'])) {
            $debug['exit_reason'] = 'nonce not set';
            update_option('wcl_debug_save', $debug);
            return;
        }

        if (!wp_verify_nonce($_POST['wcl_metabox_nonce'], 'wcl_save_metabox')) {
            $debug['exit_reason'] = 'nonce verification failed';
            update_option('wcl_debug_save', $debug);
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            $debug['exit_reason'] = 'autosave';
            update_option('wcl_debug_save', $debug);
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            $debug['exit_reason'] = 'no permission';
            update_option('wcl_debug_save', $debug);
            return;
        }

        // Check post type
        if (get_post_type($post_id) !== 'post') {
            $debug['exit_reason'] = 'not a post type';
            update_option('wcl_debug_save', $debug);
            return;
        }

        // Save enable paywall - checkbox version
        if (isset($_POST['wcl_enable_paywall']) && $_POST['wcl_enable_paywall'] === 'yes') {
            $result = update_post_meta($post_id, '_wcl_enable_paywall', 'yes');
            $debug['save_result'] = $result;
            $debug['saved_value'] = 'yes';
        } else {
            delete_post_meta($post_id, '_wcl_enable_paywall');
            $debug['saved_value'] = 'deleted';
        }

        // Save preview percentage
        if (isset($_POST['wcl_preview_percentage']) && $_POST['wcl_preview_percentage'] !== '') {
            $percentage = absint($_POST['wcl_preview_percentage']);
            $percentage = max(10, min(90, $percentage));
            update_post_meta($post_id, '_wcl_preview_percentage', $percentage);
        } else {
            delete_post_meta($post_id, '_wcl_preview_percentage');
        }

        $debug['success'] = true;
        update_option('wcl_debug_save', $debug);
    }
}

