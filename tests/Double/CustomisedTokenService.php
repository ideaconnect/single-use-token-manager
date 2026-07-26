<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Double;

use IDCT\SingleUseTokenManager\TokenService;
use Psr\SimpleCache\CacheInterface;

/**
 * Token service with every extension point overridden.
 *
 * The three protected methods of {@see TokenService} exist so that a subclass
 * can resolve the cache per call, key tokens differently, or opt out of
 * tagging. This double exercises all three at once, which is what proves they
 * really are overridable rather than protected by accident.
 */
final class CustomisedTokenService extends TokenService
{
    /** @var string Prefix used in place of the inherited cache key prefix */
    public const CUSTOM_PREFIX = 'CUSTOM_';

    /**
     * @param CacheInterface $cache        cache the parent is constructed with, and
     *                                     which the overridden getCache() ignores
     * @param CacheInterface $actualCache  cache every operation should really reach
     * @param bool           $allowTagging value the overridden supportsTagging() reports
     */
    public function __construct(
        CacheInterface $cache,
        private readonly CacheInterface $actualCache,
        private readonly bool $allowTagging = false,
    ) {
        parent::__construct($cache);
    }

    protected function getCache(): CacheInterface
    {
        return $this->actualCache;
    }

    protected function supportsTagging(CacheInterface $cache): bool
    {
        return $this->allowTagging;
    }

    protected function buildKey(string $uid): string
    {
        return self::CUSTOM_PREFIX.$uid;
    }
}
