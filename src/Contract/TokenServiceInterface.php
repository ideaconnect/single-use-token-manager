<?php

declare(strict_types=1);

namespace IDCT\SingleUseTokenManager\Contract;

use IDCT\SingleUseTokenManager\Exception\TokenRemovalException;
use IDCT\SingleUseTokenManager\Exception\TokenStorageException;

/**
 * Contract for single-use token management services.
 *
 * Describes the three operations that make up the lifecycle of a token: issuing
 * one, redeeming it, and dropping all of them at once. Implementations store
 * tokens in a PSR-16 cache, which gives expiry for free through the cache's
 * own time-to-live handling.
 *
 * @author IDCT
 *
 * @since 1.0.0
 */
interface TokenServiceInterface
{
    /**
     * Prefix prepended to every token identifier to form the cache key.
     *
     * It keeps tokens from colliding with unrelated entries when the cache pool
     * is shared with the rest of the application.
     *
     * @var string
     */
    public const CACHE_KEY = 'TKN_';

    /**
     * Tag applied to every stored token on caches that support tagging.
     *
     * Only used when the injected cache implements {@see TaggedCacheInterface}
     * or exposes the same two methods.
     *
     * @var string
     */
    public const CACHE_TAG = 'TKN';

    /**
     * Issues a new single-use token and stores it in the cache.
     *
     * The token receives a fresh unique identifier, so calling this method
     * twice with identical arguments produces two distinct tokens — unless an
     * identifier is supplied, in which case the second call replaces the first
     * in the same cache entry.
     *
     * Supply `$uid` only when the request that will redeem the token has
     * nowhere to carry a random identifier and the token therefore has to be
     * reachable from data the caller already holds. It stops being a secret at
     * that point, so whatever authorises the action belongs in the payload and
     * must be checked once the token comes back. See
     * {@see \IDCT\SingleUseTokenManager\Model\Token} for the full consequences.
     *
     * @param string      $type    token type, used to categorise the token
     * @param mixed       $payload optional data to carry with the token
     * @param int|null    $ttl     lifetime in seconds, or null to let the cache
     *                             decide how long to keep the entry
     * @param string|null $uid     identifier to store the token under, or null
     *                             to receive an unguessable one
     *
     * @return TokenInterface the created token, carrying its unique identifier
     *
     * @throws \InvalidArgumentException                 if the token type or the supplied identifier is not acceptable
     * @throws TokenStorageException                     if the cache refused to store the token
     * @throws \Psr\SimpleCache\InvalidArgumentException if the cache key is not a legal value
     */
    public function createToken(string $type, mixed $payload = null, ?int $ttl = null, ?string $uid = null): TokenInterface;

    /**
     * Redeems a token by its unique identifier.
     *
     * The token is removed from the cache as it is returned, so a second call
     * with the same identifier yields null. Pass `$keepToken` to look a token
     * up without spending it. That mode is for read-only checks only: it must
     * not be used to gate a later redeeming call, because the gap between the
     * two is exactly where a second request gets in.
     *
     * Whether simultaneous callers can both redeem depends on the cache. See
     * {@see AtomicCacheInterface} for what closes that window.
     *
     * @param string $uid       unique identifier of the token to redeem
     * @param bool   $keepToken true to leave the token in the cache
     *
     * @return TokenInterface|null the redeemed token, or null when no live
     *                             token is stored under that identifier
     *
     * @throws TokenRemovalException                     if the cache refused to remove a redeemed token
     * @throws \Psr\SimpleCache\InvalidArgumentException if the cache key is not a legal value
     */
    public function consumeToken(string $uid, bool $keepToken = false): ?TokenInterface;

    /**
     * Drops every token the service is responsible for.
     *
     * On a cache that supports tagging only the tokens are removed. On a plain
     * PSR-16 cache the whole pool is emptied, because PSR-16 offers no finer
     * bulk operation. Give the service a dedicated cache pool if that matters.
     *
     * @return bool true when the tokens were dropped, false otherwise
     */
    public function clearAllTokens(): bool;
}
