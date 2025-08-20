praetoriantechnology/token-service
==================================

Main token service: creates and consumes token using Symfony Cache component.

## Installation

```bash
composer require praetoriantechnology/token-service
```

## Usage

The token service now uses Symfony's PSR-6 Cache component, which provides great flexibility in choosing your cache backend.

### Basic Usage

```php
use Praetorian\TokenService\TokenService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

// Create a cache adapter (filesystem in this example)
$cache = new FilesystemAdapter();

// Create the token service
$tokenService = new TokenService($cache);

// Create a token
$token = $tokenService->createToken('login', ['user_id' => 123], 3600); // 1 hour TTL

// Get token details
echo $token->getUid();     // Returns UUID string
echo $token->getType();    // Returns 'login'
var_dump($token->getPayload()); // Returns ['user_id' => 123]

// Consume the token (removes it from cache)
$consumedToken = $tokenService->consumeToken($token->getUid());

// Or consume but keep it in cache
$consumedToken = $tokenService->consumeToken($token->getUid(), true);

// Clear all tokens from the cache
$cleared = $tokenService->clearAllTokens(); // Returns true if successful
```

### Clear All Tokens

The `clearAllTokens()` method provides a convenient way to remove all tokens from the cache. The implementation automatically detects the cache adapter capabilities:

- **Tag-Aware Adapters**: Uses tag-based invalidation to clear only token-related cache items, preserving other cached data
- **Regular Adapters**: Clears the entire cache pool

```php
// Using a tag-aware adapter (recommended for shared cache pools)
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$filesystemAdapter = new FilesystemAdapter();
$tagAwareCache = new TagAwareAdapter($filesystemAdapter);
$tokenService = new TokenService($tagAwareCache);

// Add some tokens
$token1 = $tokenService->createToken('login', ['user_id' => 123]);
$token2 = $tokenService->createToken('reset', 'password-data');

// Add non-token data to the same cache
$nonTokenItem = $tagAwareCache->getItem('user_preferences');
$nonTokenItem->set(['theme' => 'dark']);
$tagAwareCache->save($nonTokenItem);

// Clear only tokens (non-token data is preserved)
$tokenService->clearAllTokens();
```

**Important Notes:**
- When using tag-aware adapters, only token-related cache items are cleared
- When using regular adapters, the **entire cache pool is cleared**
- For shared cache pools, always use tag-aware adapters to avoid clearing unrelated data

### Cache Adapters

You can use any PSR-6 compatible cache adapter:

```php
// Filesystem cache (good for production)
$cache = new \Symfony\Component\Cache\Adapter\FilesystemAdapter();

// Redis cache (good for distributed systems)
$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);
$cache = new \Symfony\Component\Cache\Adapter\RedisAdapter($redis);

// Array cache (good for testing)
$cache = new \Symfony\Component\Cache\Adapter\ArrayAdapter();

// APCu cache (good for single-server setups)
$cache = new \Symfony\Component\Cache\Adapter\ApcuAdapter();
```

### Migration from previous version

If you're migrating from a previous version that used `praetoriantechnology/cache-service`, you need to:

1. Update your composer.json to require symfony/cache instead of praetoriantechnology/cache-service
2. Update your dependency injection to inject a PSR-6 CacheItemPoolInterface instead of CacheServiceInterface
3. The public API (`createToken`, `consumeToken`, `clearAllTokens` methods) remains the same

## Requirements

- PHP 8.1 or higher
- symfony/cache ^6 || ^7
- symfony/uid ^6 || ^7

## Testing

```bash
# Install dependencies
composer install

# Run unit tests
composer test:unit

# Run functional tests (requires Redis)
composer test:functional

# Run all tests
composer test
```