<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('charter_bookings', function (Blueprint $table) {
            $table->json('destinations')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('charter_bookings', fn (Blueprint $table) => $table->dropColumn('destinations'));
    }
};
