<?php

namespace Praetorian\TokenService;

/**
 * Creats and consumes tokens.
 */
interface TokenServiceInterface
{
    const CACHE_KEY = 'TOKEN:';
    const CACHE_TAG = 'TOKEN';

    /**
     * Creates a token.
     *
     * @param string
     * @param mixed|null
     * @param int|null $ttl
     * @throws InvalidArgumentException
     * @return TokenInterface
     */
    public function createToken(string $type, $payload = null, ?int $ttl = null): TokenInterface;

    /**
     * Consumes a token and returns is or returns null if token does not exist.
     *
     * @param int TYPE_USER_*
     * @param string $uid
     * @return Token|null
     */
    public function consumeToken(string $uid, bool $keepToken = false) : ?TokenInterface;
}
