<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Behat;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use IDCT\SingleUseTokenManager\Model\Token;
use PHPUnit\Framework\Assert;

/**
 * Drives the token model on its own, with no cache involved.
 *
 * Covers what a token guarantees the moment it is constructed: a unique
 * identifier, the type and payload it was handed, and a refusal to exist at all
 * when the type is not acceptable.
 */
final class TokenContext implements Context
{
    private string $type = '';

    private mixed $payload = null;

    private ?Token $token = null;

    private ?Token $otherToken = null;

    private ?\InvalidArgumentException $refusal = null;

    /**
     * Resets the state so scenarios cannot leak into one another.
     */
    #[BeforeScenario]
    public function reset(): void
    {
        $this->type = '';
        $this->payload = null;
        $this->token = null;
        $this->otherToken = null;
        $this->refusal = null;
    }

    #[Given('a token type of :type')]
    public function aTokenTypeOf(string $type): void
    {
        $this->type = $type;
    }

    #[Given('a payload of :payload')]
    public function aPayloadOf(string $payload): void
    {
        $this->payload = $payload;
    }

    #[Given('no payload')]
    public function noPayload(): void
    {
        $this->payload = null;
    }

    #[When('I construct the token')]
    public function iConstructTheToken(): void
    {
        try {
            $this->token = new Token($this->type, $this->payload);
        } catch (\InvalidArgumentException $exception) {
            $this->refusal = $exception;
        }
    }

    #[When('I construct a second token of the same type')]
    public function iConstructASecondTokenOfTheSameType(): void
    {
        $this->otherToken = new Token($this->type, $this->payload);
    }

    #[Then('I should get a token')]
    public function iShouldGetAToken(): void
    {
        Assert::assertInstanceOf(Token::class, $this->token);
    }

    #[Then('I should not get a token')]
    public function iShouldNotGetAToken(): void
    {
        Assert::assertNull($this->token);
    }

    #[Then('the construction should be refused')]
    public function theConstructionShouldBeRefused(): void
    {
        Assert::assertInstanceOf(\InvalidArgumentException::class, $this->refusal);
    }

    #[Then('the refusal should name the rejected type')]
    public function theRefusalShouldNameTheRejectedType(): void
    {
        Assert::assertNotNull($this->refusal);
        Assert::assertSame(sprintf(Token::TYPE_ERROR, $this->type), $this->refusal->getMessage());
    }

    #[Then('the token type should be :type')]
    public function theTokenTypeShouldBe(string $type): void
    {
        Assert::assertNotNull($this->token);
        Assert::assertSame($type, $this->token->getType());
    }

    #[Then('the token payload should be :payload')]
    public function theTokenPayloadShouldBe(string $payload): void
    {
        Assert::assertNotNull($this->token);
        Assert::assertSame($payload, $this->token->getPayload());
    }

    #[Then('the token should carry no payload')]
    public function theTokenPayloadShouldBeEmpty(): void
    {
        Assert::assertNotNull($this->token);
        Assert::assertNull($this->token->getPayload());
    }

    #[Then('the token identifier should be a version 6 uuid')]
    public function theTokenIdentifierShouldBeAVersionSixUuid(): void
    {
        Assert::assertNotNull($this->token);
        Assert::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-6[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $this->token->getUid(),
        );
    }

    #[Then('the two tokens should have different identifiers')]
    public function theTwoTokensShouldHaveDifferentIdentifiers(): void
    {
        Assert::assertNotNull($this->token);
        Assert::assertNotNull($this->otherToken);
        Assert::assertNotSame($this->token->getUid(), $this->otherToken->getUid());
    }
}
