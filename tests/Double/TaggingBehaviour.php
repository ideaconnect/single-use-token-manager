<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Double;

/**
 * Tag bookkeeping shared by the tagging capable cache doubles.
 *
 * Keeping it in a trait rather than a base class lets one double declare
 * {@see \IDCT\SingleUseTokenManager\Contract\TaggedCacheInterface} while
 * another merely exposes the same two methods, which is exactly the difference
 * the token service has to cope with.
 */
trait TaggingBehaviour
{
    /** @var array<string, array<int, string>> Cache keys grouped by tag */
    private array $tags = [];

    /** @var bool When true clearByTag() reports failure */
    private bool $clearByTagFails = false;

    /**
     * Makes every following clearByTag() call report failure.
     */
    public function failClearByTag(): void
    {
        $this->clearByTagFails = true;
    }

    /**
     * Returns the cache keys currently associated with the given tag.
     *
     * @return array<int, string>
     */
    public function keysTaggedWith(string $tag): array
    {
        return $this->tags[$tag] ?? [];
    }

    /**
     * Stores a value and files its key under the given tag.
     */
    public function setTagged(string $key, mixed $value, string $tag, int|\DateInterval|null $ttl = null): bool
    {
        if (!$this->write($key, $value, $ttl, $tag)) {
            return false;
        }

        $this->tags[$tag][] = $key;

        return true;
    }

    /**
     * Drops every key filed under the given tag, leaving the rest in place.
     */
    public function clearByTag(string $tag): bool
    {
        if ($this->clearByTagFails) {
            return false;
        }

        foreach ($this->tags[$tag] ?? [] as $key) {
            $this->delete($key);
        }

        unset($this->tags[$tag]);

        return true;
    }
}
