<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('author')->nullable();
            $table->string('license', 128)->nullable();
            $table->json('tags')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
