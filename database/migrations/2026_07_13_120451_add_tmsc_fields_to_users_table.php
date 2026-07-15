<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('salutation')->nullable()->after('name');
            $table->string('institution')->nullable()->after('email');
            $table->string('phone')->nullable()->after('institution');
            $table->string('country')->nullable()->after('phone');
            $table->boolean('is_east_africa')->default(true)->after('country');
            $table->enum('participant_type', [
                'researcher', 'practitioner', 'academic', 'policy_maker', 'decision_maker', 'student', 'media',
            ])->nullable()->after('is_east_africa');
            $table->boolean('is_admin')->default(false)->after('participant_type');

            $table->string('fee_category')->nullable()->after('is_admin');
            $table->decimal('fee_amount', 12, 2)->nullable()->after('fee_category');
            $table->string('currency', 8)->default('TZS')->after('fee_amount');

            $table->string('control_number')->nullable()->unique()->after('currency');
            $table->string('billing_request_id')->nullable()->after('control_number');
            $table->enum('payment_method', ['gepg', 'bank_transfer'])->nullable()->after('billing_request_id');
            $table->string('payment_proof_path')->nullable()->after('payment_method');
            $table->enum('payment_status', ['pending', 'submitted', 'verified', 'rejected'])
                ->default('pending')->after('payment_proof_path');
            $table->text('payment_notes')->nullable()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('payment_notes');

            $table->string('registration_code')->nullable()->unique()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'salutation', 'institution', 'phone', 'country', 'is_east_africa', 'participant_type', 'is_admin',
                'fee_category', 'fee_amount', 'currency',
                'control_number', 'billing_request_id', 'payment_method', 'payment_proof_path',
                'payment_status', 'payment_notes', 'paid_at', 'registration_code',
            ]);
        });
    }
};
