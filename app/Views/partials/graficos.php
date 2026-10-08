<?php
/**
 * Gráficos e peças dos painéis (empresa e administrador), no estilo dos dashboards do Power BI.
 * Tudo é SVG gerado aqui no servidor — sem biblioteca de gráficos e sem dependência externa.
 *
 * Regras seguidas em todos os gráficos:
 *  - cores por papel: uma cor para série única, rampa azul (claro → escuro) para categorias
 *    ordenadas e três cores categóricas validadas para daltonismo (ver painel.css, --gf-*);
 *  - marcas finas, linhas de grade discretas, valores visíveis e rótulos com texto (nunca só cor);
 *  - cada gráfico tem aria-label com o resumo dos dados e uma tabela "Ver dados" como alternativa;
 *  - a dica (tooltip) ao passar o mouse vem de data-valor/data-rotulo (assets/js/painel.js).
 *
 * Carregado por layouts/admin_nav.php (todas as telas do painel).
 */

/** Número no padrão brasileiro (1.234 · 47,1). */
function gf_num(int|float|null $n, int $casas = 0): string {
    return number_format((float)$n, $casas, ',', '.');
}

/** Valor compacto para os indicadores (1.284 · 12,9 mil · 1,2 mi). */
function gf_compacto(int|float $n): string {
    if (abs($n) >= 1_000_000) return gf_num($n / 1_000_000, 1).' mi';
    if (abs($n) >= 10_000) return gf_num($n / 1000, 1).' mil';
    return gf_num($n, is_float($n) && floor($n) != $n ? 1 : 0);
}

/** Percentual de $parte em $total, com uma casa só quando precisa ("44%", "2,5%"). */
function gf_pct(int|float $parte, int|float $total): string {
    if ($total <= 0) return '0%';
    $p = $parte / $total * 100;
    return gf_num($p, $p > 0 && $p < 10 && round($p, 1) != round($p) ? 1 : 0).'%';
}

/** Singular ou plural pela quantidade: gf_plural(1, 'vaga', 'vagas') = "vaga". */
function gf_plural(int|float $n, string $um, string $varios): string {
    return (int)$n === 1 ? $um : $varios;
}

/** Escala "redonda" do eixo para contagens: [máximo, passo] com até $linhas linhas de grade. */
function gf_escala(float $max, int $linhas = 4): array {
    if ($max <= 0) return [$linhas, 1];
    $bruto = $max / $linhas;
    $pot = 10 ** floor(log10($bruto));
    $passo = $pot;
    foreach ([1, 2, 5, 10] as $m) { $passo = $m * $pot; if ($passo >= $bruto) break; }
    $passo = max(1, $passo);
    return [ceil($max / $passo) * $passo, $passo];
}

/** Atributos da dica do mouse (valor em destaque + rótulo). */
function gf_dica(string $valor, string $rotulo): string {
    return ' data-valor="'.e($valor).'" data-rotulo="'.e($rotulo).'"';
}

/**
 * Barras horizontais (ranking): nome e valor na linha de cima, barra fina embaixo.
 * A largura usa porcentagem, então o gráfico acompanha a largura do cartão (celular incluso)
 * sem encolher o texto. Barras começam na mesma linha de base, com a ponta arredondada.
 *
 * @param list<array{rotulo:string, valor:int|float, texto?:string, cor?:string, dica?:string}> $itens
 * @param array $op titulo (aria-label) · unidade [singular, plural] · max_itens (o resto vira "Outras")
 *                  · rotulo_outros · escala (valor que ocupa 100%; padrão = maior valor) · vazio (texto sem dados)
 */
function grafico_barras(array $itens, array $op = []): string {
    [$um, $varios] = $op['unidade'] ?? ['', ''];
    $max = (int)($op['max_itens'] ?? 0);
    if ($max > 0 && count($itens) > $max) {
        $resto = array_slice($itens, $max - 1);
        $itens = array_slice($itens, 0, $max - 1);
        $soma = array_sum(array_column($resto, 'valor'));
        $itens[] = ['rotulo' => ($op['rotulo_outros'] ?? 'Outras').' ('.count($resto).')', 'valor' => $soma, 'cor' => 'var(--gf-outros)'];
    }
    $total = array_sum(array_column($itens, 'valor'));
    if (!$itens || ($total <= 0 && empty($op['mostrar_zeros']))) return '<p class="gf-vazio">'.e($op['vazio'] ?? 'Sem dados para exibir.').'</p>';

    $escala = (float)($op['escala'] ?? max(array_column($itens, 'valor')));
    $passo = 38; $altura = count($itens) * $passo - 4;
    $resumo = [];
    $svg = '';
    foreach ($itens as $i => $it) {
        $v = (float)$it['valor'];
        $y = $i * $passo;
        $texto = $it['texto'] ?? gf_num($v, floor($v) != $v ? 1 : 0);
        $rotulo = (string)$it['rotulo'];
        $curto = mb_strimwidth($rotulo, 0, 34, '…');
        $larg = $escala > 0 ? max(0, min(100, $v / $escala * 100)) : 0;
        $cor = $it['cor'] ?? 'var(--gf-s1)';
        $dica = $it['dica'] ?? ($texto.($um !== '' ? ' '.gf_plural($v, $um, $varios) : ''));
        $resumo[] = $rotulo.': '.$texto;
        $svg .= '<g class="gf-item"'.gf_dica($dica, $rotulo).'>'
              .'<rect class="gf-alvo" x="0" y="'.$y.'" width="100%" height="'.$passo.'"/>'
              .'<text class="gf-rot" x="0" y="'.($y + 14).'">'.e($curto).'</text>'
              .'<text class="gf-val" x="100%" y="'.($y + 14).'" text-anchor="end">'.e($texto).'</text>';
        if ($larg > 0) {
            $svg .= '<rect class="gf-barra" x="0" y="'.($y + 21).'" width="'.round($larg, 2).'%" height="11" rx="4" style="fill:'.e($cor).'"/>';
            // Base reta: a barra só é arredondada na ponta (cobre os cantos do início).
            if ($larg >= 4) $svg .= '<rect class="gf-barra" x="0" y="'.($y + 21).'" width="5" height="11" style="fill:'.e($cor).'"/>';
        }
        $svg .= '</g>';
    }
    $titulo = ($op['titulo'] ?? 'Gráfico de barras').': '.implode('; ', $resumo).'.';
    return '<svg class="gf gf-barras" width="100%" height="'.$altura.'" role="img" aria-label="'.e($titulo).'">'.$svg.'</svg>';
}

/**
 * Linha com área (série diária): uma série, linha de 2px, área clara, grade discreta,
 * valor no último ponto e no pico. Passando o mouse, uma guia vertical marca o dia.
 * O traçado usa viewBox esticado na largura; pontos e textos usam porcentagem (não distorcem).
 *
 * @param array<string,int|float> $serie 'Y-m-d' => valor, em ordem
 * @param array $op titulo · unidade [singular, plural] · vazio
 */
function grafico_linha(array $serie, array $op = []): string {
    [$um, $varios] = $op['unidade'] ?? ['', ''];
    $n = count($serie);
    if ($n < 2) return '<p class="gf-vazio">'.e($op['vazio'] ?? 'Sem dados para exibir.').'</p>';
    $valores = array_values(array_map('floatval', $serie));
    $dias = array_keys($serie);
    [$topo, $passoY] = gf_escala(max($valores));

    $alt = 200; $yTopo = 24; $yBase = 168; $margem = 2.0; // margem lateral em %, para os pontos das pontas
    $xPct = fn(int $i) => $margem + $i / ($n - 1) * (100 - 2 * $margem);
    $yPx = fn(float $v) => $yBase - ($topo > 0 ? $v / $topo : 0) * ($yBase - $yTopo);
    $fmtDia = fn(string $d) => date('d/m', strtotime($d));
    $rotValor = fn(float $v) => gf_num($v).($um !== '' ? ' '.gf_plural($v, $um, $varios) : '');

    $svg = '';
    // Linhas de grade (a do zero é a linha de base) com o valor em cima, à esquerda.
    for ($g = 0; $g <= $topo + 0.0001; $g += $passoY) {
        $y = round($yPx($g), 1);
        $svg .= '<line class="'.($g == 0 ? 'gf-base' : 'gf-grade').'" x1="0" x2="100%" y1="'.$y.'" y2="'.$y.'"/>';
        if ($g > 0) $svg .= '<text class="gf-eixo" x="0" y="'.($y - 4).'">'.gf_num($g).'</text>';
    }
    // Área e linha (coordenadas 0–1000 esticadas na largura; o traço não engrossa).
    $pts = [];
    foreach ($valores as $i => $v) $pts[] = round($xPct($i) * 10, 2).','.round($yPx($v), 2);
    $svg .= '<svg x="0" y="0" width="100%" height="'.$alt.'" viewBox="0 0 1000 '.$alt.'" preserveAspectRatio="none" aria-hidden="true">'
          .'<path class="gf-area" d="M'.round($xPct(0) * 10, 2).','.$yBase.' L'.implode(' L', $pts).' L'.round($xPct($n - 1) * 10, 2).','.$yBase.' Z"/>'
          .'<path class="gf-linha" d="M'.implode(' L', $pts).'"/></svg>';
    // Datas do eixo X: primeira, a cada 7 dias e a última.
    foreach ($dias as $i => $d) {
        if ($i % 7 !== 0 && $i !== $n - 1) continue;
        if ($i !== $n - 1 && $n - 1 - $i < 4) continue; // não encosta na última
        $ancora = $i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle');
        $svg .= '<text class="gf-eixo" x="'.round($xPct($i), 2).'%" y="'.($alt - 6).'" text-anchor="'.$ancora.'">'.$fmtDia($d).'</text>';
    }
    // Pontos com guia vertical e dica (alvo = faixa inteira do dia, maior que a marca).
    $larguraFaixa = (100 - 2 * $margem) / ($n - 1);
    foreach ($valores as $i => $v) {
        $x = round($xPct($i), 2);
        $svg .= '<g class="gf-ponto"'.gf_dica($rotValor($v), date('d/m/Y', strtotime($dias[$i]))).'>'
              .'<rect class="gf-alvo" x="'.round(max(0, $x - $larguraFaixa / 2), 2).'%" y="0" width="'.round($larguraFaixa, 2).'%" height="'.$yBase.'"/>'
              .'<line class="gf-guia" x1="'.$x.'%" x2="'.$x.'%" y1="'.$yTopo.'" y2="'.$yBase.'"/>'
              .'<circle class="gf-marca" cx="'.$x.'%" cy="'.round($yPx($v), 1).'" r="4"/></g>';
    }
    // Rótulos seletivos: último ponto (sempre) e o pico, se for outro dia.
    $ult = $n - 1;
    $pico = array_search(max($valores), $valores, true);
    $svg .= '<circle class="gf-fim" cx="'.round($xPct($ult), 2).'%" cy="'.round($yPx($valores[$ult]), 1).'" r="4"/>'
          .'<text class="gf-val" x="'.round($xPct($ult), 2).'%" dx="-8" y="'.round($yPx($valores[$ult]) - 10, 1).'" text-anchor="end">'.gf_num($valores[$ult]).'</text>';
    if ($pico !== $ult && $ult - $pico >= 3 && $valores[$pico] > 0) {
        $svg .= '<circle class="gf-fim" cx="'.round($xPct($pico), 2).'%" cy="'.round($yPx($valores[$pico]), 1).'" r="4"/>'
              .'<text class="gf-val" x="'.round($xPct($pico), 2).'%" y="'.round($yPx($valores[$pico]) - 10, 1).'" text-anchor="middle">'.gf_num($valores[$pico]).'</text>';
    }
    if (array_sum($valores) <= 0) $svg .= '<text class="gf-aviso" x="50%" y="'.($yBase / 2 + 10).'" text-anchor="middle">'.e($op['vazio'] ?? 'Nenhum registro no período').'</text>';

    $total = array_sum($valores);
    $titulo = ($op['titulo'] ?? 'Gráfico de linha').': '.$rotValor($total).' de '.date('d/m', strtotime($dias[0])).' a '.date('d/m', strtotime($dias[$ult]))
            .'; pico de '.gf_num($valores[$pico]).' em '.date('d/m', strtotime($dias[$pico])).'.';
    return '<svg class="gf gf-linhas" width="100%" height="'.$alt.'" role="img" aria-label="'.e($titulo).'">'.$svg.'</svg>';
}

/**
 * Rosca (parte do todo, até 6 fatias) com o total no centro e a legenda com valor e percentual.
 * As fatias são separadas por um vão de 2px da cor do fundo (sem contorno).
 *
 * @param list<array{rotulo:string, valor:int|float, cor:string}> $itens cor fixa por categoria (não pela posição)
 * @param array $op titulo · centro (texto sob o total) · unidade [singular, plural]
 */
function grafico_rosca(array $itens, array $op = []): string {
    [$um, $varios] = $op['unidade'] ?? ['', ''];
    $total = array_sum(array_column($itens, 'valor'));
    if ($total <= 0) return '<p class="gf-vazio">'.e($op['vazio'] ?? 'Sem dados para exibir.').'</p>';
    $r = 48; $circ = 2 * M_PI * $r; $vao = count(array_filter($itens, fn($i) => $i['valor'] > 0)) > 1 ? 1.8 : 0;
    $svg = ''; $inicio = 0.0; $resumo = []; $legenda = '';
    foreach ($itens as $it) {
        $v = (float)$it['valor'];
        $pct = gf_pct($v, $total);
        $resumo[] = $it['rotulo'].': '.gf_num($v).' ('.$pct.')';
        $legenda .= '<li><i style="background:'.e($it['cor']).'"></i><span>'.e($it['rotulo']).'</span><b>'.gf_num($v).'</b><small>'.$pct.'</small></li>';
        if ($v <= 0) continue;
        $comp = $v / $total * $circ;
        $svg .= '<circle class="gf-fatia"'.gf_dica(gf_num($v).($um !== '' ? ' '.gf_plural($v, $um, $varios) : '').' · '.$pct, (string)$it['rotulo'])
              .' cx="60" cy="60" r="'.$r.'" style="stroke:'.e($it['cor']).'" stroke-dasharray="'.round(max(0.5, $comp - $vao), 2).' '.round($circ, 2).'"'
              .' stroke-dashoffset="'.round(-$inicio, 2).'" transform="rotate(-90 60 60)"/>';
        $inicio += $comp;
    }
    $titulo = ($op['titulo'] ?? 'Gráfico de rosca').': '.implode('; ', $resumo).'.';
    return '<div class="gf-rosca"><svg class="gf" viewBox="0 0 120 120" width="136" height="136" role="img" aria-label="'.e($titulo).'">'.$svg
         .'<text class="gf-centro" x="60" y="60" text-anchor="middle">'.gf_compacto($total).'</text>'
         .'<text class="gf-centro-sub" x="60" y="76" text-anchor="middle">'.e($op['centro'] ?? 'total').'</text></svg>'
         .'<ul class="gf-legenda">'.$legenda.'</ul></div>';
}

/**
 * Tabela com os dados do gráfico (alternativa acessível e para quem prefere os números), recolhida em "Ver dados".
 * @param list<string> $colunas cabeçalhos; a partir da 2ª coluna o texto fica alinhado à direita
 * @param list<list<string>> $linhas células já formatadas (texto puro; escapado aqui)
 */
function grafico_tabela(array $colunas, array $linhas, string $resumo = 'Ver dados'): string {
    if (!$linhas) return '';
    $h = '<details class="gf-dados"><summary>'.e($resumo).'</summary><div class="gf-dados-rolagem"><table><thead><tr>';
    foreach ($colunas as $c) $h .= '<th scope="col">'.e($c).'</th>';
    $h .= '</tr></thead><tbody>';
    foreach ($linhas as $l) {
        $h .= '<tr>';
        foreach (array_values($l) as $i => $c) $h .= $i === 0 ? '<th scope="row">'.e((string)$c).'</th>' : '<td>'.e((string)$c).'</td>';
        $h .= '</tr>';
    }
    return $h.'</tbody></table></div></details>';
}

/**
 * Cartão de indicador (KPI): rótulo, valor grande e uma linha de contexto.
 * Com $link, o cartão inteiro leva à tela com o detalhe (ex.: candidaturas aguardando análise).
 */
function painel_kpi(string $rotulo, string $valor, string $detalhe = '', string $icone = '', string $link = ''): string {
    $tag = $link !== '' ? 'a' : 'div';
    return '<'.$tag.' class="pn-kpi"'.($link !== '' ? ' href="'.e(url($link)).'"' : '').'>'
         .($icone !== '' ? '<span class="pn-kpi-ic" aria-hidden="true">'.icone($icone, 18).'</span>' : '')
         .'<span class="pn-kpi-rot">'.e($rotulo).'</span>'
         .'<span class="pn-kpi-val">'.e($valor).'</span>'
         .($detalhe !== '' ? '<span class="pn-kpi-det">'.e($detalhe).'</span>' : '')
         .'</'.$tag.'>';
}

/** Selo de status (candidatura, vaga ou conta) com texto — a cor nunca aparece sozinha. */
function painel_status(string $status, string $texto = ''): string {
    return '<span class="pn-st pn-st-'.e($status).'">'.e($texto !== '' ? $texto : rotulo($status)).'</span>';
}

/** Selo da nota de match (mesmas faixas do MatchService); '—' quando não há match calculado. */
function painel_match(mixed $pontuacao, ?string $nivel): string {
    if ($pontuacao === null || $pontuacao === '') return '<span class="muted">—</span>';
    return '<span class="score-badge '.e((string)$nivel).'">'.gf_num((float)$pontuacao).'%</span>';
}

/**
 * Cabeçalho das telas do painel: área (administrador/empresa), título, descrição e ações à direita.
 * $acoes é HTML montado pela própria view (botões e selos).
 */
function painel_cabecalho(string $titulo, string $descricao = '', string $acoes = ''): string {
    return '<header class="pn-cab"><div class="pn-cab-txt"><p class="pn-area">'.(isAdmin() ? 'Painel administrativo' : 'Painel da empresa').'</p>'
         .'<h1>'.e($titulo).'</h1>'.($descricao !== '' ? '<p class="pn-desc">'.e($descricao).'</p>' : '').'</div>'
         .($acoes !== '' ? '<div class="pn-cab-acoes">'.$acoes.'</div>' : '').'</header>';
}

/** Cabeçalho de coluna ordenável: clicar ordena por ela; clicar de novo inverte. aria-sort para leitor de tela. */
function painel_th(string $campo, string $rotulo, string $ordem, string $dir, string $classe = ''): string {
    $ativo = $ordem === $campo;
    $novoDir = $ativo && $dir === 'asc' ? 'desc' : 'asc';
    $seta = $ativo ? ($dir === 'asc' ? '▲' : '▼') : '↕';
    return '<th'.($classe !== '' ? ' class="'.e($classe).'"' : '').($ativo ? ' aria-sort="'.($dir === 'asc' ? 'ascending' : 'descending').'"' : '').'>'
         .'<a class="pn-ordem'.($ativo ? ' ativo' : '').'" href="'.e(painel_qs(['ordem' => $campo, 'dir' => $novoDir, 'pagina' => ''])).'">'
         .e($rotulo).' <span class="pn-ordem-seta" aria-hidden="true">'.$seta.'</span>'
         .'<span class="sr-only">(ordenar '.($novoDir === 'asc' ? 'crescente' : 'decrescente').')</span></a></th>';
}

/** Paginação das tabelas do painel (mantém filtros e ordenação). */
function painel_paginacao(int $pagina, int $paginas): string {
    return cv_paginacao($pagina, $paginas, fn(int $n) => painel_qs(['pagina' => $n]), 'Páginas da tabela', 'pn-paginacao');
}

/** Abas internas de uma tela (ex.: Cursos · E-books · Vídeos): [valor => [rótulo, contagem]]. */
function painel_subabas(string $param, string $atual, array $abas, string $rotulo): string {
    $h = '<nav class="pn-subabas" aria-label="'.e($rotulo).'">';
    foreach ($abas as $valor => [$texto, $n]) {
        $ativo = (string)$valor === $atual;
        $h .= '<a href="'.e(painel_qs([$param => (string)$valor, 'pagina' => ''])).'"'.($ativo ? ' class="ativo" aria-current="page"' : '').'>'.e($texto).' <span class="pn-subabas-n">'.gf_num((int)$n).'</span></a>';
    }
    return $h.'</nav>';
}

/**
 * Chave liga/desliga (estilo "switch" do Bootstrap) para a situação de um registro: Aberta/Pausada,
 * Publicado/Oculto, Ativo/Bloqueado. É um botão de formulário POST com o token CSRF (role="switch"): clicar envia
 * a ação oposta ao estado atual e a lista volta com os mesmos filtros. $confirmarDesligar abre a confirmação do app.js.
 */
function painel_chave(bool $ligado, string $acaoLigar, string $acaoDesligar, int $id, string $rotuloLigado, string $rotuloDesligado,
                      string $confirmarDesligar = '', string $nome = ''): string {
    $acao = $ligado ? $acaoDesligar : $acaoLigar;
    $dica = 'Clique para mudar para "'.($ligado ? $rotuloDesligado : $rotuloLigado).'"';
    return '<form method="post" class="pn-chave-form"><input type="hidden" name="csrf" value="'.e(csrf_token()).'">'
         .'<input type="hidden" name="acao" value="'.e($acao).'"><input type="hidden" name="id" value="'.$id.'">'
         .'<button type="submit" class="pn-chave'.($ligado ? ' ligada' : '').'" role="switch" aria-checked="'.($ligado ? 'true' : 'false').'" title="'.e($dica).'"'
         .($ligado && $confirmarDesligar !== '' ? ' data-confirm="'.e($confirmarDesligar).'"' : '').'>'
         .'<span class="pn-chave-trilho" aria-hidden="true"><span class="pn-chave-bolinha"></span></span>'
         .'<span class="pn-chave-txt">'.e($ligado ? $rotuloLigado : $rotuloDesligado).'</span>'
         .($nome !== '' ? '<span class="sr-only"> — '.e($nome).'</span>' : '').'</button></form>';
}

/**
 * Barra de botões do CRUD numa linha só (estilo "btn-group" do Bootstrap): ícone + rótulo; em telas menores fica só
 * o ícone (o rótulo aparece ao passar o mouse e é lido pelo leitor de tela). Cada item:
 *  - link:  ['href' => url, 'texto' => 'Ver', 'icone' => 'olho', 'estilo' => 'primario', 'nova_aba' => true]
 *  - ação:  ['acao' => 'cancelar', 'id' => 5, 'texto' => 'Cancelar vaga', 'icone' => 'fechar', 'estilo' => 'perigo', 'confirmar' => '...']
 * Estilos: primario, neutro, sucesso, alerta, perigo. 'so_icone' => true mostra só o ícone (o nome fica na dica e no
 * leitor de tela). $nome entra no texto do leitor de tela ("Editar Atendente").
 */
function painel_botoes(array $itens, string $nome = ''): string {
    $h = '<div class="pn-bts" role="group" aria-label="Ações'.($nome !== '' ? ' de '.e($nome) : '').'">';
    foreach ($itens as $b) {
        if (!$b) continue;
        $classe = 'pn-bt pn-bt-'.e($b['estilo'] ?? 'neutro').(!empty($b['so_icone']) ? ' pn-bt-icone' : '');
        $miolo = icone($b['icone'] ?? 'seta', 15).'<span class="'.(!empty($b['so_icone']) ? 'sr-only' : 'pn-bt-txt').'">'.e($b['texto']).'</span>'
               .($nome !== '' ? '<span class="sr-only"> '.e($nome).'</span>' : '').(!empty($b['nova_aba']) ? '<span class="sr-only"> (abre em nova aba)</span>' : '');
        if (isset($b['href'])) {
            $h .= '<a class="'.$classe.'" href="'.e($b['href']).'" title="'.e($b['texto']).'"'.(!empty($b['nova_aba']) ? ' target="_blank" rel="noopener"' : '').'>'.$miolo.'</a>';
        } else {
            $h .= '<form method="post" class="pn-bt-form"><input type="hidden" name="csrf" value="'.e(csrf_token()).'">'
                .'<input type="hidden" name="acao" value="'.e($b['acao']).'"><input type="hidden" name="id" value="'.(int)$b['id'].'">'
                .'<button type="submit" class="'.$classe.'" title="'.e($b['texto']).'"'.(($b['confirmar'] ?? '') !== '' ? ' data-confirm="'.e($b['confirmar']).'"' : '').'>'.$miolo.'</button></form>';
        }
    }
    return $h.'</div>';
}

/** Miniatura da imagem do registro (vaga, curso, e-book) na lista do painel; sem imagem, o ícone do tipo. */
function painel_miniatura(?string $img, string $classe = '', string $icone = 'vagas'): string {
    $img = trim((string)$img);
    if ($img === '') return '<span class="pn-miniatura pn-miniatura-vazia '.e($classe).'" aria-hidden="true">'.icone($icone, 20).'</span>';
    $src = preg_match('#^https?://#i', $img) ? $img : url($img);
    return '<img class="pn-miniatura '.e($classe).'" src="'.e($src).'" alt="" loading="lazy">';
}
