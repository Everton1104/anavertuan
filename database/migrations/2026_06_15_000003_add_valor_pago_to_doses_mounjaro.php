<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // O valor pago passou a ser por dose (cada consulta tem seu valor), não
        // mais um total único por cliente. O total do cliente é a soma das doses.
        Schema::table('doses_mounjaro', function (Blueprint $table) {
            $table->decimal('valor_pago', 10, 2)->nullable()->after('ui');
        });
    }

    public function down(): void
    {
        Schema::table('doses_mounjaro', function (Blueprint $table) {
            $table->dropColumn('valor_pago');
        });
    }
};
