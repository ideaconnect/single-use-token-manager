<?php

declare(strict_types=1);

namespace GryfOSS\SingleUseTokenManager;

use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Data transfer object for token identification.
 *
 * This class represents a token identifier with validation constraints,
 * serialization configuration, and OpenAPI documentation attributes.
 * It's designed for use in API endpoints where token UIDs need to be
 * validated and processed.
 *
 * The token property is validated to ensure it's a non-empty string,
 * which is essential for token consumption operations.
 *
 * @author GryfOSS Team
 * @since 1.0.0
 */
class TokenIdentifier
{
    /**
     * The token's unique identifier.
     *
     * This property holds the UID of a token that should be consumed or validated.
     * It must be a non-empty string that corresponds to a valid token UID.
     *
     * Validation constraints:
     * - Must be a string type
     * - Cannot be blank (empty string or whitespace only)
     * - Cannot be null
     *
     * Serialization:
     * - Serialized as 'token' in JSON/XML representations
     *
     * OpenAPI:
     * - Documented as string type in API specifications
     */
    #[SerializedName('token')]
    #[OA\Schema(type: 'string', description: 'The unique identifier of the token to consume', example: '01234567-89ab-cdef-0123-456789abcdef')]
    #[Assert\Type('string')]
    #[Assert\NotBlank()]
    #[Assert\NotNull()]
    public string $token;
}
