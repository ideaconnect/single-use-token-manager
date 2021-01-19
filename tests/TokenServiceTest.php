<?php

declare(strict_types=1);

namespace Praetorian\Tests\TokenService;

use PHPUnit\Framework\TestCase;
use Praetorian\TokenService\Token;
use Praetorian\TokenService\TokenInterface;
use Praetorian\TokenService\TokenService;
use Preatorian\Prometheus\CacheService\CacheServiceInterface;
use ReflectionClass;
use stdClass;

final class TokenServiceTest extends TestCase
{
    use \phpmock\phpunit\PHPMock;
    const TESTED_CLASS = Token::class;

    public function testConstructor(): void
    {
        /** @var CacheServiceInterface */
        $cache = $this->getMockBuilder(CacheServiceInterface::class)
            ->setMockClassName('cacheServiceFaker')
            ->getMock();

        $tokenService = new TokenService($cache);

        $class = new ReflectionClass(TokenService::class);
        $method = $class->getMethod('getCache');
        $method->setAccessible(true);

        $cacheReturned = $method->invoke($tokenService);

        $this->assertSame($cache, $cacheReturned);
    }

    public function testCreateToken_justType()
    {
        $tokenService = $this->getMockBuilder(TokenService::class)
            ->onlyMethods(['getCache'])
            ->disableOriginalConstructor()
            ->getMock();

        $cache = $this->getMockBuilder(CacheServiceInterface::class)
            ->setMockClassName('cacheServiceFaker')
            ->getMock();

        $tokenService->expects($this->once())
            ->method('getCache')
            ->will($this->returnValue($cache));

        $cache->expects($this->once())
            ->method('set')
            ->with($this->anything(), $this->anything(), TokenService::CACHE_TAG, null);
        ;

        $token = $tokenService->createToken('testtype');

        $this->assertEquals('testtype', $token->getType());
    }

    public function testCreateToken_typeAndPayload()
    {
        $tokenService = $this->getMockBuilder(TokenService::class)
            ->onlyMethods(['getCache'])
            ->disableOriginalConstructor()
            ->getMock();

        $cache = $this->getMockBuilder(CacheServiceInterface::class)
            ->setMockClassName('cacheServiceFaker')
            ->getMock();

        $tokenService->expects($this->once())
            ->method('getCache')
            ->will($this->returnValue($cache));

        $cache->expects($this->once())
            ->method('set')
            ->with($this->anything(), $this->anything(), TokenService::CACHE_TAG, null);
        ;

        $testObject = new stdClass();
        $testObject->test = 'abc';

        $token = $tokenService->createToken('testtype', $testObject);

        $this->assertEquals('testtype', $token->getType());
        $this->assertSame($testObject, $token->getPayload());
    }

    public function testCreateToken_typeAndPayloadAndTtl()
    {
        $tokenService = $this->getMockBuilder(TokenService::class)
            ->onlyMethods(['getCache'])
            ->disableOriginalConstructor()
            ->getMock();

        $cache = $this->getMockBuilder(CacheServiceInterface::class)
            ->setMockClassName('cacheServiceFaker')
            ->getMock();

        $tokenService->expects($this->once())
            ->method('getCache')
            ->will($this->returnValue($cache));

        $cache->expects($this->once())
            ->method('set')
            ->with($this->anything(), $this->anything(), TokenService::CACHE_TAG, 150);
        ;

        $testObject = new stdClass();
        $testObject->test = 'abc';

        $token = $tokenService->createToken('testtype', $testObject, 150);

        $this->assertEquals('testtype', $token->getType());
        $this->assertSame($testObject, $token->getPayload());
    }

    public function testConsumeToken_null()
    {
        $tokenService = $this->getMockBuilder(TokenService::class)
            ->onlyMethods(['getCache'])
            ->disableOriginalConstructor()
            ->getMock();

        $cache = $this->getMockBuilder(CacheServiceInterface::class)
            ->setMockClassName('cacheServiceFaker')
            ->getMock();

        $tokenService->expects($this->once())
            ->method('getCache')
            ->will($this->returnValue($cache));

        $cache->expects($this->once())
            ->method('get')
            ->with($this->anything())
            ->willReturn(null);

        $token = $tokenService->consumeToken('abc');
        $this->assertNull($token);
    }

    public function testConsumeToken_exists()
    {
        $tokenService = $this->getMockBuilder(TokenService::class)
            ->onlyMethods(['getCache'])
            ->disableOriginalConstructor()
            ->getMock();

        $cache = $this->getMockBuilder(CacheServiceInterface::class)
            ->setMockClassName('cacheServiceFaker')
            ->getMock();

        $tokenService->expects($this->once())
            ->method('getCache')
            ->will($this->returnValue($cache));

        $tokenMock = $this->getMockBuilder(TokenInterface::class)
            ->setMockClassName('fakeToken')
            ->disableOriginalConstructor()
            ->getMock();

        $cache->expects($this->once())
            ->method('get')
            ->with($this->anything())
            ->willReturn($tokenMock);

        $token = $tokenService->consumeToken('abc');
        $this->assertSame($tokenMock, $token);
        ;
    }
}
