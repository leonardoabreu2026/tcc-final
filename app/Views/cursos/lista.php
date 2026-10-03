<?php
/**
 * Cursos (rota cursos.php), e-books (?tipo=ebook) e vídeos (?tipo=video) — cada formato na sua página,
 * sem misturar: abas por formato, filtros, "Recomendados para você" (candidato) e grade de cartões.
 * Recebe de CursoController::lista(): $tipo, $filtros, $cursos, $categorias, $porTipo, $recomendados,
 * $faltantesTop, $tituloPag, $subPag, $nomeFormato, $abaUrl, $limparUrl, $dbErro
 * a ordem ($ordem, $ordens) e a paginação ($encontrados, $pagina, $paginas, $porPagina, $qs).
 */
?>
<?=cv_faixa($tituloPag, $subPag, [pt_secao_formato($tipo)[0] => ''])?>

<div class="cv-wrap">
  <?php if ($dbErro): ?>
    <div class="alert erro" style="margin-top:16px"><b>Não foi possível carregar os <?=e($nomeFormato)?>.</b> Verifique se o MySQL está ligado e se o banco <code><?=e(DB_NAME)?></code> foi importado.<br><small><?=e($dbErro)?></small></div>
  <?php endif; ?>

  <?php $rotuloBusca = ['curso' => 'Curso', 'ebook' => 'E-book', 'video' => 'Vídeo'][$tipo].', tema ou instituição'; ?>
  <form class="cv-filtros cv-filtros-cursos" method="get" role="search" aria-label="Buscar <?=e($nomeFormato)?>">
    <?php if ($tipo !== 'curso'): ?><input type="hidden" name="tipo" value="<?=e($tipo)?>"><?php endif; ?>
    <input name="q" placeholder="<?=e($rotuloBusca)?>" value="<?=e($filtros['q'])?>" aria-label="<?=e($rotuloBusca)?>">
    <select name="categoria_id" aria-label="Área"><option value="">Todas as áreas</option><?php foreach ($categorias as $c): ?><option value="<?=(int)$c['id']?>" <?=$filtros['categoria_id'] === (int)$c['id'] ? 'selected' : ''?>><?=e($c['nome'])?></option><?php endforeach; ?></select>
    <select name="gratuito" aria-label="Preço"><option value="">Gratuitos e pagos</option><option value="1" <?=$filtros['gratuito'] === '1' ? 'selected' : ''?>>Só gratuitos</option></select>
    <select name="ordem" aria-label="Ordenar por"><?php foreach ($ordens as $k => $r): ?><option value="<?=e($k)?>" <?=$ordem === $k ? 'selected' : ''?>><?=e($r)?></option><?php endforeach; ?></select>
    <button class="cv-btn cv-btn-azul"><?=icone('busca', 16)?>Buscar</button>
  </form>

  <nav class="cv-abas" aria-label="Formato">
    <?php foreach (CursoDAO::TIPOS as $t): if ($t === 'video' && !$porTipo['video'] && $tipo !== 'video') continue; // Vídeos: só quando houver ?>
      <a href="<?=e($abaUrl($t))?>"<?=$tipo === $t ? ' class="ativo" aria-current="page"' : ''?>><?=icone(pt_secao_formato($t)[2], 14)?> <?=e(pt_secao_formato($t)[0])?> <small>(<?=(int)$porTipo[$t]?>)</small></a>
    <?php endforeach; ?>
  </nav>

  <?php if (count($atalhosArea) > 1 || $filtros['categoria_id']): // atalhos de área: filtrar com um clique ?>
    <nav class="cv-areas" aria-label="Áreas">
      <a href="<?=e($linkLista($tipo, ['categoria_id' => '']))?>"<?=!$filtros['categoria_id'] ? ' class="ativo" aria-current="page"' : ''?>>Todas as áreas</a>
      <?php foreach ($atalhosArea as $a): $aid = (int)$a['id']; ?>
        <a href="<?=e($linkLista($tipo, ['categoria_id' => $aid]))?>"<?=$filtros['categoria_id'] === $aid ? ' class="ativo" aria-current="page"' : ''?>><?=e($a['nome'])?> <small><?=(int)$porArea[$aid]?></small></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <?php if ($recomendados): ?>
    <div class="cv-recomendados">
      <h2><?=icone('alvo', 18)?> Recomendados para você</h2>
      <p>As vagas mais compatíveis com você pedem: <?=e(implode(', ', $faltantesTop))?>. Estes cursos cobrem essas competências:</p>
      <ul class="cv-rel">
        <?php foreach ($recomendados as $c): ?>
          <li><a href="<?=url('curso.php?id='.(int)$c['id'])?>"><?=e($c['titulo'])?></a><small><?=e($c['instituicao'] ?: '')?> · cobre: <?=e(implode(', ', $c['cobre']))?></small></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php $primeiro = $encontrados ? ($pagina - 1) * $porPagina + 1 : 0; $ultimo = min($encontrados, $pagina * $porPagina); ?>
  <?php $unidade = $encontrados === 1 ? ['curso' => 'curso', 'ebook' => 'e-book', 'video' => 'vídeo'][$tipo] : $nomeFormato; ?>
  <div class="cv-barra"><span><b><?=$encontrados?></b> <?=e($unidade)?><?=$paginas > 1 ? ' · mostrando '.$primeiro.'–'.$ultimo : ''?><?=array_filter($filtros) ? ' · <a href="'.e($limparUrl).'">limpar filtros</a>' : ''?></span></div>

  <?php if ($cursos): ?>
    <div class="cv-grade"><?php foreach ($cursos as $c): ?><?=cv_card_curso($c)?><?php endforeach; ?></div>
    <?=cv_paginacao($pagina, $paginas, fn(int $n) => $qs(['pagina' => $n]))?>
  <?php elseif (!$dbErro): ?>
    <?php if (array_filter($filtros)): ?>
      <div class="empty">Nenhum <?=e(['curso' => 'curso', 'ebook' => 'e-book', 'video' => 'vídeo'][$tipo])?> encontrado com esses filtros. <a href="<?=e($limparUrl)?>">Limpar filtros</a></div>
    <?php else: ?>
      <div class="empty"><?=['curso' => 'Nenhum curso publicado ainda.', 'ebook' => 'Nenhum e-book publicado ainda.', 'video' => 'Nenhum vídeo publicado ainda.'][$tipo]?> Em breve, novos conteúdos por aqui.
        <?php if ($tipo !== 'curso'): ?><a href="<?=url('cursos.php')?>">Ver os cursos</a><?php endif; ?></div>
    <?php endif; ?>
  <?php endif; ?>
  <div style="height:28px"></div>
</div>
