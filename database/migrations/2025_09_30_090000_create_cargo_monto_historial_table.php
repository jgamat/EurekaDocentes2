<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cargo_monto_historial', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('expadm_iCodigo');
            $table->decimal('monto_anterior', 12, 2)->nullable();
            $table->decimal('monto_nuevo', 12, 2);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('archivo_original')->nullable();
            $table->string('fuente', 50)->default('import_excel');
            $table->timestamp('aplicado_en')->useCurrent();
            $table->timestamps();

            $table->index('expadm_iCodigo');
            $table->index('aplicado_en');
            if (Schema::hasTable('users')) {
                // Foreign keys opcionales para no romper si las tablas no existen aún.
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            }
            // No se define FK estricta a experienciaadmision por inconsistencias históricas posibles; se puede agregar luego.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_monto_historial');
    }
};
