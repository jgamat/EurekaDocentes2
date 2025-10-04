<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('import_preview_rows') && !Schema::hasColumn('import_preview_rows','user_id')) {
            Schema::table('import_preview_rows', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('import_preview_rows') && Schema::hasColumn('import_preview_rows','user_id')) {
            Schema::table('import_preview_rows', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }
    }
};
