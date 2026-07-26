<?php

declare(strict_types=1);

namespace IDCT\Tests\SingleUseTokenManager\Double;

/**
 * Atomic take shared by the cache doubles that support it.
 *
 * In a single-threaded test there is nothing to be atomic against, so this only
 * has to behave like a take: return the value and leave the key empty. The
 * genuine atomicity check runs against Redis in
 * {@see RedisGetDelCache}.
 *
 * Kept in a trait rather than a base class so one double can declare
 * {@see \IDCT\SingleUseTokenManager\Contract\AtomicCacheInterface} while
 * another merely exposes the method, which is the difference the token service
 * has to cope with.
 */
trait AtomicTakeBehaviour
{
    /** @var int How many times take() has been called */
    private int $takeCalls = 0;

    /**
     * Returns how many times take() was called during the test.
     */
    public function takeCalls(): int
    {
        return $this->takeCalls;
    }

    /**
     * Returns the value under the key and removes it in one call.
     */
    public function take(string $key): mixed
    {
        ++$this->takeCalls;

        $value = $this->get($key);
        $this->delete($key);

        return $value;
    }
}
