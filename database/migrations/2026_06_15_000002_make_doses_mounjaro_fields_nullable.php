<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // O custo passou a ser médio (blended): a dose não aponta mais para um
        // lote de fornecedor específico, e a numeração vem da ordem dos
        // agendamentos. Logo fornecedor_id e numero_dose deixam de ser obrigatórios.
        // Modificar uma coluna com FK exige dropar a FK antes (MariaDB).
        Schema::table('doses_mounjaro', function (Blueprint $table) {
            $table->dropForeign(['fornecedor_id']);
            $table->dropForeign(['agendamento_id']);
        });

        Schema::table('doses_mounjaro', function (Blueprint $table) {
            $table->foreignId('fornecedor_id')->nullable()->change();
            $table->unsignedTinyInteger('numero_dose')->nullable()->change();
        });

        Schema::table('doses_mounjaro', function (Blueprint $table) {
            // Fornecedor é decorativo agora: se excluído, apenas zera o vínculo.
            $table->foreign('fornecedor_id')->references('id')
                ->on('fornecedores_mounjaro')->nullOnDelete();
            // Cancelar a consulta (delete do agendamento) remove a dose ligada.
            // Reagendar é só update do mesmo registro (id preservado) — não afeta.
            $table->foreign('agendamento_id')->references('id')
                ->on('agendamentos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('doses_mounjaro', function (Blueprint $table) {
            $table->dropForeign(['fornecedor_id']);
            $table->dropForeign(['agendamento_id']);
        });

        Schema::table('doses_mounjaro', function (Blueprint $table) {
            $table->foreignId('fornecedor_id')->nullable(false)->change();
            $table->unsignedTinyInteger('numero_dose')->nullable(false)->change();
        });

        Schema::table('doses_mounjaro', function (Blueprint $table) {
            $table->foreign('fornecedor_id')->references('id')->on('fornecedores_mounjaro');
            $table->foreign('agendamento_id')->references('id')->on('agendamentos');
        });
    }
};
