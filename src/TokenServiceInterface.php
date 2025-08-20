<?php

declare(strict_types=1);

namespace GryfOSS\SingleUseTokenManager;

/**
 * Interface for single-use token management services.
 *
 * Defines the contract for creating, consuming, and managing single-use tokens.
 * Implementations should provide secure token generation, storage, and retrieval
 * mechanisms using appropriate cache adapters.
 *
 * @author GryfOSS Team
 * @since 1.0.0
 */
interface TokenServiceInterface
{
    /** @var string Cache key prefix for token storage */
    const CACHE_KEY = 'TKN_';

    /** @var string Cache tag for token grouping (used with tag-aware adapters) */
    const CACHE_TAG = 'TKN';

    /**
     * Creates a new single-use token.
     *
     * Generates a token with a unique identifier, stores it in the cache,
     * and returns the token object. The token can optionally include a
     * time-to-live value for automatic expiration.
     *
     * @param string $type The token type identifier for categorization
     * @param mixed|null $payload Optional data to associate with the token
     * @param int|null $ttl Optional time-to-live in seconds
     *
     * @return TokenInterface The created token with unique identifier
     *
     * @throws \InvalidArgumentException If the token type is invalid
     * @throws \Psr\Cache\InvalidArgumentException If cache operations fail
     */
    public function createToken(string $type, $payload = null, ?int $ttl = null): TokenInterface;

    /**
     * Consumes a token by its unique identifier.
     *
     * Retrieves a token from the cache and optionally removes it. Tokens are
     * single-use by default but can be kept in cache for multiple consumptions
     * if explicitly requested.
     *
     * @param string $uid The unique identifier of the token to consume
     * @param bool $keepToken Whether to keep the token in cache after consumption
     *
     * @return TokenInterface|null The consumed token, or null if not found/expired
     *
     * @throws \Psr\Cache\InvalidArgumentException If cache operations fail
     */
    public function consumeToken(string $uid, bool $keepToken = false): ?TokenInterface;

    /**
     * Clears all tokens from the cache.
     *
     * Removes all tokens managed by this service. The implementation may vary
     * depending on the cache adapter capabilities (tag-aware vs. regular adapters).
     *
     * @return bool True if tokens were successfully cleared, false otherwise
     *
     * @throws \Psr\Cache\InvalidArgumentException If cache operations fail
     */
    public function clearAllTokens(): bool;
}
