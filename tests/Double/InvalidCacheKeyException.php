<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Double;

use Psr\SimpleCache\InvalidArgumentException as PsrInvalidArgumentException;

/**
 * Raised by the cache doubles when handed a key PSR-16 does not allow.
 *
 * PSR-16 requires the exception thrown for an illegal key to implement
 * {@see PsrInvalidArgumentException}, which is an interface rather than a
 * class, so an implementation has to supply its own. Extending the SPL
 * exception as well keeps `catch (\InvalidArgumentException)` working for
 * callers that do not know about the PSR interface.
 */
final class InvalidCacheKeyException extends \InvalidArgumentException implements PsrInvalidArgumentException
{
}
