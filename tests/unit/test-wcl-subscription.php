<?php

use PHPUnit\Framework\TestCase;

class TestWCLSubscription extends TestCase {

    public function setUp(): void {
        \WP_Mock::setUp();
    }

    public function tearDown(): void {
        \WP_Mock::tearDown();
    }

    public function test_has_active_subscription_true() {
        global $wpdb;
        $wpdb = Mockery::mock('\wpdb');
        $wpdb->prefix = 'wp_';
        
        $wpdb->shouldReceive('prepare')->andReturn('SELECT COUNT(*) ...');
        $wpdb->shouldReceive('get_var')->andReturn('1'); // Count > 0

        $this->assertTrue(WCL_Subscription::has_active_subscription(1));
    }

    public function test_has_active_subscription_false() {
        global $wpdb;
        $wpdb = Mockery::mock('\wpdb');
        $wpdb->prefix = 'wp_';
        
        $wpdb->shouldReceive('prepare')->andReturn('SELECT COUNT(*) ...');
        $wpdb->shouldReceive('get_var')->andReturn('0'); // Count 0

        $this->assertFalse(WCL_Subscription::has_active_subscription(1));
    }

    public function test_resume_subscription_success() {
        global $wpdb;
        $wpdb = Mockery::mock('\wpdb');
        $wpdb->prefix = 'wp_';

        // Mock getting subscription
        $wpdb->shouldReceive('prepare')->once()->andReturn("SELECT * FROM wp_wcl_subscriptions WHERE id = 123");
        
        $subscription = new stdClass();
        $subscription->id = 123;
        $subscription->stripe_subscription_id = 'sub_stripe_123';
        $subscription->user_id = 1;
        
        $wpdb->shouldReceive('get_row')->once()->andReturn($subscription);

        // Mock Stripe API call (static method proxy via WCL_Stripe instance)
        // Since WCL_Stripe is a singleton and we can't easily mock private static instance without reflection or helper
        // But WCL_Stripe::get_instance() creates a new one if null.
        // We can mock the WCL_Stripe class if we use alias/overload, but sticking to simple WP_Mock:
        // We'll mock the `wp_remote_request` that WCL_Stripe calls internally.
        
        \WP_Mock::userFunction('get_option', array(
            'return' => 'sk_test_123',
        ));
        
        // Mock Stripe response for resume
        \WP_Mock::userFunction('wp_remote_request', array(
            'return' => array(
                'body' => json_encode(array('id' => 'sub_stripe_123', 'cancel_at_period_end' => false)),
                'response' => array('code' => 200)
            )
        ));

        \WP_Mock::userFunction('wp_remote_retrieve_body', array(
            'return' => json_encode(array('id' => 'sub_stripe_123', 'cancel_at_period_end' => false))
        ));

        \WP_Mock::userFunction('is_wp_error', array(
            'return' => false
        ));
        
        // Mock DB update
        $wpdb->shouldReceive('update')->once()->with(
            'wp_wcl_subscriptions',
            array('status' => 'active'),
            array('id' => 123),
            Mockery::any(),
            Mockery::any()
        )->andReturn(1);

        // Mock User Meta update
        \WP_Mock::userFunction('update_user_meta', array(
            'args' => array(1, '_wcl_subscription_status', 'active'),
            'return' => true
        ));

        $result = WCL_Subscription::resume_subscription(123);
        $this->assertTrue($result);
    }
}
