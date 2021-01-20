<?php

namespace Praetorian\TokenService;

use Praetorian\Prometheus\CacheService\CacheServiceInterface;

/**
 * Creats and consumes tokens.
 */
final class TokenService implements TokenServiceInterface
{
    private CacheServiceInterface $cache;

    /**
     * Creates service instance.
     *
     * @param CacheServiceInterface
     * @return TokenService
     */
    public function __construct(CacheServiceInterface $cache)
    {
        $this->cache = $cache;
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
    public function consumeToken(string $uid): ?TokenInterface
    {
        $key = $this->buildKey($uid);
        $cache = $this->getCache();
        $token = $cache->get($key);

        if (!$token || !$token instanceof TokenInterface) {
            return null;
        }

        $cache->delete($key);

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
    private function buildKey(string $uid)
    {
        return static::CACHE_KEY . $uid;
    }
}
