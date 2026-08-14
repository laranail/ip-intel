# Outcomes

`IpIntelResult` always comes back. `Outcome` says what kind of answer it is.

```php
enum Outcome: string {
    case Found       = 'found';
    case Reserved    = 'reserved';
    case NotFound    = 'not_found';
    case Unavailable = 'unavailable';
    case Disabled    = 'disabled';
}
```

## The five cases

| Case | Means | What to do |
|---|---|---|
| `Found` | A source answered | Use it |
| `Reserved` | RFC 1918, loopback, link-local | Nothing to look up — normal in dev |
| `NotFound` | A genuine registry gap | Treat as unknown |
| `Unavailable` | **A source is broken** — dead key, 5xx, timeout | Alert somebody |
| `Disabled` | Switched off in config | Nothing |

The implementation this replaces returned bare `null` for every one of them. "We
do not know where this address is", "the API key expired three weeks ago" and
"somebody set `enabled = false` last quarter" were indistinguishable to every
caller — so the second one never got noticed, which is exactly the failure a
metered integration cannot afford.

```php
if ($result->needsAttention()) {
    // Unavailable. The address is fine; your integration is not.
    report(new IntegrationDegraded($result->message));
}
```

## `Reserved` is answered before any source is consulted

```php
IpIntel::country('10.0.0.1')->outcome;   // Outcome::Reserved
```

No driver is called. `10.0.0.1` is in use on millions of networks in every
country there is, so a lookup would spend a request to return something
misleading — and in development it is *most* of your traffic.

## Caching follows the outcome

```php
Outcome::Found->isCacheable();        // true
Outcome::Reserved->isCacheable();     // true — that fact cannot change
Outcome::NotFound->isCacheable();     // true
Outcome::Unavailable->isCacheable();  // false
Outcome::Disabled->isCacheable();     // false
```

**A failure is never cached.** Caching a dead key's answer for a day means the
outage outlives the fix, and the person who fixes it sees no change — so they
fix it again, differently, and now two things are wrong.

Putting `isCacheable()` on the enum rather than in the cache layer means the
rule lives with the thing it describes, and a new outcome cannot be added
without deciding.

## A broken source does not hide a working one

```php
'chain' => ['broken', 'good'],
```

A `SourceUnavailable` from the first is **recorded, not swallowed and not
fatal**: a later source may still answer, and if none does the caller needs to
know a source was broken rather than that the address was unknown.

So the chain continues, and the outcome is `Found` if anything answered and
`Unavailable` if nothing did.

## `message`

Set on `Unavailable`, and it names the reason the source gave:

```php
$result->message;   // 'ipapi rejected the request: your access key is invalid'
```

Never the raw exception message from the HTTP client — that embeds the full
request URL, which carries the access key.

---
[← Docs index](../../README.md#documentation)
