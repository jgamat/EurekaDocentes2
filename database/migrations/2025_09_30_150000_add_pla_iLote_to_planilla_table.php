<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('planilla')) return;        
        Schema::table('planilla', function (Blueprint $table) {
            if (!Schema::hasColumn('planilla', 'pla_iLote')) {
                $table->unsignedInteger('pla_iLote')->default(1)->after('pla_IPaginaFin');
                $table->index(['pro_iCodigo','profec_iCodigo','tipo_iCodigo','pla_iLote'], 'planilla_lote_idx');
            }
        });

        // Inicializar lote para registros existentes: contar por combinación y asignar orden de aparición.
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();
        // Estrategia portable: usamos variables en MySQL; en otros drivers simplemente ponemos 1.
        if ($driver === 'mysql') {
            $connection->statement("SET @rownum := 0, @grp_pro := NULL, @grp_fec := NULL, @grp_tipo := NULL;");
            // Asignar 1 a todos como base (por si falla el siguiente paso)
            $connection->table('planilla')->update(['pla_iLote' => 1]);
            // Recalcular lote secuencial por combinación
            $sql = "UPDATE planilla p
                JOIN (
                    SELECT pla_id,
                           @rownum := IF(@grp_pro=pro_iCodigo AND @grp_fec=profec_iCodigo AND @grp_tipo=tipo_iCodigo, @rownum+1, 1) AS lote,
                           @grp_pro := pro_iCodigo AS gpro,
                           @grp_fec := profec_iCodigo AS gfec,
                           @grp_tipo := tipo_iCodigo AS gtipo
                    FROM planilla
                    ORDER BY pro_iCodigo, profec_iCodigo, tipo_iCodigo, pla_id
                ) t ON t.pla_id = p.pla_id
                SET p.pla_iLote = t.lote";
            try { $connection->statement($sql); } catch (Throwable $e) { /* silencioso */ }
        } else {
            // Otros drivers: set por defecto 1.
            DB::table('planilla')->update(['pla_iLote' => 1]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('planilla')) return;
        Schema::table('planilla', function (Blueprint $table) {
            if (Schema::hasColumn('planilla', 'pla_iLote')) {
                $table->dropIndex('planilla_lote_idx');
                $table->dropColumn('pla_iLote');
            }
        });
    }
};
