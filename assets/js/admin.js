/**
 * AutoBlog Admin JavaScript
 */

(function($) {
    'use strict';
    
    var AutoBlogAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initCharts();
            this.initScheduleTable();
            this.loadDashboardData();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Test OpenAI connection
            $('#test-openai-connection').on('click', this.testOpenAIConnection);

            // Test Perplexity connection
            $('#test-perplexity-connection').on('click', this.testPerplexityConnection);

            // Research functionality
            $('#research-topic-form').on('submit', this.researchTopic);
            $('#generate-research-content-form').on('submit', this.generateResearchContent);

            // Generate content schedule
            $('#generate-schedule').on('click', this.generateSchedule);
            
            // Generate single post
            $('#generate-single-post').on('click', this.generateSinglePost);
            
            // Schedule actions
            $('.schedule-action').on('click', this.handleScheduleAction);
            
            // Bulk actions
            $('#bulk-action-apply').on('click', this.handleBulkAction);
            
            // Settings form validation
            $('#autoblog-settings-form').on('submit', this.validateSettings);
            
            // Content type change
            $('#content_type').on('change', this.handleContentTypeChange);
            
            // Export/Import
            $('#export-schedule').on('click', this.exportSchedule);
            $('#import-schedule').on('change', this.importSchedule);
            
            // Comment reply generation
            $('.generate-comment-reply').on('click', this.generateCommentReply);
            
            // Affiliate content generation
            $('#generate-affiliate-content').on('click', this.generateAffiliateContent);

            // Content review actions
            $('.approve-content').on('click', this.approveContent);
            $('.reject-content').on('click', this.rejectContent);
            $('.edit-content').on('click', this.editContent);

            // Real-time updates
            this.startRealTimeUpdates();
        },
        
        /**
         * Test OpenAI API connection
         */
        testOpenAIConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $status = $('#connection-status');
            
            $button.prop('disabled', true).text('Testing...');
            $status.removeClass('success error').text('Testing connection...');
            
            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'autoblog_test_openai',
                    nonce: autoblog_admin.nonce,
                    api_key: $('#openai_api_key').val()
                },
                success: function(response) {
                    if (response.success) {
                        $status.addClass('success').text('✓ Connection successful!');
                    } else {
                        $status.addClass('error').text('✗ ' + response.data);
                    }
                },
                error: function() {
                    $status.addClass('error').text('✗ Connection failed');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },

        /**
         * Test Perplexity API connection
         */
        testPerplexityConnection: function(e) {
            e.preventDefault();

            var $button = $(this);
            var $status = $('#perplexity-connection-status');

            $button.prop('disabled', true).text('Testing...');
            $status.removeClass('success error').text('Testing connection...');

            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'autoblog_test_perplexity_api',
                    nonce: autoblog_admin.nonce,
                    api_key: $('input[name="autoblog_settings[perplexity_api_key]"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        $status.addClass('success').text('✓ Connection successful!');
                    } else {
                        $status.addClass('error').text('✗ ' + response.data);
                    }
                },
                error: function() {
                    $status.addClass('error').text('✗ Connection failed');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },

        /**
         * Research topic using Perplexity
         */
        researchTopic: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var topic = $('#research-topic').val();
            var researchDepth = $('#research-depth').val();

            if (!topic.trim()) {
                alert('Please enter a topic to research.');
                return;
            }

            $button.prop('disabled', true).text('Researching...');

            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'autoblog_research_topic',
                    nonce: autoblog_admin.nonce,
                    topic: topic,
                    research_depth: researchDepth
                },
                success: function(response) {
                    if (response.success) {
                        AutoBlogAdmin.displayResearchResults(response.data);
                        $('#research-results').show();
                    } else {
                        alert('Research failed: ' + response.data);
                    }
                },
                error: function() {
                    alert('Research request failed. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Start Research');
                }
            });
        },

        /**
         * Generate content based on research
         */
        generateResearchContent: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var topic = $('#research-topic').val();
            var contentType = $('#content-type').val();
            var researchDepth = $('#research-depth').val();

            $button.prop('disabled', true).text('Generating Content...');

            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'autoblog_generate_research_content',
                    nonce: autoblog_admin.nonce,
                    topic: topic,
                    content_type: contentType,
                    research_depth: researchDepth
                },
                success: function(response) {
                    if (response.success) {
                        AutoBlogAdmin.displayGeneratedContent(response.data);
                        $('#generated-content').show();
                    } else {
                        alert('Content generation failed: ' + response.data);
                    }
                },
                error: function() {
                    alert('Content generation request failed. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Generate Content');
                }
            });
        },

        /**
         * Display research results
         */
        displayResearchResults: function(data) {
            var $content = $('#research-content');
            var $sources = $('#research-sources');

            // Display research content
            $content.html('<h3>Research Summary</h3><p>' + data.content.replace(/\n/g, '</p><p>') + '</p>');

            // Display sources if available
            if (data.citations && data.citations.length > 0) {
                var sourcesHtml = '<h3>Sources</h3><ul>';
                data.citations.forEach(function(citation) {
                    if (citation.url) {
                        sourcesHtml += '<li><a href="' + citation.url + '" target="_blank">' +
                                      (citation.title || citation.url) + '</a></li>';
                    }
                });
                sourcesHtml += '</ul>';
                $sources.html(sourcesHtml);
            } else {
                $sources.html('<h3>Sources</h3><p>No specific sources available for this research.</p>');
            }
        },

        /**
         * Display generated content
         */
        displayGeneratedContent: function(data) {
            var $preview = $('#content-preview');

            if (data.content && data.content.title) {
                var contentHtml = '<h2>' + data.content.title + '</h2>';
                contentHtml += '<div class="content-body">' + data.content.content + '</div>';

                if (data.research && data.research.sources && data.research.sources.length > 0) {
                    contentHtml += '<h3>Research Sources</h3><ul>';
                    data.research.sources.forEach(function(source) {
                        contentHtml += '<li><a href="' + source.url + '" target="_blank">' +
                                      source.title + '</a> - ' + source.domain + '</li>';
                    });
                    contentHtml += '</ul>';
                }

                $preview.html(contentHtml);

                // Store data for publishing
                $('#generated-content').data('content-data', data);
            } else {
                $preview.html('<p>Error: Invalid content data received.</p>');
            }
        },

        /**
         * Generate content schedule
         */
        generateSchedule: function(e) {
            e.preventDefault();

            var $button = $(this);
            var $status = $('#schedule-generation-status');
            var weeks = $('#schedule-weeks').val() || 4;

            var data = {
                action: 'autoblog_generate_content_schedule',
                nonce: autoblog_admin.nonce,
                weeks: weeks
            };

            $button.prop('disabled', true).text('Generating...');
            $status.show();

            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $status.html('<p style="color: green;">✓ Generated ' + response.data.generated + ' posts successfully! Saved ' + response.data.saved + ' to schedule.</p>');
                        // Refresh the page to show new schedule
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        $status.html('<p style="color: red;">✗ ' + response.data + '</p>');
                    }
                },
                error: function() {
                    $status.html('<p style="color: red;">✗ Failed to generate schedule</p>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Generate Schedule');
                }
            });
        },
        
        /**
         * Generate single post
         */
        generateSinglePost: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $status = $('#generation-status');
            
            var data = {
                action: 'autoblog_generate_single_post',
                nonce: autoblog_admin.nonce,
                content_type: $('#single_content_type').val(),
                topic: $('#post_topic').val(),
                auto_publish: $('#auto_publish_single').is(':checked')
            };
            
            $button.prop('disabled', true).text('Generating...');
            $status.text('Generating post...');
            
            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $status.text('✓ Post generated successfully!');
                        if (response.data.post_url) {
                            $status.append(' <a href="' + response.data.post_url + '" target="_blank">View Post</a>');
                        }
                    } else {
                        $status.text('✗ ' + response.data);
                    }
                },
                error: function() {
                    $status.text('✗ Failed to generate post');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Generate Post');
                }
            });
        },
        
        /**
         * Handle schedule actions
         */
        handleScheduleAction: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var action = $button.data('action');
            var postId = $button.data('post-id');
            
            if (action === 'delete' && !confirm('Are you sure you want to delete this scheduled post?')) {
                return;
            }
            
            $button.prop('disabled', true);
            
            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'autoblog_schedule_action',
                    nonce: autoblog_admin.nonce,
                    schedule_action: action,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        if (action === 'delete') {
                            $button.closest('tr').fadeOut();
                        } else {
                            AutoBlogAdmin.refreshScheduleTable();
                        }
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('Action failed');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },
        
        /**
         * Handle bulk actions
         */
        handleBulkAction: function(e) {
            e.preventDefault();
            
            var action = $('#bulk-action-selector').val();
            var selectedPosts = [];
            
            $('.schedule-checkbox:checked').each(function() {
                selectedPosts.push($(this).val());
            });
            
            if (!action || selectedPosts.length === 0) {
                alert('Please select an action and at least one post.');
                return;
            }
            
            if (action === 'delete' && !confirm('Are you sure you want to delete the selected posts?')) {
                return;
            }
            
            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'autoblog_bulk_schedule_action',
                    nonce: autoblog_admin.nonce,
                    bulk_action: action,
                    post_ids: selectedPosts
                },
                success: function(response) {
                    if (response.success) {
                        AutoBlogAdmin.refreshScheduleTable();
                        alert('Bulk action completed successfully.');
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('Bulk action failed');
                }
            });
        },
        
        /**
         * Validate settings form
         */
        validateSettings: function(e) {
            var apiKey = $('#openai_api_key').val();
            
            if (!apiKey || apiKey.length < 10) {
                alert('Please enter a valid OpenAI API key.');
                e.preventDefault();
                return false;
            }
            
            return true;
        },
        
        /**
         * Handle content type change
         */
        handleContentTypeChange: function() {
            var contentType = $(this).val();
            var $topicField = $('#post_topic').closest('.form-row');
            
            if (contentType === 'custom') {
                $topicField.show();
                $('#post_topic').prop('required', true);
            } else {
                $topicField.hide();
                $('#post_topic').prop('required', false);
            }
        },
        
        /**
         * Export schedule
         */
        exportSchedule: function(e) {
            e.preventDefault();
            
            window.location.href = autoblog_admin.ajax_url + '?action=autoblog_export_schedule&nonce=' + autoblog_admin.nonce;
        },
        
        /**
         * Import schedule
         */
        importSchedule: function(e) {
            var file = e.target.files[0];
            
            if (!file) {
                return;
            }
            
            var formData = new FormData();
            formData.append('action', 'autoblog_import_schedule');
            formData.append('nonce', autoblog_admin.nonce);
            formData.append('schedule_file', file);
            
            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert('Schedule imported successfully!');
                        AutoBlogAdmin.refreshScheduleTable();
                    } else {
                        alert('Import failed: ' + response.data);
                    }
                },
                error: function() {
                    alert('Import failed');
                }
            });
        },
        
        /**
         * Generate comment reply
         */
        generateCommentReply: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var commentId = $button.data('comment-id');
            
            $button.prop('disabled', true).text('Generating...');
            
            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'autoblog_generate_comment_reply',
                    nonce: autoblog_admin.nonce,
                    comment_id: commentId
                },
                success: function(response) {
                    if (response.success) {
                        var $replyBox = $('<div class="comment-reply-box">');
                        $replyBox.html(
                            '<h4>Generated Reply:</h4>' +
                            '<textarea class="reply-content" rows="4">' + response.data.reply + '</textarea>' +
                            '<div class="reply-actions">' +
                            '<button class="button button-primary approve-reply" data-comment-id="' + commentId + '">Post Reply</button>' +
                            '<button class="button cancel-reply">Cancel</button>' +
                            '</div>'
                        );
                        
                        $button.after($replyBox);
                        
                        // Bind reply actions
                        $replyBox.find('.approve-reply').on('click', AutoBlogAdmin.approveCommentReply);
                        $replyBox.find('.cancel-reply').on('click', function() {
                            $replyBox.remove();
                        });
                    } else {
                        alert('Failed to generate reply: ' + response.data);
                    }
                },
                error: function() {
                    alert('Failed to generate reply');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Generate Reply');
                }
            });
        },
        
        /**
         * Approve comment reply
         */
        approveCommentReply: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var commentId = $button.data('comment-id');
            var replyContent = $button.closest('.comment-reply-box').find('.reply-content').val();
            
            $button.prop('disabled', true).text('Posting...');
            
            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'autoblog_approve_reply',
                    nonce: autoblog_admin.nonce,
                    comment_id: commentId,
                    reply_content: replyContent
                },
                success: function(response) {
                    if (response.success) {
                        alert('Reply posted successfully!');
                        $button.closest('.comment-reply-box').remove();
                    } else {
                        alert('Failed to post reply: ' + response.data);
                    }
                },
                error: function() {
                    alert('Failed to post reply');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Post Reply');
                }
            });
        },
        
        /**
         * Generate affiliate content
         */
        generateAffiliateContent: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var data = {
                action: 'autoblog_generate_affiliate_content',
                nonce: autoblog_admin.nonce,
                content_type: $('#affiliate_content_type').val(),
                product_query: $('#product_query').val(),
                target_keywords: $('#target_keywords').val()
            };
            
            $button.prop('disabled', true).text('Generating...');
            
            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $('#affiliate-content-preview').html(response.data.content);
                        $('#affiliate-content-actions').show();
                    } else {
                        alert('Failed to generate content: ' + response.data);
                    }
                },
                error: function() {
                    alert('Failed to generate content');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Generate Content');
                }
            });
        },
        
        /**
         * Initialize charts
         */
        initCharts: function() {
            if (typeof Chart === 'undefined') {
                return;
            }
            
            // Content generation chart
            var contentCtx = document.getElementById('content-chart');
            if (contentCtx) {
                this.initContentChart(contentCtx);
            }
            
            // API usage chart
            var apiCtx = document.getElementById('api-chart');
            if (apiCtx) {
                this.initApiChart(apiCtx);
            }
        },
        
        /**
         * Initialize content generation chart
         */
        initContentChart: function(ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: autoblog_admin.chart_data.content_labels,
                    datasets: [{
                        label: 'Posts Generated',
                        data: autoblog_admin.chart_data.content_data,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize API usage chart
         */
        initApiChart: function(ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: autoblog_admin.chart_data.api_labels,
                    datasets: [{
                        label: 'API Calls',
                        data: autoblog_admin.chart_data.api_data,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize schedule table
         */
        initScheduleTable: function() {
            // Select all checkbox
            $('#select-all-schedule').on('change', function() {
                $('.schedule-checkbox').prop('checked', $(this).is(':checked'));
            });
            
            // Individual checkboxes
            $('.schedule-checkbox').on('change', function() {
                var allChecked = $('.schedule-checkbox:checked').length === $('.schedule-checkbox').length;
                $('#select-all-schedule').prop('checked', allChecked);
            });
        },
        
        /**
         * Refresh schedule table
         */
        refreshScheduleTable: function() {
            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'autoblog_refresh_schedule_table',
                    nonce: autoblog_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#schedule-table-container').html(response.data.html);
                        AutoBlogAdmin.initScheduleTable();
                    }
                }
            });
        },
        
        /**
         * Load dashboard data
         */
        loadDashboardData: function() {
            if ($('#autoblog-dashboard').length === 0) {
                return;
            }

            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'autoblog_get_dashboard_data',
                    nonce: autoblog_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        AutoBlogAdmin.updateDashboardStats(response.data);
                    }
                }
            });
        },

        /**
         * Update dashboard statistics
         */
        updateDashboardStats: function(data) {
            if (data.stats) {
                $('#total-posts').text(data.stats.total_posts);
                $('#scheduled-posts').text(data.stats.scheduled_posts);
                $('#api-calls').text(data.stats.api_calls);
            }
        },

        /**
         * Approve content for publishing
         */
        approveContent: function(e) {
            e.preventDefault();

            var $button = $(this);
            var contentId = $button.data('id');

            if (!confirm('Are you sure you want to approve and publish this content?')) {
                return;
            }

            $button.prop('disabled', true).text('Publishing...');

            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'autoblog_approve_content',
                    nonce: autoblog_admin.nonce,
                    content_id: contentId
                },
                success: function(response) {
                    if (response.success) {
                        $button.closest('.autoblog-review-item').fadeOut();
                        alert('Content published successfully!');
                    } else {
                        alert('Failed to publish content: ' + response.data);
                    }
                },
                error: function() {
                    alert('Failed to publish content');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Approve & Publish');
                }
            });
        },

        /**
         * Reject content
         */
        rejectContent: function(e) {
            e.preventDefault();

            var $button = $(this);
            var contentId = $button.data('id');

            if (!confirm('Are you sure you want to reject this content?')) {
                return;
            }

            $button.prop('disabled', true).text('Rejecting...');

            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'autoblog_reject_content',
                    nonce: autoblog_admin.nonce,
                    content_id: contentId
                },
                success: function(response) {
                    if (response.success) {
                        $button.closest('.autoblog-review-item').fadeOut();
                        alert('Content rejected');
                    } else {
                        alert('Failed to reject content: ' + response.data);
                    }
                },
                error: function() {
                    alert('Failed to reject content');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Reject');
                }
            });
        },

        /**
         * Edit content
         */
        editContent: function(e) {
            e.preventDefault();

            var $button = $(this);
            var contentId = $button.data('id');

            // For now, just redirect to WordPress post editor
            // In a future version, we could implement inline editing
            alert('Content editing will be available in a future version. For now, you can approve the content and edit it in the WordPress post editor.');
        },

        /**
         * Start real-time updates
         */
        startRealTimeUpdates: function() {
            // Update dashboard every 30 seconds
            setInterval(function() {
                AutoBlogAdmin.loadDashboardData();
            }, 30000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        AutoBlogAdmin.init();
    });

})(jQuery);