# Changelog

All notable changes to this project are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[semantic versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0]

A security release as much as a rename. Two of the changes below fix cases where
the library did not deliver the single-use guarantee it exists to provide, so
upgrading is worth doing even if the rename is unwelcome. See
[Upgrading](#upgrading-from-1x) for the mechanical steps.

### Security

- **Token identifiers now come from a CSPRNG.** They were UUID v6, which is time
  ordered and carries a node Symfony computes once per process and then repeats
  on every token that process issues. Under php-fpm one leaked token disclosed
  that node for thousands of others, leaving little beyond the clock to guess.
  Identifiers are now UUID v4, built from `random_bytes()`. UUID v7 would not
  have helped: Symfony seeds it once and increments.
- **A token can no longer be redeemed twice by simultaneous callers**, on a cache
  that supports it. `consumeToken()` read with `get()` and removed with
  `delete()`, and every caller arriving between the two redeemed the same token.
  Eight concurrent processes against Redis all succeeded. Where the cache can
  read and remove in one operation, `consumeToken()` now does that and exactly
  one caller wins. See [Added](#added) and the README's Concurrency section.
- **A refused removal is no longer discarded.** It left the token redeemable
  while the caller was told it had been spent. `consumeToken()` now raises
  `TokenRemovalException`.

### Added

- `Contract\AtomicCacheInterface`, declaring `take()`: read an entry and remove
  it in one operation. Detected on the method rather than the interface, so any
  cache carrying a compatible `take()` is used as-is. Backing it is usually one
  command, such as Redis `GETDEL`.
- `Exception\TokenRemovalException`, raised when the cache refuses to remove a
  redeemed token.
- An optional constructor namespace on `TokenService`, prefixing both the cache
  key and the tag, so two services can share one pool without colliding. A
  namespace containing a PSR-16 reserved character is refused at construction.
- `tests/Concurrency/single-use-under-load.php` and `composer test:concurrency`,
  which set eight processes on one token and assert that an atomic cache yields
  exactly one winner while a plain one does not.
- A Behat suite running the existing feature file against an atomic cache, and a
  CI job that resolves the declared dependency floor.

### Changed

- **Namespace is now `IDCT\SingleUseTokenManager`**, with interfaces under
  `Contract\` and models under `Model\`.
- **The package is `idct/single-use-token-manager`**, was
  `gryfoss/single-use-token-manager`.
- **Storage is PSR-16** (`Psr\SimpleCache\CacheInterface`), was PSR-6
  (`Psr\Cache\CacheItemPoolInterface`).
- **`createToken()` throws `TokenStorageException`** when the cache refuses the
  write, instead of returning a token that was never stored.
- **`TokenIdentifier` trims before its blank check**, so an identifier of
  whitespace only is rejected rather than passed on as a certain cache miss.
- `Token::TYPE_ERROR` now describes the real rule. It said "alphanumeric" while
  rejecting uppercase.
- The licence is BSD 3-Clause, was MIT.

### Removed

- `zircote/swagger-php` is no longer a runtime requirement. The OpenAPI
  attribute on `TokenIdentifier` is inert unless something reflects over it, so
  the package is a suggestion now. Install it when you want the schema.

### Fixed

- The `zircote/swagger-php` constraint allowed `^4.0`, but `OpenApi\Attributes`
  does not exist before 4.1.0, so the declared floor never worked.
- Development scaffolding is no longer shipped in the Composer dist archive.

## Upgrading from 1.x

### Namespaces

```
GryfOSS\SingleUseTokenManager\TokenService          -> IDCT\SingleUseTokenManager\TokenService
GryfOSS\SingleUseTokenManager\Token                 -> IDCT\SingleUseTokenManager\Model\Token
GryfOSS\SingleUseTokenManager\TokenIdentifier       -> IDCT\SingleUseTokenManager\Model\TokenIdentifier
GryfOSS\SingleUseTokenManager\TokenInterface        -> IDCT\SingleUseTokenManager\Contract\TokenInterface
GryfOSS\SingleUseTokenManager\TokenServiceInterface -> IDCT\SingleUseTokenManager\Contract\TokenServiceInterface
```

### The cache

The constructor takes a PSR-16 cache. To keep a Symfony PSR-6 adapter, wrap it:

```php
use Symfony\Component\Cache\Psr16Cache;

- $service = new TokenService($adapter);
+ $service = new TokenService(new Psr16Cache($adapter));
```

### New exceptions

`createToken()` can throw `TokenStorageException` and `consumeToken()` can throw
`TokenRemovalException`. Both mean the cache refused an operation the caller was
previously told had succeeded. Let them surface, or catch them:

```php
use IDCT\SingleUseTokenManager\Exception\TokenRemovalException;
use IDCT\SingleUseTokenManager\Exception\TokenStorageException;

try {
    $token = $service->createToken('reset', ['user_id' => 123]);
} catch (TokenStorageException $exception) {
    // The token was never stored. Do not send it to anyone.
}
```

### Identifier format

Identifiers are now UUID v4 rather than v6. Tokens already sitting in a cache
keep working, since lookup is by exact key, so no migration is needed. Anything
that pinned the version, such as a validation pattern matching `-6` in the third
group, has to be updated.

### Getting the concurrency guarantee

If more than one request can present the same token at once, use a cache that
can take atomically, otherwise several of them can still redeem it. See the
README's Concurrency section.

### If you validated a token identifier

`TokenIdentifier` now trims before checking for blank, so an identifier of only
whitespace is rejected where it previously passed. That was always a certain
cache miss, but code relying on the old behaviour will see a new violation.

[Unreleased]: https://github.com/ideaconnect/single-use-token-manager/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/ideaconnect/single-use-token-manager/releases/tag/v2.0.0
