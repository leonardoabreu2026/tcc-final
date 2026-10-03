<?php
declare(strict_types=1);

/**
 * Imagem dos cursos e e-books importados pela pesquisa (capa do e-book ou imagem do curso).
 *
 *  - completar(): na prévia da importação, confere se o link "Imagem:" da ficha abre mesmo uma imagem;
 *    se o link é de uma PÁGINA (e não do arquivo da imagem), ou se a ficha veio sem imagem, usa a imagem de
 *    divulgação dessa página ou da página do conteúdo (og:image — a mesma que aparece quando o link é
 *    compartilhado); caminho de imagem do próprio site (assets/...) já é a imagem certa e fica;
 *  - baixar(): no cadastro, baixa as imagens escolhidas, reduz para no máximo 800 px de largura e grava
 *    em storage/uploads, como as imagens enviadas pelo painel;
 *  - pdfs(): baixa o PDF dos e-books (do link direto ou achado na página) para a BIBLIOTECA da plataforma.
 *
 * As buscas saem ao mesmo tempo (curl_multi), com tempo e tamanho limitados.
 * Segurança: só http/https e só servidores públicos — nada de localhost ou rede interna. O HTTPS é sempre
 * verificado, com a lista de autoridades certificadoras da Mozilla em config/cacert.pem (https://curl.se/ca/):
 * a que vem no XAMPP é antiga e recusa sites com certificados novos (ex.: ev.org.br, da Fundação Bradesco).
 */
final class ImagemRemota {
    private const LIMITE_IMAGEM = 5 * 1024 * 1024;   // arquivo original (reduzido, fica bem menor)
    private const LIMITE_PAGINA = 512 * 1024;        // só o começo do HTML: as metatags ficam no <head>
    private const LIMITE_PDF_CAPA = 80 * 1024 * 1024; // PDF baixado na prévia só para tirar a capa (CapaPdf)
    private const LARGURA = 800;
    private const SIMULTANEAS = 16;
    private const CERTIFICADOS = ROOT_DIR.'/config/cacert.pem';

    /**
     * Prévia da importação: ABRE CADA LINK da ficha (Imagem, PDF e Link), descobre o que ele é — imagem, PDF ou
     * página — e traz para a plataforma o que serve:
     *   - 'imagem_url' fica só com imagem que abre de verdade, e 'imagem_origem' diz de onde a imagem veio:
     *     'ficha' (o link Imagem é a imagem) · 'caminho' (caminho de imagem do próprio site) · 'capa_pdf' (1ª página
     *     do PDF do e-book, já gravada em storage/uploads e posta em 'imagem') · 'pagina' (imagem de divulgação
     *     da página do link) · '' (sem imagem: entra a reserva ao salvar);
     *   - e-book: o PDF achado (no Link, no PDF, na Imagem ou dentro da página deles) vai para 'pdf_url' — ao
     *     salvar, ele entra na biblioteca da plataforma;
     *   - 'avisos_links': o que não abriu como deveria (ex.: link de PDF que abre uma página), para conferir.
     * @param array<int,array<string,mixed>> $itens
     * @return array<int,array<string,mixed>>
     */
    public static function completar(array $itens): array {
        // 1. A imagem que a pesquisa trouxe abre e é imagem mesmo?
        $validas = self::validas(array_map(fn($it) => (string)($it['imagem_url'] ?? ''), $itens));
        $links = [];
        foreach ($itens as $i => $it) {
            $u = (string)($it['imagem_url'] ?? '');
            $itens[$i]['imagem_url'] = $u !== '' && isset($validas[$u]) ? $u : '';
            $itens[$i]['imagem_origem'] = $itens[$i]['imagem_url'] !== '' ? 'ficha' : (!empty($it['imagem_propria']) ? 'caminho' : '');
            $itens[$i]['avisos_links'] = [];
            $itens[$i]['_abrir'] = array_values(array_unique(array_filter([$itens[$i]['imagem_url'] === '' ? $u : '', (string)($it['pdf_url'] ?? ''), (string)($it['url'] ?? '')])));
            foreach ($itens[$i]['_abrir'] as $l) $links[$l] = true;
        }
        // 2. Abre cada link (só o começo: basta para saber o que é) e separa: PDF, página (com o PDF e as imagens dela) ou nada.
        $respostas = $links ? self::buscar(array_keys($links), self::LIMITE_PAGINA, true) : [];
        $oQue = []; $daPagina = []; $pdfNaPagina = [];
        foreach (array_keys($links) as $l) {
            [$corpo, $tipo, $final] = $respostas[$l] ?? ['', '', $l];
            $oQue[$l] = match (true) {
                $corpo === '' => 'falhou',
                str_starts_with(ltrim($corpo), '%PDF') || str_contains($tipo, 'application/pdf') => 'pdf',
                str_contains($tipo, 'html') => 'pagina',
                str_starts_with($tipo, 'image/') => 'imagem',
                default => 'outro',
            };
            if ($oQue[$l] === 'pagina') { $daPagina[$l] = array_slice(self::candidatas($corpo, $final), 0, 4); $pdfNaPagina[$l] = self::pdfDaPagina($corpo, $final); }
        }
        // 3. E-book sem imagem: a capa é a 1ª página do PDF (o PDF do link, ou o que a página dele oferece).
        $pdfDoItem = [];
        foreach ($itens as $i => $it) {
            if (($it['tipo'] ?? '') !== 'ebook') continue;
            foreach ($it['_abrir'] as $l) {
                if ($oQue[$l] === 'pdf') { $pdfDoItem[$i] = $l; break; }
                if (($pdfNaPagina[$l] ?? '') !== '') { $pdfDoItem[$i] = $pdfNaPagina[$l]; break; }
            }
            if (isset($pdfDoItem[$i]) && (string)($it['pdf_url'] ?? '') === '' && $pdfDoItem[$i] !== ($it['url'] ?? '')) $itens[$i]['pdf_url'] = $pdfDoItem[$i];
        }
        $paraCapa = array_filter($pdfDoItem, fn($p, $i) => $itens[$i]['imagem_origem'] === '', ARRAY_FILTER_USE_BOTH);
        if ($paraCapa) {
            $arquivos = self::baixarArquivos(array_values(array_unique($paraCapa)), self::LIMITE_PDF_CAPA);
            $pdfsOk = [];
            foreach (array_unique($paraCapa) as $p) if (isset($arquivos[$p]) && self::ehPdf($arquivos[$p][0])) $pdfsOk[$p] = $arquivos[$p][0];
            $capas = CapaPdf::gerar($pdfsOk);
            foreach ($paraCapa as $i => $p) {
                if (isset($capas[$p]) && self::ehImagem($capas[$p]) && ($caminho = self::gravar($capas[$p], 'capa')) !== '') {
                    $itens[$i]['imagem'] = $caminho; $itens[$i]['imagem_propria'] = true; $itens[$i]['imagem_origem'] = 'capa_pdf';
                }
            }
            foreach ($arquivos as [$tmp]) if (is_file($tmp)) @unlink($tmp);
        }
        // 4. Ainda sem imagem: a de divulgação da página (a metatag às vezes aponta para um ícone: fica a primeira que abre de verdade).
        $candidatas = [];
        foreach ($itens as $i => $it) {
            if ($it['imagem_origem'] !== '') continue;
            $candidatas[$i] = array_values(array_unique(array_merge([], ...array_map(fn($l) => $daPagina[$l] ?? [], $it['_abrir']))));
        }
        $validas = self::validas(array_merge([], ...array_values($candidatas)));
        foreach ($candidatas as $i => $lista) {
            foreach ($lista as $u) if (isset($validas[$u])) { $itens[$i]['imagem_url'] = $u; $itens[$i]['imagem_origem'] = 'pagina'; break; }
        }
        // 5. Avisos do que não abriu como deveria.
        foreach ($itens as $i => $it) {
            $pdf = (string)($it['pdf_url'] ?? ''); $link = (string)($it['url'] ?? '');
            if ($link !== '' && ($oQue[$link] ?? '') === 'falhou') $itens[$i]['avisos_links'][] = 'o Link não abriu (fora do ar ou endereço errado)';
            elseif ($link !== '' && preg_match('/\.pdf($|[?#])/i', $link) && ($oQue[$link] ?? '') !== 'pdf') $itens[$i]['avisos_links'][] = 'o Link termina em .pdf, mas não abre um PDF (o site devolveu uma página): confira o endereço';
            if ($pdf !== '' && isset($oQue[$pdf]) && $oQue[$pdf] !== 'pdf' && ($pdfNaPagina[$pdf] ?? '') === '') $itens[$i]['avisos_links'][] = 'o link do PDF não abre um PDF: confira o endereço';
            if (($it['tipo'] ?? '') === 'ebook' && !isset($pdfDoItem[$i]) && $pdf === '' && !preg_match('/\.pdf($|[?#])/i', $link)) $itens[$i]['avisos_links'][] = 'nenhum PDF achado nos links: o e-book fica com o botão "Acessar"';
            unset($itens[$i]['_abrir']);
        }
        return $itens;
    }

    /**
     * Baixa as imagens e grava em storage/uploads (JPG, até 800 px de largura).
     * @param string[] $urls
     * @return array<string,string> link => 'assets/uploads/...' (só as que deram certo)
     */
    public static function baixar(array $urls, string $prefixo): array {
        $out = [];
        foreach (self::buscar($urls, self::LIMITE_IMAGEM, false) as $u => [$bytes]) {
            if (self::ehImagem($bytes) && ($caminho = self::gravar($bytes, $prefixo)) !== '') $out[$u] = $caminho;
        }
        return $out;
    }

    /**
     * PDF dos e-books para a BIBLIOTECA da plataforma (o botão do conteúdo vira "Baixar").
     * O link pode ser o próprio PDF ou a página do e-book: na página, procura o PDF (metatag citation_pdf_url
     * dos repositórios como o eduCAPES, ou o link "baixar/download/.pdf"). Só PDF de verdade e completo, até
     * MAX_PDF_REMOTO; grava em storage/uploads/biblioteca_*.pdf.
     * @param string[] $urls
     * @return array<string,string> link => 'assets/uploads/biblioteca_...pdf' (só os que deram certo)
     */
    public static function pdfs(array $urls): array {
        $urls = array_values(array_unique(array_filter($urls, fn($u) => is_string($u) && $u !== '')));
        $out = []; $paginas = [];
        $arquivos = self::baixarArquivos($urls, MAX_PDF_REMOTO);
        foreach ($urls as $u) {
            [$tmp, $tipo, $final] = $arquivos[$u] ?? ['', '', $u];
            if ($tmp === '') continue;
            if (self::ehPdf($tmp)) { if (($c = self::guardarPdf($tmp)) !== '') $out[$u] = $c; }
            elseif (str_contains($tipo, 'html') && filesize($tmp) <= 3 * 1024 * 1024 && ($p = self::pdfDaPagina((string)file_get_contents($tmp), $final)) !== '') $paginas[$u] = $p;
            if (is_file($tmp)) @unlink($tmp);
        }
        if ($paginas) {
            $arquivos = self::baixarArquivos(array_values($paginas), MAX_PDF_REMOTO);
            foreach ($paginas as $u => $p) {
                $tmp = $arquivos[$p][0] ?? '';
                if ($tmp !== '' && self::ehPdf($tmp) && ($c = self::guardarPdf($tmp)) !== '') $out[$u] = $c;
                if ($tmp !== '' && is_file($tmp)) @unlink($tmp);
            }
        }
        return $out;
    }

    /** O PDF que a página do e-book oferece: metatag citation_pdf_url ou o melhor link para ".pdf". '' se não houver. */
    public static function pdfDaPagina(string $html, string $base): string {
        if (preg_match('/<meta[^>]+name=["\']citation_pdf_url["\'][^>]*content=["\']([^"\']+)/i', $html, $m)
            || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]*name=["\']citation_pdf_url["\']/i', $html, $m)) {
            $u = self::absoluto(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), $base);
            if ($u !== '') return $u;
        }
        $melhor = ''; $pontos = -1;
        preg_match_all('/<a\b[^>]*\bhref=["\']([^"\'#]+\.pdf(?:[?#][^"\']*)?)["\'][^>]*>(.*?)<\/a>/is', $html, $ms, PREG_SET_ORDER);
        foreach ($ms as [, $href, $texto]) {
            $u = self::absoluto(html_entity_decode($href, ENT_QUOTES | ENT_HTML5), $base);
            if ($u === '') continue;
            // Link de baixar o e-book vale mais que um PDF qualquer da página (edital, termo de uso).
            // (o ".pdf" do endereço não conta: todo candidato tem)
            $p = preg_match('/baixar|download|e-?book|cartilha|guia|manual|livro|apostila/i', strip_tags($texto).' '.preg_replace('/\.pdf.*$/i', '', $href)) ? 2 : 1;
            if ($p > $pontos) { $pontos = $p; $melhor = $u; }
        }
        return $melhor;
    }

    /** PDF de verdade e inteiro (o leitor de arquivos reconhece como PDF e o fim "%%EOF" chegou). */
    private static function ehPdf(string $arquivo): bool {
        if (!is_file($arquivo) || filesize($arquivo) < 1024) return false;
        if (((new finfo(FILEINFO_MIME_TYPE))->file($arquivo) ?: '') !== 'application/pdf') return false;
        $f = fopen($arquivo, 'rb');
        if (!$f) return false;
        fseek($f, -min(8192, filesize($arquivo)), SEEK_END);
        $fim = (string)fread($f, 8192);
        fclose($f);
        return str_contains($fim, '%%EOF');
    }

    /** Move o PDF baixado para a biblioteca (storage/uploads/biblioteca_*.pdf, nome aleatório). */
    private static function guardarPdf(string $tmp): string {
        $nome = 'biblioteca_'.date('YmdHis').'_'.bin2hex(random_bytes(5)).'.pdf';
        if (!@rename($tmp, UPLOAD_DIR.$nome)) {
            if (!@copy($tmp, UPLOAD_DIR.$nome)) return '';
            @unlink($tmp);
        }
        return 'assets/uploads/'.$nome;
    }

    /**
     * Baixa arquivos grandes direto para o disco (sem guardar tudo na memória), 6 por vez, até $limite bytes e
     * 180 s cada. Mesmas travas do buscar(): só http/https, só servidor público (inclusive depois dos redirecionamentos).
     * @return array<string,array{0:string,1:string,2:string}> link => [arquivo temporário, content-type, endereço final]
     */
    private static function baixarArquivos(array $urls, int $limite): array {
        if (!function_exists('curl_multi_init')) return [];
        $urls = self::publicos($urls);
        $out = [];
        foreach (array_chunk($urls, 6) as $grupo) {
            $mh = curl_multi_init();
            $hs = []; $arqs = []; $fps = []; $tamanhos = [];
            foreach ($grupo as $u) {
                $arqs[$u] = (string)tempnam(sys_get_temp_dir(), 'cvdf_pdf_');
                $fps[$u] = fopen($arqs[$u], 'wb');
                $tamanhos[$u] = 0;
                $ch = self::abrir($u, 8, 180, function ($ch, string $parte) use (&$fps, &$tamanhos, $u, $limite): int {
                    $tamanhos[$u] += strlen($parte);
                    if ($tamanhos[$u] > $limite) return 0;   // grande demais: para de baixar
                    return (int)fwrite($fps[$u], $parte);
                });
                curl_multi_add_handle($mh, $ch);
                $hs[$u] = $ch;
            }
            self::executar($mh);
            foreach ($hs as $u => $ch) {
                fclose($fps[$u]);
                $ok = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200 && $tamanhos[$u] <= $limite && curl_errno($ch) === 0
                    && self::ipPublico((string)curl_getinfo($ch, CURLINFO_PRIMARY_IP));
                if ($ok) $out[$u] = [$arqs[$u], strtolower((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE)), (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL)];
                else @unlink($arqs[$u]);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }
            curl_multi_close($mh);
        }
        return $out;
    }

    /** A imagem de divulgação mais provável da página (a primeira das candidatas). */
    public static function daPagina(string $html, string $base): string {
        return self::candidatas($html, $base)[0] ?? '';
    }

    /**
     * Imagens de divulgação de uma página HTML, da mais provável para a menos provável, com o endereço completo
     * (resolve "/img/x.jpg" e "//cdn.site/x.jpg"). Imagem provisória ("placeholder") fica de fora.
     *  1. metatags og:image / twitter:image e <link rel="image_src">;
     *  2. a imagem que a página diz ser do curso ou a capa (ex.: Escola Virtual.Gov — alt="Imagem do curso: ...");
     *  3. loja virtual Magento (ex.: loja.sebrae.com.br): a foto da galeria do produto, num JSON ("full": "https:\/\/...").
     * @return string[]
     */
    public static function candidatas(string $html, string $base): array {
        $achadas = [];
        foreach (['og:image:secure_url', 'og:image', 'twitter:image', 'twitter:image:src'] as $p) {
            if (preg_match('/<meta\b[^>]*\b(?:property|name)\s*=\s*["\']'.preg_quote($p, '/').'["\'][^>]*>/i', $html, $m)
                && preg_match('/\bcontent\s*=\s*["\']\s*([^"\']+?)\s*["\']/i', $m[0], $c)) $achadas[] = $c[1];
        }
        if (preg_match('/<link\b[^>]*\brel\s*=\s*["\']image_src["\'][^>]*>/i', $html, $m) && preg_match('/\bhref\s*=\s*["\']\s*([^"\']+?)\s*["\']/i', $m[0], $c)) $achadas[] = $c[1];
        preg_match_all('/<img\b[^>]*>/i', $html, $imgs);
        foreach ($imgs[0] as $tag) {
            if (!preg_match('/\bsrc\s*=\s*["\']\s*([^"\']+?)\s*["\']/i', $tag, $s)) continue;
            $alt = preg_match('/\balt\s*=\s*["\']([^"\']*)["\']/i', $tag, $a) ? $a[1] : '';
            if (preg_match('/\b(imagem d[oe] curso|capa d[oe])\b/iu', $alt) || preg_match('/(imagem_curso|capa|cover)[^\/]*\.(jpe?g|png|webp)(\?|$)/i', $s[1])) $achadas[] = $s[1];
        }
        if (preg_match_all('#"(?:full|img)"\s*:\s*"(https?:\\\\?/\\\\?/[^"]*?catalog\\\\?/product\\\\?/[^"]+)"#i', $html, $g)) {
            foreach ($g[1] as $u) $achadas[] = str_replace('\/', '/', $u);
        }
        $out = [];
        foreach ($achadas as $u) {
            $u = self::absoluto(html_entity_decode($u, ENT_QUOTES | ENT_HTML5), $base);
            if ($u !== '' && !preg_match('/placeholder|no_selection|logo|favicon|sprite|avatar/i', $u) && !in_array($u, $out, true)) $out[] = $u;
        }
        return $out;
    }

    /** Link http(s) de servidor público: o nome precisa apontar só para IPs públicos (nada de rede interna). */
    public static function linkPublico(string $url): bool {
        if (!url_http_valida($url)) return false;
        $host = trim((string)parse_url($url, PHP_URL_HOST), '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        foreach ($ips as $ip) if (!self::ipPublico($ip)) return false;
        return $ips !== [];
    }

    private static function ipPublico(string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /** Endereço completo a partir de um relativo da página. */
    private static function absoluto(string $u, string $base): string {
        $u = trim($u);
        if ($u === '') return '';
        if (preg_match('#^https?://#i', $u)) return $u;
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $u)) return '';   // outro esquema (data:, javascript:, ftp:)
        $p = parse_url($base);
        if (empty($p['scheme']) || empty($p['host'])) return '';
        if (str_starts_with($u, '//')) return $p['scheme'].':'.$u;
        $raiz = $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
        if (str_starts_with($u, '/')) return $raiz.$u;
        return $raiz.(preg_replace('#/[^/]*$#', '/', $p['path'] ?? '/') ?: '/').$u;
    }

    /** @return array<string,true> links que abriram uma imagem de verdade */
    private static function validas(array $urls): array {
        $ok = [];
        foreach (self::buscar($urls, self::LIMITE_IMAGEM, false) as $u => [$bytes]) if (self::ehImagem($bytes)) $ok[$u] = true;
        return $ok;
    }

    /** JPG, PNG ou WEBP de verdade, com tamanho de imagem (nem ícone minúsculo, nem gigante demais para reduzir). */
    private static function ehImagem(string $bytes): bool {
        $info = $bytes !== '' ? @getimagesizefromstring($bytes) : false;
        return $info !== false && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            && $info[0] >= 120 && $info[1] >= 90 && $info[0] * $info[1] <= 25_000_000;
    }

    /** Reduz para no máximo 800 px de largura e grava como JPG em storage/uploads. Devolve 'assets/uploads/...' ou ''. */
    private static function gravar(string $bytes, string $prefixo): string {
        $orig = @imagecreatefromstring($bytes);
        if (!$orig) return '';
        $w = imagesx($orig); $h = imagesy($orig);
        $nw = min(self::LARGURA, $w); $nh = max(1, (int)round($h * $nw / $w));
        $img = imagecreatetruecolor($nw, $nh);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));   // PNG transparente fica sobre fundo branco
        imagecopyresampled($img, $orig, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $nome = preg_replace('/[^a-z0-9_]/i', '', $prefixo).'_'.date('YmdHis').'_'.bin2hex(random_bytes(4)).'.jpg';
        $ok = imagejpeg($img, UPLOAD_DIR.$nome, 85);
        imagedestroy($img);
        imagedestroy($orig);
        return $ok ? 'assets/uploads/'.$nome : '';
    }

    /**
     * Busca vários links ao mesmo tempo. Devolve [link => [conteúdo, content-type, endereço final]]
     * só dos que responderam 200 a partir de servidor público. $parcial: basta o começo do conteúdo.
     * @return array<string,array{0:string,1:string,2:string}>
     */
    private static function buscar(array $urls, int $limite, bool $parcial): array {
        if (!function_exists('curl_multi_init')) return [];
        $urls = self::publicos($urls);
        $out = [];
        foreach (array_chunk($urls, self::SIMULTANEAS) as $grupo) {
            $mh = curl_multi_init();
            $hs = []; $corpos = [];
            foreach ($grupo as $u) {
                $corpos[$u] = '';
                // Passou do limite: para de baixar (a página só precisa do começo; imagem grande demais é recusada).
                $ch = self::abrir($u, 6, 15, function ($ch, string $parte) use (&$corpos, $u, $limite): int {
                    $corpos[$u] .= $parte;
                    return strlen($corpos[$u]) > $limite ? 0 : strlen($parte);
                });
                curl_multi_add_handle($mh, $ch);
                $hs[$u] = $ch;
            }
            self::executar($mh);
            foreach ($hs as $u => $ch) {
                $inteiro = strlen($corpos[$u]) <= $limite;
                // Confere também o IP final: um redirecionamento não pode levar para a rede interna.
                if ((int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200 && ($inteiro || $parcial) && self::ipPublico((string)curl_getinfo($ch, CURLINFO_PRIMARY_IP))) {
                    $out[$u] = [$inteiro ? $corpos[$u] : substr($corpos[$u], 0, $limite), strtolower((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE)), (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL)];
                }
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }
            curl_multi_close($mh);
        }
        return $out;
    }

    /** Só os links http(s) de servidor público, sem repetir. */
    private static function publicos(array $urls): array {
        return array_values(array_unique(array_filter($urls, fn($u) => $u !== '' && self::linkPublico($u))));
    }

    /**
     * Prepara o download de um link com as travas comuns: só http/https (também nos redirecionamentos, até 5),
     * certificados do projeto, tempo de conexão e tempo total. $escrever recebe cada pedaço baixado.
     */
    private static function abrir(string $u, int $conexao, int $tempo, callable $escrever): CurlHandle {
        $ch = curl_init($u);
        if (is_file(self::CERTIFICADOS)) curl_setopt($ch, CURLOPT_CAINFO, self::CERTIFICADOS);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => $conexao, CURLOPT_TIMEOUT => $tempo, CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36',
            CURLOPT_HTTPHEADER => ['Accept-Language: pt-BR,pt;q=0.9'],
            CURLOPT_WRITEFUNCTION => $escrever,
        ]);
        return $ch;
    }

    /** Roda os downloads do grupo ao mesmo tempo, até o último terminar. */
    private static function executar(CurlMultiHandle $mh): void {
        do {
            $estado = curl_multi_exec($mh, $ativos);
            if ($ativos) curl_multi_select($mh, 1.0);
        } while ($ativos && $estado === CURLM_OK);
    }
}
