# Perplexity AI Integration for AutoBlog

## Overview

AutoBlog now integrates with Perplexity AI to provide research-backed content generation. This integration enables the creation of highly accurate, well-sourced content using real-time web data and comprehensive research capabilities.

## Features

### 🔍 Research Capabilities
- **Real-time web search**: Access current information and recent developments
- **Source verification**: Multiple sources gathered and verified for accuracy
- **Citation management**: Automatic source citation and reference integration
- **Research depth control**: Choose between light, medium, or deep research
- **Follow-up questions**: AI generates additional research questions for comprehensive coverage

### 📝 Content Types
- **Research-backed articles**: Comprehensive articles with verified information
- **News summaries**: Current events with proper source attribution
- **Trend analysis**: Data-driven analysis of industry trends
- **How-to guides**: Step-by-step guides with expert insights
- **Product reviews**: Reviews based on current market data and user feedback

### 🎯 Research Workflow
1. **Topic Research**: Perplexity conducts initial research on the specified topic
2. **Source Gathering**: Multiple authoritative sources are identified and analyzed
3. **Follow-up Research**: Additional questions are generated and researched for depth
4. **Data Compilation**: All research data is compiled and organized
5. **Content Generation**: OpenAI creates content based on research findings
6. **Source Integration**: Citations and references are automatically added

## Setup and Configuration

### 1. API Key Configuration

1. Navigate to **AutoBlog > Settings** in your WordPress admin
2. Locate the **Perplexity API Key** field
3. Enter your Perplexity API key (get one from [Perplexity AI](https://www.perplexity.ai/))
4. Click **Test Connection** to verify the API key
5. Enable **Research-backed content generation** checkbox
6. Select your preferred **Research Depth**:
   - **Light**: Quick overview with basic sources
   - **Medium**: Balanced research with multiple sources (recommended)
   - **Deep**: Comprehensive analysis with extensive research

### 2. Research Settings

- **Research Enabled**: Toggle research-backed content generation
- **Research Depth**: Control the thoroughness of research
- **Search Recency**: Automatically adjusted based on content type
- **Domain Filtering**: Optional filtering by specific domains (advanced)

## Usage

### Using the Research & Generate Page

1. Go to **AutoBlog > Research & Generate**
2. **Step 1: Research Topic**
   - Enter your topic or keyword
   - Select research depth
   - Click **Start Research**
3. **Step 2: Review Research Results**
   - Review the research summary
   - Check sources and citations
   - Verify key points identified
4. **Step 3: Generate Content**
   - Choose content type (article, news, how-to, etc.)
   - Click **Generate Content**
5. **Step 4: Review and Publish**
   - Review the generated content
   - Check integrated sources
   - Publish or save as draft

### Programmatic Usage

```php
// Initialize Perplexity class
$perplexity = new AutoBlog_Perplexity();

// Conduct research
$research_result = $perplexity->research("artificial intelligence trends", array(
    'max_tokens' => 2000,
    'search_recency_filter' => 'month'
));

// Generate research-backed content
$content_data = $perplexity->generate_research_content(
    "AI trends in 2024",
    "article",
    "medium"
);

// Use with OpenAI for content generation
$openai = new AutoBlog_OpenAI();
$post_result = $openai->generate_research_backed_post($content_data);
```

## API Reference

### AutoBlog_Perplexity Class

#### Methods

##### `test_connection($api_key = null)`
Tests the Perplexity API connection.

**Parameters:**
- `$api_key` (string, optional): API key to test

**Returns:** Boolean indicating success/failure

##### `research($query, $options = array())`
Conducts research using Perplexity API.

**Parameters:**
- `$query` (string): Research query
- `$options` (array): Research options
  - `max_tokens` (int): Maximum response tokens
  - `temperature` (float): Response creativity (0.0-1.0)
  - `search_recency_filter` (string): 'day', 'week', 'month', 'year'
  - `search_domain_filter` (array): Specific domains to search

**Returns:** Array with research content and citations

##### `generate_research_content($topic, $content_type, $research_depth)`
Generates comprehensive research data for content creation.

**Parameters:**
- `$topic` (string): Content topic
- `$content_type` (string): Type of content (article, news, how-to, etc.)
- `$research_depth` (string): Research depth (light, medium, deep)

**Returns:** Array with compiled research data, sources, and key points

### AutoBlog_OpenAI Class (Enhanced)

#### New Methods

##### `generate_research_backed_post($research_data)`
Generates a blog post using research data from Perplexity.

**Parameters:**
- `$research_data` (array): Research data from Perplexity

**Returns:** Array with post data including content, sources, and metadata

## Content Quality Features

### Source Verification
- Multiple sources are cross-referenced for accuracy
- Domain authority is considered in source selection
- Recent sources are prioritized for current topics

### Citation Integration
- Sources are automatically formatted and integrated
- Citations include title, URL, and domain information
- Reference sections are automatically generated

### Fact Checking
- Information is verified across multiple sources
- Conflicting information is noted and addressed
- Expert opinions and authoritative sources are prioritized

## Best Practices

### Research Topics
- Use specific, focused topics for better research results
- Include relevant keywords and context
- Consider your target audience when framing research queries

### Content Types
- **News articles**: Use recent recency filter (day/week)
- **How-to guides**: Use broader recency filter (month/year)
- **Trend analysis**: Use medium recency filter (week/month)
- **Product reviews**: Use recent filter for current market data

### Research Depth
- **Light**: Use for quick content or familiar topics
- **Medium**: Best for most content types (recommended)
- **Deep**: Use for comprehensive guides or complex topics

## Troubleshooting

### Common Issues

#### API Connection Failed
- Verify your Perplexity API key is correct
- Check your server's internet connectivity
- Ensure cURL is enabled on your server

#### Research Results Empty
- Try rephrasing your research query
- Check if the topic is too specific or too broad
- Verify your research depth setting

#### Content Generation Failed
- Ensure both Perplexity and OpenAI API keys are configured
- Check API usage limits and quotas
- Verify the research data is properly formatted

### Error Codes
- `no_api_key`: Perplexity API key not configured
- `api_error`: API request failed
- `json_error`: Invalid response format
- `empty_response`: No research data returned

## Performance Considerations

### API Usage
- Research requests consume Perplexity API credits
- Deep research uses more tokens than light research
- Consider API rate limits when generating multiple posts

### Caching
- Research results are logged for analytics
- Consider implementing caching for frequently researched topics
- Monitor API usage through the analytics dashboard

## Security

### API Key Storage
- API keys are stored securely in WordPress options
- Keys are never exposed in client-side code
- Use environment variables for additional security

### Content Validation
- All research content is sanitized before storage
- Sources are validated for proper URL format
- User input is properly escaped and validated

## Support and Updates

For support with the Perplexity integration:
1. Check the troubleshooting section above
2. Review the AutoBlog analytics for API usage patterns
3. Consult the Perplexity AI documentation for API-specific issues
4. Submit issues through the AutoBlog GitHub repository

## Changelog

### Version 1.1.0
- Added Perplexity AI integration
- Implemented research-backed content generation
- Added source citation and reference management
- Enhanced content quality with fact-checking
- Added research depth control options
- Integrated real-time web data access
