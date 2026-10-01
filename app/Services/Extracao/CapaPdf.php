<?php
declare(strict_types=1);

/**
 * CAPA DO E-BOOK a partir do PDF (quando a ficha não traz a imagem da capa, ou traz o próprio PDF no lugar dela).
 *
 *  1. Windows: desenha a 1ª página inteira (com o título, como aparece no leitor de PDF) pelo leitor de PDF do
 *     próprio Windows — capa-pdf.ps1, via PowerShell, todos os PDFs de uma vez;
 *  2. sem isso (outro sistema, PowerShell bloqueado, PDF que o Windows não abre): a maior imagem desenhada
 *     na 1ª página (PdfTexto::capa) — em geral a foto de fundo da capa, sem o texto por cima.
 * Devolve os bytes da imagem (PNG ou JPEG); quem chama confere e grava (ImagemRemota).
 */
final class CapaPdf {
    private const SCRIPT = __DIR__.'/capa-pdf.ps1';

    /**
     * @param array<string,string> $pdfs chave => arquivo PDF no disco
     * @return array<string,string> chave => bytes da imagem da capa (só as que deram certo)
     */
    public static function gerar(array $pdfs): array {
        $out = self::desenharNoWindows($pdfs);
        foreach ($pdfs as $k => $arquivo) {
            if (isset($out[$k])) continue;
            try {
                $img = (new PdfTexto((string)file_get_contents($arquivo)))->capa();
                if ($img !== null) $out[$k] = $img;
            } catch (Throwable) {
                // PDF grande ou estranho demais para o leitor em PHP: fica sem capa (entra a imagem de reserva).
            }
        }
        return $out;
    }

    /** @param array<string,string> $pdfs @return array<string,string> */
    private static function desenharNoWindows(array $pdfs): array {
        if (!$pdfs || PHP_OS_FAMILY !== 'Windows' || !function_exists('exec') || !is_file(self::SCRIPT)) return [];
        $lista = (string)tempnam(sys_get_temp_dir(), 'cvdf_capas_');
        $saidas = []; $linhas = '';
        foreach ($pdfs as $k => $arquivo) {
            $saidas[$k] = $lista.'_'.count($saidas).'.png';
            $linhas .= realpath($arquivo)."\t".$saidas[$k]."\n";
        }
        file_put_contents($lista, $linhas);
        $cmd = 'powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File '.escapeshellarg(self::SCRIPT)
             .' -Lista '.escapeshellarg($lista).' -Largura 800';
        @exec($cmd.' 2>&1', $saidaCmd, $codigo);
        $out = [];
        foreach ($saidas as $k => $png) {
            if (is_file($png)) { $bytes = (string)file_get_contents($png); if ($bytes !== '') $out[$k] = $bytes; @unlink($png); }
        }
        @unlink($lista);
        return $out;
    }
}
