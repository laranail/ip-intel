# Contributing

Thanks for helping improve `laranail/ip-intel`.

## Getting set up

```bash
composer install
composer test
composer lint
```

Requires PHP `^8.4.1 || ^8.5`. **No API key is needed to develop or test** —
every test uses a fake driver, and a test that reached a real provider would be
a test that fails when somebody's quota runs out.

## What must pass

- **Style** — `composer pint-fix`.
- **Static analysis** — `composer phpstan`.
- **Rector** — `composer rector` (dry run), pinned to the `php84` set.
- **Tests** — `composer test` (Pest).

## The two properties that must not regress

Both would fail silently, so both are asserted directly:

```php
// 1. Routes are ABSENT while the API is off — not registered-then-blocked.
expect(collect(app('router')->getRoutes()->getRoutes())
    ->filter(fn ($r) => str_contains((string) $r->uri(), 'ip-intel')))->toBeEmpty();

// 2. A country question makes no network call when an edge header answered.
expect($result->madeNetworkCall)->toBeFalse()
    ->and($remote->calls)->toBe(0);
```

The second asserts **both** the flag and a call counter on the test double. The
flag alone could be satisfied by a driver that simply never sets it.

## Adding a driver

Implement **only** the capabilities you actually have:

```php
final class AcmeDriver implements ResolvesCountry
{
    public function name(): string { return 'acme'; }
    public function isAvailable(): bool { return $this->key !== null; }
    public function isRemote(): bool { return true; }
    public function country(IpAddress $address): ?string { … }
}
```

A driver implementing `ResolvesCountry` and nothing else is complete and
correct. The chain reads `instanceof`, so it will never ask for an ASN, and
adding a capability interface you cannot honour is the one thing that breaks the
design.

Three rules that come from the failures this package was built to fix:

- **A missing key is `isAvailable() === false`, never an exception.** The chain
  skips an unavailable source; it does not crash on one.
- **A failure throws `SourceUnavailable`, never returns null.** Null means "no
  answer"; a broken source is a different thing and the caller needs to tell.
- **Never log the exception message from an HTTP client.** It embeds the full
  request URL, which carries the access key. Log `$e::class`.

## Registering it

`extend()` takes a **closure, not a class name**. Deliberately not
`Illuminate\Support\Manager`, which interpolates a driver name into a method
call — and that name arrives from a config file an operator edits, or in a
multi-tenant install from a database row.

## Nullable means "not stated"

Every threat signal is `?bool`. `(bool) null` is `false`, which turns "the
provider did not say" into "not a proxy" — the exact collapse `ThreatSignals`
exists to prevent. Keep them nullable all the way to the caller.

## Commits and PRs

Subject ≤ 72 characters, imperative mood. The body explains *why*. No AI
attribution.

---

Report vulnerabilities per [SECURITY.md](SECURITY.md) rather than in an issue.
