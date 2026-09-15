<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('driver_readiness', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('driver_id')->unique();
            $table->foreign('driver_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('sim_number', 100)->nullable();
            $table->string('health_status', 20)->default('unknown');
            $table->text('health_notes')->nullable();
            foreach (['sim', 'health', 'skck'] as $kind) {
                $table->string($kind . '_path')->nullable();
                $table->date($kind . '_valid_until')->nullable();
            }
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('driver_readiness'); }
};
