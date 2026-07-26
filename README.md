# Single Use Token Manager

[![CI](https://github.com/ideaconnect/single-use-token-manager/actions/workflows/ci.yml/badge.svg)](https://github.com/ideaconnect/single-use-token-manager/actions/workflows/ci.yml)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg?style=flat)](https://github.com/ideaconnect/single-use-token-manager/actions/workflows/ci.yml)
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
# Redis or Valkey, with tag support
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

$token->getUid();     // '1efb1c4e-0f7a-6c1a-9b2f-0242ac120002'
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
The identifier is a UUID v6, which is time ordered, so tokens issued close
together sit close together in the cache index. Expiry is the cache's job: a
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
| `createToken(string $type, mixed $payload = null, ?int $ttl = null): TokenInterface` | Issues a token and stores it. Throws `InvalidArgumentException` for an unusable type and `TokenStorageException` when the cache refuses the write. |
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

`tests/Concurrency/single-use-under-load.php` demonstrates both halves. It lines
eight processes up on one token and asserts that an atomic cache yields exactly
one winner while a plain one does not, so neither this section nor the guarantee
can drift without the build noticing:

```
$ composer test:concurrency
atomic cache:     1 of 8 redeemers won
plain PSR-16:     8 of 8 redeemers won
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
│   ├── Token.php                   Immutable token with a UUID v6 identifier
│   └── TokenIdentifier.php         Validated request object
└── TokenService.php                The service itself
```

## Requirements

- PHP 8.2 or newer, tested on 8.2, 8.3, 8.4 and 8.5
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

The suite holds four lines and the build fails below any of them: 100% line
coverage, 100% method coverage, a mutation score index of 100%, and PHPStan at
level max over `src`, `tests` and `example.php`. There is no PHPStan baseline
and no ignored error, so a new finding fails the build instead of being
recorded and forgotten.

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

## Licence

BSD 3-Clause. See [LICENSE](LICENSE).
