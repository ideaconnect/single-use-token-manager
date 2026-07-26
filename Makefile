.PHONY: help install start stop clean test analyse unit bdd bdd-memory mutation lint fix example

help: ## Show this help
	@echo "Single Use Token Manager"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

install: ## Install the dependencies
	composer install

start: ## Start the Redis and Valkey containers
	composer cache:start

stop: ## Stop the containers
	composer cache:stop

clean: ## Stop the containers and drop their volumes
	composer cache:clean

analyse: ## Run PHPStan at level max
	composer analyse

unit: ## Run the unit tests with coverage
	composer test:unit

bdd-memory: ## Run the functional tests that need no container
	composer test:bdd:memory

bdd: ## Run every functional test, starting the containers first
	composer test:bdd

mutation: ## Run mutation testing
	composer test:mutation

lint: ## Report coding standard violations
	composer lint

fix: ## Apply the coding standard
	composer fix

test: ## Run unit, functional and mutation tests
	composer test

example: ## Run the example script
	php example.php
