<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Contracts;

/**
 * The base every source implements, and the only thing they all share.
 *
 * ## Capabilities are types, not a declared list
 *
 * A driver does not tell you what it can do — it *is* what it can do. Asking
 * whether a source resolves ASNs is `$driver instanceof ResolvesAsn`, which
 * cannot disagree with the implementation because it is the implementation.
 *
 * That matters because these sources genuinely differ. A Cloudflare edge header
 * knows the country and nothing else; registry data adds ASN allocation; a
 * commercial feed adds city and threat signals. A single `IntelDriver::locate()`
 * returning a DTO with twenty nullable fields would make all three look alike
 * and turn "this source cannot answer that" into "this address has no city".
 *
 * It is also what lets the resolver chain be honest: it skips a source that
 * cannot answer the question rather than consuming its budget and getting null.
 */
interface IntelDriver
{
    /**
     * A short name, for config, errors and metering.
     */
    public function name(): string;

    /**
     * Whether this source can run right now — key present, dataset installed,
     * extension loaded.
     *
     * Separate from capability. A source can be capable of ASNs and unavailable
     * because nobody configured its key, and those need different messages.
     */
    public function isAvailable(): bool;

    /**
     * Whether a lookup leaves the machine.
     *
     * The chain reads this to keep its promise that a country-only question
     * with an edge header present makes no network call at all.
     */
    public function isRemote(): bool;
}
