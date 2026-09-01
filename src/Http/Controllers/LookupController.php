<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\IpIntel\Data\IpIntelResult;
use Simtabi\Laranail\IpIntel\Enums\Outcome;
use Simtabi\Laranail\IpIntel\Http\Requests\LookupRequest;
use Simtabi\Laranail\IpIntel\Http\Resources\IpIntelResource;
use Simtabi\Laranail\IpIntel\Services\IpIntelManager;

/**
 * Read-only lookups over HTTP.
 *
 * Registered only when `laranail.ip-intel.api.enabled` is true — and when it is
 * false the routes are never added, so a disabled API is absent from
 * `route:list` rather than present and blocked.
 */
final readonly class LookupController
{
    public function __construct(
        private IpIntelManager $intel,
    ) {}

    public function show(LookupRequest $request): JsonResponse
    {
        $result = $request->wantsFull()
            ? $this->intel->full($request->ip())
            : $this->intel->country($request->ip());

        return IpIntelResource::make($result)
            ->response()
            ->setStatusCode($this->statusFor($result));
    }

    /**
     * The current caller's own address.
     *
     * Behind a CDN this is answered from the edge header and costs nothing.
     */
    public function me(): JsonResponse
    {
        $result = $this->intel->forRequest();

        return IpIntelResource::make($result)
            ->response()
            ->setStatusCode($this->statusFor($result));
    }

    /**
     * A status a client can act on.
     *
     * A source being down is 503 — a retryable server-side problem, not a 200
     * with an empty body, and not a 404 which would say the address does not
     * exist. Everything else is 200: a reserved address and a registry gap are
     * successful answers to a well-formed question.
     */
    private function statusFor(IpIntelResult $result): int
    {
        return match ($result->outcome) {
            Outcome::Unavailable => 503,
            Outcome::Disabled => 501,
            default => 200,
        };
    }
}
