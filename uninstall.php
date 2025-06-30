<?php
/**
 * AutoBlog Uninstall Script
 *
 * This file is executed when the plugin is uninstalled.
 * It removes all plugin data, options, and database tables.
 *
 * @package AutoBlog
 * @since 1.0.0
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove all plugin data on uninstall
 */
class AutoBlog_Uninstaller {
    
    /**
     * Run the uninstall process
     */
    public static function uninstall() {
        global $wpdb;
        
        // Remove plugin options
        self::remove_options();
        
        // Remove database tables
        self::remove_tables();
        
        // Remove scheduled cron jobs
        self::remove_cron_jobs();
        
        // Remove uploaded files
        self::remove_uploaded_files();
        
        // Remove user meta
        self::remove_user_meta();
        
        // Remove post meta
        self::remove_post_meta();
        
        // Clear any cached data
        self::clear_cache();
    }
    
    /**
     * Remove all plugin options
     */
    private static function remove_options() {
        $options = [
            'autoblog_settings',
            'autoblog_openai_api_key',
            'autoblog_blog_description',
            'autoblog_auto_publish',
            'autoblog_amazon_affiliate_id',
            'autoblog_comment_auto_reply',
            'autoblog_content_types',
            'autoblog_schedule_settings',
            'autoblog_analytics_settings',
            'autoblog_version',
            'autoblog_db_version',
            'autoblog_activation_time',
            'autoblog_last_cleanup',
            'autoblog_api_usage_stats',
            'autoblog_performance_stats'
        ];
        
        foreach ($options as $option) {
            delete_option($option);
            delete_site_option($option); // For multisite
        }
    }
    
    /**
     * Remove all plugin database tables
     */
    private static function remove_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'autoblog_content_schedule',
            $wpdb->prefix . 'autoblog_comment_queue',
            $wpdb->prefix . 'autoblog_analytics',
            $wpdb->prefix . 'autoblog_api_usage',
            $wpdb->prefix . 'autoblog_daily_summary'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }
    
    /**
     * Remove scheduled cron jobs
     */
    private static function remove_cron_jobs() {
        // Remove scheduled hooks
        wp_clear_scheduled_hook('autoblog_generate_content');
        wp_clear_scheduled_hook('autoblog_process_comment_queue');
        wp_clear_scheduled_hook('autoblog_cleanup_old_data');
        wp_clear_scheduled_hook('autoblog_daily_analytics');
        
        // Remove custom cron schedules
        $schedules = wp_get_schedules();
        if (isset($schedules['autoblog_hourly'])) {
            wp_unschedule_event(wp_next_scheduled('autoblog_generate_content'), 'autoblog_generate_content');
        }
    }
    
    /**
     * Remove uploaded files and directories
     */
    private static function remove_uploaded_files() {
        $upload_dir = wp_upload_dir();
        $autoblog_dir = $upload_dir['basedir'] . '/autoblog';
        
        if (is_dir($autoblog_dir)) {
            self::delete_directory($autoblog_dir);
        }
        
        // Remove log files
        $log_dir = $upload_dir['basedir'] . '/autoblog-logs';
        if (is_dir($log_dir)) {
            self::delete_directory($log_dir);
        }
    }
    
    /**
     * Remove user meta data
     */
    private static function remove_user_meta() {
        global $wpdb;
        
        $meta_keys = [
            'autoblog_user_preferences',
            'autoblog_api_usage',
            'autoblog_last_activity',
            'autoblog_notification_settings'
        ];
        
        foreach ($meta_keys as $meta_key) {
            $wpdb->delete(
                $wpdb->usermeta,
                ['meta_key' => $meta_key],
                ['%s']
            );
        }
    }
    
    /**
     * Remove post meta data
     */
    private static function remove_post_meta() {
        global $wpdb;
        
        $meta_keys = [
            '_autoblog_generated',
            '_autoblog_generation_time',
            '_autoblog_content_type',
            '_autoblog_api_cost',
            '_autoblog_tokens_used',
            '_autoblog_affiliate_links',
            '_autoblog_seo_score',
            '_autoblog_featured_image_generated',
            '_autoblog_scheduled_id'
        ];
        
        foreach ($meta_keys as $meta_key) {
            $wpdb->delete(
                $wpdb->postmeta,
                ['meta_key' => $meta_key],
                ['%s']
            );
        }
    }
    
    /**
     * Clear any cached data
     */
    private static function clear_cache() {
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear transients
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_autoblog_%' OR option_name LIKE '_transient_timeout_autoblog_%'"
        );
        
        // Clear site transients for multisite
        $wpdb->query(
            "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_autoblog_%' OR meta_key LIKE '_site_transient_timeout_autoblog_%'"
        );
    }
    
    /**
     * Recursively delete a directory and its contents
     *
     * @param string $dir Directory path
     * @return bool Success status
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
}

// Run the uninstall process
AutoBlog_Uninstaller::uninstall();

// Log the uninstall event (if logging is still available)
if (function_exists('error_log')) {
    error_log('AutoBlog plugin has been uninstalled and all data removed.');
}