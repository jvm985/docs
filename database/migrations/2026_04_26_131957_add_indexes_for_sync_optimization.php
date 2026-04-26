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
        Schema::table('files', function (Blueprint $table) {
            $table->index('project_id');
            $table->index('updated_at');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
            $table->dropIndex(['updated_at']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['is_public']);
        });
    }
};
