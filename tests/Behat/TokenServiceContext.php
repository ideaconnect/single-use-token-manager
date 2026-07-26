<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Behat;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use IDCT\Cache\RapidCacheClient;
use IDCT\Cache\RedisConnectionConfig;
use IDCT\SingleUseTokenManager\Contract\TokenInterface;
use IDCT\SingleUseTokenManager\TokenService;
use IDCT\Tests\SingleUseTokenManager\Double\RedisGetDelCache;
use PHPUnit\Framework\Assert;
use Psr\SimpleCache\CacheInterface;
use Redis;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * Drives the token service against whichever cache the Behat suite selected.
 *
 * Every scenario in `features/service` runs unchanged against all three
 * backends, which is the point: the service is supposed to need nothing beyond
 * PSR-16, and the only observable difference between a tagging cache and a
 * plain one is what survives a call to clearAllTokens().
 */
final class TokenServiceContext implements Context
{
    /** @var string In-memory cache, needs no server */
    public const DRIVER_ARRAY = 'array';

    /** @var string Symfony Redis adapter wrapped as PSR-16, no tagging */
    public const DRIVER_REDIS = 'redis';

    /** @var string Rapid cache client, PSR-16 with tagging */
    public const DRIVER_RAPID_CACHE = 'rapid_cache';

    /** @var string Redis behind a GETDEL backed cache, PSR-16 with atomic take */
    public const DRIVER_REDIS_ATOMIC = 'redis_atomic';

    /** @var string Key prefix keeping the suites from treading on each other */
    private const KEY_PREFIX = 'sutm_behat_';

    /** @var int How many times an expiring token is looked for before giving up */
    private const EXPIRY_ATTEMPTS = 150;

    /** @var int Pause between two attempts, in microseconds */
    private const EXPIRY_POLL_MICROSECONDS = 100_000;

    private CacheInterface $cache;

    private TokenService $service;

    /** @var array<int, TokenInterface> Tokens created during the scenario, in order */
    private array $tokens = [];

    private ?TokenInterface $redeemed = null;

    private ?\InvalidArgumentException $refusal = null;

    /**
     * @param string $driver one of the DRIVER_* constants, chosen per suite in behat.yml
     * @param string $host   hostname of the Redis or Valkey server, ignored by the array driver
     * @param int    $port   port of the Redis or Valkey server, ignored by the array driver
     */
    public function __construct(
        private readonly string $driver = self::DRIVER_ARRAY,
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 6379,
    ) {
    }

    /**
     * Gives every scenario a clean cache and a service wired to it.
     */
    #[BeforeScenario]
    public function prepareService(): void
    {
        $this->cache = $this->buildCache();
        $this->cache->clear();
        $this->service = new TokenService($this->cache);
        $this->tokens = [];
        $this->redeemed = null;
        $this->refusal = null;
    }

    #[Given('the cache also holds an unrelated entry :key')]
    public function theCacheAlsoHoldsAnUnrelatedEntry(string $key): void
    {
        Assert::assertTrue($this->cache->set($key, 'unrelated value'));
    }

    #[When('I create a token of type :type')]
    #[Given('I have created a token of type :type')]
    public function iCreateATokenOfType(string $type): void
    {
        $this->tokens[] = $this->service->createToken($type);
    }

    #[When('I create a token of type :type with payload :payload')]
    #[Given('I have created a token of type :type with payload :payload')]
    public function iCreateATokenOfTypeWithPayload(string $type, string $payload): void
    {
        $this->tokens[] = $this->service->createToken($type, $payload);
    }

    #[Given('I have created a token of type :type carrying a user identifier of :id')]
    public function iHaveCreatedATokenCarryingAUserIdentifier(string $type, int $id): void
    {
        $this->tokens[] = $this->service->createToken($type, ['user' => ['id' => $id]]);
    }

    #[When('I create a token of type :type lasting :ttl second(s)')]
    #[Given('I have created a token of type :type lasting :ttl second(s)')]
    public function iCreateATokenLasting(string $type, int $ttl): void
    {
        $this->tokens[] = $this->service->createToken($type, 'payload', $ttl);
    }

    #[When('I create a token of type :type identified by :uid')]
    #[Given('I have created a token of type :type identified by :uid')]
    public function iCreateATokenIdentifiedBy(string $type, string $uid): void
    {
        $this->tokens[] = $this->service->createToken($type, null, null, $uid);
    }

    #[When('I create a token of type :type identified by :uid with payload :payload')]
    #[Given('I have created a token of type :type identified by :uid with payload :payload')]
    public function iCreateATokenIdentifiedByWithPayload(string $type, string $uid, string $payload): void
    {
        $this->tokens[] = $this->service->createToken($type, $payload, null, $uid);
    }

    #[When('I create a token of type :type identified by the invalid identifier :uid')]
    public function iCreateATokenOfTheInvalidIdentifier(string $type, string $uid): void
    {
        try {
            $this->tokens[] = $this->service->createToken($type, null, null, $uid);
        } catch (\InvalidArgumentException $exception) {
            $this->refusal = $exception;
        }
    }

    #[Given('I have created :count tokens of type :type')]
    public function iHaveCreatedSeveralTokens(int $count, string $type): void
    {
        for ($index = 0; $index < $count; ++$index) {
            $this->tokens[] = $this->service->createToken($type);
        }
    }

    #[When('I create a token of the invalid type :type')]
    public function iCreateATokenOfTheInvalidType(string $type): void
    {
        try {
            $this->tokens[] = $this->service->createToken($type);
        } catch (\InvalidArgumentException $exception) {
            $this->refusal = $exception;
        }
    }

    #[When('I redeem the token')]
    public function iRedeemTheToken(): void
    {
        $this->redeemed = $this->service->consumeToken($this->lastToken()->getUid());
    }

    #[When('I redeem the token keeping it in the cache')]
    public function iRedeemTheTokenKeepingIt(): void
    {
        $this->redeemed = $this->service->consumeToken($this->lastToken()->getUid(), true);
    }

    #[When('I redeem the identifier :uid')]
    public function iRedeemTheIdentifier(string $uid): void
    {
        $this->redeemed = $this->service->consumeToken($uid);
    }

    #[When('I clear all tokens')]
    public function iClearAllTokens(): void
    {
        Assert::assertTrue($this->service->clearAllTokens(), 'Clearing the tokens reported failure.');
    }

    /**
     * Waits for the cache to drop the token, polling rather than sleeping.
     *
     * A fixed sleep would have to be longer than the lifetime plus whatever
     * slack the machine needs, and it would still be at the mercy of a wall
     * clock that steps backwards, which happens on virtualised hosts. Polling
     * finishes as soon as the entry is actually gone, and the bound is a
     * number of attempts rather than a deadline, so a clock that moves in
     * either direction cannot cut the wait short.
     */
    #[When('I wait for the token to expire')]
    public function iWaitForTheTokenToExpire(): void
    {
        $uid = $this->lastToken()->getUid();

        for ($attempt = 0; $attempt < self::EXPIRY_ATTEMPTS; ++$attempt) {
            if (null === $this->service->consumeToken($uid, true)) {
                return;
            }

            usleep(self::EXPIRY_POLL_MICROSECONDS);
        }

        Assert::fail(sprintf(
            'Token %s was still in the cache after %d seconds, so its lifetime was not applied.',
            $uid,
            intdiv(self::EXPIRY_ATTEMPTS * self::EXPIRY_POLL_MICROSECONDS, 1_000_000),
        ));
    }

    #[Then('I should get a token')]
    public function iShouldGetAToken(): void
    {
        // lastToken() already fails the step when nothing was created, so the
        // assertion worth making here is that the token is usable.
        Assert::assertNotSame('', $this->lastToken()->getUid());
    }

    #[Then('the token identifier should be a version 4 uuid')]
    public function theTokenIdentifierShouldBeAVersionFourUuid(): void
    {
        Assert::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/',
            $this->lastToken()->getUid(),
        );
    }

    #[Then('the token identifier should be :uid')]
    public function theTokenIdentifierShouldBe(string $uid): void
    {
        Assert::assertSame($uid, $this->lastToken()->getUid());
    }

    #[Then('the redeemed token identifier should be :uid')]
    public function theRedeemedTokenIdentifierShouldBe(string $uid): void
    {
        Assert::assertNotNull($this->redeemed);
        Assert::assertSame($uid, $this->redeemed->getUid());
    }

    #[Then('every token identifier should be different')]
    public function everyTokenIdentifierShouldBeDifferent(): void
    {
        $identifiers = array_map(static fn (TokenInterface $token): string => $token->getUid(), $this->tokens);

        Assert::assertSameSize($identifiers, array_unique($identifiers));
    }

    #[Then('the creation should be refused')]
    public function theCreationShouldBeRefused(): void
    {
        Assert::assertInstanceOf(\InvalidArgumentException::class, $this->refusal);
        Assert::assertSame([], $this->tokens);
    }

    #[Then('I should get the token back')]
    public function iShouldGetTheTokenBack(): void
    {
        Assert::assertInstanceOf(TokenInterface::class, $this->redeemed);
        Assert::assertSame($this->lastToken()->getUid(), $this->redeemed->getUid());
    }

    #[Then('I should get nothing back')]
    public function iShouldGetNothingBack(): void
    {
        Assert::assertNull($this->redeemed);
    }

    #[Then('the redeemed token should be of type :type')]
    public function theRedeemedTokenShouldBeOfType(string $type): void
    {
        Assert::assertNotNull($this->redeemed);
        Assert::assertSame($type, $this->redeemed->getType());
    }

    #[Then('the redeemed token should carry the payload :payload')]
    public function theRedeemedTokenShouldCarryThePayload(string $payload): void
    {
        Assert::assertNotNull($this->redeemed);
        Assert::assertSame($payload, $this->redeemed->getPayload());
    }

    #[Then('the redeemed token should carry no payload')]
    public function theRedeemedTokenShouldCarryNoPayload(): void
    {
        Assert::assertNotNull($this->redeemed);
        Assert::assertNull($this->redeemed->getPayload());
    }

    #[Then('the redeemed token should carry a user identifier of :id')]
    public function theRedeemedTokenShouldCarryAUserIdentifier(int $id): void
    {
        Assert::assertNotNull($this->redeemed);
        Assert::assertSame(['user' => ['id' => $id]], $this->redeemed->getPayload());
    }

    #[Then('the token should no longer be redeemable')]
    public function theTokenShouldNoLongerBeRedeemable(): void
    {
        Assert::assertNull($this->service->consumeToken($this->lastToken()->getUid()));
    }

    #[Then('the token should still be redeemable')]
    public function theTokenShouldStillBeRedeemable(): void
    {
        Assert::assertInstanceOf(TokenInterface::class, $this->service->consumeToken($this->lastToken()->getUid(), true));
    }

    #[Then('no token should be redeemable')]
    public function noTokenShouldBeRedeemable(): void
    {
        Assert::assertNotSame([], $this->tokens, 'The scenario created no tokens to check.');

        foreach ($this->tokens as $token) {
            Assert::assertNull(
                $this->service->consumeToken($token->getUid()),
                sprintf('Token %s survived the clearing.', $token->getUid()),
            );
        }
    }

    #[Then('the unrelated entry :key should still be there')]
    public function theUnrelatedEntryShouldStillBeThere(string $key): void
    {
        Assert::assertSame('unrelated value', $this->cache->get($key));
    }

    #[Then('the unrelated entry :key should be gone')]
    public function theUnrelatedEntryShouldBeGone(string $key): void
    {
        Assert::assertNull($this->cache->get($key));
    }

    /**
     * Builds the cache the suite asked for.
     *
     * @throws \InvalidArgumentException when the suite names a driver that does not exist
     */
    private function buildCache(): CacheInterface
    {
        return match ($this->driver) {
            self::DRIVER_ARRAY => new Psr16Cache(new ArrayAdapter()),
            self::DRIVER_REDIS => new Psr16Cache(new RedisAdapter($this->connectRedis(), self::KEY_PREFIX)),
            self::DRIVER_REDIS_ATOMIC => new RedisGetDelCache($this->host, $this->port, self::KEY_PREFIX),
            self::DRIVER_RAPID_CACHE => new RapidCacheClient(new RedisConnectionConfig(
                host: $this->host,
                port: $this->port,
                prefix: self::KEY_PREFIX,
            )),
            default => throw new \InvalidArgumentException(sprintf('Unknown cache driver `%s`.', $this->driver)),
        };
    }

    /**
     * Opens the phpredis connection the Symfony adapter is built on.
     */
    private function connectRedis(): \Redis
    {
        $redis = new \Redis();
        $redis->connect($this->host, $this->port);

        return $redis;
    }

    /**
     * Returns the token the scenario created most recently.
     */
    private function lastToken(): TokenInterface
    {
        Assert::assertNotSame([], $this->tokens, 'The scenario has not created a token yet.');

        return $this->tokens[array_key_last($this->tokens)];
    }
}
