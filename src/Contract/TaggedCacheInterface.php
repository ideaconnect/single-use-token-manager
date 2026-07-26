<?php

declare(strict_types=1);

namespace IDCT\SingleUseTokenManager\Contract;

use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 cache that can also group entries under a tag.
 *
 * PSR-16 itself offers no way to invalidate a subset of the pool: the only
 * bulk operation is `clear()`, which empties everything. That is a problem for
 * a token manager sharing a cache with the rest of an application, because
 * dropping every token would also drop every unrelated entry.
 *
 * This contract adds the two operations the token service needs to avoid that:
 * writing an entry under a tag, and clearing everything carrying that tag.
 *
 * The method signatures are deliberately identical to those of
 * `IDCT\Cache\CacheServiceInterface` from `idct/php-rapid-cache-client`, so a
 * cache implementing that interface satisfies this one as well. The token
 * service also accepts any cache that merely exposes both methods, which keeps
 * `idct/php-rapid-cache-client` an optional dependency rather than a required
 * one.
 *
 * @author IDCT
 *
 * @since 2.0.0
 * @see \IDCT\SingleUseTokenManager\TokenService::supportsTagging()
 */
interface TaggedCacheInterface extends CacheInterface
{
    /**
     * Stores a value and associates it with a tag in a single call.
     *
     * @param string                 $key   PSR-16 compliant cache key
     * @param mixed                  $value value to store
     * @param string                 $tag   tag to associate the key with
     * @param int|\DateInterval|null $ttl   lifetime in seconds, or null for the
     *                                      cache's own default
     *
     * @return bool true when the value was stored, false otherwise
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException if the key is not a legal value
     */
    public function setTagged(string $key, mixed $value, string $tag, int|\DateInterval|null $ttl = null): bool;

    /**
     * Removes every cache entry associated with the given tag.
     *
     * Entries that carry no tag, or carry a different one, are left untouched.
     *
     * @param string $tag tag whose entries should be dropped
     *
     * @return bool true when the entries were removed, false otherwise
     */
    public function clearByTag(string $tag): bool;
}
