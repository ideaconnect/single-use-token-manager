#!/bin/bash

# Token Service Test Runner
# This script manages Docker containers and runs Behat tests for different cache adapters

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
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

# Function to check if Docker is running
check_docker() {
    if ! docker info > /dev/null 2>&1; then
        echo_error "Docker is not running. Please start Docker and try again."
        exit 1
    fi
}

# Function to start Docker services
start_services() {
    echo_info "Starting Docker services..."
    docker compose up -d

    echo_info "Waiting for Redis services to be ready..."
    timeout 30 bash -c 'until docker compose exec redis redis-cli ping; do sleep 1; done'
    timeout 30 bash -c 'until docker compose exec redis-no-tags redis-cli ping; do sleep 1; done'

    echo_success "All services are ready!"
}

# Function to stop Docker services
stop_services() {
    echo_info "Stopping Docker services..."
    docker compose down
    echo_success "Services stopped!"
}

# Function to run tests
run_tests() {
    local suite=${1:-"all"}

    echo_info "Running tests for suite: $suite"

    case $suite in
        "array")
            echo_info "Running ArrayAdapter tests (no Docker required)..."
            ./vendor/bin/behat --suite=array_adapter
            ;;
        "redis_tags")
            echo_info "Running Redis with tags tests..."
            ./vendor/bin/behat --suite=redis_tags
            ;;
        "redis_no_tags")
            echo_info "Running Redis without tags tests..."
            ./vendor/bin/behat --suite=redis_no_tags
            ;;
        "all")
            echo_info "Running all test suites..."
            echo_info "1. ArrayAdapter tests (no Docker required)..."
            ./vendor/bin/behat --suite=array_adapter
            echo_success "ArrayAdapter tests completed!"

            echo_info "2. Redis with tags tests..."
            ./vendor/bin/behat --suite=redis_tags
            echo_success "Redis with tags tests completed!"

            echo_info "3. Redis without tags tests..."
            ./vendor/bin/behat --suite=redis_no_tags
            echo_success "Redis without tags tests completed!"
            ;;
        *)
            echo_error "Unknown test suite: $suite"
            echo_info "Available suites: array, redis_tags, redis_no_tags, all"
            exit 1
            ;;
    esac
}

# Function to run PHPUnit tests
run_unit_tests() {
    echo_info "Running PHPUnit tests..."
    ./vendor/bin/phpunit
    echo_success "PHPUnit tests completed!"
}

# Function to show help
show_help() {
    echo "Token Service Test Runner"
    echo ""
    echo "Usage: $0 [COMMAND] [OPTIONS]"
    echo ""
    echo "Commands:"
    echo "  start                Start Docker services"
    echo "  stop                 Stop Docker services"
    echo "  test [SUITE]         Run Behat tests"
    echo "  unit                 Run PHPUnit tests"
    echo "  full                 Run all tests (unit + functional)"
    echo "  clean                Stop services and clean up"
    echo "  help                 Show this help message"
    echo ""
    echo "Test Suites:"
    echo "  array                ArrayAdapter tests (offline)"
    echo "  redis_tags           Redis with tag support tests (online)"
    echo "  redis_no_tags        Redis without tag support tests (online)"
    echo "  all                  All functional test suites"
    echo ""
    echo "Examples:"
    echo "  $0 start             # Start services"
    echo "  $0 test array        # Run ArrayAdapter tests only"
    echo "  $0 test all          # Run all functional tests"
    echo "  $0 full              # Run unit tests + all functional tests"
    echo "  $0 clean             # Clean up everything"
}

# Main script logic
case "${1:-help}" in
    "start")
        check_docker
        start_services
        ;;
    "stop")
        check_docker
        stop_services
        ;;
    "test")
        if [ "${2:-all}" = "array" ]; then
            # ArrayAdapter tests don't need Docker
            run_tests "${2:-all}"
        else
            check_docker
            start_services
            run_tests "${2:-all}"
        fi
        ;;
    "unit")
        run_unit_tests
        ;;
    "full")
        check_docker
        start_services
        run_unit_tests
        run_tests "all"
        echo_success "All tests completed successfully!"
        ;;
    "clean")
        check_docker
        stop_services
        docker compose down --volumes --remove-orphans
        echo_success "Cleanup completed!"
        ;;
    "help"|*)
        show_help
        ;;
esac
