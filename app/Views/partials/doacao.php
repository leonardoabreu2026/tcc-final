<?php
declare(strict_types=1);

/**
 * Doação por Pix — ajuda a manter o site no ar. Discreta, para todos os visitantes: um cantinho no rodapé
 * de todas as páginas e uma das mensagens do painel relâmpago da página inicial.
 * O código "copia e cola" vem de Pix::doacao() (chave em config/config.php → DOACAO_PIX_CHAVE);
 * o QR é desenhado no navegador por assets/js/vendor/qrcode.js (app.js, [data-qrcode]).
 * Sem chave configurada, o espaço do QR mostra "em configuração" (o texto e o convite continuam visíveis).
 */

const DOACAO_TITULO = 'Apoie o projeto';
const DOACAO_TEXTO = 'Gostou do nosso trabalho? Ajude a manter o site no ar com um Pix de qualquer valor.';

/** QR Code (ou o aviso "em configuração") num quadro branco. $tam em px. */
function cv_doacao_qr(string $pix, int $tam = 80): string {
    if ($pix === '') {
        // Sem chave Pix: a imagem do QR (config DOACAO_QR_IMAGEM — por padrão um QR fictício, para trocar depois).
        $img = defined('DOACAO_QR_IMAGEM') ? caminho_imagem_valido((string)DOACAO_QR_IMAGEM) : '';
        if ($img !== '' && is_file(PUBLIC_DIR.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $img))) {
            return '<img class="cv-doacao-codigo" src="'.e(url($img)).'" width="'.$tam.'" height="'.$tam.'" alt="QR Code Pix para doação ao Conecta Vagas DF" loading="lazy">';
        }
        return '<div class="cv-doacao-codigo cv-doacao-pendente" style="width:'.$tam.'px;height:'.$tam.'px" role="img" aria-label="QR Code Pix em configuração">'
             .icone('coracao', (int)round($tam / 4)).'<span>Pix em<br>breve</span></div>';
    }
    return '<div class="cv-doacao-codigo" style="width:'.$tam.'px;height:'.$tam.'px" data-qrcode="'.e($pix).'" role="img" aria-label="QR Code Pix para doação ao Conecta Vagas DF">'
         .'<noscript><p class="small">Ative o JavaScript para ver o QR Code ou use "Copiar código Pix".</p></noscript></div>';
}

