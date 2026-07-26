<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Unit;

use IDCT\SingleUseTokenManager\Model\TokenIdentifier;
use OpenApi\Attributes\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Unit tests for the token identifier request object.
 */
#[CoversClass(TokenIdentifier::class)]
final class TokenIdentifierTest extends TestCase
{
    private ValidatorInterface $validator;

    private Serializer $serializer;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $metadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $this->serializer = new Serializer(
            [new ObjectNormalizer($metadataFactory, new MetadataAwareNameConverter($metadataFactory))],
            [new JsonEncoder()],
        );
    }

    public function testItHoldsTheIdentifierItWasGiven(): void
    {
        $identifier = new TokenIdentifier();
        $identifier->token = 'test-token-123';

        $this->assertSame('test-token-123', $identifier->token);
    }

    public function testTheIdentifierIsAPublicStringProperty(): void
    {
        $property = new \ReflectionProperty(TokenIdentifier::class, 'token');

        $this->assertTrue($property->isPublic());
        $this->assertSame('string', (string) $property->getType());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedIdentifierProvider(): iterable
    {
        yield 'uuid' => ['1efb1c4e-0f7a-6c1a-9b2f-0242ac120002'];
        yield 'plain word' => ['token'];
        yield 'digits only' => ['123'];
        yield 'punctuation' => ['token-with-special-chars_!@#$%^&*()'];
        yield 'very long value' => [str_repeat('a', 1000)];
    }

    #[DataProvider('acceptedIdentifierProvider')]
    public function testItAcceptsAnyNonEmptyIdentifier(string $token): void
    {
        $identifier = new TokenIdentifier();
        $identifier->token = $token;

        $this->assertCount(0, $this->validator->validate($identifier));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedIdentifierProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'single space' => [' '];
        yield 'whitespace only' => ["\t\n "];
    }

    #[DataProvider('rejectedIdentifierProvider')]
    public function testItRejectsABlankIdentifier(string $token): void
    {
        $identifier = new TokenIdentifier();
        $identifier->token = $token;

        $violations = $this->validator->validate($identifier);

        $this->assertGreaterThan(0, $violations->count());
        $this->assertSame('token', $violations->get(0)->getPropertyPath());
    }

    public function testItReportsTheBlankIdentifierWithTheStandardMessage(): void
    {
        $identifier = new TokenIdentifier();
        $identifier->token = '';

        $messages = [];
        foreach ($this->validator->validate($identifier) as $violation) {
            $messages[] = $violation->getMessage();
        }

        $this->assertContains('This value should not be blank.', $messages);
    }

    /**
     * A typed property that was never assigned is uninitialised rather than
     * null, which is the state an object hydrated from an empty request body
     * ends up in.
     */
    public function testItRejectsAnIdentifierThatWasNeverSet(): void
    {
        $identifier = (new \ReflectionClass(TokenIdentifier::class))->newInstanceWithoutConstructor();

        $violations = $this->validator->validate($identifier);

        $this->assertGreaterThan(0, $violations->count());
    }

    public function testItSerialisesTheIdentifierAsToken(): void
    {
        $identifier = new TokenIdentifier();
        $identifier->token = 'serialisation-test-token';

        $this->assertSame(
            '{"token":"serialisation-test-token"}',
            $this->serializer->serialize($identifier, 'json'),
        );
    }

    public function testItDeserialisesTheIdentifierFromToken(): void
    {
        $identifier = $this->serializer->deserialize(
            '{"token":"deserialisation-test-token"}',
            TokenIdentifier::class,
            'json',
        );

        $this->assertInstanceOf(TokenIdentifier::class, $identifier);
        $this->assertSame('deserialisation-test-token', $identifier->token);
    }

    public function testItSurvivesASerialisationRoundTrip(): void
    {
        $identifier = new TokenIdentifier();
        $identifier->token = 'round-trip';

        $restored = $this->serializer->deserialize(
            $this->serializer->serialize($identifier, 'json'),
            TokenIdentifier::class,
            'json',
        );

        $this->assertInstanceOf(TokenIdentifier::class, $restored);
        $this->assertSame($identifier->token, $restored->token);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function expectedAttributeProvider(): iterable
    {
        yield 'validator type' => [Type::class];
        yield 'validator not blank' => [NotBlank::class];
        yield 'validator not null' => [NotNull::class];
        yield 'serializer name' => [SerializedName::class];
        yield 'openapi schema' => [Schema::class];
    }

    /**
     * @param class-string $attribute
     */
    #[DataProvider('expectedAttributeProvider')]
    public function testTheIdentifierCarriesTheExpectedAttribute(string $attribute): void
    {
        $property = new \ReflectionProperty(TokenIdentifier::class, 'token');
        $names = array_map(
            static fn (\ReflectionAttribute $reflected): string => $reflected->getName(),
            $property->getAttributes(),
        );

        $this->assertContains($attribute, $names);
    }

    public function testEveryAttributeOnTheIdentifierCanBeInstantiated(): void
    {
        $property = new \ReflectionProperty(TokenIdentifier::class, 'token');

        foreach ($property->getAttributes() as $attribute) {
            $this->assertIsObject($attribute->newInstance());
        }
    }
}
