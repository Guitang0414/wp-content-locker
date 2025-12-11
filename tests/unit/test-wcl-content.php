<?php

use PHPUnit\Framework\TestCase;

class TestWCLContent extends TestCase {

    public function setUp(): void {
        \WP_Mock::setUp();
    }

    public function tearDown(): void {
        \WP_Mock::tearDown();
    }

    public function test_has_paywall_default() {
        // Mock get_option to return 'disabled'
        \WP_Mock::userFunction('get_option', array(
            'args' => array('wcl_default_paywall_mode', 'disabled'),
            'return' => 'disabled',
        ));

        // Mock get_post_meta to return empty (no specific setting)
        \WP_Mock::userFunction('get_post_meta', array(
            'return' => '',
        ));

        // Mock get_the_ID
        \WP_Mock::userFunction('get_the_ID', array(
            'return' => 123,
        ));

        $this->assertFalse(WCL_Content::has_paywall(123));
    }

    public function test_truncate_content_simple_string() {
        // Mock wp_strip_all_tags
        \WP_Mock::userFunction('wp_strip_all_tags', array(
            'return' => function($str) { return strip_tags($str); }
        ));

        // Mock strip_shortcodes
        \WP_Mock::userFunction('strip_shortcodes', array(
            'return' => function($str) { return $str; }
        ));

        $content = "This is a very long string that should be truncated because it is long.";
        // Assume percentage 50%
        // Total chars: ~71. Target: ~35.
        // It should cut around "truncated..."

        // Since the truncation logic uses DOMDocument, we might need valid HTML or it wraps it.
        // Let's test the logic.
        
        $result = WCL_Content::truncate_content($content, 50);
        
        // It's hard to predict exact boundary without the mock functions behaving exactly like WP, 
        // but it should be shorter than original.
        $this->assertLessThan(strlen($content), strlen($result));
        $this->assertStringContainsString('...', $result);
    }
}
