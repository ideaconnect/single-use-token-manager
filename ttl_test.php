<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Praetorian\TokenService\TokenService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

echo "=== Testing TTL (Time To Live) functionality ===\n";

$cache = new ArrayAdapter();
$tokenService = new TokenService($cache);

// Create a token with 2 seconds TTL
$token = $tokenService->createToken('test', 'payload', 2);
echo "Created token with 2s TTL: " . $token->getUid() . "\n";

// Immediately try to consume it (should work)
$consumed1 = $tokenService->consumeToken($token->getUid(), true); // keep it
echo "Immediate consumption: " . ($consumed1 ? "SUCCESS" : "FAILED") . "\n";

// Wait 1 second and try again (should still work)
sleep(1);
$consumed2 = $tokenService->consumeToken($token->getUid(), true); // keep it
echo "After 1s: " . ($consumed2 ? "SUCCESS" : "FAILED") . "\n";

// Wait 2 more seconds (total 3s, should be expired)
sleep(2);
$consumed3 = $tokenService->consumeToken($token->getUid());
echo "After 3s total: " . ($consumed3 ? "SUCCESS" : "EXPIRED") . "\n";

echo "\n=== Testing token without TTL ===\n";

// Create a token without TTL (should persist)
$token2 = $tokenService->createToken('persistent', 'data');
echo "Created persistent token: " . $token2->getUid() . "\n";

// Wait a bit and check if it's still there
sleep(1);
$consumed4 = $tokenService->consumeToken($token2->getUid());
echo "Persistent token after 1s: " . ($consumed4 ? "SUCCESS" : "FAILED") . "\n";
