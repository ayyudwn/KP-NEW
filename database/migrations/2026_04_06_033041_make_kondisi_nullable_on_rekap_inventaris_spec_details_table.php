<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rekap_inventaris_spec_details', function (Blueprint $table) {
            $table->enum('kondisi', ['Baik', 'Kurang Baik', 'Rusak'])
                ->nullable()
                ->default(null)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('rekap_inventaris_spec_details', function (Blueprint $table) {
            $table->enum('kondisi', ['Baik', 'Kurang Baik', 'Rusak'])
                ->default('Baik')
                ->nullable(false)
                ->change();
        });
    }
};