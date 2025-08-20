# Single Use Token Manager

A comprehensive single-use token management library using Symfony Cache with support for multiple cache adapters.

## Features

- **Token Creation & Consumption**: Create unique single-use tokens with optional TTL and consume them
- **Multiple Cache Adapters**: Support for ArrayAdapter, Redis with tags, and Redis without tags
- **Tag-Aware Clearing**: Efficient token clearing with tag-aware adapters
- **Full Test Coverage**: 100% unit test coverage with comprehensive functional tests
- **Validation & Serialization**: Built-in validation and JSON serialization support
- **Docker Integration**: Easy testing with Docker-based Redis instances

## Installation

```bash
composer require gryfoss/single-use-token-manager
```

## Requirements

- PHP 8.1 or higher
- Redis extension (for Redis-based tests)
- Docker and Docker Compose (for functional tests)

## Testing

This project includes comprehensive testing with three different cache adapter scenarios:

### 1. ArrayAdapter (Offline Storage)
- In-memory storage, no persistence
- Ideal for development and unit testing
- No external dependencies

### 2. Redis with Tags (Online Storage + Tag Support)
- Persistent Redis storage with tag support
- Efficient token clearing using cache tags
- Requires Redis server

### 3. Redis without Tags (Online Storage, No Tag Support)
- Persistent Redis storage without tag support
- Full cache clearing for token management
- Requires Redis server

### GitHub Actions CI/CD

The project includes automated testing via GitHub Actions that verifies:

✅ **Unit Tests**: All 32 unit tests pass
✅ **100% Code Coverage**: Automatically verified (build fails if not 100%)
✅ **Functional Tests**: All cache adapters tested (ArrayAdapter, Redis+Tags, Redis-NoTags)

**Workflows:**
- `.github/workflows/ci.yml` - Main CI pipeline (focuses on the 3 core requirements)
- `.github/workflows/test.yml` - Comprehensive testing with matrix
- `.github/workflows/simple-test.yml` - Detailed multi-job pipeline

### Local Coverage Verification

```bash
# Verify 100% coverage locally (same check as GitHub Actions)
./verify-coverage.sh
```


### Manual Testing

```bash
# Start Docker services
./test-runner.sh start

# Run specific test suites
./test-runner.sh test array           # ArrayAdapter tests
./test-runner.sh test redis_tags      # Redis with tags tests
./test-runner.sh test redis_no_tags   # Redis without tags tests
./test-runner.sh test all             # All functional tests

# Run unit tests
./test-runner.sh unit

# Run everything
./test-runner.sh full

# Clean up
./test-runner.sh clean
```

### Composer Scripts

```bash
composer test:unit                    # Run unit tests with coverage
composer test:functional-array        # Run ArrayAdapter tests
composer test:functional-redis-tags   # Run Redis with tags tests
composer test:functional-redis-no-tags # Run Redis without tags tests
composer test:functional             # Run all functional tests
composer test:full                   # Run all tests
composer docker:start               # Start Docker services
composer docker:stop                # Stop Docker services
composer docker:clean               # Clean up Docker
```

## Architecture

### Core Classes

- **TokenService**: Main service implementing TokenServiceInterface
- **Token**: Token entity with UUID, type, payload, and TTL
- **TokenIdentifier**: DTO for token validation and serialization
- **TokenInterface**: Contract for token objects

### Cache Strategy

The service automatically detects cache adapter capabilities:

- **Tag-Aware Adapters**: Use cache tags for efficient selective clearing
- **Non-Tag-Aware Adapters**: Use full cache pool clearing
- **ArrayAdapter**: In-memory storage for development/testing

### Validation & Serialization

- **Symfony Validator**: Attribute-based validation (NotBlank, NotNull, Type)
- **Symfony Serializer**: JSON serialization with SerializedName attributes
- **OpenAPI Integration**: API documentation attributes

## Usage

```php
use GryfOSS\SingleUseTokenManager\TokenService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

// Create service with ArrayAdapter
$cache = new ArrayAdapter();
$tokenService = new TokenService($cache);

// Create a token
$token = $tokenService->createToken('user_session', ['user_id' => 123], 3600);

// Consume the token
$consumedToken = $tokenService->consumeToken($token->getUid());

// Clear all tokens
$tokenService->clearAllTokens();
```

### Advanced Usage

```php
use GryfOSS\SingleUseTokenManager\TokenService;
use GryfOSS\SingleUseTokenManager\TokenIdentifier;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Validator\Validation;

// Redis with tag support
$redis = new \Redis();
$redis->connect('localhost', 6379);
$cache = new RedisTagAwareAdapter($redis);
$tokenService = new TokenService($cache);

// Create multiple tokens
$loginToken = $tokenService->createToken('login', ['user_id' => 123], 3600);
$resetToken = $tokenService->createToken('reset', 'password-data', 1800);

// Validate token identifier
$tokenIdentifier = new TokenIdentifier();
$tokenIdentifier->token = $loginToken->getUid();

$validator = Validation::createValidatorBuilder()
    ->enableAttributeMapping()
    ->getValidator();

$violations = $validator->validate($tokenIdentifier);
if (count($violations) === 0) {
    echo "Token is valid!";
}

// Clear all tokens efficiently (uses tags)
$tokenService->clearAllTokens();
```

## Test Coverage

- **Unit Tests**: 32 tests, 75 assertions, 100% code coverage
- **Functional Tests**: Multiple scenarios across 3 cache adapters
- **Integration Tests**: Docker-based Redis testing
- **Validation Tests**: Comprehensive constraint testing

## Development

### Docker Services

The project includes Docker Compose configuration for:

- **redis**: Redis instance on port 6379 (with tag support)
- **redis-no-tags**: Redis instance on port 6380 (without tag support)

### Test Structure

```
tests/
├── Behat/
│   ├── ArrayAdapterTokenServiceContext.php
│   ├── RedisTagsTokenServiceContext.php
│   ├── RedisNoTagsTokenServiceContext.php
│   ├── TokenContext.php
│   └── TokenServiceContext.php (legacy)
├── TokenServiceTest.php
├── TokenTest.php
└── TokenIdentifierTest.php

features/
├── token.feature
├── tokenService.feature (legacy)
├── tokenService-array.feature
├── tokenService-redis-tags.feature
└── tokenService-redis-no-tags.feature
```

## License

MIT License
