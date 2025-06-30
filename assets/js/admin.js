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
         * Generate content schedule
         */
        generateSchedule: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $status = $('#schedule-status');
            
            var data = {
                action: 'autoblog_generate_schedule',
                nonce: autoblog_admin.nonce,
                days: $('#schedule_days').val(),
                posts_per_day: $('#posts_per_day').val(),
                content_types: $('#content_types').val()
            };
            
            $button.prop('disabled', true).text('Generating...');
            $status.text('Generating content schedule...');
            
            $.ajax({
                url: autoblog_admin.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $status.text('✓ Schedule generated successfully!');
                        AutoBlogAdmin.refreshScheduleTable();
                    } else {
                        $status.text('✗ ' + response.data);
                    }
                },
                error: function() {
                    $status.text('✗ Failed to generate schedule');
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
                return{"toolcall":{"thought":"Now I'll create the admin JavaScript file to handle AJAX interactions and dynamic functionality in the admin interface.","name":"write_to_file","params":{"rewrite":false,"file_path":"/home/noob/autoblog/assets/js/admin.js","content":