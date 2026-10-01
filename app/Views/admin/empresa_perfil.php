<?php
/**
 * Dados da empresa (rota admin/pages/empresa_perfil.php): nome fantasia, CNPJ, site, logo...
 * Recebe de EmpresaController::perfil(): $p (perfil da empresa).
 */
?>
<div class="pn">
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?=painel_cabecalho('Perfil da empresa', 'O nome fantasia e a logo aparecem nas suas vagas e no portfólio da empresa.')?>
<div class="form">
    <h3>Dados da empresa</h3>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <div class="form-grid">
            <div><label for="emp-nome">Nome fantasia</label><input id="emp-nome" name="nome_fantasia" required maxlength="255" value="<?=e($p['nome_fantasia'])?>"></div>
            <div><label for="emp-cnpj">CNPJ</label><input id="emp-cnpj" name="cnpj" maxlength="20" value="<?=e($p['cnpj'])?>" placeholder="00.000.000/0000-00"></div>
            <div><label for="emp-setor">Setor</label><input id="emp-setor" name="setor" maxlength="100" value="<?=e($p['setor'])?>"></div>
            <div><label for="emp-site">Site</label><input id="emp-site" name="site" type="url" maxlength="255" value="<?=e($p['site'])?>" placeholder="https://"></div>
            <div><label for="emp-tel">Telefone</label><input id="emp-tel" name="telefone" maxlength="30" value="<?=e($p['telefone'])?>"></div>
            <div><label for="emp-logo">Logo</label><input id="emp-logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp"></div>
            <div><label for="emp-cidade">Cidade</label><input id="emp-cidade" name="cidade" maxlength="100" value="<?=e($p['cidade'] ?: 'Brasília')?>"></div>
            <div><label for="emp-uf">UF</label><input id="emp-uf" name="uf" maxlength="2" value="<?=e($p['uf'] ?: 'DF')?>"></div>
            <div class="full"><label for="emp-bio">Sobre a empresa</label><textarea id="emp-bio" name="bio" rows="4"><?=e($p['bio'])?></textarea></div>
        </div>
        <?php if (!empty($p['foto'])): ?><p class="small muted" style="margin:12px 0 0"><img src="<?=e(url((string)$p['foto']))?>" alt="Logo atual da empresa" style="max-height:56px;border-radius:6px;vertical-align:middle;margin-right:8px">Logo atual — envie outra para trocar.</p><?php endif; ?>
        <div class="form-actions"><button class="btn">Salvar empresa</button><a class="btn btn-outline" href="<?=url('admin/pages/vagas.php')?>">Gerenciar vagas</a></div>
    </form>
</div>
</div>
