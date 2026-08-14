# Release

## Versioning

Pre-1.0, this package follows the laranail convention: **one tag per line, and
it moves.** `v0.1.0` is re-pointed at `main` on every release, and consumers on
`^0.1` resolve whatever it currently points at.

That is not a preference, it is the invariant the whole family depends on.
`^0.1` on a `0.x` package means `>=0.1.0 <0.2.0`, so a tag left behind does not
ship consumers older *features* — it ships them code without the *fixes*, while
the release page looks perfectly healthy. `laranail/enumerator` sat two commits
behind its tag with nine packages depending on it, and the missing commits were
a preset and an ordering bugfix.

`scripts/verify-tag-currency.sh` enforces it, weekly and on demand: every tag
must be an ancestor of `main`, and the highest tag on the line named by
`extra.branch-alias` must be `main` itself.

**The cost, stated plainly:** a moving tag means two machines resolving `^0.1`
on different days can get different code, and a `composer.lock` recording
`v0.1.0` says less than it appears to. That is the price of the convention
while pre-1.0, and it is why `1.0` ends it — from then tags are immutable and
every release is its own version.

A package that outgrows the single moving tag cuts real SemVer versions instead;
`laranail/db-tools` did that at `0.7`, and `extra.branch-alias` is what declares
which line is live.

## The public surface

**Supported:** the `IpIntel` facade, `ResolverChain`, all five `Contracts`, the
`Data` value types, both enums, `Rules\ValidIp`, `Exceptions\SourceUnavailable`,
the published config keys, and the REST API's response shapes.

**Internal:** the shipped drivers' constructor signatures, and
`Support\IpIntelConfig`.

## Before tagging

```bash
composer lint         # parallel-lint, Pint, PHPStan, Rector
composer test         # Pest
composer validate --strict
composer audit
```

## The assertions that matter most

Two claims would fail silently if their tests were removed, so check they are
still there and still meaningful:

1. **Routes are absent while the API is off.** Not registered-then-blocked. The
   test reads the live router rather than grepping the provider, so it survives
   a refactor of the registration code.
2. **A country-only lookup makes zero network calls when an edge header is
   present.** Asserted through `madeNetworkCall` *and* a call counter on the
   remote test double — the second because the first could be satisfied by a
   flag nobody sets.

## No path repositories

Every entry in `repositories` must be a `vcs` entry. A `path` repository in a
tagged release installs nothing for anybody else — the directory does not exist
on their machine — and the failure is a confusing "could not find a version of
laranail/atlas" rather than anything naming the real cause.

This package carried exactly that while `laranail/atlas` was unpublished, with a
pinned `options.versions` to make local resolution work. It was removed once
atlas was published, before the first tag.

## Cutting it

1. Update `CHANGELOG.md` under the version being cut. The release workflow
   extracts that section verbatim as the release body.
2. Commit.
3. Tag `vX.Y.Z` and push the tag.

## Distribution

Not Packagist. Consumers add VCS repositories for the **full transitive**
`laranail/*` closure — this package, `atlas`, `package-tools`, `console` —
because Composer ignores a dependency's own `repositories`.

---
[← Docs index](../README.md#documentation)
