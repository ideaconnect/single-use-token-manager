<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Double;

/**
 * Cache that can write a tag but cannot clear by one.
 *
 * Half a tagging implementation is worse than none: the token service would
 * write every token under a tag and then have no way to clear them, so it has
 * to treat this cache as a plain PSR-16 one.
 */
final class SetTaggedOnlyCache extends InMemoryCache
{
    /**
     * Stores a value, ignoring the tag it was given.
     */
    public function setTagged(string $key, mixed $value, string $tag, int|\DateInterval|null $ttl = null): bool
    {
        return $this->write($key, $value, $ttl, $tag);
    }
}
