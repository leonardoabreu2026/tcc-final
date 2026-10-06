<?php
// Desenha diagramas de caso de uso em SVG, com notação UML e layout inspirado no Astah:
// atores nas laterais, casos de uso em colunas, linhas RETAS, include/extend tracejados com seta aberta.
// Uso: php ucdraw.php specs.php pasta_saida
declare(strict_types=1);
const FONTE = 'C:/Windows/Fonts/arial.ttf';
const FS = 11;          // tamanho da fonte (px)
const LH = 13;          // altura da linha de texto

function larg(string $t): float { $b = imagettfbbox(FS * 0.75, 0, FONTE, $t); return abs($b[2] - $b[0]) * (1 / 0.75); }
function esc(string $t): string { return htmlspecialchars($t, ENT_QUOTES | ENT_XML1, 'UTF-8'); }
function texto(float $x, float $y, array $linhas, string $anchor = 'middle', string $extra = ''): string {
    $s = '';
    $y0 = $y - (count($linhas) - 1) * LH / 2 + 4;
    foreach ($linhas as $i => $l) $s .= '<text x="'.round($x, 1).'" y="'.round($y0 + $i * LH, 1).'" font-family="Arial, Helvetica, sans-serif" font-size="'.FS.'" text-anchor="'.$anchor.'"'.$extra.'>'.esc($l).'</text>';
    return $s;
}
/** Ponto em que o segmento (x0,y0)->(cx,cy) entra na elipse de centro (cx,cy) e raios rx,ry. */
function bordaElipse(float $x0, float $y0, float $cx, float $cy, float $rx, float $ry): array {
    $dx = $x0 - $cx; $dy = $y0 - $cy;
    $k = 1 / sqrt(($dx * $dx) / ($rx * $rx) + ($dy * $dy) / ($ry * $ry));
    return [$cx + $dx * $k, $cy + $dy * $k];
}
function ator(float $x, float $y, string $nome): string {
    // y = altura dos ombros (ponto onde chegam as linhas)
    return '<g stroke="#000" stroke-width="1" fill="none">'
        .'<circle cx="'.$x.'" cy="'.($y - 16).'" r="8" fill="#fff"/>'
        .'<line x1="'.$x.'" y1="'.($y - 8).'" x2="'.$x.'" y2="'.($y + 14).'"/>'
        .'<line x1="'.($x - 13).'" y1="'.($y - 1).'" x2="'.($x + 13).'" y2="'.($y - 1).'"/>'
        .'<line x1="'.$x.'" y1="'.($y + 14).'" x2="'.($x - 10).'" y2="'.($y + 32).'"/>'
        .'<line x1="'.$x.'" y1="'.($y + 14).'" x2="'.($x + 10).'" y2="'.($y + 32).'"/></g>'
        .texto($x, $y + 46, [$nome]);
}
function seta(float $x1, float $y1, float $x2, float $y2): string {
    $a = atan2($y2 - $y1, $x2 - $x1); $L = 9; $w = 0.42;
    $p1 = [$x2 - $L * cos($a - $w), $y2 - $L * sin($a - $w)]; $p2 = [$x2 - $L * cos($a + $w), $y2 - $L * sin($a + $w)];
    return '<polyline points="'.round($p1[0], 1).','.round($p1[1], 1).' '.round($x2, 1).','.round($y2, 1).' '.round($p2[0], 1).','.round($p2[1], 1).'" fill="none" stroke="#000" stroke-width="1"/>';
}

function desenhar(array $d, bool $moldura = true): string {
    // colunas: x da BORDA de cada coluna — esquerda (padrão) ou direita (colunas listadas em 'direita').
    // Com as elipses alinhadas pela borda e a linha chegando no ponto da borda virado para o ator,
    // nenhuma linha passa por dentro de outra elipse da mesma coluna.
    $colX = $d['colunas'];
    $colDir = $d['direita'] ?? [];
    $topo = $d['topo'] ?? 110; $passo = $d['passo'] ?? 62;
    $alturaCabecalho = isset($d['rotulosColunas']) ? 42 : 0;
    $uc = [];
    foreach ($d['casos'] as $id => [$rotulo, $col, $lin]) {
        $linhas = explode("\n", $rotulo);
        $w = max(array_map('larg', $linhas));
        $rx = max(62, $w / 2 + 20); $ry = max(19, (count($linhas) * LH) / 2 + 10);
        $cx = in_array($col, $colDir, true) ? $colX[$col] - $rx : $colX[$col] + $rx;
        $uc[$id] = ['l' => $linhas, 'x' => $cx, 'y' => $topo + $lin * $passo, 'rx' => $rx, 'ry' => $ry, 'col' => $col];
    }
    // Atores: posição y = média dos casos ligados a ele (ou y fixo)
    $atores = [];
    foreach ($d['atores'] as $nome => $cfg) {
        $lig = array_filter($d['ligacoes'], fn($l) => $l[0] === $nome);
        $ys = array_map(fn($l) => $uc[$l[1]]['y'], $lig);
        $y = $cfg['y'] ?? ($ys ? array_sum($ys) / count($ys) : $topo);
        $atores[$nome] = ['x' => $cfg['x'], 'y' => $y];
    }
    $minY = min(array_map(fn($u) => $u['y'] - $u['ry'], $uc)); $maxY = max(array_map(fn($u) => $u['y'] + $u['ry'], $uc));
    $minX = min(array_map(fn($u) => $u['x'] - $u['rx'], $uc)); $maxX = max(array_map(fn($u) => $u['x'] + $u['rx'], $uc));
    $bx1 = $minX - 30; $bx2 = $maxX + 30; $by1 = $minY - 40 - $alturaCabecalho; $by2 = $maxY + 25;   // a fronteira envolve só os casos de uso
    $W = max(array_map(fn($a) => $a['x'], $atores) + [$bx2]) + 70; $H = $by2 + 30;
    foreach ($atores as $a) { $W = max($W, $a['x'] + 60); $H = max($H, $a['y'] + 70); }

    $s = '<svg xmlns="http://www.w3.org/2000/svg" width="'.round($W).'" height="'.round($H).'" viewBox="0 0 '.round($W).' '.round($H).'">'
        .'<rect x="0" y="0" width="'.round($W).'" height="'.round($H).'" fill="#fff"/>';
    if (!$moldura) goto semMoldura;
    // moldura do diagrama com a aba "uc Nome"
    $tab = 'uc '.$d['nome']; $tw = larg($tab) + 18;
    $s .= '<rect x="4" y="4" width="'.round($W - 8).'" height="'.round($H - 8).'" fill="none" stroke="#000" stroke-width="1"/>'
        .'<polygon points="4,4 '.round(4 + $tw).',4 '.round(4 + $tw).',16 '.round(4 + $tw - 8).',24 4,24" fill="#fff" stroke="#000" stroke-width="1"/>'
        .texto(10, 14, [$tab], 'start');
    semMoldura:
    // fronteira do sistema
    $s .= '<rect x="'.round($bx1).'" y="'.round($by1).'" width="'.round($bx2 - $bx1).'" height="'.round($by2 - $by1).'" fill="#fff" stroke="#000" stroke-width="1"/>'
        .texto(($bx1 + $bx2) / 2, $by1 + 16, [$d['sistema'] ?? 'Sistema Conecta Vagas DF']);
    foreach ($d['rotulosColunas'] ?? [] as $col => $rotulo) {
        $casosColuna = array_values(array_filter($uc, fn($u) => $u['col'] === (int)$col));
        if (!$casosColuna) continue;
        $centro = array_sum(array_column($casosColuna, 'x')) / count($casosColuna);
        $s .= texto($centro, $by1 + 38, [(string)$rotulo], 'middle', ' font-weight="bold"');
    }
    // associações (linhas retas ator → caso de uso)
    foreach ($d['ligacoes'] as [$a, $id]) {
        $A = $atores[$a]; $u = $uc[$id];
        $esq = $A['x'] < $u['x'];
        $ax = $A['x'] + ($esq ? 14 : -14); $ay = $A['y'];
        [$ex, $ey] = [$esq ? $u['x'] - $u['rx'] : $u['x'] + $u['rx'], $u['y']];   // ponta da elipse virada para o ator
        $s .= '<line x1="'.round($ax, 1).'" y1="'.round($ay, 1).'" x2="'.round($ex, 1).'" y2="'.round($ey, 1).'" stroke="#000" stroke-width="1"/>';
    }
    // include / extend (tracejado com seta aberta no destino)
    foreach ($d['relacoes'] ?? [] as [$de, $para, $tipo]) {
        $a = $uc[$de]; $b = $uc[$para];
        [$x1, $y1] = bordaElipse($b['x'], $b['y'], $a['x'], $a['y'], $a['rx'], $a['ry']);
        [$x2, $y2] = bordaElipse($a['x'], $a['y'], $b['x'], $b['y'], $b['rx'], $b['ry']);
        $s .= '<line x1="'.round($x1, 1).'" y1="'.round($y1, 1).'" x2="'.round($x2, 1).'" y2="'.round($y2, 1).'" stroke="#000" stroke-width="1" stroke-dasharray="5,4"/>'.seta($x1, $y1, $x2, $y2);
        $mx = ($x1 + $x2) / 2; $my = ($y1 + $y2) / 2;
        $s .= '<rect x="'.round($mx - larg('«'.$tipo.'»') / 2 - 2).'" y="'.round($my - 13).'" width="'.round(larg('«'.$tipo.'»') + 4).'" height="13" fill="#fff"/>'
            .texto($mx, $my - 7, ['«'.$tipo.'»']);
    }
    // casos de uso (desenhados por cima das linhas)
    foreach ($uc as $u) {
        $s .= '<ellipse cx="'.round($u['x'], 1).'" cy="'.round($u['y'], 1).'" rx="'.round($u['rx'], 1).'" ry="'.round($u['ry'], 1).'" fill="#FFFFE0" stroke="#000" stroke-width="1"/>'
            .texto($u['x'], $u['y'], $u['l']);
    }
    foreach ($atores as $nome => $a) $s .= ator($a['x'], $a['y'], $nome);
    return $s.'</svg>';
}

/** Vários diagramas lado a lado (grade) dentro de uma moldura só. */
function grade(array $g): string {
    $partes = []; $col = $g['colunasGrade'] ?? 2;
    foreach ($g['quadros'] as $q) { $svg = desenhar($q, false); preg_match('/width="(\d+)" height="(\d+)"/', $svg, $m); $partes[] = [$svg, (int)$m[1], (int)$m[2]]; }
    $larg = []; $alt = [];
    foreach ($partes as $i => [, $w, $h]) { $c = $i % $col; $l = intdiv($i, $col); $larg[$c] = max($larg[$c] ?? 0, $w); $alt[$l] = max($alt[$l] ?? 0, $h); }
    $W = array_sum($larg) + 20; $H = array_sum($alt) + 40;
    $s = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$W.'" height="'.$H.'" viewBox="0 0 '.$W.' '.$H.'"><rect x="0" y="0" width="'.$W.'" height="'.$H.'" fill="#fff"/>';
    $tab = 'uc '.$g['nome']; $tw = larg($tab) + 18;
    $s .= '<rect x="4" y="4" width="'.($W - 8).'" height="'.($H - 8).'" fill="none" stroke="#000" stroke-width="1"/><polygon points="4,4 '.round(4 + $tw).',4 '.round(4 + $tw).',16 '.round(4 + $tw - 8).',24 4,24" fill="#fff" stroke="#000" stroke-width="1"/>'.texto(10, 14, [$tab], 'start');
    foreach ($partes as $i => [$svg, $w, $h]) {
        $c = $i % $col; $l = intdiv($i, $col);
        $x = 10 + array_sum(array_slice($larg, 0, $c)); $y = 30 + array_sum(array_slice($alt, 0, $l));
        $interno = preg_replace('/^<svg[^>]*>|<\/svg>$/', '', $svg);
        $s .= '<g transform="translate('.$x.','.$y.')">'.$interno.'</g>';
    }
    return $s.'</svg>';
}

$specs = require $argv[1];
$saida = rtrim($argv[2], '/\\');
foreach ($specs as $arq => $d) { file_put_contents("$saida/$arq.svg", isset($d['quadros']) ? grade($d) : desenhar($d)); echo "$arq.svg\n"; }
