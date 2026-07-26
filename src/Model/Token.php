<?php

declare(strict_types=1);

namespace IDCT\SingleUseTokenManager\Model;

use IDCT\SingleUseTokenManager\Contract\TokenInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Immutable single-use token.
 *
 * Left to itself a token generates a UUID v4 identifier at construction time,
 * which Symfony builds from `random_bytes()`. The version matters here. Such a
 * token is a bearer capability: whoever holds the identifier can perform the
 * action, so the identifier has to be unguessable, and only a cryptographically
 * secure source makes it so.
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
 * A caller may instead supply the identifier. That is for the case where the
 * request redeeming the token has nowhere to carry a random one — a form that
 * posts back an account id and a short code the user copied out of an e-mail,
 * say — so the token has to be reachable from data the caller already holds.
 * Doing so changes what the identifier means, and the caller takes on two
 * duties the library can no longer discharge:
 *
 * - **The identifier is no longer a secret.** Deriving it from a user id, an
 *   order number or anything else an attacker can enumerate means reaching the
 *   token proves nothing. Whatever actually authorises the action — a code sent
 *   out of band, a signature, a session — has to be carried in the payload and
 *   checked by the caller after the token comes back.
 * - **Uniqueness is the caller's problem.** Two tokens built with the same
 *   identifier occupy one cache entry, and the second silently replaces the
 *   first. Derive it from something that is unique per live token, and expect
 *   the slot to be reused rather than duplicated.
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

    /**
     * Characters PSR-16 reserves, which a supplied identifier may not contain.
     *
     * The same set {@see \IDCT\SingleUseTokenManager\TokenService::RESERVED_NAMESPACE_CHARS}
     * rejects, and for the same reason: the identifier ends up inside a cache
     * key, so a value the cache would refuse is caught here, where the caller
     * can still see which identifier caused it.
     *
     * @var string
     */
    public const RESERVED_UID_CHARS = '{}()/\\@:';

    /**
     * Message template used when a supplied identifier is rejected.
     *
     * @var string
     */
    public const UID_ERROR = 'Identifier must not be empty and must not contain any of the PSR-16 reserved characters `%s`. Used `%s`.';

    /** @var string Unique identifier of this token */
    private readonly string $uid;

    /**
     * Creates a token of the given type, carrying an optional payload.
     *
     * @param string      $type    token type: lowercase letters and digits, 1 to 16
     *                             characters
     * @param mixed       $payload optional data to carry with the token; it must
     *                             survive the serialisation of whichever cache
     *                             stores the token
     * @param string|null $uid     identifier to reach this token by, or null to
     *                             receive an unguessable UUID v4. Supplying one
     *                             makes the token addressable rather than secret;
     *                             see the class documentation for the two duties
     *                             that come with it
     *
     * @throws \InvalidArgumentException if the type is empty, too long, or
     *                                   contains anything other than lowercase
     *                                   letters and digits, or if a supplied
     *                                   identifier is empty or carries a
     *                                   character PSR-16 reserves
     */
    public function __construct(
        private readonly string $type,
        private readonly mixed $payload = null,
        ?string $uid = null,
    ) {
        if (1 !== preg_match(self::TYPE_PATTERN, $type)) {
            throw new \InvalidArgumentException(sprintf(self::TYPE_ERROR, $type));
        }

        if (null !== $uid && ('' === $uid || false !== strpbrk($uid, self::RESERVED_UID_CHARS))) {
            throw new \InvalidArgumentException(sprintf(self::UID_ERROR, self::RESERVED_UID_CHARS, $uid));
        }

        $this->uid = $uid ?? (string) Uuid::v4();
    }

    /**
     * Returns the unique identifier of this token.
     *
     * @return string the identifier supplied at construction, or the canonical
     *                string form of the generated UUID v4
     */
    public function getUid(): string
    {
        return $this->uid;
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
