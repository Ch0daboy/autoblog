<?php
/**
 * AutoBlog Scheduler Class
 *
 * @package AutoBlog
 */

class AutoBlog_Scheduler {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('autoblog_process_scheduled_content', array($this, 'process_scheduled_content'));
        add_action('init', array($this, 'maybe_schedule_content'));
        add_action('wp_ajax_autoblog_generate_content_schedule', array($this, 'ajax_generate_content_schedule'));
    }
    
    /**
     * Maybe schedule content processing
     */
    public function maybe_schedule_content() {
        if (!wp_next_scheduled('autoblog_process_scheduled_content')) {
            wp_schedule_event(time(), 'hourly', 'autoblog_process_scheduled_content');
        }
    }
    
    /**
     * Process scheduled content
     */
    public function process_scheduled_content() {
        global $wpdb;
        
        $settings = get_option('autoblog_settings', array());
        
        // Only process if auto-publish is enabled
        if (empty($settings['auto_publish'])) {
            return;
        }
        
        $table_name = $wpdb->prefix . 'autoblog_schedule';
        
        // Get posts scheduled for today or earlier that haven't been processed
        $scheduled_posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name 
                 WHERE status = 'pending' 
                 AND scheduled_date <= %s 
                 ORDER BY scheduled_date ASC 
                 LIMIT 5",
                current_time('Y-m-d')
            )
        );
        
        if (empty($scheduled_posts)) {
            return;
        }
        
        $openai = new AutoBlog_OpenAI();
        
        foreach ($scheduled_posts as $scheduled_post) {
            // Update status to processing
            $wpdb->update(
                $table_name,
                array('status' => 'processing'),
                array('id' => $scheduled_post->id),
                array('%s'),
                array('%d')
            );
            
            // Generate the post
            $result = $openai->generate_post(array(
                'post_type' => $scheduled_post->post_type,
                'topic' => $scheduled_post->title
            ));
            
            if (is_wp_error($result)) {
                // Mark as failed
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'failed',
                        'content' => $result->get_error_message()
                    ),
                    array('id' => $scheduled_post->id),
                    array('%s', '%s'),
                    array('%d')
                );
                
                // Log the error
                error_log('AutoBlog: Failed to generate post - ' . $result->get_error_message());
                continue;
            }
            
            // Mark as completed
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'completed',
                    'content' => json_encode($result)
                ),
                array('id' => $scheduled_post->id),
                array('%s', '%s'),
                array('%d')
            );
            
            // Send notification if enabled
            $this->send_completion_notification($scheduled_post, $result);
            
            // Add delay between generations to avoid rate limiting
            sleep(2);
        }
    }
    
    /**
     * Generate content schedule
     */
    public function generate_schedule($days = 30) {
        $openai = new AutoBlog_OpenAI();
        return $openai->generate_content_schedule($days);
    }
    
    /**
     * Get scheduled posts
     */
    public function get_scheduled_posts($status = null, $limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_schedule';
        
        $where_clause = '';
        $prepare_values = array();
        
        if ($status) {
            $where_clause = 'WHERE status = %s';
            $prepare_values[] = $status;
        }
        
        $prepare_values[] = $limit;
        
        $query = "SELECT * FROM $table_name $where_clause ORDER BY scheduled_date ASC LIMIT %d";
        
        if (!empty($prepare_values)) {
            return $wpdb->get_results($wpdb->prepare($query, $prepare_values));
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Add single post to schedule
     */
    public function add_to_schedule($title, $post_type, $scheduled_date = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_schedule';
        
        if (!$scheduled_date) {
            $scheduled_date = date('Y-m-d', strtotime('+1 day'));
        }
        
        return $wpdb->insert(
            $table_name,
            array(
                'title' => sanitize_text_field($title),
                'post_type' => sanitize_text_field($post_type),
                'scheduled_date' => sanitize_text_field($scheduled_date),
                'status' => 'pending'
            ),
            array('%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Update scheduled post
     */
    public function update_scheduled_post($id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_schedule';
        
        $allowed_fields = array('title', 'post_type', 'scheduled_date', 'status', 'content');
        $update_data = array();
        $update_format = array();
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $update_data[$field] = sanitize_text_field($value);
                $update_format[] = '%s';
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        return $wpdb->update(
            $table_name,
            $update_data,
            array('id' => intval($id)),
            $update_format,
            array('%d')
        );
    }
    
    /**
     * Delete scheduled post
     */
    public function delete_scheduled_post($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_schedule';
        
        return $wpdb->delete(
            $table_name,
            array('id' => intval($id)),
            array('%d')
        );
    }
    
    /**
     * Get schedule statistics
     */
    public function get_schedule_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_schedule';
        
        $stats = array();
        
        // Total scheduled
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // By status
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM $table_name GROUP BY status"
        );
        
        foreach ($status_counts as $status) {
            $stats[$status->status] = $status->count;
        }
        
        // Upcoming (next 7 days)
        $stats['upcoming'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
                 WHERE status = 'pending' 
                 AND scheduled_date BETWEEN %s AND %s",
                current_time('Y-m-d'),
                date('Y-m-d', strtotime('+7 days'))
            )
        );
        
        // Overdue
        $stats['overdue'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
                 WHERE status = 'pending' 
                 AND scheduled_date < %s",
                current_time('Y-m-d')
            )
        );
        
        return $stats;
    }
    
    /**
     * Generate post immediately
     */
    public function generate_post_now($scheduled_post_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_schedule';
        
        $scheduled_post = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $scheduled_post_id
            )
        );
        
        if (!$scheduled_post) {
            return new WP_Error('not_found', __('Scheduled post not found.', 'autoblog'));
        }
        
        if ($scheduled_post->status !== 'pending') {
            return new WP_Error('invalid_status', __('Post is not in pending status.', 'autoblog'));
        }
        
        // Update status to processing
        $wpdb->update(
            $table_name,
            array('status' => 'processing'),
            array('id' => $scheduled_post_id),
            array('%s'),
            array('%d')
        );
        
        $openai = new AutoBlog_OpenAI();
        
        $result = $openai->generate_post(array(
            'post_type' => $scheduled_post->post_type,
            'topic' => $scheduled_post->title
        ));
        
        if (is_wp_error($result)) {
            // Mark as failed
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'content' => $result->get_error_message()
                ),
                array('id' => $scheduled_post_id),
                array('%s', '%s'),
                array('%d')
            );
            
            return $result;
        }
        
        // Mark as completed
        $wpdb->update(
            $table_name,
            array(
                'status' => 'completed',
                'content' => json_encode($result)
            ),
            array('id' => $scheduled_post_id),
            array('%s', '%s'),
            array('%d')
        );
        
        return $result;
    }
    
    /**
     * Reschedule failed posts
     */
    public function reschedule_failed_posts() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_schedule';
        
        // Get failed posts from the last 24 hours
        $failed_posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name 
                 WHERE status = 'failed' 
                 AND created_at >= %s",
                date('Y-m-d H:i:s', strtotime('-24 hours'))
            )
        );
        
        $rescheduled = 0;
        
        foreach ($failed_posts as $post) {
            // Reschedule for tomorrow
            $new_date = date('Y-m-d', strtotime('+1 day'));
            
            $updated = $wpdb->update(
                $table_name,
                array(
                    'status' => 'pending',
                    'scheduled_date' => $new_date,
                    'content' => null
                ),
                array('id' => $post->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            
            if ($updated) {
                $rescheduled++;
            }
        }
        
        return $rescheduled;
    }
    
    /**
     * Clean up old completed posts
     */
    public function cleanup_old_posts($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_schedule';
        
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name 
                 WHERE status = 'completed' 
                 AND created_at < %s",
                date('Y-m-d H:i:s', strtotime("-{$days} days"))
            )
        );
        
        return $deleted;
    }
    
    /**
     * Get next scheduled post
     */
    public function get_next_scheduled_post() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_schedule';
        
        return $wpdb->get_row(
            "SELECT * FROM $table_name 
             WHERE status = 'pending' 
             ORDER BY scheduled_date ASC 
             LIMIT 1"
        );
    }
    
    /**
     * Send completion notification
     */
    private function send_completion_notification($scheduled_post, $result) {
        $settings = get_option('autoblog_settings', array());
        
        if (empty($settings['email_notifications'])) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        $site_name = get_option('blogname');
        
        $subject = sprintf(__('[%s] AutoBlog: New post published', 'autoblog'), $site_name);
        
        $message = sprintf(
            __('A new post has been automatically generated and published:\n\nTitle: %s\nPost Type: %s\nPost ID: %d\n\nView post: %s', 'autoblog'),
            $result['title'],
            $scheduled_post->post_type,
            $result['post_id'],
            get_permalink($result['post_id'])
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Bulk operations on scheduled posts
     */
    public function bulk_operation($post_ids, $operation) {
        if (empty($post_ids) || !is_array($post_ids)) {
            return false;
        }
        
        $results = array();
        
        foreach ($post_ids as $post_id) {
            switch ($operation) {
                case 'generate':
                    $results[$post_id] = $this->generate_post_now($post_id);
                    break;
                    
                case 'delete':
                    $results[$post_id] = $this->delete_scheduled_post($post_id);
                    break;
                    
                case 'reschedule':
                    $new_date = date('Y-m-d', strtotime('+1 day'));
                    $results[$post_id] = $this->update_scheduled_post($post_id, array(
                        'scheduled_date' => $new_date,
                        'status' => 'pending'
                    ));
                    break;
                    
                default:
                    $results[$post_id] = false;
            }
        }
        
        return $results;
    }
    
    /**
     * Export schedule to CSV
     */
    public function export_schedule_csv() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_schedule';
        $posts = $wpdb->get_results("SELECT * FROM $table_name ORDER BY scheduled_date ASC");
        
        if (empty($posts)) {
            return false;
        }
        
        $filename = 'autoblog-schedule-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, array('ID', 'Title', 'Post Type', 'Scheduled Date', 'Status', 'Created At'));
        
        // CSV data
        foreach ($posts as $post) {
            fputcsv($output, array(
                $post->id,
                $post->title,
                $post->post_type,
                $post->scheduled_date,
                $post->status,
                $post->created_at
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Import schedule from CSV
     */
    public function import_schedule_csv($file_path) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('CSV file not found.', 'autoblog'));
        }
        
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('file_error', __('Could not open CSV file.', 'autoblog'));
        }
        
        // Skip header row
        fgetcsv($handle);
        
        $imported = 0;
        $errors = array();
        
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 3) {
                continue;
            }
            
            $title = sanitize_text_field($data[1] ?? '');
            $post_type = sanitize_text_field($data[2] ?? 'how-to');
            $scheduled_date = sanitize_text_field($data[3] ?? date('Y-m-d'));
            
            if (empty($title)) {
                $errors[] = 'Empty title in row ' . ($imported + 2);
                continue;
            }
            
            $result = $this->add_to_schedule($title, $post_type, $scheduled_date);
            
            if ($result) {
                $imported++;
            } else {
                $errors[] = 'Failed to import: ' . $title;
            }
        }
        
        fclose($handle);
        
        return array(
            'imported' => $imported,
            'errors' => $errors
        );
    }

    /**
     * Generate intelligent content schedule using AI
     */
    public function generate_intelligent_schedule($weeks = 4) {
        $settings = get_option('autoblog_settings', array());
        $onboarding_data = get_option('autoblog_onboarding', array());

        if (empty($settings['openai_api_key']) || empty($settings['blog_description'])) {
            return new WP_Error('missing_config', __('OpenAI API key and blog description are required.', 'autoblog'));
        }

        $openai = new AutoBlog_OpenAI();

        // Build context from onboarding data
        $context = $this->build_schedule_context($settings, $onboarding_data);

        // Generate schedule using AI
        $prompt = $this->build_schedule_prompt($context, $weeks);
        $response = $openai->generate_text($prompt, 'gpt-4o-mini');

        if (is_wp_error($response)) {
            return $response;
        }

        $content = $response['choices'][0]['message']['content'] ?? '';

        // Parse the AI response into schedule items
        $schedule_items = $this->parse_schedule_response($content);

        if (empty($schedule_items)) {
            return new WP_Error('parse_error', __('Failed to parse AI schedule response.', 'autoblog'));
        }

        // Save schedule to database
        $saved_count = $this->save_schedule_items($schedule_items);

        return array(
            'generated' => count($schedule_items),
            'saved' => $saved_count,
            'items' => $schedule_items
        );
    }

    /**
     * Build context for schedule generation
     */
    private function build_schedule_context($settings, $onboarding_data) {
        $context = array(
            'blog_description' => $settings['blog_description'],
            'content_types' => $settings['content_types'] ?? array(),
            'posts_per_week' => $settings['schedule_settings']['posts_per_week'] ?? 2,
            'posting_time' => $settings['schedule_settings']['posting_time'] ?? '09:00'
        );

        // Add onboarding insights
        if (!empty($onboarding_data['questions']) && !empty($onboarding_data['answers'])) {
            $context['onboarding_insights'] = array();
            foreach ($onboarding_data['questions'] as $i => $question) {
                if (!empty($onboarding_data['answers'][$i])) {
                    $context['onboarding_insights'][] = array(
                        'question' => $question,
                        'answer' => $onboarding_data['answers'][$i]
                    );
                }
            }
        }

        return $context;
    }

    /**
     * Build AI prompt for schedule generation
     */
    private function build_schedule_prompt($context, $weeks) {
        $prompt = "You are a content strategist creating a {$weeks}-week blog content schedule.\n\n";

        $prompt .= "Blog Description: {$context['blog_description']}\n\n";

        if (!empty($context['onboarding_insights'])) {
            $prompt .= "Additional Context:\n";
            foreach ($context['onboarding_insights'] as $insight) {
                $prompt .= "- {$insight['question']}: {$insight['answer']}\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "Content Types Available: " . implode(', ', array_keys($context['content_types'])) . "\n";
        $prompt .= "Posts per week: {$context['posts_per_week']}\n\n";

        $prompt .= "Create a diverse, strategic content schedule with:\n";
        $prompt .= "- Mix of different content types\n";
        $prompt .= "- SEO-optimized topics\n";
        $prompt .= "- Logical content progression\n";
        $prompt .= "- Seasonal relevance where appropriate\n\n";

        $prompt .= "Return ONLY a JSON array with this exact format:\n";
        $prompt .= "[\n";
        $prompt .= "  {\n";
        $prompt .= "    \"title\": \"Specific post title\",\n";
        $prompt .= "    \"post_type\": \"how_to_guide\",\n";
        $prompt .= "    \"week\": 1,\n";
        $prompt .= "    \"day_of_week\": \"monday\",\n";
        $prompt .= "    \"description\": \"Brief description of the post content\"\n";
        $prompt .= "  }\n";
        $prompt .= "]\n\n";

        $prompt .= "Generate exactly " . ($weeks * $context['posts_per_week']) . " posts.";

        return $prompt;
    }

    /**
     * Parse AI schedule response
     */
    private function parse_schedule_response($content) {
        // Clean up the response
        $content = trim($content);

        // Extract JSON from response
        if (preg_match('/\[.*\]/s', $content, $matches)) {
            $json_content = $matches[0];
        } else {
            $json_content = $content;
        }

        $schedule_data = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('AutoBlog: Failed to parse schedule JSON: ' . json_last_error_msg());
            return array();
        }

        $schedule_items = array();
        $start_date = new DateTime();

        foreach ($schedule_data as $item) {
            if (!isset($item['title']) || !isset($item['post_type']) || !isset($item['week'])) {
                continue;
            }

            // Calculate scheduled date
            $week_offset = intval($item['week']) - 1;
            $day_offset = $this->get_day_offset($item['day_of_week'] ?? 'monday');

            $scheduled_date = clone $start_date;
            $scheduled_date->modify("+{$week_offset} weeks");
            $scheduled_date->modify("+{$day_offset} days");

            $schedule_items[] = array(
                'title' => sanitize_text_field($item['title']),
                'post_type' => sanitize_text_field($item['post_type']),
                'scheduled_date' => $scheduled_date->format('Y-m-d'),
                'description' => sanitize_textarea_field($item['description'] ?? ''),
                'status' => 'pending'
            );
        }

        return $schedule_items;
    }

    /**
     * Get day offset for scheduling
     */
    private function get_day_offset($day_name) {
        $days = array(
            'monday' => 0,
            'tuesday' => 1,
            'wednesday' => 2,
            'thursday' => 3,
            'friday' => 4,
            'saturday' => 5,
            'sunday' => 6
        );

        return $days[strtolower($day_name)] ?? 0;
    }

    /**
     * Save schedule items to database
     */
    private function save_schedule_items($schedule_items) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'autoblog_schedule';
        $saved_count = 0;

        foreach ($schedule_items as $item) {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'title' => $item['title'],
                    'post_type' => $item['post_type'],
                    'scheduled_date' => $item['scheduled_date'],
                    'status' => $item['status'],
                    'content' => json_encode(array('description' => $item['description'])),
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s')
            );

            if ($result) {
                $saved_count++;
            }
        }

        return $saved_count;
    }

    /**
     * AJAX handler for generating content schedule
     */
    public function ajax_generate_content_schedule() {
        check_ajax_referer('autoblog_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $weeks = intval($_POST['weeks'] ?? 4);
        $weeks = max(1, min(12, $weeks)); // Limit between 1-12 weeks

        $result = $this->generate_intelligent_schedule($weeks);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
}