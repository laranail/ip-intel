# Getting started

## Three calls

```php
use Simtabi\Laranail\IpIntel\Facades\IpIntel;

IpIntel::forRequest();          // about the caller
IpIntel::country('8.8.8.8');    // country only — the cheap path
IpIntel::full('8.8.8.8');       // everything any configured source can supply
```

All three return an `IpIntelResult`. None returns `null`.

## The chain, and why it is a chain

```
edge header  →  no lookup at all, free, already computed
local table  →  offline, registry data, country only
remote       →  metered, and the only source for city/ASN/threats
```

Sources are asked in order until the question is answered, and **that order is
the cost policy**.

An application behind Cloudflare is handed the country in a request header
before any code runs. Asking a metered API for it is paying for something you
were given — which is exactly why the implementation this replaces was never
wired into anything.

A single configurable "driver" cannot express that, because the right source
depends on the **question**: the header can answer country and nothing else, so
a chain that treated it as *the* driver would make ASN unavailable.

## Capability is a type

```php
$driver instanceof ResolvesAsn
```

Each step asks what a driver *is*, not what it claims. A source that cannot
answer is skipped **without being called**, which is what makes this true rather
than approximate:

```php
$result = IpIntel::country('8.8.8.8');

$result->madeNetworkCall;   // false, when an edge header answered
```

A test asserts exactly that.

## Reading a result

```php
$result = IpIntel::forRequest();

$result->outcome;          // Outcome enum — five cases
$result->countryCode;      // ?string
$result->country;          // ?CountryRecord — enriched from atlas, offline
$result->asn;              // ?AsnInfo
$result->place;            // ?PlaceInfo
$result->threats;          // ?ThreatSignals
$result->sources;          // list<string> — who actually contributed
$result->madeNetworkCall;  // bool
```

### Five outcomes, where the original had one null

| `Outcome` | Means | Your move |
|---|---|---|
| `Found` | Answered | Use it |
| `Reserved` | RFC 1918, loopback, link-local | Nothing to look up; this is normal in dev |
| `NotFound` | A genuine registry gap | Treat as unknown |
| `Unavailable` | **A source is broken** — dead key, 5xx | Alert somebody |
| `Disabled` | Switched off in config | Nothing |

```php
if ($result->needsAttention()) {
    // Unavailable — the address is fine, your integration is not.
}
```

The implementation this replaces returned bare `null` for every one of those, so
"we do not know where this address is" and "the API key expired three weeks ago"
were indistinguishable to every caller.

## Country data comes from atlas, offline

```php
$result->country?->name;         // 'Kenya'
$result->country?->flag();       // '🇰🇪'
$result->country?->currencies;   // ['KES']
```

Name, flag, currencies and continent are catalogue facts held locally. Paying an
API for fields `laranail/atlas` already has would be absurd, so a cheap
country-only lookup fills the whole location graph with no extra request.

## Where to go next

- [Configuration](configuration.md) — every key
- [The resolver chain](tools/chain.md) — how sources are chosen and skipped
- [Outcomes](tools/outcomes.md) — the five cases and caching
- [Drivers](tools/drivers.md) — what each source can and cannot answer
- [The REST API](tools/api.md) — opt-in

---
[← Docs index](../README.md#documentation)
