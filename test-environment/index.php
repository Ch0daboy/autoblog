<?php
/**
 * Simple WordPress Plugin Tester
 * 
 * This file provides a basic testing environment for the AutoBlog plugin
 * without requiring a full WordPress installation.
 */

// Simulate WordPress environment
define('ABSPATH', __DIR__ . '/');
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Mock WordPress functions for basic testing
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Mock implementation
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        // Mock implementation
        return true;
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {
        // Mock implementation
        return true;
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {
        // Mock implementation
        return true;
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://localhost:8000/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('current_time')) {
    function current_time($type = 'mysql', $gmt = 0) {
        if ($type === 'timestamp') {
            return time();
        }
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        // Mock implementation - return default values
        $mock_options = [
            'autoblog_settings' => [
                'openai_api_key' => '',
                'blog_description' => 'Test Blog Description',
                'auto_publish' => false
            ]
        ];
        
        return isset($mock_options[$option]) ? $mock_options[$option] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        // Mock implementation
        return true;
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        // Mock implementation for API calls
        return [
            'response' => ['code' => 200],
            'body' => json_encode(['choices' => [['message' => ['content' => 'Mock AI response']]]])
        ];
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return $response['response']['code'] ?? 200;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return $response['body'] ?? '';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return htmlspecialchars(strip_tags($str), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data) {
        return strip_tags($data, '<p><br><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6>');
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post($postarr) {
        // Mock implementation - return a fake post ID
        return rand(1, 1000);
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) {
        return true;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = []) {
        return false;
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook, $args = []) {
        return true;
    }
}

if (!function_exists('wp_unschedule_event')) {
    function wp_unschedule_event($timestamp, $hook, $args = []) {
        return true;
    }
}

if (!function_exists('wp_get_schedules')) {
    function wp_get_schedules() {
        return [
            'hourly' => ['interval' => 3600, 'display' => 'Once Hourly'],
            'daily' => ['interval' => 86400, 'display' => 'Once Daily']
        ];
    }
}

// Mock global $wpdb
class MockWPDB {
    public $prefix = 'wp_';
    
    public function get_charset_collate() {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
    
    public function prepare($query, ...$args) {
        return vsprintf(str_replace('%s', "'%s'", $query), $args);
    }
    
    public function get_results($query) {
        return [];
    }
    
    public function get_var($query) {
        return null;
    }
    
    public function insert($table, $data, $format = null) {
        return rand(1, 1000);
    }
    
    public function update($table, $data, $where, $format = null, $where_format = null) {
        return 1;
    }
    
    public function delete($table, $where, $where_format = null) {
        return 1;
    }
    
    public function query($query) {
        return true;
    }
}

$GLOBALS['wpdb'] = new MockWPDB();

// Include the plugin files for testing
try {
    // Set up plugin constants
    define('AUTOBLOG_VERSION', '1.0.0');
    define('AUTOBLOG_DB_VERSION', '1.0.0');
    define('AUTOBLOG_PLUGIN_DIR', __DIR__ . '/../');
    define('AUTOBLOG_PLUGIN_URL', 'http://localhost:8000/');
    define('AUTOBLOG_PLUGIN_FILE', __DIR__ . '/../autoblog.php');
    define('AUTOBLOG_PLUGIN_BASENAME', 'autoblog/autoblog.php');
    define('AUTOBLOG_INCLUDES_DIR', AUTOBLOG_PLUGIN_DIR . 'includes/');
    define('AUTOBLOG_ASSETS_URL', AUTOBLOG_PLUGIN_URL . 'assets/');
    
    // Include plugin classes
    require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog-activator.php';
    require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog-deactivator.php';
    require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog.php';
    require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog-admin.php';
    require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog-openai.php';
    require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog-scheduler.php';
    require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog-affiliate.php';
    require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog-comments.php';
    require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog-analytics.php';
    
    echo "<h1>AutoBlog Plugin Test Environment</h1>";
    echo "<p><strong>Status:</strong> ✅ Plugin files loaded successfully!</p>";
    
    // Test class instantiation
    echo "<h2>Class Testing</h2>";
    echo "<ul>";
    
    $classes_to_test = [
        'AutoBlog' => 'Core plugin class',
        'AutoBlog_Admin' => 'Admin interface class',
        'AutoBlog_OpenAI' => 'OpenAI integration class',
        'AutoBlog_Scheduler' => 'Content scheduler class',
        'AutoBlog_Affiliate' => 'Affiliate management class',
        'AutoBlog_Comments' => 'Comment management class',
        'AutoBlog_Analytics' => 'Analytics class',
        'AutoBlog_Activator' => 'Plugin activator class',
        'AutoBlog_Deactivator' => 'Plugin deactivator class'
    ];
    
    foreach ($classes_to_test as $class => $description) {
        if (class_exists($class)) {
            echo "<li>✅ <strong>{$class}</strong>: {$description} - Class exists</li>";
            
            // Try to instantiate if it's not a static class
            if (!in_array($class, ['AutoBlog_Activator', 'AutoBlog_Deactivator'])) {
                try {
                    $instance = new $class();
                    echo "<li>✅ <strong>{$class}</strong>: Successfully instantiated</li>";
                } catch (Exception $e) {
                    echo "<li>⚠️ <strong>{$class}</strong>: Instantiation failed - {$e->getMessage()}</li>";
                }
            }
        } else {
            echo "<li>❌ <strong>{$class}</strong>: Class not found</li>";
        }
    }
    
    echo "</ul>";
    
    // Test OpenAI class methods
    echo "<h2>OpenAI Class Method Testing</h2>";
    echo "<ul>";
    
    if (class_exists('AutoBlog_OpenAI')) {
        $openai = new AutoBlog_OpenAI();
        $methods = get_class_methods($openai);
        
        $important_methods = [
            'test_connection' => 'Test API connection',
            'generate_content' => 'Generate blog content',
            'generate_image' => 'Generate featured images',
            'make_api_request' => 'Make API requests'
        ];
        
        foreach ($important_methods as $method => $description) {
            if (method_exists($openai, $method)) {
                echo "<li>✅ <strong>{$method}</strong>: {$description} - Method exists</li>";
            } else {
                echo "<li>❌ <strong>{$method}</strong>: Method not found</li>";
            }
        }
    }
    
    echo "</ul>";
    
    // Display plugin information
    echo "<h2>Plugin Information</h2>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><td><strong>Version</strong></td><td>" . AUTOBLOG_VERSION . "</td></tr>";
    echo "<tr><td><strong>Database Version</strong></td><td>" . AUTOBLOG_DB_VERSION . "</td></tr>";
    echo "<tr><td><strong>Plugin Directory</strong></td><td>" . AUTOBLOG_PLUGIN_DIR . "</td></tr>";
    echo "<tr><td><strong>Plugin URL</strong></td><td>" . AUTOBLOG_PLUGIN_URL . "</td></tr>";
    echo "<tr><td><strong>Includes Directory</strong></td><td>" . AUTOBLOG_INCLUDES_DIR . "</td></tr>";
    echo "<tr><td><strong>Assets URL</strong></td><td>" . AUTOBLOG_ASSETS_URL . "</td></tr>";
    echo "</table>";
    
    echo "<h2>Next Steps</h2>";
    echo "<p>To fully test the AutoBlog plugin:</p>";
    echo "<ol>";
    echo "<li>Install WordPress locally or use a staging environment</li>";
    echo "<li>Copy the AutoBlog plugin folder to wp-content/plugins/</li>";
    echo "<li>Activate the plugin in WordPress admin</li>";
    echo "<li>Configure your OpenAI API key in AutoBlog settings</li>";
    echo "<li>Test content generation features</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<h1>AutoBlog Plugin Test Environment</h1>";
    echo "<p><strong>Status:</strong> ❌ Error loading plugin files</p>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
}

echo "<hr>";
echo "<p><em>AutoBlog Plugin Test Environment - " . date('Y-m-d H:i:s') . "</em></p>";
?>

<!DOCTYPE html>
<html>
<head>
    <title>AutoBlog Plugin Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <!-- Content is generated by PHP above -->
</body>
</html>