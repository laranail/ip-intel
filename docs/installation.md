# Installation

## Requirements

| | |
|---|---|
| PHP | `^8.4.1 \|\| ^8.5` |
| Laravel | `^13.0` |
| Depends on | `laranail/atlas`, `laranail/package-tools` |

## Install

```bash
composer require laranail/ip-intel
```

laranail packages resolve through **git VCS repositories**, not Packagist, and
Composer ignores a dependency's own `repositories` — so declare the full
transitive closure in your root `composer.json`:

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/laranail/ip-intel" },
  { "type": "vcs", "url": "https://github.com/laranail/atlas" },
  { "type": "vcs", "url": "https://github.com/laranail/package-tools" },
  { "type": "vcs", "url": "https://github.com/laranail/console" }
]
```

Miss one and Composer reports the *direct* dependency as unresolvable, which
sends you looking in the wrong place.

## It works with no configuration at all

```php
use Simtabi\Laranail\IpIntel\Facades\IpIntel;

IpIntel::forRequest()->countryCode;   // 'KE'
```

The default chain is `['edge', 'local']` — both free, neither requiring a key.
If you are behind Cloudflare, Vercel, Fastly or CloudFront, the edge header
answers almost every country question you will ever ask, and **no network call
happens at all**.

## The offline tier needs a table

`local` reads `laranail/atlas`' registry table, which atlas builds rather than
ships:

```bash
php vendor/laranail/atlas/tools/build-ip-table.php
```

Then switch it on in atlas' config:

```php
// config/laranail/atlas.php
'ip' => ['enabled' => true],
```

Without it the `local` source contributes nothing, and a country question with
no edge header returns `Outcome::NotFound`. That is not silent — see
[outcomes](tools/outcomes.md).

## The metered tier is opt-in

City, ASN and threat signals are **not derivable from registry data**, so they
need a paid source:

```dotenv
IP_INTEL_IPAPI_KEY=…
```

```php
'chain' => ['edge', 'local', 'ipapi'],
```

Leave it out of the chain and nothing tries to reach it. A missing key is a
configuration state, not an exception: the driver reports `isAvailable()` false
and the chain skips it.

## What you can publish

```bash
php artisan vendor:publish --tag="laranail::ip-intel-config"
```

→ `config/laranail/ip-intel.php`, read as `config('laranail.ip-intel.*')`.

## The API is off by default

No routes are registered unless you switch it on, and *off means absent* rather
than registered-then-blocked. See [the API](tools/api.md).

---
[← Docs index](../README.md#documentation)
