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

## <a name="documentation"></a>Documentation

Full documentation is at
**[opensource.simtabi.com/documentation/laranail/ip-intel](https://opensource.simtabi.com/documentation/laranail/ip-intel/)**.

## License

MIT. See [LICENSE](LICENSE).
