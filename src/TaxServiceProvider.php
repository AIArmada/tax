<?php

declare(strict_types=1);

namespace AIArmada\Tax;

use AIArmada\Tax\Contracts\TaxCalculatorInterface;
use AIArmada\Tax\Services\TaxCalculator;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class TaxServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('tax')
            ->hasConfigFile()
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
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
