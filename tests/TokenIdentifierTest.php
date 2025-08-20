<?php

declare(strict_types=1);

namespace Praetorian\Tests\TokenService;

use PHPUnit\Framework\TestCase;
use Praetorian\TokenService\TokenIdentifier;

final class TokenIdentifierTest extends TestCase
{
    public function testTokenIdentifierProperties(): void
    {
        $tokenIdentifier = new TokenIdentifier();
        $tokenIdentifier->token = 'test-token-123';
        
        $this->assertEquals('test-token-123', $tokenIdentifier->token);
    }

    public function testTokenIdentifierIsPublicProperty(): void
    {
        $reflection = new \ReflectionClass(TokenIdentifier::class);
        $property = $reflection->getProperty('token');
        
        $this->assertTrue($property->isPublic());
        $this->assertEquals('token', $property->getName());
    }
}
