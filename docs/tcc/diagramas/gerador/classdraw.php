<?php
// Modelo lógico (entidade-relacionamento) no estilo do Astah: tabelas em colunas, ligações em ângulo reto,
// notação pé de galinha. As tabelas e colunas são lidas do logico.puml (fonte única).
// Uso: php erdraw.php logico.puml saida.svg
declare(strict_types=1);
const FONTE = 'C:/Windows/Fonts/arial.ttf'; const FONTEB = 'C:/Windows/Fonts/arialbd.ttf';
const FS = 11; const LH = 15;
function larg(string $t, bool $b = false): float { $x = imagettfbbox(FS * 0.75, 0, $b ? FONTEB : FONTE, $t); return abs($x[2] - $x[0]) / 0.75; }
function esc(string $t): string { return htmlspecialchars($t, ENT_QUOTES | ENT_XML1, 'UTF-8'); }
function txt(float $x, float $y, string $t, string $anc = 'start', string $extra = ''): string {
    return '<text x="'.round($x, 1).'" y="'.round($y, 1).'" font-family="Arial, Helvetica, sans-serif" font-size="'.FS.'" text-anchor="'.$anc.'"'.$extra.'>'.esc($t).'</text>';
}

/** "FK usuario_id : INT(11) NN UQ" vira "-usuarioId : int" */
function attr(string $l): string {
    $l = preg_replace('/^(PK|FK)\s+/', '', $l);
    [$n, $t] = array_map('trim', explode(':', $l, 2) + [1 => '']);
    $n = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $n))));
    $t = strtoupper(preg_replace('/[ (].*/', '', $t));
    $tipo = match (true) { str_starts_with($t, 'TINYINT') => 'boolean', str_starts_with($t, 'INT') => 'int', $t === 'DECIMAL' => 'decimal',
        $t === 'DATE' => 'Date', $t === 'DATETIME' => 'DateTime', default => 'String' };
    return '-'.$n.' : '.$tipo;
}
// ---- tabelas a partir do logico.puml
$puml = file_get_contents($argv[1]);
preg_match_all('/entity (\w+) \{(.*?)\n\}/s', $puml, $m, PREG_SET_ORDER);
$tab = [];
foreach ($m as [, $nome, $corpo]) {
    $pk = []; $cols = [];
    $antes = true;
    foreach (preg_split('/\R/', trim($corpo)) as $l) {
        $l = trim(strip_tags($l));
        if ($l === '') continue;
        if ($l === '--') { $antes = false; continue; }
        if ($antes) $pk[] = $l; else $cols[] = $l;
    }
    $tab[$nome] = ['pk' => [], 'cols' => array_map('attr', array_merge($pk, $cols))];
}

$classe = ['usuarios' => 'Usuario', 'perfis' => 'Perfil', 'categorias' => 'Categoria', 'vagas' => 'Vaga', 'cursos' => 'Curso',
    'curriculos' => 'Curriculo', 'candidaturas' => 'Candidatura', 'matches' => 'Match', 'assinaturas' => 'Assinatura',
    'tentativas_login' => 'TentativaLogin', 'redefinicoes_senha' => 'RedefinicaoSenha'];
$nomeAssoc = ['usuarios-perfis' => 'possui', 'usuarios-assinaturas' => 'contrata', 'usuarios-redefinicoes_senha' => 'solicita',
    'perfis-curriculos' => 'envia', 'perfis-vagas' => 'publica', 'perfis-candidaturas' => 'realiza', 'perfis-matches' => 'recebe',
    'vagas-candidaturas' => 'recebe', 'vagas-matches' => 'gera', 'curriculos-candidaturas' => 'anexado em',
    'categorias-vagas' => 'classifica', 'categorias-cursos' => 'classifica'];
// ---- disposição: colunas da esquerda para a direita, tabelas empilhadas
$colunas = [
    40  => ['cursos', 'categorias', 'vagas', 'matches'],
    420 => ['usuarios', 'perfis', 'candidaturas'],
    830 => ['tentativas_login', 'assinaturas', 'redefinicoes_senha', 'curriculos'],
];
$y0 = ['cursos' => 50, 'usuarios' => 50, 'tentativas_login' => 50];
$espaco = ['categorias' => 50, 'vagas' => 70, 'matches' => 60, 'perfis' => 70, 'candidaturas' => 60, 'assinaturas' => 70, 'redefinicoes_senha' => 60, 'curriculos' => 210];
$box = [];
foreach ($colunas as $x => $lista) {
    $y = null;
    foreach ($lista as $n) {
        $t = $tab[$n];
        $w = max(larg($classe[$n], true) + 30, ...array_map(fn($l) => larg($l) + 16, [...$t['pk'], ...$t['cols']]));
        $h = 22 + 6 + count($t['cols']) * LH + 4 + 14;
        $y = $y === null ? ($y0[$n] ?? 50) : $y + ($espaco[$n] ?? 60);
        $box[$n] = ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
        $y += $h;
    }
}

// ---- relacionamentos: [pai, filho, lado no pai, lado no filho, fração ao longo do lado (pai), fração (filho), card. pai, card. filho]
// lados: t (topo), b (base), l (esquerda), r (direita). cardinalidades: '1' (um e somente um), '01' (zero ou um), 'N' (zero ou muitos), '0..1' no filho
$rel = [
    ['usuarios', 'perfis', 'b', 't', .5, .5, '1', '01'],
    ['usuarios', 'assinaturas', 'r', 'l', .55, .3, '1', 'N'],
    ['usuarios', 'redefinicoes_senha', 'r', 'l', .85, .3, '1', 'N', 30],
    ['perfis', 'curriculos', 'r', 'l', .9, .3, '1', 'N'],
    ['perfis', 'vagas', 'l', 'r', .45, .15, '1', 'N'],
    ['perfis', 'candidaturas', 'b', 't', .5, .5, '1', 'N'],
    ['perfis', 'matches', 'l', 'r', .92, .25, '1', 'N', 55],
    ['vagas', 'candidaturas', 'r', 'l', .92, .3, '1', 'N'],
    ['vagas', 'matches', 'b', 't', .5, .5, '1', 'N'],
    ['curriculos', 'candidaturas', 'l', 'r', .75, .6, '01', 'N'],
    ['categorias', 'vagas', 'b', 't', .5, .5, '01', 'N'],
    ['categorias', 'cursos', 't', 'b', .5, .5, '01', 'N'],
];
function ponto(array $b, string $lado, float $f): array {
    return match ($lado) {
        't' => [$b['x'] + $b['w'] * $f, $b['y']], 'b' => [$b['x'] + $b['w'] * $f, $b['y'] + $b['h']],
        'l' => [$b['x'], $b['y'] + $b['h'] * $f], default => [$b['x'] + $b['w'], $b['y'] + $b['h'] * $f],
    };
}
/** Símbolo pé de galinha na ponta (x,y), saindo na direção (dx,dy) para dentro da linha. */
function card(float $x, float $y, float $dx, float $dy, string $c): string {
    $rot = ['1' => '1', '01' => '0..1', 'N' => '0..*'][$c] ?? $c;
    // texto ao lado da linha, um pouco afastado da caixa
    $tx = $x + $dx * 14 + ($dx == 0 ? 6 : 0); $ty = $y + $dy * 14 + ($dy == 0 ? -5 : 4);
    return txt($tx, $ty, $rot, $dx < 0 ? 'end' : 'start');
    $px = -$dy; $py = $dx;   // perpendicular
    $s = ''; $ln = fn($x1, $y1, $x2, $y2) => '<line x1="'.round($x1, 1).'" y1="'.round($y1, 1).'" x2="'.round($x2, 1).'" y2="'.round($y2, 1).'" stroke="#000" stroke-width="1"/>';
    $barra = fn(float $d) => $ln($x + $dx * $d + $px * 6, $y + $dy * $d + $py * 6, $x + $dx * $d - $px * 6, $y + $dy * $d - $py * 6);
    $circ = fn(float $d) => '<circle cx="'.round($x + $dx * $d, 1).'" cy="'.round($y + $dy * $d, 1).'" r="4" fill="#fff" stroke="#000" stroke-width="1"/>';
    if ($c === '1') $s .= $barra(6).$barra(11);
    elseif ($c === '01') $s .= $barra(6).$circ(14);
    else { // N: pé de galinha + círculo (zero ou muitos)
        $s .= $ln($x + $px * 7, $y + $py * 7, $x + $dx * 10, $y + $dy * 10).$ln($x - $px * 7, $y - $py * 7, $x + $dx * 10, $y + $dy * 10).$ln($x, $y, $x + $dx * 10, $y + $dy * 10).$circ(15);
    }
    return $s;
}
$dir = ['t' => [0, -1], 'b' => [0, 1], 'l' => [-1, 0], 'r' => [1, 0]];

$W = max(array_map(fn($b) => $b['x'] + $b['w'], $box)) + 40; $H = max(array_map(fn($b) => $b['y'] + $b['h'], $box)) + 40;
$s = '<svg xmlns="http://www.w3.org/2000/svg" width="'.round($W).'" height="'.round($H).'" viewBox="0 0 '.round($W).' '.round($H).'"><rect width="100%" height="100%" fill="#fff"/>';
$tabTit = 'class Diagrama de Classes de Domínio'; $tw = larg($tabTit) + 18;
$s .= '<rect x="4" y="4" width="'.round($W - 8).'" height="'.round($H - 8).'" fill="none" stroke="#000" stroke-width="1"/>'
    .'<polygon points="4,4 '.round(4 + $tw).',4 '.round(4 + $tw).',16 '.round(4 + $tw - 8).',24 4,24" fill="#fff" stroke="#000" stroke-width="1"/>'.txt(10, 18, $tabTit);
// ligações (antes das tabelas)
foreach ($rel as $r) {
    [$p, $f, $lp, $lf, $fp, $ff, $cp, $cf] = $r; $desvio = $r[8] ?? 0;   // desvio: afasta o trecho do meio para não encostar em outra ligação
    [$x1, $y1] = ponto($box[$p], $lp, $fp); [$x2, $y2] = ponto($box[$f], $lf, $ff);
    if (in_array($lp, ['l', 'r'], true) && in_array($lf, ['l', 'r'], true)) { $mx = ($x1 + $x2) / 2 + $desvio; $pts = [[$x1, $y1], [$mx, $y1], [$mx, $y2], [$x2, $y2]]; }
    elseif (in_array($lp, ['t', 'b'], true) && in_array($lf, ['t', 'b'], true)) { $my = ($y1 + $y2) / 2; $pts = [[$x1, $y1], [$x1, $my], [$x2, $my], [$x2, $y2]]; }
    else $pts = [[$x1, $y1], in_array($lp, ['l', 'r'], true) ? [$x2, $y1] : [$x1, $y2], [$x2, $y2]];
    $s .= '<polyline points="'.implode(' ', array_map(fn($q) => round($q[0], 1).','.round($q[1], 1), $pts)).'" fill="none" stroke="#000" stroke-width="1"/>';
    // rótulo no trecho mais longo da ligação
    $k = 0; $maior = -1; for ($i = 0; $i < count($pts) - 1; $i++) { $c = abs($pts[$i + 1][0] - $pts[$i][0]) + abs($pts[$i + 1][1] - $pts[$i][1]); if ($c > $maior) { $maior = $c; $k = $i; } }
    $meio = $pts[$k]; $meio2 = $pts[$k + 1];
    $nm = $nomeAssoc[$p.'-'.$f] ?? '';
    // Nome da associação: à esquerda da linha (trecho vertical) ou abaixo dela (trecho horizontal);
    // as multiplicidades ficam à direita ou acima, então um texto nunca cobre o outro.
    if ($nm !== '') {
        $lx = ($meio[0] + $meio2[0]) / 2; $ly = ($meio[1] + $meio2[1]) / 2;
        $s .= abs($meio[0] - $meio2[0]) < 1 ? txt($lx - 6, $ly + 4, $nm, 'end', ' font-style="italic"') : txt($lx, $ly + 14, $nm, 'middle', ' font-style="italic"');
    }
    $s .= card($x1, $y1, ...[...$dir[$lp], $cp]).card($x2, $y2, ...[...$dir[$lf], $cf]);
}
// tabelas
foreach ($box as $n => $b) {
    $t = $tab[$n];
    $s .= '<rect x="'.round($b['x']).'" y="'.round($b['y']).'" width="'.round($b['w']).'" height="'.round($b['h']).'" fill="#FFFFE0" stroke="#000" stroke-width="1"/>'
        .txt($b['x'] + $b['w'] / 2, $b['y'] + 15, $classe[$n], 'middle', ' font-weight="bold"')
        .'<line x1="'.round($b['x']).'" y1="'.round($b['y'] + 22).'" x2="'.round($b['x'] + $b['w']).'" y2="'.round($b['y'] + 22).'" stroke="#000" stroke-width="1"/>'
        .'<line x1="'.round($b['x']).'" y1="'.round($b['y'] + $b['h'] - 14).'" x2="'.round($b['x'] + $b['w']).'" y2="'.round($b['y'] + $b['h'] - 14).'" stroke="#000" stroke-width="1"/>';
    $y = $b['y'] + 22 + 13;
    foreach ($t['pk'] as $l) { $s .= txt($b['x'] + 8, $y, $l); $y += LH; }
    $y += 1;
    foreach ($t['cols'] as $l) { $s .= txt($b['x'] + 8, $y, $l, 'start', str_starts_with($l, 'FK ') ? ' font-style="italic"' : ''); $y += LH; }
}
file_put_contents($argv[2], $s.'</svg>');
echo "ok ".round($W)."x".round($H)."\n";
