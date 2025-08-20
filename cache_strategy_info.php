<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Praetorian\TokenService\TokenService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

echo "=== clearAllTokens() Implementation Comparison ===\n\n";

// Demonstration of the two clearing strategies
echo "Strategy 1: Regular Cache Adapter (clears ENTIRE pool)\n";
echo "- Pros: Simple, works with all PSR-6 adapters\n";
echo "- Cons: Clears ALL cached data, not just tokens\n";
echo "- Use case: When cache pool is dedicated to tokens only\n\n";

echo "Strategy 2: Tag-Aware Cache Adapter (clears ONLY tagged items)\n";
echo "- Pros: Precise clearing, preserves other cached data\n";
echo "- Cons: Requires TagAwareAdapterInterface support\n";
echo "- Use case: When cache pool is shared with other data\n\n";

echo "=== Cache Adapter Support ===\n";

// Test different adapters
$adapters = [
    'ArrayAdapter' => new ArrayAdapter(),
    'FilesystemAdapter' => new FilesystemAdapter(),
    'TagAwareAdapter(FilesystemAdapter)' => new TagAwareAdapter(new FilesystemAdapter()),
];

foreach ($adapters as $name => $adapter) {
    $isTagAware = $adapter instanceof \Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
    $strategy = $isTagAware ? 'Tag-based clearing' : 'Full pool clearing';
    echo sprintf("%-35s: %s\n", $name, $strategy);
}

echo "\n=== Recommendations ===\n";
echo "✅ For shared cache pools: Use TagAwareAdapter for precise token clearing\n";
echo "✅ For dedicated token cache: Either approach works fine\n";
echo "✅ For maximum compatibility: Regular adapters with dedicated pools\n";
echo "⚠️  Always be aware which clearing strategy your setup uses!\n";
