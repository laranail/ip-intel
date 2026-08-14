# laranail/ip-intel

[![Packagist](https://img.shields.io/packagist/v/laranail/ip-intel.svg?style=flat-square)](https://packagist.org/packages/laranail/ip-intel)
[![Tests](https://img.shields.io/github/actions/workflow/status/laranail/ip-intel/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/laranail/ip-intel/actions/workflows/tests.yml)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/laranail/ip-intel/static-analysis.yml?branch=main&label=static%20analysis&style=flat-square)](https://github.com/laranail/ip-intel/actions/workflows/static-analysis.yml)
[![License MIT](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

> IP intelligence for Laravel — country, ASN and threat signals behind one resolver chain that
> answers offline first, with an opt-in REST API.

Requires PHP `^8.4.1 || ^8.5` and Laravel `^13.0`. Built on
[`laranail/atlas`](https://opensource.simtabi.com/documentation/laranail/atlas/), which owns the
address parsing and the offline country table.

## Install

```bash
composer require laranail/ip-intel
```

Behind Cloudflare, Vercel, Fastly or CloudFront, a country lookup makes **no network call at all** —
the edge already worked it out and put it in a request header.

## Quick start

```php
use Simtabi\Laranail\IpIntel\Facades\IpIntel;

IpIntel::forRequest();          // about the caller
IpIntel::country('8.8.8.8');    // country only — the cheap path
IpIntel::full('8.8.8.8');       // everything a configured source can supply
```

## The chain is the point

```
edge header  →  no lookup at all, free, already computed
local table  →  offline, registry data, country only
remote       →  metered, and the only source for city/ASN/threats
```

Sources are asked in order until the question is answered, and that order is the cost policy. A
single configurable "driver" cannot express it, because **the right source depends on the
question**: the header answers country and nothing else, so a chain that treated it as *the* driver
would make ASN unavailable.

Capability is a **type** — the chain asks `$driver instanceof ResolvesAsn`, so a source that cannot
answer is skipped without being called. That is what makes this true rather than approximate:

```php
IpIntel::country('8.8.8.8')->madeNetworkCall;   // false
```

## Five outcomes, where a null used to be

| `Outcome` | Means |
|---|---|
| `Found` | A source answered |
| `Reserved` | RFC 1918, loopback — normal in development |
| `NotFound` | A genuine registry gap |
| `Unavailable` | **A source is broken** — dead key, 5xx |
| `Disabled` | Switched off in config |

The implementation this replaces returned bare `null` for every one, so "we do not know where this
address is" and "the API key expired three weeks ago" were the same value — and the second never got
noticed. `$result->needsAttention()` separates them.

It also defaulted to the United States when a lookup failed, which is a confidently wrong answer on
every request from localhost and surfaces as a tax rate rather than as an error.

## <a name="documentation"></a>Documentation

Hosted at **[opensource.simtabi.com/documentation/laranail/ip-intel](https://opensource.simtabi.com/documentation/laranail/ip-intel/)**.

### Guides
- [Installation](docs/installation.md) — the VCS closure, and the two tiers that need setting up
- [Getting started](docs/getting-started.md) — the chain, the five outcomes, reading a result
- [Configuration](docs/configuration.md) — every key and its environment variable
- [Architecture](docs/architecture.md) — why a chain, why capability is a type, where the key goes
- [Release](docs/release.md) — cutting a version, and the path repository to remove first

### Reference
- [The resolver chain](docs/tools/chain.md) — how sources are chosen and skipped
- [Outcomes](docs/tools/outcomes.md) — the five cases, and why a failure is never cached
- [Drivers](docs/tools/drivers.md) — what each source can and cannot answer
- [The REST API](docs/tools/api.md) — opt-in, read-only, two endpoints

### Recipes
- [Geolocate without paying for it](docs/recipes/geolocate-without-paying.md)
- [Detect a login from a new country](docs/recipes/detect-a-new-country-login.md)

## Honest scope

Registry data gives **country and the fact of an allocation**. It does not give city, ISP name, or
VPN/proxy status, and those cannot be derived from it — that is a property of the data, not a
limitation here. Those need a commercial feed, so the package ships the seam for one rather than
pretending the free tier covers it.

## Sister packages

- [`laranail/atlas`](https://opensource.simtabi.com/documentation/laranail/atlas/) — the country catalogue and the offline IP table this builds on

## Contributing & security

Issues and PRs are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities per
[SECURITY.md](SECURITY.md) (opensource@simtabi.com); participation follows the
[Code of Conduct](CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).
