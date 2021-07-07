<?php

declare(strict_types=1);

namespace Praetorian\TokenService;

interface TokenInterface
{
    /**
     * Returns the unique identifier in GUID format.
     */
    public function getUid(): string;

    /**
     * Retruns the type (value used when creating the token).
     */
    public function getType(): string;

    /**
     * Returns any payload associated with the token (for instance user).
     *
     * May be null
     *
     * @return mixed|null
     */
    public function getPayload();
}
