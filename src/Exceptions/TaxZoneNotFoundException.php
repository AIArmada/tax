<?php

declare(strict_types=1);

namespace AIArmada\Tax\Exceptions;

use Exception;

class TaxZoneNotFoundException extends Exception
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'Tax zone not found',
        public readonly ?string $country = null,
        public readonly ?string $state = null,
        public readonly ?string $postcode = null,
        public readonly ?string $requestedZoneId = null,
        public readonly array $context = [],
    ) {
        parent::__construct($this->buildMessage($message));
    }

    private function buildMessage(string $message): string
    {
        $details = [];

        if ($this->requestedZoneId !== null) {
            $details[] = "zone_id={$this->requestedZoneId}";
        }

        if ($this->country !== null) {
            $details[] = "country={$this->country}";
        }

        if ($this->state !== null) {
            $details[] = "state={$this->state}";
        }

        if ($this->postcode !== null) {
            $details[] = "postcode={$this->postcode}";
        }

        if ($details === []) {
            return $message;
        }

        return $message . ' (' . implode(', ', $details) . ')';
    }

    /**
     * @param  array<string, mixed>  $address
     * @param  array<string, mixed>  $context
     */
    public static function forAddress(array $address, array $context = []): self
    {
        return new self(
            message: 'No tax zone matches the provided address',
            country: $address['country'] ?? null,
            state: $address['state'] ?? null,
            postcode: $address['postcode'] ?? null,
            context: $context,
        );
    }

    public static function forZoneId(string $zoneId): self
    {
        return new self(
            message: 'Tax zone not found',
            requestedZoneId: $zoneId,
        );
    }
}
