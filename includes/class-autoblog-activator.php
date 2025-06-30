<?php
/**
 * AutoBlog Plugin Activator
 *
 * Handles plugin activation tasks including database table creation,
 * default settings setup, and initial configuration.
 *
 * @package AutoBlog
 * @since 1.0.0
 */

class AutoBlog_Activator {
    
    /**
     * Plugin activation handler
     *
     * @since 1.0.0
     */
    public static function activate() {
        // Create database tables
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Schedule cron jobs
        self::schedule_cron_jobs();
        
        // Create upload directories
        self::create_directories();
        
        // Set activation timestamp
        update_option('autoblog_activation_time', current_time('timestamp'));
        
        // Set plugin version
        update_option('autoblog_version', AUTOBLOG_VERSION);
        update_option('autoblog_db_version', AUTOBLOG_DB_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     *
     * @since 1.0.0
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Content Schedule Table
        $table_name = $wpdb->prefix . 'autoblog_content_schedule';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            content_type varchar(50) NOT NULL,
            topic varchar(255) DEFAULT NULL,
            scheduled_date datetime NOT NULL,
            status varchar(20) DEFAULT 'pending',
            post_id bigint(20) DEFAULT NULL,
            generation_time int DEFAULT NULL,
            api_cost decimal(10,4) DEFAULT NULL,
            tokens_used int DEFAULT NULL,
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY scheduled_date (scheduled_date),
            KEY content_type (content_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Comment Queue Table
        $table_name = $wpdb->prefix . 'autoblog_comment_queue';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            comment_id bigint(20) NOT NULL,
            post_id bigint(20) NOT NULL,
            reply_content text DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            generation_time int DEFAULT NULL,
            api_cost decimal(10,4) DEFAULT NULL,
            tokens_used int DEFAULT NULL,
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY comment_id (comment_id),
            KEY status (status),
            KEY post_id (post_id)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Analytics Table
        $table_name = $wpdb->prefix . 'autoblog_analytics';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data longtext DEFAULT NULL,
            post_id bigint(20) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY post_id (post_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // API Usage Table
        $table_name = $wpdb->prefix . 'autoblog_api_usage';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            api_type varchar(50) NOT NULL,
            endpoint varchar(100) NOT NULL,
            tokens_used int NOT NULL,
            cost decimal(10,4) NOT NULL,
            response_time int DEFAULT NULL,
            status varchar(20) NOT NULL,
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY api_type (api_type),
            KEY endpoint (endpoint),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Daily Summary Table
        $table_name = $wpdb->prefix . 'autoblog_daily_summary';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            posts_generated int DEFAULT 0,
            comments_replied int DEFAULT 0,
            api_calls int DEFAULT 0,
            total_tokens int DEFAULT 0,
            total_cost decimal(10,4) DEFAULT 0,
            avg_generation_time int DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY date (date)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Set default plugin options
     *
     * @since 1.0.0
     */
    private static function set_default_options() {
        $default_settings = [
            'openai_api_key' => '',
            'blog_description' => get_bloginfo('description'),
            'auto_publish' => false,
            'amazon_affiliate_id' => '',
            'comment_auto_reply' => false,
            'content_types' => [
                'blog_post' => true,
                'product_review' => true,
                'how_to_guide' => true,
                'listicle' => true,
                'news_article' => false
            ],
            'schedule_settings' => [
                'posts_per_day' => 1,
                'posting_time' => '09:00',
                'days_ahead' => 7
            ],
            'analytics_settings' => [
                'track_performance' => true,
                'track_api_usage' => true,
                'cleanup_days' => 90
            ],
            'affiliate_settings' => [
                'auto_add_links' => false,
                'disclosure_text' => 'This post contains affiliate links. We may earn a commission if you make a purchase.',
                'link_density' => 'medium'
            ],
            'comment_settings' => [
                'auto_reply_delay' => 30,
                'require_approval' => true,
                'reply_length' => 'medium'
            ]
        ];
        
        // Only set if not already exists
        if (!get_option('autoblog_settings')) {
            update_option('autoblog_settings', $default_settings);
        }
        
        // Set individual options for backward compatibility
        foreach ($default_settings as $key => $value) {
            $option_name = 'autoblog_' . $key;
            if (!get_option($option_name)) {
                update_option($option_name, $value);
            }
        }
    }
    
    /**
     * Schedule cron jobs
     *
     * @since 1.0.0
     */
    private static function schedule_cron_jobs() {
        // Schedule content generation (hourly)
        if (!wp_next_scheduled('autoblog_generate_content')) {
            wp_schedule_event(time(), 'hourly', 'autoblog_generate_content');
        }
        
        // Schedule comment queue processing (every 15 minutes)
        if (!wp_next_scheduled('autoblog_process_comment_queue')) {
            wp_schedule_event(time(), 'autoblog_15min', 'autoblog_process_comment_queue');
        }
        
        // Schedule daily cleanup (daily)
        if (!wp_next_scheduled('autoblog_cleanup_old_data')) {
            wp_schedule_event(time(), 'daily', 'autoblog_cleanup_old_data');
        }
        
        // Schedule daily analytics summary (daily)
        if (!wp_next_scheduled('autoblog_daily_analytics')) {
            wp_schedule_event(time(), 'daily', 'autoblog_daily_analytics');
        }
    }
    
    /**
     * Create necessary directories
     *
     * @since 1.0.0
     */
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        
        // Create main plugin directory
        $autoblog_dir = $upload_dir['basedir'] . '/autoblog';
        if (!file_exists($autoblog_dir)) {
            wp_mkdir_p($autoblog_dir);
        }
        
        // Create images directory
        $images_dir = $autoblog_dir . '/images';
        if (!file_exists($images_dir)) {
            wp_mkdir_p($images_dir);
        }
        
        // Create logs directory
        $logs_dir = $upload_dir['basedir'] . '/autoblog-logs';
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }
        
        // Create .htaccess file to protect logs
        $htaccess_file = $logs_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($htaccess_file, $htaccess_content);
        }
        
        // Create index.php files to prevent directory browsing
        $index_content = "<?php\n// Silence is golden.";
        
        $directories = [$autoblog_dir, $images_dir, $logs_dir];
        foreach ($directories as $dir) {
            $index_file = $dir . '/index.php';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, $index_content);
            }
        }
    }
    
    /**
     * Add custom cron schedules
     *
     * @since 1.0.0
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public static function add_cron_schedules($schedules) {
        $schedules['autoblog_15min'] = [
            'interval' => 900, // 15 minutes
            'display' => __('Every 15 Minutes', 'autoblog')
        ];
        
        $schedules['autoblog_30min'] = [
            'interval' => 1800, // 30 minutes
            'display' => __('Every 30 Minutes', 'autoblog')
        ];
        
        return $schedules;
    }
    
    /**
     * Check if plugin requirements are met
     *
     * @since 1.0.0
     * @return bool|WP_Error True if requirements met, WP_Error otherwise
     */
    public static function check_requirements() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            return new WP_Error(
                'php_version',
                __('AutoBlog requires PHP 7.4 or higher.', 'autoblog')
            );
        }
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            return new WP_Error(
                'wp_version',
                __('AutoBlog requires WordPress 5.0 or higher.', 'autoblog')
            );
        }
        
        // Check if cURL is available
        if (!function_exists('curl_init')) {
            return new WP_Error(
                'curl_missing',
                __('AutoBlog requires cURL extension to be installed.', 'autoblog')
            );
        }
        
        // Check if JSON extension is available
        if (!function_exists('json_encode')) {
            return new WP_Error(
                'json_missing',
                __('AutoBlog requires JSON extension to be installed.', 'autoblog')
            );
        }
        
        return true;
    }
    
    /**
     * Create sample content schedule
     *
     * @since 1.0.0
     */
    private static function create_sample_schedule() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_content_schedule';
        
        $sample_posts = [
            [
                'title' => 'Welcome to AutoBlog',
                'content_type' => 'blog_post',
                'topic' => 'Introduction to automated blogging',
                'scheduled_date' => date('Y-m-d H:i:s', strtotime('+1 hour'))
            ],
            [
                'title' => 'How to Get Started with AI Content',
                'content_type' => 'how_to_guide',
                'topic' => 'AI content creation guide',
                'scheduled_date' => date('Y-m-d H:i:s', strtotime('+1 day'))
            ]
        ];
        
        foreach ($sample_posts as $post) {
            $wpdb->insert($table_name, $post);
        }
    }
}