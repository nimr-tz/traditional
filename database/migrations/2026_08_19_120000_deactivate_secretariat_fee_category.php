<?php

use App\Models\FeeCategory;
use Illuminate\Database\Migrations\Migration;

/**
 * The secretariat is conference staff, not an attendee category — nobody
 * should be walking up to the desk asking to register under it. Deactivated
 * rather than deleted: any registrant already on it (see VenueDeskDemoSeeder)
 * keeps their record, it just drops off the desk's category list, same as any
 * other retired fee category (FeeCategory::scopeActive()).
 */
return new class extends Migration
{
    public function up(): void
    {
        FeeCategory::where('key', 'complimentary_secretariat')->update(['active' => false]);
    }

    public function down(): void
    {
        FeeCategory::where('key', 'complimentary_secretariat')->update(['active' => true]);
    }
};
