<?php

declare(strict_types=1);

namespace AIArmada\Tax;

use AIArmada\Tax\Console\Commands\RecalculateTaxRatesCommand;
use AIArmada\Tax\Console\Commands\SyncTaxZonesCommand;
use AIArmada\Tax\Contracts\TaxCalculatorInterface;
use AIArmada\Tax\Contracts\TaxRateApplierInterface;
use AIArmada\Tax\Contracts\TaxZoneResolverInterface;
use AIArmada\Tax\Services\RateApplier\StandardRateApplier;
use AIArmada\Tax\Services\TaxCalculator;
use AIArmada\Tax\Services\ZoneResolver\CompositeZoneResolver;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class TaxServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('tax')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommands([
                RecalculateTaxRatesCommand::class,
                SyncTaxZonesCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(TaxZoneResolverInterface::class, CompositeZoneResolver::class);
        $this->app->singleton(TaxRateApplierInterface::class, StandardRateApplier::class);

        $this->app->singleton(TaxCalculatorInterface::class, TaxCalculator::class);
        $this->app->alias(TaxCalculatorInterface::class, 'tax');
    }

    public function bootingPackage(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../database/settings' => database_path('settings'),
        ], 'tax-settings');
    }
}
