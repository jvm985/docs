<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('shared_drive_id')
                ->nullable()
                ->after('user_id')
                ->constrained('shared_drives')
                ->nullOnDelete();
            $table->softDeletes();

            $table->index('shared_drive_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['shared_drive_id']);
            $table->dropIndex(['shared_drive_id']);
            $table->dropColumn('shared_drive_id');
            $table->dropSoftDeletes();
        });
    }
};
