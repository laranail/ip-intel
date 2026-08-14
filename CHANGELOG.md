# Changelog

All notable changes to `laranail/ip-intel` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-08-14

### Added

Rebuilt. The tree this replaces did not autoload at all: twelve files declared
`Simtabi\Laranail\GeoIp\` under a PSR-4 root of `Simtabi\Laranail\IPIntel\`, `extra.laravel` named a
provider class that did not exist, and `laranail/package-tools ^3.0` could not resolve against a
package at `v0.1.0`. It also carried `"license": "proprietary"`, `"type": "project"` and a hardcoded
`"version": "1.0.0"`.

- **Capabilities are types.** `ResolvesCountry`, `ResolvesAsn`, `ResolvesCity` and `DetectsThreats`
  are separate interfaces, so `$driver instanceof ResolvesAsn` cannot disagree with the
  implementation. The sources genuinely differ — an edge header knows the country and nothing else —
  and one `locate()` returning a DTO with twenty nullable fields made "this source cannot answer
  that" look like "this address has no city".

- **A resolver chain, not a driver.** Edge header → offline table → remote, asked in order and
  stopped as soon as the question is answered. **A country lookup behind a CDN makes zero network
  calls**, and `$result->madeNetworkCall` records it rather than claiming it.

- **`IpIntelResult` with five outcomes** where the original returned bare `null` for everything: a
  reserved address, a registry gap, a dead key, a timeout and a disabled feature need five different
  responses, and collapsing them meant an application either treated a broken integration as an
  unknown visitor forever or alerted on every unroutable address.

- **Opt-in REST API.** Off by default, and off means the routes are **never registered** — a
  disabled endpoint that still shows in `route:list` is one loosened middleware group away from
  being live. `FormRequest` validation reusing the same `ValidIp` rule consumers get, `JsonResource`
  output, 503 for a downed source rather than a 200 with an empty body.

### Fixed

The remote driver carried six defects, each now a line of the replacement:

1. `"{$baseUrl}{$ip}"` interpolated an address — which arrives from a request header — into a URL
   path, unvalidated and unencoded.
2. No timeout, so a hung provider hung the request calling it.
3. No retry, so a single 502 was a failed lookup.
4. `fromArray()` read `$data['ip']` and `$data['type']` unguarded, so a partial payload was a
   TypeError.
5. A null access key was a TypeError rather than a message.
6. Every failure returned `null`, including `200 {"success": false}` — which is what the service
   answers for a dead key or an exhausted quota, so a broken integration reported every visitor as
   unknown and nothing ever surfaced it.

Threat signals are **nullable, not false**: `false` means a source looked and found nothing, `null`
means nobody looked. A guard that treats an unanswered question as "not a proxy" stops working the
day its provider key expires, silently.
