# Configuration

Every key in `config/laranail/ip-intel.php`, read as
`config('laranail.ip-intel.*')`.

## `enabled`

```php
'enabled' => env('IP_INTEL_ENABLED', true),
```

Off means lookups return an explicit `Outcome::Disabled` rather than a null, so
a caller can tell "switched off" from "unknown address". A feature flag that
makes a service indistinguishable from a broken one is a debugging problem
waiting to happen.

## `chain`

```php
'chain' => ['edge', 'local'],
```

Sources are asked **in order** until the question is answered, and this order
*is* the cost policy:

| Source | Cost | Answers |
|---|---|---|
| `edge` | free, no network call | country |
| `local` | free, offline | country |
| `ipapi` | metered, remote | country, city, ASN, threats |

A source that cannot answer the question asked is **skipped without being
called** — capability is a type, so the chain knows before it spends a request.

The default omits `ipapi` deliberately. Add it only when you need something
registry data cannot give you, and understand that adding it to the chain does
not mean every lookup reaches it: a country question answered by the edge stops
before it.

## `sources.ipapi`

```php
'ipapi' => [
    'key'      => env('IP_INTEL_IPAPI_KEY'),
    'base_url' => env('IP_INTEL_IPAPI_URL', 'https://api.ipapi.com/api'),
    'timeout'  => (int) env('IP_INTEL_IPAPI_TIMEOUT', 5),
    'retries'  => (int) env('IP_INTEL_IPAPI_RETRIES', 2),
],
```

A **missing key is a configuration state, not an exception**: `isAvailable()`
returns false and the chain skips the driver. The version this replaces made a
null key a `TypeError`.

`timeout` and `retries` both exist because the original had neither — a hung
provider hung the request that called it, and a single 502 was a failed lookup.
Only a connection failure or a 5xx is retried; a 4xx is an answer, and retrying
a rejected key just spends the budget.

> The access key goes in the query string, because that is what the service
> accepts. It is redacted from every log line this package writes — including
> exception messages, which is why a failure logs the exception *class* rather
> than its message: the HTTP client embeds the full request URL.

## `cache`

```php
'cache' => [
    'enabled' => env('IP_INTEL_CACHE', true),
    'store'   => env('IP_INTEL_CACHE_STORE'),
    'ttl'     => (int) env('IP_INTEL_CACHE_TTL', 1440),   // minutes
    'prefix'  => 'laranail.ip-intel',
],
```

**Only cacheable outcomes are stored.** A failure never is:

| Outcome | Cached |
|---|---|
| `Found`, `Reserved`, `NotFound` | yes |
| `Unavailable`, `Disabled` | **no** |

Caching a dead key's answer for a day means the outage outlives the fix, and the
person who fixes it sees no change. That distinction is the whole reason
`Outcome` is an enum with `isCacheable()` on it rather than a boolean somewhere.

The original had no caching at all, so every lookup was a paid call.

## `api`

```php
'api' => [
    'enabled'    => env('IP_INTEL_API', false),
    'prefix'     => env('IP_INTEL_API_PREFIX', 'api/ip-intel'),
    'version'    => 'v1',
    'middleware' => ['api', 'throttle:60,1'],
],
```

Off by default, and **off means the routes are never registered** — a disabled
API should not appear in `route:list` at all, so there is nothing to expose by
loosening middleware later.

> The shipped middleware is a rate limit, **not an authorisation**. Set it to
> whatever your authentication actually is before exposing this.

## Environment variables

| Variable | Default |
|---|---|
| `IP_INTEL_ENABLED` | `true` |
| `IP_INTEL_IPAPI_KEY` | *(none — driver unavailable)* |
| `IP_INTEL_IPAPI_URL` | `https://api.ipapi.com/api` |
| `IP_INTEL_IPAPI_TIMEOUT` | `5` |
| `IP_INTEL_IPAPI_RETRIES` | `2` |
| `IP_INTEL_CACHE` | `true` |
| `IP_INTEL_CACHE_STORE` | *(default store)* |
| `IP_INTEL_CACHE_TTL` | `1440` |
| `IP_INTEL_API` | `false` |
| `IP_INTEL_API_PREFIX` | `api/ip-intel` |

---
[← Docs index](../README.md#documentation)
