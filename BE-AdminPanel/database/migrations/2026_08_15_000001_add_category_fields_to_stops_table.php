<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stops', function (Blueprint $table) {
            $table->string('category', 50)->default('regular')->after('name');
            $table->text('description')->nullable()->after('category');
            $table->string('image_url', 500)->nullable()->after('description');
            $table->json('opening_hours')->nullable()->after('image_url');
            $table->decimal('ticket_price', 10, 2)->nullable()->after('opening_hours');
            $table->string('contact_info', 255)->nullable()->after('ticket_price');
        });
    }

    public function down(): void
    {
        Schema::table('stops', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'description',
                'image_url',
                'opening_hours',
                'ticket_price',
                'contact_info',
            ]);
        });
    }
};
