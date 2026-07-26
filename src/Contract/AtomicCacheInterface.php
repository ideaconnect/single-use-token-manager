<?php

declare(strict_types=1);

namespace IDCT\SingleUseTokenManager\Contract;

use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 cache that can read an entry and remove it in one operation.
 *
 * PSR-16 has no way to take a value: reading is `get()` and removing is
 * `delete()`, two separate calls with a gap in between. For a cache that is a
 * detail, but for a single-use token it is the whole guarantee. Every caller
 * that arrives inside that gap reads a token that is still there, so several of
 * them can redeem the same token and each be told it succeeded.
 *
 * A cache implementing this contract closes the gap, and
 * {@see \IDCT\SingleUseTokenManager\TokenService::consumeToken()} uses it when
 * it is available.
 *
 * Backing it is usually one command. Redis and Valkey have `GETDEL` from 6.2
 * onwards, which phpredis exposes as `getDel()`. Anything with a scripting or
 * transaction facility can do the same.
 *
 * As with {@see TaggedCacheInterface}, the token service detects the method
 * rather than the interface, so a cache carrying a compatible `take()` is used
 * even if it has never heard of this package.
 *
 * @author IDCT
 *
 * @since 2.1.0
 * @see \IDCT\SingleUseTokenManager\TokenService::supportsAtomicTake()
 */
interface AtomicCacheInterface extends CacheInterface
{
    /**
     * Returns the value stored under the key and removes it, atomically.
     *
     * No other caller may observe the entry as present once this call has
     * returned it. Implementations that cannot promise that should not
     * implement this interface, because a half-atomic take is worse than an
     * honest `get()` followed by `delete()`: it looks safe and is not.
     *
     * @param string $key PSR-16 compliant cache key
     *
     * @return mixed the stored value, or null when the key held nothing
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException if the key is not a legal value
     */
    public function take(string $key): mixed;
}
