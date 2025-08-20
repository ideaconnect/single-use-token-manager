<?php

declare(strict_types=1);

namespace GryfOSS\SingleUseTokenManager;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;

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

        // Add tags if the cache adapter supports it
        if ($cache instanceof TagAwareAdapterInterface && method_exists($item, 'tag')) {
            $item->tag([static::CACHE_TAG]);
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
     * {@inheritdoc}
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
