# Single Use Token Manager

[![CI](https://github.com/ideaconnect/single-use-token-manager/actions/workflows/ci.yml/badge.svg)](https://github.com/ideaconnect/single-use-token-manager/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/ideaconnect/single-use-token-manager/graph/badge.svg)](https://codecov.io/gh/ideaconnect/single-use-token-manager)
[![Mutation score](https://img.shields.io/badge/MSI-100%25-brightgreen.svg?style=flat)](https://github.com/ideaconnect/single-use-token-manager/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg?style=flat)](phpstan.neon.dist)
[![PHP](https://img.shields.io/badge/php-8.2%20%7C%208.3%20%7C%208.4%20%7C%208.5-777BB4.svg?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![PSR-16](https://img.shields.io/badge/PSR-16-orange.svg?style=flat)](https://www.php-fig.org/psr/psr-16/)
[![License: BSD-3-Clause](https://img.shields.io/badge/License-BSD--3--Clause-yellow.svg?style=flat)](LICENSE)
[![Latest version](https://img.shields.io/packagist/v/idct/single-use-token-manager.svg?style=flat&logo=packagist)](https://packagist.org/packages/idct/single-use-token-manager)
[![Downloads](https://img.shields.io/packagist/dt/idct/single-use-token-manager.svg?style=flat&logo=packagist)](https://packagist.org/packages/idct/single-use-token-manager)

Hand a user something they can redeem exactly once: a password reset link, an
email confirmation, a one-off download, a payment callback that must not run
twice. The library issues a token into a cache and takes it back out again, and
the cache handles expiry, so there is no sweeping job to write and nothing to
migrate.

Storage is any [PSR-16](https://www.php-fig.org/psr/psr-16/) cache. When the
cache also supports tagging, clearing tokens reaches the tokens and nothing
else.

## Installation

```bash
composer require idct/single-use-token-manager
```

You also need a PSR-16 cache. Two that work well:

```bash
# Redis or Valkey. Gives you tag-scoped clearing and single use that holds
# under concurrency, both picked up automatically.
composer require idct/php-rapid-cache-client

# Or wrap any Symfony PSR-6 adapter as PSR-16
composer require symfony/cache
```

## Getting started

```php
use IDCT\SingleUseTokenManager\TokenService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

$service = new TokenService(new Psr16Cache(new ArrayAdapter()));

// Issue a token that lives for an hour.
$token = $service->createToken('reset', ['user_id' => 123], 3600);

$token->getUid();     // '7c6b0c0c-d9b0-45af-a9c6-79ab7dd5c35d'
$token->getType();    // 'reset'
$token->getPayload(); // ['user_id' => 123]

// Redeem it. The token is removed from the cache as it is returned.
$redeemed = $service->consumeToken($token->getUid());

// A second attempt finds nothing, which is what makes it single use.
$service->consumeToken($token->getUid()); // null
```

`example.php` in the repository walks through the same ground in more detail and
runs as it is:

```bash
composer install
php example.php
```

## How it works

A token is stored as one cache entry under the key `TKN_` plus its identifier.
The identifier is a UUID v4, built from `random_bytes()`. That matters more than
it looks: a token is a bearer capability, so whoever holds the identifier can
perform the action, and only a cryptographically secure source makes it
unguessable. Time ordered versions are deliberately avoided, for reasons set out
in [Why the identifier is random](#why-the-identifier-is-random). Expiry is the cache's job: a
lifetime handed to `createToken()` goes straight through to the PSR-16 write, so
an expired token simply is not there any more.

`consumeToken()` reads the entry and removes it in the same call. It returns
null for a token that was never issued, one that has expired, one already
redeemed, and one whose cache entry turned out to hold something else, so
calling code only needs the single null check. How that behaves when several
callers arrive together is covered in [Concurrency](#concurrency).

## The API

### `TokenService`

| Method | What it does |
| --- | --- |
| `createToken(string $type, mixed $payload = null, ?int $ttl = null, ?string $uid = null): TokenInterface` | Issues a token and stores it. Pass `$uid` only to make the token [addressable](#addressable-tokens); otherwise it gets an unguessable one. Throws `InvalidArgumentException` for an unusable type or identifier, and `TokenStorageException` when the cache refuses the write. |
| `consumeToken(string $uid, bool $keepToken = false): ?TokenInterface` | Redeems a token, removing it unless `$keepToken` says otherwise. Returns null when there is no live token under that identifier. |
| `clearAllTokens(): bool` | Drops every token. See [Clearing tokens](#clearing-tokens) for what that costs on a cache without tagging. |

### The token type

A type is lowercase letters and digits, one to sixteen characters. That keeps it
safe to put in a cache key or a URL without escaping. `reset`, `verify2fa` and
`invite` are fine; `Password Reset`, `pass_reset` and an empty string are
refused with an `InvalidArgumentException` before anything is written.

### The payload

The payload is whatever the code redeeming the token will need. It travels
through the cache, so it has to survive that cache's serialisation. Arrays and
plain objects are safe; open resources and closures are not.

### Checking a token without spending it

Pass `keepToken: true` to look a token up without redeeming it. This is for
read-only checks, such as rendering a page that says the link is still valid:

```php
// Rendering a "your reset link is valid" page. Nothing is spent.
$token = $service->consumeToken($uid, keepToken: true);
```

Do not use it as a gate in front of a second, redeeming call:

```php
// Wrong. Two requests can both pass the check and both do the work.
if (null === $service->consumeToken($uid, keepToken: true)) {
    throw new NotFoundHttpException();
}
doTheExpensiveThing();
$service->consumeToken($uid);
```

The gap between the check and the spend is exactly where a second request gets
in, and the work in the middle widens it. Spend the token first, then do the
work:

```php
$token = $service->consumeToken($uid);
if (null === $token) {
    throw new NotFoundHttpException();
}

// The token is spent. Nobody else can redeem it.
doTheExpensiveThing($token->getPayload());
```

### Reporting a refused write

PSR-16 write methods report failure by returning false rather than by throwing.
A service that ignored that would hand back a token the cache never stored, and
the user would be sent something they could never redeem. `createToken()` turns
the false into a `TokenStorageException` instead.

`consumeToken()` treats a refused delete the same way, raising a
`TokenRemovalException`. A token the cache would not remove is still redeemable,
so reporting the failure is better than telling the caller it was spent. The
redeemed token is not returned in that case: losing one legitimate redemption
beats leaving a token open to replay.

## Why the identifier is random

A single-use token is a bearer capability: possession of the identifier is the
whole authorisation. That puts it in the same category as a session cookie, not
the same category as a database primary key, and it has to be unguessable.

Time ordered UUIDs are not. A v6 identifier carries a node that Symfony computes
once per process and then repeats on every token that process issues, so tokens
from one php-fpm worker all share their last twelve characters:

```
1f18890c-1563-6ae2-a9b7-77b0849864a3
1f18890c-1563-6c9a-a438-77b0849864a3
1f18890c-1563-6ccc-988e-77b0849864a3
                        ^^^^^^^^^^^^ identical for the life of the process
```

One leaked token gives that away for all the others, and the rest is mostly a
clock. An attacker who can request a token for their own account learns the node
and pins the time, leaving far too little to guess. A v7 identifier is no better
here: Symfony seeds it once and then increments, so consecutive values sit next
to each other.

RFC 9562 makes the same point in its security considerations: do not assume a
UUID is hard to guess, and do not use one as a security capability unless it
comes from a CSPRNG.

So the identifier is a v4, which Symfony builds from `random_bytes()`. The cost
is the loss of time ordering, which only ever bought cache index locality, and
that is worth very little for entries fetched by exact key that expire on their
own.

## Addressable tokens

Sometimes the request that redeems a token has nowhere to carry a random
identifier. A mobile client posting back an account id and a six digit code the
user copied out of an e-mail is the usual shape: the fields are fixed, none of
them is 36 characters wide, and the client cannot be changed. The token still
has to be findable.

Pass `$uid` to `createToken()` and the token is stored under it:

```php
$service->createToken(
    type: 'reset',
    payload: ['code' => $code, 'email' => $newAddress],
    ttl: 600,
    uid: "reset.{$userId}",
);

// later, from {id, code} in the request body
$token = $service->consumeToken("reset.{$userId}");
```

This is a deliberate trade, and it moves two duties onto you.

**The identifier is no longer the secret.** Deriving it from a user id — or an
order number, or anything else an attacker can enumerate — means reaching the
token proves nothing at all. Whatever actually authorises the action has to
travel in the payload and be checked *after* the token comes back:

```php
$token = $service->consumeToken("reset.{$userId}");

if (null === $token || !hash_equals($token->getPayload()['code'], $submittedCode)) {
    throw new AccessDeniedException();
}
```

Compare that with the default: a random identifier is itself proof, so a
non-null return is the whole check. Give the payload check the same care you
would give a password comparison — `hash_equals`, and a rate limit on top,
because a short code taken from an e-mail carries far less entropy than a v4.

**Uniqueness becomes yours.** Two tokens built with the same identifier are one
cache entry, and the second silently replaces the first. That is often what you
want, since it gives a flow one live token per user and lets a resend overwrite
what came before — but it is worth being deliberate about rather than surprised
by.

Identifiers are checked for the characters PSR-16 reserves (`{}()/\@:`) and for
being empty, and rejected with `InvalidArgumentException` before anything
reaches the cache. Everything else is yours to choose. The service namespace, if
you set one, still applies, so two services sharing a pool cannot collide even
on identical identifiers.

## Concurrency

Whether a token really is single use depends on what the cache can do.

PSR-16 has no way to take a value. Reading is `get()` and removing is
`delete()`, two calls with a gap between them, and every caller that arrives
inside that gap reads a token that is still there. Eight processes redeeming one
token simultaneously against a plain PSR-16 cache all succeed.

When the cache can read and remove in one operation, `consumeToken()` uses that
instead and exactly one caller wins. Declare the capability by implementing
`IDCT\SingleUseTokenManager\Contract\AtomicCacheInterface`:

```php
public function take(string $key): mixed;   // return the value and remove it, atomically
```

The service detects the method rather than the interface, so any cache carrying
a compatible `take()` is used as-is. Backing it is usually one command: Redis
and Valkey have had `GETDEL` since 6.2, which phpredis exposes as `getDel()`.

`idct/php-rapid-cache-client` ships `take()` from **1.1** onwards, so it gives
you both halves at once: tag-scoped clearing and single use that holds under
concurrency, with no wiring beyond constructing it.

```php
use IDCT\Cache\RapidCacheClient;
use IDCT\Cache\RedisConnectionConfig;

$cache = new RapidCacheClient(new RedisConnectionConfig(host: '127.0.0.1', port: 6379));

// Tagging and atomic redemption are both picked up automatically.
$service = new TokenService($cache);
```

`tests/Concurrency/single-use-under-load.php` demonstrates both halves. It lines
eight processes up on one token and asserts that an atomic cache yields exactly
one winner while a plain one does not, so neither this section nor the guarantee
can drift without the build noticing:

```
$ composer test:concurrency
rapid cache client: 1 of 8 redeemers won
plain PSR-16:       8 of 8 redeemers won
```

If more than one request can present the same token at once, and both succeeding
would matter, use a cache that can take atomically.

## Clearing tokens

`clearAllTokens()` behaves differently depending on what the cache can do.

**On a plain PSR-16 cache** the only bulk operation available is `clear()`,
which empties the entire pool. Anything else sharing that pool goes with the
tokens. Give the token service its own cache pool if that matters.

**On a cache that supports tagging** every token is written under the tag `TKN`
and clearing invalidates just that tag. Nothing else in the pool is touched.

The service works this out on its own, by checking whether the cache exposes
`setTagged()` and `clearByTag()`. No configuration and no wiring:

```php
use IDCT\Cache\RapidCacheClient;
use IDCT\Cache\RedisConnectionConfig;
use IDCT\SingleUseTokenManager\TokenService;

$cache = new RapidCacheClient(new RedisConnectionConfig(host: '127.0.0.1', port: 6379));
$cache->set('unrelated', 'some other value');

$service = new TokenService($cache);
$service->createToken('reset', ['user_id' => 123]);
$service->clearAllTokens();

$cache->get('unrelated'); // still 'some other value'
```

To declare the capability on a cache of your own, implement
`IDCT\SingleUseTokenManager\Contract\TaggedCacheInterface`. Its two methods have
the same signatures as the ones on `IDCT\Cache\CacheServiceInterface`, so a
cache satisfying one satisfies the other, and this package keeps
`idct/php-rapid-cache-client` an optional dependency rather than a required one.

## Sharing a cache between services

By default every token service writes keys as `TKN_<uid>` and tags them `TKN`.
Two services sharing one pool would therefore share a key space, and clearing
one would clear the other. Pass a namespace to keep them apart:

```php
$resets  = new TokenService($cache, 'reset_');
$invites = new TokenService($cache, 'invite_');

$token = $resets->createToken('reset', ['user_id' => 123]);

$invites->consumeToken($token->getUid());   // null, it is not theirs
$invites->clearAllTokens();                 // leaves the reset tokens alone
```

The namespace prefixes both the key and the tag. Leaving it out keeps the keys
byte for byte as earlier versions wrote them, so this is safe to adopt on an
existing deployment.

PSR-16 reserves the characters `{}()/\@:`, and a namespace containing any of
them is refused at construction rather than left to fail later as an unreadable
cache error.

Note that on a cache without tagging `clearAllTokens()` still empties the whole
pool, because `clear()` is all PSR-16 offers. The namespace separates the two
services' keys, not their blast radius. See [Clearing tokens](#clearing-tokens).

## Validating an identifier from a request

`TokenIdentifier` is the request object an endpoint hydrates before redeeming. It
carries Symfony Validator, Serializer and OpenAPI attributes, so the identifier
is validated, named `token` in JSON, and documented in the API schema without
the endpoint hand rolling any of it.

```php
use IDCT\SingleUseTokenManager\Model\TokenIdentifier;
use Symfony\Component\Validator\Validation;

$validator = Validation::createValidatorBuilder()
    ->enableAttributeMapping()
    ->getValidator();

$identifier = new TokenIdentifier();
$identifier->token = $request->get('token');

if (count($validator->validate($identifier)) > 0) {
    throw new BadRequestHttpException();
}

$token = $service->consumeToken($identifier->token);
```

An empty identifier is rejected, and so is one made only of whitespace, which
would otherwise slip through as a cache miss further down the line.

The OpenAPI attribute is inert unless you install `zircote/swagger-php`, which
is why this package does not require it. PHP does not load an attribute class
until something reflects over it, so the attribute costs nothing when the
generator is absent. Install it when you want the schema:

```bash
composer require --dev zircote/swagger-php
```

## Layout

```
src/
├── Contract/
│   ├── TaggedCacheInterface.php    PSR-16 plus setTagged() and clearByTag()
│   ├── TokenInterface.php          What a token guarantees
│   └── TokenServiceInterface.php   What the service guarantees
├── Exception/
│   └── TokenStorageException.php   Raised when the cache refuses a write
├── Model/
│   ├── Token.php                   Immutable token with a random UUID v4 identifier
│   └── TokenIdentifier.php         Validated request object
└── TokenService.php                The service itself
```

## Requirements

- PHP 8.2 or newer, tested on 8.2, 8.3, 8.4 and 8.5
- Symfony Serializer, Uid and Validator, 7.4 or newer, or 8.0 or newer. Symfony
  8 itself needs PHP 8.4, so PHP 8.2 and 8.3 resolve to the 7.4 line
- A PSR-16 cache
- Docker and Docker Compose, for the functional tests that need a server

## Testing

```bash
composer install

composer analyse           # PHPStan at level max
composer test:unit         # PHPUnit with coverage
composer test:bdd:memory   # Behat, no container needed
composer test:bdd          # Behat against every cache, starts the containers
composer test:mutation     # Infection
composer test              # all of the above
composer lint              # coding standard
```

Every one of these runs on every CI job, across PHP 8.2 to 8.5 and against both
the newest and the lowest allowed dependency resolution. Nothing is gated to a
single job, so a failure that only shows up under one version or one resolution
still fails the build.

The suite holds four lines and the build fails below any of them: 100% line
coverage, 100% method coverage, a mutation score index of 100%, and PHPStan at
level max over `src`, `tests` and `example.php`. There is no PHPStan baseline
and no ignored error, so a new finding fails the build instead of being
recorded and forgotten. Coverage is published to
[Codecov](https://codecov.io/gh/ideaconnect/single-use-token-manager) from each
job, flagged by PHP version and dependency mode.

### What the functional tests cover

The scenarios in `features/service` run unchanged against three different
caches, because the service is meant to need nothing beyond PSR-16:

| Suite | Cache | Tagging | Needs a server |
| --- | --- | --- | --- |
| `model` | none | not applicable | no |
| `array_cache` | `Psr16Cache` over `ArrayAdapter` | no | no |
| `redis_no_tags` | `Psr16Cache` over `RedisAdapter` | no | Redis on 6379 |
| `rapid_cache_tags` | `RapidCacheClient` | yes | Valkey on 6380 |

Run one at a time with `./vendor/bin/behat --suite=rapid_cache_tags`. The
containers behind the last two come from `docker-compose.yml`:

```bash
composer cache:start   # start Redis and Valkey
composer cache:stop    # stop them
composer cache:clean   # stop them and drop the volumes
```

## Changelog

[CHANGELOG.md](CHANGELOG.md) records what changed in each release. If you are
coming from 1.x under the old `GryfOSS` namespace, it also carries the upgrade
steps.

## Licence

BSD 3-Clause. See [LICENSE](LICENSE).
