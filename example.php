<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Praetorian\TokenService\TokenService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;

// Example 1: Using FilesystemAdapter (for production)
echo "=== Example 1: Filesystem Cache ===\n";
$filesystemCache = new FilesystemAdapter();
$tokenService = new TokenService($filesystemCache);

$token = $tokenService->createToken('login', ['user_id' => 123], 3600); // 1 hour TTL
echo "Created token: " . $token->getUid() . "\n";
echo "Token type: " . $token->getType() . "\n";
echo "Token payload: " . print_r($token->getPayload(), true) . "\n";

$consumedToken = $tokenService->consumeToken($token->getUid());
if ($consumedToken) {
    echo "Token consumed successfully!\n";
} else {
    echo "Token not found or expired\n";
}

// Try to consume again (should return null since token was consumed)
$consumedAgain = $tokenService->consumeToken($token->getUid());
echo "Second consumption result: " . ($consumedAgain ? "Found" : "Not found") . "\n";

echo "\n";

// Example 2: Using ArrayAdapter (for testing/development)
echo "=== Example 2: Array Cache (In-Memory) ===\n";
$arrayCache = new ArrayAdapter();
$tokenService2 = new TokenService($arrayCache);

$token2 = $tokenService2->createToken('reset', 'password-reset-data');
echo "Created token: " . $token2->getUid() . "\n";

// Consume but keep the token
$consumedToken2 = $tokenService2->consumeToken($token2->getUid(), true);
echo "Token consumed (kept): " . ($consumedToken2 ? "Success" : "Failed") . "\n";

// Try to consume again (should still work since we kept it)
$consumedAgain2 = $tokenService2->consumeToken($token2->getUid());
echo "Second consumption result: " . ($consumedAgain2 ? "Found" : "Not found") . "\n";

echo "\n";

// Example 3: Using RedisAdapter (requires Redis to be running)
echo "=== Example 3: Redis Cache (if Redis is available) ===\n";
try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    $redisCache = new RedisAdapter($redis);
    $tokenService3 = new TokenService($redisCache);

    $token3 = $tokenService3->createToken('session', ['session_data' => 'example'], 300); // 5 minutes
    echo "Created Redis token: " . $token3->getUid() . "\n";

    $consumedToken3 = $tokenService3->consumeToken($token3->getUid());
    echo "Redis token consumed: " . ($consumedToken3 ? "Success" : "Failed") . "\n";
} catch (Exception $e) {
    echo "Redis not available: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 4: Demonstrating clearAllTokens functionality
echo "=== Example 4: Clear All Tokens ===\n";
$arrayCache2 = new ArrayAdapter();
$tokenService4 = new TokenService($arrayCache2);

// Create multiple tokens
$token4a = $tokenService4->createToken('multi1', 'data1');
$token4b = $tokenService4->createToken('multi2', 'data2');
$token4c = $tokenService4->createToken('multi3', 'data3');

echo "Created 3 tokens:\n";
echo "- " . $token4a->getUid() . " (type: " . $token4a->getType() . ")\n";
echo "- " . $token4b->getUid() . " (type: " . $token4b->getType() . ")\n";
echo "- " . $token4c->getUid() . " (type: " . $token4c->getType() . ")\n";

// Verify they exist
$exists4a = $tokenService4->consumeToken($token4a->getUid(), true) !== null;
$exists4b = $tokenService4->consumeToken($token4b->getUid(), true) !== null;
$exists4c = $tokenService4->consumeToken($token4c->getUid(), true) !== null;
echo "All tokens exist: " . ($exists4a && $exists4b && $exists4c ? "YES" : "NO") . "\n";

// Clear all tokens
$clearResult = $tokenService4->clearAllTokens();
echo "Clear all tokens result: " . ($clearResult ? "SUCCESS" : "FAILED") . "\n";

// Verify they're gone
$gone4a = $tokenService4->consumeToken($token4a->getUid()) === null;
$gone4b = $tokenService4->consumeToken($token4b->getUid()) === null;
$gone4c = $tokenService4->consumeToken($token4c->getUid()) === null;
echo "All tokens cleared: " . ($gone4a && $gone4b && $gone4c ? "YES" : "NO") . "\n";
