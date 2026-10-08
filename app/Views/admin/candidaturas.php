<?php
/**
 * Candidaturas recebidas, com o match de cada candidato (rota admin/pages/candidaturas.php).
 * Recebe de EmpresaController::candidaturas(): $lista (página atual), $totalLista, $pagina, $paginas, $vagasFiltro,
 * $vagaId, $status, $ordem, $ordensCand e $statusPermitidos.
 */
?>
<div class="pn">
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?=painel_cabecalho('Candidaturas recebidas', 'Candidatos VIP primeiro, depois o maior match com a vaga. Mude o status e escreva um retorno: o candidato vê no perfil dele.')?>
<form class="filtros" method="get" style="grid-template-columns:2fr 1fr 1fr auto">
    <select name="vaga_id" aria-label="Vaga"><option value="">Todas as vagas</option><?php foreach ($vagasFiltro as $v): ?><option value="<?=(int)$v['id']?>" <?=$vagaId === (int)$v['id'] ? 'selected' : ''?>><?=e($v['titulo'])?><?=isAdmin() ? ' — '.e($v['empresa_nome'] ?? '') : ''?></option><?php endforeach; ?></select>
    <select name="status" aria-label="Status da candidatura"><option value="">Todos os status</option><?php foreach (CandidaturaDAO::STATUS as $s): ?><option value="<?=$s?>" <?=$status === $s ? 'selected' : ''?>><?=e(rotulo($s))?></option><?php endforeach; ?></select>
    <select name="ordem" aria-label="Ordenar por"><?php foreach ($ordensCand as $k => $r): ?><option value="<?=$k?>" <?=$ordem === $k ? 'selected' : ''?>>Ordem: <?=e($r)?></option><?php endforeach; ?></select>
    <button class="btn">Filtrar</button>
</form>
<div class="pn-contagem"><h2>Candidaturas</h2><span><?=gf_num($totalLista)?> <?=gf_plural($totalLista, 'candidatura', 'candidaturas')?><?=$paginas > 1 ? ' · página '.$pagina.' de '.$paginas : ''?><?=$vagaId || $status !== '' ? ' · <a href="'.e(url('admin/pages/candidaturas.php')).'">limpar filtros</a>' : ''?></span></div>

<?php foreach ($lista as $x): $d = $x['match_detalhes']; $cancelada = $x['status'] === 'cancelada'; ?>
<article class="pn-cand<?=$x['is_vip'] ? ' vip' : ''?>" aria-labelledby="cand-<?=(int)$x['id']?>">
    <div>
        <p class="pn-cand-nome" id="cand-<?=(int)$x['id']?>"><?=e($x['candidato_nome'])?><?=$x['is_vip'] ? ' <span class="badge-vip">VIP</span>' : ''?> <?=painel_status((string)$x['status'])?></p>
        <p class="meta"><?=e($x['titulo_profissional'] ?: 'Candidato')?> · <?=e(rotulo((string)$x['nivel_experiencia']))?><?=!empty($x['candidato_cidade']) ? ' · '.e($x['candidato_cidade']) : ''?></p>
        <p class="meta">Vaga: <b><?=e($x['titulo'])?></b><?=isAdmin() ? ' · '.e($x['empresa_nome']) : ''?> · enviada em <?=date('d/m/Y H:i', strtotime($x['data_candidatura']))?></p>
        <?php if (!$cancelada || isAdmin()): ?>
            <p class="meta pn-cand-contato"><?=icone('email', 14)?> <?=e($x['email'])?><?php if ($x['telefone']): ?> · <?=icone('telefone', 14)?> <?=e($x['telefone'])?><?php endif; ?></p>
        <?php endif; ?>
        <?php if (!empty($d['competencias']['atendidas']) || !empty($d['competencias']['faltantes'])): ?>
            <div class="chips" aria-label="Competências da vaga"><?php foreach ($d['competencias']['atendidas'] ?? [] as $c): ?><span class="chip ok"><?=icone('check', 12)?> <?=e($c)?><span class="sr-only"> (atende)</span></span><?php endforeach; ?><?php foreach ($d['competencias']['faltantes'] ?? [] as $c): ?><span class="chip falta"><?=e($c)?><span class="sr-only"> (falta)</span></span><?php endforeach; ?></div>
            <?php if (!empty($d['observacoes'])): ?><ul class="small pn-cand-obs"><?php foreach ($d['observacoes'] as $o): ?><li class="<?=!empty($o['ok']) ? 'ok' : 'aviso'?>"><?=e($o['texto'] ?? '')?></li><?php endforeach; ?></ul><?php endif; ?>
        <?php endif; ?>
        <?php if ($x['carta_apresentacao']): ?><details><summary>Carta de apresentação</summary><p class="small pn-cand-carta"><?=e($x['carta_apresentacao'])?></p></details><?php endif; ?>
        <div class="actions" style="margin-top:10px">
            <?php if (!$cancelada || isAdmin()): ?>
                <a class="btn btn-sm btn-outline" target="_blank" rel="noopener" href="<?=url('view/perfil/portfolio.php?id='.(int)$x['candidato_perfil_id'])?>">Ver portfólio<span class="sr-only"> de <?=e($x['candidato_nome'])?> (abre em nova aba)</span></a>
                <?php if ($x['curriculo_id']): ?><a class="btn btn-sm btn-green" target="_blank" rel="noopener" href="<?=url('download.php?id='.(int)$x['curriculo_id'])?>"><?=icone('formulario', 14)?> Currículo (<?=e(strtoupper((string)$x['curriculo_tipo']))?>)<span class="sr-only"> (abre em nova aba)</span></a><?php else: ?><span class="meta">Currículo removido pelo candidato</span><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="pn-cand-match">
        <?php if ($x['match_pontuacao'] !== null): ?>
            <?=painel_match($x['match_pontuacao'], (string)$x['match_nivel'])?><div class="meta">match</div>
        <?php else: ?><span class="meta">match indisponível<br>(vaga inativa)</span><?php endif; ?>
    </div>
    <?php if (!isAdmin() && $cancelada): ?>
        <div class="notice small pn-cand-aviso">Esta candidatura foi cancelada. Ela fica só como histórico: o contato e o currículo deixam de ficar disponíveis.</div>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$x['id']?>">
        <label for="st-<?=(int)$x['id']?>">Status</label>
        <select id="st-<?=(int)$x['id']?>" name="status"><?php foreach ($statusPermitidos as $s): ?><option value="<?=$s?>" <?=$x['status'] === $s ? 'selected' : ''?>><?=e(rotulo($s))?></option><?php endforeach; ?></select>
        <label for="obs-<?=(int)$x['id']?>">Retorno para o candidato</label>
        <textarea id="obs-<?=(int)$x['id']?>" name="observacao" rows="2" maxlength="2000" placeholder="Ex.: Entrevista na segunda às 10h."><?=e($x['observacao_empresa'])?></textarea>
        <div class="actions" style="margin-top:8px"><button class="btn btn-sm">Salvar</button>
        <?php if (isAdmin() && !$cancelada): ?><button class="btn btn-sm btn-danger" name="acao" value="cancelar" data-confirm="Cancelar esta candidatura? Ela continua guardada no histórico. Clique em OK para confirmar.">Cancelar candidatura</button><?php endif; ?></div>
    </form>
    <?php endif; ?>
</article>
<?php endforeach; ?>
<?=painel_paginacao($pagina, $paginas)?>
<?php if (!$lista): ?><div class="empty">Nenhuma candidatura encontrada<?=$vagaId || $status !== '' ? ' com esses filtros' : ''?>.</div><?php endif; ?>
</div>
