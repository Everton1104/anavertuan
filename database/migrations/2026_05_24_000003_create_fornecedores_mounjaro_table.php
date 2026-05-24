<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fornecedores_mounjaro', function (Blueprint $table) {
            $table->id();
            $table->string('fornecedor');
            $table->date('data_compra');
            $table->string('produto');
            $table->unsignedInteger('ampolas_compradas');
            $table->unsignedInteger('ui_por_ampola');
            $table->decimal('valor_total', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornecedores_mounjaro');
    }
};
