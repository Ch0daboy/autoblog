<?php
/**
 * Main AutoBlog Class
 *
 * @package AutoBlog
 */

class AutoBlog {
    
    /**
     * Plugin version
     */
    public $version = '1.0.0';
    
    /**
     * Plugin settings
     */
    public $settings;
    
    /**
     * Initialize the plugin
     */
    public function init() {
        $this->load_settings();
        $this->init_hooks();
        $this->init_components();
    }
    
    /**
     * Load plugin settings
     */
    private function load_settings() {
        $this->settings = get_option('autoblog_settings', array());
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Security hooks
        add_filter('wp_kses_allowed_html', array($this, 'allow_iframe_in_posts'), 10, 2);
        add_action('wp_ajax_autoblog_test_api', array($this, 'test_openai_connection'));
        add_action('wp_ajax_autoblog_test_perplexity_api', array($this, 'test_perplexity_connection'));
        add_action('wp_ajax_autoblog_generate_schedule', array($this, 'generate_content_schedule'));
        add_action('wp_ajax_autoblog_generate_post', array($this, 'generate_single_post'));
        add_action('wp_ajax_autoblog_research_topic', array($this, 'research_topic'));
        add_action('wp_ajax_autoblog_generate_research_content', array($this, 'generate_research_content'));
        add_action('wp_ajax_autoblog_get_dashboard_data', array($this, 'get_dashboard_data'));
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        if (is_admin()) {
            new AutoBlog_Admin();
        }
        
        new AutoBlog_Scheduler();
        new AutoBlog_Affiliate();
        new AutoBlog_Comments();
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('autoblog', false, dirname(plugin_basename(AUTOBLOG_PLUGIN_FILE)) . '/languages');
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_script('autoblog-frontend', AUTOBLOG_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), AUTOBLOG_VERSION, true);
        wp_enqueue_style('autoblog-frontend', AUTOBLOG_PLUGIN_URL . 'assets/css/frontend.css', array(), AUTOBLOG_VERSION);
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'autoblog') === false) {
            return;
        }
        
        wp_enqueue_script('autoblog-admin', AUTOBLOG_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), AUTOBLOG_VERSION, true);
        wp_enqueue_style('autoblog-admin', AUTOBLOG_PLUGIN_URL . 'assets/css/admin.css', array(), AUTOBLOG_VERSION);
        
        // Localize script for AJAX
        wp_localize_script('autoblog-admin', 'autoblog_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('autoblog_nonce'),
            'strings' => array(
                'testing_connection' => __('Testing connection...', 'autoblog'),
                'connection_success' => __('Connection successful!', 'autoblog'),
                'connection_failed' => __('Connection failed. Please check your API key.', 'autoblog'),
                'generating_schedule' => __('Generating content schedule...', 'autoblog'),
                'schedule_generated' => __('Content schedule generated successfully!', 'autoblog'),
                'generating_post' => __('Generating post...', 'autoblog'),
                'post_generated' => __('Post generated successfully!', 'autoblog')
            )
        ));
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('autoblog/v1', '/generate-post', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_generate_post'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('autoblog/v1', '/schedule', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_schedule'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('autoblog/v1', '/settings', array(
            'methods' => array('GET', 'POST'),
            'callback' => array($this, 'rest_handle_settings'),
            'permission_callback' => array($this, 'check_permissions')
        ));
    }
    
    /**
     * Check REST API permissions
     */
    public function check_permissions() {
        return current_user_can('manage_options');
    }
    
    /**
     * REST endpoint to generate a post
     */
    public function rest_generate_post($request) {
        $openai = new AutoBlog_OpenAI();
        $result = $openai->generate_post($request->get_params());
        
        if (is_wp_error($result)) {
            return new WP_Error('generation_failed', $result->get_error_message(), array('status' => 500));
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * REST endpoint to get content schedule
     */
    public function rest_get_schedule($request) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_schedule';
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY scheduled_date ASC");
        
        return rest_ensure_response($results);
    }
    
    /**
     * REST endpoint to handle settings
     */
    public function rest_handle_settings($request) {
        if ($request->get_method() === 'GET') {
            return rest_ensure_response($this->settings);
        }
        
        if ($request->get_method() === 'POST') {
            $new_settings = $request->get_json_params();
            
            // Sanitize and validate settings
            $sanitized_settings = $this->sanitize_settings($new_settings);
            
            update_option('autoblog_settings', $sanitized_settings);
            $this->settings = $sanitized_settings;
            
            return rest_ensure_response(array('success' => true, 'settings' => $sanitized_settings));
        }
    }
    
    /**
     * Sanitize settings input
     */
    private function sanitize_settings($settings) {
        $sanitized = array();
        
        $sanitized['gemini_api_key'] = sanitize_text_field($settings['gemini_api_key'] ?? '');
        $sanitized['perplexity_api_key'] = sanitize_text_field($settings['perplexity_api_key'] ?? '');
        $sanitized['blog_description'] = sanitize_textarea_field($settings['blog_description'] ?? '');
        $sanitized['auto_publish'] = (bool) ($settings['auto_publish'] ?? false);
        $sanitized['amazon_affiliate_id'] = sanitize_text_field($settings['amazon_affiliate_id'] ?? '');
        $sanitized['comment_auto_reply'] = (bool) ($settings['comment_auto_reply'] ?? false);
        $sanitized['gsc_connected'] = (bool) ($settings['gsc_connected'] ?? false);
        $sanitized['research_enabled'] = (bool) ($settings['research_enabled'] ?? false);
        $sanitized['research_depth'] = sanitize_text_field($settings['research_depth'] ?? 'medium');
        
        return $sanitized;
    }
    
    /**
     * AJAX handler to test OpenAI connection
     */
    public function test_openai_connection() {
        check_ajax_referer('autoblog_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'autoblog'));
        }
        
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        if (empty($api_key)) {
            wp_send_json_error(__('API key is required', 'autoblog'));
        }
        
        $gemini = new AutoBlog_Gemini();
        $result = $gemini->test_connection($api_key);
        
        if ($result) {
            wp_send_json_success(__('Connection successful!', 'autoblog'));
        } else {
            wp_send_json_error(__('Connection failed. Please check your API key.', 'autoblog'));
        }
    }
    
    /**
     * AJAX handler to generate content schedule
     */
    public function generate_content_schedule() {
        check_ajax_referer('autoblog_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'autoblog'));
        }
        
        $scheduler = new AutoBlog_Scheduler();
        $result = $scheduler->generate_schedule();
        
        if ($result) {
            wp_send_json_success(__('Content schedule generated successfully!', 'autoblog'));
        } else {
            wp_send_json_error(__('Failed to generate content schedule.', 'autoblog'));
        }
    }
    
    /**
     * AJAX handler to generate single post
     */
    public function generate_single_post() {
        check_ajax_referer('autoblog_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'autoblog'));
        }
        
        $post_type = sanitize_text_field($_POST['post_type'] ?? 'how-to');
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        
        $openai = new AutoBlog_OpenAI();
        $result = $openai->generate_post(array(
            'post_type' => $post_type,
            'topic' => $topic
        ));
        
        if (!is_wp_error($result)) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result->get_error_message());
        }
    }
    
    /**
     * Allow iframe in posts for embedded content
     */
    public function allow_iframe_in_posts($allowed, $context) {
        if ($context === 'post') {
            $allowed['iframe'] = array(
                'src' => true,
                'width' => true,
                'height' => true,
                'frameborder' => true,
                'allowfullscreen' => true,
            );
        }
        return $allowed;
    }
    
    /**
     * Get plugin setting
     */
    public function get_setting($key, $default = null) {
        return $this->settings[$key] ?? $default;
    }
    
    /**
     * Update plugin setting
     */
    public function update_setting($key, $value) {
        $this->settings[$key] = $value;
        update_option('autoblog_settings', $this->settings);
    }

    /**
     * AJAX handler to get dashboard data
     */
    public function get_dashboard_data() {
        check_ajax_referer('autoblog_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'autoblog'));
        }

        global $wpdb;

        // Get statistics
        $total_posts = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key = '_autoblog_generated' AND pm.meta_value = '1'"
        );

        $scheduled_posts = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}autoblog_schedule WHERE status = 'pending'"
        );

        $api_calls = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}autoblog_api_usage WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        $data = array(
            'stats' => array(
                'total_posts' => intval($total_posts),
                'scheduled_posts' => intval($scheduled_posts),
                'api_calls' => intval($api_calls)
            )
        );

        wp_send_json_success($data);
    }

    /**
     * AJAX handler to test Perplexity connection
     */
    public function test_perplexity_connection() {
        check_ajax_referer('autoblog_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'autoblog'));
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');

        if (empty($api_key)) {
            wp_send_json_error(__('API key is required', 'autoblog'));
        }

        $perplexity = new AutoBlog_Perplexity();
        $result = $perplexity->test_connection($api_key);

        if ($result) {
            wp_send_json_success(__('Perplexity connection successful!', 'autoblog'));
        } else {
            wp_send_json_error(__('Perplexity connection failed. Please check your API key.', 'autoblog'));
        }
    }

    /**
     * AJAX handler to research a topic
     */
    public function research_topic() {
        check_ajax_referer('autoblog_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'autoblog'));
        }

        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $research_depth = sanitize_text_field($_POST['research_depth'] ?? 'medium');

        if (empty($topic)) {
            wp_send_json_error(__('Topic is required', 'autoblog'));
        }

        $perplexity = new AutoBlog_Perplexity();
        $result = $perplexity->research($topic, array(
            'max_tokens' => $research_depth === 'deep' ? 4000 : 2000,
            'search_recency_filter' => 'month'
        ));

        if (!is_wp_error($result)) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result->get_error_message());
        }
    }

    /**
     * AJAX handler to generate research-backed content
     */
    public function generate_research_content() {
        check_ajax_referer('autoblog_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'autoblog'));
        }

        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $content_type = sanitize_text_field($_POST['content_type'] ?? 'article');
        $research_depth = sanitize_text_field($_POST['research_depth'] ?? 'medium');

        if (empty($topic)) {
            wp_send_json_error(__('Topic is required', 'autoblog'));
        }

        $perplexity = new AutoBlog_Perplexity();
        $research_result = $perplexity->generate_research_content($topic, $content_type, $research_depth);

        if (is_wp_error($research_result)) {
            wp_send_json_error($research_result->get_error_message());
        }

        // Now use the research data to generate the actual content with OpenAI
        $openai = new AutoBlog_OpenAI();
        $content_result = $openai->generate_research_backed_post($research_result);

        if (!is_wp_error($content_result)) {
            wp_send_json_success(array(
                'research' => $research_result,
                'content' => $content_result
            ));
        } else {
            wp_send_json_error($content_result->get_error_message());
        }
    }
}