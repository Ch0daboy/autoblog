<?php
/**
 * Test script for OpenAI API integration
 * Run this to verify the API is working with updated models
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-config.php';
require_once dirname(__FILE__) . '/includes/class-autoblog-openai.php';

// Test the OpenAI integration
function test_openai_integration() {
    echo "Testing OpenAI API Integration...\n\n";
    
    // Initialize the OpenAI class
    $openai = new AutoBlog_OpenAI();
    
    // Test 1: Check if API key is configured
    echo "1. Checking API key configuration...\n";
    $settings = get_option('autoblog_settings', array());
    $api_key = $settings['openai_api_key'] ?? '';
    
    if (empty($api_key)) {
        echo "❌ No API key configured. Please set your OpenAI API key in the plugin settings.\n";
        return false;
    }
    echo "✅ API key is configured.\n\n";
    
    // Test 2: Test API connection
    echo "2. Testing API connection...\n";
    $connection_test = $openai->test_connection();
    
    if (is_wp_error($connection_test) || !$connection_test) {
        if (is_wp_error($connection_test)) {
            echo "❌ Connection failed: " . $connection_test->get_error_message() . "\n";
        } else {
            echo "❌ Connection test failed.\n";
        }
        return false;
    }
    echo "✅ API connection successful.\n\n";
    
    // Test 3: Test content generation with new model
    echo "3. Testing content generation with gpt-4o-mini...\n";
    $test_params = array(
        'post_type' => 'how-to',
        'topic' => 'How to test WordPress plugins'
    );
    
    $result = $openai->generate_post($test_params);
    
    if (is_wp_error($result)) {
        echo "❌ Content generation failed: " . $result->get_error_message() . "\n";
        return false;
    }
    
    if (isset($result['title']) && isset($result['content'])) {
        echo "✅ Content generation successful!\n";
        echo "Generated title: " . $result['title'] . "\n";
        echo "Content length: " . strlen($result['content']) . " characters\n\n";
    } else {
        echo "❌ Content generation returned unexpected format.\n";
        return false;
    }
    
    // Test 4: Test comment reply generation
    echo "4. Testing comment reply generation...\n";
    $reply = $openai->generate_comment_reply(
        "This is a great article! Thanks for sharing.",
        "Test Post Title",
        "Test post content about WordPress plugins."
    );
    
    if ($reply && !is_wp_error($reply)) {
        echo "✅ Comment reply generation successful!\n";
        echo "Generated reply: " . substr($reply, 0, 100) . "...\n\n";
    } else {
        echo "❌ Comment reply generation failed.\n";
        return false;
    }
    
    echo "🎉 All tests passed! OpenAI API integration is working correctly.\n";
    return true;
}

// Run the test
if (php_sapi_name() === 'cli') {
    test_openai_integration();
} else {
    echo "<pre>";
    test_openai_integration();
    echo "</pre>";
}
?>