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

        $post_setting = get_post_meta($post->ID, '_wcl_enable_paywall', true);
        $preview_percentage = get_post_meta($post->ID, '_wcl_preview_percentage', true);
        $default_mode = get_option('wcl_default_paywall_mode', 'disabled');

        // Determine if paywall is currently active for this post
        $is_paywall_active = ($post_setting === 'yes') || ($post_setting === '' && $default_mode === 'enabled');

        // Use global setting if not set
        if ($preview_percentage === '') {
            $preview_percentage = get_option('wcl_preview_percentage', 30);
        }
        ?>
        <div class="wcl-metabox-content">
            <?php if ($default_mode === 'enabled') : ?>
                <!-- Default is ENABLED: show option to disable -->
                <p class="wcl-default-notice">
                    <em><?php _e('Paywall is enabled by default for all posts.', 'wp-content-locker'); ?></em>
                </p>
                <p>
                    <label>
                        <input type="radio" name="wcl_enable_paywall" value="" <?php checked($post_setting, ''); ?> />
                        <?php _e('Use default (enabled)', 'wp-content-locker'); ?>
                    </label>
                </p>
                <p>
                    <label>
                        <input type="radio" name="wcl_enable_paywall" value="no" <?php checked($post_setting, 'no'); ?> />
                        <?php _e('Disable paywall for this post', 'wp-content-locker'); ?>
                    </label>
                </p>
                <p>
                    <label>
                        <input type="radio" name="wcl_enable_paywall" value="yes" <?php checked($post_setting, 'yes'); ?> />
                        <?php _e('Enable paywall (explicit)', 'wp-content-locker'); ?>
                    </label>
                </p>
            <?php else : ?>
                <!-- Default is DISABLED: show option to enable -->
                <p class="wcl-default-notice">
                    <em><?php _e('Paywall is disabled by default for all posts.', 'wp-content-locker'); ?></em>
                </p>
                <p>
                    <label>
                        <input type="radio" name="wcl_enable_paywall" value="" <?php checked($post_setting, ''); ?> />
                        <?php _e('Use default (disabled)', 'wp-content-locker'); ?>
                    </label>
                </p>
                <p>
                    <label>
                        <input type="radio" name="wcl_enable_paywall" value="yes" <?php checked($post_setting, 'yes'); ?> />
                        <?php _e('Enable paywall for this post', 'wp-content-locker'); ?>
                    </label>
                </p>
                <p>
                    <label>
                        <input type="radio" name="wcl_enable_paywall" value="no" <?php checked($post_setting, 'no'); ?> />
                        <?php _e('Disable paywall (explicit)', 'wp-content-locker'); ?>
                    </label>
                </p>
            <?php endif; ?>

            <hr style="margin: 15px 0;">

            <p class="wcl-preview-percentage-wrap" style="<?php echo !$is_paywall_active ? 'opacity: 0.5;' : ''; ?>">
                <label for="wcl_preview_percentage">
                    <?php _e('Preview percentage:', 'wp-content-locker'); ?>
                </label>
                <input type="number" name="wcl_preview_percentage" id="wcl_preview_percentage"
                       value="<?php echo esc_attr($preview_percentage); ?>"
                       min="10" max="90" style="width: 60px;" /> %
                <br>
                <span class="description"><?php _e('Leave empty to use global setting.', 'wp-content-locker'); ?></span>
            </p>
        </div>

        <style>
            .wcl-default-notice {
                background: #f0f0f1;
                padding: 8px 10px;
                border-left: 3px solid #2271b1;
                margin: 0 0 10px;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('input[name="wcl_enable_paywall"]').on('change', function() {
                var value = $('input[name="wcl_enable_paywall"]:checked').val();
                var defaultMode = '<?php echo esc_js($default_mode); ?>';
                var isActive = (value === 'yes') || (value === '' && defaultMode === 'enabled');

                if (isActive) {
                    $('.wcl-preview-percentage-wrap').css('opacity', '1');
                } else {
                    $('.wcl-preview-percentage-wrap').css('opacity', '0.5');
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_meta_box($post_id) {
        // Check nonce
        if (!isset($_POST['wcl_metabox_nonce']) ||
            !wp_verify_nonce($_POST['wcl_metabox_nonce'], 'wcl_save_metabox')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save enable paywall
        // Values: 'yes' = explicitly enabled, 'no' = explicitly disabled, '' = use default
        if (isset($_POST['wcl_enable_paywall'])) {
            $enable_paywall = sanitize_text_field($_POST['wcl_enable_paywall']);
            if (in_array($enable_paywall, array('yes', 'no', ''))) {
                if ($enable_paywall === '') {
                    delete_post_meta($post_id, '_wcl_enable_paywall');
                } else {
                    update_post_meta($post_id, '_wcl_enable_paywall', $enable_paywall);
                }
            }
        }

        // Save preview percentage
        if (isset($_POST['wcl_preview_percentage']) && $_POST['wcl_preview_percentage'] !== '') {
            $percentage = absint($_POST['wcl_preview_percentage']);
            $percentage = max(10, min(90, $percentage));
            update_post_meta($post_id, '_wcl_preview_percentage', $percentage);
        } else {
            delete_post_meta($post_id, '_wcl_preview_percentage');
        }
    }
}
