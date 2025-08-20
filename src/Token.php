<?php

declare(strict_types=1);

namespace GryfOSS\SingleUseTokenManager;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV6;

/**
 * Immutable token implementation.
 *
 * Represents a single-use token with a unique identifier, type, and optional payload.
 * Tokens are immutable once created and use UUID v6 for time-ordered unique identifiers.
 *
 * The token type must be a non-empty alphanumeric string (lowercase a-z, 0-9) with
 * a maximum length of 16 characters for efficient storage and indexing.
 *
 * @author GryfOSS Team
 * @since 1.0.0
 */
final class Token implements TokenInterface
{
    /** @var string Error message template for invalid token types */
    const TYPE_ERROR = 'Type must be a not empty, only alphanumeric string not longer than 16 symbols. Used `%s`.';

    /** @var UuidV6 The unique identifier for this token */
    private UuidV6 $uid;

    /**
     * Creates a new immutable token with the specified type and payload.
     *
     * The token is assigned a time-ordered UUID v6 identifier for uniqueness
     * and optimal database/cache performance.
     *
     * @param string $type The token type (alphanumeric, max 16 chars, lowercase)
     * @param mixed|null $payload Optional data to associate with the token
     *
     * @throws InvalidArgumentException If the token type is invalid
     */
    public function __construct(
        private string $type,
        private $payload = null)
    {
        if (empty($type) || mb_strlen($type) > 16 || preg_match('/[^a-z0-9]/', $type)) {
            throw new InvalidArgumentException(sprintf(static::TYPE_ERROR, $type));
        }

        $this->uid = Uuid::v6();
        $this->type = $type;
        $this->payload = $payload;
    }

    /**
     * Returns the unique identifier of this token.
     *
     * The UID is a UUID v6 string that provides time-based ordering
     * and global uniqueness across all token instances.
     *
     * @return string The token's unique identifier
     */
    public function getUid(): string
    {
        return (string) $this->uid;
    }

    /**
     * Returns the type identifier of this token.
     *
     * The type is used for categorizing tokens and should represent
     * the intended use case (e.g., 'reset', 'verify', 'access').
     *
     * @return string The token type identifier
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Returns the payload data associated with this token.
     *
     * The payload can contain any serializable data relevant to the
     * token's purpose. It may be null if no additional data is needed.
     *
     * @return mixed The token's payload data, or null if none was set
     */
    public function getPayload()
    {
        return $this->payload;
    }
}
