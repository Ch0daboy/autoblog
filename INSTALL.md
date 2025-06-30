# AutoBlog Installation Guide

This guide will help you install and configure the AutoBlog WordPress plugin.

## Prerequisites

Before installing AutoBlog, ensure your system meets these requirements:

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **cURL**: PHP extension (usually enabled by default)
- **JSON**: PHP extension (usually enabled by default)
- **OpenAI API Key**: Required for content generation

## Installation Methods

### Method 1: WordPress Admin Dashboard (Recommended)

1. **Download the Plugin**
   - Download the `autoblog.zip` file
   - Or clone this repository and create a ZIP file

2. **Upload via WordPress Admin**
   - Log in to your WordPress admin dashboard
   - Navigate to **Plugins** → **Add New**
   - Click **Upload Plugin**
   - Choose the `autoblog.zip` file
   - Click **Install Now**

3. **Activate the Plugin**
   - After installation, click **Activate Plugin**
   - You'll see "AutoBlog" in your admin menu

### Method 2: Manual FTP Upload

1. **Extract Files**
   ```bash
   unzip autoblog.zip
   ```

2. **Upload via FTP**
   - Upload the `autoblog` folder to `/wp-content/plugins/`
   - Ensure proper file permissions (644 for files, 755 for directories)

3. **Activate via Admin**
   - Go to **Plugins** in WordPress admin
   - Find "AutoBlog" and click **Activate**

### Method 3: WP-CLI Installation

```bash
# Navigate to WordPress root directory
cd /path/to/wordpress

# Install the plugin
wp plugin install autoblog.zip --activate

# Verify installation
wp plugin list | grep autoblog
```

## Initial Configuration

### Step 1: Get OpenAI API Key

1. Visit [OpenAI Platform](https://platform.openai.com/api-keys)
2. Sign up or log in to your account
3. Create a new API key
4. Copy the key (starts with `sk-`)

### Step 2: Configure Plugin Settings

1. **Access Settings**
   - Go to **AutoBlog** → **Settings** in WordPress admin

2. **Basic Configuration**
   ```
   OpenAI API Key: sk-your-api-key-here
   Blog Description: Brief description of your blog's niche
   Auto-Publish: ☐ Enable automatic publishing
   ```

3. **Test API Connection**
   - Click **Test Connection** button
   - Verify you see "✅ Connection successful"

4. **Save Settings**
   - Click **Save Changes**

### Step 3: Configure Content Types

1. **Select Content Types**
   - ☑ Blog Posts
   - ☑ Product Reviews
   - ☑ How-to Guides
   - ☑ Listicles
   - ☐ News Articles

2. **Set Posting Schedule**
   ```
   Posts per Day: 1
   Posting Time: 09:00 AM
   Days Ahead: 7
   ```

### Step 4: Optional Configurations

#### Amazon Affiliate Integration
```
Amazon Affiliate ID: your-affiliate-id
Auto-add Links: ☐ Enable
Disclosure Text: "This post contains affiliate links..."
```

#### Comment Management
```
AI Comment Replies: ☐ Enable
Reply Delay: 30 minutes
Require Approval: ☑ Yes
```

## Verification

### Check Installation

1. **Database Tables**
   - Verify these tables exist in your database:
     - `wp_autoblog_content_schedule`
     - `wp_autoblog_comment_queue`
     - `wp_autoblog_analytics`
     - `wp_autoblog_api_usage`
     - `wp_autoblog_daily_summary`

2. **Cron Jobs**
   ```bash
   wp cron event list | grep autoblog
   ```
   Should show:
   - `autoblog_generate_content`
   - `autoblog_process_comment_queue`
   - `autoblog_cleanup_old_data`
   - `autoblog_daily_analytics`

3. **File Permissions**
   ```bash
   ls -la wp-content/plugins/autoblog/
   ```
   Ensure proper permissions are set

### Test Content Generation

1. **Manual Test**
   - Go to **AutoBlog** → **Generate Content**
   - Select "Blog Post" as content type
   - Enter a topic (e.g., "WordPress tips")
   - Click **Generate Post**
   - Verify content is generated successfully

2. **Schedule Test**
   - Go to **AutoBlog** → **Content Schedule**
   - Click **Generate Schedule**
   - Set 1 day, 1 post per day
   - Verify schedule is created

## Troubleshooting

### Common Issues

#### API Connection Failed
```
Error: OpenAI API connection failed

Solutions:
1. Verify API key is correct
2. Check internet connectivity
3. Ensure cURL is enabled
4. Check OpenAI service status
```

#### Database Table Creation Failed
```
Error: Could not create database tables

Solutions:
1. Check database permissions
2. Verify MySQL version compatibility
3. Check available disk space
4. Review error logs
```

#### Cron Jobs Not Running
```
Error: Scheduled content not generating

Solutions:
1. Check WordPress cron system
2. Verify server cron configuration
3. Test with WP-CLI: wp cron test
4. Check for plugin conflicts
```

### Debug Mode

Enable debug mode for detailed logging:

```php
// Add to wp-config.php
define('AUTOBLOG_DEBUG', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Logs will be saved to:
- `/wp-content/uploads/autoblog-logs/`
- `/wp-content/debug.log`

### System Requirements Check

```bash
# Check PHP version
php -v

# Check required extensions
php -m | grep -E "curl|json|mysqli"

# Check WordPress version
wp core version

# Check available memory
php -r "echo ini_get('memory_limit');"
```

## Security Considerations

### API Key Security

1. **Never commit API keys to version control**
2. **Use environment variables when possible**
3. **Regularly rotate API keys**
4. **Monitor API usage for unusual activity**

### File Permissions

```bash
# Set proper permissions
find wp-content/plugins/autoblog/ -type f -exec chmod 644 {} \;
find wp-content/plugins/autoblog/ -type d -exec chmod 755 {} \;
```

### Database Security

1. **Use strong database passwords**
2. **Limit database user permissions**
3. **Regular database backups**
4. **Monitor for suspicious queries**

## Performance Optimization

### Caching

```php
// Enable object caching
define('WP_CACHE', true);

// Use Redis or Memcached if available
```

### Database Optimization

```sql
-- Add indexes for better performance
ALTER TABLE wp_autoblog_content_schedule ADD INDEX idx_status_date (status, scheduled_date);
ALTER TABLE wp_autoblog_analytics ADD INDEX idx_event_date (event_type, created_at);
```

### Server Configuration

```apache
# .htaccess optimizations
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
</IfModule>
```

## Backup and Maintenance

### Regular Backups

1. **Database Backup**
   ```bash
   wp db export autoblog-backup-$(date +%Y%m%d).sql
   ```

2. **Plugin Files Backup**
   ```bash
   tar -czf autoblog-files-$(date +%Y%m%d).tar.gz wp-content/plugins/autoblog/
   ```

3. **Settings Export**
   ```bash
   wp option get autoblog_settings > autoblog-settings-$(date +%Y%m%d).json
   ```

### Maintenance Tasks

1. **Weekly Tasks**
   - Review generated content quality
   - Check API usage and costs
   - Monitor error logs

2. **Monthly Tasks**
   - Update plugin if new version available
   - Clean up old analytics data
   - Review and optimize content schedule

3. **Quarterly Tasks**
   - Full system backup
   - Security audit
   - Performance optimization review

## Getting Help

### Documentation
- [Full Documentation](README.md)
- [API Reference](https://autoblog-plugin.com/api)
- [Video Tutorials](https://autoblog-plugin.com/tutorials)

### Support Channels
- [GitHub Issues](https://github.com/your-username/autoblog/issues)
- [WordPress Support Forum](https://wordpress.org/support/plugin/autoblog)
- [Discord Community](https://discord.gg/autoblog)

### Professional Support
- Email: support@autoblog-plugin.com
- [Premium Support](https://autoblog-plugin.com/support)

---

**Installation Complete!** 🎉

Your AutoBlog plugin is now ready to start generating amazing content for your WordPress site.