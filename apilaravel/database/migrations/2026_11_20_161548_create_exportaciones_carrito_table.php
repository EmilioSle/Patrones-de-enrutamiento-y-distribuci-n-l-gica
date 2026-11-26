<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exportaciones_carrito', function (Blueprint $table): void {
            $table->uuid('message_id')->primary();
            $table->uuid('correlation_id')->index();
            $table->foreignId('cliente_id')->constrained('users')->cascadeOnDelete();
            $table->string('estado');
            $table->string('solicitado_en');
            $table->string('finalizado_en')->nullable();
            $table->longText('resultado_csv')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exportaciones_carrito');
    }
};
