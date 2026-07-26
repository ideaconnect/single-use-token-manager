<?php

declare(strict_types=1);

namespace IDCT\SingleUseTokenManager\Model;

use IDCT\SingleUseTokenManager\Contract\TokenInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

/**
 * Immutable single-use token.
 *
 * Each instance receives a UUID v4 identifier at construction time, which
 * Symfony builds from `random_bytes()`. The version matters here. A token is a
 * bearer capability: whoever holds the identifier can perform the action, so
 * the identifier has to be unguessable, and only a cryptographically secure
 * source makes it so.
 *
 * Time ordered versions are deliberately not used. A v6 identifier carries a
 * node that Symfony computes once per process and then repeats on every token
 * that process issues, so a single leaked token discloses it for all the
 * others, leaving little beyond the clock to guess. A v7 identifier is seeded
 * once and then incremented, so consecutive values sit next to each other.
 * Both are fine for database keys and neither is fine for a secret. RFC 9562
 * says as much: do not assume a UUID is hard to guess unless it comes from a
 * CSPRNG.
 *
 * Giving up the time ordering costs only cache index locality, which is worth
 * very little for entries fetched by exact key that expire on their own.
 *
 * The type is deliberately restrictive: lowercase letters and digits only, at
 * most 16 characters. That keeps it safe to embed in a cache key or a URL
 * without escaping, and short enough not to bloat every stored entry.
 *
 * @author IDCT
 *
 * @since 1.0.0
 */
final class Token implements TokenInterface
{
    /**
     * Message template used when the given type is rejected.
     *
     * It names the real rule rather than saying "alphanumeric", which would
     * suggest uppercase is allowed and leave the caller guessing why `Reset`
     * was refused.
     *
     * @var string
     */
    public const TYPE_ERROR = 'Type must be 1 to 16 characters, using only lowercase letters and digits. Used `%s`.';

    /**
     * Pattern every accepted token type has to match in full.
     *
     * Length and character set are checked together so that neither can be
     * satisfied without the other. The subject is anchored with `\z` rather
     * than `$`, because `$` would also match just before a trailing newline and
     * would let `reset\n` through.
     *
     * @var string
     */
    public const TYPE_PATTERN = '/^[a-z0-9]{1,16}\z/';

    /** @var UuidV4 Randomly generated unique identifier of this token */
    private UuidV4 $uid;

    /**
     * Creates a token of the given type, carrying an optional payload.
     *
     * @param string $type    token type: lowercase letters and digits, 1 to 16
     *                        characters
     * @param mixed  $payload optional data to carry with the token; it must
     *                        survive the serialisation of whichever cache
     *                        stores the token
     *
     * @throws \InvalidArgumentException if the type is empty, too long, or
     *                                   contains anything other than lowercase
     *                                   letters and digits
     */
    public function __construct(
        private readonly string $type,
        private readonly mixed $payload = null,
    ) {
        if (1 !== preg_match(self::TYPE_PATTERN, $type)) {
            throw new \InvalidArgumentException(sprintf(self::TYPE_ERROR, $type));
        }

        $this->uid = Uuid::v4();
    }

    /**
     * Returns the unique identifier of this token.
     *
     * @return string the canonical string form of the token's UUID v4
     */
    public function getUid(): string
    {
        return (string) $this->uid;
    }

    /**
     * Returns the type this token was created with.
     *
     * @return string the token type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Returns the payload this token was created with.
     *
     * @return mixed the payload, or null when the token carries none
     */
    public function getPayload(): mixed
    {
        return $this->payload;
    }
}
