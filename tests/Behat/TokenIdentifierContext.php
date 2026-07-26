<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Behat;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use IDCT\SingleUseTokenManager\Model\TokenIdentifier;
use PHPUnit\Framework\Assert;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Drives the request object an HTTP endpoint would hydrate before redeeming.
 *
 * The scenarios describe the journey a token identifier makes through an API:
 * arriving as JSON, being validated, and going back out as JSON.
 */
final class TokenIdentifierContext implements Context
{
    private ValidatorInterface $validator;

    private Serializer $serializer;

    private ?TokenIdentifier $identifier = null;

    private ?ConstraintViolationListInterface $violations = null;

    private string $json = '';

    /**
     * Builds a fresh validator, serialiser and blank state for each scenario.
     */
    #[BeforeScenario]
    public function prepare(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $metadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $this->serializer = new Serializer(
            [new ObjectNormalizer($metadataFactory, new MetadataAwareNameConverter($metadataFactory))],
            [new JsonEncoder()],
        );

        $this->identifier = null;
        $this->violations = null;
        $this->json = '';
    }

    #[Given('an identifier holding :token')]
    public function anIdentifierHolding(string $token): void
    {
        $this->identifier = new TokenIdentifier();
        $this->identifier->token = $token;
    }

    #[Given('an identifier of whitespace only')]
    public function anIdentifierHoldingASingleSpace(): void
    {
        $this->anIdentifierHolding(' ');
    }

    #[Given('an empty identifier')]
    public function anIdentifierHoldingNothing(): void
    {
        $this->anIdentifierHolding('');
    }

    #[Given('an identifier that was never filled in')]
    public function anIdentifierThatWasNeverFilledIn(): void
    {
        $this->identifier = new TokenIdentifier();
    }

    #[When('I validate it')]
    public function iValidateIt(): void
    {
        Assert::assertNotNull($this->identifier);
        $this->violations = $this->validator->validate($this->identifier);
    }

    #[When('I serialise it')]
    public function iSerialiseIt(): void
    {
        Assert::assertNotNull($this->identifier);
        $this->json = $this->serializer->serialize($this->identifier, 'json');
    }

    #[When('I deserialise the request body :json')]
    public function iDeserialiseTheRequestBody(string $json): void
    {
        $identifier = $this->serializer->deserialize($json, TokenIdentifier::class, 'json');

        Assert::assertInstanceOf(TokenIdentifier::class, $identifier);
        $this->identifier = $identifier;
    }

    #[Then('it should be accepted')]
    public function itShouldBeAccepted(): void
    {
        Assert::assertNotNull($this->violations);
        Assert::assertCount(0, $this->violations);
    }

    #[Then('it should be rejected')]
    public function itShouldBeRejected(): void
    {
        Assert::assertNotNull($this->violations);
        Assert::assertGreaterThan(0, $this->violations->count());
    }

    #[Then('the complaint should be about the token property')]
    public function theComplaintShouldBeAboutTheTokenProperty(): void
    {
        Assert::assertNotNull($this->violations);
        Assert::assertGreaterThan(0, $this->violations->count());
        Assert::assertSame('token', $this->violations->get(0)->getPropertyPath());
    }

    #[Then('the json should be :json')]
    public function theJsonShouldBe(string $json): void
    {
        Assert::assertSame($json, $this->json);
    }

    #[Then('the identifier should hold :token')]
    public function theIdentifierShouldHold(string $token): void
    {
        Assert::assertNotNull($this->identifier);
        Assert::assertSame($token, $this->identifier->token);
    }
}
