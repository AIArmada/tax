<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('tax_zones.multiZoneEnabled', false);
        $this->migrator->add('tax_zones.defaultZoneId', null);
        $this->migrator->add('tax_zones.autoDetectZone', true);
        $this->migrator->add('tax_zones.fallbackBehavior', 'default');
        $this->migrator->add('tax_zones.compoundTaxEnabled', false);
        $this->migrator->add('tax_zones.showTaxBreakdown', true);
    }
};
