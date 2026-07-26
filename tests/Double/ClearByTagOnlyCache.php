<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Double;

/**
 * Cache that can clear by tag but cannot write one.
 *
 * The mirror image of {@see SetTaggedOnlyCache}: nothing would ever carry the
 * tag, so clearing by it would silently do nothing. The token service has to
 * fall back to plain PSR-16 behaviour here as well.
 */
final class ClearByTagOnlyCache extends InMemoryCache
{
    /**
     * Pretends to clear by tag, which is all this double is asked to do.
     */
    public function clearByTag(string $tag): bool
    {
        return true;
    }
}
