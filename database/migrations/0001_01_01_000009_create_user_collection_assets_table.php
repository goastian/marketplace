<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_collection_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_collection_id')->constrained('user_collections')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['user_collection_id', 'asset_id']);
            $table->index(['user_collection_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_collection_assets');
    }
};
