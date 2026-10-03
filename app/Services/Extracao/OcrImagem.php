<?php
declare(strict_types=1);

/**
 * Leitura de texto em imagens (OCR) com o Tesseract instalado no servidor — sem API externa.
 * Usada pela máquina de extração de vagas (cartaz da vaga) e de currículos enviados como foto.
 *
 * Como funciona:
 *  1. a imagem é preparada com o GD (tons de cinza, correção de gama e ampliação para ~2200 px de
 *     largura — cartazes de rede social chegam com ~1080 px e letra pequena);
 *  2. o Tesseract lê a imagem duas vezes, devolvendo cada palavra com posição, altura e confiança:
 *       - leitura estruturada (psm 3): mantém as linhas na ordem ("Salário: R$ 1.900,00");
 *       - leitura de texto esparso (psm 11): acha textos soltos e letreiros decorados;
 *  3. linhas com confiança baixa (ruído de fundo, ícones, fotos) são descartadas;
 *  4. as linhas escritas com as maiores letras viram "destaques" (o título do cartaz).
 *
 * LEITOR DA PLATAFORMA (padrão): as 4 leituras são feitas NO NAVEGADOR de quem envia o cartaz, pelo Tesseract.js
 * que vem com o site (public/assets/js/leitor-cartaz.js + assets/js/vendor/tesseract) — nada para instalar no
 * servidor. O navegador manda os TSVs junto com o cartaz (leiturasDoNavegador) e o resultado é montado aqui,
 * com os mesmos ajustes (montar).
 *
 * Tesseract no servidor: OPCIONAL, só reserva para quando o navegador não conseguiu ler.
 * Windows: https://github.com/UB-Mannheim/tesseract/wiki (marcar o idioma português).
 * Linux: apt install tesseract-ocr tesseract-ocr-por.
 */
final class OcrImagem {
    /** Largura (px) para a qual a imagem é ampliada antes da leitura. */
    private const LARGURA = 2200;
    /** Confiança média mínima (0–100) para uma linha entrar no texto. */
    private const CONFIANCA_LINHA = 55;
    /** Maior imagem aceita (pixels), antes e depois da ampliação: no GD cada pixel ocupa 4 bytes de memória. */
    private const MAX_PIXELS = 40_000_000;
    /** Maior leitura (TSV) aceita do navegador, em bytes: um cartaz cheio de texto gera ~100 KB. */
    private const MAX_TSV = 1_500_000;

    /** Caminho do executável do Tesseract (null = não instalado). */
    public static function comando(): ?string {
        static $cmd = false;
        if ($cmd === false) {
            $cmd = LeitorDocumento::encontrarComando('tesseract', [
                'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
                'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
                'C:\\xampp\\tesseract\\tesseract.exe',
                // Instalação "só para mim" (winget/instalador sem administrador): fica na pasta do usuário.
                // O Apache do XAMPP nem sempre recebe a variável LOCALAPPDATA, então procura em todos os usuários.
                ...(getenv('LOCALAPPDATA') ? [getenv('LOCALAPPDATA').'\\Programs\\Tesseract-OCR\\tesseract.exe'] : []),
                ...(DIRECTORY_SEPARATOR === '\\' ? (glob('C:\\Users\\*\\AppData\\Local\\Programs\\Tesseract-OCR\\tesseract.exe') ?: []) : []),
                '/usr/bin/tesseract', '/usr/local/bin/tesseract', '/opt/homebrew/bin/tesseract',
            ]);
        }
        return $cmd;
    }

    public static function disponivel(): bool { return self::comando() !== null && extension_loaded('gd'); }

    /**
     * Lê o texto de uma imagem (JPG, PNG, WEBP ou GIF).
     * @return array{texto:string,complemento:string[],destaques:string[],confianca:int}
     *   texto:       linhas da leitura estruturada, na ordem do cartaz;
     *   complemento: trechos que só a leitura esparsa encontrou (fora da ordem);
     *   destaques:   linhas com as maiores letras (título), da maior para a menor;
     *   confianca:   confiança média das palavras aproveitadas (0–100).
     */
    public static function ler(string $path): array {
        $leituras = self::leituras($path);
        return $leituras ? self::montar(...$leituras) : self::vazio();
    }

    /**
     * As duas leituras brutas do Tesseract (TSV: palavra, posição, altura e confiança).
     * @return array{0:string,1:string}|null [estruturada, esparsa]; null se não foi possível ler
     */
    public static function leituras(string $path): ?array {
        $cmd = self::comando();
        if ($cmd === null || !extension_loaded('gd') || !is_file($path)) return null;
        $preparadas = self::preparar($path);
        if ($preparadas === null) return null;
        [$tmp, $neg] = $preparadas;
        try {
            $idioma = self::idioma($cmd);
            // Leituras 3 e 4: a imagem em NEGATIVO — letra clara sobre fundo escuro (faixas verdes/azuis com texto
            // branco, muito comuns em cartaz) vira letra escura sobre fundo claro, que é o que o Tesseract lê bem.
            $trabalhos = [[$tmp, 3], [$tmp, 11]];
            if ($neg) array_push($trabalhos, [$neg, 3], [$neg, 11]);
            // As leituras rodam AO MESMO TEMPO (≈4× mais rápido); se o paralelo não der, uma por uma.
            $saidas = self::tsvParalelo($cmd, $trabalhos, $idioma);
            if (implode('', $saidas) === '') $saidas = array_map(fn($t) => self::tsv($cmd, $t[0], $t[1], $idioma), $trabalhos);
            // Tesseract às vezes falha numa execução isolada (arquivo ainda em uso, antivírus): tenta mais uma vez.
            if (trim($saidas[0] ?? '') === '' && trim($saidas[1] ?? '') === '') { $saidas[0] = self::tsv($cmd, $tmp, 3, $idioma); $saidas[1] = self::tsv($cmd, $tmp, 11, $idioma); }
            return [$saidas[0] ?? '', $saidas[1] ?? '', $saidas[2] ?? '', $saidas[3] ?? ''];
        } finally {
            @unlink($tmp);
            if ($neg) @unlink($neg);
        }
    }

    /**
     * Leituras feitas NO NAVEGADOR pelo leitor da plataforma (os mesmos 4 TSVs: normal psm 3 e 11, negativo psm 3 e 11).
     * Chegam do formulário (dado do usuário): só aceita uma lista de 1 a 4 textos UTF-8 de tamanho limitado.
     * O conteúdo equivale a colar o texto do anúncio — o montar() só aproveita linhas no formato do Tesseract.
     * @return array{0:string,1:string,2:string,3:string}|null null = nada aproveitável (o servidor lê, se puder)
     */
    public static function leiturasDoNavegador(mixed $tsvs): ?array {
        if (!is_array($tsvs) || !$tsvs || count($tsvs) > 4) return null;
        $out = [];
        foreach (array_values($tsvs) as $t) {
            if (!is_string($t) || strlen($t) > self::MAX_TSV || !mb_check_encoding($t, 'UTF-8')) return null;
            $out[] = str_replace(["\r\n", "\r"], "\n", $t);
        }
        // Tem que parecer uma leitura do Tesseract: linhas com as 12 colunas separadas por tabulação.
        if (!preg_match('/^\d\t(-?[\d.]+\t){10}/m', $out[0])) return null;
        return array_pad($out, 4, '');
    }

    /**
     * Monta o resultado a partir das duas leituras em TSV (separado para poder ser testado
     * com leituras já salvas, sem rodar o Tesseract de novo).
     */
    public static function montar(string $tsv3, string $tsv11, string $tsvNeg3 = '', string $tsvNeg11 = ''): array {
        $estruturada = self::linhas($tsv3);
        // As leituras do negativo entram como leitura esparsa extra (complemento e destaques).
        $esparsa = [...self::linhas($tsv11), ...self::linhas($tsvNeg3), ...self::linhas($tsvNeg11)];

        $boas = array_values(array_filter($estruturada, fn($l) => self::boa($l)));
        $texto = implode("\n", array_column($boas, 't'));

        // Complemento: o que a leitura esparsa achou e a estruturada não (títulos decorados, valores soltos).
        $base = ' '.Competencias::normalizar($texto).' ';
        $complemento = []; $vistos = [];
        foreach ($esparsa as $l) {
            $k = Competencias::normalizar($l['t']);
            if (!self::boa($l, 62) || $k === '' || isset($vistos[$k]) || str_contains($base, ' '.$k.' ')) continue;
            $vistos[$k] = true;
            $complemento[] = $l['t'];
        }

        // Destaques: as maiores letras das duas leituras (sem repetir).
        $todas = array_filter([...$boas, ...array_filter($esparsa, fn($l) => self::boa($l, 62))], fn($l) => preg_match_all('/\p{L}/u', $l['t']) >= 4);
        usort($todas, fn($a, $b) => $b['h'] <=> $a['h']);
        $destaques = []; $vistos = [];
        foreach ($todas as $l) {
            $k = Competencias::normalizar($l['t']);
            if ($k === '' || isset($vistos[$k])) continue;
            $vistos[$k] = true;
            $destaques[] = $l['t'];
            if (count($destaques) >= 10) break;
        }

        $confs = array_merge(...array_map(fn($l) => $l['confs'], $boas ?: [['confs' => []]]));
        return [
            'texto' => $texto,
            'complemento' => $complemento,
            'destaques' => $destaques,
            // Todas as linhas boas de todas as leituras, com repetições: o que se repete no cartaz
            // (ex.: o nome da empresa no topo e no rodapé) é pista forte para a extração.
            'todas' => array_column(array_filter([...$boas, ...$esparsa], fn($l) => self::boa($l, 62)), 't'),
            'confianca' => $confs ? (int)round(array_sum($confs) / count($confs)) : 0,
        ];
    }

    // ------------------------------------------------------------------

    private static function vazio(): array { return ['texto' => '', 'complemento' => [], 'destaques' => [], 'confianca' => 0]; }

    /** Imagem ampliada, em tons de cinza e com mais contraste, salva num PNG temporário. */
    /**
     * Prepara as duas imagens que o Tesseract lê: a normal (cinza, gama, ampliada) e o NEGATIVO dela.
     * Cinza e gama são aplicados na imagem ORIGINAL (bem menor) e o negativo sai da ampliada em memória —
     * o mesmo resultado de antes, com uma fração do trabalho. PNG sem compressão: o arquivo é temporário.
     * @return array{0:string,1:?string}|null [normal, negativo]
     */
    private static function preparar(string $path): ?array {
        // Dimensões lidas do cabeçalho ANTES de decodificar: um PNG de poucos KB pode declarar
        // 50.000 × 50.000 px e esgotar a memória dentro do imagecreatefromstring.
        $info = @getimagesize($path);
        if (!$info || $info[0] < 20 || $info[1] < 20 || $info[0] * $info[1] > self::MAX_PIXELS) return null;
        $bin = @file_get_contents($path);
        $im = $bin !== false ? @imagecreatefromstring($bin) : false;
        if (!$im) return null;
        $w = imagesx($im); $h = imagesy($im);
        if ($w < 20 || $h < 20 || $w * $h > self::MAX_PIXELS) { imagedestroy($im); return null; }
        // Fundo branco para PNG transparente, já em cores verdadeiras (imagem com paleta vira truecolor aqui).
        $base = imagecreatetruecolor($w, $h);
        imagefill($base, 0, 0, imagecolorallocate($base, 255, 255, 255));
        imagecopy($base, $im, 0, 0, 0, 0, $w, $h);
        imagedestroy($im);
        imagefilter($base, IMG_FILTER_GRAYSCALE);
        // Gama escurece os tons médios: letra amarela/laranja sobre fundo claro (comum em cartazes)
        // deixa de ficar quase branca em tons de cinza, sem apagar o texto claro sobre fundo escuro.
        imagegammacorrect($base, 2.2, 1.0);
        $f = max(1.0, min(3.0, self::LARGURA / $w));
        // A ampliação também tem teto (uma imagem estreita e muito alta seria ampliada 3×).
        $f = min($f, sqrt(self::MAX_PIXELS / ($w * $h)));
        $nw = (int)round($w * $f); $nh = (int)round($h * $f);
        $out = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($out, $base, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($base);
        $pasta = rtrim(sys_get_temp_dir(), '\/').DIRECTORY_SEPARATOR;
        $tmp = $pasta.'cvdf_ocr_'.bin2hex(random_bytes(6)).'.png';
        if (!imagepng($out, $tmp, 0)) { imagedestroy($out); return null; }
        // Negativo (e menos contraste nos tons médios), feito da mesma imagem ampliada, sem reler o arquivo.
        imagefilter($out, IMG_FILTER_NEGATE);
        imagefilter($out, IMG_FILTER_CONTRAST, -40);
        $neg = $pasta.'cvdf_ocr_neg_'.bin2hex(random_bytes(6)).'.png';
        $okNeg = imagepng($out, $neg, 0);
        imagedestroy($out);
        return [$tmp, $okNeg ? $neg : null];
    }

    /** "por" quando o português está instalado; senão o inglês (lê o alfabeto latino, sem acentos). */
    private static function idioma(string $cmd): string {
        static $idioma = null;
        if ($idioma === null) {
            $lista = (string)@shell_exec(LeitorDocumento::linhaComando(LeitorDocumento::q($cmd).' --list-langs'));
            $idioma = preg_match('/^por\s*$/m', $lista) ? 'por' : 'eng';
        }
        return $idioma;
    }

    /**
     * Várias leituras do Tesseract em paralelo: cada processo grava o TSV num arquivo temporário (sem risco de
     * travar em pipe cheio) e usa 1 thread (OMP_THREAD_LIMIT=1), para os processos não disputarem a CPU.
     * Tempo máximo de 90 s; o que não terminou é encerrado. Retorna os TSVs na ordem dos trabalhos ('' = falhou).
     * @param list<array{0:string,1:int}> $trabalhos [imagem, psm]
     * @return list<string>
     */
    private static function tsvParalelo(string $cmd, array $trabalhos, string $idioma): array {
        if (!function_exists('proc_open')) return array_fill(0, count($trabalhos), '');
        $env = array_merge(getenv() ?: [], ['OMP_THREAD_LIMIT' => '1']);
        $procs = []; $bases = [];
        foreach ($trabalhos as $i => [$png, $psm]) {
            $bases[$i] = rtrim(sys_get_temp_dir(), '\\/').DIRECTORY_SEPARATOR.'cvdf_ocr_tsv_'.bin2hex(random_bytes(6));
            $nulo = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
            $p = @proc_open([$cmd, $png, $bases[$i], '-l', $idioma, '--psm', (string)$psm, 'tsv'], [0 => ['file', $nulo, 'r'], 1 => ['file', $nulo, 'w'], 2 => ['file', $nulo, 'w']], $pipes, null, $env);
            if (is_resource($p)) $procs[$i] = $p;
        }
        $limite = microtime(true) + 90;
        while ($procs && microtime(true) < $limite) {
            foreach ($procs as $i => $p) if (!proc_get_status($p)['running']) { proc_close($p); unset($procs[$i]); }
            if ($procs) usleep(100_000);
        }
        foreach ($procs as $p) { @proc_terminate($p); @proc_close($p); }   // passou do tempo
        $out = [];
        foreach (array_keys($trabalhos) as $i) {
            $arq = $bases[$i].'.tsv';
            $out[$i] = is_file($arq) ? (string)file_get_contents($arq) : '';
            @unlink($arq);
        }
        return $out;
    }

    private static function tsv(string $cmd, string $png, int $psm, string $idioma): string {
        return (string)@shell_exec(LeitorDocumento::linhaComando(
            LeitorDocumento::q($cmd).' '.LeitorDocumento::q($png).' stdout -l '.$idioma.' --psm '.$psm.' tsv'));
    }

    /**
     * Agrupa as palavras do TSV em linhas.
     * @return array<int,array{t:string,conf:float,h:float,top:int,confs:float[]}>
     */
    private static function linhas(string $tsv): array {
        $grupos = [];
        foreach (explode("\n", $tsv) as $i => $row) {
            if ($i === 0) continue;
            $c = explode("\t", rtrim($row, "\r"));
            if (count($c) < 12 || $c[0] !== '5') continue;
            $palavra = trim($c[11]); $conf = (float)$c[10];
            if ($palavra === '' || $conf < 0) continue;
            $k = $c[2].'-'.$c[3].'-'.$c[4];
            $grupos[$k][] = ['p' => $palavra, 'conf' => $conf, 'h' => (int)$c[9], 'top' => (int)$c[7]];
        }
        $out = [];
        foreach ($grupos as $palavras) {
            // Palavras quase ilegíveis nas pontas da linha costumam ser ícones ou pedaços da foto.
            while ($palavras && $palavras[0]['conf'] < 40) array_shift($palavras);
            while ($palavras && end($palavras)['conf'] < 40) array_pop($palavras);
            if (!$palavras) continue;
            $confs = array_column($palavras, 'conf');
            $alturas = array_column($palavras, 'h'); sort($alturas);
            $out[] = [
                't' => trim(preg_replace('/\s+/u', ' ', implode(' ', array_column($palavras, 'p'))) ?? ''),
                'conf' => array_sum($confs) / count($confs),
                'h' => (float)$alturas[intdiv(count($alturas), 2)],
                'top' => min(array_column($palavras, 'top')),
                'confs' => $confs,
            ];
        }
        return $out;
    }

    /** Linha aproveitável: confiança média boa e com pelo menos uma palavra de verdade. */
    private static function boa(array $l, int $minimo = self::CONFIANCA_LINHA): bool {
        if ($l['conf'] < $minimo || $l['t'] === '') return false;
        // Precisa de uma palavra de 3+ letras — ou de uma sigla de vaga ("VT (DF ou GO)", "VR", "PLR", "CLT").
        if (!preg_match('/[\p{L}\d]{3,}/u', $l['t']) && !preg_match('/\b(VT|VR|VA|PLR|CLT|PJ)\b/u', $l['t'])) return false;
        // Muitos símbolos soltos = ruído de ícones/fundo.
        $letras = preg_match_all('/[\p{L}\d]/u', $l['t']);
        return $letras / max(1, mb_strlen(str_replace(' ', '', $l['t']))) >= 0.6;
    }
}
