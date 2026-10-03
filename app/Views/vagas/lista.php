<?php
/**
 * Lista de vagas (rota vagas.php): faixa de título, filtros, abas por área, grade de cartões e paginação.
 * Recebe de VagaController::lista(): $filtros, $vagas (só a página atual), $total, $pagina, $paginas, $porPagina,
 * $todas, $categorias, $catAtual, $contagem, $mapaMatch, $minhas, $ordenarMatch, $filtrosAtivos, $trilha,
 * $dbErro e $qs (monta links mantendo filtros e ordem).
 */
$primeiro = $total ? ($pagina - 1) * $porPagina + 1 : 0;
$ultimo = min($total, $pagina * $porPagina);
?>
<?=cv_faixa($catAtual ? 'Vagas em '.$catAtual['nome'] : 'Vagas de Emprego', 'Oportunidades abertas no Distrito Federal, publicadas pelas empresas.', $trilha)?>

<div class="cv-wrap an-lista">
  <?php if ($dbErro): ?>
    <div class="alert erro" style="margin-top:16px"><b>Não foi possível carregar as vagas.</b> Verifique se o MySQL está ligado e se o banco <code><?=e(DB_NAME)?></code> foi importado.<br><small><?=e($dbErro)?></small></div>
  <?php endif; ?>

  <form class="an-filtros" method="get" action="<?=e(url('vagas.php'))?>" role="search" aria-label="Buscar vagas">
    <?php if ($filtros['categoria_id']): ?><input type="hidden" name="categoria_id" value="<?=(int)$filtros['categoria_id']?>"><?php endif; ?>
    <?php if ($ordenarMatch): ?><input type="hidden" name="ordem" value="match"><?php endif; ?>
    <label class="an-campo an-campo-busca"><span class="sr-only">Cargo, palavra-chave ou empresa</span><?=icone('busca', 18)?>
      <input type="search" name="q" placeholder="Cargo, palavra-chave ou empresa" value="<?=e($filtros['q'])?>"></label>
    <label class="an-campo"><span class="sr-only">Cidade</span><?=icone('local', 18)?>
      <input name="cidade" placeholder="Cidade ou região" value="<?=e($filtros['cidade'])?>"></label>
    <select name="tipo" aria-label="Contratação"><option value="">Qualquer contratação</option><?php foreach (VagaDAO::TIPOS as $n): ?><option value="<?=$n?>" <?=$filtros['tipo'] === $n ? 'selected' : ''?>><?=e(rotulo($n))?></option><?php endforeach; ?></select>
    <select name="nivel" aria-label="Nível"><option value="">Qualquer nível</option><?php foreach (VagaDAO::NIVEIS as $n): ?><option value="<?=$n?>" <?=$filtros['nivel'] === $n ? 'selected' : ''?>><?=e(rotulo($n))?></option><?php endforeach; ?></select>
    <select name="remoto" aria-label="Modelo de trabalho"><option value="">Qualquer modelo</option><?php foreach (VagaDAO::MODELOS as $n): ?><option value="<?=$n?>" <?=$filtros['remoto'] === $n ? 'selected' : ''?>><?=e(rotulo($n))?></option><?php endforeach; ?></select>
    <button class="cv-btn cv-btn-azul"><?=icone('busca', 16)?>Buscar</button>
  </form>

  <nav class="an-abas" aria-label="Áreas">
    <a href="<?=e($qs(['categoria_id' => 0]))?>"<?=$catAtual ? '' : ' class="ativo" aria-current="page"'?>>Todas <span><?=count($todas)?></span></a>
    <?php foreach ($categorias as $c): if (empty($contagem[(int)$c['id']])) continue; $ativa = $catAtual && (int)$catAtual['id'] === (int)$c['id']; ?>
      <a href="<?=e($qs(['categoria_id' => (int)$c['id']]))?>"<?=$ativa ? ' class="ativo" aria-current="page"' : ''?>><?=e($c['nome'])?> <span><?=$contagem[(int)$c['id']]?></span></a>
    <?php endforeach; ?>
  </nav>

  <div class="an-barra">
    <p class="an-barra-total" role="status">
      <b><?=$total?></b> <?=$total === 1 ? 'vaga encontrada' : 'vagas encontradas'?>
      <?php if ($paginas > 1): ?><span class="an-barra-faixa">· mostrando <?=$primeiro?>–<?=$ultimo?></span><?php endif; ?>
    </p>
    <?php if ($filtrosAtivos): ?>
      <ul class="an-etiquetas" aria-label="Filtros em uso">
        <?php foreach ($filtrosAtivos as $f): ?><li><a href="<?=e($f['link'])?>" title="Remover este filtro"><?=e($f['texto'])?> <span aria-hidden="true">×</span><span class="sr-only"> (remover filtro)</span></a></li><?php endforeach; ?>
        <li><a class="an-limpar" href="<?=e(url('vagas.php'))?>">Limpar tudo</a></li>
      </ul>
    <?php endif; ?>
    <?php if ($mapaMatch): ?>
      <a class="cv-btn an-btn-linha an-ordem" href="<?=e($qs(['ordem' => $ordenarMatch ? '' : 'match']))?>"><?=icone('alvo', 15)?><?=$ordenarMatch ? 'Ordenar por data' : 'Ordenar pelo meu match'?></a>
    <?php endif; ?>
  </div>

  <?php if ($vagas): ?>
    <div class="cv-grade an-grade">
      <?php foreach ($vagas as $v): ?><?=cv_card_vaga($v, $mapaMatch[(int)$v['id']] ?? null, $minhas[(int)$v['id']] ?? null)?><?php endforeach; ?>
    </div>

    <?=cv_paginacao($pagina, $paginas, fn(int $n) => $qs(['pagina' => $n]))?>
  <?php elseif (!$dbErro): ?>
    <div class="an-vazio">
      <span class="an-vazio-ic"><?=icone('busca', 34)?></span>
      <h2>Nenhuma vaga encontrada</h2>
      <p>Tente outra palavra-chave, tire algum filtro ou veja todas as oportunidades abertas<?=$catAtual ? ' em outras áreas' : ''?>.</p>
      <p class="an-vazio-acoes">
        <?php if ($filtrosAtivos && $catAtual): ?><a class="cv-btn an-btn-linha" href="<?=e($qs(['q' => '', 'cidade' => '', 'tipo' => '', 'nivel' => '', 'remoto' => '']))?>">Ver todas em <?=e($catAtual['nome'])?></a><?php endif; ?>
        <a class="cv-btn cv-btn-azul" href="<?=e(url('vagas.php'))?>">Ver todas as vagas</a>
      </p>
    </div>
  <?php endif; ?>
</div>
