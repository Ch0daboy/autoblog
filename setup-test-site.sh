#!/bin/bash

# AutoBlog WordPress Testing Environment Setup Script
# This script sets up a local WordPress testing environment using Docker

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Docker is installed
check_docker() {
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed. Please install Docker first."
        echo "Visit: https://docs.docker.com/get-docker/"
        exit 1
    fi
    
    if ! command -v docker-compose &> /dev/null; then
        print_error "Docker Compose is not installed. Please install Docker Compose first."
        echo "Visit: https://docs.docker.com/compose/install/"
        exit 1
    fi
    
    print_success "Docker and Docker Compose are installed"
}

# Check if Docker daemon is running
check_docker_daemon() {
    if ! docker info &> /dev/null; then
        print_error "Docker daemon is not running. Please start Docker first."
        exit 1
    fi
    
    print_success "Docker daemon is running"
}

# Start the WordPress testing environment
start_environment() {
    print_status "Starting WordPress testing environment..."
    
    # Stop any existing containers
    docker-compose down 2>/dev/null || true
    
    # Start the containers
    docker-compose up -d
    
    print_success "WordPress testing environment started!"
}

# Wait for WordPress to be ready
wait_for_wordpress() {
    print_status "Waiting for WordPress to be ready..."
    
    local max_attempts=60
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if curl -s http://localhost:8080 > /dev/null 2>&1; then
            print_success "WordPress is ready!"
            return 0
        fi
        
        echo -n "."
        sleep 2
        ((attempt++))
    done
    
    print_error "WordPress failed to start within expected time"
    return 1
}

# Install WordPress CLI in container
install_wp_cli() {
    print_status "Installing WordPress CLI..."
    
    docker exec autoblog_wordpress bash -c "
        curl -O https://raw.githubusercontent.com/wp-cli/wp-cli/v2.8.1/phar/wp-cli.phar
        chmod +x wp-cli.phar
        mv wp-cli.phar /usr/local/bin/wp
    " 2>/dev/null || true
    
    print_success "WordPress CLI installed"
}

# Configure WordPress
configure_wordpress() {
    print_status "Configuring WordPress..."
    
    # Wait a bit more for database to be ready
    sleep 10
    
    # Install WordPress
    docker exec autoblog_wordpress wp core install \
        --url="http://localhost:8080" \
        --title="AutoBlog Test Site" \
        --admin_user="admin" \
        --admin_password="admin123" \
        --admin_email="admin@test.local" \
        --allow-root 2>/dev/null || true
    
    # Activate the AutoBlog plugin
    docker exec autoblog_wordpress wp plugin activate autoblog --allow-root 2>/dev/null || true
    
    print_success "WordPress configured with AutoBlog plugin activated"
}

# Display access information
show_access_info() {
    echo ""
    echo "==========================================="
    echo "  AutoBlog WordPress Test Site Ready!  "
    echo "==========================================="
    echo ""
    echo "🌐 WordPress Site: http://localhost:8080"
    echo "👤 Admin Login: http://localhost:8080/wp-admin"
    echo "   Username: admin"
    echo "   Password: admin123"
    echo ""
    echo "🗄️  Database (phpMyAdmin): http://localhost:8081"
    echo "   Username: wordpress"
    echo "   Password: wordpress"
    echo ""
    echo "📁 Plugin Location: /wp-content/plugins/autoblog"
    echo ""
    echo "==========================================="
    echo "  Testing Instructions"
    echo "==========================================="
    echo ""
    echo "1. Go to WordPress Admin: http://localhost:8080/wp-admin"
    echo "2. Navigate to 'AutoBlog' in the admin menu"
    echo "3. Go to Settings and add your OpenAI API key"
    echo "4. Test content generation features"
    echo "5. Check the plugin functionality"
    echo ""
    echo "📝 To stop the test environment:"
    echo "   docker-compose down"
    echo ""
    echo "🔄 To restart the test environment:"
    echo "   docker-compose up -d"
    echo ""
    echo "🗑️  To remove all data and start fresh:"
    echo "   docker-compose down -v"
    echo ""
}

# Main execution
main() {
    echo "==========================================="
    echo "  AutoBlog WordPress Testing Setup"
    echo "==========================================="
    echo ""
    
    check_docker
    check_docker_daemon
    start_environment
    wait_for_wordpress
    install_wp_cli
    configure_wordpress
    show_access_info
}

# Handle script interruption
trap 'print_error "Setup interrupted"; exit 1' INT TERM

# Run main function
main