# Architecture

## A chain, not a driver

The production reality this is built around: **Cloudflare already answers
country-level, for free, at the edge.** That is precisely why the
implementation this package replaces was never wired into anything — the
application read `CF-IPCountry` and had no use for a paid lookup.

So the entry point is a chain, and the order is the cost policy:

```
Request → edge header → cache → local dataset (atlas) → remote (optional)
        → atlas enrichment (name, flag, currency, languages, calling code)
```

A single configurable driver cannot express this, because the right source
depends on the **question**, not on configuration. The header answers country
and nothing else; treating it as *the* driver would make ASN unavailable.

## Capability is a type

```php
src/Contracts/
  IntelDriver.php       the base — name(), isAvailable(), isRemote()
  ResolvesCountry.php   ResolvesCity.php   ResolvesAsn.php   DetectsThreats.php
```

Granular interfaces, not one god-interface with a `supports()` array. The chain
asks `$driver instanceof ResolvesAsn`, so a driver **cannot claim what it has
not implemented** — the claim is the implementation.

Two things follow:

- A source that cannot answer is skipped **without being called**, which is what
  makes "a country question makes zero network calls" true rather than
  approximate.
- `EdgeHeaderDriver` implements `ResolvesCountry` and nothing else, so asking it
  for an ASN is not a silently-empty DTO — it is never asked.

That is also the fix for the shape problem. The version this replaces had one
DTO with twenty nullable fields, because one interface had to cover a header and
a paid API at once.

## Five outcomes where there was one null

`Outcome` distinguishes `Found`, `Reserved`, `NotFound`, `Unavailable` and
`Disabled`. The original returned bare `null` for all five, so "unknown address"
and "the key expired three weeks ago" were the same value.

`isCacheable()` lives on the enum, so the rule that **a failure is never cached**
sits with the thing it describes and a new outcome cannot be added without
deciding. Caching a dead key's answer for a day means the outage outlives the
fix.

## Reserved is answered before any source

`10.0.0.1` is in use on millions of networks in every country there is. A lookup
would spend a request to return something misleading — and in development it is
most of your traffic.

## No fallback country, ever

The implementation this replaces defaulted to the United States when a lookup
failed. That is a **confidently wrong answer on every request from localhost**,
and it surfaces as a tax rate, a currency or a consent banner rather than as an
error.

Here it is an explicit `null` with an `Outcome` saying why, which the caller has
to handle.

## Enrichment is offline

Name, flag, currencies, languages and continent come from `laranail/atlas`, not
from the provider. They are catalogue facts held locally, so a cheap
country-only lookup fills the whole location graph without a second request.

The DTOs this package does **not** ship are the point: the version it replaces
had its own `CurrencyData`, `LanguageData` and `TimeZoneData`. One answer per
question, org-wide.

## Where the boundary with atlas is

| | |
|---|---|
| **atlas** | Country from an IP, offline, from registry data. The catalogue |
| **ip-intel** | The chain, the cost policy, city/ASN/threats, the metered tier |

Registry data gives country and the fact of an allocation. City, ISP name and
VPN status need either a commercial feed or our own crawling — so this package
ships the **seam** for them rather than pretending the free tier covers them.

## Secrets

The access key goes in the query string because that is what the service
accepts. Everything else follows from assuming it will leak if it can:

- It is redacted from every log line.
- A request failure logs the exception **class**, never its message — the HTTP
  client embeds the full request URL in that message.
- The class docblock says so, rather than leaving someone to find out from a
  log.

## Routes are absent, not blocked

Off means the routes are never registered. A disabled API sitting in
`route:list` is one loosened middleware group away from being live, and the
person loosening it would have no reason to look in a package's config.

---
[← Docs index](../README.md#documentation)
