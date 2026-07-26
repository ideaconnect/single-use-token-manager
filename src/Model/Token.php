<?php

declare(strict_types=1);

namespace IDCT\SingleUseTokenManager\Model;

use IDCT\SingleUseTokenManager\Contract\TokenInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV6;

/**
 * Immutable single-use token.
 *
 * Each instance receives a UUID v6 identifier at construction time. Version 6
 * is time ordered, so tokens created close together sort close together, which
 * keeps the index locality of a cache or database sensible while still being
 * unguessable in practice.
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
    /** @var string Message template used when the given type is rejected */
    public const TYPE_ERROR = 'Type must be a not empty, only alphanumeric string not longer than 16 symbols. Used `%s`.';

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

    /** @var UuidV6 Time ordered unique identifier of this token */
    private UuidV6 $uid;

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

        $this->uid = Uuid::v6();
    }

    /**
     * Returns the unique identifier of this token.
     *
     * @return string the canonical string form of the token's UUID v6
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
