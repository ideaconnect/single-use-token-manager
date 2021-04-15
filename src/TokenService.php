<?php

namespace Praetorian\TokenService;

use Praetorian\CacheService\CacheServiceInterface;

/**
 * Creats and consumes tokens.
 */
final class TokenService implements TokenServiceInterface
{
    /**
     * Creates service instance.
     *
     * @param CacheServiceInterface
     * @return TokenService
     */
    public function __construct(
        protected CacheServiceInterface $cache
        ) {
    }

    /**
     * {@inheritdoc}
     */
    public function createToken(string $type, $payload = null, ?int $ttl = null): TokenInterface
    {
        $token = new Token($type, $payload);
        $this->getCache()->set($this->buildKey($token->getUid()), $token, static::CACHE_TAG, $ttl);

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    public function consumeToken(string $uid, bool $keepToken = false): ?TokenInterface
    {
        $key = $this->buildKey($uid);
        $cache = $this->getCache();
        $token = $cache->get($key);

        if (!$token || !$token instanceof TokenInterface) {
            return null;
        }

        if (!$keepToken) {
            $cache->delete($key);
        }

        return $token;
    }

    /**
     * Returns the cache.
     *
     * @return CacheServiceInterface
     */
    protected function getCache(): CacheServiceInterface
    {
        return $this->cache;
    }

    /**
     * Builds the token key.
     *
     * @param string
     * @return string
     */
    private function buildKey(string $uid): string
    {
        return static::CACHE_KEY . $uid;
    }
}
