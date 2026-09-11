<?php

declare(strict_types=1);

namespace AIArmada\Tax\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class TaxOwnerScope
{
    /**
     * Resolve the owner for a tax calculation or zone lookup.
     *
     * @param  array<string, mixed>  $context
     */
    public static function resolve(array $context): ?Model
    {
        if (! config('tax.features.owner.enabled', false)) {
            return null;
        }

        $owner = $context['owner'] ?? OwnerContext::resolve();

        if ($owner !== null && ! $owner instanceof Model) {
            throw new InvalidArgumentException('Tax owner context must be an Eloquent model or null.');
        }

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            'Tax calculation requires an owner context or explicit global context.',
        );

        return $owner;
    }

    /**
     * Apply the package owner boundary to a tax query.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $context
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, array $context): Builder
    {
        if (! config('tax.features.owner.enabled', false)) {
            return $query;
        }

        $owner = self::resolve($context);

        return OwnerQuery::applyToEloquentBuilder(
            $query->withoutGlobalScope(OwnerScope::class),
            $owner,
            includeGlobal: (bool) config('tax.features.owner.include_global', false),
        );
    }
}
