#!/bin/bash

# AutoBlog Plugin Validation Script
# This script validates the plugin structure and files without requiring PHP

echo "==========================================="
echo "  AutoBlog Plugin Validation"
echo "==========================================="
echo

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
PASSED=0
FAILED=0
WARNINGS=0

# Function to print status
print_status() {
    local status=$1
    local message=$2
    
    case $status in
        "PASS")
            echo -e "${GREEN}✅ PASS${NC}: $message"
            ((PASSED++))
            ;;
        "FAIL")
            echo -e "${RED}❌ FAIL${NC}: $message"
            ((FAILED++))
            ;;
        "WARN")
            echo -e "${YELLOW}⚠️  WARN${NC}: $message"
            ((WARNINGS++))
            ;;
        "INFO")
            echo -e "${BLUE}ℹ️  INFO${NC}: $message"
            ;;
    esac
}

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

print_status "INFO" "Validating plugin in: $SCRIPT_DIR"
echo

# Test 1: Check main plugin file
echo "=== Testing Main Plugin File ==="
if [ -f "autoblog.php" ]; then
    print_status "PASS" "Main plugin file exists: autoblog.php"
    
    # Check for required PHP opening tag
    if head -1 "autoblog.php" | grep -q "<?php"; then
        print_status "PASS" "PHP opening tag found"
    else
        print_status "FAIL" "PHP opening tag missing or not on first line"
    fi
    
    # Check for plugin header
    if grep -q "Plugin Name:" "autoblog.php"; then
        print_status "PASS" "Plugin header found"
    else
        print_status "FAIL" "Plugin header missing"
    fi
    
    # Check for security check
    if grep -q "defined.*ABSPATH" "autoblog.php"; then
        print_status "PASS" "Security check (ABSPATH) found"
    else
        print_status "WARN" "Security check (ABSPATH) not found"
    fi
else
    print_status "FAIL" "Main plugin file missing: autoblog.php"
fi
echo

# Test 2: Check uninstall file
echo "=== Testing Uninstall File ==="
if [ -f "uninstall.php" ]; then
    print_status "PASS" "Uninstall file exists: uninstall.php"
    
    if grep -q "WP_UNINSTALL_PLUGIN" "uninstall.php"; then
        print_status "PASS" "Uninstall security check found"
    else
        print_status "WARN" "Uninstall security check not found"
    fi
else
    print_status "WARN" "Uninstall file missing: uninstall.php"
fi
echo

# Test 3: Check includes directory and class files
echo "=== Testing Class Files ==="
if [ -d "includes" ]; then
    print_status "PASS" "Includes directory exists"
    
    # List of required class files
    declare -a class_files=(
        "class-autoblog.php"
        "class-autoblog-admin.php"
        "class-autoblog-openai.php"
        "class-autoblog-scheduler.php"
        "class-autoblog-affiliate.php"
        "class-autoblog-comments.php"
        "class-autoblog-analytics.php"
        "class-autoblog-activator.php"
        "class-autoblog-deactivator.php"
    )
    
    for file in "${class_files[@]}"; do
        if [ -f "includes/$file" ]; then
            print_status "PASS" "Class file exists: $file"
            
            # Check for PHP opening tag
            if head -1 "includes/$file" | grep -q "<?php"; then
                print_status "PASS" "$file has PHP opening tag"
            else
                print_status "FAIL" "$file missing PHP opening tag"
            fi
            
            # Check for class definition
            class_name=$(echo "$file" | sed 's/class-//g' | sed 's/.php//g' | sed 's/-/_/g')
            class_name="$(echo "$class_name" | sed 's/\b\w/\U&/g')" # Capitalize
            
            if grep -q "class $class_name" "includes/$file"; then
                print_status "PASS" "$file contains class definition"
            else
                print_status "WARN" "$file may be missing class definition"
            fi
        else
            print_status "FAIL" "Class file missing: $file"
        fi
    done
else
    print_status "FAIL" "Includes directory missing"
fi
echo

# Test 4: Check assets directory
echo "=== Testing Asset Files ==="
if [ -d "assets" ]; then
    print_status "PASS" "Assets directory exists"
    
    # Check CSS directory
    if [ -d "assets/css" ]; then
        print_status "PASS" "CSS directory exists"
        
        if [ -f "assets/css/admin.css" ]; then
            print_status "PASS" "Admin CSS file exists"
            
            # Check if CSS file has content
            if [ -s "assets/css/admin.css" ]; then
                print_status "PASS" "Admin CSS file has content"
            else
                print_status "WARN" "Admin CSS file is empty"
            fi
        else
            print_status "FAIL" "Admin CSS file missing"
        fi
    else
        print_status "FAIL" "CSS directory missing"
    fi
    
    # Check JS directory
    if [ -d "assets/js" ]; then
        print_status "PASS" "JS directory exists"
        
        if [ -f "assets/js/admin.js" ]; then
            print_status "PASS" "Admin JS file exists"
            
            # Check if JS file has content
            if [ -s "assets/js/admin.js" ]; then
                print_status "PASS" "Admin JS file has content"
            else
                print_status "WARN" "Admin JS file is empty"
            fi
        else
            print_status "FAIL" "Admin JS file missing"
        fi
    else
        print_status "FAIL" "JS directory missing"
    fi
else
    print_status "FAIL" "Assets directory missing"
fi
echo

# Test 5: Check documentation files
echo "=== Testing Documentation Files ==="
declare -a doc_files=(
    "README.md"
    "INSTALL.md"
    "TESTING.md"
    "plugin-info.json"
)

for file in "${doc_files[@]}"; do
    if [ -f "$file" ]; then
        print_status "PASS" "Documentation file exists: $file"
        
        if [ -s "$file" ]; then
            print_status "PASS" "$file has content"
        else
            print_status "WARN" "$file is empty"
        fi
    else
        print_status "WARN" "Documentation file missing: $file"
    fi
done
echo

# Test 6: Check test environment files
echo "=== Testing Test Environment ==="
if [ -f "docker-compose.yml" ]; then
    print_status "PASS" "Docker Compose file exists"
else
    print_status "WARN" "Docker Compose file missing"
fi

if [ -f "setup-test-site.sh" ]; then
    print_status "PASS" "Test setup script exists"
    
    if [ -x "setup-test-site.sh" ]; then
        print_status "PASS" "Test setup script is executable"
    else
        print_status "WARN" "Test setup script is not executable"
    fi
else
    print_status "WARN" "Test setup script missing"
fi

if [ -f "start-test-server.sh" ]; then
    print_status "PASS" "Test server script exists"
    
    if [ -x "start-test-server.sh" ]; then
        print_status "PASS" "Test server script is executable"
    else
        print_status "WARN" "Test server script is not executable"
    fi
else
    print_status "WARN" "Test server script missing"
fi

if [ -d "test-environment" ]; then
    print_status "PASS" "Test environment directory exists"
    
    if [ -f "test-environment/index.php" ]; then
        print_status "PASS" "Test environment index file exists"
    else
        print_status "WARN" "Test environment index file missing"
    fi
else
    print_status "WARN" "Test environment directory missing"
fi
echo

# Test 7: File permissions check
echo "=== Testing File Permissions ==="

# Check if main files are readable
for file in "autoblog.php" "uninstall.php"; do
    if [ -f "$file" ]; then
        if [ -r "$file" ]; then
            print_status "PASS" "$file is readable"
        else
            print_status "FAIL" "$file is not readable"
        fi
    fi
done

# Check if directories are accessible
for dir in "includes" "assets" "assets/css" "assets/js"; do
    if [ -d "$dir" ]; then
        if [ -r "$dir" ] && [ -x "$dir" ]; then
            print_status "PASS" "$dir is accessible"
        else
            print_status "FAIL" "$dir is not accessible"
        fi
    fi
done
echo

# Test 8: Basic syntax check (if available)
echo "=== Basic Syntax Validation ==="
if command -v php &> /dev/null; then
    print_status "INFO" "PHP is available, performing syntax checks"
    
    # Check main plugin file
    if [ -f "autoblog.php" ]; then
        if php -l "autoblog.php" &> /dev/null; then
            print_status "PASS" "autoblog.php syntax is valid"
        else
            print_status "FAIL" "autoblog.php has syntax errors"
        fi
    fi
    
    # Check class files
    if [ -d "includes" ]; then
        for file in includes/*.php; do
            if [ -f "$file" ]; then
                if php -l "$file" &> /dev/null; then
                    print_status "PASS" "$(basename "$file") syntax is valid"
                else
                    print_status "FAIL" "$(basename "$file") has syntax errors"
                fi
            fi
        done
    fi
else
    print_status "INFO" "PHP not available, skipping syntax checks"
fi
echo

# Summary
echo "==========================================="
echo "  Validation Summary"
echo "==========================================="
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${RED}Failed: $FAILED${NC}"
echo -e "${YELLOW}Warnings: $WARNINGS${NC}"
echo

if [ $FAILED -eq 0 ]; then
    if [ $WARNINGS -eq 0 ]; then
        echo -e "${GREEN}🎉 All tests passed! Plugin structure is valid.${NC}"
        exit 0
    else
        echo -e "${YELLOW}⚠️  Tests passed with warnings. Review warnings above.${NC}"
        exit 0
    fi
else
    echo -e "${RED}❌ Some tests failed. Please fix the issues above.${NC}"
    exit 1
fi