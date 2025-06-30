<?php
/**
 * AutoBlog Analytics Class
 *
 * @package AutoBlog
 */

class AutoBlog_Analytics {
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('autoblog_settings', array());
        
        add_action('wp_insert_post', array($this, 'track_post_creation'), 10, 3);
        add_action('transition_post_status', array($this, 'track_post_status_change'), 10, 3);
        add_action('wp_insert_comment', array($this, 'track_comment_creation'), 10, 2);
        add_action('autoblog_track_api_usage', array($this, 'track_api_usage'), 10, 3);
        add_action('autoblog_daily_analytics', array($this, 'process_daily_analytics'));
        
        // Schedule daily analytics processing
        if (!wp_next_scheduled('autoblog_daily_analytics')) {
            wp_schedule_event(time(), 'daily', 'autoblog_daily_analytics');
        }
        
        // Create analytics tables
        $this->create_analytics_tables();
    }
    
    /**
     * Track post creation
     */
    public function track_post_creation($post_id, $post, $update) {
        // Only track new posts, not updates
        if ($update) {
            return;
        }
        
        // Only track published posts
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Check if this is an AI-generated post
        $is_ai_generated = get_post_meta($post_id, '_autoblog_generated', true);
        
        if ($is_ai_generated) {
            $this->log_event('post_created', array(
                'post_id' => $post_id,
                'post_type' => $post->post_type,
                'ai_generated' => 1,
                'word_count' => str_word_count(strip_tags($post->post_content)),
                'generation_time' => get_post_meta($post_id, '_autoblog_generation_time', true),
                'content_type' => get_post_meta($post_id, '_autoblog_content_type', true)
            ));
        }
    }
    
    /**
     * Track post status changes
     */
    public function track_post_status_change($new_status, $old_status, $post) {
        // Only track AI-generated posts
        if (!get_post_meta($post->ID, '_autoblog_generated', true)) {
            return;
        }
        
        $this->log_event('post_status_change', array(
            'post_id' => $post->ID,
            'old_status' => $old_status,
            'new_status' => $new_status
        ));
    }
    
    /**
     * Track comment creation
     */
    public function track_comment_creation($comment_id, $comment) {
        // Check if this is an AI-generated comment reply
        $is_ai_generated = get_comment_meta($comment_id, 'autoblog_ai_generated', true);
        
        if ($is_ai_generated) {
            $this->log_event('ai_comment_created', array(
                'comment_id' => $comment_id,
                'post_id' => $comment->comment_post_ID,
                'parent_comment_id' => $comment->comment_parent,
                'word_count' => str_word_count(strip_tags($comment->comment_content))
            ));
        }
    }
    
    /**
     * Track API usage
     */
    public function track_api_usage($api_type, $tokens_used, $cost = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_api_usage';
        
        $wpdb->insert(
            $table_name,
            array(
                'api_type' => $api_type,
                'tokens_used' => $tokens_used,
                'cost' => $cost,
                'date' => current_time('mysql')
            ),
            array('%s', '%d', '%f', '%s')
        );
    }
    
    /**
     * Log analytics event
     */
    private function log_event($event_type, $data = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_analytics';
        
        $wpdb->insert(
            $table_name,
            array(
                'event_type' => $event_type,
                'event_data' => json_encode($data),
                'date' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
    }
    
    /**
     * Get content generation statistics
     */
    public function get_content_stats($days = 30) {
        global $wpdb;
        
        $stats = array();
        $date_limit = date('Y-m-d', strtotime("-{$days} days"));
        
        // Total AI-generated posts
        $total_posts = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_autoblog_generated'
                 AND pm.meta_value = '1'
                 AND p.post_status = 'publish'
                 AND p.post_date >= %s",
                $date_limit
            )
        );
        
        $stats['total_posts'] = $total_posts;
        
        // Posts by content type
        $content_types = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT pm2.meta_value as content_type, COUNT(*) as count
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
                 WHERE pm.meta_key = '_autoblog_generated'
                 AND pm.meta_value = '1'
                 AND pm2.meta_key = '_autoblog_content_type'
                 AND p.post_status = 'publish'
                 AND p.post_date >= %s
                 GROUP BY pm2.meta_value",
                $date_limit
            )
        );
        
        $stats['content_types'] = array();
        foreach ($content_types as $type) {
            $stats['content_types'][$type->content_type] = $type->count;
        }
        
        // Average generation time
        $avg_time = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(CAST(pm2.meta_value AS DECIMAL(10,2)))
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
                 WHERE pm.meta_key = '_autoblog_generated'
                 AND pm.meta_value = '1'
                 AND pm2.meta_key = '_autoblog_generation_time'
                 AND p.post_status = 'publish'
                 AND p.post_date >= %s",
                $date_limit
            )
        );
        
        $stats['avg_generation_time'] = round($avg_time, 2);
        
        // Daily post counts
        $daily_counts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(p.post_date) as date, COUNT(*) as count
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_autoblog_generated'
                 AND pm.meta_value = '1'
                 AND p.post_status = 'publish'
                 AND p.post_date >= %s
                 GROUP BY DATE(p.post_date)
                 ORDER BY date DESC",
                $date_limit
            )
        );
        
        $stats['daily_counts'] = $daily_counts;
        
        return $stats;
    }
    
    /**
     * Get API usage statistics
     */
    public function get_api_stats($days = 30) {
        global $wpdb;
        
        $stats = array();
        $table_name = $wpdb->prefix . 'autoblog_api_usage';
        $date_limit = date('Y-m-d', strtotime("-{$days} days"));
        
        // Total API calls
        $total_calls = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE date >= %s",
                $date_limit
            )
        );
        
        $stats['total_calls'] = $total_calls;
        
        // Total tokens used
        $total_tokens = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(tokens_used) FROM $table_name WHERE date >= %s",
                $date_limit
            )
        );
        
        $stats['total_tokens'] = $total_tokens;
        
        // Total cost
        $total_cost = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(cost) FROM $table_name WHERE date >= %s",
                $date_limit
            )
        );
        
        $stats['total_cost'] = round($total_cost, 4);
        
        // Usage by API type
        $api_usage = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT api_type, COUNT(*) as calls, SUM(tokens_used) as tokens, SUM(cost) as cost
                 FROM $table_name 
                 WHERE date >= %s
                 GROUP BY api_type",
                $date_limit
            )
        );
        
        $stats['api_usage'] = array();
        foreach ($api_usage as $usage) {
            $stats['api_usage'][$usage->api_type] = array(
                'calls' => $usage->calls,
                'tokens' => $usage->tokens,
                'cost' => round($usage->cost, 4)
            );
        }
        
        // Daily usage
        $daily_usage = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(date) as date, COUNT(*) as calls, SUM(tokens_used) as tokens, SUM(cost) as cost
                 FROM $table_name 
                 WHERE date >= %s
                 GROUP BY DATE(date)
                 ORDER BY date DESC",
                $date_limit
            )
        );
        
        $stats['daily_usage'] = $daily_usage;
        
        return $stats;
    }
    
    /**
     * Get performance statistics
     */
    public function get_performance_stats($days = 30) {
        global $wpdb;
        
        $stats = array();
        $date_limit = date('Y-m-d', strtotime("-{$days} days"));
        
        // Get AI-generated posts
        $ai_posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, p.post_date
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_autoblog_generated'
                 AND pm.meta_value = '1'
                 AND p.post_status = 'publish'
                 AND p.post_date >= %s",
                $date_limit
            )
        );
        
        if (empty($ai_posts)) {
            return array(
                'avg_views' => 0,
                'avg_comments' => 0,
                'total_views' => 0,
                'total_comments' => 0,
                'engagement_rate' => 0
            );
        }
        
        $post_ids = wp_list_pluck($ai_posts, 'ID');
        $post_ids_str = implode(',', array_map('intval', $post_ids));
        
        // Total comments on AI posts
        $total_comments = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->comments} 
             WHERE comment_post_ID IN ($post_ids_str) 
             AND comment_approved = '1'"
        );
        
        $stats['total_comments'] = $total_comments;
        $stats['avg_comments'] = count($ai_posts) > 0 ? round($total_comments / count($ai_posts), 2) : 0;
        
        // If a page view tracking plugin is available, get view stats
        $total_views = 0;
        if (function_exists('wp_statistics_pages')) {
            // WP Statistics plugin integration
            foreach ($post_ids as $post_id) {
                $total_views += wp_statistics_pages('total', '', $post_id);
            }
        } elseif (class_exists('WP_Statistics')) {
            // Alternative WP Statistics integration
            foreach ($post_ids as $post_id) {
                $total_views += wp_statistics_pages('total', '', $post_id);
            }
        }
        
        $stats['total_views'] = $total_views;
        $stats['avg_views'] = count($ai_posts) > 0 ? round($total_views / count($ai_posts), 2) : 0;
        
        // Calculate engagement rate (comments per view)
        $stats['engagement_rate'] = $total_views > 0 ? round(($total_comments / $total_views) * 100, 2) : 0;
        
        return $stats;
    }
    
    /**
     * Get recent activity
     */
    public function get_recent_activity($limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_analytics';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name 
                 ORDER BY date DESC 
                 LIMIT %d",
                $limit
            )
        );
    }
    
    /**
     * Process daily analytics
     */
    public function process_daily_analytics() {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Calculate daily summary
        $summary = array(
            'date' => $yesterday,
            'posts_generated' => $this->get_daily_post_count($yesterday),
            'comments_generated' => $this->get_daily_comment_count($yesterday),
            'api_calls' => $this->get_daily_api_calls($yesterday),
            'tokens_used' => $this->get_daily_tokens($yesterday),
            'cost' => $this->get_daily_cost($yesterday)
        );
        
        // Store daily summary
        $this->store_daily_summary($summary);
        
        // Clean up old analytics data (keep 90 days)
        $this->cleanup_old_analytics(90);
    }
    
    /**
     * Get daily post count
     */
    private function get_daily_post_count($date) {
        global $wpdb;
        
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_autoblog_generated'
                 AND pm.meta_value = '1'
                 AND p.post_status = 'publish'
                 AND DATE(p.post_date) = %s",
                $date
            )
        );
    }
    
    /**
     * Get daily comment count
     */
    private function get_daily_comment_count($date) {
        global $wpdb;
        
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->comments} c
                 INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
                 WHERE cm.meta_key = 'autoblog_ai_generated'
                 AND cm.meta_value = '1'
                 AND DATE(c.comment_date) = %s",
                $date
            )
        );
    }
    
    /**
     * Get daily API calls
     */
    private function get_daily_api_calls($date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_api_usage';
        
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE DATE(date) = %s",
                $date
            )
        );
    }
    
    /**
     * Get daily tokens used
     */
    private function get_daily_tokens($date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_api_usage';
        
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(tokens_used) FROM $table_name WHERE DATE(date) = %s",
                $date
            )
        );
    }
    
    /**
     * Get daily cost
     */
    private function get_daily_cost($date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_api_usage';
        
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(cost) FROM $table_name WHERE DATE(date) = %s",
                $date
            )
        );
    }
    
    /**
     * Store daily summary
     */
    private function store_daily_summary($summary) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_daily_summary';
        
        $wpdb->replace(
            $table_name,
            $summary,
            array('%s', '%d', '%d', '%d', '%d', '%f')
        );
    }
    
    /**
     * Get daily summaries
     */
    public function get_daily_summaries($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_daily_summary';
        $date_limit = date('Y-m-d', strtotime("-{$days} days"));
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name 
                 WHERE date >= %s 
                 ORDER BY date DESC",
                $date_limit
            )
        );
    }
    
    /**
     * Export analytics data
     */
    public function export_analytics($start_date, $end_date, $format = 'csv') {
        $data = array();
        
        // Get content stats
        $content_stats = $this->get_content_stats_for_period($start_date, $end_date);
        $api_stats = $this->get_api_stats_for_period($start_date, $end_date);
        $performance_stats = $this->get_performance_stats_for_period($start_date, $end_date);
        
        $data['content'] = $content_stats;
        $data['api'] = $api_stats;
        $data['performance'] = $performance_stats;
        
        if ($format === 'json') {
            return json_encode($data, JSON_PRETTY_PRINT);
        } elseif ($format === 'csv') {
            return $this->convert_to_csv($data);
        }
        
        return $data;
    }
    
    /**
     * Convert data to CSV format
     */
    private function convert_to_csv($data) {
        $csv = "";
        
        // Content stats CSV
        $csv .= "Content Statistics\n";
        $csv .= "Metric,Value\n";
        foreach ($data['content'] as $key => $value) {
            if (!is_array($value)) {
                $csv .= "$key,$value\n";
            }
        }
        
        $csv .= "\n";
        
        // API stats CSV
        $csv .= "API Statistics\n";
        $csv .= "Metric,Value\n";
        foreach ($data['api'] as $key => $value) {
            if (!is_array($value)) {
                $csv .= "$key,$value\n";
            }
        }
        
        return $csv;
    }
    
    /**
     * Get content stats for specific period
     */
    private function get_content_stats_for_period($start_date, $end_date) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(p.post_date) as date, COUNT(*) as posts_created
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_autoblog_generated'
                 AND pm.meta_value = '1'
                 AND p.post_status = 'publish'
                 AND DATE(p.post_date) BETWEEN %s AND %s
                 GROUP BY DATE(p.post_date)
                 ORDER BY date",
                $start_date,
                $end_date
            )
        );
    }
    
    /**
     * Get API stats for specific period
     */
    private function get_api_stats_for_period($start_date, $end_date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_api_usage';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(date) as date, COUNT(*) as api_calls, SUM(tokens_used) as tokens, SUM(cost) as cost
                 FROM $table_name 
                 WHERE DATE(date) BETWEEN %s AND %s
                 GROUP BY DATE(date)
                 ORDER BY date",
                $start_date,
                $end_date
            )
        );
    }
    
    /**
     * Get performance stats for specific period
     */
    private function get_performance_stats_for_period($start_date, $end_date) {
        // This would integrate with analytics plugins or custom tracking
        return array(
            'note' => 'Performance stats require integration with analytics plugins'
        );
    }
    
    /**
     * Clean up old analytics data
     */
    private function cleanup_old_analytics($days = 90) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Clean up analytics events
        $analytics_table = $wpdb->prefix . 'autoblog_analytics';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $analytics_table WHERE date < %s",
                $cutoff_date
            )
        );
        
        // Clean up API usage (keep longer - 180 days)
        $api_table = $wpdb->prefix . 'autoblog_api_usage';
        $api_cutoff = date('Y-m-d H:i:s', strtotime('-180 days'));
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $api_table WHERE date < %s",
                $api_cutoff
            )
        );
    }
    
    /**
     * Create analytics tables
     */
    private function create_analytics_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Analytics events table
        $analytics_table = $wpdb->prefix . 'autoblog_analytics';
        $sql1 = "CREATE TABLE IF NOT EXISTS $analytics_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data longtext,
            date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY date (date)
        ) $charset_collate;";
        
        // API usage table
        $api_table = $wpdb->prefix . 'autoblog_api_usage';
        $sql2 = "CREATE TABLE IF NOT EXISTS $api_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            api_type varchar(50) NOT NULL,
            tokens_used int(11) DEFAULT 0,
            cost decimal(10,6) DEFAULT 0,
            date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY api_type (api_type),
            KEY date (date)
        ) $charset_collate;";
        
        // Daily summary table
        $summary_table = $wpdb->prefix . 'autoblog_daily_summary';
        $sql3 = "CREATE TABLE IF NOT EXISTS $summary_table (
            date date NOT NULL,
            posts_generated int(11) DEFAULT 0,
            comments_generated int(11) DEFAULT 0,
            api_calls int(11) DEFAULT 0,
            tokens_used int(11) DEFAULT 0,
            cost decimal(10,6) DEFAULT 0,
            PRIMARY KEY (date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
    }
    
    /**
     * Get analytics dashboard data
     */
    public function get_dashboard_data($days = 30) {
        return array(
            'content_stats' => $this->get_content_stats($days),
            'api_stats' => $this->get_api_stats($days),
            'performance_stats' => $this->get_performance_stats($days),
            'recent_activity' => $this->get_recent_activity(5)
        );
    }
}