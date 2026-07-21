<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Anexos de uma ficha (notas livres, mas a FK é genérica — qualquer ficha pode
// ter anexos no futuro). O arquivo em si fica no disco `public` em
// fichas-notas/{ficha_id}/...; aqui guardamos apenas o caminho e metadados.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fichas_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_id')->constrained('fichas_anamnese')->cascadeOnDelete();
            // Caminho relativo no disco public (ex.: fichas-notas/12/abc.pdf).
            $table->string('caminho');
            // Nome original do arquivo enviado pelo usuário (para exibição/download).
            $table->string('nome_original');
            $table->string('mime')->nullable();
            // Tamanho em bytes.
            $table->unsignedInteger('tamanho')->nullable();
            $table->timestamps();

            $table->index('ficha_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fichas_anexos');
    }
};
