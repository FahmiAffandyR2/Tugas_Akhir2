<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charter_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('charter_bookings', 'payment_deadline')) {
                $table->timestamp('payment_deadline')->nullable()->after('payment_account_holder');
            }
        });
    }

    public function down(): void
    {
        Schema::table('charter_bookings', function (Blueprint $table) {
            $table->dropColumn('payment_deadline');
        });
    }
};
