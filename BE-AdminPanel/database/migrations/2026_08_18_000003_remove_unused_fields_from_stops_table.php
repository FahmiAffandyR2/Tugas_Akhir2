<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stops', function (Blueprint $table) {
            $table->dropColumn(['opening_hours', 'ticket_price', 'contact_info', 'image_url']);
        });
    }

    public function down(): void
    {
        Schema::table('stops', function (Blueprint $table) {
            $table->string('opening_hours')->nullable()->after('image_url');
            $table->decimal('ticket_price', 10, 2)->nullable()->after('opening_hours');
            $table->string('contact_info')->nullable()->after('ticket_price');
        });
    }
};
