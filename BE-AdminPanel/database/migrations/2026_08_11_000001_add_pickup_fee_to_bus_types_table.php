<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bus_types', function (Blueprint $table) {
            if (!Schema::hasColumn('bus_types', 'pickup_fee')) {
                $table->decimal('pickup_fee', 15, 2)->default(0)->after('minimum_price');
            }
        });

        $fees = [
            'elf'             => 200000,
            'medium'          => 250000,
            'medium_25'       => 250000,
            'medium_26'       => 250000,
            'big'             => 350000,
            'big_45'          => 350000,
            'big_50'          => 350000,
            'luxury'          => 500000,
        ];
        foreach ($fees as $slug => $fee) {
            DB::table('bus_types')->where('slug', $slug)->update(['pickup_fee' => $fee, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('bus_types', function (Blueprint $table) {
            $table->dropColumn('pickup_fee');
        });
    }
};
