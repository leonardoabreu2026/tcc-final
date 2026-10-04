<?php
declare(strict_types=1);
/*
 * Relatório da extração do currículo (incluído no perfil e no portfólio do dono).
 * Espera $relatorio (array guardado na sessão por CurriculoController::upload()).
 */
if (!isset($relatorio) || !is_array($relatorio)) return;

$rotulosStatus = [
    'aplicado' => ['Aplicado ao perfil', 'ok'],
    'mesclado' => ['Somado ao seu', 'ok'],
    'igual' => ['Igual ao seu perfil', 'neutro'],
    'mantido' => ['Mantido o seu', 'aviso'],
    'sugerido' => ['Nome diferente', 'aviso'],
    'informativo' => ['Lido do currículo', 'neutro'],
    'nao_encontrado' => ['Não encontrado', 'falta'],
];
$itensRel = $relatorio['itens'] ?? [];
$contar = fn(array $st) => count(array_filter($itensRel, fn($i) => in_array($i['status'], $st, true)));
$encontrados = $contar(['aplicado', 'mesclado', 'igual', 'mantido', 'sugerido', 'informativo']);
$aplicadosRel = $contar(['aplicado', 'mesclado']);
$mantidos = array_values(array_filter($itensRel, fn($i) => in_array($i['status'], ['mantido', 'sugerido'], true) && ($i['campo'] === 'nome' ? ($relatorio['nome_sugerido'] ?? '') !== '' : isset($relatorio['pendentes'][$i['campo']]))));
$faltando = array_values(array_filter($itensRel, fn($i) => $i['status'] === 'nao_encontrado'));
?>
<section class="pf-relatorio" aria-labelledby="pf-rel-titulo">
  <?=carregador_html('Pronto: currículo lido — confira o relatório e aplique o que quiser no perfil.', true)?>
  <div class="pf-rel-topo">
    <div>
      <h2 id="pf-rel-titulo">Relatório da extração do currículo</h2>
      <p class="pf-rel-sub"><?=e($relatorio['arquivo'] ?? '')?> · <?=e($relatorio['quando'] ?? '')?> · lido por <?=e($relatorio['metodo'] ?? '')?><?=!empty($relatorio['substituir']) ? ' · modo "substituir dados"' : ''?></p>
    </div>
    <div class="pf-rel-numeros">
      <span><b><?=$encontrados?></b> encontrados</span>
      <span><b><?=$aplicadosRel?></b> aplicados</span>
      <span><b><?=count($mantidos)?></b> mantidos</span>
      <span><b><?=count($faltando)?></b> faltando</span>
    </div>
  </div>

  <?php if ($mantidos): ?>
  <form class="pf-rel-aplicar" method="post" action="<?=url('view/perfil/aplicar_extracao.php')?>">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <p><b>Você já tinha estes dados preenchidos, então mantivemos os seus.</b> Marque os que quer trocar pelo que está no currículo:</p>
    <?php foreach ($mantidos as $i): ?>
      <label class="pf-rel-opcao">
        <input type="checkbox" name="campos[]" value="<?=e($i['campo'])?>">
        <?php if (($i['imagem'] ?? '') !== ''): ?><img class="pf-rel-foto" src="<?=e(url($i['imagem']))?>" alt="Foto encontrada no currículo"><?php endif; ?>
        <span><b><?=e($i['rotulo'])?>:</b> <?=e($i['valor'])?><?php if ($i['atual'] !== ''): ?> <small>(atual: <?=e($i['atual'])?>)</small><?php endif; ?></span>
      </label>
    <?php endforeach; ?>
    <button class="btn btn-sm">Usar os dados marcados</button>
  </form>
  <?php endif; ?>

  <details class="pf-rel-detalhes" <?=count($itensRel) <= 30 ? 'open' : ''?>>
    <summary>Ver campo por campo</summary>
    <div class="pf-rel-tabela" role="table">
      <?php foreach ($itensRel as $i): [$rot, $cls] = $rotulosStatus[$i['status']] ?? [$i['status'], 'neutro']; ?>
        <div class="pf-rel-linha" role="row">
          <span class="pf-rel-campo" role="cell"><?=e($i['rotulo'])?></span>
          <span class="pf-rel-valor" role="cell"><?php if (($i['imagem'] ?? '') !== ''): ?><img class="pf-rel-foto" src="<?=e(url($i['imagem']))?>" alt=""><?php endif; ?><?=$i['valor'] !== '' ? e($i['valor']) : '<span class="muted">—</span>'?></span>
          <span class="pf-rel-status <?=e($cls)?>" role="cell"><?=e($rot)?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </details>

  <?php if ($faltando): ?>
    <p class="pf-rel-falta">Não encontramos no arquivo: <b><?=e(implode(', ', array_map(fn($i) => $i['rotulo'], $faltando)))?></b>.
      <?php // No próprio perfil, só desce até o formulário (o relatório continua aberto); no portfólio, abre o perfil. ?>
      <a href="<?=Router::atual() === 'view/perfil/index.php' ? '#cadastro' : e(url('view/perfil/index.php#cadastro'))?>">Completar no perfil</a> para melhorar o portfólio e o match.</p>
  <?php endif; ?>
  <?php if (!empty($relatorio['match'])): ?><p class="pf-rel-match"><?=e($relatorio['match'])?> <a href="<?=url('view/perfil/portfolio.php#match')?>">Ver vagas compatíveis</a></p><?php endif; ?>
</section>
