<?php

declare(strict_types=1);

namespace IDCT\SingleUseTokenManager\Model;

use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request object naming the token a caller wants to redeem.
 *
 * An HTTP endpoint that redeems a token receives little more than the token
 * identifier. This class gives that single value a home, so the identifier can
 * be deserialised from the request body, validated, and documented in the API
 * schema without the endpoint hand rolling any of it.
 *
 * The class carries no behaviour on purpose. Validation is driven by the
 * Symfony Validator attributes, serialisation by the Serializer attribute, and
 * the OpenAPI description by the swagger-php attribute.
 *
 * @author IDCT
 *
 * @since 1.0.0
 */
class TokenIdentifier
{
    /**
     * Unique identifier of the token to redeem.
     *
     * Validation rejects anything that is not a non-empty string, so an
     * endpoint can trust the value before it reaches the token service. The
     * blank check trims first, because a value made only of whitespace is just
     * as unusable as an empty one and would otherwise slip through as a cache
     * miss further down the line.
     *
     * Serialisation exposes the property as `token`, which is also the name the
     * OpenAPI schema documents.
     */
    #[SerializedName('token')]
    #[OA\Schema(type: 'string', description: 'The unique identifier of the token to consume', example: '1efb1c4e-0f7a-6c1a-9b2f-0242ac120002')]
    #[Assert\Type('string')]
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\NotNull]
    public string $token;
}
