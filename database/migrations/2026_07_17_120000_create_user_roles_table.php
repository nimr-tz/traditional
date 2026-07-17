<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['user_id', 'role']);
        });

        // Every existing user keeps their current single role as their first assignment.
        DB::table('users')->select('id', 'role')->orderBy('id')->chunkById(500, function ($users) {
            $now = now();

            DB::table('user_roles')->insert(
                $users->map(fn ($user) => [
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
