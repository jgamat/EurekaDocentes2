<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('import_preview_rows')) {
            Schema::create('import_preview_rows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('token', 64)->index();
                $table->integer('row');
                $table->string('codigo')->nullable();
                $table->string('dni')->nullable();
                $table->string('nombres')->nullable();
                $table->string('cargo')->nullable();
                $table->string('local')->nullable();
                $table->string('fecha')->nullable();
                $table->json('errores')->nullable();
                $table->json('warnings')->nullable();
                $table->boolean('valid')->default(false);
                $table->timestamps();
                $table->index(['user_id','token']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('import_preview_rows');
    }
};
