<?php
/**
 * Plugin Name: AutoBlog
 * Plugin URI: https://github.com/your-username/autoblog
 * Description: AI-powered WordPress plugin for automated blog content generation using OpenAI API.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: autoblog
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AUTOBLOG_VERSION', '1.0.0');
define('AUTOBLOG_DB_VERSION', '1.0.0');
define('AUTOBLOG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AUTOBLOG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AUTOBLOG_PLUGIN_FILE', __FILE__);
define('AUTOBLOG_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('AUTOBLOG_INCLUDES_DIR', AUTOBLOG_PLUGIN_DIR . 'includes/');
define('AUTOBLOG_ASSETS_URL', AUTOBLOG_PLUGIN_URL . 'assets/');

// Include activation and deactivation classes
require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog-activator.php';
require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog-deactivator.php';

// Include core classes
require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog.php';
require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog-admin.php';
require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog-openai.php';
require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog-scheduler.php';
require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog-affiliate.php';
require_once AUTOBLOG_INCLUDES_DIR . 'class-autoblog-comments.php';

// Initialize the plugin
function autoblog_init() {
    $autoblog = new AutoBlog();
    $autoblog->init();
}
add_action('plugins_loaded', 'autoblog_init');

// Plugin activation hook
register_activation_hook(__FILE__, ['AutoBlog_Activator', 'activate']);

// Plugin deactivation hook
register_deactivation_hook(__FILE__, ['AutoBlog_Deactivator', 'deactivate']);

// Add custom cron schedules
add_filter('cron_schedules', ['AutoBlog_Activator', 'add_cron_schedules']);



// Handle scheduled content generation
add_action('autoblog_generate_content', 'autoblog_handle_scheduled_content');
function autoblog_handle_scheduled_content() {
    $scheduler = new AutoBlog_Scheduler();
    $scheduler->process_scheduled_content();
}