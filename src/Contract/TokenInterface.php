<?php

declare(strict_types=1);

namespace IDCT\SingleUseTokenManager\Contract;

/**
 * Contract for single-use token objects.
 *
 * A token carries three pieces of information: a globally unique identifier,
 * a short type used to categorise it, and an optional payload. Implementations
 * are expected to be immutable once constructed, so that a token handed to the
 * caller cannot drift away from the copy held in the cache.
 *
 * @author IDCT
 *
 * @since 1.0.0
 */
interface TokenInterface
{
    /**
     * Returns the unique identifier of the token.
     *
     * The identifier is what the client sends back when it wants to redeem the
     * token, and it is also the value the service turns into a cache key. It
     * must therefore be globally unique and safe to use inside a PSR-16 key,
     * which rules out the reserved characters `{}()/\@:`.
     *
     * @return string the token's unique identifier
     */
    public function getUid(): string;

    /**
     * Returns the type identifier of the token.
     *
     * The type groups tokens by purpose, for example `reset`, `verify` or
     * `invite`. It is stored alongside the token so that the code redeeming it
     * can confirm the token was issued for the operation being performed.
     *
     * @return string the token type identifier
     */
    public function getType(): string;

    /**
     * Returns the payload associated with the token.
     *
     * The payload holds whatever the issuing code needs when the token comes
     * back, such as a user identifier or a set of permissions. It travels
     * through the cache, so it has to survive the cache's serialisation. Null
     * means the token carries no extra data.
     *
     * @return mixed the token's payload, or null when none was set
     */
    public function getPayload(): mixed;
}
