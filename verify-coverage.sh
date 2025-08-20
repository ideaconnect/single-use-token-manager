#!/bin/bash

# Local Coverage Verification Script
# This script mimics the GitHub Actions coverage check

set -e

echo "🔍 Local Coverage Verification"
echo "=============================="

# Check if required tools are available
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed"
    exit 1
fi

if [ ! -f "vendor/bin/phpunit" ]; then
    echo "❌ PHPUnit not found. Run: composer install"
    exit 1
fi

echo "📊 Running tests with coverage analysis..."
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-clover=coverage.xml --coverage-text

if [ ! -f "coverage.xml" ]; then
    echo "❌ Coverage file not generated"
    exit 1
fi

echo ""
echo "📈 Extracting coverage percentage..."

# Extract coverage percentage from clover XML
COVERAGE=$(php -r "
\$xml = simplexml_load_file('coverage.xml');
\$metrics = \$xml->project->metrics;
\$covered = (int)\$metrics['coveredstatements'];
\$total = (int)\$metrics['statements'];
\$percentage = (\$total > 0) ? round((\$covered / \$total) * 100, 2) : 0;
echo \$percentage;
")

echo "Current coverage: ${COVERAGE}%"
echo "Required coverage: 100%"
echo ""

if [ "$COVERAGE" != "100" ]; then
    echo "❌ COVERAGE CHECK FAILED!"
    echo "Coverage is ${COVERAGE}%, but 100% is required."
    echo ""
    echo "GitHub Actions will fail with this coverage level."
    echo "Please add tests to achieve 100% coverage."
    exit 1
else
    echo "✅ COVERAGE CHECK PASSED!"
    echo "Coverage is exactly 100% - GitHub Actions will pass."
fi

echo ""
echo "📋 Coverage verification complete."
