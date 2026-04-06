<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rekap_inventaris_specs', function (Blueprint $table) {

            // DROP UNIQUE LAMA (GLOBAL)
            $table->dropUnique('rekap_inventaris_specs_fingerprint_unique');

            // BUAT UNIQUE BARU (PER PERIODE)
            $table->unique(
                ['rekap_inventaris_periode_id', 'fingerprint'],
                'specs_periode_fingerprint_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('rekap_inventaris_specs', function (Blueprint $table) {

            $table->dropUnique('specs_periode_fingerprint_unique');

            $table->unique('fingerprint');
        });
    }
};