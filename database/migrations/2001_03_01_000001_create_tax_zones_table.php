<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonColumnType = (string) config('tax.database.json_column_type', 'json');

        Schema::create((string) config('tax.database.tables.tax_zones', 'tax_zones'), function (Blueprint $table) use ($jsonColumnType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();

            // Zone type: country, state, postcode
            $table->string('type')->default('country');

            // Geographic matching
            $table->{$jsonColumnType}('countries')->nullable(); // ['MY', 'SG']
            $table->{$jsonColumnType}('states')->nullable();    // ['Selangor', 'Perak']
            $table->{$jsonColumnType}('postcodes')->nullable(); // ['10000-19999', '50*']

            // Priority for zone matching (higher = checked first)
            $table->integer('priority')->default(0);

            // Flags
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index('code');
            $table->index(['is_active', 'priority']);
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('tax.database.tables.tax_zones', 'tax_zones'));
    }
};
