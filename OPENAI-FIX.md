# OpenAI API Integration Fix

This document outlines the fixes applied to resolve OpenAI API integration issues in the AutoBlog plugin.

## Issues Fixed

### 1. Deprecated Model Names
**Problem**: The plugin was using deprecated model names:
- `gpt-4` (may not be available or have changed pricing)
- `gpt-3.5-turbo` (deprecated in favor of newer models)

**Solution**: Updated to use current OpenAI models:
- `gpt-4o-mini` - More cost-effective and reliable for content generation
- Added proper model fallback handling

### 2. API Request Improvements
**Problem**: Basic API request handling without proper error logging and debugging.

**Solution**: Enhanced the `make_request` method with:
- Better error handling and logging
- Increased timeout from 60s to 120s
- Added User-Agent header for better API tracking
- Improved SSL verification
- Detailed error logging for debugging

### 3. Database Table Mismatch
**Problem**: The `log_api_usage` method was referencing `autoblog_api_logs` table, but the activator creates `autoblog_api_usage`.

**Solution**: 
- Fixed table name reference
- Updated logging to match the correct database schema
- Added token counting and cost estimation

### 4. DALL-E API Updates
**Problem**: Missing response format specification for image generation.

**Solution**: Added `response_format: 'url'` parameter to DALL-E requests for better compatibility.

## Files Modified

1. **`includes/class-autoblog-openai.php`**
   - Updated model names to `gpt-4o-mini`
   - Enhanced error handling and logging
   - Fixed database table references
   - Improved API request reliability

## Testing the Fix

### Method 1: Use the Test Script
```bash
# From the plugin directory
php test-openai-api.php
```

### Method 2: WordPress Admin
1. Go to AutoBlog settings in WordPress admin
2. Enter your OpenAI API key
3. Click "Test Connection" button
4. Try generating a test post

### Method 3: Check Error Logs
If issues persist, check your WordPress error logs for detailed API responses:
```bash
tail -f /path/to/wordpress/wp-content/debug.log
```

## API Key Requirements

Ensure your OpenAI API key has access to:
- Chat Completions API (for content generation)
- DALL-E 3 API (for image generation, optional)
- Sufficient credits/quota

## Common Issues and Solutions

### Issue: "Model not found" error
**Solution**: The API key might not have access to GPT-4 models. The plugin now defaults to `gpt-4o-mini` which should be available to most accounts.

### Issue: Rate limiting errors
**Solution**: The plugin now includes better error handling for rate limits. Consider:
- Reducing posting frequency
- Upgrading your OpenAI plan
- Adding delays between requests

### Issue: JSON parsing errors
**Solution**: Enhanced error logging now captures the raw API response for debugging. Check the error logs for the actual response content.

## Model Pricing (as of 2024)

- **gpt-4o-mini**: $0.15/1M input tokens, $0.60/1M output tokens
- **gpt-4o**: $5.00/1M input tokens, $15.00/1M output tokens
- **DALL-E 3**: $0.040 per image (1024×1024)

## Configuration Recommendations

1. **For cost-effective content generation**: Use `gpt-4o-mini` (default)
2. **For higher quality content**: Manually change to `gpt-4o` in the code
3. **For image generation**: Ensure DALL-E 3 access in your OpenAI account
4. **For high-volume sites**: Consider implementing request queuing and rate limiting

## Monitoring API Usage

The plugin now logs detailed API usage to the `autoblog_api_usage` table, including:
- Endpoint called
- Tokens used
- Estimated cost
- Response time
- Error messages

You can query this data to monitor your API usage and costs.

## Support

If you continue to experience issues:
1. Check the test script output
2. Review WordPress error logs
3. Verify your OpenAI API key permissions
4. Ensure your OpenAI account has sufficient credits