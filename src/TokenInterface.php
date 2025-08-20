<?php

declare(strict_types=1);

namespace GryfOSS\SingleUseTokenManager;

/**
 * Interface for single-use token objects.
 *
 * Defines the contract for token implementations that provide unique identification,
 * type classification, and optional payload data. Tokens should be immutable once
 * created to ensure security and consistency.
 *
 * @author GryfOSS Team
 * @since 1.0.0
 */
interface TokenInterface
{
    /**
     * Returns the unique identifier of the token.
     *
     * The UID should be globally unique and suitable for use as a cache key
     * or database primary key. Typically implemented as a UUID string.
     *
     * @return string The token's unique identifier
     */
    public function getUid(): string;

    /**
     * Returns the type identifier of the token.
     *
     * The type categorizes the token's intended use case and should be a
     * standardized string that your application recognizes (e.g., 'password_reset',
     * 'email_verification', 'api_access').
     *
     * @return string The token type identifier
     */
    public function getType(): string;

    /**
     * Returns the payload data associated with the token.
     *
     * The payload contains any additional data relevant to the token's purpose.
     * This could be user information, permissions, configuration, or any other
     * serializable data. May be null if no payload is required.
     *
     * @return mixed|null The token's payload data, or null if none was set
     */
    public function getPayload();
}
