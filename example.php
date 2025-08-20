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
