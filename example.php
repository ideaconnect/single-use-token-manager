<?php

declare(strict_types=1);

/**
 * Runnable tour of the token service.
 *
 * Each section is self-contained, so you can copy one out and adapt it. Run the
 * file with `php example.php` after `composer install`. Sections that need a
 * server say so and skip themselves when it is not reachable, which means the
 * file runs end to end on a bare checkout.
 */

require_once __DIR__.'/vendor/autoload.php';

use IDCT\Cache\RapidCacheClient;
use IDCT\Cache\RedisConnectionConfig;
use IDCT\SingleUseTokenManager\Exception\TokenStorageException;
use IDCT\SingleUseTokenManager\Model\TokenIdentifier;
use IDCT\SingleUseTokenManager\TokenService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Validator\Validation;

/**
 * Prints a section heading.
 */
function heading(string $title): void
{
    echo PHP_EOL, '=== ', $title, ' ===', PHP_EOL;
}

// ---------------------------------------------------------------------------
heading('1. Issuing and redeeming a token');
// ---------------------------------------------------------------------------

// Any PSR-16 cache will do. Psr16Cache wraps a Symfony PSR-6 adapter as one.
$service = new TokenService(new Psr16Cache(new ArrayAdapter()));

// The type says what the token is for, the payload carries whatever the code
// redeeming it will need, and the lifetime is handed straight to the cache.
$token = $service->createToken('login', ['user_id' => 123], 3600);

echo 'Identifier: ', $token->getUid(), PHP_EOL;
echo 'Type:       ', $token->getType(), PHP_EOL;
echo 'Payload:    ', json_encode($token->getPayload()), PHP_EOL;

$redeemed = $service->consumeToken($token->getUid());
echo 'Redeemed:   ', null !== $redeemed ? 'yes' : 'no', PHP_EOL;

// Single use means exactly that: the second attempt finds nothing.
$again = $service->consumeToken($token->getUid());
echo 'Redeemed a second time: ', null !== $again ? 'yes' : 'no', PHP_EOL;

// ---------------------------------------------------------------------------
heading('2. Checking a token without spending it');
// ---------------------------------------------------------------------------

$token = $service->createToken('verify', 'email@example.com');

// Useful when you want to reject a bad request before doing any real work.
$peeked = $service->consumeToken($token->getUid(), keepToken: true);
echo 'Peeked:     ', null !== $peeked ? 'yes' : 'no', PHP_EOL;
echo 'Still there after peeking: ', null !== $service->consumeToken($token->getUid()) ? 'yes' : 'no', PHP_EOL;

// ---------------------------------------------------------------------------
heading('3. A token that expires on its own');
// ---------------------------------------------------------------------------

$token = $service->createToken('reset', 'user-7', 1);
echo 'Issued with a lifetime of one second.', PHP_EOL;
sleep(2);
echo 'After two seconds: ', null !== $service->consumeToken($token->getUid()) ? 'still there' : 'gone', PHP_EOL;

// ---------------------------------------------------------------------------
heading('4. Refusing an unusable type');
// ---------------------------------------------------------------------------

// The type has to be lowercase letters and digits, at most 16 characters.
try {
    $service->createToken('Password Reset');
} catch (InvalidArgumentException $exception) {
    echo 'Refused: ', $exception->getMessage(), PHP_EOL;
}

// ---------------------------------------------------------------------------
heading('5. Validating an identifier that arrived over HTTP');
// ---------------------------------------------------------------------------

$validator = Validation::createValidatorBuilder()
    ->enableAttributeMapping()
    ->getValidator();

$token = $service->createToken('login', ['user_id' => 123]);

$identifier = new TokenIdentifier();
$identifier->token = $token->getUid();

$violations = $validator->validate($identifier);
echo 'Violations for a real identifier:  ', count($violations), PHP_EOL;

$identifier->token = '   ';
echo 'Violations for a blank identifier: ', count($validator->validate($identifier)), PHP_EOL;

// ---------------------------------------------------------------------------
heading('6. Clearing tokens on a cache without tagging');
// ---------------------------------------------------------------------------

$plainCache = new Psr16Cache(new ArrayAdapter());
$plainCache->set('unrelated', 'some other value');
$plainService = new TokenService($plainCache);

$tokens = [
    $plainService->createToken('multi1', 'data1'),
    $plainService->createToken('multi2', 'data2'),
    $plainService->createToken('multi3', 'data3'),
];

$plainService->clearAllTokens();

echo 'Tokens left:    ', count(array_filter(
    $tokens,
    static fn ($issued): bool => null !== $plainService->consumeToken($issued->getUid()),
)), PHP_EOL;

// PSR-16 has no way to clear part of a pool, so the unrelated entry goes too.
// Give the service its own pool, or use a cache that supports tagging.
echo 'Unrelated entry: ', null !== $plainCache->get('unrelated') ? 'kept' : 'gone as well', PHP_EOL;

// ---------------------------------------------------------------------------
heading('7. Clearing tokens on a cache with tagging');
// ---------------------------------------------------------------------------

// idct/php-rapid-cache-client is a PSR-16 cache that can also clear by tag. The
// token service picks that up on its own and stops emptying the whole pool.
try {
    $taggedCache = new RapidCacheClient(new RedisConnectionConfig(
        host: '127.0.0.1',
        port: 6380,
        prefix: 'example_',
    ));
    $taggedCache->set('unrelated', 'some other value');

    $taggedService = new TokenService($taggedCache);
    $tagged = $taggedService->createToken('login', ['user_id' => 123]);

    $taggedService->clearAllTokens();

    echo 'Token after clearing:  ', null !== $taggedService->consumeToken($tagged->getUid()) ? 'kept' : 'gone', PHP_EOL;
    echo 'Unrelated entry:       ', null !== $taggedCache->get('unrelated') ? 'kept' : 'gone', PHP_EOL;

    $taggedCache->clear();
} catch (Throwable $exception) {
    echo 'Skipped, no Valkey on 127.0.0.1:6380 (run `composer cache:start`): ', $exception->getMessage(), PHP_EOL;
}

// ---------------------------------------------------------------------------
heading('8. Noticing when the cache refuses a write');
// ---------------------------------------------------------------------------

// A token the cache never stored would be handed to a user who could never
// redeem it, so a refused write is reported rather than swallowed.
$readOnlyCache = new class(new Psr16Cache(new FilesystemAdapter())) implements Psr\SimpleCache\CacheInterface {
    public function __construct(private readonly Psr\SimpleCache\CacheInterface $inner)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->inner->get($key, $default);
    }

    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        return false;
    }

    public function delete(string $key): bool
    {
        return $this->inner->delete($key);
    }

    public function clear(): bool
    {
        return $this->inner->clear();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->inner->getMultiple($keys, $default);
    }

    /**
     * @param iterable<mixed, mixed> $values
     */
    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        return false;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return $this->inner->deleteMultiple($keys);
    }

    public function has(string $key): bool
    {
        return $this->inner->has($key);
    }
};

try {
    (new TokenService($readOnlyCache))->createToken('login');
} catch (TokenStorageException $exception) {
    echo 'Reported: ', $exception->getMessage(), PHP_EOL;
}

echo PHP_EOL, 'Done.', PHP_EOL;
