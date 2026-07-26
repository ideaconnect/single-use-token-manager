<?php

declare(strict_types=1);

namespace IDCT\SingleUseTokenManager;

use IDCT\SingleUseTokenManager\Contract\TaggedCacheInterface;
use IDCT\SingleUseTokenManager\Contract\TokenInterface;
use IDCT\SingleUseTokenManager\Contract\TokenServiceInterface;
use IDCT\SingleUseTokenManager\Exception\TokenStorageException;
use IDCT\SingleUseTokenManager\Model\Token;
use Psr\SimpleCache\CacheInterface;

/**
 * Single-use token manager backed by a PSR-16 cache.
 *
 * Tokens are stored as cache entries keyed by {@see TokenServiceInterface::CACHE_KEY}
 * plus the token identifier. Expiry is delegated to the cache, so a token given
 * a time-to-live disappears on its own without any sweeping job.
 *
 * Any PSR-16 implementation will do. When the injected cache also supports
 * tagging, the service stores every token under
 * {@see TokenServiceInterface::CACHE_TAG} and clears tokens by that tag, which
 * leaves the rest of the pool alone. `idct/php-rapid-cache-client` is such a
 * cache; see {@see supportsTagging()} for how the capability is detected.
 *
 * @author IDCT
 *
 * @since 1.0.0
 */
class TokenService implements TokenServiceInterface
{
    /**
     * Initialises the service with the cache that will hold the tokens.
     *
     * No connection is opened here. Whether the cache connects lazily or eagerly
     * is entirely up to the implementation being injected.
     *
     * @param CacheInterface $cache PSR-16 cache used for token storage; give it
     *                              a dedicated pool if you intend to call
     *                              {@see clearAllTokens()} on a cache without
     *                              tagging support
     */
    public function __construct(
        protected CacheInterface $cache,
    ) {
    }

    /**
     * Issues a new token and stores it in the cache.
     *
     * When the cache supports tagging the token is written under
     * {@see TokenServiceInterface::CACHE_TAG} so that
     * {@see clearAllTokens()} can later drop tokens without touching anything
     * else in the pool.
     *
     * @param string   $type    token type: lowercase letters and digits, at
     *                          most 16 characters
     * @param mixed    $payload optional data to carry with the token; it must
     *                          survive the cache's serialisation
     * @param int|null $ttl     lifetime in seconds, or null to let the cache
     *                          decide how long to keep the entry
     *
     * @return TokenInterface the created token, carrying its unique identifier
     *
     * @throws \InvalidArgumentException                 if the token type is not acceptable
     * @throws TokenStorageException                     if the cache refused to store the token
     * @throws \Psr\SimpleCache\InvalidArgumentException if the cache key is not a legal value
     */
    public function createToken(string $type, mixed $payload = null, ?int $ttl = null): TokenInterface
    {
        $token = new Token($type, $payload);
        $cache = $this->getCache();
        $key = $this->buildKey($token->getUid());

        $stored = $this->supportsTagging($cache)
            ? $cache->setTagged($key, $token, static::CACHE_TAG, $ttl)
            : $cache->set($key, $token, $ttl);

        if (false === $stored) {
            throw TokenStorageException::forKey($key);
        }

        return $token;
    }

    /**
     * Redeems a token by its unique identifier.
     *
     * A missing, expired or unrecognisable entry all produce null, so callers
     * only need the one check. The entry is deleted as it is read unless
     * `$keepToken` says otherwise, which is what makes a token single use.
     *
     * @param string $uid       unique identifier of the token to redeem
     * @param bool   $keepToken true to leave the token in the cache so it can
     *                          be redeemed again
     *
     * @return TokenInterface|null the redeemed token, or null when no live
     *                             token is stored under that identifier
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException if the cache key is not a legal value
     */
    public function consumeToken(string $uid, bool $keepToken = false): ?TokenInterface
    {
        $key = $this->buildKey($uid);
        $cache = $this->getCache();
        $token = $cache->get($key);

        if (!$token instanceof TokenInterface) {
            return null;
        }

        if (false === $keepToken) {
            $cache->delete($key);
        }

        return $token;
    }

    /**
     * Drops every token the service is responsible for.
     *
     * On a cache that supports tagging only the tagged tokens go. On a plain
     * PSR-16 cache the entire pool is emptied, since `clear()` is the only bulk
     * operation the standard offers. That is destructive for anything else
     * sharing the pool, so either give the service its own pool or use a cache
     * with tagging support.
     *
     * @return bool true when the tokens were dropped, false otherwise
     */
    public function clearAllTokens(): bool
    {
        $cache = $this->getCache();

        if ($this->supportsTagging($cache)) {
            return $cache->clearByTag(static::CACHE_TAG);
        }

        return $cache->clear();
    }

    /**
     * Returns the cache the service was constructed with.
     *
     * Kept protected so a subclass can swap in a different pool, for instance
     * one resolved per tenant, without the rest of the class changing.
     *
     * @return CacheInterface the PSR-16 cache holding the tokens
     */
    protected function getCache(): CacheInterface
    {
        return $this->cache;
    }

    /**
     * Reports whether the given cache can store and clear entries by tag.
     *
     * The check is made on the methods rather than on
     * {@see TaggedCacheInterface}, which covers both shapes at once: a cache
     * implementing the contract necessarily carries the two methods, and a
     * cache that merely exposes them is recognised as well. The second case is
     * what lets `IDCT\Cache\RapidCacheClient` be used without this package
     * depending on `idct/php-rapid-cache-client`.
     *
     * Both methods are required. Being able to write a tag without being able
     * to clear by one would leave {@see clearAllTokens()} with no way to reach
     * the tokens it just tagged, so half an implementation is treated as none.
     *
     * @param CacheInterface $cache cache to inspect
     *
     * @return bool true when both tagging methods are available
     *
     * @phpstan-assert-if-true TaggedCacheInterface $cache
     */
    protected function supportsTagging(CacheInterface $cache): bool
    {
        return method_exists($cache, 'setTagged') && method_exists($cache, 'clearByTag');
    }

    /**
     * Turns a token identifier into the cache key the token is stored under.
     *
     * @param string $uid token's unique identifier
     *
     * @return string the prefixed cache key
     */
    protected function buildKey(string $uid): string
    {
        return static::CACHE_KEY.$uid;
    }
}
