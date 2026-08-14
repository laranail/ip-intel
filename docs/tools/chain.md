# The resolver chain

`ResolverChain` asks each source in turn, stopping as soon as the question is
answered.

## Why a chain rather than a driver

Because the cheap answer is usually already available.

An application behind Cloudflare is handed the country in a request header
before any code runs; asking a metered API for it is paying for something it was
given. That is not a hypothetical — it is why the implementation this replaces
was never wired into anything: the application already read `CF-IPCountry` and
had no use for a paid lookup.

```
edge header  →  no lookup at all, free, already computed
local table  →  offline, registry data, country only
remote       →  metered, and the only source for city/ASN/threats
```

A single configurable "driver" cannot express that, because **the right source
depends on the question**. The header can answer country and nothing else, so a
chain that treated it as *the* driver would make ASN unavailable.

## Capability, not configuration

```php
$driver instanceof ResolvesAsn
```

Each step asks what a driver **is**, derived from the interfaces it implements,
rather than consulting a list of what it claims:

| Contract | Answers |
|---|---|
| `ResolvesCountry` | an ISO country code |
| `ResolvesCity` | a `PlaceInfo` — city, region, postcode, coordinates |
| `ResolvesAsn` | an `AsnInfo` |
| `DetectsThreats` | `ThreatSignals` |

A source that cannot answer is **skipped without being called**. That is what
makes the promise below true rather than approximate, and it is why a driver
cannot claim a capability it has not implemented — the claim *is* the
implementation.

## The promise

> **A country-only question, with an edge header present, makes zero network
> calls.**

```php
$result = IpIntel::country('8.8.8.8');

$result->madeNetworkCall;   // false
```

`madeNetworkCall` records it and a test asserts it. `canContribute()` is the
check that makes it hold: it stops a remote source being called for a country an
edge header already gave us, before any budget is spent.

## The two entry points

```php
IpIntel::country($address);   // country only — the cheap path
IpIntel::full($address);      // everything any configured source can supply
```

`full()` is the one that reaches a metered source, and only for the parts still
missing. If the edge answered the country, the remote driver is asked for ASN,
place and threats — not for the country it would also have returned.

## Enrichment happens offline

```php
$result->country;   // a full CountryRecord
```

Name, flag, currencies, languages and continent come from `laranail/atlas`, not
from the provider. They are catalogue facts held locally, and paying an API for
fields we already have would be absurd.

The practical effect: a **free** country-only lookup fills the whole location
graph.

## Errors do not stop the chain

A `SourceUnavailable` is recorded and the chain continues. A later source may
still answer; if none does, the result is `Unavailable` with the reason rather
than `NotFound`, so a broken integration is distinguishable from an unknown
address. See [outcomes](outcomes.md).

## Adding a source

```php
use Simtabi\Laranail\IpIntel\Contracts\ResolvesCountry;
use Simtabi\Laranail\IpIntel\Facades\IpIntel;

IpIntel::extend('acme', fn (): ResolvesCountry => new AcmeDriver($client));
```

Then add `'acme'` to the chain. `extend()` takes a **closure, not a class
name** — deliberately not `Illuminate\Support\Manager`, which interpolates a
driver name into a method call, and that name arrives from a config file an
operator edits.

Implement only the capabilities you actually have. A driver that implements
`ResolvesCountry` and nothing else is a complete, correct driver, and the chain
will never ask it for an ASN.

---
[← Docs index](../../README.md#documentation)
