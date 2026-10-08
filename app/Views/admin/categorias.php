<?php
/**
 * CRUD de categorias de vagas e cursos (rota admin/pages/categorias.php) — só administrador.
 * Recebe de AdminController::categorias(): $cats (página atual), $todasCats, $totalCats, $pagina, $paginas, $edit,
 * $filtroTipo, $busca, $ordem e $dir.
 */
?>
<div class="pn">
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?=painel_cabecalho('Categorias', 'Áreas usadas para agrupar vagas e cursos. Desativar ou cancelar esconde a categoria dos filtros, e as vagas e os cursos continuam com ela.')?>
<div class="form">
    <h2 class="pn-form-titulo" id="form-categoria"><?=$edit ? 'Editar categoria' : 'Nova categoria'?></h2>
    <form method="post">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?=(int)($edit['id'] ?? 0)?>">
        <div class="form-grid">
            <div><label for="cat-nome">Nome</label><input id="cat-nome" name="nome" required maxlength="100" value="<?=e($edit['nome'] ?? '')?>"></div>
            <div><label for="cat-tipo">Usada em</label><select id="cat-tipo" name="tipo"><?php foreach (CategoriaDAO::TIPOS as $t): ?><option value="<?=$t?>" <?=($edit['tipo'] ?? 'vaga') === $t ? 'selected' : ''?>><?=$t === 'vaga' ? 'Vagas' : 'Cursos'?></option><?php endforeach; ?></select></div>
            <div><label for="cat-ativo">Ativa</label><select id="cat-ativo" name="ativo"><option value="1">Sim</option><option value="0" <?=$edit && !(int)$edit['ativo'] ? 'selected' : ''?>>Não (some dos filtros)</option></select></div>
        </div>
        <div class="form-actions"><button class="btn">Salvar</button><?php if ($edit): ?><a class="btn btn-outline" href="<?=url('admin/pages/categorias.php')?>">Cancelar</a><?php endif; ?></div>
    </form>
</div>
<div class="pn-contagem" id="lista-categorias"><h2>Categorias cadastradas</h2><span><?=gf_num($totalCats)?> <?=gf_plural($totalCats, 'categoria', 'categorias')?><?=$paginas > 1 ? ' · página '.$pagina.' de '.$paginas : ''?><?=$filtroTipo !== '' || $busca !== '' ? ' · <a href="'.e(url('admin/pages/categorias.php')).'#lista-categorias">limpar filtros</a>' : ''?></span></div>
<?=painel_subabas('tipo', $filtroTipo, ['' => ['Todas', count($todasCats)], 'vaga' => ['De vagas', count(array_filter($todasCats, fn($c) => $c['tipo'] === 'vaga'))], 'curso' => ['De cursos e e-books', count(array_filter($todasCats, fn($c) => $c['tipo'] === 'curso'))]], 'Uso da categoria')?>
<form class="filtros" method="get" action="#lista-categorias" style="grid-template-columns:2fr auto">
    <?php if ($filtroTipo !== ''): ?><input type="hidden" name="tipo" value="<?=e($filtroTipo)?>"><?php endif; ?>
    <?php if (get_str('ordem') !== ''): ?><input type="hidden" name="ordem" value="<?=e($ordem)?>"><input type="hidden" name="dir" value="<?=e($dir)?>"><?php endif; ?>
    <input name="q" placeholder="Buscar categoria pelo nome" value="<?=e($busca)?>" aria-label="Buscar categoria pelo nome">
    <button class="btn">Filtrar</button>
</form>
<div class="table-wrap"><table class="table">
    <tr><?=painel_th('nome', 'Nome', $ordem, $dir)?><?=painel_th('tipo', 'Usada em', $ordem, $dir)?><?=painel_th('ativo', 'Situação', $ordem, $dir)?><?=painel_th('em_uso', 'Itens', $ordem, $dir, 'num')?><th>Ações</th></tr>
    <?php foreach ($cats as $x): $linkPublico = $x['tipo'] === 'vaga' ? 'vagas.php?categoria_id='.(int)$x['id'] : 'cursos.php?categoria_id='.(int)$x['id']; ?>
    <tr>
        <td><?=e($x['nome'])?></td><td><?=$x['tipo'] === 'vaga' ? 'Vagas' : 'Cursos'?></td>
        <td><?=painel_chave((bool)$x['ativo'], 'ativar', 'desativar', (int)$x['id'], 'Ativa', 'Inativa', '', (string)$x['nome'])?></td>
        <td class="num"><?=(int)$x['em_uso']?></td>
        <td><?=painel_botoes([
            ['href' => url($linkPublico), 'texto' => 'Ver', 'icone' => 'olho', 'estilo' => 'primario', 'nova_aba' => true],
            ['href' => painel_qs(['edit' => (int)$x['id']]).'#form-categoria', 'texto' => 'Editar', 'icone' => 'editar'],
            !$x['ativo'] ? null : ['acao' => 'cancelar', 'id' => (int)$x['id'], 'texto' => 'Cancelar categoria', 'icone' => 'fechar', 'estilo' => 'perigo', 'confirmar' => 'Cancelar esta categoria? Ela sai dos filtros, e as vagas e os cursos continuam com ela. Clique em OK para confirmar.'],
        ], (string)$x['nome'])?></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?=painel_paginacao($pagina, $paginas)?>
<?php if (!$cats): ?><div class="empty"><?=$filtroTipo !== '' || $busca !== '' ? 'Nenhuma categoria com esses filtros.' : 'Nenhuma categoria cadastrada.'?></div><?php endif; ?>
</div>
