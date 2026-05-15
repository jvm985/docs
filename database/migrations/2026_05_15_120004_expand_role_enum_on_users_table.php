<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('users')->pluck('role', 'id');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('student')->after('email');
        });

        foreach ($existing as $id => $role) {
            $valid = in_array($role, ['student', 'teacher', 'admin'], true) ? $role : 'student';
            DB::table('users')->where('id', $id)->update(['role' => $valid]);
        }
    }

    public function down(): void
    {
        $existing = DB::table('users')->pluck('role', 'id');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['student', 'teacher'])->default('student')->after('email');
        });

        foreach ($existing as $id => $role) {
            $valid = in_array($role, ['student', 'teacher'], true) ? $role : 'student';
            DB::table('users')->where('id', $id)->update(['role' => $valid]);
        }
    }
};
