<?php
/**
 * CRUD de usuários (rota admin/pages/usuarios.php) — só administrador.
 * Recebe de AdminController::usuarios(): $usuarios (página atual), $totalUsuarios, $pagina, $paginas, $edit, $ver (ficha da conta),
 * $filtroTipo, $filtroSituacao, $busca, $ordem, $dir e $meuId.
 */
?>
<div class="pn">
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?=painel_cabecalho('Usuários', 'Cadastre, veja, edite, bloqueie e remova contas. Você não pode remover nem rebaixar a própria conta, e o sistema mantém pelo menos um administrador ativo.')?>

<?php if ($ver): ?>
<section class="form pn-ficha" aria-labelledby="ficha-titulo">
    <div class="pn-ficha-topo">
        <div>
            <h2 id="ficha-titulo"><?=e($ver['nome'])?> <small class="meta">#<?=(int)$ver['id']?></small></h2>
            <p class="meta"><?=e($ver['email'])?><?=$ver['telefone'] ? ' · '.e($ver['telefone']) : ''?></p>
        </div>
        <div class="actions">
            <?=painel_status((string)$ver['tipo'])?> <?=$ver['ativo'] ? painel_status('ativo', 'Ativo') : painel_status('bloqueado', 'Bloqueado')?>
            <?php if ($ver['plano_ativo']): ?><?=painel_status('publicado', $ver['plano_ativo'] === 'assinante' ? 'Candidato VIP' : 'Empresa Premium')?><?php endif; ?>
        </div>
    </div>
    <dl class="pn-ficha-dados">
        <?php if ($ver['tipo'] === 'empresa' && $ver['perfil_id']): ?>
            <div><dt>Nome fantasia</dt><dd><?=e($ver['nome_fantasia'] ?: '—')?></dd></div>
            <div><dt>Setor</dt><dd><?=e($ver['setor'] ?: '—')?></dd></div>
            <div><dt>Vagas publicadas</dt><dd><?=(int)$ver['total_vagas']?></dd></div>
        <?php elseif ($ver['tipo'] === 'candidato'): ?>
            <div><dt>Título profissional</dt><dd><?=e($ver['titulo_profissional'] ?: '—')?></dd></div>
            <div><dt>Candidaturas</dt><dd><?=(int)$ver['total_candidaturas']?></dd></div>
            <div><dt>Currículos enviados</dt><dd><?=(int)$ver['total_curriculos']?></dd></div>
            <div><dt>Perfil público</dt><dd><?=$ver['publico'] ? 'Sim' : 'Não'?></dd></div>
        <?php endif; ?>
        <?php if ($ver['tipo'] !== 'admin'): ?><div><dt>Cidade</dt><dd><?=e(trim(($ver['cidade'] ?? '').($ver['uf'] ? '/'.$ver['uf'] : ''), '/') ?: '—')?></dd></div><?php endif; ?>
        <div><dt>Conta criada em</dt><dd><?=date('d/m/Y', strtotime((string)$ver['created_at']))?></dd></div>
        <div><dt>Último acesso</dt><dd><?=$ver['ultimo_acesso'] ? date('d/m/Y H:i', strtotime((string)$ver['ultimo_acesso'])) : 'Nunca entrou'?></dd></div>
    </dl>
    <div class="form-actions">
        <a class="btn btn-sm btn-outline" href="<?=e(painel_qs(['edit' => (int)$ver['id']]))?>#form-usuario">Editar</a>
        <?php if ($ver['tipo'] === 'candidato' && $ver['perfil_id']): ?><a class="btn btn-sm btn-outline" href="<?=url('view/perfil/portfolio.php?id='.(int)$ver['perfil_id'])?>" target="_blank" rel="noopener">Ver portfólio<span class="sr-only"> (abre em nova aba)</span></a><?php endif; ?>
        <?php if ($ver['tipo'] === 'empresa'): ?><a class="btn btn-sm btn-outline" href="<?=url('admin/pages/vagas.php?empresa='.(int)$ver['perfil_id'])?>#lista-vagas">Vagas da empresa (<?=(int)$ver['total_vagas']?>)</a><?php endif; ?>
        <?php if ($ver['tipo'] !== 'admin'): ?><a class="btn btn-sm btn-outline" href="<?=url('admin/pages/assinaturas.php?usuario_id='.(int)$ver['id'])?>#lista-assinaturas"><?=$ver['plano_ativo'] ? 'Assinaturas' : 'Assinaturas / conceder plano'?></a><?php endif; ?>
        <a class="btn btn-sm btn-outline" href="<?=e(url('admin/pages/usuarios.php').painel_qs())?>">Fechar</a>
    </div>
</section>
<?php endif; ?>

<div class="form">
    <h2 class="pn-form-titulo" id="form-usuario"><?= $edit ? 'Editar usuário #'.(int)$edit['id'] : 'Novo usuário' ?></h2>
    <form method="post">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?=(int)($edit['id'] ?? 0)?>">
        <div class="form-grid">
            <div><label for="u-nome">Nome</label><input id="u-nome" name="nome" required maxlength="255" value="<?=e($edit['nome'] ?? '')?>"></div>
            <div><label for="u-email">E-mail</label><input id="u-email" type="email" name="email" required maxlength="255" value="<?=e($edit['email'] ?? '')?>"></div>
            <div><label for="u-tipo">Tipo</label><select id="u-tipo" name="tipo"><?php foreach (UsuarioDAO::TIPOS as $t): ?><option value="<?=$t?>" <?=($edit['tipo'] ?? 'candidato') === $t ? 'selected' : ''?>><?=e(rotulo($t))?></option><?php endforeach; ?></select></div>
            <div><label for="u-tel">Telefone</label><input id="u-tel" name="telefone" maxlength="30" value="<?=e($edit['telefone'] ?? '')?>"></div>
            <div><label for="u-senha">Senha <?= $edit ? '<small class="muted">(deixe vazio para manter)</small>' : '' ?></label><input id="u-senha" type="password" name="senha" minlength="6" maxlength="72" <?=$edit ? '' : 'required'?> autocomplete="new-password"></div>
            <div><label for="u-ativo">Ativo</label><select id="u-ativo" name="ativo"><option value="1" <?=(!$edit || (int)$edit['ativo']) ? 'selected' : ''?>>Sim</option><option value="0" <?=$edit && !(int)$edit['ativo'] ? 'selected' : ''?>>Não (bloqueia o login)</option></select></div>
        </div>
        <div class="form-actions"><button class="btn">Salvar</button><?php if ($edit): ?><a class="btn btn-outline" href="<?=url('admin/pages/usuarios.php')?>">Cancelar</a><?php endif; ?></div>
    </form>
</div>

<form class="filtros" method="get" action="#lista-usuarios" style="grid-template-columns:2fr 1fr 1fr auto">
    <?php if (get_str('ordem') !== ''): ?><input type="hidden" name="ordem" value="<?=e($ordem)?>"><input type="hidden" name="dir" value="<?=e($dir)?>"><?php endif; ?>
    <input name="q" placeholder="Buscar por nome ou e-mail" value="<?=e($busca)?>" aria-label="Buscar por nome ou e-mail">
    <select name="tipo" aria-label="Tipo de conta"><option value="">Todos os tipos</option><?php foreach (UsuarioDAO::TIPOS as $t): ?><option value="<?=$t?>" <?=$filtroTipo === $t ? 'selected' : ''?>><?=e(rotulo($t))?></option><?php endforeach; ?></select>
    <select name="situacao" aria-label="Situação da conta"><option value="">Ativas e bloqueadas</option><option value="ativo" <?=$filtroSituacao === 'ativo' ? 'selected' : ''?>>Só ativas</option><option value="bloqueado" <?=$filtroSituacao === 'bloqueado' ? 'selected' : ''?>>Só bloqueadas</option></select>
    <button class="btn">Filtrar</button>
</form>
<div class="pn-contagem" id="lista-usuarios"><h2>Contas</h2><span><?=gf_num($totalUsuarios)?> <?=gf_plural($totalUsuarios, 'usuário encontrado', 'usuários encontrados')?><?=$paginas > 1 ? ' · página '.$pagina.' de '.$paginas : ''?><?=$filtroTipo !== '' || $busca !== '' || $filtroSituacao !== '' ? ' · <a href="'.e(url('admin/pages/usuarios.php')).'#lista-usuarios">limpar filtros</a>' : ''?></span></div>
<div class="table-wrap"><table class="table">
    <tr><?=painel_th('id', 'ID', $ordem, $dir, 'num')?><?=painel_th('nome', 'Nome', $ordem, $dir)?><?=painel_th('email', 'E-mail', $ordem, $dir)?><?=painel_th('tipo', 'Tipo', $ordem, $dir)?><?=painel_th('ativo', 'Situação', $ordem, $dir)?><?=painel_th('ultimo_acesso', 'Último acesso', $ordem, $dir)?><th>Ações</th></tr>
    <?php foreach ($usuarios as $x): $eu = (int)$x['id'] === $meuId; ?>
    <tr>
        <td class="num meta"><?=(int)$x['id']?></td><td><?=e($x['nome'])?><?=$eu ? ' <small class="meta">(você)</small>' : ''?></td><td><?=e($x['email'])?></td>
        <td><?=painel_status((string)$x['tipo'])?></td>
        <td><?php if ($eu): ?><?=painel_status('ativo', 'Ativo')?><?php else: ?><?=painel_chave((bool)$x['ativo'], 'ativar', 'desativar', (int)$x['id'], 'Ativo', 'Bloqueado', 'Bloquear esta conta? A pessoa perde o acesso na hora.', (string)$x['nome'])?><?php endif; ?></td>
        <td class="meta"><?=$x['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($x['ultimo_acesso'])) : '—'?></td>
        <td><?=painel_botoes([
            ['href' => painel_qs(['ver' => (int)$x['id']]), 'texto' => 'Ver', 'icone' => 'olho', 'estilo' => 'primario'],
            ['href' => painel_qs(['edit' => (int)$x['id']]).'#form-usuario', 'texto' => 'Editar', 'icone' => 'editar'],
            $eu ? null : ['acao' => 'excluir', 'id' => (int)$x['id'], 'texto' => 'Remover usuário', 'icone' => 'lixeira', 'estilo' => 'perigo', 'confirmar' => 'Remover este usuário e todos os dados dele? Esta ação não pode ser desfeita.'],
        ], (string)$x['nome'])?></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?=painel_paginacao($pagina, $paginas)?>
<?php if (!$usuarios): ?><div class="empty">Nenhum usuário encontrado.</div><?php endif; ?>
</div>
