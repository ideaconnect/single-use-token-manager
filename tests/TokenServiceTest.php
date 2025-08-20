<?php

declare(strict_types=1);

namespace Praetorian\Tests\TokenService;

use PHPUnit\Framework\TestCase;
use Praetorian\TokenService\Token;
use Praetorian\TokenService\TokenInterface;
use Praetorian\TokenService\TokenService;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;
use stdClass;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

final class TokenServiceTest extends TestCase
{
    use \phpmock\phpunit\PHPMock;
    const TESTED_CLASS = Token::class;

    public function testConstructor(): void
    {
        /** @var CacheItemPoolInterface */
        $cache = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->setMockClassName('cacheServiceFaker')
            ->getMock();

        $tokenService = new TokenService($cache);

        $class = new ReflectionClass(TokenService::class);
        $method = $class->getMethod('getCache');
        $method->setAccessible(true);

        $cacheReturned = $method->invoke($tokenService);

        $this->assertSame($cache, $cacheReturned);
    }

    public function testCreateTokenJustType()
    {
        $tokenService = $this->getMockBuilder(TokenService::class)
            ->onlyMethods(['getCache'])
            ->disableOriginalConstructor()
            ->getMock();

        $cache = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->setMockClassName('cacheServiceFaker')
            ->getMock();

        $cacheItem = $this->getMockBuilder(CacheItemInterface::class)
            ->getMock();

        $tokenService->expects($this->once())
            ->method('getCache')
            ->will($this->returnValue($cache));

        $cache->expects($this->once())
            ->method('getItem')
            ->with($this->anything())
            ->willReturn($cacheItem);

        $cacheItem->expects($this->once())
            ->method('set')
            ->with($this->anything());

        $cacheItem->expects($this->never())
            ->method('expiresAfter');

        $cache->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $token = $tokenService->createToken('testtype');

        $this->assertEquals('testtype', $token->getType());
    }

    public function testCreateTokenTypeAndPayload()
    {
        $tokenService = $this->getMockBuilder(TokenService::class)
            ->onlyMethods(['getCache'])
            ->disableOriginalConstructor()
            ->getMock();

        $cache = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->setMockClassName('cacheServiceFaker')
            ->getMock();

        $cacheItem = $this->getMockBuilder(CacheItemInterface::class)
            ->getMock();

        $tokenService->expects($this->once())
            ->method('getCache')
            ->will($this->returnValue($cache));

        $cache->expects($this->once())
            ->method('getItem')
            ->with($this->anything())
            ->willReturn($cacheItem);

        $cacheItem->expects($this->once())
            ->method('set')
            ->with($this->anything());

        $cacheItem->expects($this->never())
            ->method('expiresAfter');

        $cache->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $testObject = new stdClass();
        $testObject->test = 'abc';

        $token = $tokenService->createToken('testtype', $testObject);

        $this->assertEquals('testtype', $token->getType());
        $this->assertSame($testObject, $token->getPayload());
    }

    public function testCreateTokenTypeAndPayloadAndTtl()
    {
        $tokenService = $this->getMockBuilder(TokenService::class)
            ->onlyMethods(['getCache'])
            ->disableOriginalConstructor()
            ->getMock();

        $cache = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->setMockClassName('cacheServiceFaker')
            ->getMock();

        $cacheItem = $this->getMockBuilder(CacheItemInterface::class)
            ->getMock();

        $tokenService->expects($this->once())
            ->method('getCache')
            ->will($this->returnValue($cache));

        $cache->expects($this->once())
            ->method('getItem')
            ->with($this->anything())
            ->willReturn($cacheItem);

        $cacheItem->expects($this->once())
            ->method('set')
            ->with($this->anything());

        $cacheItem->expects($this->once())
            ->method('expiresAfter')
            ->with(150);

        $cache->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $testObject = new stdClass();
        $testObject->test = 'abc';

        $token = $tokenService->createToken('testtype', $testObject, 150);

        $this->assertEquals('testtype', $token->getType());
        $this->assertSame($testObject, $token->getPayload());
    }

    public function testConsumeTokenNull()
    {
        $tokenService = $this->getMockBuilder(TokenService::class)
            ->onlyMethods(['getCache'])
            ->disableOriginalConstructor()
            ->getMock();

        $cache = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->setMockClassName('cacheServiceFaker')
            ->getMock();

        $cacheItem = $this->getMockBuilder(CacheItemInterface::class)
            ->getMock();

        $tokenService->expects($this->once())
            ->method('getCache')
            ->will($this->returnValue($cache));

        $cache->expects($this->once())
            ->method('getItem')
            ->with($this->anything())
            ->willReturn($cacheItem);

        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);

        $token = $tokenService->consumeToken('abc');
        $this->assertNull($token);
    }

    public function testConsumeTokenExists()
    {
        $tokenService = $this->getMockBuilder(TokenService::class)
            ->onlyMethods(['getCache'])
            ->disableOriginalConstructor()
            ->getMock();

        $cache = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->setMockClassName('cacheServiceFaker')
            ->getMock();

        $cacheItem = $this->getMockBuilder(CacheItemInterface::class)
            ->getMock();

        $tokenMock = $this->getMockBuilder(TokenInterface::class)
            ->setMockClassName('fakeToken')
            ->disableOriginalConstructor()
            ->getMock();

        $tokenService->expects($this->once())
            ->method('getCache')
            ->will($this->returnValue($cache));

        $cache->expects($this->once())
            ->method('getItem')
            ->with($this->anything())
            ->willReturn($cacheItem);

        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);

        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($tokenMock);

        $cache->expects($this->once())
            ->method('deleteItem')
            ->with($this->anything());

        $token = $tokenService->consumeToken('abc');
        $this->assertSame($tokenMock, $token);
    }

    public function testClearAllTokensWithRegularCache()
    {
        $tokenService = $this->getMockBuilder(TokenService::class)
            ->onlyMethods(['getCache'])
            ->disableOriginalConstructor()
            ->getMock();

        $cache = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->setMockClassName('cacheServiceFaker')
            ->getMock();

        $tokenService->expects($this->once())
            ->method('getCache')
            ->will($this->returnValue($cache));

        $cache->expects($this->once())
            ->method('clear')
            ->willReturn(true);

        $result = $tokenService->clearAllTokens();
        $this->assertTrue($result);
    }

    public function testClearAllTokensWithTagAwareCache()
    {
        $tokenService = $this->getMockBuilder(TokenService::class)
            ->onlyMethods(['getCache'])
            ->disableOriginalConstructor()
            ->getMock();

        $cache = $this->getMockBuilder(TagAwareAdapterInterface::class)
            ->setMockClassName('tagAwareCacheFaker')
            ->getMock();

        $tokenService->expects($this->once())
            ->method('getCache')
            ->will($this->returnValue($cache));

        $cache->expects($this->once())
            ->method('invalidateTags')
            ->with([TokenService::CACHE_TAG])
            ->willReturn(true);

        $cache->expects($this->never())
            ->method('clear');

        $result = $tokenService->clearAllTokens();
        $this->assertTrue($result);
    }
}
