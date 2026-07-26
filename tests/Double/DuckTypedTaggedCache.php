<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Double;

/**
 * Cache exposing both tagging methods without implementing the contract.
 *
 * This is the shape `IDCT\Cache\RapidCacheClient` has as far as this package is
 * concerned: it satisfies the tagging methods but knows nothing about
 * `IDCT\SingleUseTokenManager\Contract\TaggedCacheInterface`. The token service
 * has to recognise it all the same, otherwise the optional dependency would
 * have to become a required one.
 */
final class DuckTypedTaggedCache extends InMemoryCache
{
    use TaggingBehaviour;
}
