<?php
/**
 * Test Perplexity API Integration
 * 
 * This script tests the Perplexity API integration for AutoBlog
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Define WordPress-like constants for testing
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Include WordPress functions simulation
if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = '') {
        if (is_object($args)) {
            $parsed_args = get_object_vars($args);
        } elseif (is_array($args)) {
            $parsed_args = &$args;
        } else {
            wp_parse_str($args, $parsed_args);
        }

        if (is_array($defaults)) {
            return array_merge($defaults, $parsed_args);
        }
        return $parsed_args;
    }
}

if (!function_exists('wp_parse_str')) {
    function wp_parse_str($string, &$array) {
        parse_str($string, $array);
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        // Simulate WordPress get_option
        $options = array(
            'autoblog_settings' => array(
                'perplexity_api_key' => 'your-perplexity-api-key-here', // Replace with actual key for testing
                'blog_description' => 'A technology blog focused on AI and automation'
            )
        );
        return isset($options[$option]) ? $options[$option] : $default;
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = array();
        public $error_data = array();

        public function __construct($code = '', $message = '', $data = '') {
            if (empty($code)) {
                return;
            }
            $this->errors[$code][] = $message;
            if (!empty($data)) {
                $this->error_data[$code] = $data;
            }
        }

        public function get_error_message() {
            if (empty($this->errors)) {
                return '';
            }
            $code = array_keys($this->errors)[0];
            return $this->errors[$code][0];
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return ($thing instanceof WP_Error);
    }
}

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = array()) {
        // Simple cURL implementation
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $args['timeout'] ?? 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $args['headers'] ?? array());
        
        if (isset($args['method']) && $args['method'] === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (isset($args['body'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $args['body']);
            }
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return new WP_Error('http_request_failed', $error);
        }
        
        return array(
            'response' => array('code' => $http_code),
            'body' => $response
        );
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return $response['response']['code'] ?? 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return $response['body'] ?? '';
    }
}

if (!function_exists('error_log')) {
    function error_log($message) {
        echo "[LOG] " . $message . "\n";
    }
}

// Include the Perplexity class
require_once 'includes/class-autoblog-perplexity.php';

echo "<h1>AutoBlog Perplexity API Test</h1>\n";

try {
    // Initialize Perplexity class
    $perplexity = new AutoBlog_Perplexity();
    
    echo "<h2>1. Testing API Connection</h2>\n";
    $connection_test = $perplexity->test_connection();
    
    if ($connection_test) {
        echo "<p style='color: green;'>✓ Perplexity API connection successful!</p>\n";
        
        echo "<h2>2. Testing Basic Research</h2>\n";
        $research_result = $perplexity->research("What is artificial intelligence?", array(
            'max_tokens' => 500,
            'search_recency_filter' => 'month'
        ));
        
        if (!is_wp_error($research_result)) {
            echo "<p style='color: green;'>✓ Basic research successful!</p>\n";
            echo "<h3>Research Content:</h3>\n";
            echo "<div style='background: #f9f9f9; padding: 15px; border-radius: 5px;'>\n";
            echo "<p>" . htmlspecialchars(substr($research_result['content'], 0, 500)) . "...</p>\n";
            echo "</div>\n";
            
            if (!empty($research_result['citations'])) {
                echo "<h3>Sources:</h3>\n";
                echo "<ul>\n";
                foreach (array_slice($research_result['citations'], 0, 3) as $citation) {
                    if (is_array($citation) && !empty($citation['url'])) {
                        echo "<li><a href='" . htmlspecialchars($citation['url']) . "' target='_blank'>" . 
                             htmlspecialchars($citation['title'] ?? $citation['url']) . "</a></li>\n";
                    }
                }
                echo "</ul>\n";
            }
        } else {
            echo "<p style='color: red;'>✗ Basic research failed: " . $research_result->get_error_message() . "</p>\n";
        }
        
        echo "<h2>3. Testing Research Content Generation</h2>\n";
        $content_result = $perplexity->generate_research_content(
            "Latest trends in artificial intelligence", 
            "article", 
            "medium"
        );
        
        if (!is_wp_error($content_result)) {
            echo "<p style='color: green;'>✓ Research content generation successful!</p>\n";
            echo "<h3>Generated Research Data:</h3>\n";
            echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 5px;'>\n";
            echo "<p><strong>Topic:</strong> " . htmlspecialchars($content_result['topic']) . "</p>\n";
            echo "<p><strong>Content Type:</strong> " . htmlspecialchars($content_result['content_type']) . "</p>\n";
            echo "<p><strong>Sources Found:</strong> " . count($content_result['sources']) . "</p>\n";
            echo "<p><strong>Key Points:</strong> " . count($content_result['key_points']) . "</p>\n";
            
            if (!empty($content_result['sources'])) {
                echo "<h4>Top Sources:</h4>\n";
                echo "<ul>\n";
                foreach (array_slice($content_result['sources'], 0, 3) as $source) {
                    echo "<li><a href='" . htmlspecialchars($source['url']) . "' target='_blank'>" . 
                         htmlspecialchars($source['title']) . "</a> - " . htmlspecialchars($source['domain']) . "</li>\n";
                }
                echo "</ul>\n";
            }
            
            if (!empty($content_result['key_points'])) {
                echo "<h4>Key Points:</h4>\n";
                echo "<ul>\n";
                foreach (array_slice($content_result['key_points'], 0, 3) as $point) {
                    echo "<li>" . htmlspecialchars($point) . "</li>\n";
                }
                echo "</ul>\n";
            }
            echo "</div>\n";
        } else {
            echo "<p style='color: red;'>✗ Research content generation failed: " . $content_result->get_error_message() . "</p>\n";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Perplexity API connection failed. Please check your API key in the get_option simulation above.</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Test failed with exception: " . $e->getMessage() . "</p>\n";
}

echo "<h2>Test Complete</h2>\n";
echo "<p><strong>Note:</strong> To run this test with a real API key, update the 'perplexity_api_key' value in the get_option function above.</p>\n";
?>
