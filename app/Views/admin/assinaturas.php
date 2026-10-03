<?php
/**
 * CRUD de assinaturas (rota admin/pages/assinaturas.php) — só administrador.
 * Recebe de AdminController::assinaturas(): $lista (página atual), $totalLista, $pagina, $paginas, $todas, $resumo
 * (AssinaturaDAO::resumoPorPlano), $edit, $contas (candidatos e empresas ativos), $filtroPlano, $filtroStatus,
 * $filtroUsuario, $busca, $ordem e $dir.
 */
$nomePlano = ['assinante' => 'Candidato VIP', 'empresa' => 'Empresa Premium'];
$receita = $resumo['assinante']['receita'] + $resumo['empresa']['receita'];
$encerradas = $resumo['assinante']['canceladas'] + $resumo['empresa']['canceladas'] + $resumo['assinante']['expiradas'] + $resumo['empresa']['expiradas'];
$dinheiro = fn($v) => 'R$ '.number_format((float)$v, 2, ',', '.');
?>
<div class="pn">
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?=painel_cabecalho('Assinaturas', 'Planos Candidato VIP e Empresa Premium: conceda, veja, edite, cancele e remova. Cada conta tem no máximo uma assinatura ativa; cobrança demonstrativa.')?>
<div class="pn-kpis">
    <?=painel_kpi('Candidato VIP', gf_num($resumo['assinante']['vigentes']), 'vigentes · '.$dinheiro(AssinaturaDAO::PRECOS['assinante']).'/mês', 'planos', 'admin/pages/assinaturas.php?plano=assinante&status=ativa#lista-assinaturas')?>
    <?=painel_kpi('Empresa Premium', gf_num($resumo['empresa']['vigentes']), 'vigentes · '.$dinheiro(AssinaturaDAO::PRECOS['empresa']).'/mês', 'maleta', 'admin/pages/assinaturas.php?plano=empresa&status=ativa#lista-assinaturas')?>
    <?=painel_kpi('Receita mensal', $dinheiro($receita), 'das vigentes (demonstrativo)', 'dinheiro')?>
    <?=painel_kpi('Encerradas', gf_num($encerradas), 'canceladas ou expiradas', 'relogio', 'admin/pages/assinaturas.php?status=cancelada#lista-assinaturas')?>
</div>

<div class="form">
<?php if ($edit): ?>
    <h2 class="pn-form-titulo" id="form-assinatura">Editar assinatura #<?=(int)$edit['id']?></h2>
    <p class="meta"><?=e($nomePlano[$edit['plano']] ?? $edit['plano'])?> de <b><?=e($edit['usuario_nome'])?></b> (<?=e($edit['usuario_email'])?>)</p>
    <form method="post">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?=(int)$edit['id']?>">
        <div class="form-grid">
            <div><label for="as-valor">Valor mensal (R$)</label><input id="as-valor" name="valor" inputmode="decimal" required value="<?=e(number_format((float)$edit['valor'], 2, ',', '.'))?>"></div>
            <div><label for="as-ini">Início</label><input id="as-ini" type="date" name="data_inicio" required value="<?=e($edit['data_inicio'])?>"></div>
            <div><label for="as-fim">Fim</label><input id="as-fim" type="date" name="data_fim" required value="<?=e($edit['data_fim'])?>"></div>
            <div><label for="as-st">Situação</label><select id="as-st" name="status"><?php foreach (AssinaturaDAO::STATUS as $st): ?><option value="<?=$st?>" <?=$edit['status'] === $st ? 'selected' : ''?>><?=e(rotulo($st))?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-actions"><button class="btn">Salvar</button><a class="btn btn-outline" href="<?=e(url('admin/pages/assinaturas.php').painel_qs())?>">Cancelar</a></div>
    </form>
<?php else: ?>
    <h2 class="pn-form-titulo" id="form-assinatura">Conceder plano</h2>
    <p class="meta">Candidato recebe o Candidato VIP; empresa recebe o Empresa Premium. Se a conta já tiver uma assinatura ativa, ela é substituída.</p>
    <form method="post">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="conceder">
        <div class="form-grid">
            <div><label for="as-conta">Conta</label><select id="as-conta" name="usuario_id" required><option value="">Escolha a conta</option>
                <?php foreach (['candidato' => 'Candidatos → Candidato VIP', 'empresa' => 'Empresas → Empresa Premium'] as $tipo => $grupo): ?>
                    <optgroup label="<?=e($grupo)?>"><?php foreach ($contas as $u): if ($u['tipo'] !== $tipo) continue; ?><option value="<?=(int)$u['id']?>" <?=$filtroUsuario === (int)$u['id'] ? 'selected' : ''?>><?=e($u['nome'])?> — <?=e($u['email'])?></option><?php endforeach; ?></optgroup>
                <?php endforeach; ?></select></div>
            <div><label for="as-dias">Duração (dias)</label><input id="as-dias" type="number" name="dias" min="1" max="3660" value="30" required></div>
            <div><label for="as-valor-n">Valor mensal (R$)</label><input id="as-valor-n" name="valor" inputmode="decimal" placeholder="vazio = preço do plano"></div>
        </div>
        <div class="form-actions"><button class="btn">Conceder plano</button></div>
    </form>
<?php endif; ?>
</div>

<div class="pn-contagem" id="lista-assinaturas"><h2>Histórico de assinaturas</h2><span><?=gf_num($totalLista)?> <?=gf_plural($totalLista, 'assinatura', 'assinaturas')?><?=$paginas > 1 ? ' · página '.$pagina.' de '.$paginas : ''?><?=$filtroPlano !== '' || $filtroStatus !== '' || $busca !== '' || $filtroUsuario ? ' · <a href="'.e(url('admin/pages/assinaturas.php')).'#lista-assinaturas">limpar filtros</a>' : ''?></span></div>
<?=painel_subabas('plano', $filtroPlano, ['' => ['Todos os planos', count($todas)], 'assinante' => ['Candidato VIP', count(array_filter($todas, fn($a) => $a['plano'] === 'assinante'))], 'empresa' => ['Empresa Premium', count(array_filter($todas, fn($a) => $a['plano'] === 'empresa'))]], 'Plano')?>
<form class="filtros" method="get" action="#lista-assinaturas" style="grid-template-columns:2fr 1fr auto">
    <?php if ($filtroPlano !== ''): ?><input type="hidden" name="plano" value="<?=e($filtroPlano)?>"><?php endif; ?>
    <?php if (get_str('ordem') !== ''): ?><input type="hidden" name="ordem" value="<?=e($ordem)?>"><input type="hidden" name="dir" value="<?=e($dir)?>"><?php endif; ?>
    <input name="q" placeholder="Buscar pelo nome ou e-mail da conta" value="<?=e($busca)?>" aria-label="Buscar pelo nome ou e-mail da conta">
    <select name="status" aria-label="Situação"><option value="">Todas as situações</option><?php foreach (AssinaturaDAO::STATUS as $st): ?><option value="<?=$st?>" <?=$filtroStatus === $st ? 'selected' : ''?>><?=e(rotulo($st))?></option><?php endforeach; ?></select>
    <button class="btn">Filtrar</button>
</form>
<div class="table-wrap"><table class="table">
    <tr><?=painel_th('id', 'ID', $ordem, $dir, 'num')?><?=painel_th('usuario_nome', 'Conta', $ordem, $dir)?><?=painel_th('plano', 'Plano', $ordem, $dir)?><?=painel_th('valor', 'Valor', $ordem, $dir, 'num')?><?=painel_th('data_inicio', 'Início', $ordem, $dir)?><?=painel_th('data_fim', 'Fim', $ordem, $dir)?><?=painel_th('status', 'Situação', $ordem, $dir)?><th>Ações</th></tr>
    <?php foreach ($lista as $x): $vencida = $x['status'] === 'ativa' && !$x['vigente']; ?>
    <tr>
        <td class="num meta"><?=(int)$x['id']?></td>
        <td class="quebra"><?=e($x['usuario_nome'])?><br><small class="meta"><?=e($x['usuario_email'])?></small></td>
        <td><?=e($nomePlano[$x['plano']] ?? $x['plano'])?></td>
        <td class="num"><?=e($dinheiro($x['valor']))?></td>
        <td class="meta"><?=date('d/m/Y', strtotime((string)$x['data_inicio']))?></td>
        <td class="meta"><?=date('d/m/Y', strtotime((string)$x['data_fim']))?></td>
        <td><?=$vencida ? painel_status('expirada', 'Vencida') : painel_status((string)$x['status'])?></td>
        <td><?=painel_botoes([
            ['href' => url('admin/pages/usuarios.php?ver='.(int)$x['usuario_id']), 'texto' => 'Conta', 'icone' => 'usuario', 'estilo' => 'primario'],
            ['href' => painel_qs(['edit' => (int)$x['id']]).'#form-assinatura', 'texto' => 'Editar', 'icone' => 'editar'],
            $x['status'] === 'ativa' ? ['acao' => 'cancelar', 'id' => (int)$x['id'], 'texto' => 'Cancelar', 'icone' => 'encerrar', 'estilo' => 'alerta', 'confirmar' => 'Cancelar esta assinatura? A conta volta ao plano gratuito na hora.'] : null,
            ['acao' => 'excluir', 'id' => (int)$x['id'], 'texto' => 'Remover assinatura', 'icone' => 'lixeira', 'estilo' => 'perigo', 'confirmar' => 'Remover esta assinatura do histórico? Para manter o registro, use Cancelar.'],
        ], 'assinatura #'.(int)$x['id'])?></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?=painel_paginacao($pagina, $paginas)?>
<?php if (!$lista): ?><div class="empty"><?=$filtroPlano !== '' || $filtroStatus !== '' || $busca !== '' || $filtroUsuario ? 'Nenhuma assinatura com esses filtros.' : 'Nenhuma assinatura ainda. Use "Conceder plano" ou aguarde as contratações em Planos.'?></div><?php endif; ?>
</div>
