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
        add_action('admin_init', array($this, 'handle_onboarding_submissions'));
        add_action('wp_ajax_autoblog_approve_content', array($this, 'ajax_approve_content'));
        add_action('wp_ajax_autoblog_reject_content', array($this, 'ajax_reject_content'));
        add_action('wp_ajax_autoblog_generate_schedule', array($this, 'ajax_generate_schedule'));
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
            __('Research & Generate', 'autoblog'),
            __('Research & Generate', 'autoblog'),
            'manage_options',
            'autoblog-research',
            array($this, 'research_page')
        );

        add_submenu_page(
            'autoblog',
            __('Analytics', 'autoblog'),
            __('Analytics', 'autoblog'),
            'manage_options',
            'autoblog-analytics',
            array($this, 'analytics_page')
        );

        add_submenu_page(
            'autoblog',
            __('Onboarding', 'autoblog'),
            __('Onboarding', 'autoblog'),
            'manage_options',
            'autoblog-onboarding',
            array($this, 'onboarding_page')
        );

        add_submenu_page(
            'autoblog',
            __('Content Review', 'autoblog'),
            __('Content Review', 'autoblog'),
            'manage_options',
            'autoblog-review',
            array($this, 'content_review_page')
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
        
        $sanitized['gemini_api_key'] = sanitize_text_field($input['gemini_api_key'] ?? '');
        $sanitized['perplexity_api_key'] = sanitize_text_field($input['perplexity_api_key'] ?? '');
        $sanitized['blog_description'] = sanitize_textarea_field($input['blog_description'] ?? '');
        $sanitized['auto_publish'] = (bool) ($input['auto_publish'] ?? false);
        $sanitized['amazon_affiliate_id'] = sanitize_text_field($input['amazon_affiliate_id'] ?? '');
        $sanitized['comment_auto_reply'] = (bool) ($input['comment_auto_reply'] ?? false);
        $sanitized['gsc_connected'] = (bool) ($input['gsc_connected'] ?? false);
        $sanitized['research_enabled'] = (bool) ($input['research_enabled'] ?? false);
        $sanitized['research_depth'] = sanitize_text_field($input['research_depth'] ?? 'medium');
        
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
                        <th scope="row"><?php _e('Google Gemini API Key', 'autoblog'); ?></th>
                        <td>
                            <input type="password" name="autoblog_settings[gemini_api_key]" value="<?php echo esc_attr($settings['gemini_api_key'] ?? ''); ?>" class="regular-text" />
                            <button type="button" id="test-gemini-connection" class="button"><?php _e('Test Connection', 'autoblog'); ?></button>
                            <p class="description"><?php _e('Enter your Google Gemini API key to enable content generation.', 'autoblog'); ?></p>
                            <div id="connection-status"></div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Perplexity API Key', 'autoblog'); ?></th>
                        <td>
                            <input type="password" name="autoblog_settings[perplexity_api_key]" value="<?php echo esc_attr($settings['perplexity_api_key'] ?? ''); ?>" class="regular-text" />
                            <button type="button" id="test-perplexity-connection" class="button"><?php _e('Test Connection', 'autoblog'); ?></button>
                            <p class="description"><?php _e('Enter your Perplexity API key to enable research-backed content generation.', 'autoblog'); ?></p>
                            <div id="perplexity-connection-status"></div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Research Settings', 'autoblog'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="autoblog_settings[research_enabled]" value="1" <?php checked($settings['research_enabled'] ?? false); ?> />
                                <?php _e('Enable research-backed content generation', 'autoblog'); ?>
                            </label>
                            <p class="description"><?php _e('When enabled, content will be generated using real-time research data from Perplexity.', 'autoblog'); ?></p>

                            <label for="research_depth"><?php _e('Research Depth:', 'autoblog'); ?></label>
                            <select name="autoblog_settings[research_depth]" id="research_depth">
                                <option value="light" <?php selected($settings['research_depth'] ?? 'medium', 'light'); ?>><?php _e('Light - Quick research', 'autoblog'); ?></option>
                                <option value="medium" <?php selected($settings['research_depth'] ?? 'medium', 'medium'); ?>><?php _e('Medium - Balanced research', 'autoblog'); ?></option>
                                <option value="deep" <?php selected($settings['research_depth'] ?? 'medium', 'deep'); ?>><?php _e('Deep - Comprehensive research', 'autoblog'); ?></option>
                            </select>
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
            
            <div class="autoblog-schedule-controls">
                <div class="schedule-generator">
                    <h3><?php _e('Generate Intelligent Schedule', 'autoblog'); ?></h3>
                    <p><?php _e('Let AI create a strategic content calendar based on your blog description and preferences.', 'autoblog'); ?></p>

                    <div class="schedule-options">
                        <label for="schedule-weeks"><?php _e('Number of weeks:', 'autoblog'); ?></label>
                        <select id="schedule-weeks">
                            <option value="2">2 weeks</option>
                            <option value="4" selected>4 weeks</option>
                            <option value="8">8 weeks</option>
                            <option value="12">12 weeks</option>
                        </select>

                        <button type="button" id="generate-schedule" class="button button-primary"><?php _e('Generate Schedule', 'autoblog'); ?></button>
                    </div>

                    <div id="schedule-generation-status" style="display: none;">
                        <p><span class="spinner is-active"></span> <?php _e('Generating intelligent content schedule...', 'autoblog'); ?></p>
                    </div>
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
     * Research & Generate page
     */
    public function research_page() {
        $settings = get_option('autoblog_settings', array());
        $perplexity_configured = !empty($settings['perplexity_api_key']);

        ?>
        <div class="wrap">
            <h1><?php _e('Research & Generate Content', 'autoblog'); ?></h1>

            <?php if (!$perplexity_configured): ?>
                <div class="notice notice-warning">
                    <p><?php _e('Please configure your Perplexity API key in the settings to enable research-backed content generation.', 'autoblog'); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=autoblog-settings'); ?>" class="button button-primary"><?php _e('Go to Settings', 'autoblog'); ?></a></p>
                </div>
            <?php endif; ?>

            <div class="autoblog-research-container">
                <div class="autoblog-research-form">
                    <h2><?php _e('Step 1: Research Topic', 'autoblog'); ?></h2>
                    <form id="research-topic-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Topic/Keyword', 'autoblog'); ?></th>
                                <td>
                                    <input type="text" name="topic" id="research-topic" class="regular-text" placeholder="<?php _e('Enter a topic to research...', 'autoblog'); ?>" required />
                                    <p class="description"><?php _e('Enter a topic or keyword you want to research and create content about.', 'autoblog'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php _e('Research Depth', 'autoblog'); ?></th>
                                <td>
                                    <select name="research_depth" id="research-depth">
                                        <option value="light"><?php _e('Light - Quick overview', 'autoblog'); ?></option>
                                        <option value="medium" selected><?php _e('Medium - Balanced research', 'autoblog'); ?></option>
                                        <option value="deep"><?php _e('Deep - Comprehensive analysis', 'autoblog'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary" <?php echo !$perplexity_configured ? 'disabled' : ''; ?>><?php _e('Start Research', 'autoblog'); ?></button>
                        </p>
                    </form>
                </div>

                <div id="research-results" class="autoblog-research-results" style="display: none;">
                    <h2><?php _e('Research Results', 'autoblog'); ?></h2>
                    <div id="research-content"></div>
                    <div id="research-sources"></div>

                    <h2><?php _e('Step 2: Generate Content', 'autoblog'); ?></h2>
                    <form id="generate-research-content-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Content Type', 'autoblog'); ?></th>
                                <td>
                                    <select name="content_type" id="content-type">
                                        <option value="article"><?php _e('Article', 'autoblog'); ?></option>
                                        <option value="news"><?php _e('News Article', 'autoblog'); ?></option>
                                        <option value="how-to"><?php _e('How-to Guide', 'autoblog'); ?></option>
                                        <option value="review"><?php _e('Review', 'autoblog'); ?></option>
                                        <option value="listicle"><?php _e('Listicle', 'autoblog'); ?></option>
                                        <option value="trend-analysis"><?php _e('Trend Analysis', 'autoblog'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php _e('Generate Content', 'autoblog'); ?></button>
                        </p>
                    </form>
                </div>

                <div id="generated-content" class="autoblog-generated-content" style="display: none;">
                    <h2><?php _e('Generated Content', 'autoblog'); ?></h2>
                    <div id="content-preview"></div>
                    <div id="content-actions">
                        <button type="button" class="button button-primary" id="publish-content"><?php _e('Publish Now', 'autoblog'); ?></button>
                        <button type="button" class="button" id="save-draft"><?php _e('Save as Draft', 'autoblog'); ?></button>
                        <button type="button" class="button" id="regenerate-content"><?php _e('Regenerate', 'autoblog'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .autoblog-research-container {
            max-width: 1200px;
        }
        .autoblog-research-results {
            background: #f9f9f9;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .autoblog-generated-content {
            background: #f0f8ff;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        #research-content {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 3px;
            max-height: 400px;
            overflow-y: auto;
        }
        #research-sources {
            margin: 10px 0;
        }
        #research-sources ul {
            list-style-type: none;
            padding: 0;
        }
        #research-sources li {
            background: white;
            padding: 10px;
            margin: 5px 0;
            border-radius: 3px;
            border-left: 4px solid #0073aa;
        }
        #content-preview {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 3px;
            max-height: 500px;
            overflow-y: auto;
        }
        #content-actions {
            margin: 15px 0;
        }
        #content-actions button {
            margin-right: 10px;
        }
        </style>
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
     * Onboarding page
     */
    public function onboarding_page() {
        $settings = get_option('autoblog_settings', array());
        $onboarding_data = get_option('autoblog_onboarding', array());
        $current_step = $_GET['step'] ?? 1;

        ?>
        <div class="wrap">
            <h1><?php _e('AutoBlog Onboarding', 'autoblog'); ?></h1>

            <div class="autoblog-onboarding">
                <?php $this->render_onboarding_step($current_step, $settings, $onboarding_data); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Content review page
     */
    public function content_review_page() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'autoblog_schedule';
        $pending_content = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE status = 'generated' ORDER BY created_at DESC"
        );

        ?>
        <div class="wrap">
            <h1><?php _e('Content Review', 'autoblog'); ?></h1>

            <div class="autoblog-content-review">
                <?php if (empty($pending_content)): ?>
                    <p><?php _e('No content pending review.', 'autoblog'); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=autoblog-generate'); ?>" class="button button-primary"><?php _e('Generate New Content', 'autoblog'); ?></a></p>
                <?php else: ?>
                    <?php foreach ($pending_content as $content): ?>
                        <div class="autoblog-review-item" data-id="<?php echo $content->id; ?>">
                            <h3><?php echo esc_html($content->title); ?></h3>
                            <p><strong><?php _e('Type:', 'autoblog'); ?></strong> <?php echo esc_html($content->post_type); ?></p>
                            <p><strong><?php _e('Scheduled:', 'autoblog'); ?></strong> <?php echo esc_html($content->scheduled_date); ?></p>

                            <div class="content-preview">
                                <?php
                                $content_data = json_decode($content->content, true);
                                if ($content_data && isset($content_data['content'])) {
                                    echo wp_kses_post(wp_trim_words($content_data['content'], 50));
                                }
                                ?>
                            </div>

                            <div class="review-actions">
                                <button class="button button-primary approve-content" data-id="<?php echo $content->id; ?>"><?php _e('Approve & Publish', 'autoblog'); ?></button>
                                <button class="button edit-content" data-id="<?php echo $content->id; ?>"><?php _e('Edit', 'autoblog'); ?></button>
                                <button class="button button-secondary reject-content" data-id="<?php echo $content->id; ?>"><?php _e('Reject', 'autoblog'); ?></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
     * Render onboarding step
     */
    private function render_onboarding_step($step, $settings, $onboarding_data) {
        switch ($step) {
            case 1:
                $this->render_onboarding_step_1($settings);
                break;
            case 2:
                $this->render_onboarding_step_2($settings, $onboarding_data);
                break;
            case 3:
                $this->render_onboarding_step_3($settings, $onboarding_data);
                break;
            case 4:
                $this->render_onboarding_step_4($settings, $onboarding_data);
                break;
            default:
                $this->render_onboarding_complete($settings, $onboarding_data);
        }
    }

    /**
     * Onboarding Step 1: Basic Setup
     */
    private function render_onboarding_step_1($settings) {
        ?>
        <div class="autoblog-onboarding-step">
            <h2><?php _e('Step 1: Basic Setup', 'autoblog'); ?></h2>
            <p><?php _e('Let\'s start by setting up your OpenAI API key and describing your blog.', 'autoblog'); ?></p>

            <form method="post" action="<?php echo admin_url('admin.php?page=autoblog-onboarding&step=2'); ?>">
                <?php wp_nonce_field('autoblog_onboarding_step1'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('OpenAI API Key', 'autoblog'); ?></th>
                        <td>
                            <input type="password" name="openai_api_key" value="<?php echo esc_attr($settings['openai_api_key'] ?? ''); ?>" class="regular-text" required />
                            <p class="description"><?php _e('Get your API key from OpenAI Platform.', 'autoblog'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Blog Description', 'autoblog'); ?></th>
                        <td>
                            <textarea name="blog_description" rows="5" cols="50" class="large-text" required><?php echo esc_textarea($settings['blog_description'] ?? ''); ?></textarea>
                            <p class="description"><?php _e('Describe your blog\'s niche, target audience, and goals. Be specific!', 'autoblog'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="<?php _e('Continue', 'autoblog'); ?>" />
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Onboarding Step 2: AI Questions
     */
    private function render_onboarding_step_2($settings, $onboarding_data) {
        // Generate clarifying questions based on blog description
        $questions = $this->generate_clarifying_questions($settings['blog_description'] ?? '');

        ?>
        <div class="autoblog-onboarding-step">
            <h2><?php _e('Step 2: Tell Us More', 'autoblog'); ?></h2>
            <p><?php _e('Based on your blog description, we have some questions to better understand your content needs.', 'autoblog'); ?></p>

            <form method="post" action="<?php echo admin_url('admin.php?page=autoblog-onboarding&step=3'); ?>">
                <?php wp_nonce_field('autoblog_onboarding_step2'); ?>

                <div class="autoblog-questions">
                    <?php foreach ($questions as $i => $question): ?>
                        <div class="question-group">
                            <label for="question_<?php echo $i; ?>"><strong><?php echo esc_html($question); ?></strong></label>
                            <textarea name="answers[<?php echo $i; ?>]" id="question_<?php echo $i; ?>" rows="3" cols="50" class="large-text"></textarea>
                            <input type="hidden" name="questions[<?php echo $i; ?>]" value="<?php echo esc_attr($question); ?>" />
                        </div>
                    <?php endforeach; ?>
                </div>

                <p class="submit">
                    <a href="<?php echo admin_url('admin.php?page=autoblog-onboarding&step=1'); ?>" class="button"><?php _e('Back', 'autoblog'); ?></a>
                    <input type="submit" name="submit" class="button button-primary" value="<?php _e('Continue', 'autoblog'); ?>" />
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Onboarding Step 3: Content Preferences
     */
    private function render_onboarding_step_3($settings, $onboarding_data) {
        ?>
        <div class="autoblog-onboarding-step">
            <h2><?php _e('Step 3: Content Preferences', 'autoblog'); ?></h2>
            <p><?php _e('Choose what types of content you\'d like AutoBlog to create for you.', 'autoblog'); ?></p>

            <form method="post" action="<?php echo admin_url('admin.php?page=autoblog-onboarding&step=4'); ?>">
                <?php wp_nonce_field('autoblog_onboarding_step3'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Content Types', 'autoblog'); ?></th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" name="content_types[]" value="how_to_guide" checked /> <?php _e('How-to Guides', 'autoblog'); ?></label><br>
                                <label><input type="checkbox" name="content_types[]" value="product_review" checked /> <?php _e('Product Reviews', 'autoblog'); ?></label><br>
                                <label><input type="checkbox" name="content_types[]" value="listicle" checked /> <?php _e('Listicles', 'autoblog'); ?></label><br>
                                <label><input type="checkbox" name="content_types[]" value="case_study" /> <?php _e('Case Studies', 'autoblog'); ?></label><br>
                                <label><input type="checkbox" name="content_types[]" value="opinion_piece" /> <?php _e('Opinion Pieces', 'autoblog'); ?></label><br>
                                <label><input type="checkbox" name="content_types[]" value="comparison" /> <?php _e('Comparison Posts', 'autoblog'); ?></label><br>
                                <label><input type="checkbox" name="content_types[]" value="tutorial" /> <?php _e('Tutorials', 'autoblog'); ?></label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Publishing Frequency', 'autoblog'); ?></th>
                        <td>
                            <select name="posts_per_week">
                                <option value="1"><?php _e('1 post per week', 'autoblog'); ?></option>
                                <option value="2" selected><?php _e('2 posts per week', 'autoblog'); ?></option>
                                <option value="3"><?php _e('3 posts per week', 'autoblog'); ?></option>
                                <option value="5"><?php _e('5 posts per week (daily)', 'autoblog'); ?></option>
                                <option value="7"><?php _e('7 posts per week', 'autoblog'); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Publishing Time', 'autoblog'); ?></th>
                        <td>
                            <input type="time" name="posting_time" value="09:00" />
                            <p class="description"><?php _e('What time should posts be published?', 'autoblog'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <a href="<?php echo admin_url('admin.php?page=autoblog-onboarding&step=2'); ?>" class="button"><?php _e('Back', 'autoblog'); ?></a>
                    <input type="submit" name="submit" class="button button-primary" value="<?php _e('Continue', 'autoblog'); ?>" />
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Onboarding Step 4: Monetization Setup
     */
    private function render_onboarding_step_4($settings, $onboarding_data) {
        ?>
        <div class="autoblog-onboarding-step">
            <h2><?php _e('Step 4: Monetization (Optional)', 'autoblog'); ?></h2>
            <p><?php _e('Set up affiliate marketing to monetize your content automatically.', 'autoblog'); ?></p>

            <form method="post" action="<?php echo admin_url('admin.php?page=autoblog-onboarding&step=complete'); ?>">
                <?php wp_nonce_field('autoblog_onboarding_step4'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Amazon Affiliate ID', 'autoblog'); ?></th>
                        <td>
                            <input type="text" name="amazon_affiliate_id" value="<?php echo esc_attr($settings['amazon_affiliate_id'] ?? ''); ?>" class="regular-text" />
                            <p class="description"><?php _e('Your Amazon Associates tracking ID (optional).', 'autoblog'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Auto-Publish', 'autoblog'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_publish" value="1" />
                                <?php _e('Automatically publish generated content without review', 'autoblog'); ?>
                            </label>
                            <p class="description"><?php _e('If unchecked, content will be saved as drafts for your review.', 'autoblog'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Comment Auto-Reply', 'autoblog'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="comment_auto_reply" value="1" />
                                <?php _e('Enable AI-powered automatic comment replies', 'autoblog'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <a href="<?php echo admin_url('admin.php?page=autoblog-onboarding&step=3'); ?>" class="button"><?php _e('Back', 'autoblog'); ?></a>
                    <input type="submit" name="submit" class="button button-primary" value="<?php _e('Complete Setup', 'autoblog'); ?>" />
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Onboarding Complete
     */
    private function render_onboarding_complete($settings, $onboarding_data) {
        ?>
        <div class="autoblog-onboarding-step">
            <h2><?php _e('🎉 Setup Complete!', 'autoblog'); ?></h2>
            <p><?php _e('AutoBlog is now configured and ready to start generating content for your blog.', 'autoblog'); ?></p>

            <div class="autoblog-next-steps">
                <h3><?php _e('Next Steps:', 'autoblog'); ?></h3>
                <ol>
                    <li><a href="<?php echo admin_url('admin.php?page=autoblog-schedule'); ?>"><?php _e('Generate your first content schedule', 'autoblog'); ?></a></li>
                    <li><a href="<?php echo admin_url('admin.php?page=autoblog-generate'); ?>"><?php _e('Create your first blog post', 'autoblog'); ?></a></li>
                    <li><a href="<?php echo admin_url('admin.php?page=autoblog-analytics'); ?>"><?php _e('Monitor your content performance', 'autoblog'); ?></a></li>
                </ol>
            </div>

            <p class="submit">
                <a href="<?php echo admin_url('admin.php?page=autoblog'); ?>" class="button button-primary"><?php _e('Go to Dashboard', 'autoblog'); ?></a>
            </p>
        </div>
        <?php
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

    /**
     * Generate clarifying questions based on blog description
     */
    private function generate_clarifying_questions($blog_description) {
        if (empty($blog_description)) {
            return array(
                'What is your target audience?',
                'What tone should your content have?',
                'What are your main content goals?'
            );
        }

        $openai = new AutoBlog_OpenAI();

        $prompt = "Based on this blog description: '{$blog_description}'\n\n";
        $prompt .= "Generate 3-5 specific clarifying questions that would help understand:\n";
        $prompt .= "- Target audience details\n";
        $prompt .= "- Content style preferences\n";
        $prompt .= "- Specific topics of interest\n";
        $prompt .= "- Business goals\n\n";
        $prompt .= "Return only the questions, one per line, without numbering.";

        $response = $openai->generate_text($prompt, 'gpt-4o-mini');

        if (is_wp_error($response)) {
            // Fallback questions
            return array(
                'Who is your target audience?',
                'What tone should your content have (professional, casual, friendly)?',
                'What are your main business goals with this blog?',
                'What specific topics are you most interested in covering?'
            );
        }

        $content = $response['choices'][0]['message']['content'] ?? '';
        $questions = array_filter(explode("\n", $content));

        return array_slice($questions, 0, 5); // Limit to 5 questions
    }

    /**
     * Handle onboarding form submissions
     */
    public function handle_onboarding_submissions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle Step 1 submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'autoblog_onboarding_step1')) {
            $settings = get_option('autoblog_settings', array());
            $settings['openai_api_key'] = sanitize_text_field($_POST['openai_api_key']);
            $settings['blog_description'] = sanitize_textarea_field($_POST['blog_description']);
            update_option('autoblog_settings', $settings);

            wp_redirect(admin_url('admin.php?page=autoblog-onboarding&step=2'));
            exit;
        }

        // Handle Step 2 submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'autoblog_onboarding_step2')) {
            $onboarding_data = get_option('autoblog_onboarding', array());
            $onboarding_data['questions'] = $_POST['questions'] ?? array();
            $onboarding_data['answers'] = $_POST['answers'] ?? array();
            update_option('autoblog_onboarding', $onboarding_data);

            wp_redirect(admin_url('admin.php?page=autoblog-onboarding&step=3'));
            exit;
        }

        // Handle Step 3 submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'autoblog_onboarding_step3')) {
            $settings = get_option('autoblog_settings', array());
            $settings['content_types'] = $_POST['content_types'] ?? array();
            $settings['schedule_settings'] = array(
                'posts_per_week' => intval($_POST['posts_per_week'] ?? 2),
                'posting_time' => sanitize_text_field($_POST['posting_time'] ?? '09:00')
            );
            update_option('autoblog_settings', $settings);

            wp_redirect(admin_url('admin.php?page=autoblog-onboarding&step=4'));
            exit;
        }

        // Handle Step 4 submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'autoblog_onboarding_step4')) {
            $settings = get_option('autoblog_settings', array());
            $settings['amazon_affiliate_id'] = sanitize_text_field($_POST['amazon_affiliate_id'] ?? '');
            $settings['auto_publish'] = isset($_POST['auto_publish']);
            $settings['comment_auto_reply'] = isset($_POST['comment_auto_reply']);
            $settings['onboarding_completed'] = true;
            update_option('autoblog_settings', $settings);

            wp_redirect(admin_url('admin.php?page=autoblog-onboarding&step=complete'));
            exit;
        }
    }

    /**
     * AJAX handler for approving content
     */
    public function ajax_approve_content() {
        check_ajax_referer('autoblog_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $content_id = intval($_POST['content_id']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'autoblog_schedule';

        // Get the content
        $content = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $content_id));

        if (!$content) {
            wp_send_json_error('Content not found');
        }

        $content_data = json_decode($content->content, true);

        // Create the post
        $post_data = array(
            'post_title' => $content->title,
            'post_content' => $content_data['content'],
            'post_status' => 'publish',
            'post_type' => 'post',
            'meta_input' => array(
                '_autoblog_generated' => '1',
                '_autoblog_post_type' => $content->post_type
            )
        );

        $post_id = wp_insert_post($post_data);

        if ($post_id) {
            // Update schedule status
            $wpdb->update(
                $table_name,
                array('status' => 'published', 'post_id' => $post_id),
                array('id' => $content_id),
                array('%s', '%d'),
                array('%d')
            );

            wp_send_json_success('Content published successfully');
        } else {
            wp_send_json_error('Failed to publish content');
        }
    }

    /**
     * AJAX handler for rejecting content
     */
    public function ajax_reject_content() {
        check_ajax_referer('autoblog_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $content_id = intval($_POST['content_id']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'autoblog_schedule';

        $wpdb->update(
            $table_name,
            array('status' => 'rejected'),
            array('id' => $content_id),
            array('%s'),
            array('%d')
        );

        wp_send_json_success('Content rejected');
    }
}