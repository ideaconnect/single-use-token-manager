<?php

declare(strict_types=1);

namespace Praetorian\TokenService;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Creats and consumes tokens.
 */
final class TokenService implements TokenServiceInterface
{
    /**
     * Creates service instance.
     *
     * @param CacheItemPoolInterface
     *
     * @return TokenService
     */
    public function __construct(
        protected CacheItemPoolInterface $cache
        ) {
    }

    /**
     * {@inheritdoc}
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

        $cache->save($item);

        return $token;
    }

    /**
     * {@inheritdoc}
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
     * Returns the cache.
     */
    protected function getCache(): CacheItemPoolInterface
    {
        return $this->cache;
    }

    /**
     * Builds the token key.
     *
     * @param string
     */
    protected function buildKey(string $uid): string
    {
        return static::CACHE_KEY.$uid;
    }
}
