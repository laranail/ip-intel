# The REST API

Two read-only `GET` endpoints, off by default.

## Switching it on

```php
'api' => [
    'enabled'    => env('IP_INTEL_API', true),
    'prefix'     => env('IP_INTEL_API_PREFIX', 'api/ip-intel'),
    'version'    => 'v1',
    'middleware' => ['api', 'throttle:60,1'],
],
```

**Off means the routes are never registered.** A disabled API that still appears
in `route:list` is one loosened middleware group away from being live, and
nobody reviewing that change would think to look in a package's config. Verify:

```bash
php artisan route:list | grep ip-intel   # nothing, while disabled
```

> The shipped middleware is a **rate limit, not an authorisation**. This
> endpoint answers questions about arbitrary addresses using a key you are
> paying for; set the middleware to whatever your authentication actually is
> before exposing it.

## Endpoints

Relative to `{prefix}/{version}` — by default `api/ip-intel/v1`.

| Method | Path | Answers |
|---|---|---|
| `GET` | `/lookup?ip=…` | About an address |
| `GET` | `/me` | About the caller |

Add `&full=1` to `/lookup` for city, ASN and threats — which reaches a metered
source, so it is opt-in per request rather than the default.

## Status codes carry meaning

| Code | When |
|---|---|
| `200` | Answered — **including** `reserved` and `not_found` |
| `422` | The `ip` parameter is missing or is not an address |
| `503` | A source is down |
| `405` | You used a verb other than `GET` |

### 200 for a reserved address

```json
{"data": {"outcome": "reserved", "country_code": null}}
```

The question was well-formed and the answer is "nowhere". A 404 would say the
address does not exist.

### 503 for a broken source

```json
{"data": {"outcome": "unavailable"}}
```

Retryable and server-side — **not** a 200 with an empty body, which a client
would cache as "this address is unknown" and never ask about again. That is the
same distinction `Outcome` draws internally, surfaced over HTTP.

## Validation

A `FormRequest` with the `ValidIp` rule, so a malformed address is a **422
naming the field** rather than a 500 from somewhere inside a driver.

Rejected: `not-an-ip`, `256.1.1.1`, an empty string — and `01.02.03.04`, which
most of the internet reads as octal `1.2.3.4`, so accepting it means a blocklist
and a resolver disagree about where it points.

`ValidIp` is exported for your own forms.

---
[← Docs index](../../README.md#documentation)
