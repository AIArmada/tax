<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('tax.enabled', true);
        $this->migrator->add('tax.defaultTaxRate', 6.0); // Malaysia SST
        $this->migrator->add('tax.defaultTaxName', 'SST');
        $this->migrator->add('tax.pricesIncludeTax', false);
        $this->migrator->add('tax.taxBasedOnShippingAddress', true);
        $this->migrator->add('tax.digitalGoodsTaxable', true);
        $this->migrator->add('tax.shippingTaxable', false);
        $this->migrator->add('tax.taxIdLabel', 'SST Number');
        $this->migrator->add('tax.validateTaxIds', false);
        $this->migrator->add('tax.requireExemptionCertificate', false);
    }
};
