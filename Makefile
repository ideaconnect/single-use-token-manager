.PHONY: help install start stop test test-array test-redis-tags test-redis-no-tags test-unit test-full clean

# Default target
help: ## Show this help message
	@echo "Single Use Token Manager Test Management"
	@echo ""
	@echo "Available targets:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

install: ## Install dependencies
	composer install

start: ## Start Docker services
	./test-runner.sh start

stop: ## Stop Docker services
	./test-runner.sh stop

test-array: ## Run ArrayAdapter tests
	./test-runner.sh test array

test-redis-tags: ## Run Redis with tags tests
	./test-runner.sh test redis_tags

test-redis-no-tags: ## Run Redis without tags tests
	./test-runner.sh test redis_no_tags

test-functional: ## Run all functional tests
	./test-runner.sh test all

test-unit: ## Run PHPUnit tests
	./test-runner.sh unit

test-full: ## Run all tests (unit + functional)
	./test-runner.sh full

clean: ## Stop services and clean up
	./test-runner.sh clean

# Development shortcuts
dev-setup: install start ## Setup development environment
	@echo "Development environment ready!"

dev-test: test-unit test-array ## Quick development test (unit + array)
	@echo "Quick tests completed!"
