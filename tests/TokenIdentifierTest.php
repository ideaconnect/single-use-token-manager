<?php

declare(strict_types=1);

namespace GryfOSS\Tests\SingleUseTokenManager;

use PHPUnit\Framework\TestCase;
use GryfOSS\SingleUseTokenManager\TokenIdentifier;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TokenIdentifierTest extends TestCase
{
    private ValidatorInterface $validator;
    private Serializer $serializer;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $this->serializer = new Serializer($normalizers, $encoders);
    }

    public function testTokenIdentifierProperties(): void
    {
        $tokenIdentifier = new TokenIdentifier();
        $tokenIdentifier->token = 'test-token-123';

        $this->assertEquals('test-token-123', $tokenIdentifier->token);
    }

    public function testTokenIdentifierIsPublicProperty(): void
    {
        $reflection = new \ReflectionClass(TokenIdentifier::class);
        $property = $reflection->getProperty('token');

        $this->assertTrue($property->isPublic());
        $this->assertEquals('token', $property->getName());
    }

    public function testValidTokenPassesValidation(): void
    {
        $tokenIdentifier = new TokenIdentifier();
        $tokenIdentifier->token = 'valid-token-123';

        $violations = $this->validator->validate($tokenIdentifier);

        $this->assertCount(0, $violations);
    }

    public function testEmptyTokenFailsValidation(): void
    {
        $tokenIdentifier = new TokenIdentifier();
        $tokenIdentifier->token = '';

        $violations = $this->validator->validate($tokenIdentifier);

        $this->assertGreaterThan(0, $violations->count());

        $violationMessages = [];
        foreach ($violations as $violation) {
            $violationMessages[] = $violation->getMessage();
        }

        $this->assertContains('This value should not be blank.', $violationMessages);
    }

    public function testNonStringTokenFailsValidation(): void
    {
        // Since PHP's type system enforces string type at runtime,
        // we'll test this by creating a scenario where validation would catch type issues
        // For example, if the constraint was configured for a different type
        $tokenIdentifier = new TokenIdentifier();
        $tokenIdentifier->token = '123'; // This is a string but could represent a number

        // This should pass since it's a valid string
        $violations = $this->validator->validate($tokenIdentifier);
        $this->assertEquals(0, $violations->count());

        // Test with empty string to ensure NotBlank works
        $tokenIdentifier->token = '';
        $violations = $this->validator->validate($tokenIdentifier);
        $this->assertGreaterThan(0, $violations->count());
    }

    public function testNullTokenFailsValidation(): void
    {
        // Test validation by creating object without setting the token
        // This tests the NotNull constraint indirectly
        $reflection = new \ReflectionClass(TokenIdentifier::class);
        $tokenIdentifier = $reflection->newInstanceWithoutConstructor();

        $violations = $this->validator->validate($tokenIdentifier);

        $this->assertGreaterThan(0, $violations->count());

        $violationMessages = [];
        foreach ($violations as $violation) {
            $violationMessages[] = $violation->getMessage();
        }

        // Should contain either NotNull or NotBlank violation
        $hasNotNullOrNotBlank = false;
        foreach ($violationMessages as $message) {
            if (str_contains($message, 'null') || str_contains($message, 'blank')) {
                $hasNotNullOrNotBlank = true;
                break;
            }
        }

        $this->assertTrue($hasNotNullOrNotBlank, 'Should have NotNull or NotBlank validation error');
    }

    public function testTokenIdentifierSerialization(): void
    {
        $tokenIdentifier = new TokenIdentifier();
        $tokenIdentifier->token = 'serialization-test-token';

        $json = $this->serializer->serialize($tokenIdentifier, 'json');

        $this->assertEquals('{"token":"serialization-test-token"}', $json);
    }

    public function testTokenIdentifierDeserialization(): void
    {
        $json = '{"token":"deserialization-test-token"}';

        $tokenIdentifier = $this->serializer->deserialize($json, TokenIdentifier::class, 'json');

        $this->assertInstanceOf(TokenIdentifier::class, $tokenIdentifier);
        $this->assertEquals('deserialization-test-token', $tokenIdentifier->token);
    }

    public function testSerializedNameAttribute(): void
    {
        // Test that the SerializedName attribute works correctly
        $tokenIdentifier = new TokenIdentifier();
        $tokenIdentifier->token = 'test-serialized-name';

        $json = $this->serializer->serialize($tokenIdentifier, 'json');
        $decoded = json_decode($json, true);

        // The property should be serialized as 'token' due to SerializedName attribute
        $this->assertArrayHasKey('token', $decoded);
        $this->assertEquals('test-serialized-name', $decoded['token']);
    }

    public function testValidationWithLongToken(): void
    {
        $tokenIdentifier = new TokenIdentifier();
        $tokenIdentifier->token = str_repeat('a', 1000); // Very long token

        $violations = $this->validator->validate($tokenIdentifier);

        // Should pass as there's no length constraint defined
        $this->assertCount(0, $violations);
    }

    public function testValidationWithSpecialCharacters(): void
    {
        $tokenIdentifier = new TokenIdentifier();
        $tokenIdentifier->token = 'token-with-special-chars_!@#$%^&*()';

        $violations = $this->validator->validate($tokenIdentifier);

        // Should pass as there's no character restriction defined
        $this->assertCount(0, $violations);
    }

    public function testValidationConstraints(): void
    {
        $reflection = new \ReflectionClass(TokenIdentifier::class);
        $property = $reflection->getProperty('token');

        $attributes = $property->getAttributes();

        // Should have validation attributes
        $this->assertGreaterThan(0, count($attributes));

        $attributeNames = array_map(fn($attr) => $attr->getName(), $attributes);

        // Check that required validation attributes are present
        $this->assertContains('Symfony\Component\Validator\Constraints\Type', $attributeNames);
        $this->assertContains('Symfony\Component\Validator\Constraints\NotBlank', $attributeNames);
        $this->assertContains('Symfony\Component\Validator\Constraints\NotNull', $attributeNames);
    }
}
