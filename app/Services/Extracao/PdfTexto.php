<?php
declare(strict_types=1);

/**
 * Leitor de PDF em PHP puro, focado em extrair texto:
 * objetos, object streams (PDF 1.5+), FlateDecode, fontes com ToUnicode (CMap) ou /Differences,
 * recursos por página, Form XObjects, matriz de transformação (cm/q/Q) e posição de cada trecho.
 *
 * A ordem de leitura é reconstruída pela posição: os trechos viram segmentos de linha, e,
 * quando a página tem duas colunas (ex.: barra lateral de contato/habilidades, comum em
 * modelos do Canva/Word), cada coluna é lida inteira antes da outra — sem embaralhar.
 */
final class PdfTexto {
    /** @var array<int,string> dicionário/valor bruto de cada objeto */
    private array $objs = [];
    /** @var array<int,string> stream bruto de cada objeto */
    private array $streams = [];
    private array $cmaps = [];
    /** @var array<int,array{x:float,y:float,x1:float,s:float,t:string}> trechos da página em leitura */
    private array $frags = [];
    /** @var string[] linhas com a maior fonte da 1ª página */
    public array $destaques = [];
    /** Bytes que ainda podem sair dos streams deste arquivo (proteção contra "bomba de descompressão"). */
    private int $orcamento = LeitorDocumento::LIMITE_DESCOMPACTADO;
    /** Trechos de texto lidos até agora; passar de MAX_TRECHOS (um currículo tem poucos milhares) interrompe a leitura. */
    private int $trechos = 0;
    private const MAX_TRECHOS = 200000;

    /** Nomes de glifos (Adobe Glyph List) usados em /Differences de fontes latinas. */
    private const GLIFOS = [
        'aacute'=>'á','agrave'=>'à','acircumflex'=>'â','atilde'=>'ã','adieresis'=>'ä','eacute'=>'é','egrave'=>'è','ecircumflex'=>'ê','edieresis'=>'ë',
        'iacute'=>'í','igrave'=>'ì','icircumflex'=>'î','idieresis'=>'ï','oacute'=>'ó','ograve'=>'ò','ocircumflex'=>'ô','otilde'=>'õ','odieresis'=>'ö',
        'uacute'=>'ú','ugrave'=>'ù','ucircumflex'=>'û','udieresis'=>'ü','ccedilla'=>'ç','ntilde'=>'ñ',
        'Aacute'=>'Á','Agrave'=>'À','Acircumflex'=>'Â','Atilde'=>'Ã','Eacute'=>'É','Ecircumflex'=>'Ê','Iacute'=>'Í','Oacute'=>'Ó','Ocircumflex'=>'Ô',
        'Otilde'=>'Õ','Uacute'=>'Ú','Udieresis'=>'Ü','Ccedilla'=>'Ç','Ntilde'=>'Ñ','space'=>' ','bullet'=>'•','endash'=>'–','emdash'=>'—',
        'quoteright'=>'’','quoteleft'=>'‘','quotedblleft'=>'“','quotedblright'=>'”','ordfeminine'=>'ª','ordmasculine'=>'º','degree'=>'°',
        'hyphen'=>'-','period'=>'.','comma'=>',','colon'=>':','semicolon'=>';','slash'=>'/','parenleft'=>'(','parenright'=>')','at'=>'@',
        'fi'=>'fi','fl'=>'fl','ellipsis'=>'…','periodcentered'=>'·','bar'=>'|','ampersand'=>'&','plus'=>'+','underscore'=>'_','numbersign'=>'#',
        'zero'=>'0','one'=>'1','two'=>'2','three'=>'3','four'=>'4','five'=>'5','six'=>'6','seven'=>'7','eight'=>'8','nine'=>'9',
    ];

    public function __construct(private string $raw) {
        $this->indexar();
    }

    public function texto(): string {
        $paginas = $this->paginas();
        $out = [];
        foreach ($paginas as $k => $num) {
            $pagina = $this->valor($this->objs[$num]);
            if (!is_array($pagina)) continue;
            $recursos = $this->recursosDaPagina($pagina);
            $conteudo = '';
            $c = $this->resolver($pagina['Contents'] ?? null);
            $lista = (is_array($c) && isset($c[0])) ? $c : [$pagina['Contents'] ?? null];
            foreach ($lista as $ref) $conteudo .= $this->streamDe($ref)."\n";
            $this->frags = [];
            $this->interpretar($conteudo, $recursos, 0, [1.0, 0.0, 0.0, 1.0, 0.0, 0.0]);
            $out[] = $this->montarPagina($this->frags, $k === 0);
        }
        if (!$paginas) {
            // PDF sem árvore de páginas reconhecível: interpreta todos os streams.
            foreach ($this->streams as $n => $s) {
                $this->frags = [];
                $this->interpretar($this->decodificar($n), [], 0, [1.0, 0.0, 0.0, 1.0, 0.0, 0.0]);
                $out[] = $this->montarPagina($this->frags, false);
            }
        }
        return implode("\n\n", array_filter($out, fn($t) => trim($t) !== ''));
    }

    /** URLs das anotações de link (/URI). */
    public function links(): array {
        $out = [];
        foreach ($this->objs as $corpo) {
            if (!str_contains($corpo, '/URI')) continue;
            if (!preg_match_all('/\/URI\s*(\((?:\\\\.|[^\\\\)])*\)|<[0-9A-Fa-f\s]*>)/', $corpo, $ms)) continue;
            foreach ($ms[1] as $v) {
                $i = 0; $val = $this->ler($v, $i);
                $u = is_array($val) ? trim((string)($val['_str'] ?? '')) : '';
                if (str_starts_with($u, "\xFE\xFF")) $u = (string)mb_convert_encoding(substr($u, 2), 'UTF-8', 'UTF-16BE');
                if ($u !== '' && preg_match('#^(https?://|mailto:|www\.)#i', $u)) $out[] = $u;
            }
        }
        return array_values(array_unique($out));
    }

    /** Dados brutos das imagens JPEG (DCTDecode) do arquivo — candidatas a foto. */
    public function imagensJpeg(): array {
        $out = [];
        foreach ($this->objs as $num => $dict) {
            if (!isset($this->streams[$num]) || !preg_match('/\/Subtype\s*\/Image/', $dict) || !preg_match('/\/DCTDecode/', $dict)) continue;
            if (preg_match('/\/Filter\s*\[[^\]]*\/FlateDecode/', $dict)) continue; // JPEG comprimido de novo: ignora
            $d = $this->streams[$num];
            if (str_starts_with($d, "\xFF\xD8")) $out[] = $d;
        }
        return $out;
    }

    /**
     * Imagens do PDF que podem ser a foto do candidato: os JPEG (DCTDecode) como estão e as imagens
     * compactadas (FlateDecode, RGB ou cinza, 8 bits — é assim que o Word e o Canva costumam gravar uma
     * foto que era PNG), remontadas em PNG aqui mesmo, sem biblioteca. Máscaras de transparência (/SMask)
     * ficam de fora: não são fotos.
     * @return list<string> bytes de JPEG ou PNG
     */
    public function imagens(int $maxPixels = 3_000_000): array {
        $out = $this->imagensJpeg();
        $mascaras = [];
        foreach ($this->objs as $dict) if (preg_match_all('/\/SMask\s+(\d+)\s+\d+\s+R/', $dict, $m)) foreach ($m[1] as $n) $mascaras[(int)$n] = true;
        foreach ($this->objs as $num => $dict) {
            if (isset($mascaras[$num]) || !isset($this->streams[$num]) || !preg_match('/\/Subtype\s*\/Image/', $dict)) continue;
            if (!preg_match('/\/Filter\s*(?:\/FlateDecode|\[\s*\/FlateDecode\s*\])/', $dict)) continue;
            $png = $this->flateParaPng($num, $maxPixels);
            if ($png !== null) $out[] = $png;
        }
        return $out;
    }

    /**
     * CAPA de um e-book em PDF: a maior imagem desenhada na 1ª página (inclusive dentro de Form XObjects),
     * como JPEG ou PNG. A capa costuma ser uma imagem de página inteira; o texto por cima dela (vetor) não vem.
     * null = a 1ª página não tem imagem (capa feita só de texto e vetores) ou a imagem está num formato que
     * não sabemos remontar (CMYK, indexada...).
     */
    public function capa(int $maxPixels = 25_000_000): ?string {
        $paginas = $this->paginas();
        $pagina = $paginas ? $this->valor($this->objs[$paginas[0]] ?? '') : null;
        if (!is_array($pagina)) return null;
        $imagens = [];
        $coletar = function (mixed $recursos, int $prof) use (&$coletar, &$imagens): void {
            $xo = is_array($recursos) ? $this->resolver($recursos['XObject'] ?? null) : null;
            if (!is_array($xo) || $prof > 3) return;
            foreach ($xo as $ref) {
                if (!is_array($ref) || !isset($ref['_ref'])) continue;
                $num = (int)$ref['_ref'];
                $dict = $this->objs[$num] ?? '';
                if (preg_match('/\/Subtype\s*\/Image/', $dict)) {
                    $v = $this->valor($dict);
                    if (is_array($v)) $imagens[$num] = (int)$this->resolver($v['Width'] ?? 0) * (int)$this->resolver($v['Height'] ?? 0);
                } elseif (preg_match('/\/Subtype\s*\/Form/', $dict)) {
                    $v = $this->valor($dict);
                    if (is_array($v)) $coletar($this->resolver($v['Resources'] ?? null), $prof + 1);
                }
            }
        };
        $coletar($this->recursosDaPagina($pagina), 0);
        arsort($imagens);
        foreach (array_keys($imagens) as $num) {
            $dict = $this->objs[$num];
            if (preg_match('/\/DCTDecode/', $dict) && !preg_match('/\/Filter\s*\[[^\]]*\/FlateDecode/', $dict)) {
                $d = $this->streams[$num] ?? '';
                // JPEG em CMYK (comum em material de gráfica) sai com as cores trocadas: não serve de capa.
                if (str_starts_with($d, "\xFF\xD8") && ((@getimagesizefromstring($d))['channels'] ?? 3) !== 4) return $d;
            } elseif (($png = $this->flateParaPng($num, $maxPixels)) !== null) {
                return $png;
            }
        }
        return null;
    }

    /** Imagem compactada (FlateDecode, RGB ou cinza, 8 bits) remontada em PNG; null se não der. */
    private function flateParaPng(int $num, int $maxPixels): ?string {
        try {
            $v = $this->valor($this->objs[$num] ?? '');
            if (!is_array($v) || !isset($this->streams[$num])) return null;
            $w = (int)$this->resolver($v['Width'] ?? 0); $h = (int)$this->resolver($v['Height'] ?? 0);
            $comp = $this->componentesCor($v['ColorSpace'] ?? null);
            if ((int)$this->resolver($v['BitsPerComponent'] ?? 8) !== 8 || !$comp || $w < 80 || $h < 80 || $w * $h > $maxPixels) return null;
            $raw = $this->inflar($this->streams[$num]);
            if ($raw === null) return null;
            $this->orcamento -= strlen($raw);
            if ($this->orcamento < 0) self::grandeDemais();
            $parms = $this->resolver($v['DecodeParms'] ?? null);
            if (is_array($parms) && isset($parms[0])) $parms = $this->resolver($parms[0]);
            if (is_array($parms) && (int)($parms['Predictor'] ?? 1) >= 10) $raw = self::desfiltrarPng($raw, $w * $comp, $comp);
            if (strlen($raw) < $w * $h * $comp) return null;
            return self::montarPng($raw, $w, $h, $comp);
        } catch (LengthException $e) {
            throw $e;
        } catch (Throwable) {
            return null;   // imagem num formato que não sabemos remontar
        }
    }

    /** Canais de cor de um /ColorSpace: 3 (RGB), 1 (cinza) ou 0 (formato não suportado: CMYK, indexado...). */
    private function componentesCor(mixed $cs): int {
        $cs = $this->resolver($cs);
        if ($cs === '/DeviceRGB' || $cs === '/CalRGB') return 3;
        if ($cs === '/DeviceGray' || $cs === '/CalGray') return 1;
        if (is_array($cs) && ($cs[0] ?? '') === '/ICCBased') {
            $icc = $this->resolver($cs[1] ?? null);
            $n = is_array($icc) ? (int)$this->resolver($icc['N'] ?? 0) : 0;
            return in_array($n, [1, 3], true) ? $n : 0;
        }
        if (is_array($cs) && in_array($cs[0] ?? '', ['/CalRGB', '/CalGray'], true)) return $cs[0] === '/CalRGB' ? 3 : 1;
        return 0;
    }

    /** Desfaz os filtros PNG por linha (None, Sub, Up, Average, Paeth), com bpp bytes por pixel. */
    private static function desfiltrarPng(string $d, int $bytesLinha, int $bpp): string {
        $out = ''; $ant = str_repeat("\0", $bytesLinha); $w = $bytesLinha + 1;
        for ($i = 0; $i + $w <= strlen($d); $i += $w) {
            $tipo = ord($d[$i]); $linha = substr($d, $i + 1, $bytesLinha);
            if ($tipo === 0) { $out .= $linha; $ant = $linha; continue; }
            $nova = $linha;
            for ($j = 0; $j < $bytesLinha; $j++) {
                $a = $j >= $bpp ? ord($nova[$j - $bpp]) : 0; $b = ord($ant[$j]); $c = $j >= $bpp ? ord($ant[$j - $bpp]) : 0; $x = ord($linha[$j]);
                $pred = match ($tipo) {
                    1 => $a, 2 => $b, 3 => intdiv($a + $b, 2),
                    4 => (function () use ($a, $b, $c) { $p = $a + $b - $c; $pa = abs($p - $a); $pb = abs($p - $b); $pc = abs($p - $c);
                                return $pa <= $pb && $pa <= $pc ? $a : ($pb <= $pc ? $b : $c); })(),
                    default => 0,
                };
                $nova[$j] = chr(($x + $pred) & 0xFF);
            }
            $out .= $nova; $ant = $nova;
        }
        return $out;
    }

    /** Pixels crus (RGB ou cinza, 8 bits) → arquivo PNG (IHDR + IDAT + IEND). */
    private static function montarPng(string $raw, int $w, int $h, int $comp): string {
        $bytesLinha = $w * $comp; $linhas = '';
        for ($y = 0; $y < $h; $y++) $linhas .= "\0".substr($raw, $y * $bytesLinha, $bytesLinha);
        $pedaco = fn(string $tipo, string $dados) => pack('N', strlen($dados)).$tipo.$dados.pack('N', crc32($tipo.$dados));
        return "\x89PNG\r\n\x1a\n".$pedaco('IHDR', pack('NNCCCCC', $w, $h, 8, $comp === 3 ? 2 : 0, 0, 0, 0))
             .$pedaco('IDAT', (string)gzcompress($linhas, 6)).$pedaco('IEND', '');
    }

    /** Números dos objetos de página, na ordem da árvore /Pages (ou do arquivo, se não houver árvore). */
    private function paginas(): array {
        $raiz = null;
        foreach ($this->objs as $num => $corpo) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $corpo)) { $cat = $this->valor($corpo); $raiz = is_array($cat) ? ($cat['Pages'] ?? null) : null; break; }
        }
        $out = [];
        $visitar = function (mixed $ref, int $prof) use (&$visitar, &$out) {
            if ($prof > 30 || !is_array($ref) || !isset($ref['_ref'])) return;
            $no = $this->resolver($ref);
            if (!is_array($no)) return;
            if (($no['Type'] ?? '') === '/Page' || !isset($no['Kids'])) { $out[] = (int)$ref['_ref']; return; }
            $kids = $this->resolver($no['Kids']);
            if (is_array($kids)) foreach ($kids as $k) $visitar($k, $prof + 1);
        };
        $visitar($raiz, 0);
        if ($out) return array_values(array_unique($out));
        foreach ($this->objs as $num => $corpo) {
            if (preg_match('/\/Type\s*\/Page(?![a-zA-Z])/', $corpo)) $out[] = $num;
        }
        sort($out);
        return $out;
    }

    // --------------------------------------------------------------- estrutura

    private function indexar(): void {
        if (preg_match_all('/(?<![0-9])(\d+)\s+\d+\s+obj\b/', $this->raw, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as $i => $cap) {
                $num = (int)$cap[0];
                $ini = $m[0][$i][1] + strlen($m[0][$i][0]);
                $fim = strpos($this->raw, 'endobj', $ini);
                if ($fim === false) $fim = strlen($this->raw);
                $corpo = substr($this->raw, $ini, $fim - $ini);
                $sp = $this->posStream($corpo);
                if ($sp !== null) {
                    $dict = substr($corpo, 0, $sp[0]);
                    $dados = substr($corpo, $sp[1]);
                    $len = null;
                    if (preg_match('/\/Length\s+(\d+)(?!\s+\d+\s+R)/', $dict, $lm)) $len = (int)$lm[1];
                    if ($len !== null && $len <= strlen($dados)) {
                        $dados = substr($dados, 0, $len);
                    } else {
                        $e = strrpos($dados, 'endstream');
                        if ($e !== false) $dados = substr($dados, 0, $e);
                        $dados = rtrim($dados, "\r\n");
                    }
                    $this->objs[$num] = $dict;
                    $this->streams[$num] = $dados;
                } else {
                    $this->objs[$num] = $corpo;
                }
            }
        }
        // Object streams: objetos compactados dentro de outros streams.
        foreach ($this->objs as $num => $dict) {
            if (!preg_match('/\/Type\s*\/ObjStm/', $dict) || !isset($this->streams[$num])) continue;
            $d = $this->valor($dict);
            $dados = $this->decodificar($num);
            $n = (int)($d['N'] ?? 0); $first = (int)($d['First'] ?? 0);
            $cab = preg_split('/\s+/', trim(substr($dados, 0, $first))) ?: [];
            for ($i = 0; $i + 1 < count($cab) && $i / 2 < $n; $i += 2) {
                $on = (int)$cab[$i]; $off = (int)$cab[$i + 1];
                $prox = isset($cab[$i + 3]) ? (int)$cab[$i + 3] : strlen($dados) - $first;
                if (!isset($this->objs[$on])) $this->objs[$on] = substr($dados, $first + $off, $prox - $off);
            }
        }
    }

    private function posStream(string $corpo): ?array {
        if (!preg_match('/>>\s*stream(\r\n|\n|\r)/', $corpo, $m, PREG_OFFSET_CAPTURE)) return null;
        $ini = $m[0][1] + strlen($m[0][0]);
        return [$m[0][1] + 2, $ini];
    }

    private function decodificar(int $num): string {
        $dados = $this->streams[$num] ?? '';
        $dict = $this->objs[$num] ?? '';
        if (str_contains($dict, 'FlateDecode')) {
            $d = $this->inflar($dados);
            if ($d === null) return '';
            $dados = $d;
            $v = $this->valor($dict);
            $parms = $this->resolver($v['DecodeParms'] ?? null);
            if (is_array($parms) && isset($parms[0])) $parms = $this->resolver($parms[0]);
            if (is_array($parms) && (int)($parms['Predictor'] ?? 1) >= 10) {
                $dados = $this->despredizer($dados, (int)($parms['Columns'] ?? 1));
            }
        } elseif (preg_match('/\/Filter\s*\/(DCTDecode|JPXDecode|CCITTFaxDecode|JBIG2Decode)/', $dict)) {
            return ''; // imagem
        }
        // Todo stream lido conta no orçamento (o mesmo stream pode ser pedido muitas vezes).
        $this->orcamento -= strlen($dados);
        if ($this->orcamento < 0) self::grandeDemais();
        return $dados;
    }

    /**
     * Descompacta um stream FlateDecode (zlib, ou deflate puro) sem passar do que resta do orçamento:
     * poucos KB podem se expandir para gigabytes. null = dado corrompido.
     */
    private function inflar(string $dados): ?string {
        $max = $this->orcamento + 1;
        $tentativas = [fn() => gzuncompress($dados, $max), fn() => gzinflate($dados, $max)];
        if (strlen($dados) > 2) $tentativas[] = fn() => gzinflate(substr($dados, 2), $max);
        foreach ($tentativas as $tentar) {
            error_clear_last();
            $d = @$tentar();
            if ($d !== false) return $d;
            // O zlib avisa "insufficient memory" quando a saída passaria do teto.
            if (str_contains((string)(error_get_last()['message'] ?? ''), 'insufficient memory')) self::grandeDemais();
        }
        return null;
    }

    /** Interrompe a leitura: o arquivo passou dos limites (LeitorDocumento trata como ilegível). */
    private static function grandeDemais(): never {
        throw new LengthException('Conteúdo do PDF grande demais para ser lido.');
    }

    private function despredizer(string $d, int $cols): string {
        $out = ''; $ant = str_repeat("\0", $cols); $w = $cols + 1;
        for ($i = 0; $i + $w <= strlen($d); $i += $w) {
            $tipo = ord($d[$i]); $linha = substr($d, $i + 1, $cols); $nova = '';
            for ($j = 0; $j < $cols; $j++) {
                $a = $j > 0 ? ord($nova[$j - 1]) : 0; $b = ord($ant[$j]); $x = ord($linha[$j]);
                $nova .= chr(match ($tipo) { 1 => $x + $a, 2 => $x + $b, 3 => $x + intdiv($a + $b, 2), default => $x } & 0xFF);
            }
            $out .= $nova; $ant = $nova;
        }
        return $out;
    }

    private function streamDe(mixed $ref): string {
        if (is_array($ref) && ($ref['_ref'] ?? null) !== null) return $this->decodificar((int)$ref['_ref']);
        return '';
    }

    private function resolver(mixed $v, int $prof = 0): mixed {
        while (is_array($v) && isset($v['_ref']) && $prof++ < 10) {
            $corpo = $this->objs[(int)$v['_ref']] ?? null;
            if ($corpo === null) return null;
            $v = $this->valor($corpo);
        }
        return $v;
    }

    private function recursosDaPagina(array $pagina): array {
        $p = $pagina; $prof = 0;
        while ($prof++ < 20) {
            if (isset($p['Resources'])) { $r = $this->resolver($p['Resources']); return is_array($r) ? $r : []; }
            $pai = $this->resolver($p['Parent'] ?? null);
            if (!is_array($pai)) break;
            $p = $pai;
        }
        return [];
    }

    // --------------------------------------------------------------- parser de valores

    private function valor(string $s): mixed { $i = 0; return $this->ler($s, $i); }

    private function pularEspacos(string $s, int &$i): void {
        $n = strlen($s);
        while ($i < $n) {
            $c = $s[$i];
            if ($c === '%') { while ($i < $n && $s[$i] !== "\n" && $s[$i] !== "\r") $i++; continue; }
            if (strpos(" \t\r\n\f\0", $c) === false) break;
            $i++;
        }
    }

    private function ler(string $s, int &$i): mixed {
        $this->pularEspacos($s, $i);
        $n = strlen($s);
        if ($i >= $n) return null;
        $c = $s[$i];
        if ($c === '<' && ($s[$i + 1] ?? '') === '<') {
            $i += 2; $d = [];
            while (true) {
                $this->pularEspacos($s, $i);
                if ($i >= $n) break;
                if ($s[$i] === '>' && ($s[$i + 1] ?? '') === '>') { $i += 2; break; }
                $k = $this->ler($s, $i);
                if (!is_string($k) || ($k[0] ?? '') !== '/') { if ($i >= $n) break; continue; }
                $d[substr($k, 1)] = $this->ler($s, $i);
            }
            return $d;
        }
        if ($c === '[') {
            $i++; $a = [];
            while (true) {
                $this->pularEspacos($s, $i);
                if ($i >= $n) break;
                if ($s[$i] === ']') { $i++; break; }
                $a[] = $this->ler($s, $i);
            }
            return $a;
        }
        if ($c === '(') return ['_str' => $this->lerLiteral($s, $i)];
        if ($c === '<') { $j = strpos($s, '>', $i); $h = substr($s, $i + 1, ($j === false ? $n : $j) - $i - 1); $i = ($j === false ? $n : $j + 1); return ['_str' => self::hex($h)]; }
        if ($c === '/') {
            $j = $i + 1;
            while ($j < $n && strpos(" \t\r\n\f\0/[]<>()%{}", $s[$j]) === false) $j++;
            $nome = substr($s, $i, $j - $i); $i = $j; return $nome;
        }
        if (preg_match('/\G([+-]?\d+)\s+(\d+)\s+R(?![A-Za-z])/', $s, $m, 0, $i)) { $i += strlen($m[0]); return ['_ref' => (int)$m[1]]; }
        if (preg_match('/\G[+-]?(\d+\.?\d*|\.\d+)/', $s, $m, 0, $i)) { $i += strlen($m[0]); return $m[0] + 0; }
        if (preg_match('/\G[A-Za-z\'"*]+/', $s, $m, 0, $i)) { $i += strlen($m[0]); return ['_op' => $m[0]]; }
        $i++;
        return null;
    }

    private function lerLiteral(string $s, int &$i): string {
        $n = strlen($s); $i++; $prof = 1; $out = '';
        while ($i < $n) {
            $c = $s[$i];
            if ($c === '\\') {
                $p = $s[$i + 1] ?? '';
                if ($p >= '0' && $p <= '7') {
                    preg_match('/\G[0-7]{1,3}/', $s, $m, 0, $i + 1);
                    $out .= chr(octdec($m[0]) & 0xFF); $i += 1 + strlen($m[0]); continue;
                }
                $map = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f", '(' => '(', ')' => ')', '\\' => '\\'];
                if (isset($map[$p])) $out .= $map[$p];
                elseif ($p === "\r") { if (($s[$i + 2] ?? '') === "\n") $i++; }
                elseif ($p !== "\n") $out .= $p;
                $i += 2; continue;
            }
            if ($c === '(') $prof++;
            if ($c === ')' && --$prof === 0) { $i++; break; }
            $out .= $c; $i++;
        }
        return $out;
    }

    private static function hex(string $h): string {
        $h = preg_replace('/[^0-9A-Fa-f]/', '', $h) ?? '';
        if (strlen($h) % 2) $h .= '0';
        return (string)hex2bin($h);
    }

    // --------------------------------------------------------------- fontes / CMap

    /**
     * Dados da fonte necessários para extrair texto:
     * cmap (bytes por código + mapa código→texto, ou null), diferenças de codificação e larguras (1/1000 em).
     */
    private function infoFonte(mixed $fonteRef): array {
        $chave = is_array($fonteRef) && isset($fonteRef['_ref']) ? 'r'.$fonteRef['_ref'] : null;
        if ($chave !== null && isset($this->cmaps[$chave])) return $this->cmaps[$chave];
        $fonte = $this->resolver($fonteRef);
        $info = ['cmap' => null, 'bytes' => 1, 'larg' => [], 'dw' => 500.0, 'dif' => []];
        if (is_array($fonte)) {
            $tipo0 = ($fonte['Subtype'] ?? '') === '/Type0';
            $tu = $fonte['ToUnicode'] ?? null;
            if (is_array($tu) && isset($tu['_ref'])) $info['cmap'] = $this->lerCMap($this->decodificar((int)$tu['_ref']));
            if ($tipo0) {
                $info['bytes'] = 2; $info['cmap'] ??= [2, []];
                $desc = $this->resolver($fonte['DescendantFonts'] ?? null);
                $cid = is_array($desc) ? $this->resolver($desc[0] ?? null) : null;
                if (is_array($cid)) {
                    $info['dw'] = (float)($cid['DW'] ?? 1000);
                    $w = $this->resolver($cid['W'] ?? null);
                    if (is_array($w)) {
                        for ($i = 0; $i < count($w); ) {
                            $c1 = (int)$w[$i];
                            $prox = $this->resolver($w[$i + 1] ?? null);
                            if (is_array($prox)) { foreach ($prox as $k => $lv) $info['larg'][$c1 + $k] = (float)$lv; $i += 2; }
                            else { $c2 = (int)$prox; $lv = (float)($w[$i + 2] ?? 0); for ($c = $c1; $c <= $c2 && $c - $c1 < 65536; $c++) $info['larg'][$c] = $lv; $i += 3; }
                        }
                    }
                }
            } else {
                $first = (int)($fonte['FirstChar'] ?? 0);
                $ws = $this->resolver($fonte['Widths'] ?? null);
                if (is_array($ws)) foreach ($ws as $k => $lv) $info['larg'][$first + $k] = (float)$this->resolver($lv);
                $fd = $this->resolver($fonte['FontDescriptor'] ?? null);
                if (is_array($fd) && isset($fd['MissingWidth'])) $info['dw'] = (float)$fd['MissingWidth'];
                // Fontes simples sem tabela de larguras (Helvetica etc.): média aproximada.
                if (!$info['larg']) $info['dw'] = 520.0;
                // /Encoding << /Differences [...] >>: códigos redefinidos por nome de glifo.
                $enc = $this->resolver($fonte['Encoding'] ?? null);
                if (is_array($enc) && is_array($dif = $this->resolver($enc['Differences'] ?? null))) {
                    $cod = 0;
                    foreach ($dif as $item) {
                        if (is_int($item) || is_float($item)) { $cod = (int)$item; continue; }
                        if (is_string($item) && str_starts_with($item, '/')) {
                            $g = substr($item, 1);
                            $u = self::GLIFOS[$g] ?? (preg_match('/^uni([0-9A-Fa-f]{4})$/', $g, $gm) ? (string)mb_chr((int)hexdec($gm[1]), 'UTF-8') : null);
                            if ($u !== null) $info['dif'][$cod] = $u;
                            $cod++;
                        }
                    }
                }
            }
            if ($info['cmap'] !== null) $info['bytes'] = $info['cmap'][0];
        }
        if ($chave !== null) $this->cmaps[$chave] = $info;
        return $info;
    }

    private function lerCMap(string $c): array {
        $bytes = 1; $mapa = [];
        if (preg_match('/begincodespacerange\s*<([0-9A-Fa-f]+)>/', $c, $m)) $bytes = max(1, intdiv(strlen($m[1]), 2));
        if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $c, $blocos)) {
            foreach ($blocos[1] as $b) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]*)>/', $b, $pares, PREG_SET_ORDER)) {
                    foreach ($pares as $p) $mapa[self::hex($p[1])] = self::utf16($p[2]);
                }
            }
        }
        if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $c, $blocos)) {
            foreach ($blocos[1] as $b) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*(<[0-9A-Fa-f]*>|\[[^\]]*\])/', $b, $faixas, PREG_SET_ORDER)) {
                    foreach ($faixas as $f) {
                        $ini = hexdec($f[1]); $fim = hexdec($f[2]); $w = strlen($f[1]);
                        if ($fim - $ini > 5000) continue;
                        if ($f[3][0] === '[') {
                            preg_match_all('/<([0-9A-Fa-f]*)>/', $f[3], $dst);
                            foreach ($dst[1] as $k => $hx) $mapa[self::hex(str_pad(dechex($ini + $k), $w, '0', STR_PAD_LEFT))] = self::utf16($hx);
                        } else {
                            $base = trim($f[3], '<>');
                            $baseInt = hexdec(substr($base, -4) ?: '0'); $prefixo = substr($base, 0, -4);
                            for ($k = 0; $k <= $fim - $ini; $k++) {
                                $mapa[self::hex(str_pad(dechex($ini + $k), $w, '0', STR_PAD_LEFT))] = self::utf16($prefixo.str_pad(dechex($baseInt + $k), 4, '0', STR_PAD_LEFT));
                            }
                        }
                    }
                }
            }
        }
        return [$bytes, $mapa];
    }

    private static function utf16(string $hex): string {
        $b = self::hex($hex);
        if ($b === '') return '';
        if (strlen($b) % 2) return mb_convert_encoding($b, 'UTF-8', 'Windows-1252');
        return (string)mb_convert_encoding($b, 'UTF-8', 'UTF-16BE');
    }

    /**
     * Converte os bytes de uma string do PDF em texto e mede o avanço horizontal.
     * @return array{0:string,1:float,2:int,3:int} [texto, soma das larguras (1/1000 em), nº de glifos, nº de espaços]
     */
    private function decodificarTexto(string $bytes, array $fonte): array {
        $w = max(1, (int)$fonte['bytes']); $mapa = $fonte['cmap'][1] ?? null;
        $out = ''; $larg = 0.0; $glifos = 0; $espacos = 0;
        for ($i = 0; $i < strlen($bytes); $i += $w) {
            $cod = substr($bytes, $i, $w);
            $num = $w === 1 ? ord($cod) : (int)hexdec(bin2hex($cod));
            $larg += $fonte['larg'][$num] ?? $fonte['dw'];
            $glifos++;
            if ($w === 1 && $num === 32) $espacos++;
            if ($mapa !== null && isset($mapa[$cod])) { $out .= $mapa[$cod]; continue; }
            if ($w === 1 && isset($fonte['dif'][$num])) { $out .= $fonte['dif'][$num]; continue; }
            if ($w === 1) $out .= (string)mb_convert_encoding($cod, 'UTF-8', 'Windows-1252');
        }
        return [$out, $larg, $glifos, $espacos];
    }

    // --------------------------------------------------------------- conteúdo

    /** Produto de matrizes 2D do PDF [a b c d e f]: primeiro $m, depois $n. */
    private static function mul(array $m, array $n): array {
        return [
            $m[0] * $n[0] + $m[1] * $n[2], $m[0] * $n[1] + $m[1] * $n[3],
            $m[2] * $n[0] + $m[3] * $n[2], $m[2] * $n[1] + $m[3] * $n[3],
            $m[4] * $n[0] + $m[5] * $n[2] + $n[4], $m[4] * $n[1] + $m[5] * $n[3] + $n[5],
        ];
    }

    /**
     * Interpreta o fluxo de conteúdo e guarda cada trecho de texto com sua posição na página
     * (espaço do dispositivo: matriz de texto × CTM), tamanho efetivo da fonte e texto.
     */
    private function interpretar(string $cs, array $recursos, int $prof, array $ctm): void {
        if ($cs === '' || $prof > 6) return;
        $fontes = $this->resolver($recursos['Font'] ?? null);
        $fontes = is_array($fontes) ? $fontes : [];
        $xobjs = $this->resolver($recursos['XObject'] ?? null);
        $xobjs = is_array($xobjs) ? $xobjs : [];

        $vazia = ['cmap' => null, 'bytes' => 1, 'larg' => [], 'dw' => 500.0, 'dif' => []];
        $g = ['ctm' => $ctm, 'fonte' => $vazia, 'fs' => 12.0, 'tc' => 0.0, 'tw' => 0.0, 'th' => 1.0, 'tl' => 0.0, 'rise' => 0.0];
        $pilhaG = [];
        $id = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        $tm = $id; $tlm = $id;

        $mostrar = function (string $bytes) use (&$g, &$tm) {
            [$t, $larg, $gl, $esp] = $this->decodificarTexto($bytes, $g['fonte']);
            $m = self::mul([1, 0, 0, 1, 0, $g['rise']], self::mul($tm, $g['ctm']));
            $tx = (($larg / 1000) * $g['fs'] + $g['tc'] * $gl + $g['tw'] * $esp) * $g['th'];
            $tm = self::mul([1, 0, 0, 1, $tx, 0], $tm);
            $fim = self::mul($tm, $g['ctm']);
            $tam = abs($g['fs']) * (hypot($m[2], $m[3]) ?: 1.0);
            if (trim($t) === '' && $t !== ' ') return;
            if (++$this->trechos > self::MAX_TRECHOS) self::grandeDemais();
            $this->frags[] =['x' => $m[4], 'y' => $m[5], 'x1' => $fim[4], 's' => $tam, 't' => $t];
        };

        $pilha = [];
        $i = 0; $n = strlen($cs);
        while ($i < $n) {
            $this->pularEspacos($cs, $i);
            if ($i >= $n) break;
            // Imagens inline: pula os dados binários entre ID e EI.
            if (substr($cs, $i, 2) === 'BI' && preg_match('/\GBI\b/', $cs, $mm, 0, $i)) {
                $fim = strpos($cs, 'EI', $i + 2); $i = $fim === false ? $n : $fim + 2; $pilha = []; continue;
            }
            $antes = $i;
            $tok = $this->ler($cs, $i);
            if ($i === $antes) { $i++; continue; }
            if (!is_array($tok) || !isset($tok['_op'])) { $pilha[] = $tok; continue; }
            $op = $tok['_op'];
            $num = fn(int $k) => (float)(is_numeric($pilha[$k] ?? null) ? $pilha[$k] : 0);
            switch ($op) {
                case 'q': $pilhaG[] = $g; break;
                case 'Q': if ($pilhaG) $g = array_pop($pilhaG); break;
                case 'cm':
                    if (count($pilha) >= 6) $g['ctm'] = self::mul([$num(0), $num(1), $num(2), $num(3), $num(4), $num(5)], $g['ctm']);
                    break;
                case 'BT': $tm = $id; $tlm = $id; break;
                case 'Tf':
                    $nome = $pilha[count($pilha) - 2] ?? null;
                    $g['fonte'] = is_string($nome) ? $this->infoFonte($fontes[substr($nome, 1)] ?? null) : $vazia;
                    $g['fs'] = (float)(end($pilha) ?: 12);
                    break;
                case 'Tc': $g['tc'] = $num(0); break;
                case 'Tw': $g['tw'] = $num(0); break;
                case 'Tz': $g['th'] = $num(0) / 100; break;
                case 'TL': $g['tl'] = $num(0); break;
                case 'Ts': $g['rise'] = $num(0); break;
                case 'Td': case 'TD':
                    if ($op === 'TD') $g['tl'] = -$num(1);
                    $tlm = self::mul([1, 0, 0, 1, $num(0), $num(1)], $tlm); $tm = $tlm;
                    break;
                case 'Tm':
                    $tlm = [$num(0), $num(1), $num(2), $num(3), $num(4), $num(5)]; $tm = $tlm;
                    break;
                case 'T*':
                    $tlm = self::mul([1, 0, 0, 1, 0, -$g['tl']], $tlm); $tm = $tlm;
                    break;
                case 'Tj': case "'": case '"':
                    if ($op !== 'Tj') { $tlm = self::mul([1, 0, 0, 1, 0, -$g['tl']], $tlm); $tm = $tlm; }
                    if ($op === '"') { $g['tw'] = $num(0); $g['tc'] = $num(1); }
                    $s = end($pilha);
                    if (is_array($s) && isset($s['_str'])) $mostrar($s['_str']);
                    break;
                case 'TJ':
                    $arr = end($pilha);
                    if (is_array($arr)) {
                        foreach ($arr as $el) {
                            if (is_array($el) && isset($el['_str'])) { $mostrar($el['_str']); continue; }
                            if (!is_numeric($el)) continue;
                            $tm = self::mul([1, 0, 0, 1, -((float)$el / 1000) * $g['fs'] * $g['th'], 0], $tm);
                        }
                    }
                    break;
                case 'Do':
                    $nome = end($pilha);
                    if (is_string($nome)) {
                        $ref = $xobjs[substr($nome, 1)] ?? null;
                        if (is_array($ref) && isset($ref['_ref'])) {
                            $dx = $this->valor($this->objs[(int)$ref['_ref']] ?? '');
                            if (is_array($dx) && ($dx['Subtype'] ?? '') === '/Form') {
                                $r = $this->resolver($dx['Resources'] ?? null);
                                $mat = $this->resolver($dx['Matrix'] ?? null);
                                $mf = (is_array($mat) && count($mat) === 6) ? array_map(fn($v) => (float)$v, $mat) : $id;
                                $this->interpretar($this->decodificar((int)$ref['_ref']), is_array($r) ? $r : $recursos, $prof + 1, self::mul($mf, $g['ctm']));
                            }
                        }
                    }
                    break;
            }
            $pilha = [];
        }
    }

    // --------------------------------------------------------------- layout

    /**
     * Monta o texto da página a partir dos trechos posicionados:
     * trechos → segmentos de linha → (colunas, se houver) → linhas na ordem de leitura.
     */
    private function montarPagina(array $frags, bool $primeira): string {
        if (!$frags) return '';
        // Remove trechos repetidos no mesmo lugar (negrito simulado desenhando duas vezes).
        $vistos = []; $f2 = [];
        foreach ($frags as $f) {
            $k = $f['t'].'|'.round($f['x']).'|'.round($f['y']);
            if (isset($vistos[$k])) continue;
            $vistos[$k] = true; $f2[] = $f;
        }
        $segs = $this->segmentos($f2);
        if ($primeira) $this->destaques = $this->calcularDestaques($segs);
        return implode("\n", $this->ordenar($segs, 0));
    }

    /**
     * Agrupa os trechos em segmentos: mesma linha de base e sem grande espaço horizontal entre eles.
     * @return array<int,array{x:float,x1:float,y:float,s:float,t:string}>
     */
    private function segmentos(array $frags): array {
        usort($frags, fn($a, $b) => [-$a['y'], $a['x']] <=> [-$b['y'], $b['x']]);
        // 1) linhas pela altura (tolerância proporcional ao tamanho da fonte)
        $linhas = [];
        foreach ($frags as $f) {
            $ult = count($linhas) - 1;
            if ($ult >= 0 && abs($linhas[$ult]['y'] - $f['y']) <= 0.45 * max(1.0, min($linhas[$ult]['s'], $f['s']))) {
                $linhas[$ult]['f'][] = $f; $linhas[$ult]['s'] = max($linhas[$ult]['s'], $f['s']);
            } else {
                $linhas[] = ['y' => $f['y'], 's' => $f['s'], 'f' => [$f]];
            }
        }
        // 2) segmentos dentro da linha
        $segs = [];
        foreach ($linhas as $l) {
            usort($l['f'], fn($a, $b) => $a['x'] <=> $b['x']);
            $atual = null;
            foreach ($l['f'] as $f) {
                if ($atual !== null) {
                    $gap = $f['x'] - $atual['x1'];
                    $ref = max(1.0, min($atual['s'], $f['s']));
                    if ($gap <= 1.6 * $ref && $gap > -2 * $ref) {
                        $sep = ($gap > 0.18 * $ref && !preg_match('/\s$/u', $atual['t']) && !preg_match('/^\s/u', $f['t'])) ? ' ' : '';
                        $atual['t'] .= $sep.$f['t'];
                        $atual['x1'] = max($atual['x1'], $f['x1']); $atual['s'] = max($atual['s'], $f['s']);
                        continue;
                    }
                    $segs[] = $atual;
                }
                $atual = ['x' => $f['x'], 'x1' => max($f['x1'], $f['x'] + 1), 'y' => $l['y'], 's' => $f['s'], 't' => $f['t']];
            }
            if ($atual !== null) $segs[] = $atual;
        }
        foreach ($segs as &$s) $s['t'] = trim(preg_replace('/\s+/u', ' ', $s['t']) ?? $s['t']);
        unset($s);
        return array_values(array_filter($segs, fn($s) => $s['t'] !== ''));
    }

    /**
     * Ordem de leitura: procura um "corredor" vertical sem texto que separe duas colunas.
     * Se existir, lê faixa por faixa (separadas pelos segmentos que atravessam o corredor,
     * como um cabeçalho de largura total): a coluna inteira da esquerda, depois a da direita
     * (ou a da direita primeiro, se é nela que está o nome em destaque).
     * @return string[]
     */
    private function ordenar(array $segs, int $prof): array {
        $corte = $prof < 2 ? $this->corredor($segs) : null;
        if ($corte === null) return $this->linhasDe($segs);
        $atravessa = fn($s) => $s['x'] < $corte - 0.5 && $s['x1'] > $corte + 0.5;
        usort($segs, fn($a, $b) => $b['y'] <=> $a['y']);
        $out = []; $faixa = [];
        $fechar = function () use (&$faixa, &$out, $corte, $prof) {
            if (!$faixa) return;
            $esq = array_values(array_filter($faixa, fn($s) => $s['x1'] <= $corte + 0.5));
            $dir = array_values(array_filter($faixa, fn($s) => $s['x1'] > $corte + 0.5));
            $maxE = $esq ? max(array_column($esq, 's')) : 0; $maxD = $dir ? max(array_column($dir, 's')) : 0;
            $ordem = $maxD > $maxE * 1.3 ? [$dir, $esq] : [$esq, $dir];
            foreach ($ordem as $col) if ($col) array_push($out, ...$this->ordenar($col, $prof + 1));
            $faixa = [];
        };
        foreach ($segs as $s) {
            if ($atravessa($s)) { $fechar(); array_push($out, ...$this->linhasDe([$s])); continue; }
            $faixa[] = $s;
        }
        $fechar();
        return $out;
    }

    /** Posição x do corredor entre duas colunas, ou null se a página (ou bloco) tem uma coluna só. */
    private function corredor(array $segs): ?float {
        $n = count($segs);
        if ($n < 8) return null;
        $minX = min(array_column($segs, 'x')); $maxX = max(array_column($segs, 'x1'));
        $larg = $maxX - $minX;
        if ($larg < 100) return null;
        // Quantos segmentos cruzam cada posição x candidata.
        $passo = 2.0; $melhor = PHP_INT_MAX; $cruz = [];
        for ($x = $minX + 0.12 * $larg; $x <= $maxX - 0.12 * $larg; $x += $passo) {
            $c = 0;
            foreach ($segs as $s) if ($s['x'] < $x - 0.5 && $s['x1'] > $x + 0.5) $c++;
            $cruz[] = [$x, $c];
            $melhor = min($melhor, $c);
        }
        if (!$cruz || $melhor > 0.2 * $n) return null;
        // Faixa contínua mais larga com o mínimo de cruzamentos.
        $ini = null; $largura = 0.0; $corte = null;
        foreach ($cruz as $k => [$x, $c]) {
            if ($c === $melhor) { $ini ??= $x; $w = $x - $ini; if ($w >= $largura) { $largura = $w; $corte = $ini + $w / 2; } }
            else $ini = null;
        }
        if ($corte === null || $largura < 4) return null;
        $esq = array_filter($segs, fn($s) => $s['x1'] <= $corte + 0.5);
        $dir = array_filter($segs, fn($s) => $s['x'] >= $corte - 0.5);
        if (count($esq) < 3 || count($dir) < 3) return null;
        $chars = fn($l) => array_sum(array_map(fn($s) => mb_strlen($s['t']), $l));
        $ce = $chars($esq); $cd = $chars($dir); $tot = max(1, $ce + $cd);
        // Os dois lados precisam ter texto de verdade (e não só datas alinhadas à direita ou rótulos).
        if ($ce / $tot < 0.12 || $cd / $tot < 0.12) return null;
        if ($ce / count($esq) < 8 || $cd / count($dir) < 8) return null;
        // E precisam estar lado a lado (sobreposição vertical).
        $ye = [min(array_column($esq, 'y')), max(array_column($esq, 'y'))];
        $yd = [min(array_column($dir, 'y')), max(array_column($dir, 'y'))];
        $sobre = min($ye[1], $yd[1]) - max($ye[0], $yd[0]);
        $menor = max(1.0, min($ye[1] - $ye[0], $yd[1] - $yd[0]));
        if ($sobre < 0.4 * $menor) return null;
        return $corte;
    }

    /**
     * Linhas de texto de um conjunto de segmentos (uma coluna): de cima para baixo;
     * segmentos na mesma altura viram linhas separadas, exceto "Rótulo:" + valor.
     * Espaço vertical grande vira linha em branco (separa blocos).
     * @return string[]
     */
    private function linhasDe(array $segs): array {
        usort($segs, fn($a, $b) => [-$a['y'], $a['x']] <=> [-$b['y'], $b['x']]);
        // Item quebrado em duas linhas numa lista em colunas ("Banco de Dados –" / "SENAI"):
        // junta o segmento que termina em conector com o que está logo abaixo, na mesma posição x.
        for ($i = 0, $total = count($segs); $i < $total; $i++) {
            if (!isset($segs[$i]) || !preg_match('/(?:[–—\-,]|\s(?:de|da|do|das|dos|e|em|com|para))$/u', $segs[$i]['t'])) continue;
            foreach ($segs as $j => $o) {
                if ($j === $i) continue;
                $dy = $segs[$i]['y'] - $o['y'];
                if ($dy > 0.3 * $o['s'] && $dy <= 1.8 * max($o['s'], $segs[$i]['s']) && abs($o['x'] - $segs[$i]['x']) < 3) {
                    $segs[$i]['t'] .= ' '.$o['t']; $segs[$i]['x1'] = max($segs[$i]['x1'], $o['x1']);
                    unset($segs[$j]);
                    break;
                }
            }
        }
        $segs = array_values($segs);
        $out = []; $yAnt = null; $sAnt = 0.0; $grupo = [];
        $emitir = function () use (&$grupo, &$out) {
            if (!$grupo) return;
            usort($grupo, fn($a, $b) => $a['x'] <=> $b['x']);
            $linha = '';
            foreach ($grupo as $s) {
                if ($linha === '') { $linha = $s['t']; continue; }
                if (str_ends_with($linha, ':')) { $linha .= ' '.$s['t']; continue; }
                $out[] = $linha; $linha = $s['t'];
            }
            if ($linha !== '') $out[] = $linha;
            $grupo = [];
        };
        foreach ($segs as $s) {
            if ($yAnt !== null && abs($s['y'] - $yAnt) <= 0.45 * max(1.0, min($s['s'], $sAnt))) { $grupo[] = $s; continue; }
            $emitir();
            if ($yAnt !== null && $yAnt - $s['y'] > 2.2 * max($s['s'], $sAnt)) $out[] = '';
            $grupo = [$s]; $yAnt = $s['y']; $sAnt = $s['s'];
        }
        $emitir();
        return $out;
    }

    /** Linhas escritas com a maior fonte da página, quando ela se destaca (nome do candidato). */
    private function calcularDestaques(array $segs): array {
        if (!$segs) return [];
        $tams = array_map(fn($s) => round($s['s'], 1), $segs);
        $max = max($tams);
        sort($tams); $mediana = $tams[intdiv(count($tams), 2)];
        if ($max < $mediana * 1.25) return [];
        $top = array_values(array_filter($segs, fn($s) => $s['s'] >= $max * 0.97));
        usort($top, fn($a, $b) => [-$a['y'], $a['x']] <=> [-$b['y'], $b['x']]);
        return array_slice(array_map(fn($s) => $s['t'], $top), 0, 3);
    }
}
