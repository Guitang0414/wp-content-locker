<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class WCL_Subscriptions_List_Table extends WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => __('Subscription', 'wp-content-locker'),
            'plural'   => __('Subscriptions', 'wp-content-locker'),
            'ajax'     => false
        ));
    }

    /**
     * Get columns
     */
    public function get_columns() {
        $columns = array(
            'cb'            => '<input type="checkbox" />',
            'id'            => __('ID', 'wp-content-locker'),
            'user'          => __('User', 'wp-content-locker'),
            'plan_type'     => __('Plan', 'wp-content-locker'),
            'mode'          => __('Mode', 'wp-content-locker'),
            'status'        => __('Status', 'wp-content-locker'),
            'started'       => __('Started', 'wp-content-locker'),
            'ends'          => __('Ends', 'wp-content-locker'),
            'stripe_id'     => __('Stripe ID', 'wp-content-locker')
        );
        return $columns;
    }

    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'id'        => array('id', false),
            'started'   => array('created_at', false),
            'status'    => array('status', false),
            'user'      => array('user_id', false)
        );
        return $sortable_columns;
    }

    /**
     * Render checkbox column
     */
    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="subscription_ids[]" value="%s" />',
            $item['id']
        );
    }

    /**
     * Render User column
     */
    protected function column_user($item) {
        $user_id = $item['user_id'];
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return __('Unknown User', 'wp-content-locker');
        }

        $avatar = get_avatar($user_id, 32);
        $user_name = sprintf(
            '<a href="%s"><strong>%s</strong></a>',
            get_edit_user_link($user_id),
            $user->display_name
        );
        $user_email = sprintf('<span class="email">%s</span>', $user->user_email);

        // Actions
        $actions = array(
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
                wp_nonce_url(add_query_arg(array('action' => 'delete', 'id' => $item['id'])), 'wcl_delete_subscription'),
                __('Are you sure you want to delete this subscription?', 'wp-content-locker'),
                __('Delete', 'wp-content-locker')
            )
        );

        return sprintf('%s %s<br>%s %s', $avatar, $user_name, $user_email, $this->row_actions($actions));
    }

    /**
     * Render Status column
     */
    protected function column_status($item) {
        $status = $item['status'];
        $label = WCL_Subscription::get_status_label($status);
        $color = 'gray';

        switch ($status) {
            case 'active':
                $color = 'green';
                break;
            case 'canceled':
                $color = 'red';
                break;
            case 'past_due':
                $color = 'orange';
                break;
        }

        return sprintf(
            '<span style="color:%s;font-weight:bold;">%s</span>',
            esc_attr($color),
            esc_html($label)
        );
    }

    /**
     * Render default columns
     */
    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
            case 'plan_type':
            case 'mode':
            case 'stripe_id':
                return esc_html($item[$column_name]);
            case 'started':
                return esc_html($item['created_at']); // Using created_at as started date
            case 'ends':
                return esc_html($item['current_period_end']);
            default:
                return print_r($item, true);
        }
    }

    /**
     * Prepare items
     */
    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcl_subscriptions';

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        // Handle Search
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $where = "WHERE 1=1";
        
        if (!empty($search)) {
            $where .= $wpdb->prepare(
                " AND (stripe_customer_id LIKE %s OR stripe_subscription_id LIKE %s OR user_id IN (SELECT ID FROM {$wpdb->users} WHERE user_email LIKE %s OR user_login LIKE %s))",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }

        // Handle Sorting
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
        
        // whitelist orderby
        $allowed_orderby = array('id', 'created_at', 'status', 'user_id');
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'created_at';
        }

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where");

        $data = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_name $where ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, $offset),
            ARRAY_A
        );

        $this->items = $data;

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }
}
