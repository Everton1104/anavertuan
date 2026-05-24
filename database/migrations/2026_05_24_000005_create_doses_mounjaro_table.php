<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doses_mounjaro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aplicacao_id')->constrained('aplicacoes_mounjaro')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero_dose');
            $table->unsignedInteger('ui');
            $table->foreignId('fornecedor_id')->constrained('fornecedores_mounjaro');
            $table->date('data_aplicacao')->nullable();
            $table->foreignId('agendamento_id')->nullable()->constrained('agendamentos');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doses_mounjaro');
    }
};
