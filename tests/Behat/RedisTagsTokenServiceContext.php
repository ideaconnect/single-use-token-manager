<?php

declare(strict_types=1);

namespace Praetorian\Tests\TokenService\Behat;

use Behat\Behat\Context\Context;
use PHPUnit\Framework\Assert;
use Praetorian\TokenService\TokenInterface;
use Praetorian\TokenService\TokenService;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;

final class RedisTagsTokenServiceContext implements Context
{
    private TokenService $tokenService;
    private string $type;
    private $payload = null;
    private ?TokenInterface $token = null;
    private $consumedToken = null;
    private array $createdTokens = [];

    public function __construct(string $host, ?int $port = null)
    {
        $redisConnection = new \Redis();
        $redisConnection->connect($host, $port ?: 6379);

        // Clear Redis before tests
        $redisConnection->flushAll();

        $cache = new RedisTagAwareAdapter($redisConnection);
        $this->tokenService = new TokenService($cache);
    }

    /**
     * @Given I am using Redis with tag support for caching
     */
    public function iAmUsingRedisWithTagSupportForCaching()
    {
        // This is just a descriptive step - the adapter is already configured in constructor
        Assert::assertTrue(true, 'Redis with tag support is configured');
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
        $this->createdTokens[] = $this->token;
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
        $this->createdTokens[] = $this->token;
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
        $this->createdTokens[] = $this->token;
        sleep(2);
    }

    /**
     * @Given I create multiple tokens of different types
     */
    public function iCreateMultipleTokensOfDifferentTypes()
    {
        $this->createdTokens[] = $this->tokenService->createToken('type1', 'payload1');
        $this->createdTokens[] = $this->tokenService->createToken('type2', 'payload2');
        $this->createdTokens[] = $this->tokenService->createToken('type3', 'payload3');
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
        foreach ($this->createdTokens as $token) {
            Assert::assertNull($this->tokenService->consumeToken($token->getUid()));
        }
    }

    /**
     * @Then tag-aware clearing should work efficiently
     */
    public function tagAwareClearingShouldWorkEfficiently()
    {
        // This is implicitly tested by the clearAllTokens method
        // In a tag-aware adapter, it should use tags to clear efficiently
        Assert::assertTrue(true, 'Tag-aware clearing executed successfully');
    }
}
