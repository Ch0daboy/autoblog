<?php
/**
 * AutoBlog Comments Class
 *
 * @package AutoBlog
 */

class AutoBlog_Comments {
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('autoblog_settings', array());
        
        add_action('comment_post', array($this, 'handle_new_comment'), 10, 3);
        add_action('wp_set_comment_status', array($this, 'handle_comment_status_change'), 10, 2);
        add_action('autoblog_process_comment_replies', array($this, 'process_pending_replies'));
        add_filter('comment_text', array($this, 'add_ai_reply_indicator'));
        add_action('wp_ajax_autoblog_generate_comment_reply', array($this, 'ajax_generate_reply'));
        add_action('wp_ajax_autoblog_approve_reply', array($this, 'ajax_approve_reply'));
        
        // Schedule comment processing
        if (!wp_next_scheduled('autoblog_process_comment_replies')) {
            wp_schedule_event(time(), 'hourly', 'autoblog_process_comment_replies');
        }
    }
    
    /**
     * Handle new comment submission
     */
    public function handle_new_comment($comment_id, $comment_approved, $commentdata) {
        // Only process if auto-reply is enabled
        if (empty($this->settings['comment_auto_reply'])) {
            return;
        }
        
        // Skip if comment is spam or not approved
        if ($comment_approved !== 1) {
            return;
        }
        
        // Skip if comment is from admin
        if (user_can($commentdata['user_id'], 'manage_options')) {
            return;
        }
        
        // Skip if comment is a reply to another comment
        if (!empty($commentdata['comment_parent'])) {
            return;
        }
        
        // Queue for AI reply generation
        $this->queue_comment_for_reply($comment_id);
    }
    
    /**
     * Handle comment status changes
     */
    public function handle_comment_status_change($comment_id, $status) {
        if ($status === 'approve' && !empty($this->settings['comment_auto_reply'])) {
            $comment = get_comment($comment_id);
            
            // Only process top-level comments
            if ($comment && $comment->comment_parent == 0) {
                $this->queue_comment_for_reply($comment_id);
            }
        }
    }
    
    /**
     * Queue comment for AI reply
     */
    private function queue_comment_for_reply($comment_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_comment_queue';
        
        // Create table if it doesn't exist
        $this->create_comment_queue_table();
        
        // Check if already queued
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table_name WHERE comment_id = %d AND status = 'pending'",
                $comment_id
            )
        );
        
        if ($existing) {
            return;
        }
        
        // Add to queue
        $wpdb->insert(
            $table_name,
            array(
                'comment_id' => $comment_id,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s')
        );
    }
    
    /**
     * Process pending comment replies
     */
    public function process_pending_replies() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_comment_queue';
        
        // Get pending comments (limit to 5 per run to avoid overwhelming the API)
        $pending_comments = $wpdb->get_results(
            "SELECT * FROM $table_name 
             WHERE status = 'pending' 
             ORDER BY created_at ASC 
             LIMIT 5"
        );
        
        if (empty($pending_comments)) {
            return;
        }
        
        $openai = new AutoBlog_OpenAI();
        
        foreach ($pending_comments as $queue_item) {
            // Update status to processing
            $wpdb->update(
                $table_name,
                array('status' => 'processing'),
                array('id' => $queue_item->id),
                array('%s'),
                array('%d')
            );
            
            $comment = get_comment($queue_item->comment_id);
            
            if (!$comment) {
                // Mark as failed - comment not found
                $wpdb->update(
                    $table_name,
                    array('status' => 'failed', 'error_message' => 'Comment not found'),
                    array('id' => $queue_item->id),
                    array('%s', '%s'),
                    array('%d')
                );
                continue;
            }
            
            $post = get_post($comment->comment_post_ID);
            
            if (!$post) {
                // Mark as failed - post not found
                $wpdb->update(
                    $table_name,
                    array('status' => 'failed', 'error_message' => 'Post not found'),
                    array('id' => $queue_item->id),
                    array('%s', '%s'),
                    array('%d')
                );
                continue;
            }
            
            // Generate AI reply
            $reply_content = $openai->generate_comment_reply(
                $comment->comment_content,
                $post->post_title,
                wp_strip_all_tags($post->post_content)
            );
            
            if (!$reply_content) {
                // Mark as failed
                $wpdb->update(
                    $table_name,
                    array('status' => 'failed', 'error_message' => 'Failed to generate reply'),
                    array('id' => $queue_item->id),
                    array('%s', '%s'),
                    array('%d')
                );
                continue;
            }
            
            // Create the reply comment
            $reply_data = array(
                'comment_post_ID' => $comment->comment_post_ID,
                'comment_author' => get_option('blogname') . ' Team',
                'comment_author_email' => get_option('admin_email'),
                'comment_author_url' => home_url(),
                'comment_content' => $reply_content,
                'comment_parent' => $comment->comment_ID,
                'comment_approved' => $this->should_auto_approve_replies() ? 1 : 0,
                'comment_meta' => array(
                    'autoblog_ai_generated' => 1,
                    'autoblog_generation_date' => current_time('mysql')
                )
            );
            
            $reply_id = wp_insert_comment($reply_data);
            
            if ($reply_id) {
                // Mark as completed
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'completed',
                        'reply_id' => $reply_id,
                        'completed_at' => current_time('mysql')
                    ),
                    array('id' => $queue_item->id),
                    array('%s', '%d', '%s'),
                    array('%d')
                );
                
                // Send notification if enabled
                $this->send_reply_notification($comment, $reply_id);
            } else {
                // Mark as failed
                $wpdb->update(
                    $table_name,
                    array('status' => 'failed', 'error_message' => 'Failed to create reply comment'),
                    array('id' => $queue_item->id),
                    array('%s', '%s'),
                    array('%d')
                );
            }
            
            // Add delay to avoid rate limiting
            sleep(1);
        }
    }
    
    /**
     * Check if replies should be auto-approved
     */
    private function should_auto_approve_replies() {
        return apply_filters('autoblog_auto_approve_ai_replies', true);
    }
    
    /**
     * Send notification about new AI reply
     */
    private function send_reply_notification($original_comment, $reply_id) {
        $settings = get_option('autoblog_settings', array());
        
        if (empty($settings['reply_notifications'])) {
            return;
        }
        
        $post = get_post($original_comment->comment_post_ID);
        $reply = get_comment($reply_id);
        
        $subject = sprintf(__('[%s] AI Reply Generated', 'autoblog'), get_option('blogname'));
        
        $message = sprintf(
            __('An AI reply has been generated for a comment on "%s"\n\nOriginal Comment: %s\n\nAI Reply: %s\n\nView: %s', 'autoblog'),
            $post->post_title,
            $original_comment->comment_content,
            $reply->comment_content,
            get_comment_link($reply_id)
        );
        
        wp_mail(get_option('admin_email'), $subject, $message);
    }
    
    /**
     * Add AI reply indicator to comment text
     */
    public function add_ai_reply_indicator($comment_text) {
        global $comment;
        
        if (get_comment_meta($comment->comment_ID, 'autoblog_ai_generated', true)) {
            $indicator = '<div class="autoblog-ai-indicator">';
            $indicator .= '<small><em>' . __('This reply was generated by AI', 'autoblog') . '</em></small>';
            $indicator .= '</div>';
            
            $comment_text .= $indicator;
        }
        
        return $comment_text;
    }
    
    /**
     * AJAX handler for generating manual reply
     */
    public function ajax_generate_reply() {
        check_ajax_referer('autoblog_nonce', 'nonce');
        
        if (!current_user_can('moderate_comments')) {
            wp_die(__('Insufficient permissions', 'autoblog'));
        }
        
        $comment_id = intval($_POST['comment_id'] ?? 0);
        
        if (!$comment_id) {
            wp_send_json_error(__('Invalid comment ID', 'autoblog'));
        }
        
        $comment = get_comment($comment_id);
        
        if (!$comment) {
            wp_send_json_error(__('Comment not found', 'autoblog'));
        }
        
        $post = get_post($comment->comment_post_ID);
        
        if (!$post) {
            wp_send_json_error(__('Post not found', 'autoblog'));
        }
        
        $openai = new AutoBlog_OpenAI();
        $reply_content = $openai->generate_comment_reply(
            $comment->comment_content,
            $post->post_title,
            wp_strip_all_tags($post->post_content)
        );
        
        if (!$reply_content) {
            wp_send_json_error(__('Failed to generate reply', 'autoblog'));
        }
        
        wp_send_json_success(array('reply' => $reply_content));
    }
    
    /**
     * AJAX handler for approving AI reply
     */
    public function ajax_approve_reply() {
        check_ajax_referer('autoblog_nonce', 'nonce');
        
        if (!current_user_can('moderate_comments')) {
            wp_die(__('Insufficient permissions', 'autoblog'));
        }
        
        $comment_id = intval($_POST['comment_id'] ?? 0);
        $reply_content = sanitize_textarea_field($_POST['reply_content'] ?? '');
        
        if (!$comment_id || !$reply_content) {
            wp_send_json_error(__('Missing required data', 'autoblog'));
        }
        
        $comment = get_comment($comment_id);
        
        if (!$comment) {
            wp_send_json_error(__('Comment not found', 'autoblog'));
        }
        
        // Create the reply
        $reply_data = array(
            'comment_post_ID' => $comment->comment_post_ID,
            'comment_author' => get_option('blogname') . ' Team',
            'comment_author_email' => get_option('admin_email'),
            'comment_author_url' => home_url(),
            'comment_content' => $reply_content,
            'comment_parent' => $comment->comment_ID,
            'comment_approved' => 1,
            'comment_meta' => array(
                'autoblog_ai_generated' => 1,
                'autoblog_generation_date' => current_time('mysql'),
                'autoblog_manually_approved' => 1
            )
        );
        
        $reply_id = wp_insert_comment($reply_data);
        
        if ($reply_id) {
            wp_send_json_success(array(
                'reply_id' => $reply_id,
                'message' => __('Reply posted successfully', 'autoblog')
            ));
        } else {
            wp_send_json_error(__('Failed to post reply', 'autoblog'));
        }
    }
    
    /**
     * Get comment reply statistics
     */
    public function get_reply_stats($days = 30) {
        global $wpdb;
        
        $stats = array();
        
        // Total AI replies
        $total_replies = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->comments} c
                 INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
                 WHERE cm.meta_key = 'autoblog_ai_generated'
                 AND cm.meta_value = '1'
                 AND c.comment_date >= %s",
                date('Y-m-d', strtotime("-{$days} days"))
            )
        );
        
        $stats['total_replies'] = $total_replies;
        
        // Pending in queue
        $table_name = $wpdb->prefix . 'autoblog_comment_queue';
        $pending_queue = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
        
        $stats['pending_queue'] = $pending_queue;
        
        // Success rate
        $completed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
        $failed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'failed'");
        
        $total_processed = $completed + $failed;
        $stats['success_rate'] = $total_processed > 0 ? round(($completed / $total_processed) * 100, 2) : 0;
        
        return $stats;
    }
    
    /**
     * Get recent AI replies
     */
    public function get_recent_replies($limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*, p.post_title 
                 FROM {$wpdb->comments} c
                 INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
                 INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
                 WHERE cm.meta_key = 'autoblog_ai_generated'
                 AND cm.meta_value = '1'
                 ORDER BY c.comment_date DESC
                 LIMIT %d",
                $limit
            )
        );
    }
    
    /**
     * Create comment queue table
     */
    private function create_comment_queue_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_comment_queue';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            comment_id bigint(20) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            reply_id bigint(20) DEFAULT NULL,
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY comment_id (comment_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Clean up old queue entries
     */
    public function cleanup_old_queue_entries($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_comment_queue';
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name 
                 WHERE status IN ('completed', 'failed') 
                 AND created_at < %s",
                date('Y-m-d H:i:s', strtotime("-{$days} days"))
            )
        );
    }
    
    /**
     * Retry failed comment replies
     */
    public function retry_failed_replies($limit = 5) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_comment_queue';
        
        // Reset failed entries to pending (only recent ones)
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table_name 
                 SET status = 'pending', error_message = NULL 
                 WHERE status = 'failed' 
                 AND created_at >= %s 
                 LIMIT %d",
                date('Y-m-d H:i:s', strtotime('-24 hours')),
                $limit
            )
        );
        
        return $updated;
    }
    
    /**
     * Disable AI replies for specific posts
     */
    public function disable_ai_replies_for_post($post_id) {
        update_post_meta($post_id, '_autoblog_disable_ai_replies', 1);
    }
    
    /**
     * Enable AI replies for specific posts
     */
    public function enable_ai_replies_for_post($post_id) {
        delete_post_meta($post_id, '_autoblog_disable_ai_replies');
    }
    
    /**
     * Check if AI replies are disabled for a post
     */
    public function are_ai_replies_disabled($post_id) {
        return get_post_meta($post_id, '_autoblog_disable_ai_replies', true) == 1;
    }
    
    /**
     * Bulk moderate AI replies
     */
    public function bulk_moderate_replies($reply_ids, $action) {
        if (empty($reply_ids) || !is_array($reply_ids)) {
            return false;
        }
        
        $results = array();
        
        foreach ($reply_ids as $reply_id) {
            switch ($action) {
                case 'approve':
                    $results[$reply_id] = wp_set_comment_status($reply_id, 'approve');
                    break;
                    
                case 'spam':
                    $results[$reply_id] = wp_set_comment_status($reply_id, 'spam');
                    break;
                    
                case 'trash':
                    $results[$reply_id] = wp_set_comment_status($reply_id, 'trash');
                    break;
                    
                case 'delete':
                    $results[$reply_id] = wp_delete_comment($reply_id, true);
                    break;
                    
                default:
                    $results[$reply_id] = false;
            }
        }
        
        return $results;
    }
}