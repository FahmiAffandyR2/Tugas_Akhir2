<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charter_bookings', function (Blueprint $table) {
            $table->string('payment_bank_name', 100)->nullable()->after('admin_notes');
            $table->string('payment_account_number', 100)->nullable()->after('payment_bank_name');
            $table->string('payment_account_holder', 150)->nullable()->after('payment_account_number');
            $table->string('payment_status', 40)->default('unpaid')->after('status');
            $table->string('payment_method', 50)->nullable()->after('payment_status');
            $table->string('payment_reference', 150)->nullable()->after('payment_method');
            $table->string('payment_proof_path')->nullable()->after('payment_reference');
            $table->timestamp('payment_submitted_at')->nullable()->after('payment_proof_path');
            $table->timestamp('paid_at')->nullable()->after('payment_submitted_at');
            $table->text('payment_rejection_reason')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('charter_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'payment_bank_name', 'payment_account_number', 'payment_account_holder',
                'payment_status', 'payment_method', 'payment_reference', 'payment_proof_path',
                'payment_submitted_at', 'paid_at', 'payment_rejection_reason',
            ]);
        });
    }
};
