<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // O adm pode lançar apenas o valor pago e preencher o UI aplicado depois
        // (ou vice-versa). Logo o UI deixa de ser obrigatório.
        Schema::table('doses_mounjaro', function (Blueprint $table) {
            $table->unsignedInteger('ui')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('doses_mounjaro', function (Blueprint $table) {
            $table->unsignedInteger('ui')->nullable(false)->change();
        });
    }
};
