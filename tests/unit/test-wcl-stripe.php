<?php

use PHPUnit\Framework\TestCase;

class TestWCLStripe extends TestCase {

    public function setUp(): void {
        \WP_Mock::setUp();
    }

    public function tearDown(): void {
        \WP_Mock::tearDown();
    }

    public function test_get_mode_default() {
        // Mock get_option
        \WP_Mock::userFunction('get_option', array(
            'args' => array('wcl_stripe_mode', 'test'),
            'return' => 'test',
        ));

        // Mock current_user_can
        \WP_Mock::userFunction('current_user_can', array(
            'return' => false,
        ));

        $this->assertEquals('test', WCL_Stripe::get_mode());
    }

    public function test_get_mode_admin_override() {
        // Simulate URL param
        $_GET['wcl_test_mode'] = '1';

        // Mock current_user_can
        \WP_Mock::userFunction('current_user_can', array(
            'args' => array('manage_options'),
            'return' => true,
        ));

        $this->assertEquals('test', WCL_Stripe::get_mode());
        
        // Cleanup
        unset($_GET['wcl_test_mode']);
    }

    public function test_create_checkout_session_params() {
        $stripe = WCL_Stripe::get_instance();
        
        // Mock get_option calls for keys
        \WP_Mock::userFunction('get_option', array(
            'return' => 'sk_test_123',
        ));

        // Mock wp_remote_request to verify arguments
        \WP_Mock::userFunction('wp_remote_request', array(
            'return' => array(
                'body' => json_encode(array('id' => 'cs_test_123')),
                'response' => array('code' => 200)
            ),
            'args' => array(
                'https://api.stripe.com/v1/checkout/sessions',
                \Mockery::type('array')
            )
        ));

        \WP_Mock::userFunction('wp_remote_retrieve_body', array(
            'return' => json_encode(array('id' => 'cs_test_123'))
        ));

        \WP_Mock::userFunction('is_wp_error', array(
            'return' => false
        ));

        $params = array(
            'price_id' => 'price_123',
            'success_url' => 'http://example.com/success',
            'cancel_url' => 'http://example.com/cancel',
        );

        $result = $stripe->create_checkout_session($params);
        $this->assertEquals('cs_test_123', $result['id']);
    }

    public function test_resume_subscription() {
        $stripe = WCL_Stripe::get_instance();

        // Mock get_option for secret key
        \WP_Mock::userFunction('get_option', array(
            'return' => 'sk_test_123'
        ));

        // Verify request to resume (cancel_at_period_end = false)
        \WP_Mock::userFunction('wp_remote_request', array(
            'return' => array(
                'body' => json_encode(array('id' => 'sub_123', 'cancel_at_period_end' => false)),
                'response' => array('code' => 200)
            ),
            'args' => array(
                'https://api.stripe.com/v1/subscriptions/sub_123',
                \Mockery::type('array')
            )
        ));

        \WP_Mock::userFunction('wp_remote_retrieve_body', array(
            'return' => json_encode(array('id' => 'sub_123', 'cancel_at_period_end' => false))
        ));

        \WP_Mock::userFunction('is_wp_error', array(
            'return' => false
        ));

        $result = $stripe->resume_subscription('sub_123');
        $this->assertFalse($result['cancel_at_period_end']);
    }
}
