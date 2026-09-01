<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Read as `config('laranail.ip-intel.*')`; publishes to
    | `config/laranail/ip-intel.php`.
    |
    | Off means lookups return an explicit Disabled outcome rather than a null,
    | so a caller can tell "switched off" from "unknown address".
    |
    */

    'enabled' => env('IP_INTEL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | The source chain
    |--------------------------------------------------------------------------
    |
    | Sources are asked in order until the question is answered, and this order
    | IS the cost policy:
    |
    |   edge   the reverse proxy already worked it out (CF-IPCountry and
    |          friends). Free, no lookup, no network call. If you are behind
    |          Cloudflare, Vercel, Fastly or CloudFront, this answers almost
    |          every country question you will ever ask.
    |   local  laranail/atlas' offline registry table. Country only — that is
    |          the honest limit of freely redistributable data, not an
    |          unfinished implementation. Needs `atlas ip.enabled` and its
    |          table built.
    |   ipapi  metered and remote. The only source here for city, ASN and
    |          threat signals, because none of those are derivable from
    |          registry files.
    |
    | A source that cannot answer the question asked is skipped without being
    | called — capability is a type, so the chain knows before it spends a
    | request. A country-only lookup with an edge header present makes zero
    | network calls, and `$result->madeNetworkCall` says so.
    |
    */

    'chain' => ['edge', 'local'],

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    */

    'sources' => [

        'ipapi' => [
            'key' => env('IP_INTEL_IPAPI_KEY'),
            'base_url' => env('IP_INTEL_IPAPI_URL', 'https://api.ipapi.com/api'),
            'timeout' => (int) env('IP_INTEL_IPAPI_TIMEOUT', 5),
            'retries' => (int) env('IP_INTEL_IPAPI_RETRIES', 2),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Only cacheable outcomes are stored. A failure never is: caching a dead
    | key's answer for a day means the outage outlives the fix and the person
    | who fixed it sees no change.
    |
    */

    'cache' => [
        'enabled' => env('IP_INTEL_CACHE', true),
        'store' => env('IP_INTEL_CACHE_STORE'),
        'ttl' => (int) env('IP_INTEL_CACHE_TTL', 1440),
        'prefix' => 'laranail.ip-intel',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP API
    |--------------------------------------------------------------------------
    |
    | Off by default, and off means the routes are never registered — not
    | registered-then-blocked. A disabled API should not appear in `route:list`
    | at all, so there is nothing to expose by loosening middleware later.
    |
    | Read-only, and it hands out the same answers the chain gives internally.
    | Set the middleware to whatever your authentication actually is: the
    | shipped default is the api group and a throttle, which is a rate limit
    | and not an authorisation.
    |
    */

    'api' => [
        'enabled' => env('IP_INTEL_API', false),
        'prefix' => env('IP_INTEL_API_PREFIX', 'api/ip-intel'),
        'version' => 'v1',
        'middleware' => ['api', 'throttle:60,1'],
    ],

];
