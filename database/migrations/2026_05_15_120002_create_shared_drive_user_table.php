<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_drive_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shared_drive_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('permission', ['read', 'write'])->default('read');
            $table->timestamps();

            $table->unique(['shared_drive_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_drive_user');
    }
};
