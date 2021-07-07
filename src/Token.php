<?php

declare(strict_types=1);

namespace Praetorian\TokenService;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV6;

final class Token implements TokenInterface
{
    const TYPE_ERROR = 'Type must be a not empty, only alphanumeric string not longer than 16 symbols. Used `%s`.';

    private UuidV6 $uid;

    /**
     * Creates a new immutable token.
     *
     * @param string     $type
     * @param mixed|null $payload
     *
     * @throws InvalidArgumentException
     *
     * @return Token
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
     * {@inheritdoc}
     */
    public function getUid(): string
    {
        return (string) $this->uid;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getPayload()
    {
        return $this->payload;
    }
}
