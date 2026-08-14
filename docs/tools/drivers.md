# Drivers

Three ship. Each implements only the capabilities it genuinely has.

| Driver | Country | City | ASN | Threats | Network |
|---|:---:|:---:|:---:|:---:|---|
| `edge` | ✓ | | | | none |
| `local` | ✓ | | | | none |
| `ipapi` | ✓ | ✓ | ✓ | ✓ | metered |

## `edge` — the reverse proxy already worked it out

Reads the country header your CDN sets:

| Header | Set by |
|---|---|
| `CF-IPCountry` | Cloudflare |
| `CloudFront-Viewer-Country` | AWS CloudFront |
| `X-Vercel-IP-Country` | Vercel |
| `Fastly-Geo-Country` | Fastly |

Free, no lookup, no network call. If you are behind any of these, this answers
almost every country question you will ever ask.

### It refuses the sentinels

Cloudflare sends `XX` for unknown and `T1` for Tor. **Both pass a naive
two-letter check and neither is a country.** So do `KEN` (three letters) and
`1E`. All are rejected.

### It only answers about the request that carried it

```php
IpIntel::forRequest($request)->countryCode;   // 'KE' — the caller
IpIntel::country('1.1.1.1')->countryCode;     // null — not this request
```

A header describes the client that sent it. Using it for a different address
would be confidently wrong rather than merely missing, and confidently wrong is
the failure mode this package exists to remove.

## `local` — the offline registry table

Reads `laranail/atlas`' IP-to-country table. Free, offline, **country only**.

That limit is honest rather than unfinished: regional registry delegation data
records which country an allocation was made in, and nothing else. City, ISP and
VPN status are not in it and cannot be derived from it.

Needs atlas' `ip.enabled` and its table built — see
[installation](../installation.md#the-offline-tier-needs-a-table).

## `ipapi` — metered, and the only source for the rest

The paid tier, for the questions registry data cannot answer. Implements all
four capabilities because it genuinely has four.

### One request per address

Four capability methods read one payload, memoised per instance. Without that,
asking an address for its country, ASN, place and threats would be **four billed
calls**.

The memo is **instance state, not static**. A static memo outlives the request
under Octane and leaks between tests, and an IP's answer is not a
process-wide constant.

### What was wrong with the version this replaces

Six things, each of which is a line in the current driver:

1. **`"{$baseUrl}{$ip}"`** — the address was interpolated into a URL path
   unvalidated and unencoded, and it arrives from a request header. It now takes
   a parsed `IpAddress` and passes it as a URL *parameter* the client encodes.
2. **No timeout** — a hung provider hung the request that called it.
3. **No retry** — a single 502 was a failed lookup.
4. **`fromArray()` read `$data['ip']` and `$data['type']` unguarded** — a
   partial payload was a `TypeError` rather than a missing field.
5. **A null access key was a `TypeError`**, not a message.
6. **Every failure returned bare `null`** — a dead key and an unknown address
   were indistinguishable.

### `success: false` is not data

The service answers **200** with `{"success": false}` for a rejected key or an
exhausted quota. Treating that as data is how a broken integration reports every
visitor as unknown, indefinitely, while returning HTTP 200 to your monitoring.

It raises `SourceUnavailable::rejected()` with the service's own reason.

### Absent stays absent

```php
$signals->isProxy;   // ?bool — null means "the provider did not say"
```

`(bool) null` is `false`, which would turn "not stated" into "not a proxy" — the
exact collapse `ThreatSignals` exists to prevent. Every signal is nullable and
stays that way.

---
[← Docs index](../../README.md#documentation)
