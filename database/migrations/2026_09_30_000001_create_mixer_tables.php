<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('recordings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('path');
            $table->string('mime')->default('audio/m4a');
            $table->unsignedInteger('duration')->nullable();
            $table->string('status')->default('local');
            $table->string('server_id')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('next_attempt_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordings');
        Schema::dropIfExists('settings');
    }
};
