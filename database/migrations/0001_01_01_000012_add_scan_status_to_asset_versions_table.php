<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_versions', function (Blueprint $table) {
            $table->string('scan_status', 32)->default('pending')->after('size_bytes');
            $table->text('scan_notes')->nullable()->after('scan_status');
            $table->index('scan_status');
        });
    }

    public function down(): void
    {
        Schema::table('asset_versions', function (Blueprint $table) {
            $table->dropIndex(['scan_status']);
            $table->dropColumn(['scan_status', 'scan_notes']);
        });
    }
};
