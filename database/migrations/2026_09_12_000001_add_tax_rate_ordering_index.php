<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('tax.database.tables.tax_rates', 'tax_rates');
        $indexName = 'tax_rates_zone_class_active_compound_priority_index';

        if (
            ! Schema::hasTable($tableName)
            || ! Schema::hasColumns($tableName, ['zone_id', 'tax_class', 'is_active', 'is_compound', 'priority'])
            || Schema::hasIndex($tableName, $indexName)
        ) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->index(
                ['zone_id', 'tax_class', 'is_active', 'is_compound', 'priority'],
                $indexName,
            );
        });
    }

    public function down(): void
    {
        $tableName = (string) config('tax.database.tables.tax_rates', 'tax_rates');
        $indexName = 'tax_rates_zone_class_active_compound_priority_index';

        if (! Schema::hasTable($tableName) || ! Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }
};
