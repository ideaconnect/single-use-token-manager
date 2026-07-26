<?php

declare(strict_types=1);

/**
 * Proves that a token can be redeemed exactly once when several processes go
 * for it at the same instant.
 *
 * A unit test cannot show this. One PHP process has nothing to race against, so
 * the gap between reading a token and removing it never opens. This script
 * forks real processes, lines them up on a shared wall-clock start so they all
 * arrive together, and counts how many believe they redeemed the token.
 *
 * Both cache shapes are exercised on purpose:
 *
 *   - idct/php-rapid-cache-client, whose take() is a real atomic operation,
 *     must produce exactly one winner. Running the actual dependency rather
 *     than a test fixture means this measures what a consumer following the
 *     README gets. If the atomic path is ever bypassed, this fails.
 *   - A plain PSR-16 cache is expected to produce more than one winner, which
 *     is the limitation the README documents. It is asserted rather than
 *     assumed, so the documentation cannot quietly drift away from the code.
 *
 * Usage:
 *   php tests/Concurrency/single-use-under-load.php
 *   php tests/Concurrency/single-use-under-load.php --redeemer <driver> <uid> <startAt>
 */

require_once __DIR__.'/../../vendor/autoload.php';

use IDCT\Cache\RapidCacheClient;
use IDCT\Cache\RedisConnectionConfig;
use IDCT\SingleUseTokenManager\TokenService;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;

const REDEEMERS = 8;
const KEY_PREFIX = 'concurrency_';
const DRIVER_ATOMIC = 'rapid_cache';
const DRIVER_PLAIN = 'plain';

/** How many times the plain-cache race may be retried before it counts as a failure. */
const PLAIN_ROUNDS = 3;

/**
 * Builds the cache the given driver names.
 */
function build_cache(string $driver): CacheInterface
{
    $configuredHost = getenv('REDIS_HOST');
    $configuredPort = getenv('REDIS_PORT');

    $host = false === $configuredHost || '' === $configuredHost ? '127.0.0.1' : $configuredHost;
    $port = false === $configuredPort || '' === $configuredPort ? 6379 : (int) $configuredPort;

    if (DRIVER_ATOMIC === $driver) {
        // The real dependency, not a test fixture: idct/php-rapid-cache-client
        // exposes take() from 1.1 onwards, so this measures what a consumer
        // following the README actually gets.
        return new RapidCacheClient(new RedisConnectionConfig(
            host: $host,
            port: $port,
            prefix: KEY_PREFIX,
        ));
    }

    $redis = new Redis();
    $redis->connect($host, $port);

    return new Psr16Cache(new RedisAdapter($redis, KEY_PREFIX));
}

// Child mode: wait for the agreed instant, then try to redeem exactly once.
if (($argv[1] ?? '') === '--redeemer') {
    $service = new TokenService(build_cache($argv[2]));
    $startAt = (float) $argv[4];

    while (microtime(true) < $startAt) {
        usleep(200);
    }

    exit(null !== $service->consumeToken($argv[3]) ? 0 : 1);
}

/**
 * Issues one token, sets N processes on it at once, and returns how many won.
 */
function count_winners(string $driver): int
{
    $cache = build_cache($driver);
    $cache->clear();

    $uid = (new TokenService($cache))->createToken('reset', ['user_id' => 1])->getUid();
    $startAt = microtime(true) + 2.0;

    $children = [];
    $pipes = [];
    for ($i = 0; $i < REDEEMERS; ++$i) {
        $command = sprintf(
            '%s %s --redeemer %s %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__FILE__),
            escapeshellarg($driver),
            escapeshellarg($uid),
            escapeshellarg((string) $startAt),
        );

        $child = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $childPipes);
        if (false === $child) {
            throw new RuntimeException('Could not start a redeemer process.');
        }

        $children[] = $child;
        $pipes[] = $childPipes;
    }

    $winners = 0;
    foreach ($children as $index => $child) {
        fclose($pipes[$index][1]);
        fclose($pipes[$index][2]);

        if (0 === proc_close($child)) {
            ++$winners;
        }
    }

    $cache->clear();

    return $winners;
}

$failures = 0;

$atomicWinners = count_winners(DRIVER_ATOMIC);
printf('rapid cache client: %d of %d redeemers won%s', $atomicWinners, REDEEMERS, PHP_EOL);
if (1 !== $atomicWinners) {
    fwrite(STDERR, sprintf(
        'FAILED: an atomic cache must yield exactly one winner, got %d.%s',
        $atomicWinners,
        PHP_EOL,
    ));
    ++$failures;
}

// The atomic assertion above is deterministic: atomicity guarantees one winner
// however the processes happen to interleave. This one is not. It needs the
// redeemers to actually overlap, and on a loaded machine they may not, which
// would fail the build for a scheduling accident rather than a defect. Demanding
// only that the overlap shows up once across a few rounds keeps the
// documentation honest without making the check a coin toss.
$plainWinners = 0;
for ($round = 0; $round < PLAIN_ROUNDS && $plainWinners <= 1; ++$round) {
    $plainWinners = max($plainWinners, count_winners(DRIVER_PLAIN));
}

printf('plain PSR-16:       %d of %d redeemers won%s', $plainWinners, REDEEMERS, PHP_EOL);
if ($plainWinners <= 1) {
    fwrite(STDERR, sprintf(
        'FAILED: a plain cache was expected to let more than one redeemer through, but the '
        .'best of %d rounds was %d. Either the redeemers never overlapped, or PSR-16 has '
        .'gained an atomic take and the README no longer describes reality.%s',
        PLAIN_ROUNDS,
        $plainWinners,
        PHP_EOL,
    ));
    ++$failures;
}

if (0 === $failures) {
    echo PHP_EOL, 'Single use holds under concurrency on a cache that can take atomically.', PHP_EOL;
}

exit($failures > 0 ? 1 : 0);
