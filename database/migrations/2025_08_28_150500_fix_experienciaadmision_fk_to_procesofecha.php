<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Try to drop any existing FK on experienciaadmision.profec_iCodigo
        foreach ([
            'experienciaadmision_procesofecha_pro_iCodigo_fk',
            'experienciaadmision_profec_icodigo_foreign',
        ] as $fkName) {
            try {
                DB::statement("ALTER TABLE `experienciaadmision` DROP FOREIGN KEY `{$fkName}`");
            } catch (\Throwable $e) {
                // ignore if not exists
            }
        }

        Schema::table('experienciaadmision', function (Blueprint $table) {
            // Add correct FK: experienciaadmision.profec_iCodigo -> procesofecha.profec_iCodigo
            $table->foreign('profec_iCodigo', 'experienciaadmision_profec_fk')
                ->references('profec_iCodigo')
                ->on('procesofecha')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Drop the new FK
        try {
            DB::statement('ALTER TABLE `experienciaadmision` DROP FOREIGN KEY `experienciaadmision_profec_fk`');
        } catch (\Throwable $e) {
            // ignore
        }

        // Restore previous (incorrect) FK for rollback symmetry
        try {
            DB::statement('ALTER TABLE `experienciaadmision` ADD CONSTRAINT `experienciaadmision_procesofecha_pro_iCodigo_fk` FOREIGN KEY (`profec_iCodigo`) REFERENCES `procesofecha`(`pro_iCodigo`) ON DELETE CASCADE');
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
