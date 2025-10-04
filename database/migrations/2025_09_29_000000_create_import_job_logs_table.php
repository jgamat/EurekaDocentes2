<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_job_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename_original')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('importadas')->default(0);
            $table->unsignedInteger('omitidas')->default(0);
            $table->json('errores')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_job_logs');
    }
};
