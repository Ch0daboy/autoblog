<?php
/**
 * AutoBlog OpenAI Integration Class
 *
 * @package AutoBlog
 */

class AutoBlog_OpenAI {
    
    /**
     * OpenAI API endpoint
     */
    private $api_endpoint = 'https://api.openai.com/v1/';
    
    /**
     * API key
     */
    private $api_key;
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('autoblog_settings', array());
        $this->api_key = $this->settings['openai_api_key'] ?? '';
    }
    
    /**
     * Test OpenAI API connection
     */
    public function test_connection($api_key = null) {
        $key = $api_key ?: $this->api_key;
        
        if (empty($key)) {
            return false;
        }
        
        $response = $this->make_request('models', array(), 'GET', $key);
        
        return !is_wp_error($response) && isset($response['data']);
    }
    
    /**
     * Generate a blog post
     */
    public function generate_post($params = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured.', 'autoblog'));
        }
        
        $post_type = $params['post_type'] ?? 'how-to';
        $topic = $params['topic'] ?? '';
        $blog_description = $this->settings['blog_description'] ?? '';
        
        // Generate the prompt based on post type and blog description
        $prompt = $this->build_content_prompt($post_type, $topic, $blog_description);
        
        // Generate the main content
        $content_response = $this->generate_text($prompt);
        
        if (is_wp_error($content_response)) {
            return $content_response;
        }
        
        $content = $content_response['choices'][0]['message']['content'] ?? '';
        
        if (empty($content)) {
            return new WP_Error('empty_content', __('Generated content is empty.', 'autoblog'));
        }
        
        // Parse the generated content
        $parsed_content = $this->parse_generated_content($content);
        
        // Generate featured image if needed
        $featured_image = $this->generate_featured_image($parsed_content['title']);
        
        // Add affiliate links if Amazon ID is configured
        if (!empty($this->settings['amazon_affiliate_id'])) {
            $parsed_content['content'] = $this->add_affiliate_links($parsed_content['content'], $post_type);
        }
        
        // Create the post
        $post_data = array(
            'post_title' => $parsed_content['title'],
            'post_content' => $parsed_content['content'],
            'post_excerpt' => $parsed_content['excerpt'],
            'post_status' => $this->settings['auto_publish'] ? 'publish' : 'draft',
            'post_type' => 'post',
            'meta_input' => array(
                '_autoblog_generated' => '1',
                '_autoblog_post_type' => $post_type,
                '_autoblog_generation_date' => current_time('mysql'),
                '_yoast_wpseo_title' => $parsed_content['seo_title'],
                '_yoast_wpseo_metadesc' => $parsed_content['meta_description']
            )
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Set featured image
        if ($featured_image && !is_wp_error($featured_image)) {
            set_post_thumbnail($post_id, $featured_image);
        }
        
        // Add categories and tags
        $this->add_post_taxonomy($post_id, $parsed_content['categories'], $parsed_content['tags']);
        
        // Log the API usage
        $this->log_api_usage('content_generation', $prompt, $content);
        
        return array(
            'post_id' => $post_id,
            'title' => $parsed_content['title'],
            'content' => $parsed_content['content'],
            'status' => $post_data['post_status']
        );
    }
    
    /**
     * Build content generation prompt
     */
    private function build_content_prompt($post_type, $topic, $blog_description) {
        $base_prompt = "You are an expert content writer. Create a comprehensive, engaging, and SEO-optimized blog post.\n\n";
        
        $base_prompt .= "Blog Context: {$blog_description}\n\n";
        
        $type_prompts = array(
            'how-to' => "Write a detailed how-to guide that provides step-by-step instructions.",
            'review' => "Write an in-depth product review with pros, cons, and recommendations.",
            'listicle' => "Create a well-researched listicle with actionable items.",
            'comparison' => "Write a comprehensive comparison post analyzing different options.",
            'tutorial' => "Create a detailed tutorial with clear explanations and examples.",
            'case-study' => "Write a compelling case study with real-world insights.",
            'faq' => "Create a comprehensive FAQ post addressing common questions.",
            'opinion' => "Write a thoughtful opinion piece with well-reasoned arguments."
        );
        
        $prompt = $base_prompt . ($type_prompts[$post_type] ?? $type_prompts['how-to']);
        
        if (!empty($topic)) {
            $prompt .= "\n\nTopic/Keyword: {$topic}";
        }
        
        $prompt .= "\n\nRequirements:
- Minimum 1500 words
- Include relevant subheadings (H2, H3)
- Add a compelling introduction and conclusion
- Include actionable tips and insights
- Optimize for SEO with natural keyword usage
- Add internal linking opportunities (use placeholder [INTERNAL_LINK: anchor text])
- Include call-to-action where appropriate\n\n";
        
        $prompt .= "Format your response as JSON with the following structure:
{
  \"title\": \"SEO-optimized title\",
  \"seo_title\": \"Meta title (60 chars max)\",
  \"meta_description\": \"Meta description (160 chars max)\",
  \"excerpt\": \"Post excerpt (155 chars max)\",
  \"content\": \"Full HTML content with proper formatting\",
  \"categories\": [\"category1\", \"category2\"],
  \"tags\": [\"tag1\", \"tag2\", \"tag3\"]
}";
        
        return $prompt;
    }
    
    /**
     * Generate text using OpenAI API
     */
    private function generate_text($prompt, $model = 'gpt-4o-mini') {
        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a professional content writer and SEO expert. Always respond with valid JSON format.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 4000,
            'temperature' => 0.7,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        );
        
        return $this->make_request('chat/completions', $data);
    }
    
    /**
     * Generate featured image using DALL-E
     */
    public function generate_featured_image($title) {
        if (empty($this->api_key)) {
            return false;
        }
        
        $prompt = "Create a professional, high-quality featured image for a blog post titled: {$title}. Style: modern, clean, relevant to the topic, suitable for web use.";
        
        $data = array(
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
            'quality' => 'standard',
            'response_format' => 'url'
        );
        
        $response = $this->make_request('images/generations', $data);
        
        if (is_wp_error($response) || empty($response['data'][0]['url'])) {
            return false;
        }
        
        $image_url = $response['data'][0]['url'];
        
        // Download and save the image
        return $this->save_generated_image($image_url, $title);
    }
    
    /**
     * Save generated image to WordPress media library
     */
    private function save_generated_image($image_url, $title) {
        $upload_dir = wp_upload_dir();
        $image_data = wp_remote_get($image_url);
        
        if (is_wp_error($image_data)) {
            return false;
        }
        
        $image_content = wp_remote_retrieve_body($image_data);
        $filename = sanitize_file_name($title) . '-' . time() . '.png';
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        if (file_put_contents($file_path, $image_content) === false) {
            return false;
        }
        
        $file_type = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $file_type['type'],
            'post_title' => $title,
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $file_path);
        
        if (!is_wp_error($attachment_id)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attachment_data);
            
            return $attachment_id;
        }
        
        return false;
    }
    
    /**
     * Parse generated content from JSON
     */
    private function parse_generated_content($content) {
        // Try to extract JSON from the response
        $json_start = strpos($content, '{');
        $json_end = strrpos($content, '}');
        
        if ($json_start !== false && $json_end !== false) {
            $json_content = substr($content, $json_start, $json_end - $json_start + 1);
            $parsed = json_decode($json_content, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                return array(
                    'title' => $parsed['title'] ?? 'Generated Post',
                    'seo_title' => $parsed['seo_title'] ?? $parsed['title'] ?? 'Generated Post',
                    'meta_description' => $parsed['meta_description'] ?? '',
                    'excerpt' => $parsed['excerpt'] ?? '',
                    'content' => $parsed['content'] ?? $content,
                    'categories' => $parsed['categories'] ?? array(),
                    'tags' => $parsed['tags'] ?? array()
                );
            }
        }
        
        // Fallback: parse content manually
        $lines = explode("\n", $content);
        $title = trim($lines[0] ?? 'Generated Post');
        
        return array(
            'title' => $title,
            'seo_title' => $title,
            'meta_description' => '',
            'excerpt' => '',
            'content' => $content,
            'categories' => array(),
            'tags' => array()
        );
    }
    
    /**
     * Add affiliate links to content
     */
    private function add_affiliate_links($content, $post_type) {
        if ($post_type !== 'review' && $post_type !== 'comparison') {
            return $content;
        }
        
        $affiliate_id = $this->settings['amazon_affiliate_id'];
        
        // Simple affiliate link injection (can be enhanced)
        $affiliate_cta = "\n\n<div class='affiliate-cta'>\n";
        $affiliate_cta .= "<p><strong>Recommended Products:</strong></p>\n";
        $affiliate_cta .= "<p><a href='https://amazon.com/?tag={$affiliate_id}' target='_blank' rel='nofollow'>Check Latest Prices on Amazon</a></p>\n";
        $affiliate_cta .= "</div>\n";
        
        return $content . $affiliate_cta;
    }
    
    /**
     * Add categories and tags to post
     */
    private function add_post_taxonomy($post_id, $categories, $tags) {
        if (!empty($categories)) {
            $category_ids = array();
            foreach ($categories as $category) {
                $term = get_term_by('name', $category, 'category');
                if (!$term) {
                    $term = wp_insert_term($category, 'category');
                    if (!is_wp_error($term)) {
                        $category_ids[] = $term['term_id'];
                    }
                } else {
                    $category_ids[] = $term->term_id;
                }
            }
            wp_set_post_categories($post_id, $category_ids);
        }
        
        if (!empty($tags)) {
            wp_set_post_tags($post_id, $tags);
        }
    }
    
    /**
     * Generate content schedule
     */
    public function generate_content_schedule($days = 30) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured.', 'autoblog'));
        }
        
        $blog_description = $this->settings['blog_description'] ?? '';
        
        $prompt = "Based on this blog description: {$blog_description}\n\n";
        $prompt .= "Generate a {$days}-day content calendar with diverse post types. Include:\n";
        $prompt .= "- How-to guides\n- Product reviews\n- Listicles\n- Tutorials\n- Comparison posts\n- Case studies\n- FAQ posts\n- Opinion pieces\n\n";
        $prompt .= "Format as JSON array with objects containing: title, post_type, scheduled_date (YYYY-MM-DD format starting from tomorrow)\n";
        $prompt .= "Ensure good variety and SEO-focused titles.";
        
        $response = $this->generate_text($prompt);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $content = $response['choices'][0]['message']['content'] ?? '';
        
        // Parse JSON response
        $json_start = strpos($content, '[');
        $json_end = strrpos($content, ']');
        
        if ($json_start !== false && $json_end !== false) {
            $json_content = substr($content, $json_start, $json_end - $json_start + 1);
            $schedule = json_decode($json_content, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($schedule)) {
                return $this->save_content_schedule($schedule);
            }
        }
        
        return new WP_Error('parse_error', __('Failed to parse generated schedule.', 'autoblog'));
    }
    
    /**
     * Save content schedule to database
     */
    private function save_content_schedule($schedule) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_schedule';
        
        // Clear existing schedule
        $wpdb->query("DELETE FROM $table_name WHERE status = 'pending'");
        
        $inserted = 0;
        foreach ($schedule as $item) {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'post_type' => sanitize_text_field($item['post_type'] ?? 'how-to'),
                    'title' => sanitize_text_field($item['title'] ?? ''),
                    'scheduled_date' => sanitize_text_field($item['scheduled_date'] ?? date('Y-m-d')),
                    'status' => 'pending'
                ),
                array('%s', '%s', '%s', '%s')
            );
            
            if ($result !== false) {
                $inserted++;
            }
        }
        
        return $inserted;
    }
    
    /**
     * Make API request to OpenAI
     */
    private function make_request($endpoint, $data = array(), $method = 'POST', $api_key = null) {
        $key = $api_key ?: $this->api_key;
        
        if (empty($key)) {
            return new WP_Error('no_api_key', __('OpenAI API key is required.', 'autoblog'));
        }
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
                'User-Agent' => 'AutoBlog-WordPress-Plugin/1.0'
            ),
            'timeout' => 120,
            'sslverify' => true
        );
        
        if ($method === 'POST' && !empty($data)) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($this->api_endpoint . $endpoint, $args);
        
        if (is_wp_error($response)) {
            $this->log_api_usage($endpoint, $data, $response->get_error_message(), 'error');
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Log the raw response for debugging
        error_log('OpenAI API Response: ' . $body);
        
        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_msg = 'Invalid JSON response from OpenAI API. Raw response: ' . substr($body, 0, 500);
            $this->log_api_usage($endpoint, $data, $error_msg, 'error');
            return new WP_Error('json_error', __($error_msg, 'autoblog'));
        }
        
        if ($status_code >= 400) {
            $error_message = $decoded['error']['message'] ?? 'Unknown API error. Status: ' . $status_code;
            $this->log_api_usage($endpoint, $data, $error_message, 'error');
            return new WP_Error('api_error', $error_message);
        }
        
        $this->log_api_usage($endpoint, $data, $decoded, 'success');
        return $decoded;
    }
    
    /**
     * Log API usage
     */
    private function log_api_usage($endpoint, $request_data, $response_data, $status = 'success') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_api_usage';
        
        // Calculate tokens and cost (simplified estimation)
        $tokens_used = 0;
        $cost = 0;
        
        if (is_array($request_data) && isset($request_data['messages'])) {
            foreach ($request_data['messages'] as $message) {
                $tokens_used += str_word_count($message['content']) * 1.3; // Rough estimation
            }
        }
        
        if (is_array($response_data) && isset($response_data['choices'][0]['message']['content'])) {
            $tokens_used += str_word_count($response_data['choices'][0]['message']['content']) * 1.3;
        }
        
        // Rough cost calculation (adjust based on current OpenAI pricing)
        $cost = $tokens_used * 0.00002; // Approximate cost per token
        
        $wpdb->insert(
            $table_name,
            array(
                'api_type' => 'openai',
                'endpoint' => $endpoint,
                'tokens_used' => intval($tokens_used),
                'cost' => $cost,
                'response_time' => 0, // Could be calculated if needed
                'status' => $status,
                'error_message' => $status === 'error' ? (is_string($response_data) ? $response_data : json_encode($response_data)) : null,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%f', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Generate AI reply to comment
     */
    public function generate_comment_reply($comment_content, $post_title, $post_content) {
        if (empty($this->api_key)) {
            return false;
        }
        
        $blog_description = $this->settings['blog_description'] ?? '';
        
        $prompt = "You are responding to a blog comment. Be helpful, engaging, and professional.\n\n";
        $prompt .= "Blog context: {$blog_description}\n\n";
        $prompt .= "Post title: {$post_title}\n\n";
        $prompt .= "Comment: {$comment_content}\n\n";
        $prompt .= "Generate a thoughtful, helpful reply (max 200 words). Be conversational but professional.";
        
        $response = $this->generate_text($prompt, 'gpt-4o-mini');
        
        if (is_wp_error($response)) {
            return false;
        }
        
        return $response['choices'][0]['message']['content'] ?? false;
    }

    /**
     * Generate research-backed blog post using Perplexity research data
     */
    public function generate_research_backed_post($research_data) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured.', 'autoblog'));
        }

        $topic = $research_data['topic'];
        $content_type = $research_data['content_type'];
        $research_content = $research_data['research_data']['primary_content'];
        $sources = $research_data['sources'];
        $key_points = $research_data['key_points'];
        $blog_description = $this->settings['blog_description'] ?? '';

        // Build comprehensive prompt with research data
        $prompt = $this->build_research_backed_prompt($topic, $content_type, $research_content, $sources, $key_points, $blog_description);

        // Generate the main content
        $content_response = $this->generate_text($prompt, 'gpt-4o');

        if (is_wp_error($content_response)) {
            return $content_response;
        }

        $content = $content_response['choices'][0]['message']['content'] ?? '';

        if (empty($content)) {
            return new WP_Error('empty_content', __('Generated content is empty.', 'autoblog'));
        }

        // Parse the generated content
        $parsed_content = $this->parse_generated_content($content);

        // Add research sources to the content
        $parsed_content['content'] = $this->add_research_sources($parsed_content['content'], $sources);

        // Generate featured image if needed
        $featured_image = $this->generate_featured_image($parsed_content['title']);

        // Add affiliate links if Amazon ID is configured
        if (!empty($this->settings['amazon_affiliate_id'])) {
            $parsed_content['content'] = $this->add_affiliate_links($parsed_content['content'], $content_type);
        }

        // Create the post
        $post_data = array(
            'post_title' => $parsed_content['title'],
            'post_content' => $parsed_content['content'],
            'post_excerpt' => $parsed_content['excerpt'],
            'post_status' => $this->settings['auto_publish'] ? 'publish' : 'draft',
            'post_type' => 'post',
            'meta_input' => array(
                '_autoblog_generated' => '1',
                '_autoblog_post_type' => $content_type,
                '_autoblog_research_backed' => '1',
                '_autoblog_generation_date' => current_time('mysql'),
                '_autoblog_research_sources' => json_encode($sources),
                '_yoast_wpseo_title' => $parsed_content['seo_title'],
                '_yoast_wpseo_metadesc' => $parsed_content['meta_description']
            )
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Set featured image if generated
        if ($featured_image && !is_wp_error($featured_image)) {
            set_post_thumbnail($post_id, $featured_image);
        }

        // Log the generation
        $this->log_api_usage('research-backed-generation', array(
            'topic' => $topic,
            'content_type' => $content_type,
            'post_id' => $post_id
        ), 'success', 'success');

        return array(
            'post_id' => $post_id,
            'title' => $parsed_content['title'],
            'content' => $parsed_content['content'],
            'sources' => $sources,
            'research_data' => $research_data
        );
    }

    /**
     * Build prompt for research-backed content generation
     */
    private function build_research_backed_prompt($topic, $content_type, $research_content, $sources, $key_points, $blog_description) {
        $prompt = "You are a professional content writer creating a {$content_type} about '{$topic}'. ";

        if (!empty($blog_description)) {
            $prompt .= "Blog context: {$blog_description}\n\n";
        }

        $prompt .= "RESEARCH DATA:\n";
        $prompt .= $research_content . "\n\n";

        if (!empty($key_points)) {
            $prompt .= "KEY POINTS TO INCLUDE:\n";
            foreach ($key_points as $point) {
                $prompt .= "- " . $point . "\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "INSTRUCTIONS:\n";
        $prompt .= "1. Create comprehensive, well-structured content based on the research data\n";
        $prompt .= "2. Include specific facts, statistics, and insights from the research\n";
        $prompt .= "3. Write in an engaging, informative style appropriate for the content type\n";
        $prompt .= "4. Ensure the content is original while incorporating the research findings\n";
        $prompt .= "5. Include relevant subheadings and structure for readability\n";
        $prompt .= "6. Add a compelling introduction and conclusion\n";
        $prompt .= "7. Optimize for SEO with natural keyword integration\n\n";

        $prompt .= "Return the response in this exact JSON format:\n";
        $prompt .= "{\n";
        $prompt .= '  "title": "Compelling blog post title",';
        $prompt .= '  "content": "Full blog post content with HTML formatting",';
        $prompt .= '  "excerpt": "Brief excerpt (150-160 characters)",';
        $prompt .= '  "seo_title": "SEO-optimized title (60 characters max)",';
        $prompt .= '  "meta_description": "Meta description (150-160 characters)"';
        $prompt .= "\n}";

        return $prompt;
    }

    /**
     * Add research sources to content
     */
    private function add_research_sources($content, $sources) {
        if (empty($sources)) {
            return $content;
        }

        $sources_html = "\n\n<h3>Sources and References</h3>\n<ul class=\"autoblog-sources\">\n";

        foreach ($sources as $index => $source) {
            $sources_html .= sprintf(
                '<li><a href="%s" target="_blank" rel="noopener">%s</a> - %s</li>' . "\n",
                esc_url($source['url']),
                esc_html($source['title']),
                esc_html($source['domain'])
            );
        }

        $sources_html .= "</ul>\n";

        return $content . $sources_html;
    }
}