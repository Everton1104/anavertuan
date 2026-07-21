<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Anexo de uma ficha. O binário fica no disco `public` (fichas-notas/{ficha_id});
// aqui guardamos o caminho relativo e metadados para exibição. Excluir o anexo
// remove também o arquivo físico (ver FichaAnamneseController::destroyAnexo).
class FichaAnexo extends Model
{
    protected $table = 'fichas_anexos';

    protected $fillable = ['ficha_id', 'caminho', 'nome_original', 'mime', 'tamanho'];

    // Ficha dona do anexo.
    public function ficha()
    {
        return $this->belongsTo(FichaAnamnese::class, 'ficha_id');
    }
}
