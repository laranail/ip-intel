# Detect a login from a new country

The security signal this package was designed against: an account that has
always signed in from Kenya suddenly signing in from somewhere else.

## Record the country on each login

```php
use Simtabi\Laranail\IpIntel\Enums\Outcome;
use Simtabi\Laranail\IpIntel\Facades\IpIntel;

class RecordLoginLocation
{
    public function handle(Login $event): void
    {
        $result = IpIntel::forRequest();

        // Only Found is worth storing. Reserved is a developer on a VPN or
        // localhost; NotFound is a registry gap; Unavailable is your
        // integration, not the user.
        if ($result->outcome !== Outcome::Found) {
            return;
        }

        $event->user->logins()->create([
            'country_code' => $result->countryCode,
            'ip'           => request()->ip(),
        ]);
    }
}
```

That guard is the whole recipe. Storing a null for a reserved address makes
every developer's login look like a country change, and people stop reading the
alerts.

## Compare

```php
$seen = $user->logins()
    ->where('created_at', '>', now()->subYear())
    ->distinct()
    ->pluck('country_code');

if ($seen->isNotEmpty() && ! $seen->contains($result->countryCode)) {
    $user->notify(new LoginFromNewCountry($result->country));
}
```

`$seen->isNotEmpty()` matters: a first-ever login is not a *new* country, and
alerting on it trains people to ignore the mail.

`$result->country` is a full `CountryRecord` — name, flag, currencies — enriched
from `laranail/atlas` offline, so the notification can say "Kenya 🇰🇪" without a
second lookup or a mapping table in your application.

## It costs nothing behind a CDN

```php
$result->madeNetworkCall;   // false, when an edge header answered
```

If you are behind Cloudflare, Vercel, Fastly or CloudFront, this whole recipe
runs on a request header the proxy already set. No API key, no quota, no
latency added to a login.

## Do not use this to block

Country is a weak signal. People travel, use VPNs, and route through corporate
egress in another jurisdiction. **Notify, ask for a second factor, log** — but a
country mismatch is not grounds to refuse a login on its own, and treating it
that way locks out exactly the travelling users most likely to be legitimate.

If you want a stronger signal, `full()` adds `ThreatSignals` — but read what
they mean first: every field is nullable, and null means *the provider did not
say*, not *no*.

---
[← Docs index](../../README.md#documentation)
