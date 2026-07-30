<?php

namespace App\Console\Commands;

use App\Models\FichaAnexo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

// Migra os anexos das fichas do disco `public` (acessível por URL pública direta
// — furo de segurança) para o disco `local` (privado, storage/app/private), de
// onde só são servidos pela rota autenticada FichaAnamneseController::showAnexo.
//
// Rodar UMA vez em produção logo após o deploy que introduziu o disco local para
// anexos. Idempotente: pode ser re-executada sem efeito colateral (anexos já no
// `local` são ignorados; a cópia pública, se ainda existir, é removida). Ao fim,
// a pasta storage/app/public/fichas-notas fica vazia e as URLs públicas antigas
// deixam de funcionar (furo fechado).
class MigrarAnexosPrivado extends Command
{
    protected $signature = 'fichas:anexos-privado';

    protected $description = 'Migra anexos das fichas do disco public para o disco local (privado)';

    public function handle(): int
    {
        $local  = Storage::disk('local');
        $public = Storage::disk('public');

        $movidos   = 0;
        $jaMigrados = 0;
        $semArquivo = 0;

        // chunkById evita carregar todos os anexos na memória.
        FichaAnexo::chunkById(200, function ($anexos) use ($local, $public, &$movidos, &$jaMigrados, &$semArquivo) {
            foreach ($anexos as $anexo) {
                $caminho = $anexo->caminho;

                if ($local->exists($caminho)) {
                    // Já migrado: remove cópia pública órfã (se houver) e segue.
                    if ($public->exists($caminho)) {
                        $public->delete($caminho);
                    }
                    $jaMigrados++;
                    continue;
                }

                if (!$public->exists($caminho)) {
                    $this->warn("Anexo {$anexo->id} ({$caminho}): arquivo ausente em ambos os discos.");
                    $semArquivo++;
                    continue;
                }

                // Copia public -> local via stream (seguro para arquivos grandes).
                $stream = $public->readStream($caminho);
                $local->writeStream($caminho, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                // Só remove do public após confirmar a cópia no local.
                if ($local->exists($caminho)) {
                    $public->delete($caminho);
                    $movidos++;
                } else {
                    $this->error("Anexo {$anexo->id} ({$caminho}): falha ao copiar para o disco local.");
                    $semArquivo++;
                }
            }
        });

        $this->info("Anexos: {$movidos} migrado(s), {$jaMigrados} já no disco local, {$semArquivo} sem arquivo.");
        return self::SUCCESS;
    }
}
