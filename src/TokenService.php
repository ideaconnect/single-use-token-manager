<?php

declare(strict_types=1);

namespace GryfOSS\SingleUseTokenManager;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;

/**
 * Single-use token management service.
 *
 * This service provides functionality to create, consume, and manage single-use tokens
 * using PSR-6 compatible cache adapters. Tokens are automatically assigned unique
 * identifiers and can be consumed only once (unless explicitly kept in cache).
 *
 * The service supports both tag-aware and regular cache adapters:
 * - Tag-aware adapters (like Redis with tag support) allow efficient bulk operations
 * - Regular adapters fall back to full cache clearing for bulk operations
 *
 * @author GryfOSS Team
 * @since 1.0.0
 */
final class TokenService implements TokenServiceInterface
{
    /**
     * Initializes the token service with a cache adapter.
     *
     * The cache adapter should implement PSR-6 CacheItemPoolInterface.
     * For optimal performance and bulk operations support, consider using
     * a TagAwareAdapterInterface implementation.
     *
     * @param CacheItemPoolInterface $cache The cache adapter to use for token storage
     */
    public function __construct(
        protected CacheItemPoolInterface $cache
        ) {
    }

    /**
     * Creates a new single-use token with the specified type and payload.
     *
     * Generates a unique identifier for the token and stores it in the cache.
     * The token can optionally have a time-to-live (TTL) value for automatic expiration.
     *
     * If the cache adapter supports tags (TagAwareAdapterInterface), the token
     * will be tagged for efficient bulk operations.
     *
     * @param string $type The token type identifier (e.g., 'password_reset', 'email_verification')
     * @param mixed $payload Optional data to associate with the token
     * @param int|null $ttl Optional time-to-live in seconds. If null, token never expires
     *
     * @return TokenInterface The created token with unique identifier
     *
     * @throws \Psr\Cache\InvalidArgumentException If the cache key is invalid
     */
    public function createToken(string $type, $payload = null, ?int $ttl = null): TokenInterface
    {
        $token = new Token($type, $payload);
        $cache = $this->getCache();
        $item = $cache->getItem($this->buildKey($token->getUid()));
        $item->set($token);

        if ($ttl !== null) {
            $item->expiresAfter($ttl);
        }

        // Add tags if the cache adapter supports it
        if ($cache instanceof TagAwareAdapterInterface && method_exists($item, 'tag')) {
            $item->tag([static::CACHE_TAG]);
        }

        $cache->save($item);

        return $token;
    }

    /**
     * Consumes a token by its unique identifier.
     *
     * Retrieves and optionally removes a token from the cache. By default,
     * tokens are single-use and will be deleted after consumption. Set
     * $keepToken to true to keep the token in cache for multiple uses.
     *
     * @param string $uid The unique identifier of the token to consume
     * @param bool $keepToken Whether to keep the token in cache after consumption (default: false)
     *
     * @return TokenInterface|null The consumed token, or null if not found or expired
     *
     * @throws \Psr\Cache\InvalidArgumentException If the cache key is invalid
     */
    public function consumeToken(string $uid, bool $keepToken = false): ?TokenInterface
    {
        $key = $this->buildKey($uid);
        $cache = $this->getCache();
        $item = $cache->getItem($key);

        if (!$item->isHit()) {
            return null;
        }

        $token = $item->get();

        if (!$token instanceof TokenInterface) {
            return null;
        }

        if (!$keepToken) {
            $cache->deleteItem($key);
        }

        return $token;
    }

    /**
     * Clears all tokens from the cache.
     *
     * The behavior depends on the cache adapter type:
     * - Tag-aware adapters: Only tokens are cleared using tag invalidation
     * - Regular adapters: The entire cache is cleared (affects all cached items!)
     *
     * WARNING: When using non-tag-aware adapters, this method will clear
     * ALL items in the cache pool, not just tokens. Use with caution in
     * shared cache environments.
     *
     * @return bool True if the operation was successful, false otherwise
     *
     * @throws \Psr\Cache\InvalidArgumentException If tag invalidation fails
     */
    public function clearAllTokens(): bool
    {
        $cache = $this->getCache();

        // If the cache adapter supports tags, use tag-based invalidation
        if ($cache instanceof TagAwareAdapterInterface) {
            return $cache->invalidateTags([static::CACHE_TAG]);
        }

        // Otherwise, clear the entire cache pool
        // WARNING: This will clear ALL cache items, not just tokens!
        return $cache->clear();
    }

    /**
     * Returns the configured cache adapter.
     *
     * Provides access to the underlying cache implementation for
     * internal operations. This method is protected to maintain
     * encapsulation while allowing extension by subclasses.
     *
     * @return CacheItemPoolInterface The cache adapter instance
     */
    protected function getCache(): CacheItemPoolInterface
    {
        return $this->cache;
    }

    /**
     * Builds a cache key for the given token UID.
     *
     * Combines the configured cache key prefix with the token's unique
     * identifier to create a cache key that's unlikely to collide with
     * other cached items.
     *
     * @param string $uid The token's unique identifier
     *
     * @return string The complete cache key for the token
     */
    protected function buildKey(string $uid): string
    {
        return static::CACHE_KEY.$uid;
    }
}
