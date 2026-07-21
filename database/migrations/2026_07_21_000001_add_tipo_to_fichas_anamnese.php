<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adiciona a coluna `tipo` para distinguir fichas de anamnese ('anamnese') de
// anotações livres ('nota'). Fichas existentes viram 'anamnese' pelo default.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas_anamnese', function (Blueprint $table) {
            $table->string('tipo', 20)->default('anamnese')->after('criado_por');
            $table->index(['user_id', 'excluido', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::table('fichas_anamnese', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'excluido', 'tipo']);
            $table->dropColumn('tipo');
        });
    }
};
