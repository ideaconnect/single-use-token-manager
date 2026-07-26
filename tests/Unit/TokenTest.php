<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Unit;

use IDCT\SingleUseTokenManager\Contract\TokenInterface;
use IDCT\SingleUseTokenManager\Model\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV4;

/**
 * Unit tests for the immutable token model.
 */
#[CoversClass(Token::class)]
final class TokenTest extends TestCase
{
    /**
     * Asked through reflection rather than with assertInstanceOf, because the
     * declared return types already prove the latter and a statically certain
     * assertion documents nothing a reader could not see.
     */
    public function testItImplementsTheTokenContract(): void
    {
        self::assertTrue(
            (new \ReflectionClass(Token::class))->implementsInterface(TokenInterface::class),
        );
    }

    public function testItKeepsTheTypeItWasBuiltWith(): void
    {
        $token = new Token('sometest');

        self::assertSame('sometest', $token->getType());
    }

    public function testItKeepsTheObjectPayloadItWasBuiltWith(): void
    {
        $payload = new \stdClass();
        $payload->data = 'value';

        $token = new Token('sometest', $payload);

        self::assertSame($payload, $token->getPayload());
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

        self::assertSame($payload, $token->getPayload());
    }

    public function testItDefaultsToAnEmptyPayload(): void
    {
        $token = new Token('sometest');

        self::assertNull($token->getPayload());
    }

    public function testItReturnsTheUidAsAString(): void
    {
        $token = new Token('sometest');

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/',
            $token->getUid(),
        );
    }

    public function testItReturnsTheStringFormOfItsOwnUuid(): void
    {
        $token = new Token('sometest');
        $uuid = new UuidV4();

        $reflectionProperty = (new \ReflectionClass(Token::class))->getProperty('uid');
        $reflectionProperty->setValue($token, $uuid);

        self::assertSame((string) $uuid, $token->getUid());
    }

    public function testItGivesEveryTokenItsOwnUid(): void
    {
        $identifiers = [];
        for ($i = 0; $i < 1000; ++$i) {
            $identifiers[] = (new Token('sometest'))->getUid();
        }

        self::assertCount(1000, array_unique($identifiers));
    }

    /**
     * The regression guard for the identifier being a bearer secret.
     *
     * The previous UUID v6 identifiers carried a node that Symfony computes
     * once per process and then repeats, so every token a process issued ended
     * with the same twelve characters and one leaked token gave that away for
     * all of them. Under the old behaviour this assertion sees a single
     * distinct suffix and fails; under a CSPRNG it sees essentially as many as
     * there are tokens.
     *
     * The bar is deliberately loose rather than demanding perfect uniqueness,
     * because random collisions are possible and a flaky security test gets
     * ignored. Anything short of "the suffix is effectively constant" passes.
     */
    public function testItDoesNotRepeatAFixedSuffixAcrossTokens(): void
    {
        $suffixes = [];
        for ($i = 0; $i < 200; ++$i) {
            $suffixes[] = substr((new Token('sometest'))->getUid(), -12);
        }

        self::assertGreaterThan(190, count(array_unique($suffixes)));
    }

    /**
     * Identifiers must not be derivable from when they were issued, so two
     * tokens created back to back should not reliably sort in creation order.
     * A time ordered identifier does exactly that, which is why this package
     * does not use one.
     */
    public function testItDoesNotIssueTimeOrderedIdentifiers(): void
    {
        $ascending = 0;
        for ($i = 0; $i < 100; ++$i) {
            $first = (new Token('sometest'))->getUid();
            $second = (new Token('sometest'))->getUid();

            if (strcmp($first, $second) < 0) {
                ++$ascending;
            }
        }

        // A time ordered source gives 100. Random gives roughly half, so a
        // generous band still fails loudly if ordering ever comes back.
        self::assertGreaterThan(20, $ascending);
        self::assertLessThan(80, $ascending);
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
        self::assertSame($type, (new Token($type))->getType());
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
