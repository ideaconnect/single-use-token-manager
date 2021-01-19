<?php

namespace Praetorian\TokenService;

interface TokenInterface
{
    /**
     * Returns the unique identifier in GUID format.
     *
     * @return string
     */
    public function getUid(): string;

    /**
     * Retruns the type (value used when creating the token).
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Returns any payload associated with the token (for instance user).
     *
     * May be null
     *
     * @return null|mixed
     */
    public function getPayload();
}
