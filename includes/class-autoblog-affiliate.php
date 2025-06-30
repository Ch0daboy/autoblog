<?php
/**
 * AutoBlog Affiliate Integration Class
 *
 * @package AutoBlog
 */

class AutoBlog_Affiliate {
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * Amazon API endpoints
     */
    private $amazon_endpoints = array(
        'US' => 'webservices.amazon.com',
        'UK' => 'webservices.amazon.co.uk',
        'DE' => 'webservices.amazon.de',
        'FR' => 'webservices.amazon.fr',
        'JP' => 'webservices.amazon.co.jp',
        'CA' => 'webservices.amazon.ca'
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('autoblog_settings', array());
        
        add_filter('the_content', array($this, 'process_affiliate_links'));
        add_action('wp_ajax_autoblog_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_autoblog_generate_affiliate_content', array($this, 'ajax_generate_affiliate_content'));
        add_shortcode('autoblog_product', array($this, 'product_shortcode'));
        add_shortcode('autoblog_comparison', array($this, 'comparison_shortcode'));
    }
    
    /**
     * Process affiliate links in content
     */
    public function process_affiliate_links($content) {
        if (empty($this->settings['amazon_affiliate_id'])) {
            return $content;
        }
        
        // Process Amazon links
        $content = $this->process_amazon_links($content);
        
        // Add affiliate disclosures
        $content = $this->add_affiliate_disclosure($content);
        
        return $content;
    }
    
    /**
     * Process Amazon affiliate links
     */
    private function process_amazon_links($content) {
        $affiliate_id = $this->settings['amazon_affiliate_id'];
        
        // Pattern to match Amazon product URLs
        $pattern = '/https?:\/\/(www\.)?amazon\.(com|co\.uk|de|fr|co\.jp|ca)\/([\w\-\/]+\/)?(dp|gp\/product)\/([A-Z0-9]{10})/i';
        
        $content = preg_replace_callback($pattern, function($matches) use ($affiliate_id) {
            $domain = $matches[2];
            $asin = $matches[5];
            
            // Build affiliate link
            $affiliate_link = "https://amazon.{$domain}/dp/{$asin}?tag={$affiliate_id}";
            
            return $affiliate_link;
        }, $content);
        
        return $content;
    }
    
    /**
     * Add affiliate disclosure
     */
    private function add_affiliate_disclosure($content) {
        // Check if content already has disclosure
        if (strpos($content, 'affiliate') !== false || strpos($content, 'commission') !== false) {
            return $content;
        }
        
        // Check if post has affiliate links
        if (strpos($content, 'amazon.') === false) {
            return $content;
        }
        
        $disclosure = '<div class="autoblog-affiliate-disclosure">';
        $disclosure .= '<p><em>' . __('This post contains affiliate links. We may earn a commission if you purchase through these links at no additional cost to you.', 'autoblog') . '</em></p>';
        $disclosure .= '</div>';
        
        // Add disclosure at the beginning of the content
        return $disclosure . $content;
    }
    
    /**
     * Search for products (placeholder for future Amazon API integration)
     */
    public function search_products($query, $category = null, $limit = 10) {
        // This would integrate with Amazon Product Advertising API
        // For now, return mock data structure
        
        return array(
            'products' => array(
                array(
                    'asin' => 'B08N5WRWNW',
                    'title' => 'Sample Product',
                    'price' => '$29.99',
                    'image' => 'https://via.placeholder.com/300x300',
                    'rating' => 4.5,
                    'reviews' => 1234,
                    'url' => 'https://amazon.com/dp/B08N5WRWNW?tag=' . $this->settings['amazon_affiliate_id']
                )
            ),
            'total' => 1
        );
    }
    
    /**
     * Generate affiliate content for products
     */
    public function generate_affiliate_content($products, $content_type = 'review') {
        if (empty($products)) {
            return '';
        }
        
        $content = '';
        
        switch ($content_type) {
            case 'review':
                $content = $this->generate_product_review($products[0]);
                break;
                
            case 'comparison':
                $content = $this->generate_product_comparison($products);
                break;
                
            case 'list':
                $content = $this->generate_product_list($products);
                break;
                
            default:
                $content = $this->generate_product_showcase($products);
        }
        
        return $content;
    }
    
    /**
     * Generate product review content
     */
    private function generate_product_review($product) {
        $content = '<div class="autoblog-product-review">';
        
        $content .= '<div class="product-header">';
        $content .= '<img src="' . esc_url($product['image']) . '" alt="' . esc_attr($product['title']) . '" class="product-image">';
        $content .= '<div class="product-info">';
        $content .= '<h3>' . esc_html($product['title']) . '</h3>';
        $content .= '<div class="product-rating">' . $this->render_stars($product['rating']) . ' (' . number_format($product['reviews']) . ' reviews)</div>';
        $content .= '<div class="product-price">' . esc_html($product['price']) . '</div>';
        $content .= '<a href="' . esc_url($product['url']) . '" class="affiliate-button" target="_blank" rel="nofollow">' . __('Check Price on Amazon', 'autoblog') . '</a>';
        $content .= '</div>';
        $content .= '</div>';
        
        $content .= '<div class="product-details">';
        $content .= '<h4>' . __('Product Overview', 'autoblog') . '</h4>';
        $content .= '<p>' . sprintf(__('The %s is a popular choice among consumers, offering great value for money.', 'autoblog'), esc_html($product['title'])) . '</p>';
        
        $content .= '<h4>' . __('Pros and Cons', 'autoblog') . '</h4>';
        $content .= '<div class="pros-cons">';
        $content .= '<div class="pros"><h5>' . __('Pros:', 'autoblog') . '</h5><ul><li>' . __('High quality construction', 'autoblog') . '</li><li>' . __('Great value for money', 'autoblog') . '</li><li>' . __('Positive customer reviews', 'autoblog') . '</li></ul></div>';
        $content .= '<div class="cons"><h5>' . __('Cons:', 'autoblog') . '</h5><ul><li>' . __('Limited color options', 'autoblog') . '</li><li>' . __('May not suit all preferences', 'autoblog') . '</li></ul></div>';
        $content .= '</div>';
        
        $content .= '<div class="final-verdict">';
        $content .= '<h4>' . __('Final Verdict', 'autoblog') . '</h4>';
        $content .= '<p>' . sprintf(__('Overall, the %s is a solid choice that delivers on its promises. With a rating of %.1f stars from %s customers, it\'s clearly a popular option.', 'autoblog'), esc_html($product['title']), $product['rating'], number_format($product['reviews'])) . '</p>';
        $content .= '<a href="' . esc_url($product['url']) . '" class="affiliate-button-large" target="_blank" rel="nofollow">' . __('Buy Now on Amazon', 'autoblog') . '</a>';
        $content .= '</div>';
        
        $content .= '</div>';
        $content .= '</div>';
        
        return $content;
    }
    
    /**
     * Generate product comparison content
     */
    private function generate_product_comparison($products) {
        if (count($products) < 2) {
            return $this->generate_product_review($products[0]);
        }
        
        $content = '<div class="autoblog-product-comparison">';
        $content .= '<h3>' . __('Product Comparison', 'autoblog') . '</h3>';
        
        $content .= '<div class="comparison-table">';
        $content .= '<table>';
        $content .= '<thead><tr><th>' . __('Product', 'autoblog') . '</th><th>' . __('Price', 'autoblog') . '</th><th>' . __('Rating', 'autoblog') . '</th><th>' . __('Reviews', 'autoblog') . '</th><th>' . __('Action', 'autoblog') . '</th></tr></thead>';
        $content .= '<tbody>';
        
        foreach ($products as $product) {
            $content .= '<tr>';
            $content .= '<td><img src="' . esc_url($product['image']) . '" alt="' . esc_attr($product['title']) . '" width="50"> ' . esc_html($product['title']) . '</td>';
            $content .= '<td>' . esc_html($product['price']) . '</td>';
            $content .= '<td>' . $this->render_stars($product['rating']) . '</td>';
            $content .= '<td>' . number_format($product['reviews']) . '</td>';
            $content .= '<td><a href="' . esc_url($product['url']) . '" class="affiliate-button-small" target="_blank" rel="nofollow">' . __('View', 'autoblog') . '</a></td>';
            $content .= '</tr>';
        }
        
        $content .= '</tbody></table>';
        $content .= '</div>';
        $content .= '</div>';
        
        return $content;
    }
    
    /**
     * Generate product list content
     */
    private function generate_product_list($products) {
        $content = '<div class="autoblog-product-list">';
        
        foreach ($products as $index => $product) {
            $rank = $index + 1;
            $content .= '<div class="product-item">';
            $content .= '<div class="product-rank">' . $rank . '</div>';
            $content .= '<img src="' . esc_url($product['image']) . '" alt="' . esc_attr($product['title']) . '" class="product-image">';
            $content .= '<div class="product-details">';
            $content .= '<h4>' . esc_html($product['title']) . '</h4>';
            $content .= '<div class="product-rating">' . $this->render_stars($product['rating']) . ' (' . number_format($product['reviews']) . ' reviews)</div>';
            $content .= '<div class="product-price">' . esc_html($product['price']) . '</div>';
            $content .= '<a href="' . esc_url($product['url']) . '" class="affiliate-button" target="_blank" rel="nofollow">' . __('Check Price', 'autoblog') . '</a>';
            $content .= '</div>';
            $content .= '</div>';
        }
        
        $content .= '</div>';
        
        return $content;
    }
    
    /**
     * Generate product showcase content
     */
    private function generate_product_showcase($products) {
        $content = '<div class="autoblog-product-showcase">';
        
        foreach ($products as $product) {
            $content .= '<div class="showcase-item">';
            $content .= '<img src="' . esc_url($product['image']) . '" alt="' . esc_attr($product['title']) . '">';
            $content .= '<h4>' . esc_html($product['title']) . '</h4>';
            $content .= '<div class="rating">' . $this->render_stars($product['rating']) . '</div>';
            $content .= '<div class="price">' . esc_html($product['price']) . '</div>';
            $content .= '<a href="' . esc_url($product['url']) . '" class="affiliate-link" target="_blank" rel="nofollow">' . __('View on Amazon', 'autoblog') . '</a>';
            $content .= '</div>';
        }
        
        $content .= '</div>';
        
        return $content;
    }
    
    /**
     * Render star rating
     */
    private function render_stars($rating) {
        $stars = '';
        $full_stars = floor($rating);
        $half_star = ($rating - $full_stars) >= 0.5;
        
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $full_stars) {
                $stars .= '<span class="star full">★</span>';
            } elseif ($i == $full_stars + 1 && $half_star) {
                $stars .= '<span class="star half">☆</span>';
            } else {
                $stars .= '<span class="star empty">☆</span>';
            }
        }
        
        return '<span class="star-rating">' . $stars . ' ' . number_format($rating, 1) . '</span>';
    }
    
    /**
     * Product shortcode
     */
    public function product_shortcode($atts) {
        $atts = shortcode_atts(array(
            'asin' => '',
            'title' => '',
            'price' => '',
            'image' => '',
            'rating' => '0',
            'reviews' => '0'
        ), $atts);
        
        if (empty($atts['asin'])) {
            return '';
        }
        
        $product = array(
            'asin' => $atts['asin'],
            'title' => $atts['title'],
            'price' => $atts['price'],
            'image' => $atts['image'],
            'rating' => floatval($atts['rating']),
            'reviews' => intval($atts['reviews']),
            'url' => 'https://amazon.com/dp/' . $atts['asin'] . '?tag=' . $this->settings['amazon_affiliate_id']
        );
        
        return $this->generate_product_showcase(array($product));
    }
    
    /**
     * Comparison shortcode
     */
    public function comparison_shortcode($atts) {
        $atts = shortcode_atts(array(
            'products' => ''
        ), $atts);
        
        if (empty($atts['products'])) {
            return '';
        }
        
        $product_asins = explode(',', $atts['products']);
        $products = array();
        
        foreach ($product_asins as $asin) {
            $asin = trim($asin);
            if (!empty($asin)) {
                // In a real implementation, you would fetch product data from Amazon API
                $products[] = array(
                    'asin' => $asin,
                    'title' => 'Product ' . $asin,
                    'price' => '$XX.XX',
                    'image' => 'https://via.placeholder.com/150x150',
                    'rating' => 4.0,
                    'reviews' => 100,
                    'url' => 'https://amazon.com/dp/' . $asin . '?tag=' . $this->settings['amazon_affiliate_id']
                );
            }
        }
        
        return $this->generate_product_comparison($products);
    }
    
    /**
     * AJAX handler for product search
     */
    public function ajax_search_products() {
        check_ajax_referer('autoblog_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'autoblog'));
        }
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');
        
        if (empty($query)) {
            wp_send_json_error(__('Search query is required', 'autoblog'));
        }
        
        $results = $this->search_products($query, $category);
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for generating affiliate content
     */
    public function ajax_generate_affiliate_content() {
        check_ajax_referer('autoblog_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'autoblog'));
        }
        
        $products = $_POST['products'] ?? array();
        $content_type = sanitize_text_field($_POST['content_type'] ?? 'review');
        
        if (empty($products)) {
            wp_send_json_error(__('No products selected', 'autoblog'));
        }
        
        $content = $this->generate_affiliate_content($products, $content_type);
        
        wp_send_json_success(array('content' => $content));
    }
    
    /**
     * Get affiliate statistics
     */
    public function get_affiliate_stats($days = 30) {
        global $wpdb;
        
        $stats = array();
        
        // Posts with affiliate links
        $affiliate_posts = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) 
                 FROM {$wpdb->posts} p 
                 WHERE p.post_content LIKE %s 
                 AND p.post_status = 'publish' 
                 AND p.post_date >= %s",
                '%amazon.%',
                date('Y-m-d', strtotime("-{$days} days"))
            )
        );
        
        $stats['affiliate_posts'] = $affiliate_posts;
        
        // Total affiliate links
        $total_links = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$wpdb->posts} p 
                 WHERE p.post_content LIKE %s 
                 AND p.post_status = 'publish' 
                 AND p.post_date >= %s",
                '%amazon.%',
                date('Y-m-d', strtotime("-{$days} days"))
            )
        );
        
        $stats['total_links'] = $total_links;
        
        return $stats;
    }
    
    /**
     * Clean affiliate links (remove tracking parameters)
     */
    public function clean_affiliate_links($content) {
        // Remove existing affiliate tags
        $content = preg_replace('/([?&])tag=[^&]*(&|$)/', '$1', $content);
        
        // Clean up any double ampersands or question marks
        $content = preg_replace('/[?&]{2,}/', '?', $content);
        $content = preg_replace('/[?&]$/', '', $content);
        
        return $content;
    }
    
    /**
     * Validate affiliate ID format
     */
    public function validate_affiliate_id($affiliate_id) {
        // Amazon affiliate IDs are typically 8-20 characters, alphanumeric with hyphens
        return preg_match('/^[a-zA-Z0-9-]{8,20}$/', $affiliate_id);
    }
    
    /**
     * Get supported affiliate networks
     */
    public function get_supported_networks() {
        return array(
            'amazon' => array(
                'name' => 'Amazon Associates',
                'domains' => array('amazon.com', 'amazon.co.uk', 'amazon.de', 'amazon.fr', 'amazon.co.jp', 'amazon.ca'),
                'param' => 'tag'
            )
            // Future: Add support for other networks
            // 'shareasale' => array(...),
            // 'cj' => array(...)
        );
    }
}