<?php
/**
 * AutoBlog Admin Class
 *
 * @package AutoBlog
 */

class AutoBlog_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AutoBlog', 'autoblog'),
            __('AutoBlog', 'autoblog'),
            'manage_options',
            'autoblog',
            array($this, 'admin_page'),
            'dashicons-edit-large',
            30
        );
        
        add_submenu_page(
            'autoblog',
            __('Settings', 'autoblog'),
            __('Settings', 'autoblog'),
            'manage_options',
            'autoblog-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'autoblog',
            __('Content Schedule', 'autoblog'),
            __('Content Schedule', 'autoblog'),
            'manage_options',
            'autoblog-schedule',
            array($this, 'schedule_page')
        );
        
        add_submenu_page(
            'autoblog',
            __('Generate Content', 'autoblog'),
            __('Generate Content', 'autoblog'),
            'manage_options',
            'autoblog-generate',
            array($this, 'generate_page')
        );
        
        add_submenu_page(
            'autoblog',
            __('Analytics', 'autoblog'),
            __('Analytics', 'autoblog'),
            'manage_options',
            'autoblog-analytics',
            array($this, 'analytics_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('autoblog_settings', 'autoblog_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key'] ?? '');
        $sanitized['blog_description'] = sanitize_textarea_field($input['blog_description'] ?? '');
        $sanitized['auto_publish'] = (bool) ($input['auto_publish'] ?? false);
        $sanitized['amazon_affiliate_id'] = sanitize_text_field($input['amazon_affiliate_id'] ?? '');
        $sanitized['comment_auto_reply'] = (bool) ($input['comment_auto_reply'] ?? false);
        $sanitized['gsc_connected'] = (bool) ($input['gsc_connected'] ?? false);
        
        return $sanitized;
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        $settings = get_option('autoblog_settings', array());
        $is_configured = !empty($settings['openai_api_key']) && !empty($settings['blog_description']);
        
        ?>
        <div class="wrap">
            <h1><?php _e('AutoBlog Dashboard', 'autoblog'); ?></h1>
            
            <?php if (!$is_configured): ?>
                <div class="notice notice-warning">
                    <p><?php _e('Please configure your OpenAI API key and blog description in the settings to get started.', 'autoblog'); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=autoblog-settings'); ?>" class="button button-primary"><?php _e('Go to Settings', 'autoblog'); ?></a></p>
                </div>
            <?php endif; ?>
            
            <div class="autoblog-dashboard">
                <div class="autoblog-cards">
                    <div class="autoblog-card">
                        <h3><?php _e('Quick Actions', 'autoblog'); ?></h3>
                        <p><a href="<?php echo admin_url('admin.php?page=autoblog-generate'); ?>" class="button button-primary"><?php _e('Generate New Post', 'autoblog'); ?></a></p>
                        <p><a href="<?php echo admin_url('admin.php?page=autoblog-schedule'); ?>" class="button"><?php _e('View Schedule', 'autoblog'); ?></a></p>
                        <p><a href="<?php echo admin_url('admin.php?page=autoblog-settings'); ?>" class="button"><?php _e('Settings', 'autoblog'); ?></a></p>
                    </div>
                    
                    <div class="autoblog-card">
                        <h3><?php _e('Recent Activity', 'autoblog'); ?></h3>
                        <?php $this->display_recent_activity(); ?>
                    </div>
                    
                    <div class="autoblog-card">
                        <h3><?php _e('Statistics', 'autoblog'); ?></h3>
                        <?php $this->display_statistics(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        $settings = get_option('autoblog_settings', array());
        
        ?>
        <div class="wrap">
            <h1><?php _e('AutoBlog Settings', 'autoblog'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('autoblog_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('OpenAI API Key', 'autoblog'); ?></th>
                        <td>
                            <input type="password" name="autoblog_settings[openai_api_key]" value="<?php echo esc_attr($settings['openai_api_key'] ?? ''); ?>" class="regular-text" />
                            <button type="button" id="test-openai-connection" class="button"><?php _e('Test Connection', 'autoblog'); ?></button>
                            <p class="description"><?php _e('Enter your OpenAI API key to enable content generation.', 'autoblog'); ?></p>
                            <div id="connection-status"></div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Blog Description', 'autoblog'); ?></th>
                        <td>
                            <textarea name="autoblog_settings[blog_description]" rows="5" cols="50" class="large-text"><?php echo esc_textarea($settings['blog_description'] ?? ''); ?></textarea>
                            <p class="description"><?php _e('Describe your blog niche, tone, target audience, and goals. This helps the AI understand your content style.', 'autoblog'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Auto-Publish', 'autoblog'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="autoblog_settings[auto_publish]" value="1" <?php checked($settings['auto_publish'] ?? false); ?> />
                                <?php _e('Automatically publish generated content without manual approval', 'autoblog'); ?>
                            </label>
                            <p class="description"><?php _e('When enabled, generated posts will be published automatically. Use with caution.', 'autoblog'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Amazon Affiliate ID', 'autoblog'); ?></th>
                        <td>
                            <input type="text" name="autoblog_settings[amazon_affiliate_id]" value="<?php echo esc_attr($settings['amazon_affiliate_id'] ?? ''); ?>" class="regular-text" />
                            <p class="description"><?php _e('Your Amazon Associates tracking ID for affiliate link generation.', 'autoblog'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Comment Auto-Reply', 'autoblog'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="autoblog_settings[comment_auto_reply]" value="1" <?php checked($settings['comment_auto_reply'] ?? false); ?> />
                                <?php _e('Enable AI-powered automatic replies to blog comments', 'autoblog'); ?>
                            </label>
                            <p class="description"><?php _e('The AI will automatically respond to comments on your posts.', 'autoblog'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <h2><?php _e('Content Schedule Generator', 'autoblog'); ?></h2>
            <p><?php _e('Generate a content calendar based on your blog description and preferences.', 'autoblog'); ?></p>
            <button type="button" id="generate-schedule" class="button button-primary"><?php _e('Generate Content Schedule', 'autoblog'); ?></button>
            <div id="schedule-status"></div>
        </div>
        <?php
    }
    
    /**
     * Schedule page
     */
    public function schedule_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_schedule';
        $scheduled_posts = $wpdb->get_results("SELECT * FROM $table_name ORDER BY scheduled_date ASC");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Content Schedule', 'autoblog'); ?></h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <button type="button" id="generate-schedule" class="button"><?php _e('Generate New Schedule', 'autoblog'); ?></button>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Title', 'autoblog'); ?></th>
                        <th><?php _e('Post Type', 'autoblog'); ?></th>
                        <th><?php _e('Scheduled Date', 'autoblog'); ?></th>
                        <th><?php _e('Status', 'autoblog'); ?></th>
                        <th><?php _e('Actions', 'autoblog'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($scheduled_posts)): ?>
                        <tr>
                            <td colspan="5"><?php _e('No scheduled posts found. Generate a content schedule to get started.', 'autoblog'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($scheduled_posts as $post): ?>
                            <tr>
                                <td><?php echo esc_html($post->title); ?></td>
                                <td><?php echo esc_html($post->post_type); ?></td>
                                <td><?php echo esc_html($post->scheduled_date); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($post->status); ?>">
                                        <?php echo esc_html(ucfirst($post->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($post->status === 'pending'): ?>
                                        <button type="button" class="button generate-post" data-id="<?php echo esc_attr($post->id); ?>"><?php _e('Generate Now', 'autoblog'); ?></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Generate content page
     */
    public function generate_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Generate Content', 'autoblog'); ?></h1>
            
            <form id="generate-post-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Post Type', 'autoblog'); ?></th>
                        <td>
                            <select name="post_type" id="post-type">
                                <option value="how-to"><?php _e('How-to Guide', 'autoblog'); ?></option>
                                <option value="review"><?php _e('Product Review', 'autoblog'); ?></option>
                                <option value="listicle"><?php _e('Listicle', 'autoblog'); ?></option>
                                <option value="comparison"><?php _e('Comparison Post', 'autoblog'); ?></option>
                                <option value="tutorial"><?php _e('Tutorial', 'autoblog'); ?></option>
                                <option value="case-study"><?php _e('Case Study', 'autoblog'); ?></option>
                                <option value="faq"><?php _e('FAQ', 'autoblog'); ?></option>
                                <option value="opinion"><?php _e('Opinion Piece', 'autoblog'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Topic/Keyword', 'autoblog'); ?></th>
                        <td>
                            <input type="text" name="topic" id="topic" class="regular-text" placeholder="<?php _e('Enter a topic or keyword...', 'autoblog'); ?>" />
                            <p class="description"><?php _e('Optional: Specify a topic or keyword for the post. Leave blank for AI to choose.', 'autoblog'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Generate Post', 'autoblog'); ?></button>
                </p>
            </form>
            
            <div id="generation-status"></div>
            <div id="generated-content" style="display: none;">
                <h2><?php _e('Generated Content', 'autoblog'); ?></h2>
                <div id="content-preview"></div>
                <p>
                    <button type="button" id="publish-post" class="button button-primary"><?php _e('Publish Post', 'autoblog'); ?></button>
                    <button type="button" id="save-draft" class="button"><?php _e('Save as Draft', 'autoblog'); ?></button>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Analytics page
     */
    public function analytics_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('AutoBlog Analytics', 'autoblog'); ?></h1>
            
            <div class="autoblog-analytics">
                <div class="autoblog-cards">
                    <div class="autoblog-card">
                        <h3><?php _e('Content Generation', 'autoblog'); ?></h3>
                        <?php $this->display_content_stats(); ?>
                    </div>
                    
                    <div class="autoblog-card">
                        <h3><?php _e('API Usage', 'autoblog'); ?></h3>
                        <?php $this->display_api_stats(); ?>
                    </div>
                    
                    <div class="autoblog-card">
                        <h3><?php _e('Performance', 'autoblog'); ?></h3>
                        <?php $this->display_performance_stats(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display recent activity
     */
    private function display_recent_activity() {
        global $wpdb;
        
        $recent_posts = get_posts(array(
            'numberposts' => 5,
            'meta_key' => '_autoblog_generated',
            'meta_value' => '1'
        ));
        
        if (empty($recent_posts)) {
            echo '<p>' . __('No recent activity.', 'autoblog') . '</p>';
            return;
        }
        
        echo '<ul>';
        foreach ($recent_posts as $post) {
            echo '<li><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a> - ' . esc_html($post->post_date) . '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Display statistics
     */
    private function display_statistics() {
        global $wpdb;
        
        $total_generated = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE pm.meta_key = '_autoblog_generated' AND pm.meta_value = '1'"
        );
        
        $scheduled_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}autoblog_schedule WHERE status = 'pending'"
        );
        
        echo '<ul>';
        echo '<li>' . sprintf(__('Total Generated Posts: %d', 'autoblog'), $total_generated) . '</li>';
        echo '<li>' . sprintf(__('Scheduled Posts: %d', 'autoblog'), $scheduled_count) . '</li>';
        echo '</ul>';
    }
    
    /**
     * Display content statistics
     */
    private function display_content_stats() {
        global $wpdb;
        
        $stats = $wpdb->get_results(
            "SELECT 
                DATE(post_date) as date,
                COUNT(*) as count
             FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE pm.meta_key = '_autoblog_generated' 
             AND pm.meta_value = '1'
             AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(post_date)
             ORDER BY date DESC"
        );
        
        if (empty($stats)) {
            echo '<p>' . __('No content generated in the last 30 days.', 'autoblog') . '</p>';
            return;
        }
        
        echo '<ul>';
        foreach ($stats as $stat) {
            echo '<li>' . esc_html($stat->date) . ': ' . esc_html($stat->count) . ' posts</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Display API statistics
     */
    private function display_api_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'autoblog_api_logs';
        
        $total_calls = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $successful_calls = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'success' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        echo '<ul>';
        echo '<li>' . sprintf(__('Total API Calls (30 days): %d', 'autoblog'), $total_calls) . '</li>';
        echo '<li>' . sprintf(__('Successful Calls: %d', 'autoblog'), $successful_calls) . '</li>';
        if ($total_calls > 0) {
            $success_rate = round(($successful_calls / $total_calls) * 100, 2);
            echo '<li>' . sprintf(__('Success Rate: %s%%', 'autoblog'), $success_rate) . '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Display performance statistics
     */
    private function display_performance_stats() {
        global $wpdb;
        
        $avg_generation_time = $wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) 
             FROM {$wpdb->prefix}autoblog_schedule 
             WHERE status = 'completed' 
             AND updated_at IS NOT NULL"
        );
        
        echo '<ul>';
        if ($avg_generation_time) {
            echo '<li>' . sprintf(__('Average Generation Time: %d seconds', 'autoblog'), round($avg_generation_time)) . '</li>';
        } else {
            echo '<li>' . __('No performance data available yet.', 'autoblog') . '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        $settings = get_option('autoblog_settings', array());
        
        if (empty($settings['openai_api_key'])) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php _e('AutoBlog: Please configure your OpenAI API key to start generating content.', 'autoblog'); ?> <a href="<?php echo admin_url('admin.php?page=autoblog-settings'); ?>"><?php _e('Go to Settings', 'autoblog'); ?></a></p>
            </div>
            <?php
        }
    }
}