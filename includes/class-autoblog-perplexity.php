<?php
/**
 * AutoBlog Perplexity AI Integration Class
 *
 * @package AutoBlog
 */

class AutoBlog_Perplexity {
    
    /**
     * Perplexity API endpoint
     */
    private $api_endpoint = 'https://api.perplexity.ai/';
    
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
        $this->api_key = $this->settings['perplexity_api_key'] ?? '';
    }
    
    /**
     * Test Perplexity API connection
     */
    public function test_connection($api_key = null) {
        $key = $api_key ?: $this->api_key;
        
        if (empty($key)) {
            return false;
        }
        
        // Test with a simple query
        $test_query = "What is artificial intelligence?";
        $response = $this->research($test_query, array('max_tokens' => 50));
        
        return !is_wp_error($response) && !empty($response['content']);
    }
    
    /**
     * Conduct research using Perplexity API
     */
    public function research($query, $options = array()) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Perplexity API key not configured.', 'autoblog'));
        }
        
        $defaults = array(
            'model' => 'llama-3.1-sonar-small-128k-online',
            'max_tokens' => 2000,
            'temperature' => 0.2,
            'return_citations' => true,
            'return_images' => false,
            'search_domain_filter' => array(),
            'search_recency_filter' => 'month'
        );
        
        $options = wp_parse_args($options, $defaults);
        
        $data = array(
            'model' => $options['model'],
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a helpful research assistant. Provide accurate, well-sourced information with proper citations.'
                ),
                array(
                    'role' => 'user',
                    'content' => $query
                )
            ),
            'max_tokens' => $options['max_tokens'],
            'temperature' => $options['temperature'],
            'return_citations' => $options['return_citations'],
            'return_images' => $options['return_images']
        );
        
        // Add search filters if specified
        if (!empty($options['search_domain_filter'])) {
            $data['search_domain_filter'] = $options['search_domain_filter'];
        }
        
        if (!empty($options['search_recency_filter'])) {
            $data['search_recency_filter'] = $options['search_recency_filter'];
        }
        
        $response = $this->make_request('chat/completions', $data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->parse_research_response($response);
    }
    
    /**
     * Generate research-backed content
     */
    public function generate_research_content($topic, $content_type = 'article', $research_depth = 'medium') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Perplexity API key not configured.', 'autoblog'));
        }
        
        // Step 1: Conduct initial research
        $research_query = $this->build_research_query($topic, $content_type, $research_depth);
        $research_data = $this->research($research_query, array(
            'max_tokens' => $research_depth === 'deep' ? 4000 : 2000,
            'search_recency_filter' => $this->get_recency_filter($content_type)
        ));
        
        if (is_wp_error($research_data)) {
            return $research_data;
        }
        
        // Step 2: Generate follow-up research questions if needed
        $follow_up_questions = $this->generate_follow_up_questions($topic, $research_data['content'], $content_type);
        
        $additional_research = array();
        if (!empty($follow_up_questions) && $research_depth !== 'light') {
            foreach (array_slice($follow_up_questions, 0, 3) as $question) {
                $additional_data = $this->research($question, array('max_tokens' => 1000));
                if (!is_wp_error($additional_data)) {
                    $additional_research[] = $additional_data;
                }
            }
        }
        
        // Step 3: Compile comprehensive research data
        $compiled_research = $this->compile_research_data($research_data, $additional_research);
        
        return array(
            'topic' => $topic,
            'content_type' => $content_type,
            'research_data' => $compiled_research,
            'sources' => $this->extract_sources($compiled_research),
            'key_points' => $this->extract_key_points($compiled_research),
            'generated_at' => current_time('mysql')
        );
    }
    
    /**
     * Build research query based on topic and content type
     */
    private function build_research_query($topic, $content_type, $research_depth) {
        $blog_description = $this->settings['blog_description'] ?? '';
        
        $query_templates = array(
            'article' => "Provide comprehensive information about {topic}. Include recent developments, key facts, statistics, and expert opinions.",
            'news' => "What are the latest news and developments about {topic}? Include recent events, updates, and current trends.",
            'how-to' => "Provide detailed step-by-step information about how to {topic}. Include best practices, common mistakes, and expert tips.",
            'review' => "Provide detailed information about {topic} including features, pros and cons, comparisons, and user experiences.",
            'listicle' => "Provide comprehensive information about {topic} that can be organized into a list format with detailed explanations.",
            'trend-analysis' => "Analyze current trends related to {topic}. Include market data, statistics, and future predictions."
        );
        
        $template = $query_templates[$content_type] ?? $query_templates['article'];
        $query = str_replace('{topic}', $topic, $template);
        
        if (!empty($blog_description)) {
            $query .= " Context: This is for a blog about: " . $blog_description;
        }
        
        if ($research_depth === 'deep') {
            $query .= " Provide in-depth analysis with multiple perspectives and detailed explanations.";
        }
        
        return $query;
    }
    
    /**
     * Get recency filter based on content type
     */
    private function get_recency_filter($content_type) {
        $recency_map = array(
            'news' => 'day',
            'trend-analysis' => 'week',
            'article' => 'month',
            'how-to' => 'year',
            'review' => 'month',
            'listicle' => 'month'
        );
        
        return $recency_map[$content_type] ?? 'month';
    }
    
    /**
     * Generate follow-up research questions
     */
    private function generate_follow_up_questions($topic, $initial_research, $content_type) {
        $prompt = "Based on this research about '{$topic}' for a {$content_type}:\n\n{$initial_research}\n\n";
        $prompt .= "Generate 3-5 specific follow-up research questions that would provide additional valuable information. ";
        $prompt .= "Focus on gaps in the information, recent developments, or specific details that would enhance the content.";
        
        $response = $this->research($prompt, array(
            'max_tokens' => 500,
            'temperature' => 0.3
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        // Extract questions from the response
        $questions = array();
        $lines = explode("\n", $response['content']);
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^\d+\.?\s*(.+\?)$/', $line, $matches) || 
                preg_match('/^[-*]\s*(.+\?)$/', $line, $matches)) {
                $questions[] = trim($matches[1]);
            }
        }
        
        return $questions;
    }
    
    /**
     * Compile research data from multiple sources
     */
    private function compile_research_data($primary_research, $additional_research) {
        $compiled = array(
            'primary_content' => $primary_research['content'],
            'primary_citations' => $primary_research['citations'] ?? array(),
            'additional_content' => array(),
            'all_citations' => $primary_research['citations'] ?? array()
        );
        
        foreach ($additional_research as $research) {
            $compiled['additional_content'][] = $research['content'];
            if (!empty($research['citations'])) {
                $compiled['all_citations'] = array_merge($compiled['all_citations'], $research['citations']);
            }
        }
        
        // Remove duplicate citations
        $compiled['all_citations'] = array_unique($compiled['all_citations'], SORT_REGULAR);
        
        return $compiled;
    }
    
    /**
     * Extract sources from research data
     */
    private function extract_sources($research_data) {
        $sources = array();
        
        if (!empty($research_data['all_citations'])) {
            foreach ($research_data['all_citations'] as $citation) {
                if (is_array($citation) && !empty($citation['url'])) {
                    $sources[] = array(
                        'title' => $citation['title'] ?? 'Unknown Title',
                        'url' => $citation['url'],
                        'domain' => parse_url($citation['url'], PHP_URL_HOST)
                    );
                }
            }
        }
        
        return $sources;
    }
    
    /**
     * Extract key points from research data
     */
    private function extract_key_points($research_data) {
        $content = $research_data['primary_content'];
        
        // Simple extraction of key points (can be enhanced with NLP)
        $sentences = preg_split('/[.!?]+/', $content);
        $key_points = array();
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 50 && strlen($sentence) < 200) {
                // Look for sentences that might contain key information
                if (preg_match('/\b(important|key|significant|major|primary|main|according to|research shows|studies indicate)\b/i', $sentence)) {
                    $key_points[] = $sentence . '.';
                }
            }
        }
        
        return array_slice($key_points, 0, 5); // Return top 5 key points
    }
    
    /**
     * Parse research response from Perplexity API
     */
    private function parse_research_response($response) {
        if (empty($response['choices'][0]['message']['content'])) {
            return new WP_Error('empty_response', __('Empty response from Perplexity API.', 'autoblog'));
        }
        
        $content = $response['choices'][0]['message']['content'];
        $citations = array();
        
        // Extract citations if available
        if (!empty($response['citations'])) {
            $citations = $response['citations'];
        }
        
        return array(
            'content' => $content,
            'citations' => $citations,
            'usage' => $response['usage'] ?? array()
        );
    }
    
    /**
     * Make API request to Perplexity
     */
    private function make_request($endpoint, $data, $method = 'POST', $api_key = null) {
        $key = $api_key ?: $this->api_key;
        
        if (empty($key)) {
            return new WP_Error('no_api_key', __('Perplexity API key not provided.', 'autoblog'));
        }
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
                'User-Agent' => 'AutoBlog-WordPress-Plugin/1.0'
            ),
            'timeout' => 60,
            'body' => $method !== 'GET' ? json_encode($data) : null
        );
        
        if ($method === 'GET' && !empty($data)) {
            $endpoint .= '?' . http_build_query($data);
        }
        
        $response = wp_remote_request($this->api_endpoint . $endpoint, $args);
        
        if (is_wp_error($response)) {
            $this->log_api_usage($endpoint, $data, $response->get_error_message(), 'error');
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Log the raw response for debugging
        error_log('Perplexity API Response: ' . $body);
        
        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_msg = 'Invalid JSON response from Perplexity API. Raw response: ' . substr($body, 0, 500);
            $this->log_api_usage($endpoint, $data, $error_msg, 'error');
            return new WP_Error('json_error', __($error_msg, 'autoblog'));
        }
        
        if ($status_code !== 200) {
            $error_msg = $decoded['error']['message'] ?? 'Unknown API error';
            $this->log_api_usage($endpoint, $data, $error_msg, 'error');
            return new WP_Error('api_error', __('Perplexity API Error: ' . $error_msg, 'autoblog'));
        }
        
        $this->log_api_usage($endpoint, $data, 'success', 'success');
        
        return $decoded;
    }
    
    /**
     * Log API usage for analytics
     */
    private function log_api_usage($endpoint, $request_data, $response, $status) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_api_usage';
        
        $wpdb->insert(
            $table_name,
            array(
                'api_provider' => 'perplexity',
                'endpoint' => $endpoint,
                'request_tokens' => $this->estimate_tokens(json_encode($request_data)),
                'response_tokens' => $this->estimate_tokens(is_string($response) ? $response : json_encode($response)),
                'status' => $status,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%d', '%s', '%s')
        );
    }
    
    /**
     * Estimate token count (rough approximation)
     */
    private function estimate_tokens($text) {
        return ceil(str_word_count($text) * 1.3);
    }
}
