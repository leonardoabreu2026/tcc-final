<?php
declare(strict_types=1);

/**
 * Componentes de apresentação no estilo "portal de notícias", usados nas telas:
 * vagas aparecem como notícias e cursos/e-books como reportagens.
 *
 *  - pt_*  : formatação de datas, textos, preços e mídia (imagens com fundo desfocado);
 *  - cv_*  : blocos prontos do layout (faixa de título, título de seção, cartões de vaga e de curso).
 *
 * Só formatação: não acessa o banco (os dados chegam prontos do controller).
 */

/** Converte string de data/hora do banco em DateTimeImmutable (ou null). */
function pt_dt(?string $v): ?DateTimeImmutable {
    if ($v === null || trim($v) === '' || str_starts_with($v, '0000')) return null;
    try { return new DateTimeImmutable($v); } catch (Throwable) { return null; }
}

/** Momento de publicação de uma vaga ou curso (created_at; se não houver, data_publicacao). */
function pt_quando(array $item): ?string {
    return ($item['created_at'] ?? null) ?: ($item['data_publicacao'] ?? null);
}

/** "há 5 minutos", "há 2 horas", "ontem", "há 3 dias" ou a data. */
function pt_tempo_relativo(?string $quando): string {
    $d = pt_dt($quando);
    if (!$d) return '';
    $seg = time() - $d->getTimestamp();
    if ($seg < 0) return 'agora';
    if ($seg < 60) return 'agora há pouco';
    if ($seg < 3600) { $m = intdiv($seg, 60); return 'há '.$m.' '.($m === 1 ? 'minuto' : 'minutos'); }
    if ($seg < 86400) { $h = intdiv($seg, 3600); return 'há '.$h.' '.($h === 1 ? 'hora' : 'horas'); }
    $dias = (int)(new DateTimeImmutable('today'))->diff($d->setTime(0, 0))->days;
    if ($dias <= 1) return 'ontem';
    if ($dias < 7) return 'há '.$dias.' dias';
    if ($dias < 30) { $s = intdiv($dias, 7); return 'há '.$s.' '.($s === 1 ? 'semana' : 'semanas'); }
    return $d->format('d/m/Y');
}

/** "23/09/2026 às 00h45" (ou só a data, se não houver hora). */
function pt_data_hora(?string $quando): string {
    $d = pt_dt($quando);
    if (!$d) return '';
    return strlen((string)$quando) > 10 ? $d->format('d/m/Y \à\s H\hi') : $d->format('d/m/Y');
}

/** Elemento <time> com data relativa e a data completa no title (acessível). */
function pt_time(?string $quando, string $classe = 'pt-hora'): string {
    $d = pt_dt($quando);
    if (!$d) return '';
    return '<time class="'.e($classe).'" datetime="'.e($d->format('c')).'" title="'.e(pt_data_hora($quando)).'">'.e(pt_tempo_relativo($quando)).'</time>';
}

/** Corta o texto em até $max caracteres sem quebrar palavra. */
function pt_resumo(?string $t, int $max = 160): string {
    $t = trim(preg_replace('/\s+/u', ' ', (string)$t) ?? '');
    if (mb_strlen($t) <= $max) return $t;
    $corte = mb_substr($t, 0, $max);
    $esp = mb_strrpos($corte, ' ');
    return trim_u(mb_substr($corte, 0, $esp ?: $max), ' ,.;:-–', 'fim').'…';
}

/** Link externo seguro: só http(s). */
function pt_url_externa(?string $u): string {
    $u = trim((string)$u);
    return preg_match('#^https?://#i', $u) ? $u : '';
}

/**
 * Mídia no padrão do portal: a imagem aparece inteira (contain) sobre um fundo
 * desfocado dela mesma — fica bonito tanto para cartazes em pé quanto para banners.
 * Sem imagem, mostra um fundo na cor da editoria com o ícone.
 */
function pt_midia(?string $img, string $alt, string $classe = '', bool $lazy = true, string $icone = 'vagas'): string {
    $img = trim((string)$img);
    if ($img === '') {
        $a11y = $alt === '' ? 'aria-hidden="true"' : 'role="img" aria-label="'.e($alt).'"';
        return '<div class="pt-midia pt-midia-vazia '.e($classe).'" '.$a11y.'>'.icone($icone, 44).'</div>';
    }
    $src = preg_match('#^https?://#i', $img) ? $img : url($img);
    return '<div class="pt-midia '.e($classe).'" style="--pt-img:url(\''.e($src).'\')">'
        .'<img src="'.e($src).'" alt="'.e($alt).'"'.($lazy ? ' loading="lazy"' : '').' decoding="async"></div>';
}

/** Local no formato "Guará/DF". */
function pt_local(array $v): string {
    $c = trim((string)($v['cidade'] ?? '')); $uf = trim((string)($v['uf'] ?? ''));
    return $c !== '' ? $c.($uf !== '' ? '/'.$uf : '') : ($uf ?: 'DF');
}

/** Linha fina da vaga: "Empresa · Local · Salário". */
function pt_linha_fina_vaga(array $v): string {
    return ($v['empresa_nome'] ?: 'Empresa').' · '.pt_local($v).' · '.salario_texto($v['salario_minimo'] ?? null, $v['salario_maximo'] ?? null);
}

/** Lide (primeiro parágrafo) da matéria, gerado a partir da ficha da vaga. */
function pt_lide_vaga(array $v): string {
    $empresa = $v['empresa_nome'] ?: 'Uma empresa do DF';
    $regime = ['clt' => 'pelo regime CLT', 'pj' => 'como pessoa jurídica (PJ)', 'estagio' => 'na modalidade de estágio', 'temporario' => 'em caráter temporário'][$v['tipo_vaga'] ?? ''] ?? '';
    $nivel = ['estagiario' => 'estudantes', 'junior' => 'profissionais de nível júnior', 'pleno' => 'profissionais de nível pleno', 'senior' => 'profissionais de nível sênior'][$v['nivel_experiencia'] ?? ''] ?? 'profissionais';
    $modelo = ['presencial' => 'com trabalho presencial', 'remoto' => 'com trabalho 100% remoto', 'hibrido' => 'em modelo híbrido'][$v['remoto'] ?? ''] ?? '';
    $sal = salario_texto($v['salario_minimo'] ?? null, $v['salario_maximo'] ?? null);
    $salTxt = $sal === 'A combinar' ? 'O salário será combinado com os selecionados.' : 'A remuneração informada é de '.$sal.'.';
    return sprintf('%s está com inscrições abertas para a vaga de %s em %s. A contratação é %s, voltada a %s, %s. %s',
        $empresa, $v['titulo'], pt_local($v), $regime, $nivel, $modelo, $salTxt);
}

/** Nome do formato do conteúdo: Curso, E-book, Vídeo. */
function pt_formato(string $tipo): string { return rotulo($tipo); }

/**
 * Cada formato tem a sua página própria (cursos só com cursos, e-books só com e-books, vídeos só com vídeos):
 * [rótulo no plural, link da lista, ícone]. Tipo desconhecido cai em "curso".
 */
function pt_secao_formato(string $tipo): array {
    return match ($tipo) {
        'ebook' => ['E-books', url('cursos.php?tipo=ebook'), 'ebooks'],
        'video' => ['Vídeos', url('cursos.php?tipo=video'), 'play'],
        default => ['Cursos', url('cursos.php'), 'cursos'],
    };
}

/** Verbo do botão de acesso ao conteúdo. */
function pt_cta_curso(string $tipo): string {
    return ['ebook' => 'Acessar e-book', 'video' => 'Acessar vídeo'][$tipo] ?? 'Acessar curso';
}

/**
 * Botão de acesso ao conteúdo, decidido pelo ENDEREÇO (não pelo formato):
 *  - PDF da nossa biblioteca (assets/uploads/biblioteca_*.pdf) → "Baixar": baixa direto, com o nome do conteúdo;
 *  - link da web (página ou PDF de outro site)                → "Acessar": abre em nova aba.
 * @return array{href:string,baixar:bool,curto:string,longo:string,icone:string,atributos:string}|null null = sem link
 */
function pt_acesso_conteudo(array $c): ?array {
    $u = trim((string)($c['url'] ?? ''));
    if (eh_pdf_biblioteca($u)) {
        $nome = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower((string)($c['titulo'] ?? 'conteudo'))) ?? '', '-') ?: 'conteudo';
        return ['href' => url($u), 'baixar' => true, 'curto' => 'Baixar', 'longo' => 'Baixar PDF', 'icone' => 'download',
                'atributos' => ' download="'.e(mb_substr($nome, 0, 80)).'.pdf"'];
    }
    $ext = pt_url_externa($u);
    if ($ext === '') return null;
    return ['href' => $ext, 'baixar' => false, 'curto' => 'Acessar', 'longo' => pt_cta_curso((string)($c['tipo'] ?? 'curso')), 'icone' => 'externo',
            'atributos' => ' target="_blank" rel="noopener"'];
}

/** Preço do curso: "Gratuito" ou "R$ 49,90". */
function pt_preco(array $c): string {
    return !empty($c['gratuito']) || (float)($c['preco'] ?? 0) <= 0 ? 'Gratuito' : 'R$ '.number_format((float)$c['preco'], 2, ',', '.');
}

/** Linha fina da reportagem (curso/e-book). */
function pt_linha_fina_curso(array $c): string {
    $partes = [($c['instituicao'] ?: 'Instituição parceira').' oferece '.mb_strtolower(pt_formato((string)$c['tipo'])).' '.(pt_preco($c) === 'Gratuito' ? 'gratuito' : 'por '.pt_preco($c))];
    $det = [];
    if (!empty($c['modalidade'])) $det[] = 'modalidade '.rotulo((string)$c['modalidade']);
    if (!empty($c['nivel'])) $det[] = 'nível '.mb_strtolower(rotulo((string)$c['nivel']));
    if (!empty($c['duracao']) && mb_strtolower((string)$c['duracao']) !== 'variável') $det[] = 'duração de '.$c['duracao'];
    return $partes[0].($det ? ', '.implode(', ', $det) : '').'.';
}

/**
 * Transforma texto livre em parágrafos HTML (escapados).
 * Linhas iniciadas por "-", "•" ou "*" viram lista.
 */
function pt_paragrafos(?string $t): string {
    $linhas = preg_split('/\R/u', trim((string)$t)) ?: [];
    $html = ''; $lista = [];
    $fechar = function () use (&$lista, &$html): void {
        if ($lista) { $html .= '<ul>'.implode('', array_map(fn($l) => '<li>'.e($l).'</li>', $lista)).'</ul>'; $lista = []; }
    };
    foreach ($linhas as $l) {
        $l = trim($l);
        if ($l === '') { $fechar(); continue; }
        if (preg_match('/^[-•*–]\s*(.+)$/u', $l, $m)) { $lista[] = $m[1]; continue; }
        $fechar();
        $html .= '<p>'.e($l).'</p>';
    }
    $fechar();
    return $html;
}

/** Ordena vagas da mais nova para a mais antiga (created_at, depois id). */
function pt_ordenar_recentes(array $vagas): array {
    usort($vagas, fn($a, $b) => [(string)pt_quando($b), (int)$b['id']] <=> [(string)pt_quando($a), (int)$a['id']]);
    return $vagas;
}

/** "1 visualização" / "12 visualizações". */
function pt_views(mixed $n): string {
    $n = (int)$n;
    return number_format($n, 0, ',', '.').' '.($n === 1 ? 'visualização' : 'visualizações');
}

/** Selo público "Empresa Premium" (assinatura ativa), exibido ao lado do nome da empresa. */
function pt_selo_premium(array $v): string {
    return !empty($v['empresa_premium']) ? ' <span class="pt-premium" title="Empresa com plano Premium ativo">✔ Empresa Premium</span>' : '';
}

/** Rótulos dos chips da vaga: contratação, nível e modelo de trabalho (ex.: CLT, Júnior, Presencial). */
function pt_chips_vaga(array $v): array {
    return array_values(array_filter([
        rotulo((string)($v['tipo_vaga'] ?? '')), rotulo((string)($v['nivel_experiencia'] ?? '')), rotulo((string)($v['remoto'] ?? '')),
    ]));
}

/**
 * Descrição da vaga em HTML (escapado): parágrafos e, quando as linhas começam com marcador
 * ("-", "•", "*") ou numeração ("1.", "2)"), listas com ou sem número.
 */
function pt_texto_vaga(?string $t): string {
    $html = ''; $lista = []; $tag = 'ul';
    $fechar = function () use (&$lista, &$html, &$tag): void {
        if ($lista) { $html .= '<'.$tag.'>'.implode('', array_map(fn($l) => '<li>'.e($l).'</li>', $lista)).'</'.$tag.'>'; $lista = []; }
    };
    foreach (preg_split('/\R/u', trim((string)$t)) ?: [] as $l) {
        $l = trim($l);
        if ($l === '') { $fechar(); continue; }
        if (preg_match('/^(?:[-•*–✓✔]\s*|(\d{1,2})[.)]\s+)(.+)$/u', $l, $m)) {
            $nova = $m[1] !== '' ? 'ol' : 'ul';
            if ($nova !== $tag) { $fechar(); $tag = $nova; }
            $lista[] = $m[2];
            continue;
        }
        $fechar();
        $html .= '<p>'.e($l).'</p>';
    }
    $fechar();
    return $html;
}

/**
 * Requisitos e benefícios como lista: uma linha = um item, sem o marcador do início.
 * Junta a linha quebrada no meio da frase (terminada em vírgula, "e", "ou", "de"...) com a seguinte.
 * @return list<string>
 */
function pt_itens_vaga(?string $t): array {
    $itens = []; $emendar = false;
    foreach (preg_split('/\R/u', trim((string)$t)) ?: [] as $l) {
        $l = trim((string)preg_replace('/^(?:[-•*–✓✔]|\d{1,2}[.)](?=\s))\s*/u', '', trim($l)));
        if ($l === '') { $emendar = false; continue; }
        if ($emendar && $itens) $itens[count($itens) - 1] .= ' '.$l; else $itens[] = $l;
        $emendar = (bool)preg_match('/(?:,|\s(?:e|ou|de|da|do|das|dos|com|para))$/iu', $l);
    }
    return $itens;
}

/**
 * Contato do anúncio em itens clicáveis: WhatsApp (wa.me, com mensagem pronta), telefone fixo (tel:),
 * e-mail (mailto:) e link. Ex.: "Jéssica — WhatsApp (61) 99839-0202 · rh@empresa.com".
 * Celular (número começando com 6 a 9) ou "WhatsApp" escrito no anúncio viram link do WhatsApp.
 * @return list<array{tipo:string, texto:string, href:string, nome:string}>
 */
function pt_contatos_vaga(?string $contato, string $mensagem = ''): array {
    $itens = [];
    foreach (preg_split('/\s*(?:·|\||;|\R)\s*/u', trim((string)$contato)) ?: [] as $parte) {
        if (trim($parte) === '') continue;
        $zap = (bool)preg_match('/whats|zap/iu', $parte);
        $achados = []; $resto = $parte;
        if (preg_match_all('#https?://[^\s,]+#iu', $resto, $m)) foreach ($m[0] as $u) {
            $achados[] = ['tipo' => 'link', 'texto' => preg_replace('#^https?://#i', '', $u), 'href' => $u];
            $resto = str_replace($u, ' ', $resto);
        }
        if (preg_match_all('/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/u', $resto, $m)) foreach ($m[0] as $em) {
            $achados[] = ['tipo' => 'email', 'texto' => $em, 'href' => 'mailto:'.$em];
            $resto = str_replace($em, ' ', $resto);
        }
        if (preg_match_all('/\(?\b(\d{2})\)?[\s.]*(\d{4,5})[\s.-]?(\d{3,4})\b/u', $resto, $m, PREG_SET_ORDER)) foreach ($m as $f) {
            $num = $f[2].$f[3];
            $whats = $zap || (int)$num[0] >= 6;
            $achados[] = ['tipo' => $whats ? 'whatsapp' : 'telefone', 'texto' => '('.$f[1].') '.$f[2].'-'.$f[3],
                'href' => $whats ? 'https://wa.me/55'.$f[1].$num.($mensagem !== '' ? '?text='.rawurlencode($mensagem) : '') : 'tel:+55'.$f[1].$num];
            $resto = str_replace($f[0], ' ', $resto);
        }
        // O que sobra (tirando "WhatsApp", "tel.", travessões...) é o nome de quem atende.
        $nome = trim((string)preg_replace('/\b(?:whats\s*app|whatsapp|zap|tel(?:efone)?|e-?mail|contato)\b\.?|[:—–\-()]/iu', ' ', $resto));
        $nome = trim((string)preg_replace('/\s+/u', ' ', $nome));
        if (!$achados) { $itens[] = ['tipo' => 'texto', 'texto' => trim($parte), 'href' => '', 'nome' => '']; continue; }
        foreach ($achados as $a) $itens[] = $a + ['nome' => $nome];
    }
    return $itens;
}

// =====================================================================
// Componentes do layout (v5) — usados na capa e nas páginas de vagas/cursos
// =====================================================================

/**
 * Faixa de título das páginas internas, com o fundo de Brasília.
 * @param array<string,string> $trilha rótulo => link (o último item não precisa de link)
 */
function cv_faixa(string $titulo, string $sub = '', array $trilha = []): string {
    $h = '<section class="cv-faixa"><div class="cv-wrap">';
    if ($trilha) {
        $itens = ['<a href="'.e(url('index.php')).'">Início</a>'];
        foreach ($trilha as $rot => $link) $itens[] = $link !== '' ? '<a href="'.e($link).'">'.e($rot).'</a>' : '<span aria-current="page">'.e($rot).'</span>';
        $h .= '<nav class="cv-trilha" aria-label="Você está em">'.implode(' <span aria-hidden="true">›</span> ', $itens).'</nav>';
    }
    $h .= '<h1>'.e($titulo).'</h1>';
    if ($sub !== '') $h .= '<p>'.e($sub).'</p>';
    return $h.'</div></section>';
}

/** Título de seção com ícone, subtítulo e link "ver todos". */
function cv_titulo_secao(string $icone, string $titulo, string $sub = '', string $link = '', string $rotuloLink = 'Ver todas'): string {
    return '<div class="cv-secao-tit"><span class="cv-secao-ic">'.icone($icone, 22).'</span><div><h2>'.e($titulo).'</h2>'
        .($sub !== '' ? '<p>'.e($sub).'</p>' : '').'</div>'
        .($link !== '' ? '<a class="cv-ver" href="'.e($link).'">'.e($rotuloLink).' '.icone('seta', 14).'</a>' : '').'</div>';
}

/**
 * Cartão de vaga (capa, lista de vagas, "outras vagas"): cartaz inteiro, área + hora, título,
 * empresa, local, salário, chips (contratação, nível, modelo) e as ações. Altura igual na fileira:
 * o título tem até 2 linhas e os textos longos terminam em "…".
 * @param ?float  $match  % de match do candidato logado (ou null)
 * @param ?string $status status da candidatura do candidato (ou null)
 */
function cv_card_vaga(array $v, ?float $match = null, ?string $status = null): string {
    $id = (int)$v['id'];
    $link = url('vaga.php?id='.$id);
    $empresa = $v['empresa_nome'] ?: 'Empresa';
    $salario = salario_texto($v['salario_minimo'] ?? null, $v['salario_maximo'] ?? null);
    $h = '<article class="cv-card an-card'.(!empty($v['destaque']) ? ' cv-card-destaque' : '').'">';
    $h .= '<a class="cv-card-img an-card-img" href="'.e($link).'" tabindex="-1" aria-hidden="true">'.cv_cartaz_vaga($v['imagem'] ?? '').'</a>';
    if (!empty($v['destaque'])) $h .= '<span class="an-card-fita">★ Destaque</span>';
    if ($match !== null) $h .= '<span class="cv-card-match an-card-match score-badge '.e(classe_match($match)).'" title="Seu match com esta vaga">'.number_format($match, 0).'%<small> match</small></span>';
    $h .= '<div class="cv-card-corpo an-card-corpo">';
    $h .= '<p class="cv-chapeu"><span>'.e($v['categoria_nome'] ?? 'Vaga').'</span>'.pt_time(pt_quando($v), 'cv-hora').'</p>';
    $h .= '<h3><a href="'.e($link).'">'.e($v['titulo']).'</a></h3>';
    $h .= '<p class="an-card-linha" title="'.e($empresa).'">'.icone('maleta', 14).'<span>'.e($empresa).'</span>'
        .(!empty($v['empresa_premium']) ? '<b class="an-selo" title="Empresa com plano Premium ativo">✔<span class="sr-only"> Empresa Premium</span></b>' : '').'</p>';
    $h .= '<p class="an-card-linha">'.icone('local', 14).'<span>'.e(pt_local($v)).'</span></p>';
    $h .= '<p class="an-card-salario'.($salario === 'A combinar' ? ' an-combinar' : '').'">'.icone('dinheiro', 14).'<span>'.e($salario).'</span></p>';
    $chips = pt_chips_vaga($v);
    if ($chips) $h .= '<ul class="an-chips" aria-label="Contratação, nível e modelo">'.implode('', array_map(fn($c) => '<li>'.e($c).'</li>', $chips)).'</ul>';
    if ($status !== null) $h .= '<p class="cv-card-status an-card-status">'.icone('check', 14).'Candidatura: '.e(mb_strtolower(rotulo($status))).'</p>';
    $h .= '</div><div class="cv-card-acoes an-card-acoes">';
    $h .= '<a class="cv-btn an-btn-linha" href="'.e($link).'">Saiba mais<span class="sr-only"> sobre a vaga de '.e($v['titulo']).'</span></a>';
    if ($status === null && (!usuarioLogado() || isCandidato())) {
        $h .= '<a class="cv-btn cv-btn-verde" href="'.e(url(usuarioLogado() ? 'candidatar.php?vaga_id='.$id : 'login.php')).'">Candidatar-se<span class="sr-only"> à vaga de '.e($v['titulo']).'</span></a>';
    }
    return $h.'</div></article>';
}

/**
 * Cartaz da vaga inteiro (sem cortar), sobre um fundo suave feito da própria imagem —
 * serve para cartazes em pé, quadrados ou deitados. Sem imagem, mostra o ícone de vagas.
 */
function cv_cartaz_vaga(?string $img, string $alt = '', bool $lazy = true): string {
    $img = trim((string)$img);
    if ($img === '') return '<span class="an-cartaz an-cartaz-vazio"'.($alt !== '' ? ' role="img" aria-label="'.e($alt).'"' : '').'>'.icone('vagas', 44).'</span>';
    $src = preg_match('#^https?://#i', $img) ? $img : url($img);
    return '<span class="an-cartaz" style="--an-img:url(\''.e($src).'\')"><img src="'.e($src).'" alt="'.e($alt).'"'.($lazy ? ' loading="lazy"' : '').' decoding="async"></span>';
}

/** Botões de contato do anúncio (WhatsApp, telefone, e-mail, link), a partir de pt_contatos_vaga(). */
function cv_contatos_vaga(array $contatos): string {
    if (!$contatos) return '';
    $info = ['whatsapp' => ['whatsapp', 'WhatsApp'], 'telefone' => ['telefone', 'Telefone'], 'email' => ['email', 'E-mail'], 'link' => ['externo', 'Site'], 'texto' => ['formulario', 'Contato']];
    $h = '<ul class="an-contatos">';
    foreach ($contatos as $c) {
        [$ic, $rot] = $info[$c['tipo']] ?? $info['texto'];
        $rot .= $c['nome'] !== '' ? ' · '.$c['nome'] : '';
        $corpo = '<span class="an-contato-ic">'.icone($ic, 18).'</span><span class="an-contato-txt"><small>'.e($rot).'</small><b>'.e($c['texto']).'</b></span>';
        if ($c['href'] === '') { $h .= '<li><span class="an-contato an-contato-'.e($c['tipo']).'">'.$corpo.'</span></li>'; continue; }
        $novaAba = in_array($c['tipo'], ['whatsapp', 'link'], true);
        $h .= '<li><a class="an-contato an-contato-'.e($c['tipo']).'" href="'.e($c['href']).'"'.($novaAba ? ' target="_blank" rel="noopener"' : '').'>'.$corpo
            .($novaAba ? '<span class="sr-only"> (abre em nova aba)</span>' : '').'</a></li>';
    }
    return $h.'</ul>';
}

/** Cartão de curso/e-book/vídeo: capa, formato + área, título, instituição e ações. */
function cv_card_curso(array $c): string {
    $id = (int)$c['id'];
    $link = url('curso.php?id='.$id);
    $acesso = pt_acesso_conteudo($c);
    $img = trim((string)($c['imagem'] ?? ''));
    // E-book: a capa (em pé) aparece inteira, sem cortar, sobre um fundo suave dela mesma — como o cartaz das vagas.
    $midia = $c['tipo'] === 'ebook' && $img !== '' ? cv_cartaz_vaga($img) : cv_img($img, pt_secao_formato((string)$c['tipo'])[2]);
    $h = '<article class="cv-card cv-card-curso'.($c['tipo'] === 'ebook' ? ' cv-card-ebook' : '').'">';
    $h .= '<a class="cv-card-img" href="'.e($link).'" tabindex="-1" aria-hidden="true">'.$midia.'</a>';
    $h .= '<div class="cv-card-corpo">';
    $h .= '<p class="cv-chapeu"><span>'.e(pt_formato((string)$c['tipo'])).($c['categoria_nome'] ? ' · '.e($c['categoria_nome']) : '').'</span></p>';
    $h .= '<h3><a href="'.e($link).'">'.e($c['titulo']).'</a></h3>';
    $h .= '<p class="cv-card-txt">'.e(pt_resumo($c['descricao'] ?: pt_linha_fina_curso($c), 95)).'</p>';
    $h .= '<p class="cv-card-meta">'.e($c['instituicao'] ?: 'Instituição parceira').'<br><b>'.e(pt_preco($c)).'</b> · '.e(rotulo((string)$c['modalidade'])).($c['duracao'] ? ' · '.e($c['duracao']) : '').'</p>';
    $h .= '</div><div class="cv-card-acoes">';
    $h .= '<a class="cv-btn cv-btn-azul" href="'.e($link).'">'.icone('olho', 15).'Saiba mais</a>';
    if ($acesso) $h .= '<a class="cv-btn cv-btn-verde" href="'.e($acesso['href']).'"'.$acesso['atributos'].'>'.icone($acesso['icone'], 15).$acesso['curto']
                     .'<span class="sr-only"> '.e($c['titulo']).($acesso['baixar'] ? ' (PDF)' : ' (abre em nova aba)').'</span></a>';
    return $h.'</div></article>';
}

/** Imagem do cartão, sem fundo: a figura aparece limpa (sem imagem, mostra o ícone). */
function cv_img(?string $img, string $icone = 'vagas'): string {
    $img = trim((string)$img);
    if ($img === '') return '<span class="cv-img-vazia">'.icone($icone, 40).'</span>';
    $src = preg_match('#^https?://#i', $img) ? $img : url($img);
    return '<img src="'.e($src).'" alt="" loading="lazy" decoding="async">';
}

/**
 * Paginação numerada (‹ Anterior · 1 … 4 5 6 … 12 · Próxima ›), usada nas listas públicas e nas tabelas do painel.
 * $href(int $pagina) devolve o endereço de cada página já escapado para o atributo href.
 */
function cv_paginacao(int $pagina, int $paginas, callable $href, string $rotulo = 'Páginas de resultados', string $classe = ''): string {
    if ($paginas <= 1) return '';
    $h = '<nav class="an-paginacao'.($classe !== '' ? ' '.e($classe) : '').'" aria-label="'.e($rotulo).'">';
    $h .= $pagina > 1 ? '<a class="an-pag-seta" href="'.$href($pagina - 1).'" rel="prev">‹ Anterior</a>' : '<span class="an-pag-seta" aria-disabled="true">‹ Anterior</span>';
    $antes = 0;
    for ($n = 1; $n <= $paginas; $n++) {
        if ($n !== 1 && $n !== $paginas && abs($n - $pagina) > 1) { if ($antes !== -1) $h .= '<span class="an-pag-reticencias" aria-hidden="true">…</span>'; $antes = -1; continue; }
        $antes = $n;
        $h .= $n === $pagina ? '<span class="an-pag-num ativo" aria-current="page"><span class="sr-only">Página </span>'.$n.'</span>'
                             : '<a class="an-pag-num" href="'.$href($n).'"><span class="sr-only">Página </span>'.$n.'</a>';
    }
    $h .= $pagina < $paginas ? '<a class="an-pag-seta" href="'.$href($pagina + 1).'" rel="next">Próxima ›</a>' : '<span class="an-pag-seta" aria-disabled="true">Próxima ›</span>';
    return $h.'</nav>';
}
