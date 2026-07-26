<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Unit;

use IDCT\SingleUseTokenManager\Contract\AtomicCacheInterface;
use IDCT\SingleUseTokenManager\Contract\TaggedCacheInterface;
use IDCT\SingleUseTokenManager\Contract\TokenServiceInterface;
use IDCT\SingleUseTokenManager\Exception\TokenRemovalException;
use IDCT\SingleUseTokenManager\Exception\TokenStorageException;
use IDCT\SingleUseTokenManager\Model\Token;
use IDCT\SingleUseTokenManager\TokenService;
use IDCT\Tests\SingleUseTokenManager\Double\AtomicInMemoryCache;
use IDCT\Tests\SingleUseTokenManager\Double\ClearByTagOnlyCache;
use IDCT\Tests\SingleUseTokenManager\Double\CustomisedTokenService;
use IDCT\Tests\SingleUseTokenManager\Double\DuckTypedAtomicCache;
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
#[CoversClass(TokenRemovalException::class)]
#[CoversClass(TokenStorageException::class)]
#[UsesClass(Token::class)]
final class TokenServiceTest extends TestCase
{
    public function testItKeepsTheCacheItWasConstructedWith(): void
    {
        $cache = new InMemoryCache();
        $service = new TokenService($cache);

        self::assertSame($cache, $this->callProtected($service, 'getCache'));
    }

    /**
     * Asked through reflection rather than with assertInstanceOf, because the
     * declared types already prove the latter.
     */
    public function testItImplementsTheServiceContract(): void
    {
        self::assertTrue(
            (new \ReflectionClass(TokenService::class))->implementsInterface(TokenServiceInterface::class),
        );
    }

    public function testItPrefixesTheUidToBuildTheCacheKey(): void
    {
        $service = new TokenService(new InMemoryCache());

        self::assertSame(
            'TKN_test-uid-123',
            $this->callProtected($service, 'buildKey', 'test-uid-123'),
        );
    }

    public function testTheCacheKeyPrefixIsTheDeclaredConstant(): void
    {
        $service = new TokenService(new InMemoryCache());

        self::assertSame(
            TokenServiceInterface::CACHE_KEY.'uid',
            $this->callProtected($service, 'buildKey', 'uid'),
        );
    }

    public function testItNamespacesTheCacheKeyWhenAsked(): void
    {
        $service = new TokenService(new InMemoryCache(), 'tenant7_');

        self::assertSame('tenant7_TKN_uid', $this->callProtected($service, 'buildKey', 'uid'));
    }

    public function testItNamespacesTheTagWhenAsked(): void
    {
        $service = new TokenService(new InMemoryCache(), 'tenant7_');

        self::assertSame('tenant7_TKN', $this->callProtected($service, 'cacheTag'));
    }

    public function testItLeavesKeysAndTagsAloneWithoutANamespace(): void
    {
        $service = new TokenService(new InMemoryCache());

        self::assertSame('TKN_uid', $this->callProtected($service, 'buildKey', 'uid'));
        self::assertSame('TKN', $this->callProtected($service, 'cacheTag'));
    }

    /**
     * Two services sharing one pool must not see each other's tokens, which is
     * the whole point of the namespace.
     */
    public function testTokensOfOneNamespaceAreInvisibleToAnother(): void
    {
        $cache = new InMemoryCache();
        $first = new TokenService($cache, 'first_');
        $second = new TokenService($cache, 'second_');

        $token = $first->createToken('testtype');

        self::assertNull($second->consumeToken($token->getUid()));
        self::assertNotNull($first->consumeToken($token->getUid()));
    }

    public function testClearingOneNamespaceLeavesTheOtherIntact(): void
    {
        $cache = new TaggedInMemoryCache();
        $first = new TokenService($cache, 'first_');
        $second = new TokenService($cache, 'second_');

        $kept = $second->createToken('testtype');
        $first->createToken('testtype');

        self::assertTrue($first->clearAllTokens());
        self::assertNotNull($second->consumeToken($kept->getUid()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function reservedNamespaceProvider(): iterable
    {
        yield 'brace' => ['ten{ant'];
        yield 'parenthesis' => ['ten(ant'];
        yield 'slash' => ['ten/ant'];
        yield 'backslash' => ['ten\\ant'];
        yield 'at sign' => ['ten@ant'];
        yield 'colon' => ['ten:ant'];
    }

    #[DataProvider('reservedNamespaceProvider')]
    public function testItRefusesANamespaceCarryingAReservedCharacter(string $namespace): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            TokenService::NAMESPACE_ERROR,
            TokenService::RESERVED_NAMESPACE_CHARS,
            $namespace,
        ));

        new TokenService(new InMemoryCache(), $namespace);
    }

    public function testItAcceptsANamespaceOfOrdinaryCharacters(): void
    {
        $service = new TokenService(new InMemoryCache(), 'tenant-7.eu_');

        self::assertSame('tenant-7.eu_TKN_uid', $this->callProtected($service, 'buildKey', 'uid'));
    }

    public function testItCreatesATokenOfTheGivenType(): void
    {
        $service = new TokenService(new InMemoryCache());

        $token = $service->createToken('testtype');

        self::assertSame('testtype', $token->getType());
        self::assertNull($token->getPayload());
        self::assertNotSame('', $token->getUid());
    }

    public function testItCreatesATokenCarryingThePayload(): void
    {
        $payload = new \stdClass();
        $payload->test = 'abc';
        $service = new TokenService(new InMemoryCache());

        $token = $service->createToken('testtype', $payload);

        self::assertSame($payload, $token->getPayload());
    }

    public function testItStoresTheTokenUnderItsPrefixedKey(): void
    {
        $cache = new InMemoryCache();
        $service = new TokenService($cache);

        $token = $service->createToken('testtype');

        $write = $cache->lastWrite();
        self::assertNotNull($write);
        self::assertSame('TKN_'.$token->getUid(), $write['key']);
        self::assertSame($token, $write['value']);
    }

    public function testItStoresTheTokenUnderTheSuppliedIdentifier(): void
    {
        $cache = new InMemoryCache();
        $service = new TokenService($cache);

        $token = $service->createToken('testtype', null, null, 'reset.42');

        self::assertSame('reset.42', $token->getUid());
        $write = $cache->lastWrite();
        self::assertNotNull($write);
        self::assertSame('TKN_reset.42', $write['key']);
    }

    /**
     * The namespace still applies to a supplied identifier, so two services
     * sharing a pool cannot reach each other's tokens just because the caller
     * chose the same identifier in both.
     */
    public function testItNamespacesASuppliedIdentifierToo(): void
    {
        $cache = new InMemoryCache();

        (new TokenService($cache, 'tenant_'))->createToken('testtype', null, null, 'reset.42');

        $write = $cache->lastWrite();
        self::assertNotNull($write);
        self::assertSame('tenant_TKN_reset.42', $write['key']);
    }

    /**
     * A supplied identifier is a slot, not a fresh entry: re-issuing into it is
     * how a flow replaces the token it had in flight.
     */
    public function testReusingASuppliedIdentifierReplacesTheStoredToken(): void
    {
        $cache = new InMemoryCache();
        $service = new TokenService($cache);

        $service->createToken('testtype', 'first', null, 'reset.42');
        $service->createToken('testtype', 'second', null, 'reset.42');

        $redeemed = $service->consumeToken('reset.42');
        self::assertNotNull($redeemed);
        self::assertSame('second', $redeemed->getPayload());
        self::assertNull($service->consumeToken('reset.42'));
    }

    public function testATokenStoredUnderASuppliedIdentifierIsRedeemedByIt(): void
    {
        $service = new TokenService(new InMemoryCache());
        $service->createToken('testtype', 'user-7', null, 'reset.42');

        $redeemed = $service->consumeToken('reset.42');

        self::assertNotNull($redeemed);
        self::assertSame('user-7', $redeemed->getPayload());
        self::assertSame('reset.42', $redeemed->getUid());
    }

    public function testItRejectsAnUnusableIdentifierBeforeTouchingTheCache(): void
    {
        $cache = new InMemoryCache();

        try {
            (new TokenService($cache))->createToken('testtype', null, null, 'reset:42');
            self::fail('An identifier carrying a reserved character should be refused.');
        } catch (\InvalidArgumentException) {
            self::assertNull($cache->lastWrite());
        }
    }

    public function testItPassesTheTtlThroughAlongsideASuppliedIdentifier(): void
    {
        $cache = new InMemoryCache();

        (new TokenService($cache))->createToken('testtype', 'payload', 150, 'reset.42');

        $write = $cache->lastWrite();
        self::assertNotNull($write);
        self::assertSame(150, $write['ttl']);
        self::assertSame('TKN_reset.42', $write['key']);
    }

    public function testItTagsATokenStoredUnderASuppliedIdentifier(): void
    {
        $cache = new TaggedInMemoryCache();

        (new TokenService($cache))->createToken('testtype', null, null, 'reset.42');

        $write = $cache->lastWrite();
        self::assertNotNull($write);
        self::assertSame('TKN_reset.42', $write['key']);
        self::assertSame(TokenServiceInterface::CACHE_TAG, $write['tag']);
        self::assertSame(['TKN_reset.42'], $cache->keysTaggedWith(TokenServiceInterface::CACHE_TAG));
    }

    public function testItStoresTheTokenWithoutATtlByDefault(): void
    {
        $cache = new InMemoryCache();

        (new TokenService($cache))->createToken('testtype');

        $write = $cache->lastWrite();
        self::assertNotNull($write);
        self::assertNull($write['ttl']);
    }

    public function testItPassesTheGivenTtlToTheCache(): void
    {
        $cache = new InMemoryCache();

        (new TokenService($cache))->createToken('testtype', 'payload', 150);

        $write = $cache->lastWrite();
        self::assertNotNull($write);
        self::assertSame(150, $write['ttl']);
    }

    public function testItDoesNotTagOnAPlainCache(): void
    {
        $cache = new InMemoryCache();

        (new TokenService($cache))->createToken('testtype');

        $write = $cache->lastWrite();
        self::assertNotNull($write);
        self::assertNull($write['tag']);
    }

    public function testItTagsTheTokenOnATaggingCache(): void
    {
        $cache = new TaggedInMemoryCache();

        $token = (new TokenService($cache))->createToken('testtype');

        $write = $cache->lastWrite();
        self::assertNotNull($write);
        self::assertSame(TokenServiceInterface::CACHE_TAG, $write['tag']);
        self::assertSame(['TKN_'.$token->getUid()], $cache->keysTaggedWith(TokenServiceInterface::CACHE_TAG));
    }

    public function testItPassesTheTtlThroughTheTaggedWriteAsWell(): void
    {
        $cache = new TaggedInMemoryCache();

        (new TokenService($cache))->createToken('testtype', null, 900);

        $write = $cache->lastWrite();
        self::assertNotNull($write);
        self::assertSame(900, $write['ttl']);
    }

    public function testItRejectsAnInvalidTypeBeforeTouchingTheCache(): void
    {
        $cache = new InMemoryCache();

        try {
            (new TokenService($cache))->createToken('NOT VALID');
            self::fail('An invalid token type should have been rejected.');
        } catch (\InvalidArgumentException) {
            self::assertSame([], $cache->writes());
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

        self::assertSame(sprintf(TokenStorageException::MESSAGE, 'TKN_abc'), $exception->getMessage());
    }

    public function testItReturnsTheTokenWhenTheCacheAcceptsTheWrite(): void
    {
        $cache = self::createStub(CacheInterface::class);
        $cache->method('set')->willReturn(true);

        $token = (new TokenService($cache))->createToken('testtype');

        self::assertSame('testtype', $token->getType());
    }

    public function testItReadsTheTokenBackUnderItsPrefixedKey(): void
    {
        $token = new Token('testtype');
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with('TKN_'.$token->getUid())
            ->willReturn($token);
        $cache->method('delete')->willReturn(true);

        self::assertSame($token, (new TokenService($cache))->consumeToken($token->getUid()));
    }

    public function testItRemovesTheTokenOnceRedeemed(): void
    {
        $cache = new InMemoryCache();
        $service = new TokenService($cache);
        $token = $service->createToken('testtype');

        self::assertSame($token->getUid(), $service->consumeToken($token->getUid())?->getUid());
        self::assertNull($service->consumeToken($token->getUid()));
    }

    public function testItDeletesExactlyTheRedeemedKey(): void
    {
        $token = new Token('testtype');
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn($token);
        $cache->expects($this->once())
            ->method('delete')
            ->with('TKN_'.$token->getUid())
            ->willReturn(true);

        (new TokenService($cache))->consumeToken($token->getUid());
    }

    public function testItKeepsTheTokenWhenAskedTo(): void
    {
        $cache = new InMemoryCache();
        $service = new TokenService($cache);
        $token = $service->createToken('testtype');

        self::assertNotNull($service->consumeToken($token->getUid(), true));
        self::assertNotNull($service->consumeToken($token->getUid(), true));
        self::assertNotNull($service->consumeToken($token->getUid()));
    }

    public function testItDoesNotDeleteAnythingWhenKeepingTheToken(): void
    {
        $token = new Token('testtype');
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn($token);
        $cache->expects($this->never())->method('delete');

        self::assertSame($token, (new TokenService($cache))->consumeToken($token->getUid(), true));
    }

    public function testItTakesAtomicallyWhenTheCacheCan(): void
    {
        $token = new Token('testtype');
        $cache = $this->createMock(AtomicCacheInterface::class);
        $cache->expects($this->once())
            ->method('take')
            ->with('TKN_'.$token->getUid())
            ->willReturn($token);
        $cache->expects($this->never())->method('get');
        $cache->expects($this->never())->method('delete');

        self::assertSame($token, (new TokenService($cache))->consumeToken($token->getUid()));
    }

    public function testItReturnsNullWhenTheAtomicTakeFindsNothing(): void
    {
        $cache = self::createStub(AtomicCacheInterface::class);
        $cache->method('take')->willReturn(null);

        self::assertNull((new TokenService($cache))->consumeToken('no-such-uid'));
    }

    /**
     * The atomic path needs the same guard as the plain one: a shared pool can
     * hand back whatever somebody else stored under a colliding key.
     */
    #[DataProvider('nonTokenCacheValueProvider')]
    public function testItReturnsNullWhenTheAtomicTakeYieldsSomethingElse(mixed $taken): void
    {
        $cache = self::createStub(AtomicCacheInterface::class);
        $cache->method('take')->willReturn($taken);

        self::assertNull((new TokenService($cache))->consumeToken('some-uid'));
    }

    /**
     * Taking would spend the token, which is the opposite of what keepToken
     * asks for, so that mode has to stay on the read-only path.
     */
    public function testItDoesNotTakeWhenKeepingTheToken(): void
    {
        $token = new Token('testtype');
        $cache = $this->createMock(AtomicCacheInterface::class);
        $cache->expects($this->never())->method('take');
        $cache->expects($this->once())->method('get')->willReturn($token);

        self::assertSame($token, (new TokenService($cache))->consumeToken($token->getUid(), true));
    }

    public function testAnAtomicTakeLeavesNothingBehind(): void
    {
        $cache = new AtomicInMemoryCache();
        $service = new TokenService($cache);
        $token = $service->createToken('testtype');

        self::assertNotNull($service->consumeToken($token->getUid()));
        self::assertSame(1, $cache->takeCalls());
        self::assertNull($service->consumeToken($token->getUid()));
    }

    public function testItUsesTakeOnACacheThatOnlyLooksAtomic(): void
    {
        $cache = new DuckTypedAtomicCache();
        $service = new TokenService($cache);
        $token = $service->createToken('testtype');

        self::assertNotNull($service->consumeToken($token->getUid()));
        self::assertSame(1, $cache->takeCalls());
    }

    /**
     * @return iterable<string, array{CacheInterface, bool}>
     */
    public static function atomicSupportProvider(): iterable
    {
        yield 'plain PSR-16 cache' => [new InMemoryCache(), false];
        yield 'cache implementing the contract' => [new AtomicInMemoryCache(), true];
        yield 'cache exposing only the method' => [new DuckTypedAtomicCache(), true];
    }

    #[DataProvider('atomicSupportProvider')]
    public function testItDetectsAtomicTakeSupport(CacheInterface $cache, bool $expected): void
    {
        $service = new TokenService($cache);

        self::assertSame($expected, $this->callProtected($service, 'supportsAtomicTake', $cache));
    }

    public function testItThrowsWhenTheCacheRefusesToRemoveARedeemedToken(): void
    {
        $token = new Token('testtype');
        $cache = self::createStub(CacheInterface::class);
        $cache->method('get')->willReturn($token);
        $cache->method('delete')->willReturn(false);

        $this->expectException(TokenRemovalException::class);
        $this->expectExceptionMessageMatches('/^The cache refused to remove the redeemed token stored under key `TKN_/');

        (new TokenService($cache))->consumeToken($token->getUid());
    }

    public function testTheRemovalFailureNamesTheKeyTheTokenIsStillUnder(): void
    {
        $exception = TokenRemovalException::forKey('TKN_abc');

        self::assertSame(sprintf(TokenRemovalException::MESSAGE, 'TKN_abc'), $exception->getMessage());
    }

    /**
     * A refused delete only matters when the token was meant to be spent.
     */
    public function testItDoesNotThrowOnAFailedDeleteWhenKeepingTheToken(): void
    {
        $token = new Token('testtype');
        $cache = self::createStub(CacheInterface::class);
        $cache->method('get')->willReturn($token);
        $cache->method('delete')->willReturn(false);

        self::assertSame($token, (new TokenService($cache))->consumeToken($token->getUid(), true));
    }

    public function testItReturnsNullForAnUnknownIdentifier(): void
    {
        $service = new TokenService(new InMemoryCache());

        self::assertNull($service->consumeToken('no-such-uid'));
    }

    public function testItDoesNotDeleteAnythingForAnUnknownIdentifier(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->never())->method('delete');

        self::assertNull((new TokenService($cache))->consumeToken('no-such-uid'));
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

        self::assertNull((new TokenService($cache))->consumeToken('some-uid'));
    }

    public function testItClearsTheWholePoolOnAPlainCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('clear')->willReturn(true);

        self::assertTrue((new TokenService($cache))->clearAllTokens());
    }

    public function testItReportsAFailedPoolClear(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('clear')->willReturn(false);

        self::assertFalse((new TokenService($cache))->clearAllTokens());
    }

    public function testItClearsByTagOnATaggingCache(): void
    {
        $cache = $this->createMock(TaggedCacheInterface::class);
        $cache->expects($this->once())
            ->method('clearByTag')
            ->with(TokenServiceInterface::CACHE_TAG)
            ->willReturn(true);
        $cache->expects($this->never())->method('clear');

        self::assertTrue((new TokenService($cache))->clearAllTokens());
    }

    public function testItReportsAFailedTagClear(): void
    {
        $cache = new TaggedInMemoryCache();
        $cache->failClearByTag();

        self::assertFalse((new TokenService($cache))->clearAllTokens());
    }

    public function testTagClearingLeavesUntaggedEntriesAlone(): void
    {
        $cache = new TaggedInMemoryCache();
        $cache->set('unrelated', 'value');
        $service = new TokenService($cache);
        $token = $service->createToken('testtype');

        self::assertTrue($service->clearAllTokens());
        self::assertNull($service->consumeToken($token->getUid()));
        self::assertSame('value', $cache->get('unrelated'));
    }

    public function testPoolClearingTakesUnrelatedEntriesWithIt(): void
    {
        $cache = new InMemoryCache();
        $cache->set('unrelated', 'value');
        $service = new TokenService($cache);
        $token = $service->createToken('testtype');

        self::assertTrue($service->clearAllTokens());
        self::assertNull($service->consumeToken($token->getUid()));
        self::assertNull($cache->get('unrelated'));
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

        self::assertSame($expected, $this->callProtected($service, 'supportsTagging', $cache));
    }

    public function testItTagsOnACacheThatOnlyLooksTheTaggingPart(): void
    {
        $cache = new DuckTypedTaggedCache();
        $service = new TokenService($cache);

        $token = $service->createToken('testtype');

        $write = $cache->lastWrite();
        self::assertNotNull($write);
        self::assertSame(TokenServiceInterface::CACHE_TAG, $write['tag']);
        self::assertSame(['TKN_'.$token->getUid()], $cache->keysTaggedWith(TokenServiceInterface::CACHE_TAG));
    }

    public function testItFallsBackToPlainWritesWhenOnlyHalfTheTaggingApiIsThere(): void
    {
        $cache = new SetTaggedOnlyCache();

        (new TokenService($cache))->createToken('testtype');

        $write = $cache->lastWrite();
        self::assertNotNull($write);
        self::assertNull($write['tag']);
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

        self::assertNotNull($consumed);
        self::assertSame($token->getUid(), $consumed->getUid());
        self::assertSame('session', $consumed->getType());
        self::assertSame(['user' => 7], $consumed->getPayload());
        self::assertNull($service->consumeToken($token->getUid()));
    }

    public function testASubclassCanSubstituteTheCache(): void
    {
        $constructed = new InMemoryCache();
        $substituted = new InMemoryCache();
        $service = new CustomisedTokenService($constructed, $substituted);

        $service->createToken('testtype');

        self::assertSame(0, $constructed->count());
        self::assertSame(1, $substituted->count());
    }

    public function testASubclassCanChangeTheCacheKey(): void
    {
        $cache = new InMemoryCache();
        $service = new CustomisedTokenService($cache, $cache);

        $token = $service->createToken('testtype');

        $write = $cache->lastWrite();
        self::assertNotNull($write);
        self::assertSame(CustomisedTokenService::CUSTOM_PREFIX.$token->getUid(), $write['key']);
        self::assertNotNull($service->consumeToken($token->getUid()));
    }

    public function testASubclassCanTurnTaggingOff(): void
    {
        $cache = new TaggedInMemoryCache();
        $service = new CustomisedTokenService($cache, $cache);

        $service->createToken('testtype');

        $write = $cache->lastWrite();
        self::assertNotNull($write);
        self::assertNull($write['tag']);
        self::assertSame([], $cache->keysTaggedWith(TokenServiceInterface::CACHE_TAG));
    }

    public function testASubclassCanTurnAtomicTakeOff(): void
    {
        $cache = new AtomicInMemoryCache();
        $service = new CustomisedTokenService($cache, $cache);

        $token = $service->createToken('testtype');
        self::assertNotNull($service->consumeToken($token->getUid()));

        self::assertSame(0, $cache->takeCalls());
    }

    public function testASubclassCanTurnAtomicTakeOn(): void
    {
        $cache = new AtomicInMemoryCache();
        $service = new CustomisedTokenService($cache, $cache, false, true);

        $token = $service->createToken('testtype');
        self::assertNotNull($service->consumeToken($token->getUid()));

        self::assertSame(1, $cache->takeCalls());
    }

    public function testASubclassCanTurnTaggingOn(): void
    {
        $cache = new TaggedInMemoryCache();
        $service = new CustomisedTokenService($cache, $cache, true);

        $token = $service->createToken('testtype');

        $write = $cache->lastWrite();
        self::assertNotNull($write);
        self::assertTrue($service->clearAllTokens());
        self::assertNull($service->consumeToken($token->getUid()));
    }

    public function testASubclassCanChangeTheTag(): void
    {
        $cache = new TaggedInMemoryCache();
        $service = new CustomisedTokenService($cache, $cache, true);

        $token = $service->createToken('testtype');

        $write = $cache->lastWrite();
        self::assertNotNull($write);
        self::assertSame(CustomisedTokenService::CUSTOM_TAG, $write['tag']);
        self::assertSame(
            [CustomisedTokenService::CUSTOM_PREFIX.$token->getUid()],
            $cache->keysTaggedWith(CustomisedTokenService::CUSTOM_TAG),
        );
        self::assertSame([], $cache->keysTaggedWith(TokenServiceInterface::CACHE_TAG));
    }

    /**
     * Invokes a protected method of the service.
     */
    private function callProtected(TokenService $service, string $method, mixed ...$arguments): mixed
    {
        return (new \ReflectionMethod(TokenService::class, $method))->invoke($service, ...$arguments);
    }
}
