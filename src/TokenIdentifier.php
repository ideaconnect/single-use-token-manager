<?php

declare(strict_types=1);

namespace Praetorian\TokenService;

use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

class TokenIdentifier
{
    #[SerializedName('token')]
    #[OA\Schema(type: 'string')]
    #[Assert\Type('string')]
    #[Assert\NotBlank()]
    #[Assert\NotNull()]
    public string $token;
}
