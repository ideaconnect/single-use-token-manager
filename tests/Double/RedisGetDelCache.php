<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Double;

use IDCT\SingleUseTokenManager\Contract\AtomicCacheInterface;
use Redis;

/**
 * Small PSR-16 cache over phpredis whose take() is a single GETDEL.
 *
 * This exists so the atomicity of {@see \IDCT\SingleUseTokenManager\TokenService::consumeToken()}
 * can be demonstrated across real processes rather than asserted. An in-memory
 * double cannot show it, because there is nothing to race against inside one
 * PHP process.
 *
 * It is a test fixture, not a cache worth using. It has no reconnection, no
 * pipelining and no key validation. For production use a real client such as
 * `idct/php-rapid-cache-client`.
 *
 * GETDEL needs Redis or Valkey 6.2 or newer.
 */
final class RedisGetDelCache implements AtomicCacheInterface
{
    private readonly \Redis $redis;

    /**
     * @param string $host   hostname of the Redis or Valkey server
     * @param int    $port   port the server listens on
     * @param string $prefix key prefix, so parallel suites cannot collide
     */
    public function __construct(string $host, int $port, private readonly string $prefix = '')
    {
        $redis = new \Redis();
        $redis->connect($host, $port);
        $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
        $redis->setOption(\Redis::OPT_PREFIX, $this->prefix);

        $this->redis = $redis;
    }

    public function take(string $key): mixed
    {
        $value = $this->redis->getDel($key);

        return false === $value ? null : $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($key);

        return false === $value ? $default : $value;
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        if (null === $ttl) {
            return (bool) $this->redis->set($key, $value);
        }

        $seconds = $ttl instanceof \DateInterval
            ? (new \DateTimeImmutable('@0'))->add($ttl)->getTimestamp()
            : $ttl;

        return (bool) $this->redis->setex($key, $seconds, $value);
    }

    public function delete(string $key): bool
    {
        $this->redis->del($key);

        return true;
    }

    public function clear(): bool
    {
        // Prefixes are applied by phpredis on the way out, so KEYS has to be
        // asked with the raw pattern and the results deleted without the
        // prefix being added a second time.
        $this->redis->setOption(\Redis::OPT_PREFIX, '');

        try {
            /** @var list<string> $keys */
            $keys = $this->redis->keys($this->prefix.'*');

            if ([] !== $keys) {
                $this->redis->del($keys);
            }
        } finally {
            $this->redis->setOption(\Redis::OPT_PREFIX, $this->prefix);
        }

        return true;
    }

    /**
     * @param iterable<string> $keys
     *
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * @param iterable<mixed, mixed> $values
     */
    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        $result = true;
        foreach ($values as $key => $value) {
            if (!is_string($key) && !is_int($key)) {
                throw new InvalidCacheKeyException('A cache key must be a string.');
            }

            $result = $this->set((string) $key, $value, $ttl) && $result;
        }

        return $result;
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($key);
    }
}
