<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Double;

use IDCT\SingleUseTokenManager\Contract\AtomicCacheInterface;

/**
 * In-memory cache declaring atomic take through the contract.
 */
final class AtomicInMemoryCache extends InMemoryCache implements AtomicCacheInterface
{
    use AtomicTakeBehaviour;
}
