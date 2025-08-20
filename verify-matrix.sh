#!/bin/bash

# Matrix Testing Verification Script
# This script simulates what the GitHub Actions matrix will test

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

echo_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

echo_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

echo_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

echo_info "🧪 Matrix Testing Verification"
echo_info "This script simulates GitHub Actions matrix testing"
echo ""

# Get current PHP version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo_info "Current PHP version: $PHP_VERSION"

# Check if current PHP version is in our matrix
if [[ "$PHP_VERSION" == "8.2" || "$PHP_VERSION" == "8.3" || "$PHP_VERSION" == "8.4" ]]; then
    echo_success "✅ PHP $PHP_VERSION is supported in matrix"
else
    echo_warning "⚠️ PHP $PHP_VERSION is not in the test matrix (8.2, 8.3, 8.4)"
fi

echo ""
echo_info "Running simulated matrix tests..."

# Step 1: Unit Tests
echo_info "🧪 Step 1: Running unit tests (simulating PHP $PHP_VERSION)..."
if ./vendor/bin/phpunit; then
    echo_success "✅ Unit tests passed on PHP $PHP_VERSION"
else
    echo_error "❌ Unit tests failed on PHP $PHP_VERSION"
    exit 1
fi

# Step 2: Coverage (only for PHP 8.3 simulation)
if [[ "$PHP_VERSION" == "8.3" ]]; then
    echo ""
    echo_info "📊 Step 2: Running coverage verification (PHP 8.3 only)..."

    if XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-clover=coverage.xml --coverage-text; then
        # Extract coverage percentage
        COVERAGE=$(php -r "
        if (file_exists('coverage.xml')) {
            \$xml = simplexml_load_file('coverage.xml');
            \$metrics = \$xml->project->metrics;
            \$covered = (int)\$metrics['coveredstatements'];
            \$total = (int)\$metrics['statements'];
            \$percentage = (\$total > 0) ? round((\$covered / \$total) * 100, 2) : 0;
            echo \$percentage;
        } else {
            echo '0';
        }
        ")

        echo_info "Code coverage: ${COVERAGE}%"

        if [[ "$COVERAGE" == "100" ]]; then
            echo_success "✅ 100% code coverage verified"
        else
            echo_error "❌ Coverage is not 100%: ${COVERAGE}%"
            exit 1
        fi
    else
        echo_error "❌ Coverage verification failed"
        exit 1
    fi
else
    echo_info "📊 Step 2: Skipping coverage (only runs on PHP 8.3 in matrix)"
fi

# Step 3: Functional Tests
echo ""
echo_info "🧪 Step 3: Running functional tests (simulating PHP $PHP_VERSION)..."

# Test ArrayAdapter (always works)
echo_info "Testing ArrayAdapter..."
if ./vendor/bin/behat --suite=array_adapter; then
    echo_success "✅ ArrayAdapter tests passed"
else
    echo_error "❌ ArrayAdapter tests failed"
    exit 1
fi

echo ""
echo_success "🎉 Matrix simulation completed successfully for PHP $PHP_VERSION!"
echo ""
echo_info "📋 Matrix Test Summary:"
echo_info "✅ Unit tests: PASSED"
if [[ "$PHP_VERSION" == "8.3" ]]; then
    echo_info "✅ Coverage: VERIFIED (100%)"
else
    echo_info "⏭️ Coverage: SKIPPED (only on PHP 8.3)"
fi
echo_info "✅ Functional tests: PASSED"
echo ""
echo_info "🚀 Your code is ready for GitHub Actions matrix testing!"
