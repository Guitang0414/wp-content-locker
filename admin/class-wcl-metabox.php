<?php
/**
 * Post meta box for enabling paywall on individual posts
 */

if (!defined('ABSPATH')) {
    exit;
}

class WCL_Metabox {

    /**
     * Constructor - register meta for REST API / Gutenberg support
     */
    public function __construct() {
        add_action('init', array($this, 'register_meta'));
    }

    /**
     * Register meta fields for REST API (Gutenberg support)
     */
    public function register_meta() {
        register_post_meta('post', '_wcl_enable_paywall', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));

        register_post_meta('post', '_wcl_preview_percentage', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'integer',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }

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
        $enabled = get_post_meta($post->ID, '_wcl_enable_paywall', true);
        $preview_percentage = get_post_meta($post->ID, '_wcl_preview_percentage', true);
        $default_mode = get_option('wcl_default_paywall_mode', 'disabled');

        // Use global setting if not set
        if ($preview_percentage === '' || $preview_percentage === 0) {
            $preview_percentage = get_option('wcl_preview_percentage', 30);
        }

        $is_checked = ($enabled === 'yes');
        ?>
        <div class="wcl-metabox-content" id="wcl-metabox">
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
                       id="wcl_preview_percentage"
                       value="<?php echo esc_attr($preview_percentage); ?>"
                       min="10" max="90" style="width: 60px;" /> %
            </p>

            <p id="wcl-status" style="<?php echo $is_checked ? 'color:green;' : 'color:#666;'; ?>">
                <?php if ($is_checked) : ?>
                    <strong><?php _e('Status: Paywall is ON', 'wp-content-locker'); ?></strong>
                <?php else : ?>
                    <?php _e('Status: Paywall is OFF', 'wp-content-locker'); ?>
                <?php endif; ?>
            </p>

            <p id="wcl-save-status" style="display:none;color:#0073aa;"></p>
        </div>

        <script>
        (function() {
            var postId = <?php echo $post->ID; ?>;
            var checkbox = document.getElementById('wcl_enable_paywall');
            var percentInput = document.getElementById('wcl_preview_percentage');
            var statusEl = document.getElementById('wcl-status');
            var saveStatusEl = document.getElementById('wcl-save-status');

            function updateStatus(isEnabled) {
                if (isEnabled) {
                    statusEl.innerHTML = '<strong><?php _e('Status: Paywall is ON', 'wp-content-locker'); ?></strong>';
                    statusEl.style.color = 'green';
                } else {
                    statusEl.innerHTML = '<?php _e('Status: Paywall is OFF', 'wp-content-locker'); ?>';
                    statusEl.style.color = '#666';
                }
            }

            function saveMeta() {
                var value = checkbox.checked ? 'yes' : '';
                var percent = parseInt(percentInput.value) || 30;

                saveStatusEl.style.display = 'block';
                saveStatusEl.innerHTML = '<?php _e('Saving...', 'wp-content-locker'); ?>';

                // Use WordPress REST API
                wp.apiFetch({
                    path: '/wp/v2/posts/' + postId,
                    method: 'POST',
                    data: {
                        meta: {
                            _wcl_enable_paywall: value,
                            _wcl_preview_percentage: percent
                        }
                    }
                }).then(function(response) {
                    saveStatusEl.innerHTML = '<?php _e('Saved!', 'wp-content-locker'); ?>';
                    saveStatusEl.style.color = 'green';
                    setTimeout(function() {
                        saveStatusEl.style.display = 'none';
                    }, 2000);
                    updateStatus(checkbox.checked);
                }).catch(function(error) {
                    saveStatusEl.innerHTML = '<?php _e('Error saving. Please try again.', 'wp-content-locker'); ?>';
                    saveStatusEl.style.color = 'red';
                    console.error('WCL Save Error:', error);
                });
            }

            // Save on checkbox change
            checkbox.addEventListener('change', function() {
                saveMeta();
            });

            // Save on percentage change (debounced)
            var debounceTimer;
            percentInput.addEventListener('change', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(saveMeta, 500);
            });
        })();
        </script>
        <?php
    }

    /**
     * Save meta box data - for classic editor fallback
     */
    public function save_meta_box($post_id) {
        // Skip if this is a REST request (Gutenberg handles it)
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        // Check if our nonce is set (classic editor)
        if (!isset($_POST['wcl_metabox_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['wcl_metabox_nonce'], 'wcl_save_metabox')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (get_post_type($post_id) !== 'post') {
            return;
        }

        // Save enable paywall
        if (isset($_POST['wcl_enable_paywall']) && $_POST['wcl_enable_paywall'] === 'yes') {
            update_post_meta($post_id, '_wcl_enable_paywall', 'yes');
        } else {
            delete_post_meta($post_id, '_wcl_enable_paywall');
        }

        // Save preview percentage
        if (isset($_POST['wcl_preview_percentage']) && $_POST['wcl_preview_percentage'] !== '') {
            $percentage = absint($_POST['wcl_preview_percentage']);
            $percentage = max(10, min(90, $percentage));
            update_post_meta($post_id, '_wcl_preview_percentage', $percentage);
        }
    }
}
