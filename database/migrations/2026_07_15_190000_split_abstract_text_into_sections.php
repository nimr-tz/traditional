<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abstract_submissions', function (Blueprint $table) {
            $table->text('background')->nullable()->after('authors');
            $table->text('objective')->nullable()->after('background');
            $table->text('methods')->nullable()->after('objective');
            $table->text('results')->nullable()->after('methods');
            $table->text('conclusion')->nullable()->after('results');
        });

        // Best-effort carry-over for any existing rows: the old single
        // free-text field becomes the Background section rather than being
        // silently discarded. New submissions always fill all five.
        DB::table('abstract_submissions')->whereNotNull('abstract_text')->update([
            'background' => DB::raw('abstract_text'),
        ]);

        Schema::table('abstract_submissions', function (Blueprint $table) {
            $table->dropColumn('abstract_text');
        });
    }

    public function down(): void
    {
        Schema::table('abstract_submissions', function (Blueprint $table) {
            $table->text('abstract_text')->nullable()->after('authors');
        });

        DB::table('abstract_submissions')->update([
            'abstract_text' => DB::raw('background'),
        ]);

        Schema::table('abstract_submissions', function (Blueprint $table) {
            $table->dropColumn(['background', 'objective', 'methods', 'results', 'conclusion']);
        });
    }
};
