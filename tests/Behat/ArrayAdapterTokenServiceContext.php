<?php

declare(strict_types=1);

namespace GryfOSS\Tests\SingleUseTokenManager\Behat;

use Behat\Behat\Context\Context;
use PHPUnit\Framework\Assert;
use GryfOSS\SingleUseTokenManager\TokenInterface;
use GryfOSS\SingleUseTokenManager\TokenService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ArrayAdapterTokenServiceContext implements Context
{
    private TokenService $tokenService;
    private string $type;
    private $payload = null;
    private ?TokenInterface $token = null;
    private $consumedToken = null;

    public function __construct()
    {
        $cache = new ArrayAdapter();
        $this->tokenService = new TokenService($cache);
    }

    /**
     * @Given I am using ArrayAdapter for caching
     */
    public function iAmUsingArrayAdapterForCaching()
    {
        // This is just a descriptive step - the adapter is already configured in constructor
        Assert::assertTrue(true, 'ArrayAdapter is configured');
    }

    /**
     * @Given I have type :arg1 and payload :arg2
     */
    public function iHaveTypeAndPayload(string $type, $payload)
    {
        $this->type = $type;
        $this->payload = $payload;
    }

    /**
     * @When I create token using type and payload
     */
    public function iCreateTokenUsingTypeAndPayload()
    {
        $this->token = $this->tokenService->createToken($this->type, $this->payload);
    }

    /**
     * @Then I should have token created
     */
    public function iShouldHaveTokenCreated()
    {
        Assert::assertInstanceOf(TokenInterface::class, $this->token);
    }

    /**
     * @Given I create token with type :arg1 and payload :arg2
     */
    public function iCreateTokenWithTypeAndPayload($arg1, $arg2)
    {
        $this->token = $this->tokenService->createToken($arg1, $arg2);
    }

    /**
     * @When I consume token with empty uid
     */
    public function iConsumeTokenWithEmptyUid()
    {
        $this->consumedToken = $this->tokenService->consumeToken('x');
    }

    /**
     * @Then I should have null returned instead of consumed token
     */
    public function iShouldHaveNullReturnedInsteadOfConsumedToken()
    {
        Assert::assertNull($this->consumedToken);
    }

    /**
     * @When I consume token with uid from token
     */
    public function iConsumeTokenWithUidFromToken()
    {
        $this->consumedToken = $this->tokenService->consumeToken($this->token->getUid());
    }

    /**
     * @Then I should have instance of TokenInterface returned and removed from cache
     */
    public function iShouldHaveInstanceOfTokeninterfaceReturnedAndRemovedFromCache()
    {
        Assert::assertInstanceOf(TokenInterface::class, $this->consumedToken);
        Assert::assertNull($this->tokenService->consumeToken($this->token->getUid()));
    }

    /**
     * @When I consume token with uid from token and keep token :arg1
     */
    public function iConsumeTokenWithUidFromTokenAndKeepToken(bool $arg1)
    {
        $this->consumedToken = $this->tokenService->consumeToken($this->token->getUid(), $arg1);
    }

    /**
     * @Then I should have instance of TokenInterface returned and kept in cache
     */
    public function iShouldHaveInstanceOfTokeninterfaceReturnedAndKeptInCache()
    {
        Assert::assertInstanceOf(TokenInterface::class, $this->consumedToken);
        Assert::assertNotNull($this->tokenService->consumeToken($this->token->getUid()));
    }

    /**
     * @Given I create token with type :arg1 and payload :arg2 and ttl :arg3
     */
    public function iCreateTokenWithTypeAndPayloadAndTtl($arg1, $arg2, $arg3)
    {
        $this->token = $this->tokenService->createToken($arg1, $arg2, (int) $arg3);
        sleep(2);
    }

    /**
     * @When I clear all tokens
     */
    public function iClearAllTokens()
    {
        $this->tokenService->clearAllTokens();
    }

    /**
     * @Then all tokens should be cleared from cache
     */
    public function allTokensShouldBeClearedFromCache()
    {
        Assert::assertNull($this->tokenService->consumeToken($this->token->getUid()));
    }
}
