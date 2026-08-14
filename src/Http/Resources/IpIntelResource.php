<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Simtabi\Laranail\IpIntel\Data\IpIntelResult;

/**
 * The API shape of a lookup.
 *
 * @property-read IpIntelResult $resource
 */
final class IpIntelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $result = $this->resource;

        // `outcome` is in the body rather than only in the status code,
        // because "reserved", "not found" and "a source is down" are all 200s
        // for a well-formed request and a client needs to tell them apart.
        return $result->toArray();
    }
}
