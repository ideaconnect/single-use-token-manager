<?php

declare(strict_types=1);

namespace IDCT\SingleUseTokenManager\Exception;

/**
 * Thrown when the cache refuses to store a freshly created token.
 *
 * PSR-16 write methods report failure by returning false rather than by
 * throwing, so a service that ignores the return value would hand out a token
 * that was never persisted. The caller would then send that token to a user who
 * could never redeem it. Turning the false into this exception makes the
 * failure impossible to miss.
 *
 * @author IDCT
 *
 * @since 2.0.0
 */
final class TokenStorageException extends \RuntimeException
{
    /** @var string Message template used by {@see forKey()} */
    public const MESSAGE = 'The cache refused to store the token under key `%s`.';

    /**
     * Builds the exception for a cache key that could not be written.
     *
     * @param string $key cache key the token was to be stored under
     *
     * @return self exception carrying a message naming the rejected key
     */
    public static function forKey(string $key): self
    {
        return new self(sprintf(self::MESSAGE, $key));
    }
}
