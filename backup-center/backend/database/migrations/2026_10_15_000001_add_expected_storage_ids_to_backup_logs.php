<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('backup_logs', function (Blueprint $table) {
            $table->json('expected_storage_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('backup_logs', fn (Blueprint $table) => $table->dropColumn('expected_storage_ids'));
    }
};
