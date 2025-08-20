<?php

declare(strict_types=1);

namespace GryfOSS\Tests\SingleUseTokenManager\Behat;

use Behat\Behat\Context\Context;
use PHPUnit\Framework\Assert;
use GryfOSS\SingleUseTokenManager\Token;

final class TokenContext implements Context
{
    private string $type;

    private $payload = null;

    private ?Token $token = null;

    /**
     * @Given I have type :arg1 with empty payload
     */
    public function iHaveTypeWithEmptyPayload(string $type)
    {
        $this->type = $type;
    }

    /**
     * @When I construct token with :arg1
     */
    public function iConstructTokenWithType(string $type)
    {
        $this->token = new Token($this->type);
    }

    /**
     * @Then I should have uid returned
     */
    public function iShouldHaveUidReturned()
    {
        Assert::assertIsString($this->token->getUid());
    }

    /**
     * @Then I should have empty payload
     */
    public function iShouldHaveEmptyPayload()
    {
        Assert::assertNull($this->token->getPayload());
    }

    /**
     * @Given I have empty type with payload :arg1
     */
    public function iHaveEmptyTypeWithPayload($payload)
    {
        $this->payload = $payload;
    }

    /**
     * @When I construct token with empty type
     */
    public function iConstructTokenWithEmptyType()
    {
        try {
            $this->token = new Token('', $this->payload);
        } catch (\Exception $exception) {
        }
    }

    /**
     * @Then I should not get token
     */
    public function iShouldNotGetToken()
    {
        Assert::assertNull($this->token);
    }

    /**
     * @Given I have type :arg1 with payload :arg2
     */
    public function iHaveTypeWithPayload(string $type, $payload)
    {
        $this->type = $type;
        $this->payload = $payload;
    }

    /**
     * @When I construct token with type :arg1 and payload :arg2
     */
    public function iConstructTokenWithTypeAndPayload(string $type, $payload)
    {
        $this->token = new Token($type, $payload);
    }

    /**
     * @Then I should have value under type and payload
     */
    public function iShouldHaveValueUnderTypeAndPayload()
    {
        Assert::assertIsString($this->token->getType());
        Assert::assertIsString($this->token->getPayload());
    }
}
