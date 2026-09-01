<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Simtabi\Laranail\IpIntel\Rules\ValidIp;

/**
 * Validates a lookup before it reaches the chain.
 *
 * A FormRequest rather than inline checks in the controller, so the rules are
 * the same objects a consumer can reuse — and so a malformed address is a 422
 * with a field name rather than a 500 from somewhere inside a driver.
 */
final class LookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorisation is the middleware's, configured by the host. A package
        // cannot know who is allowed to call this, and returning false here
        // would make the endpoint unusable rather than secure.
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'ip' => ['required', 'string', new ValidIp],
            'full' => ['sometimes', 'boolean'],
        ];
    }

    public function ip(): string
    {
        return (string) $this->validated('ip');
    }

    public function wantsFull(): bool
    {
        return $this->boolean('full');
    }
}
