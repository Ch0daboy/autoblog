<?php
/**
 * AutoBlog Plugin Deactivator
 *
 * Handles plugin deactivation tasks including clearing scheduled cron jobs
 * and temporary data cleanup.
 *
 * @package AutoBlog
 * @since 1.0.0
 */

class AutoBlog_Deactivator {
    
    /**
     * Plugin deactivation handler
     *
     * @since 1.0.0
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        self::clear_cron_jobs();
        
        // Clear temporary data
        self::clear_temporary_data();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        self::log_deactivation();
    }
    
    /**
     * Clear all scheduled cron jobs
     *
     * @since 1.0.0
     */
    private static function clear_cron_jobs() {
        // Clear content generation cron
        $timestamp = wp_next_scheduled('autoblog_generate_content');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'autoblog_generate_content');
        }
        
        // Clear comment queue processing cron
        $timestamp = wp_next_scheduled('autoblog_process_comment_queue');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'autoblog_process_comment_queue');
        }
        
        // Clear cleanup cron
        $timestamp = wp_next_scheduled('autoblog_cleanup_old_data');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'autoblog_cleanup_old_data');
        }
        
        // Clear analytics cron
        $timestamp = wp_next_scheduled('autoblog_daily_analytics');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'autoblog_daily_analytics');
        }
        
        // Clear all instances of our cron hooks
        wp_clear_scheduled_hook('autoblog_generate_content');
        wp_clear_scheduled_hook('autoblog_process_comment_queue');
        wp_clear_scheduled_hook('autoblog_cleanup_old_data');
        wp_clear_scheduled_hook('autoblog_daily_analytics');
    }
    
    /**
     * Clear temporary data and caches
     *
     * @since 1.0.0
     */
    private static function clear_temporary_data() {
        global $wpdb;
        
        // Clear transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_autoblog_%' OR option_name LIKE '_transient_timeout_autoblog_%'"
        );
        
        // Clear site transients for multisite
        if (is_multisite()) {
            $wpdb->query(
                "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_autoblog_%' OR meta_key LIKE '_site_transient_timeout_autoblog_%'"
            );
        }
        
        // Clear object cache
        wp_cache_flush();
        
        // Clear any pending comment queue items that are in processing state
        $table_name = $wpdb->prefix . 'autoblog_comment_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            $wpdb->update(
                $table_name,
                ['status' => 'pending'],
                ['status' => 'processing'],
                ['%s'],
                ['%s']
            );
        }
        
        // Clear any content schedule items that are in processing state
        $table_name = $wpdb->prefix . 'autoblog_content_schedule';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            $wpdb->update(
                $table_name,
                ['status' => 'pending'],
                ['status' => 'processing'],
                ['%s'],
                ['%s']
            );
        }
    }
    
    /**
     * Log the deactivation event
     *
     * @since 1.0.0
     */
    private static function log_deactivation() {
        // Update deactivation timestamp
        update_option('autoblog_deactivation_time', current_time('timestamp'));
        
        // Log to analytics if table exists
        global $wpdb;
        $table_name = $wpdb->prefix . 'autoblog_analytics';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            $wpdb->insert(
                $table_name,
                [
                    'event_type' => 'plugin_deactivated',
                    'event_data' => json_encode([
                        'version' => get_option('autoblog_version', '1.0.0'),
                        'deactivation_time' => current_time('mysql'),
                        'user_id' => get_current_user_id()
                    ]),
                    'user_id' => get_current_user_id(),
                    'created_at' => current_time('mysql')
                ],
                [
                    '%s',
                    '%s',
                    '%d',
                    '%s'
                ]
            );
        }
        
        // Log to error log if available
        if (function_exists('error_log')) {
            error_log('AutoBlog plugin has been deactivated.');
        }
    }
    
    /**
     * Cleanup any running processes
     *
     * @since 1.0.0
     */
    private static function cleanup_running_processes() {
        // This method can be extended to handle any cleanup of running processes
        // For example, if there are any background tasks or API calls in progress
        
        // Clear any locks or semaphores
        delete_transient('autoblog_generation_lock');
        delete_transient('autoblog_comment_processing_lock');
        delete_transient('autoblog_analytics_processing_lock');
    }
    
    /**
     * Save current state for potential reactivation
     *
     * @since 1.0.0
     */
    private static function save_state() {
        $state = [
            'deactivation_time' => current_time('timestamp'),
            'version' => get_option('autoblog_version', '1.0.0'),
            'settings' => get_option('autoblog_settings', []),
            'pending_schedules' => self::get_pending_schedules_count(),
            'pending_comments' => self::get_pending_comments_count()
        ];
        
        update_option('autoblog_deactivation_state', $state);
    }
    
    /**
     * Get count of pending scheduled content
     *
     * @since 1.0.0
     * @return int Number of pending schedules
     */
    private static function get_pending_schedules_count() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_content_schedule';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return 0;
        }
        
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
                'pending'
            )
        );
    }
    
    /**
     * Get count of pending comment replies
     *
     * @since 1.0.0
     * @return int Number of pending comment replies
     */
    private static function get_pending_comments_count() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_comment_queue';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return 0;
        }
        
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
                'pending'
            )
        );
    }
    
    /**
     * Send deactivation feedback (optional)
     *
     * @since 1.0.0
     */
    private static function send_deactivation_feedback() {
        // This method can be used to collect anonymous usage statistics
        // or feedback about why the plugin was deactivated
        
        $feedback_data = [
            'action' => 'plugin_deactivated',
            'plugin_version' => get_option('autoblog_version', '1.0.0'),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'site_url' => home_url(),
            'deactivation_time' => current_time('c'),
            'usage_stats' => [
                'posts_generated' => self::get_total_posts_generated(),
                'comments_replied' => self::get_total_comments_replied(),
                'days_active' => self::get_days_active()
            ]
        ];
        
        // Only send if user has opted in to usage tracking
        $settings = get_option('autoblog_settings', []);
        if (isset($settings['allow_usage_tracking']) && $settings['allow_usage_tracking']) {
            // Send feedback to remote server (implement as needed)
            // wp_remote_post('https://api.autoblog-plugin.com/feedback', [
            //     'body' => json_encode($feedback_data),
            //     'headers' => ['Content-Type' => 'application/json']
            // ]);
        }
    }
    
    /**
     * Get total posts generated
     *
     * @since 1.0.0
     * @return int Total posts generated
     */
    private static function get_total_posts_generated() {
        global $wpdb;
        
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_autoblog_generated' AND meta_value = '1'"
        );
    }
    
    /**
     * Get total comments replied
     *
     * @since 1.0.0
     * @return int Total comments replied
     */
    private static function get_total_comments_replied() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_comment_queue';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return 0;
        }
        
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
                'completed'
            )
        );
    }
    
    /**
     * Get number of days the plugin was active
     *
     * @since 1.0.0
     * @return int Days active
     */
    private static function get_days_active() {
        $activation_time = get_option('autoblog_activation_time');
        
        if (!$activation_time) {
            return 0;
        }
        
        $current_time = current_time('timestamp');
        $days_active = floor(($current_time - $activation_time) / DAY_IN_SECONDS);
        
        return max(0, $days_active);
    }
}