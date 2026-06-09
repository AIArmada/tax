<?php

declare(strict_types=1);

namespace AIArmada\Tax\Services\ZoneResolver;

use AIArmada\Tax\Contracts\TaxZoneResolverInterface;
use AIArmada\Tax\Models\TaxZone;

final class CompositeZoneResolver implements TaxZoneResolverInterface
{
    /** @var array<int, TaxZoneResolverInterface> */
    private array $resolvers;

    private DefaultZoneResolver $defaultResolver;

    public function __construct(
        ?array $resolvers = null,
        ?DefaultZoneResolver $defaultResolver = null,
    ) {
        $this->resolvers = $resolvers ?? [
            new ZoneIdResolver,
            new AddressZoneResolver,
        ];
        $this->defaultResolver = $defaultResolver ?? new DefaultZoneResolver;
    }

    public function resolve(?string $zoneId, array $context): ?TaxZone
    {
        foreach ($this->resolvers as $resolver) {
            $zone = $resolver->resolve($zoneId, $context);

            if ($zone !== null) {
                return $zone;
            }
        }

        $zone = $this->defaultResolver->resolve($zoneId, $context);

        if ($zone !== null) {
            return $zone;
        }

        return $this->defaultResolver->handleUnknown();
    }
}
