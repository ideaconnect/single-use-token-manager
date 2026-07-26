<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Unit;

use IDCT\SingleUseTokenManager\Contract\TokenInterface;
use IDCT\SingleUseTokenManager\Model\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

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

    public function testItReturnsTheStringFormOfTheUuidItGenerated(): void
    {
        $token = new Token('sometest');

        self::assertSame((string) Uuid::fromString($token->getUid()), $token->getUid());
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

    public function testItKeepsASuppliedIdentifierVerbatim(): void
    {
        $token = new Token('sometest', null, 'reset.42');

        self::assertSame('reset.42', $token->getUid());
    }

    /**
     * A supplied identifier is the whole point of the feature, so it has to win
     * over the generated one rather than being merged with it or appended to
     * a prefix the caller cannot see.
     */
    public function testASuppliedIdentifierReplacesTheGeneratedOne(): void
    {
        $token = new Token('sometest', null, 'reset.42');

        self::assertDoesNotMatchRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/',
            $token->getUid(),
        );
    }

    /**
     * Two tokens built with the same identifier are the same entry as far as
     * any cache is concerned. The model does not police that — it cannot see
     * the cache — so it must at least not quietly make them different.
     */
    public function testTwoTokensMayShareASuppliedIdentifier(): void
    {
        self::assertSame(
            (new Token('sometest', 'first', 'reset.42'))->getUid(),
            (new Token('sometest', 'second', 'reset.42'))->getUid(),
        );
    }

    public function testAnExplicitNullIdentifierStillGeneratesOne(): void
    {
        $token = new Token('sometest', null, null);

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/',
            $token->getUid(),
        );
    }

    /**
     * Identifiers the cache would refuse are caught here instead, where the
     * caller can still see which value caused it.
     *
     * @return iterable<string, array{string}>
     */
    public static function rejectedIdentifierProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'opening brace' => ['reset{42'];
        yield 'closing brace' => ['reset}42'];
        yield 'opening parenthesis' => ['reset(42'];
        yield 'closing parenthesis' => ['reset)42'];
        yield 'forward slash' => ['reset/42'];
        yield 'backslash' => ['reset\\42'];
        yield 'at sign' => ['reset@42'];
        yield 'colon' => ['reset:42'];
    }

    #[DataProvider('rejectedIdentifierProvider')]
    public function testItRejectsAnUnusableIdentifier(string $uid): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(Token::UID_ERROR, Token::RESERVED_UID_CHARS, $uid));

        new Token('sometest', null, $uid);
    }

    /**
     * Everything outside the reserved set is the caller's business, including
     * the dot and dash the library's own UUID identifiers already rely on.
     *
     * @return iterable<string, array{string}>
     */
    public static function acceptedIdentifierProvider(): iterable
    {
        yield 'single character' => ['a'];
        yield 'dotted segments' => ['reset.42'];
        yield 'dashed' => ['reset-42'];
        yield 'underscored' => ['reset_42'];
        yield 'uuid shaped' => ['3f2504e0-4f89-41d3-9a0c-0305e82c3301'];
        yield 'uppercase' => ['RESET42'];
        yield 'long' => [str_repeat('a', 512)];
    }

    #[DataProvider('acceptedIdentifierProvider')]
    public function testItAcceptsAUsableIdentifier(string $uid): void
    {
        self::assertSame($uid, (new Token('sometest', null, $uid))->getUid());
    }

    /**
     * The type is checked before the identifier, so a caller who got both
     * wrong is told about the type first and does not have to fix them one
     * round trip at a time in an order the library never documented.
     */
    public function testItReportsAnUnusableTypeBeforeAnUnusableIdentifier(): void
    {
        $this->expectExceptionMessage(sprintf(Token::TYPE_ERROR, 'Reset'));

        new Token('Reset', null, '');
    }
}
