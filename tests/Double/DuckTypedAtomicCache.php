<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Double;

/**
 * Cache exposing take() without implementing the contract.
 *
 * Stands for a third-party cache that happens to carry a compatible method and
 * has never heard of this package. The token service has to use it anyway,
 * otherwise the contract would have to become a required dependency for
 * everyone.
 */
final class DuckTypedAtomicCache extends InMemoryCache
{
    use AtomicTakeBehaviour;
}
