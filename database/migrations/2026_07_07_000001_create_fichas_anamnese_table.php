<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fichas_anamnese', function (Blueprint $table) {
            $table->id();
            // Paciente dono da ficha (users onde adm=0 e func=0).
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Staff (adm/func) que criou a ficha.
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            // Campos estruturados (peso, altura, comorbidades, etc.) — JSON flexível.
            $table->json('dados')->nullable();
            // Soft-delete manual (mesmo padrão do restante do sistema).
            $table->boolean('excluido')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'excluido']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fichas_anamnese');
    }
};
