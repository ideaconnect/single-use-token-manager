<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Unit;

use IDCT\SingleUseTokenManager\Contract\TokenInterface;
use IDCT\SingleUseTokenManager\Model\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV6;

/**
 * Unit tests for the immutable token model.
 */
#[CoversClass(Token::class)]
final class TokenTest extends TestCase
{
    public function testItImplementsTheTokenContract(): void
    {
        $this->assertInstanceOf(TokenInterface::class, new Token('sometest'));
    }

    public function testItKeepsTheTypeItWasBuiltWith(): void
    {
        $token = new Token('sometest');

        $this->assertSame('sometest', $token->getType());
    }

    public function testItKeepsTheObjectPayloadItWasBuiltWith(): void
    {
        $payload = new \stdClass();
        $payload->data = 'value';

        $token = new Token('sometest', $payload);

        $this->assertSame($payload, $token->getPayload());
    }

    /**
     * The payload is typed `mixed`, so anything serialisable has to survive the
     * round trip unchanged, including values that are easy to confuse with
     * "no payload at all".
     *
     * @return iterable<string, array{mixed}>
     */
    public static function payloadProvider(): iterable
    {
        yield 'string' => ['payload'];
        yield 'integer' => [42];
        yield 'zero' => [0];
        yield 'float' => [1.5];
        yield 'boolean false' => [false];
        yield 'empty string' => [''];
        yield 'empty array' => [[]];
        yield 'nested array' => [['user' => ['id' => 7, 'roles' => ['admin']]]];
    }

    #[DataProvider('payloadProvider')]
    public function testItKeepsAnyPayloadUnchanged(mixed $payload): void
    {
        $token = new Token('sometest', $payload);

        $this->assertSame($payload, $token->getPayload());
    }

    public function testItDefaultsToAnEmptyPayload(): void
    {
        $token = new Token('sometest');

        $this->assertNull($token->getPayload());
    }

    public function testItReturnsTheUidAsAString(): void
    {
        $token = new Token('sometest');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-6[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $token->getUid(),
        );
    }

    public function testItReturnsTheStringFormOfItsOwnUuid(): void
    {
        $token = new Token('sometest');
        $uuid = new UuidV6();

        $reflectionProperty = (new \ReflectionClass(Token::class))->getProperty('uid');
        $reflectionProperty->setValue($token, $uuid);

        $this->assertSame((string) $uuid, $token->getUid());
    }

    public function testItGivesEveryTokenItsOwnUid(): void
    {
        $first = new Token('sometest');
        $second = new Token('sometest');

        $this->assertNotSame($first->getUid(), $second->getUid());
    }

    public function testItUsesTimeOrderedIdentifiers(): void
    {
        $first = new Token('sometest');
        $second = new Token('sometest');

        $this->assertLessThan(0, strcmp($first->getUid(), $second->getUid()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedTypeProvider(): iterable
    {
        yield 'single letter' => ['a'];
        yield 'single digit' => ['1'];
        yield 'letters and digits' => ['reset2fa'];
        yield 'exactly sixteen characters' => ['abcdefghij123456'];
    }

    #[DataProvider('acceptedTypeProvider')]
    public function testItAcceptsAValidType(string $type): void
    {
        $this->assertSame($type, (new Token($type))->getType());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedTypeProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'seventeen characters' => ['abcdefghij1234567'];
        yield 'uppercase' => ['Reset'];
        yield 'underscore' => ['pass_reset'];
        yield 'dash' => ['pass-reset'];
        yield 'space' => ['pass reset'];
        yield 'punctuation' => ['_!@$aaa'];
        yield 'multibyte' => ['zażółć'];
        yield 'trailing newline' => ["reset\n"];
        yield 'leading newline' => ["\nreset"];
        yield 'embedded null byte' => ["reset\0x"];
    }

    #[DataProvider('rejectedTypeProvider')]
    public function testItRejectsAnInvalidType(string $type): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(Token::TYPE_ERROR, $type));

        new Token($type);
    }

    /**
     * The pattern is anchored with `\z` rather than `$` on purpose, since `$`
     * also matches just before a trailing newline. A type ending in one has to
     * be rejected like any other value carrying a forbidden character.
     */
    public function testItRejectsATypeEndingInANewline(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Token("reset\n");
    }

    public function testItNamesTheRejectedTypeInTheErrorMessage(): void
    {
        $this->expectExceptionMessage('Used `Reset`.');

        new Token('Reset');
    }
}
