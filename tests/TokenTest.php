<?php

declare(strict_types=1);

namespace Praetorian\Tests\TokenService;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Praetorian\TokenService\Token;
use ReflectionClass;
use Symfony\Component\Uid\UuidV6;

final class TokenTest extends TestCase
{
    use \phpmock\phpunit\PHPMock;
    const TESTED_CLASS = Token::class;

    public function testConstructorWithPayload(): void
    {
        $payload = new \stdClass();
        $payload->data = mt_rand(100, 200);

        $token = new Token('sometest', $payload);

        $this->assertEquals('sometest', $token->getType());
        $this->assertSame($payload, $token->getPayload());
    }

    public function testConstructorWithoutPayload(): void
    {
        $token = new Token('sometest');
        $this->assertEquals('sometest', $token->getType());
    }

    public function testGetUid(): void
    {
        $token = new Token('sometest');

        $uuid = new UuidV6();

        $reflectionClass = new ReflectionClass(static::TESTED_CLASS);
        $reflectionProperty = $reflectionClass->getProperty('uid');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($token, $uuid);
        $this->assertEquals((string) $uuid, $token->getUid());
    }

    public function testEmptyType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $token = new Token('');
    }

    public function testTooLongType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $token = new Token('909999999999999999999999999');
    }

    public function testInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $token = new Token('_!@$aaa');
    }
}
