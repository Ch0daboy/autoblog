#!/bin/bash

# AutoBlog Plugin Test Server
# This script starts a simple PHP development server for testing the plugin

echo "==========================================="
echo "  AutoBlog Plugin Test Server"
echo "==========================================="
echo

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "[ERROR] PHP is not installed. Please install PHP to run the test server."
    exit 1
fi

echo "[SUCCESS] PHP is installed"
echo "PHP Version: $(php -v | head -n 1)"
echo

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEST_DIR="$SCRIPT_DIR/test-environment"

# Check if test environment exists
if [ ! -d "$TEST_DIR" ]; then
    echo "[ERROR] Test environment directory not found: $TEST_DIR"
    exit 1
fi

echo "[INFO] Test environment found: $TEST_DIR"
echo

# Start PHP development server
echo "[INFO] Starting PHP development server..."
echo "[INFO] Server will be available at: http://localhost:8000"
echo "[INFO] Press Ctrl+C to stop the server"
echo
echo "==========================================="
echo "  Server Starting..."
echo "==========================================="
echo

# Change to test directory and start server
cd "$TEST_DIR"
php -S localhost:8000

echo
echo "==========================================="
echo "  Server Stopped"
echo "==========================================="