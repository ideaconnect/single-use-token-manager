<?php

declare(strict_types=1);

namespace IDCT\SingleUseTokenManager\Exception;

/**
 * Thrown when the cache refuses to remove a token that was just redeemed.
 *
 * PSR-16 `delete()` reports failure by returning false. Discarding that return
 * value would leave the token sitting in the cache, ready to be redeemed again,
 * while the caller is told it was spent. That is the single-use guarantee
 * failing silently, so the failure is raised instead.
 *
 * The redeemed token is not returned when this is thrown. Losing one legitimate
 * redemption is the better outcome: the caller can retry, whereas a token that
 * quietly stays live can be replayed by somebody else.
 *
 * @author IDCT
 *
 * @since 2.1.0
 * @see TokenStorageException for the equivalent on the write path
 */
final class TokenRemovalException extends \RuntimeException
{
    /** @var string Message template used by {@see forKey()} */
    public const MESSAGE = 'The cache refused to remove the redeemed token stored under key `%s`.';

    /**
     * Builds the exception for a cache key that could not be removed.
     *
     * @param string $key cache key the token is still stored under
     *
     * @return self exception carrying a message naming the key
     */
    public static function forKey(string $key): self
    {
        return new self(sprintf(self::MESSAGE, $key));
    }
}
