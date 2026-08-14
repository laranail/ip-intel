# Geolocate without paying for it

The default configuration already does this. This page is about knowing that,
and proving it.

## The default chain is free

```php
'chain' => ['edge', 'local'],
```

Neither source costs anything and neither makes a network call:

- **`edge`** reads a header your CDN already set — `CF-IPCountry` and friends.
- **`local`** reads `laranail/atlas`' offline registry table.

`ipapi` is deliberately **not** in the default chain. Adding it is a decision to
start paying, and it should look like one.

## Prove it

```php
$result = IpIntel::country($request->ip());

$result->madeNetworkCall;   // false
$result->sources;           // ['edge']
```

`sources` names who actually contributed, not who was configured. If it says
`['edge']`, nothing else was called — the chain skipped the rest because the
question was already answered.

## Set the local tier up

The offline table is built rather than shipped, because registry data changes
daily:

```bash
php vendor/laranail/atlas/tools/build-ip-table.php
```

```php
// config/laranail/atlas.php
'ip' => ['enabled' => true],
```

Skip this if you are behind a CDN — the edge header will answer nearly
everything, and `local` is the fallback for the requests that arrive without
one.

## What you cannot get for free

City, ISP, ASN organisation, VPN and proxy detection. **None of it is in
registry data**, and none can be derived from it — that is a property of the
data, not a limitation of this package.

If you need them, add `ipapi` to the chain and use `full()` for the requests
that actually need it:

```php
IpIntel::country($ip);   // free path, always
IpIntel::full($ip);      // reaches the metered source — use deliberately
```

Note that even `full()` only asks the remote source for what is still missing.
If the edge already gave the country, the paid call is for ASN, place and
threats — not for a country you were handed for nothing.

## Cache the free path anyway

```php
'cache' => ['enabled' => true, 'ttl' => 1440],
```

It is free but not instant, and a `Found` or `Reserved` answer for an address
does not change minute to minute. Failures are never cached, so an outage does
not outlive its fix.

---
[← Docs index](../../README.md#documentation)
