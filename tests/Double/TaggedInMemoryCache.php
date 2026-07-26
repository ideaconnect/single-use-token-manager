<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Double;

use IDCT\SingleUseTokenManager\Contract\TaggedCacheInterface;

/**
 * In-memory cache that declares tagging support through the contract.
 *
 * Stands in for a cache such as `IDCT\Cache\RapidCacheClient` in tests that
 * only care about which branch the token service takes, without needing a
 * Redis server to be running.
 */
final class TaggedInMemoryCache extends InMemoryCache implements TaggedCacheInterface
{
    use TaggingBehaviour;
}
