<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('version', 64);
            $table->string('status', 16)->default('draft');
            $table->string('min_app_version', 32)->nullable();
            $table->string('max_app_version', 32)->nullable();
            $table->json('browsers')->nullable();
            $table->json('manifest');
            $table->string('file_disk', 64)->default('local');
            $table->string('file_path');
            $table->string('checksum', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'version']);
            $table->index(['asset_id', 'status']);
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_versions');
    }
};
