<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Double;

use Psr\SimpleCache\CacheInterface;

/**
 * Minimal PSR-16 cache backed by a plain array.
 *
 * Written for the test suite rather than for production use. It keeps the
 * arguments of the last write around so tests can assert what the token
 * service passed down, and it can be told to report every write as failed,
 * which is otherwise awkward to reproduce with a real cache.
 */
class InMemoryCache implements CacheInterface
{
    /** @var array<string, mixed> Stored values keyed by cache key */
    protected array $values = [];

    /** @var array<string, int> Expiry timestamps keyed by cache key */
    protected array $expiries = [];

    /** @var array<int, array{key: string, value: mixed, ttl: int|\DateInterval|null, tag: string|null}> Every write in order */
    protected array $writes = [];

    /** @var bool When true every write reports failure */
    protected bool $writesFail = false;

    /**
     * Makes every following write report failure without storing anything.
     */
    public function failWrites(): void
    {
        $this->writesFail = true;
    }

    /**
     * Returns the arguments of every write performed so far, in order.
     *
     * @return array<int, array{key: string, value: mixed, ttl: int|\DateInterval|null, tag: string|null}>
     */
    public function writes(): array
    {
        return $this->writes;
    }

    /**
     * Returns the arguments of the most recent write.
     *
     * @return array{key: string, value: mixed, ttl: int|\DateInterval|null, tag: string|null}|null
     */
    public function lastWrite(): ?array
    {
        return [] === $this->writes ? null : $this->writes[array_key_last($this->writes)];
    }

    /**
     * Returns how many keys the cache currently holds, expired ones included.
     */
    public function count(): int
    {
        return count($this->values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->values[$key];
    }

    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        return $this->write($key, $value, $ttl, null);
    }

    public function delete(string $key): bool
    {
        unset($this->values[$key], $this->expiries[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->values = [];
        $this->expiries = [];

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
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        $result = true;
        foreach ($values as $key => $value) {
            $result = $this->set($key, $value, $ttl) && $result;
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
        if (!array_key_exists($key, $this->values)) {
            return false;
        }

        if (isset($this->expiries[$key]) && $this->expiries[$key] <= time()) {
            $this->delete($key);

            return false;
        }

        return true;
    }

    /**
     * Records and performs a write, honouring the failure switch.
     *
     * @param int|\DateInterval|null $ttl lifetime, or null to keep the value indefinitely
     * @param string|null            $tag tag the value was written under, when any
     */
    protected function write(string $key, mixed $value, int|\DateInterval|null $ttl, ?string $tag): bool
    {
        $this->writes[] = ['key' => $key, 'value' => $value, 'ttl' => $ttl, 'tag' => $tag];

        if ($this->writesFail) {
            return false;
        }

        $this->values[$key] = $value;

        if (null !== $ttl) {
            $seconds = $ttl instanceof \DateInterval
                ? (new \DateTimeImmutable('@0'))->add($ttl)->getTimestamp()
                : $ttl;
            $this->expiries[$key] = time() + $seconds;
        }

        return true;
    }
}
