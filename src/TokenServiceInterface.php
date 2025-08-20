<?php

declare(strict_types=1);

namespace Praetorian\TokenService;

/**
 * Creats and consumes tokens.
 */
interface TokenServiceInterface
{
    const CACHE_KEY = 'TKN_';
    const CACHE_TAG = 'TKN';

    /**
     * Creates a token.
     *
     * @param string
     * @param mixed|null
     *
     * @throws InvalidArgumentException
     */
    public function createToken(string $type, $payload = null, ?int $ttl = null): TokenInterface;

    /**
     * Consumes a token and returns is or returns null if token does not exist.
     *
     * @param int TYPE_USER_*
     *
     * @return Token|null
     */
    public function consumeToken(string $uid, bool $keepToken = false): ?TokenInterface;

    /**
     * Clears all tokens from the cache.
     *
     * @return bool True if tokens were successfully cleared, false otherwise
     */
    public function clearAllTokens(): bool;
}
