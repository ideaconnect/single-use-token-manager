<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Unit;

use IDCT\SingleUseTokenManager\Contract\TaggedCacheInterface;
use IDCT\SingleUseTokenManager\Contract\TokenInterface;
use IDCT\SingleUseTokenManager\Contract\TokenServiceInterface;
use IDCT\SingleUseTokenManager\Exception\TokenStorageException;
use IDCT\SingleUseTokenManager\Model\Token;
use IDCT\SingleUseTokenManager\TokenService;
use IDCT\Tests\SingleUseTokenManager\Double\ClearByTagOnlyCache;
use IDCT\Tests\SingleUseTokenManager\Double\CustomisedTokenService;
use IDCT\Tests\SingleUseTokenManager\Double\DuckTypedTaggedCache;
use IDCT\Tests\SingleUseTokenManager\Double\InMemoryCache;
use IDCT\Tests\SingleUseTokenManager\Double\SetTaggedOnlyCache;
use IDCT\Tests\SingleUseTokenManager\Double\TaggedInMemoryCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

/**
 * Unit tests for the PSR-16 backed token service.
 */
#[CoversClass(TokenService::class)]
#[CoversClass(TokenStorageException::class)]
#[UsesClass(Token::class)]
final class TokenServiceTest extends TestCase
{
    public function testItKeepsTheCacheItWasConstructedWith(): void
    {
        $cache = new InMemoryCache();
        $service = new TokenService($cache);

        $this->assertSame($cache, $this->callProtected($service, 'getCache'));
    }

    public function testItImplementsTheServiceContract(): void
    {
        $this->assertInstanceOf(TokenServiceInterface::class, new TokenService(new InMemoryCache()));
    }

    public function testItPrefixesTheUidToBuildTheCacheKey(): void
    {
        $service = new TokenService(new InMemoryCache());

        $this->assertSame(
            'TKN_test-uid-123',
            $this->callProtected($service, 'buildKey', 'test-uid-123'),
        );
    }

    public function testTheCacheKeyPrefixIsTheDeclaredConstant(): void
    {
        $service = new TokenService(new InMemoryCache());

        $this->assertSame(
            TokenServiceInterface::CACHE_KEY.'uid',
            $this->callProtected($service, 'buildKey', 'uid'),
        );
    }

    public function testItCreatesATokenOfTheGivenType(): void
    {
        $service = new TokenService(new InMemoryCache());

        $token = $service->createToken('testtype');

        $this->assertInstanceOf(TokenInterface::class, $token);
        $this->assertSame('testtype', $token->getType());
        $this->assertNull($token->getPayload());
    }

    public function testItCreatesATokenCarryingThePayload(): void
    {
        $payload = new \stdClass();
        $payload->test = 'abc';
        $service = new TokenService(new InMemoryCache());

        $token = $service->createToken('testtype', $payload);

        $this->assertSame($payload, $token->getPayload());
    }

    public function testItStoresTheTokenUnderItsPrefixedKey(): void
    {
        $cache = new InMemoryCache();
        $service = new TokenService($cache);

        $token = $service->createToken('testtype');

        $write = $cache->lastWrite();
        $this->assertNotNull($write);
        $this->assertSame('TKN_'.$token->getUid(), $write['key']);
        $this->assertSame($token, $write['value']);
    }

    public function testItStoresTheTokenWithoutATtlByDefault(): void
    {
        $cache = new InMemoryCache();

        (new TokenService($cache))->createToken('testtype');

        $write = $cache->lastWrite();
        $this->assertNotNull($write);
        $this->assertNull($write['ttl']);
    }

    public function testItPassesTheGivenTtlToTheCache(): void
    {
        $cache = new InMemoryCache();

        (new TokenService($cache))->createToken('testtype', 'payload', 150);

        $write = $cache->lastWrite();
        $this->assertNotNull($write);
        $this->assertSame(150, $write['ttl']);
    }

    public function testItDoesNotTagOnAPlainCache(): void
    {
        $cache = new InMemoryCache();

        (new TokenService($cache))->createToken('testtype');

        $write = $cache->lastWrite();
        $this->assertNotNull($write);
        $this->assertNull($write['tag']);
    }

    public function testItTagsTheTokenOnATaggingCache(): void
    {
        $cache = new TaggedInMemoryCache();

        $token = (new TokenService($cache))->createToken('testtype');

        $write = $cache->lastWrite();
        $this->assertNotNull($write);
        $this->assertSame(TokenServiceInterface::CACHE_TAG, $write['tag']);
        $this->assertSame(['TKN_'.$token->getUid()], $cache->keysTaggedWith(TokenServiceInterface::CACHE_TAG));
    }

    public function testItPassesTheTtlThroughTheTaggedWriteAsWell(): void
    {
        $cache = new TaggedInMemoryCache();

        (new TokenService($cache))->createToken('testtype', null, 900);

        $write = $cache->lastWrite();
        $this->assertNotNull($write);
        $this->assertSame(900, $write['ttl']);
    }

    public function testItRejectsAnInvalidTypeBeforeTouchingTheCache(): void
    {
        $cache = new InMemoryCache();

        try {
            (new TokenService($cache))->createToken('NOT VALID');
            $this->fail('An invalid token type should have been rejected.');
        } catch (\InvalidArgumentException) {
            $this->assertSame([], $cache->writes());
        }
    }

    public function testItThrowsWhenAPlainCacheRefusesTheWrite(): void
    {
        $cache = new InMemoryCache();
        $cache->failWrites();

        $this->expectException(TokenStorageException::class);
        $this->expectExceptionMessageMatches('/^The cache refused to store the token under key `TKN_/');

        (new TokenService($cache))->createToken('testtype');
    }

    public function testItThrowsWhenATaggingCacheRefusesTheWrite(): void
    {
        $cache = new TaggedInMemoryCache();
        $cache->failWrites();

        $this->expectException(TokenStorageException::class);

        (new TokenService($cache))->createToken('testtype');
    }

    public function testTheStorageFailureNamesTheRejectedKey(): void
    {
        $exception = TokenStorageException::forKey('TKN_abc');

        $this->assertSame(sprintf(TokenStorageException::MESSAGE, 'TKN_abc'), $exception->getMessage());
    }

    public function testItReturnsTheTokenWhenTheCacheAcceptsTheWrite(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('set')->willReturn(true);

        $this->assertInstanceOf(TokenInterface::class, (new TokenService($cache))->createToken('testtype'));
    }

    public function testItReadsTheTokenBackUnderItsPrefixedKey(): void
    {
        $token = new Token('testtype');
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with('TKN_'.$token->getUid())
            ->willReturn($token);

        $this->assertSame($token, (new TokenService($cache))->consumeToken($token->getUid()));
    }

    public function testItRemovesTheTokenOnceRedeemed(): void
    {
        $cache = new InMemoryCache();
        $service = new TokenService($cache);
        $token = $service->createToken('testtype');

        $this->assertSame($token->getUid(), $service->consumeToken($token->getUid())?->getUid());
        $this->assertNull($service->consumeToken($token->getUid()));
    }

    public function testItDeletesExactlyTheRedeemedKey(): void
    {
        $token = new Token('testtype');
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn($token);
        $cache->expects($this->once())
            ->method('delete')
            ->with('TKN_'.$token->getUid());

        (new TokenService($cache))->consumeToken($token->getUid());
    }

    public function testItKeepsTheTokenWhenAskedTo(): void
    {
        $cache = new InMemoryCache();
        $service = new TokenService($cache);
        $token = $service->createToken('testtype');

        $this->assertNotNull($service->consumeToken($token->getUid(), true));
        $this->assertNotNull($service->consumeToken($token->getUid(), true));
        $this->assertNotNull($service->consumeToken($token->getUid()));
    }

    public function testItDoesNotDeleteAnythingWhenKeepingTheToken(): void
    {
        $token = new Token('testtype');
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn($token);
        $cache->expects($this->never())->method('delete');

        $this->assertSame($token, (new TokenService($cache))->consumeToken($token->getUid(), true));
    }

    public function testItReturnsNullForAnUnknownIdentifier(): void
    {
        $service = new TokenService(new InMemoryCache());

        $this->assertNull($service->consumeToken('no-such-uid'));
    }

    public function testItDoesNotDeleteAnythingForAnUnknownIdentifier(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->never())->method('delete');

        $this->assertNull((new TokenService($cache))->consumeToken('no-such-uid'));
    }

    /**
     * A cache pool shared with the rest of an application can return anything,
     * including a value someone else stored under a colliding key. Only an
     * actual token may be handed back.
     *
     * @return iterable<string, array{mixed}>
     */
    public static function nonTokenCacheValueProvider(): iterable
    {
        yield 'string' => ['not a token'];
        yield 'integer' => [42];
        yield 'array' => [['type' => 'testtype']];
        yield 'unrelated object' => [new \stdClass()];
        yield 'boolean' => [true];
    }

    #[DataProvider('nonTokenCacheValueProvider')]
    public function testItReturnsNullWhenTheCachedValueIsNotAToken(mixed $cached): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn($cached);
        $cache->expects($this->never())->method('delete');

        $this->assertNull((new TokenService($cache))->consumeToken('some-uid'));
    }

    public function testItClearsTheWholePoolOnAPlainCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('clear')->willReturn(true);

        $this->assertTrue((new TokenService($cache))->clearAllTokens());
    }

    public function testItReportsAFailedPoolClear(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('clear')->willReturn(false);

        $this->assertFalse((new TokenService($cache))->clearAllTokens());
    }

    public function testItClearsByTagOnATaggingCache(): void
    {
        $cache = $this->createMock(TaggedCacheInterface::class);
        $cache->expects($this->once())
            ->method('clearByTag')
            ->with(TokenServiceInterface::CACHE_TAG)
            ->willReturn(true);
        $cache->expects($this->never())->method('clear');

        $this->assertTrue((new TokenService($cache))->clearAllTokens());
    }

    public function testItReportsAFailedTagClear(): void
    {
        $cache = new TaggedInMemoryCache();
        $cache->failClearByTag();

        $this->assertFalse((new TokenService($cache))->clearAllTokens());
    }

    public function testTagClearingLeavesUntaggedEntriesAlone(): void
    {
        $cache = new TaggedInMemoryCache();
        $cache->set('unrelated', 'value');
        $service = new TokenService($cache);
        $token = $service->createToken('testtype');

        $this->assertTrue($service->clearAllTokens());
        $this->assertNull($service->consumeToken($token->getUid()));
        $this->assertSame('value', $cache->get('unrelated'));
    }

    public function testPoolClearingTakesUnrelatedEntriesWithIt(): void
    {
        $cache = new InMemoryCache();
        $cache->set('unrelated', 'value');
        $service = new TokenService($cache);
        $token = $service->createToken('testtype');

        $this->assertTrue($service->clearAllTokens());
        $this->assertNull($service->consumeToken($token->getUid()));
        $this->assertNull($cache->get('unrelated'));
    }

    /**
     * @return iterable<string, array{CacheInterface, bool}>
     */
    public static function taggingSupportProvider(): iterable
    {
        yield 'plain PSR-16 cache' => [new InMemoryCache(), false];
        yield 'cache implementing the contract' => [new TaggedInMemoryCache(), true];
        yield 'cache exposing both methods' => [new DuckTypedTaggedCache(), true];
        yield 'cache exposing only setTagged' => [new SetTaggedOnlyCache(), false];
        yield 'cache exposing only clearByTag' => [new ClearByTagOnlyCache(), false];
    }

    #[DataProvider('taggingSupportProvider')]
    public function testItDetectsTaggingSupport(CacheInterface $cache, bool $expected): void
    {
        $service = new TokenService($cache);

        $this->assertSame($expected, $this->callProtected($service, 'supportsTagging', $cache));
    }

    public function testItTagsOnACacheThatOnlyLooksTheTaggingPart(): void
    {
        $cache = new DuckTypedTaggedCache();
        $service = new TokenService($cache);

        $token = $service->createToken('testtype');

        $write = $cache->lastWrite();
        $this->assertNotNull($write);
        $this->assertSame(TokenServiceInterface::CACHE_TAG, $write['tag']);
        $this->assertSame(['TKN_'.$token->getUid()], $cache->keysTaggedWith(TokenServiceInterface::CACHE_TAG));
    }

    public function testItFallsBackToPlainWritesWhenOnlyHalfTheTaggingApiIsThere(): void
    {
        $cache = new SetTaggedOnlyCache();

        (new TokenService($cache))->createToken('testtype');

        $write = $cache->lastWrite();
        $this->assertNotNull($write);
        $this->assertNull($write['tag']);
    }

    /**
     * Full round trip over a cache that has no idea what a token is, proving
     * the service needs nothing beyond PSR-16 to work.
     */
    public function testItRoundTripsOverAPlainPsr16Cache(): void
    {
        $service = new TokenService(new InMemoryCache());

        $token = $service->createToken('session', ['user' => 7], 3600);
        $consumed = $service->consumeToken($token->getUid());

        $this->assertNotNull($consumed);
        $this->assertSame($token->getUid(), $consumed->getUid());
        $this->assertSame('session', $consumed->getType());
        $this->assertSame(['user' => 7], $consumed->getPayload());
        $this->assertNull($service->consumeToken($token->getUid()));
    }

    public function testASubclassCanSubstituteTheCache(): void
    {
        $constructed = new InMemoryCache();
        $substituted = new InMemoryCache();
        $service = new CustomisedTokenService($constructed, $substituted);

        $service->createToken('testtype');

        $this->assertSame(0, $constructed->count());
        $this->assertSame(1, $substituted->count());
    }

    public function testASubclassCanChangeTheCacheKey(): void
    {
        $cache = new InMemoryCache();
        $service = new CustomisedTokenService($cache, $cache);

        $token = $service->createToken('testtype');

        $write = $cache->lastWrite();
        $this->assertNotNull($write);
        $this->assertSame(CustomisedTokenService::CUSTOM_PREFIX.$token->getUid(), $write['key']);
        $this->assertNotNull($service->consumeToken($token->getUid()));
    }

    public function testASubclassCanTurnTaggingOff(): void
    {
        $cache = new TaggedInMemoryCache();
        $service = new CustomisedTokenService($cache, $cache);

        $service->createToken('testtype');

        $write = $cache->lastWrite();
        $this->assertNotNull($write);
        $this->assertNull($write['tag']);
        $this->assertSame([], $cache->keysTaggedWith(TokenServiceInterface::CACHE_TAG));
    }

    public function testASubclassCanTurnTaggingOn(): void
    {
        $cache = new TaggedInMemoryCache();
        $service = new CustomisedTokenService($cache, $cache, true);

        $token = $service->createToken('testtype');

        $write = $cache->lastWrite();
        $this->assertNotNull($write);
        $this->assertSame(TokenServiceInterface::CACHE_TAG, $write['tag']);
        $this->assertTrue($service->clearAllTokens());
        $this->assertNull($service->consumeToken($token->getUid()));
    }

    /**
     * Invokes a protected method of the service.
     */
    private function callProtected(TokenService $service, string $method, mixed ...$arguments): mixed
    {
        return (new \ReflectionMethod(TokenService::class, $method))->invoke($service, ...$arguments);
    }
}
