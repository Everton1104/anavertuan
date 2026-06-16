<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            // Marca serviços de aplicação de Mounjaro: os agendamentos desses
            // serviços alimentam automaticamente a tela de Aplicações.
            $table->boolean('mounjaro')->default(false)->after('visivel_cliente');
        });
    }

    public function down(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn('mounjaro');
        });
    }
};
