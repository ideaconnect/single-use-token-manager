<?php

declare(strict_types=1);

namespace IDCT\SingleUseTokenManager;

use IDCT\SingleUseTokenManager\Contract\AtomicCacheInterface;
use IDCT\SingleUseTokenManager\Contract\TaggedCacheInterface;
use IDCT\SingleUseTokenManager\Contract\TokenInterface;
use IDCT\SingleUseTokenManager\Contract\TokenServiceInterface;
use IDCT\SingleUseTokenManager\Exception\TokenRemovalException;
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
     * Characters PSR-16 reserves, which a namespace therefore may not contain.
     *
     * Rejecting them here beats letting the cache fail later on a key nobody
     * can trace back to the namespace that produced it.
     *
     * @var string
     */
    public const RESERVED_NAMESPACE_CHARS = '{}()/\\@:';

    /** @var string Message template used when a namespace is rejected */
    public const NAMESPACE_ERROR = 'Namespace must not contain any of the PSR-16 reserved characters `%s`. Used `%s`.';

    /** @var bool Whether the injected cache can group and clear entries by tag */
    private readonly bool $cacheSupportsTagging;

    /** @var bool Whether the injected cache can read and remove an entry in one step */
    private readonly bool $cacheSupportsAtomicTake;

    /**
     * Initialises the service with the cache that will hold the tokens.
     *
     * No connection is opened here. Whether the cache connects lazily or eagerly
     * is entirely up to the implementation being injected.
     *
     * @param CacheInterface $cache     PSR-16 cache used for token storage; give it
     *                                  a dedicated pool if you intend to call
     *                                  {@see clearAllTokens()} on a cache without
     *                                  tagging support
     * @param string         $namespace optional prefix separating this service's
     *                                  tokens from those of another sharing the
     *                                  same cache, for instance one per tenant.
     *                                  Empty, the default, keeps the keys and the
     *                                  tag exactly as earlier versions wrote them
     *
     * @throws \InvalidArgumentException if the namespace contains a character
     *                                   PSR-16 reserves
     */
    public function __construct(
        protected CacheInterface $cache,
        private readonly string $namespace = '',
    ) {
        if ('' !== $namespace && false !== strpbrk($namespace, self::RESERVED_NAMESPACE_CHARS)) {
            throw new \InvalidArgumentException(sprintf(self::NAMESPACE_ERROR, self::RESERVED_NAMESPACE_CHARS, $namespace));
        }

        // Settled once, here, rather than on every operation. A cache object
        // cannot gain or lose methods during its lifetime, so asking again on
        // each createToken() or consumeToken() would burn reflection on the hot
        // path to reach a conclusion that cannot have changed.
        //
        // A cache declaring the contract is taken at its word. Everything else
        // is judged on the methods it carries, which is what lets a cache that
        // has never heard of this package, such as `IDCT\Cache\RapidCacheClient`,
        // be used without the contract becoming a required dependency.
        $this->cacheSupportsTagging = $cache instanceof TaggedCacheInterface
            || (method_exists($cache, 'setTagged') && method_exists($cache, 'clearByTag'));

        $this->cacheSupportsAtomicTake = $cache instanceof AtomicCacheInterface
            || method_exists($cache, 'take');
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
            ? $cache->setTagged($key, $token, $this->cacheTag(), $ttl)
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
     * only need the one check. The entry is removed as it is read unless
     * `$keepToken` says otherwise, which is what makes a token single use.
     *
     * How well that holds under concurrency depends on the cache. When it
     * implements {@see AtomicCacheInterface} the read and the removal are one
     * operation, so exactly one of several simultaneous callers can win. On a
     * plain PSR-16 cache they are two operations with a gap in between, and
     * every caller arriving inside that gap redeems the same token. If more
     * than one request can present the same token at once, and the consequence
     * of both succeeding matters, use a cache that can take atomically.
     *
     * @param string $uid       unique identifier of the token to redeem
     * @param bool   $keepToken true to leave the token in the cache so it can
     *                          be redeemed again
     *
     * @return TokenInterface|null the redeemed token, or null when no live
     *                             token is stored under that identifier
     *
     * @throws TokenRemovalException                     if the cache refused to remove a redeemed token
     * @throws \Psr\SimpleCache\InvalidArgumentException if the cache key is not a legal value
     */
    public function consumeToken(string $uid, bool $keepToken = false): ?TokenInterface
    {
        $key = $this->buildKey($uid);
        $cache = $this->getCache();

        if (false === $keepToken && $this->supportsAtomicTake($cache)) {
            $taken = $cache->take($key);

            return $taken instanceof TokenInterface ? $taken : null;
        }

        $token = $cache->get($key);

        if (!$token instanceof TokenInterface) {
            return null;
        }

        // A refused removal leaves the token redeemable while the caller is
        // told it was spent, so it is reported rather than swallowed, for the
        // same reason createToken() reports a refused write.
        if (false === $keepToken && false === $cache->delete($key)) {
            throw TokenRemovalException::forKey($key);
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
            return $cache->clearByTag($this->cacheTag());
        }

        return $cache->clear();
    }

    /**
     * Returns the cache the service was constructed with.
     *
     * Kept protected so a subclass can swap in a different pool, for instance
     * one resolved per tenant, without the rest of the class changing.
     *
     * A subclass returning a pool of the same kind needs nothing else. One
     * returning a different implementation must also override
     * {@see supportsTagging()} and {@see supportsAtomicTake()}, because those
     * report what the cache given to the constructor could do, which is settled
     * once and cannot follow a pool that changes per call.
     *
     * @return CacheInterface the PSR-16 cache holding the tokens
     */
    protected function getCache(): CacheInterface
    {
        return $this->cache;
    }

    /**
     * Reports whether the cache can store and clear entries by tag.
     *
     * Answered from the flag settled in the constructor, so this costs a field
     * read rather than a fresh round of reflection on every operation.
     *
     * Both tagging methods are required. Being able to write a tag without
     * being able to clear by one would leave {@see clearAllTokens()} with no
     * way to reach the tokens it just tagged, so half an implementation is
     * treated as none.
     *
     * The `$cache` argument is deliberately not inspected. It is here so the
     * assertion below can narrow the caller's variable, which is what lets the
     * duck-typed `setTagged()` and `clearByTag()` calls satisfy static
     * analysis. Override this method to force the answer either way.
     *
     * @param CacheInterface $cache cache the caller is about to use, narrowed
     *                              by the assertion rather than examined
     *
     * @return bool true when both tagging methods are available
     *
     * @phpstan-assert-if-true TaggedCacheInterface $cache
     */
    protected function supportsTagging(CacheInterface $cache): bool
    {
        return $this->cacheSupportsTagging;
    }

    /**
     * Reports whether the cache can read and remove an entry in one go.
     *
     * Answered from the flag settled in the constructor, for the same reason
     * {@see supportsTagging()} is.
     *
     * @param CacheInterface $cache cache the caller is about to use, narrowed
     *                              by the assertion rather than examined
     *
     * @return bool true when the cache can take atomically
     *
     * @phpstan-assert-if-true AtomicCacheInterface $cache
     */
    protected function supportsAtomicTake(CacheInterface $cache): bool
    {
        return $this->cacheSupportsAtomicTake;
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
        return $this->namespace.static::CACHE_KEY.$uid;
    }

    /**
     * Returns the tag this service files its tokens under.
     *
     * Namespaced alongside the keys, so that clearing one service's tokens on a
     * shared pool cannot reach another's.
     *
     * @return string the tag used for tagged writes and tag invalidation
     */
    protected function cacheTag(): string
    {
        return $this->namespace.static::CACHE_TAG;
    }
}
