<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('avatar')->nullable()->after('google_id');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // SQLite weigert DROP COLUMN als er nog een index naar refereert.
            $table->dropUnique('users_google_id_unique');
            $table->dropColumn(['google_id', 'avatar']);
            // password terug op NOT NULL forceren faalt op rijen die via Google
            // ingelogd zijn (geen password). Tijdens rollbacks bij testen is dat
            // schadelijk; we laten password nullable. Dit is een one-way change.
        });
    }
};
