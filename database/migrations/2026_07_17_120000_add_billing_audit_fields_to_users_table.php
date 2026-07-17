<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('billing_requested_at')->nullable()->after('billing_request_id');
            $table->string('payment_transaction_id')->nullable()->unique()->after('payment_status');
            $table->decimal('payment_received_amount', 12, 2)->nullable()->after('payment_transaction_id');
            $table->string('payment_received_currency', 8)->nullable()->after('payment_received_amount');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['payment_transaction_id']);
            $table->dropColumn([
                'billing_requested_at',
                'payment_transaction_id',
                'payment_received_amount',
                'payment_received_currency',
            ]);
        });
    }
};
