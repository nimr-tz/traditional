<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('administrator_access_changes', function (Blueprint $table) {
            $table->string('role', 20)->nullable()->after('action');
        });
    }

    public function down(): void
    {
        Schema::table('administrator_access_changes', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
