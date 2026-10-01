<?php
declare(strict_types=1);

/**
 * Extração de cursos: transforma o texto de divulgação de um curso nos campos do cadastro.
 * O resultado só preenche o formulário — o administrador revisa antes de salvar.
 *
 * CALIBRADOR: a área (categoria) pode vir de um termo calibrado pelo administrador e a instituição,
 * de uma já cadastrada no catálogo (Calibrador). As decisões dele ficam em $r['calibrador'].
 */
final class ExtracaoCurso {
    private const INSTITUICOES = [
        'Fundação Bradesco – Escola Virtual' => ['fundacao bradesco','escola virtual bradesco','ev org br'],
        'Escola Virtual do Governo' => ['escola virtual do governo','escola virtual gov','escolavirtual gov','enap','evg'],
        'SEBRAE' => ['sebrae'], 'SENAI' => ['senai'], 'SENAC' => ['senac'], 'SESI' => ['sesi'], 'SENAR' => ['senar'],
        'Google Grow' => ['google grow','grow google','google ateliê digital','google'],
        'FGV Online' => ['fgv'], 'Microsoft Learn' => ['microsoft learn','microsoft'], 'Cisco Networking Academy' => ['cisco'],
        'Coursera' => ['coursera'], 'Udemy' => ['udemy'], 'Alura' => ['alura'], 'Fundação Estudar' => ['fundacao estudar'],
        'IFB – Instituto Federal de Brasília' => ['ifb','instituto federal de brasilia'], 'Senac EAD' => ['senac ead'],
        'Prime Cursos' => ['prime cursos'], 'Cursa' => ['cursa'], 'Kultivi' => ['kultivi'], 'YouTube' => ['youtube'],
    ];

    private const CATEGORIAS = [
        'Informática e Excel' => ['Excel','Pacote Office','Informática','PHP','JavaScript','React','HTML e CSS','Banco de dados SQL','Python','Java','Git','Desenvolvimento de software','Suporte técnico de TI'],
        'Empreendedorismo e Gestão' => ['Empreendedorismo','Gestão e liderança'],
        'Administração e Atendimento' => ['Rotinas administrativas','Administração','Atendimento ao cliente','Vendas','Recursos Humanos','Saúde e cuidados','Enfermagem','Telemarketing','Comunicação'],
        'Marketing, Dados e UX' => ['Marketing digital','Análise de dados','UX e design'],
        'Negócios, Finanças e ESG' => ['Finanças','Contabilidade','ESG e sustentabilidade'],
    ];

    /** @return array<string,mixed> */
    public static function doTexto(string $texto): array {
        $texto = trim(str_replace(["\r\n", "\r"], "\n", $texto));
        $r = ['titulo'=>'','descricao'=>'','tipo'=>'curso','modalidade'=>'ead','nivel'=>'iniciante','duracao'=>'','gratuito'=>1,'preco'=>null,
              'url'=>'','instituicao'=>'','categoria'=>'','competencias'=>[],'calibrador'=>[]];
        if ($texto === '') return $r;
        $n = Competencias::normalizar($texto);
        $linhas = array_values(array_filter(array_map(fn($l) => trim_u($l, " \t*_•·-–—>"), explode("\n", $texto)), fn($l) => $l !== ''));

        $r['url'] = self::primeiroLink($texto);
        foreach ($linhas as $l) {
            if (preg_match('/^(?:curso|t[ií]tulo|nome do curso)\s*:\s*(.+)$/iu', $l, $m)) { $r['titulo'] = trim($m[1]); break; }
        }
        if ($r['titulo'] === '') {
            foreach ($linhas as $l) if (!preg_match('/https?:|@|r\$/i', $l) && mb_strlen($l) <= 100) { $r['titulo'] = $l; break; }
        }
        if ($r['titulo'] !== '' && $r['titulo'] === mb_strtoupper($r['titulo'])) $r['titulo'] = mb_convert_case(mb_strtolower($r['titulo']), MB_CASE_TITLE, 'UTF-8');

        foreach ($linhas as $l) if (preg_match('/^(?:institui[cç][aã]o|oferecido por|promovido por|realiza[cç][aã]o|plataforma)\s*:\s*(.+)$/iu', $l, $m)) { $r['instituicao'] = trim($m[1]); break; }
        if ($r['instituicao'] === '') {
            $busca = ' '.$n.' '.Competencias::normalizar($r['url']).' ';
            foreach (self::INSTITUICOES as $nome => $chaves) {
                foreach ($chaves as $c) if (str_contains($busca, ' '.$c.' ')) { $r['instituicao'] = $nome; break 2; }
            }
        }
        if ($r['instituicao'] === '') {
            // Instituição fora da lista acima, mas que já está no catálogo (calibrador automático).
            $r['instituicao'] = Calibrador::nomeConhecido('instituicao', $texto);
            if ($r['instituicao'] !== '') $r['calibrador'][] = ['campo' => 'instituicao', 'texto' => $r['instituicao'], 'regra' => '', 'para' => $r['instituicao'], 'termo' => 'instituição já cadastrada'];
        }

        if (preg_match('/r\$\s*(\d{1,3}(?:\.\d{3})*(?:,\d{2})?|\d+(?:,\d{2})?)/iu', $texto, $m) && !preg_match('/\b(gratuito|gratis|gratuita|free|sem custo)\b/', $n)) {
            $r['preco'] = decimal_ou_null($m[1]); $r['gratuito'] = $r['preco'] ? 0 : 1;
        }
        if (preg_match('/(\d+)\s*(h\b|horas?|hrs?|semanas?|meses|m[eê]s|dias?|aulas?|m[oó]dulos?)/iu', $texto, $m)) {
            $u = mb_strtolower($m[2]);
            $r['duracao'] = $m[1].' '.(str_starts_with($u, 'h') ? 'horas' : $u);
        }
        $r['modalidade'] = match (true) {
            (bool)preg_match('/\b(hibrido|hibrida|semipresencial)\b/', $n) => 'hibrido',
            (bool)preg_match('/\bpresencial\b/', $n) && !preg_match('/\b(ead|online|a distancia)\b/', $n) => 'presencial',
            default => 'ead',
        };
        $r['nivel'] = match (true) {
            (bool)preg_match('/\b(avancado|avancada|especialista)\b/', $n) => 'avancado',
            (bool)preg_match('/\b(intermediario|intermediaria)\b/', $n) => 'intermediario',
            default => 'iniciante',
        };
        $r['tipo'] = match (true) {
            (bool)preg_match('/\b(e book|ebook|livro digital|apostila)\b/', $n) => 'ebook',
            (bool)preg_match('/\b(video|videoaula|youtube|webinar|live)\b/', $n) && !preg_match('/\bcurso\b/', $n) => 'video',
            default => 'curso',
        };
        $tituloN = Competencias::normalizar($r['titulo']);
        $desc = array_filter($linhas, fn($l) => Competencias::normalizar($l) !== $tituloN && !preg_match('/^https?:/i', $l));
        $r['descricao'] = implode("\n", $desc);
        $r['competencias'] = Competencias::doCurso($r);
        $r = self::categoriaSugerida($r);
        return $r;
    }

    /**
     * Área do curso: palpite da regra (pelas competências), a não ser que um termo calibrado
     * pelo administrador aponte outra área (primeiro no título, depois na descrição).
     */
    private static function categoriaSugerida(array $r): array {
        $regra = self::categoria($r['competencias']);
        $d = Calibrador::decidir('curso_categoria', $r['titulo'], $regra);
        if ($d['origem'] === 'regra') $d = Calibrador::decidir('curso_categoria', $r['titulo']."\n".$r['descricao'], $regra);
        $r['categoria'] = $d['classe'];
        if ($d['origem'] === 'calibrador') $r['calibrador'][] = ['campo' => 'categoria', 'texto' => $r['titulo'], 'regra' => $regra, 'para' => $d['classe'], 'termo' => $d['termo']];
        return $r;
    }

    /** Áreas (categorias de curso) do database/seed.sql — usadas no prompt quando o banco ainda não tem categorias. */
    public const AREAS = [
        'Informática e Excel', 'Empreendedorismo e Gestão', 'Administração e Atendimento', 'Marketing, Dados e UX',
        'Negócios, Finanças e ESG', 'Tecnologia e Inteligência Artificial', 'Carreira e Empregabilidade',
    ];

    /**
     * As capas de public/assets/img/cursos são banners COM A MARCA da instituição (Fundação Bradesco, SEBRAE,
     * Escola Virtual Gov, Google, FGV). Por isso a capa segue a instituição, nunca a área: um e-book do
     * Banco Central não pode sair com o banner da FGV. Instituição sem banner → sem capa (o cartão mostra o
     * ícone do formato). A ordem importa: "Fundação Bradesco – Escola Virtual" é Bradesco, não a Escola Virtual Gov.
     */
    private const CAPAS_INSTITUICAO = [
        'bradesco|ev org br' => 'assets/img/cursos/curso1.png',
        'sebrae' => 'assets/img/cursos/capas/sebrae.jpg',   // imagem oficial do SEBRAE (a curso2.png tinha marca d'água de outro site)
        'escola virtual gov|escola virtual do governo|escolavirtual gov|enap|evg' => 'assets/img/cursos/curso3.png',
        'google' => 'assets/img/cursos/curso4.png',
        'fgv' => 'assets/img/cursos/curso5.png',
    ];

    /** Capa com a marca da instituição (ou '' quando não há banner dela). Os banners anunciam "cursos": e-book e vídeo ficam com a capa do formato. */
    public static function capa(string $instituicao, string $url = '', string $tipo = 'curso'): string {
        if ($tipo !== 'curso') return '';
        $busca = ' '.Competencias::normalizar($instituicao).' ';
        $link = ' '.Competencias::normalizar(preg_replace('#^https?://(www\.)?#i', '', $url) ?? '').' ';
        foreach (self::CAPAS_INSTITUICAO as $chaves => $img) {
            foreach (explode('|', $chaves) as $c) if (str_contains($busca, ' '.$c.' ') || str_contains($link, ' '.$c.' ')) return $img;
        }
        return '';
    }

    /**
     * Primeiro link http(s) do texto. Aceita parênteses no endereço quando estão em par
     * (ex.: ".../Cartilha%20(2)%20(1).pdf" — antes o link era cortado no primeiro ")" e ficava quebrado),
     * mas não leva junto o ")" de quem escreveu o link entre parênteses nem a pontuação do fim da frase.
     */
    public static function primeiroLink(string $texto): string {
        if (!preg_match('/https?:\/\/[^\s<>"\'\]]+/i', $texto, $m)) return '';
        $u = $m[0];
        while ($u !== '') {
            $ultimo = substr($u, -1);
            if (strpbrk($ultimo, '.,;:!?') !== false) { $u = substr($u, 0, -1); continue; }
            if ($ultimo === ')' && substr_count($u, '(') < substr_count($u, ')')) { $u = substr($u, 0, -1); continue; }
            break;
        }
        return $u;
    }

    /** Rótulos aceitos nas fichas (com ou sem acento, maiúsculas ou não) → campo. */
    private const ROTULOS = [
        'titulo' => 'titulo|nome|nome do curso|curso|e ?book',
        'tipo' => 'tipo|formato',
        'instituicao' => 'instituicao|plataforma|oferecido por|realizacao',
        'modalidade' => 'modalidade',
        'cidade' => 'cidade|local|cidade uf',
        'nivel' => 'nivel',
        'duracao' => 'carga horaria|duracao',
        'gratuito' => 'gratuito|gratis|custo',
        'preco' => 'preco|valor',
        'url' => 'link|link oficial|url|site|endereco',
        'pdf_url' => 'pdf|link do pdf|link direto do pdf|arquivo pdf|pdf direto|download|link de download|link para download',
        'categoria' => 'area|categoria',
        'descricao' => 'descricao|resumo|sobre',
        'imagem' => 'imagem|imagem da capa|imagem de capa|capa|link da imagem|url da imagem|foto|banner',
    ];

    /**
     * IMPORTAÇÃO EM LOTE: lê várias fichas coladas de uma vez (resposta do prompt de pesquisa, ex.: Perplexity).
     * Fichas separadas por uma linha "---" (ou por um novo "Título:"). Cada ficha passa pela extração normal
     * (doTexto) e os campos rotulados da ficha têm prioridade. Nada é gravado aqui.
     * @param string[] $categorias nomes das categorias de curso cadastradas
     * @return array<int,array<string,mixed>>
     */
    public static function fichas(string $texto, array $categorias = []): array {
        $texto = trim(str_replace(["\r\n", "\r"], "\n", $texto));
        if ($texto === '') return [];
        // Tira a formatação de Markdown que as IAs costumam usar (negrito, títulos, listas, links [texto](url)).
        // O link do Markdown pode ter parênteses em par no endereço: [pdf](https://site/arquivo%20(2).pdf).
        $texto = preg_replace(['/\*\*|__/u', '/^\s*#{1,6}\s*/mu', '/\[([^\]]*)\]\((https?:\/\/(?:[^()\s]|\([^()\s]*\))+)\)/u', '/\[\d+\]/u'], ['', '', '$2', ''], $texto) ?? $texto;
        $blocos = preg_split('/^\s*(?:-{3,}|={3,}|_{3,})\s*$/mu', $texto) ?: [];
        if (count($blocos) === 1) $blocos = preg_split('/\n(?=\s*(?:\d+[.)]\s*)?t[ií]tulo\s*:)/iu', $texto) ?: [$texto];
        $out = [];
        foreach ($blocos as $b) {
            $b = trim($b);
            if ($b === '' || !preg_match('/\S+\s*:\s*\S/u', $b)) continue;
            $campos = self::camposRotulados($b);
            if (($campos['titulo'] ?? '') === '' && ($campos['url'] ?? '') === '') continue; // texto solto (introdução/conclusão da IA)
            $out[] = self::daFicha($b, $campos, $categorias);
        }
        return $out;
    }

    /** "Título: X" → ['titulo' => 'X']; linhas sem rótulo conhecido continuam o campo anterior (descrição longa). */
    private static function camposRotulados(string $bloco): array {
        $campos = []; $atual = null;
        foreach (explode("\n", $bloco) as $l) {
            $l = trim_u(trim($l), " \t*•·-–—>");
            if ($l === '') continue;
            $achou = null;
            if (preg_match('/^(?:\d+[.)]\s*)?([\p{L} \/]{2,30}?)\s*:\s*(.*)$/u', $l, $m)) {
                $rot = Competencias::normalizar($m[1]);
                foreach (self::ROTULOS as $campo => $rx) if (preg_match('/^(?:'.$rx.')$/', $rot)) { $achou = $campo; break; }
            }
            if ($achou) { $atual = $achou; $campos[$atual] = trim($m[2]); }
            elseif ($atual === 'descricao') $campos['descricao'] .= ' '.$l;
        }
        return $campos;
    }

    private static function daFicha(string $bloco, array $c, array $categorias): array {
        $r = self::doTexto($bloco); // extração normal: preenche o que a ficha não disser com clareza
        $n = fn(string $k) => Competencias::normalizar($c[$k] ?? '');
        if (($c['titulo'] ?? '') !== '') $r['titulo'] = mb_substr(trim($c['titulo'], ' "\''), 0, 255);
        if (($c['instituicao'] ?? '') !== '') $r['instituicao'] = mb_substr($c['instituicao'], 0, 255);
        if (($link = self::primeiroLink($c['url'] ?? '')) !== '') $r['url'] = $link;
        // Link direto do PDF (e-book gratuito): ao cadastrar, o PDF vai para a biblioteca da plataforma.
        $r['pdf_url'] = self::primeiroLink($c['pdf_url'] ?? '');
        if ($r['url'] === '' && $r['pdf_url'] !== '') $r['url'] = $r['pdf_url'];
        $r['instituicao'] = FontesCursos::nomeOficial($r['instituicao'], $r['url']);   // nome padronizado pela fonte oficial do link
        if ($n('tipo') !== '') $r['tipo'] = preg_match('/e ?book|livro|apostila|guia|pdf/', $n('tipo')) ? 'ebook' : (preg_match('/video|webinar|aula gravada/', $n('tipo')) ? 'video' : 'curso');
        if ($n('modalidade') !== '') $r['modalidade'] = preg_match('/hibrid|semipresencial/', $n('modalidade')) ? 'hibrido' : (preg_match('/^presencial/', $n('modalidade')) ? 'presencial' : 'ead');
        if ($n('nivel') !== '') $r['nivel'] = preg_match('/avanc/', $n('nivel')) ? 'avancado' : (preg_match('/intermed/', $n('nivel')) ? 'intermediario' : 'iniciante');
        if (($c['duracao'] ?? '') !== '' && !preg_match('/^(nao informad|n\/?a|-)/i', $n('duracao'))) $r['duracao'] = mb_substr($c['duracao'], 0, 50);
        $preco = decimal_ou_null(preg_replace('/[^\d.,]/', '', $c['preco'] ?? '') ?? '');
        if ($n('gratuito') !== '') $r['gratuito'] = preg_match('/^(sim|gratuito|gratis|free|0)/', $n('gratuito')) ? 1 : 0;
        elseif ($preco !== null && $preco > 0) $r['gratuito'] = 0;
        $r['preco'] = $r['gratuito'] ? null : ($preco ?: $r['preco']);
        // Descrição: a da ficha + o local, quando for presencial (o cadastro não tem campo de cidade).
        $desc = trim($c['descricao'] ?? '') !== '' ? trim($c['descricao']) : '';
        $cidade = trim($c['cidade'] ?? '');
        if ($cidade !== '' && $r['modalidade'] !== 'ead' && !preg_match('/^(online|ead|nao se aplica|n\/?a|-)/i', Competencias::normalizar($cidade))) $desc .= ($desc !== '' ? "\n" : '').'Local: '.$cidade.'.';
        if ($desc !== '') $r['descricao'] = $desc;
        // Área: a da ficha se for uma categoria cadastrada; senão, a da regra ou a do calibrador (categoriaSugerida).
        $r['competencias'] = Competencias::doCurso($r);
        $areaDaFicha = '';
        foreach ($categorias as $cat) if (Competencias::normalizar($cat) === $n('categoria')) $areaDaFicha = $cat;
        $r['calibrador'] = array_values(array_filter($r['calibrador'], fn($m) => $m['campo'] !== 'categoria'));   // o doTexto já opinou; decide de novo com a descrição da ficha
        if ($areaDaFicha !== '') $r['categoria'] = $areaDaFicha; else $r = self::categoriaSugerida($r);
        // Imagem que a pesquisa trouxe (capa do e-book / imagem do curso): o link é baixado ao cadastrar (ImagemRemota);
        // um caminho do próprio site que existe (assets/img/... ou assets/uploads/...) entra direto (imagem_propria).
        // Sem nenhum dos dois: o banner da instituição como reserva (e, sem ele, a imagem padrão ao salvar).
        $r['imagem_url'] = self::primeiroLink($c['imagem'] ?? '');
        $local = $r['imagem_url'] === '' ? caminho_imagem_valido((string)($c['imagem'] ?? '')) : '';
        $r['imagem_propria'] = $local !== '' && (($up = caminho_upload($local)) !== null ? is_file($up) : is_file(PUBLIC_DIR.'/'.$local));
        $r['imagem'] = $r['imagem_propria'] ? $local : self::capa($r['instituicao'], $r['url'], $r['tipo']);
        return $r;
    }

    /**
     * Prompt para a IA de pesquisa (Perplexity, ChatGPT…) no formato de ficha que fichas() lê.
     * Atalho para o prompt da pesquisa guiada (FontesCursos::prompt), sem direcionamento de formato/área/fonte.
     */
    public static function promptPesquisa(array $categorias, int $quantidade = 20): string {
        return FontesCursos::prompt(['quantidade' => $quantidade], $categorias);
    }

    public static function categoria(array $competencias): string {
        $melhor = ''; $max = 0;
        foreach (self::CATEGORIAS as $cat => $lista) {
            $q = count(array_intersect($competencias, $lista));
            if ($q > $max) { $max = $q; $melhor = $cat; }
        }
        return $melhor;
    }
}
