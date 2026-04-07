<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->boolean('is_official')->default(false)->after('status');
            $table->string('approval_status', 32)->default('pending')->after('is_official');
            $table->index('approval_status');
            $table->index(['status', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['status', 'approval_status']);
            $table->dropIndex(['approval_status']);
            $table->dropColumn(['is_official', 'approval_status']);
        });
    }
};
