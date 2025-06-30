# AutoBlog Plugin Testing Guide

This guide provides multiple methods to test the AutoBlog WordPress plugin, from simple code validation to full WordPress integration testing.

## Testing Methods Overview

### 1. Code Validation Testing (No Dependencies)
- Syntax checking
- Class structure validation
- Method existence verification

### 2. Local PHP Testing (Requires PHP)
- Mock WordPress environment
- Basic functionality testing
- API integration testing

### 3. WordPress Integration Testing (Full Environment)
- Complete WordPress installation
- Plugin activation testing
- End-to-end functionality testing

## Method 1: Code Validation Testing

### Prerequisites
- None (files are already created)

### What This Tests
- PHP syntax validation
- Class and method structure
- File organization
- Basic code quality

### Files to Review

#### Core Plugin Files
- `autoblog.php` - Main plugin file
- `uninstall.php` - Uninstallation handler

#### Class Files (in `includes/` directory)
- `class-autoblog.php` - Core plugin class
- `class-autoblog-admin.php` - Admin interface
- `class-autoblog-openai.php` - OpenAI API integration
- `class-autoblog-scheduler.php` - Content scheduling
- `class-autoblog-affiliate.php` - Affiliate management
- `class-autoblog-comments.php` - AI comment replies
- `class-autoblog-analytics.php` - Analytics and reporting
- `class-autoblog-activator.php` - Plugin activation
- `class-autoblog-deactivator.php` - Plugin deactivation

#### Asset Files
- `assets/css/admin.css` - Admin styling
- `assets/js/admin.js` - Admin JavaScript

#### Documentation
- `README.md` - Plugin documentation
- `INSTALL.md` - Installation guide
- `plugin-info.json` - Plugin metadata

### Manual Code Review Checklist

#### ✅ File Structure
- [ ] All required files are present
- [ ] Files are in correct directories
- [ ] Naming conventions are consistent

#### ✅ PHP Syntax
- [ ] All PHP files have proper opening tags
- [ ] No syntax errors in any file
- [ ] Proper class declarations
- [ ] Method signatures are correct

#### ✅ WordPress Integration
- [ ] Proper use of WordPress hooks
- [ ] Security measures implemented
- [ ] Database operations use $wpdb
- [ ] Proper sanitization and validation

#### ✅ Plugin Structure
- [ ] Main plugin file follows WordPress standards
- [ ] Activation/deactivation hooks are registered
- [ ] Uninstall process is defined
- [ ] Plugin metadata is complete

## Method 2: Local PHP Testing

### Prerequisites
```bash
# Install PHP (Ubuntu/Debian)
sudo apt update
sudo apt install php php-cli php-curl php-json php-mbstring

# Install PHP (CentOS/RHEL)
sudo yum install php php-cli php-curl php-json php-mbstring

# Install PHP (macOS with Homebrew)
brew install php

# Verify installation
php --version
```

### Running the Test Server

1. **Start the test server:**
   ```bash
   cd /path/to/autoblog
   ./start-test-server.sh
   ```

2. **Access the test environment:**
   - Open your browser
   - Navigate to `http://localhost:8000`
   - Review the test results

### What the PHP Test Environment Checks

- ✅ Plugin file loading
- ✅ Class instantiation
- ✅ Method existence
- ✅ Basic functionality
- ✅ Mock WordPress environment

### Expected Test Results

When the test server runs successfully, you should see:

- **Plugin Status**: ✅ Plugin files loaded successfully
- **Class Testing**: All 9 core classes should exist and instantiate
- **Method Testing**: Key OpenAI methods should be available
- **Plugin Information**: Version and path details

## Method 3: WordPress Integration Testing

### Option A: Local WordPress Installation

#### Prerequisites
- Web server (Apache/Nginx)
- PHP 7.4 or higher
- MySQL/MariaDB
- WordPress 5.0 or higher

#### Installation Steps

1. **Download WordPress:**
   ```bash
   wget https://wordpress.org/latest.tar.gz
   tar -xzf latest.tar.gz
   ```

2. **Set up database:**
   ```sql
   CREATE DATABASE wordpress_test;
   CREATE USER 'wp_user'@'localhost' IDENTIFIED BY 'password';
   GRANT ALL PRIVILEGES ON wordpress_test.* TO 'wp_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. **Install WordPress:**
   - Copy WordPress files to web directory
   - Run WordPress installation
   - Complete setup wizard

4. **Install AutoBlog plugin:**
   ```bash
   cp -r autoblog /path/to/wordpress/wp-content/plugins/
   ```

5. **Activate plugin:**
   - Login to WordPress admin
   - Go to Plugins → Installed Plugins
   - Activate "AutoBlog"

### Option B: Docker WordPress (if Docker is available)

#### Prerequisites
- Docker and Docker Compose

#### Quick Setup
```bash
# If Docker is running, use the provided docker-compose.yml
docker-compose up -d

# Wait for containers to start
sleep 30

# Access WordPress at http://localhost:8080
# Access phpMyAdmin at http://localhost:8081
```

### Option C: Online Testing Environments

#### Local by Flywheel
- Download from getflywheel.com
- Create new WordPress site
- Upload AutoBlog plugin
- Test functionality

#### XAMPP/WAMP/MAMP
- Install local server stack
- Set up WordPress
- Install and test plugin

#### WordPress.com Developer Account
- Create developer sandbox
- Upload plugin for testing
- Test in controlled environment

## Testing Checklist

### Basic Functionality
- [ ] Plugin activates without errors
- [ ] Admin menu appears
- [ ] Settings page loads
- [ ] Database tables are created

### OpenAI Integration
- [ ] API key can be saved
- [ ] Connection test works
- [ ] Content generation functions
- [ ] Error handling works

### Content Management
- [ ] Scheduled content creation
- [ ] Post publishing works
- [ ] Content types are supported
- [ ] SEO optimization functions

### Comment Management
- [ ] AI comment replies work
- [ ] Comment moderation functions
- [ ] Reply notifications work
- [ ] Comment analytics track

### Affiliate Features
- [ ] Amazon affiliate links work
- [ ] Product recommendations generate
- [ ] Affiliate tracking functions
- [ ] Commission calculations work

### Analytics
- [ ] Data collection works
- [ ] Reports generate correctly
- [ ] Charts display properly
- [ ] Export functions work

### Performance
- [ ] Page load times acceptable
- [ ] Database queries optimized
- [ ] Cron jobs execute properly
- [ ] Memory usage reasonable

### Security
- [ ] Input sanitization works
- [ ] API keys are protected
- [ ] User permissions respected
- [ ] SQL injection prevention

## Troubleshooting Common Issues

### Plugin Won't Activate
- Check PHP version compatibility
- Verify file permissions
- Review error logs
- Check for conflicting plugins

### OpenAI API Issues
- Verify API key is correct
- Check API quota and billing
- Test network connectivity
- Review API response logs

### Database Errors
- Check database permissions
- Verify table creation
- Review MySQL error logs
- Test database connectivity

### Cron Job Issues
- Verify WordPress cron is working
- Check server cron configuration
- Test manual cron execution
- Review cron job logs

## Getting Help

If you encounter issues during testing:

1. **Check the logs:**
   - WordPress debug log
   - Server error logs
   - Plugin-specific logs

2. **Review documentation:**
   - README.md
   - INSTALL.md
   - WordPress Codex

3. **Test in isolation:**
   - Disable other plugins
   - Use default theme
   - Test with fresh WordPress

4. **Community resources:**
   - WordPress support forums
   - Plugin development documentation
   - OpenAI API documentation

## Test Environment Files

The following files are included for testing:

- `docker-compose.yml` - Docker WordPress environment
- `setup-test-site.sh` - Automated setup script
- `start-test-server.sh` - PHP development server
- `test-environment/index.php` - Mock WordPress environment
- `TESTING.md` - This testing guide

Choose the testing method that best fits your environment and requirements.