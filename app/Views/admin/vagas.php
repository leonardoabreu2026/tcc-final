<?php
/**
 * CRUD de vagas + extração de vagas (rota admin/pages/vagas.php) — empresa (suas vagas) e administrador.
 * Recebe de EmpresaController::vagas(): $form, $extraido, $relatorioVaga, $textoAnuncio, $parecida, $cats,
 * $empresas, $lista, $porSituacao, $filtroStatus, $busca, $imagens, $isPremium, $ocrDisponivel e $dinheiro.
 */
$planoHtml = '';
if (isEmpresa()) {
    $planoHtml = $isPremium
        ? '<span class="badge-pro">Empresa Premium — vagas ilimitadas e destaque</span>'
        : '<span class="tag">Plano Básico: até '.AssinaturaDAO::LIMITE_VAGAS_GRATIS.' vagas ativas</span> <a class="btn btn-sm btn-outline" href="'.e(url('planos.php')).'">Fazer upgrade</a>';
}
$cartazNoForm = !empty($form['imagem']) && str_starts_with((string)$form['imagem'], 'assets/uploads/cartaz_');
?>
<div class="pn">
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?=painel_cabecalho('Vagas', 'Publique pelo cartaz ou pelo texto do anúncio: a máquina de extração preenche o formulário e você só revisa. Ative, pause, edite ou cancele pela lista.', $planoHtml)?>

<div class="form" style="max-width:none">
    <details class="extrator" <?=$extraido || !empty($form['id']) ? '' : 'open'?>>
        <summary>Máquina de extração: envie o cartaz da vaga (imagem) ou cole o anúncio — o formulário é preenchido</summary>
        <?php /* Leitor de cartaz da plataforma: o OCR roda no navegador (assets/js/leitor-cartaz.js), sem nada instalado no servidor. */ ?>
        <form method="post" enctype="multipart/form-data" class="cartaz-form" style="margin-top:10px" data-leitor-cartaz="<?=e(url('assets/js/vendor/tesseract/'))?>">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="ler_cartaz"><input type="hidden" name="id" value="<?=(int)($form['id'] ?? 0)?>">
            <?php if (isAdmin()): ?><input type="hidden" name="perfil_empresa_id" value="<?=(int)($form['perfil_empresa_id'] ?? 0)?>"><?php endif; ?>
            <label for="cartaz">1. Cartaz da vaga (JPG, PNG ou WEBP, até 8 MB) — a leitura começa ao escolher o arquivo</label>
            <input type="file" id="cartaz" name="cartaz" accept="image/jpeg,image/png,image/webp" required data-auto-envio>
            <img data-previa hidden alt="Prévia do cartaz escolhido" class="ex-previa">
            <?=carregador_html()?>
            <?php if (!$ocrDisponivel): ?><noscript><p class="small ex-aviso">Com o JavaScript desligado o leitor de cartaz não roda: o cartaz será salvo como imagem da vaga, mas os campos precisam ser preenchidos à mão ou pelo texto abaixo.</p></noscript><?php endif; ?>
            <div class="form-actions"><button class="btn">Ler cartaz e preencher</button></div>
        </form>
        <script src="<?=url('assets/js/leitor-cartaz.js')?>?v=1" defer></script>

        <form method="post" style="margin-top:8px" data-carregando="Extraindo os dados do anúncio…">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="extrair"><input type="hidden" name="id" value="<?=(int)($form['id'] ?? 0)?>">
            <?php if (isAdmin()): ?><input type="hidden" name="perfil_empresa_id" value="<?=(int)($form['perfil_empresa_id'] ?? 0)?>"><?php endif; ?>
            <?php if ($cartazNoForm): ?><input type="hidden" name="imagem_atual" value="<?=e($form['imagem'])?>"><?php endif; ?>
            <label for="texto_anuncio">2. <?=!empty($extraido['texto_ocr']) ? 'Texto lido no cartaz — corrija o que o OCR leu errado e extraia de novo (o cartaz continua como imagem)' : '…ou cole o texto do anúncio (WhatsApp, Instagram, site)'?></label>
            <textarea id="texto_anuncio" name="texto_anuncio" required rows="<?=!empty($extraido['texto_ocr']) ? 10 : 6?>" placeholder="Ex.: VAGA: Vendedor Interno — Taguatinga&#10;Salário: R$ 3.000 a R$ 5.500 + comissões&#10;Requisitos: experiência com vendas, ensino médio&#10;Benefícios: VT + VR"><?=e($textoAnuncio)?></textarea>
            <div class="form-actions"><button class="btn btn-outline"><?=!empty($extraido['texto_ocr']) ? 'Extrair de novo com o texto corrigido' : 'Extrair dados do anúncio'?></button></div>
        </form>
    </details>

    <?php if ($relatorioVaga): ?>
    <section class="pf-relatorio" id="relatorio-extracao" aria-labelledby="ex-rel-titulo">
        <?=carregador_html(isset($extraido['confianca']) ? 'Pronto: cartaz lido — confira os campos e salve a vaga.' : 'Pronto: anúncio lido — confira os campos e salve a vaga.', true)?>
        <div class="pf-rel-topo">
            <div>
                <h2 id="ex-rel-titulo">Relatório da extração da vaga</h2>
                <p class="pf-rel-sub"><?=isset($extraido['confianca']) ? 'Lido do cartaz'.(!empty($extraido['ocr']) ? ' com '.(int)$extraido['confianca'].'% de confiança' : ' (sem OCR neste servidor)') : 'Lido do texto do anúncio'?> · nada foi salvo: revise o formulário abaixo e clique em "Salvar vaga".</p>
            </div>
            <div class="pf-rel-numeros">
                <span><b><?=(int)$relatorioVaga['lidos']?></b> lidos do anúncio</span>
                <span><b><?=(int)$relatorioVaga['padrao']?></b> valor padrão</span>
                <span><b><?=(int)$relatorioVaga['faltando']?></b> faltando</span>
            </div>
        </div>
        <?php if ($extraido['avisos'] ?? []): ?>
            <ul class="ex-avisos"><?php foreach ($extraido['avisos'] as $a): ?><li><?=e($a)?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <details class="pf-rel-detalhes" open>
            <summary>Ver campo por campo</summary>
            <div class="pf-rel-tabela" role="table" aria-label="Campos extraídos">
                <?php foreach ($relatorioVaga['itens'] as $i): [$rot, $cls] = ['lido' => ['Lido do anúncio', 'ok'], 'padrao' => ['Valor padrão — confira', 'aviso'], 'falta' => ['Não encontrado', 'falta']][$i['status']]; ?>
                <div class="pf-rel-linha" role="row">
                    <span class="pf-rel-campo" role="cell"><?=e($i['rotulo'])?></span>
                    <span class="pf-rel-valor" role="cell"><?=$i['valor'] !== '' ? e($i['valor']) : '<span class="muted">—</span>'?></span>
                    <span class="pf-rel-status <?=e($cls)?>" role="cell"><?=e($rot)?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </details>
        <?php if (!empty($extraido['padroes'])): ?>
        <div class="ex-padroes">
            <h3>Padrões automáticos usados</h3>
            <ul>
                <?php foreach ($extraido['padroes'] as $a): ?>
                <li><span class="ex-padroes-campo"><?=e(['linha' => 'Linha', 'categoria' => 'Área', 'anunciante' => 'Empresa'][$a['campo']] ?? $a['campo'])?></span> “<?=e(mb_strimwidth((string)$a['texto'], 0, 90, '…'))?>” → <b><?=e($a['campo'] === 'linha' ? (['descricao' => 'Descrição / atividades', 'requisitos' => 'Requisitos', 'beneficios' => 'Benefícios'][$a['para']] ?? $a['para']) : (string)$a['para'])?></b>
                    <span class="muted">(padrão: <?=e((string)$a['termo'])?>)</span></li>
                <?php endforeach; ?>
            </ul>
            <p class="small muted" style="margin:8px 0 0">Tirados das vagas já cadastradas: valem onde a regra não tem pista e se atualizam a cada vaga salva.</p>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php if ($parecida): ?>
        <div class="alert erro">Já existe uma vaga aberta igual: <a href="<?=url('vaga.php?id='.(int)$parecida['id'])?>" target="_blank" rel="noopener">#<?=(int)$parecida['id']?> — <?=e($parecida['titulo'])?> (<?=e($parecida['empresa_nome'] ?? '')?>)<span class="sr-only"> (abre em nova aba)</span></a>. Confira antes de publicar.</div>
    <?php endif; ?>

    <h2 class="pn-form-titulo" id="form-vaga"><?=!empty($form['id']) ? 'Editar vaga #'.(int)$form['id'] : 'Cadastrar vaga'?></h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?=(int)($form['id'] ?? 0)?>">
        <div class="form-grid">
            <?php if (isAdmin()): ?>
                <div class="full"><label for="v-empresa">Empresa (quem publica)</label><select id="v-empresa" name="perfil_empresa_id" required><option value="">Selecione</option><?php foreach ($empresas as $ep): ?><option value="<?=(int)$ep['id']?>" <?=(int)($form['perfil_empresa_id'] ?? 0) === (int)$ep['id'] ? 'selected' : ''?>><?=e($ep['nome_fantasia'] ?: $ep['nome'])?></option><?php endforeach; ?></select></div>
            <?php endif; ?>
            <div><label for="v-titulo">Título</label><input id="v-titulo" name="titulo" required maxlength="255" value="<?=e($form['titulo'])?>"></div>
            <div><label for="v-anunciante">Empresa anunciante <small class="muted">(se a vaga for de outra empresa)</small></label><input id="v-anunciante" name="anunciante" maxlength="150" value="<?=e($form['anunciante'] ?? '')?>"></div>
            <div class="full"><label for="v-contato">Contato do anúncio <small class="muted">(WhatsApp, telefone ou e-mail)</small></label><input id="v-contato" name="contato" maxlength="255" value="<?=e($form['contato'] ?? '')?>"></div>
            <div><label for="v-cat">Área (categoria)</label><select id="v-cat" name="categoria_id"><option value="">Sem categoria</option><?php foreach ($cats as $c): ?><option value="<?=(int)$c['id']?>" <?=(int)($form['categoria_id'] ?? 0) === (int)$c['id'] ? 'selected' : ''?>><?=e($c['nome'])?><?=$c['ativo'] ? '' : ' (inativa)'?></option><?php endforeach; ?></select></div>
            <div><label for="v-tipo">Contratação</label><select id="v-tipo" name="tipo_vaga"><?php foreach (VagaDAO::TIPOS as $x): ?><option value="<?=$x?>" <?=$form['tipo_vaga'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label for="v-nivel">Nível</label><select id="v-nivel" name="nivel_experiencia"><?php foreach (VagaDAO::NIVEIS as $x): ?><option value="<?=$x?>" <?=$form['nivel_experiencia'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label for="v-modelo">Modelo</label><select id="v-modelo" name="remoto"><?php foreach (VagaDAO::MODELOS as $x): ?><option value="<?=$x?>" <?=$form['remoto'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label for="v-status">Status</label><select id="v-status" name="status"><?php foreach (VagaDAO::STATUS as $x): ?><option value="<?=$x?>" <?=$form['status'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label for="v-cidade">Cidade</label><input id="v-cidade" name="cidade" maxlength="100" value="<?=e($form['cidade'])?>"></div>
            <div><label for="v-uf">UF</label><input id="v-uf" name="uf" maxlength="2" value="<?=e($form['uf'])?>"></div>
            <div><label for="v-smin">Salário mínimo (R$)</label><input id="v-smin" name="salario_minimo" inputmode="decimal" value="<?=e($dinheiro($form['salario_minimo']))?>" placeholder="Ex.: 1.900,00"></div>
            <div><label for="v-smax">Salário máximo (R$)</label><input id="v-smax" name="salario_maximo" inputmode="decimal" value="<?=e($dinheiro($form['salario_maximo']))?>" placeholder="Vazio = a combinar"></div>
            <div><label for="v-exp">Inscrições até</label><input id="v-exp" type="date" name="data_expiracao" value="<?=e($form['data_expiracao'])?>"></div>
            <div><label for="v-img">Imagem (caminho)</label><input id="v-img" name="imagem" list="imgs-vaga" maxlength="255" value="<?=e($form['imagem'])?>"><datalist id="imgs-vaga"><?php foreach ($imagens as $i): ?><option value="<?=e($i)?>"><?php endforeach; ?></datalist></div>
            <div class="full"><label for="v-arq">…ou envie uma imagem</label><input id="v-arq" type="file" name="imagem_arquivo" accept="image/jpeg,image/png,image/webp"></div>
            <div class="full check ex-destaque<?=$isPremium ? ' premium' : ''?>">
                <input type="checkbox" name="destaque" value="1" id="chk_destaque" <?=!empty($form['destaque']) ? 'checked' : ''?> <?=$isPremium ? '' : 'disabled'?>>
                <label for="chk_destaque" style="margin:0"><strong>Destacar esta vaga no topo (recurso Premium)</strong><br>
                <span class="muted small"><?=$isPremium ? 'A vaga ganha borda dourada e aparece primeiro na busca.' : 'Disponível no Plano Empresa Premium.'?></span></label>
                <?php if (!$isPremium): ?><a class="small" href="<?=url('planos.php')?>">Ativar</a><?php endif; ?>
            </div>
            <div class="full"><label for="v-desc">Descrição / atividades</label><textarea id="v-desc" name="descricao" rows="4"><?=e($form['descricao'])?></textarea></div>
            <div class="full"><label for="v-req">Requisitos <small class="muted">— as competências daqui alimentam o match</small></label><textarea id="v-req" name="requisitos" rows="4"><?=e($form['requisitos'])?></textarea></div>
            <div class="full"><label for="v-ben">Benefícios</label><textarea id="v-ben" name="beneficios" rows="3"><?=e($form['beneficios'])?></textarea></div>
        </div>
        <?php if ($cartazNoForm): ?><p class="small muted ex-cartaz"><img src="<?=e(url($form['imagem']))?>" alt="Cartaz enviado"> Cartaz enviado (será a imagem da vaga).</p><?php endif; ?>
        <?php if ($parecida): ?><div class="check"><input type="checkbox" name="publicar_duplicada" value="1" id="dup"><label for="dup">Publicar mesmo assim (é outra vaga)</label></div><?php endif; ?>
        <div class="form-actions"><button class="btn">Salvar vaga</button><?php if (!empty($form['id']) || $extraido): ?><a class="btn btn-outline" href="<?=url('admin/pages/vagas.php')?>">Cancelar</a><?php endif; ?></div>
    </form>
</div>

<form class="filtros" method="get" action="#lista-vagas" style="grid-template-columns:<?=isAdmin() ? '2fr 1fr 1fr auto' : '2fr 1fr auto'?>">
    <?php if (get_str('ordem') !== ''): ?><input type="hidden" name="ordem" value="<?=e($ordem)?>"><input type="hidden" name="dir" value="<?=e($dir)?>"><?php endif; ?>
    <input name="q" placeholder="Buscar por título, empresa ou cidade" value="<?=e($busca)?>" aria-label="Buscar por título, empresa ou cidade">
    <select name="status" aria-label="Situação da vaga">
        <option value="">Todas as situações</option>
        <?php foreach (['ativa' => 'Abertas', 'pausada' => 'Pausadas', 'encerrada' => 'Encerradas', 'cancelada' => 'Canceladas', 'expirada' => 'Expiradas'] as $st => $rot): ?>
            <option value="<?=$st?>" <?=$filtroStatus === $st ? 'selected' : ''?>><?=$rot?> (<?=(int)($porSituacao[$st] ?? 0)?>)</option>
        <?php endforeach; ?>
    </select>
    <?php if (isAdmin()): ?><select name="empresa" aria-label="Empresa"><option value="">Todas as empresas</option><?php foreach ($empresas as $emp): ?><option value="<?=(int)$emp['id']?>" <?=$filtroEmpresa === (int)$emp['id'] ? 'selected' : ''?>><?=e($emp['nome_fantasia'] ?: $emp['nome'])?></option><?php endforeach; ?></select><?php endif; ?>
    <button class="btn">Filtrar</button>
</form>
<div class="pn-contagem" id="lista-vagas"><h2><?=isAdmin() ? 'Vagas cadastradas' : 'Suas vagas'?></h2><span><?=gf_num($totalLista)?> <?=gf_plural($totalLista, 'vaga', 'vagas')?><?=$paginas > 1 ? ' · página '.$pagina.' de '.$paginas : ''?><?=$filtroStatus !== '' || $busca !== '' || $filtroEmpresa ? ' · <a href="'.e(url('admin/pages/vagas.php')).'#lista-vagas">limpar filtros</a>' : ''?></span></div>
<div class="table-wrap"><table class="table">
    <tr><th><span class="sr-only">Imagem</span></th><?=painel_th('titulo', 'Vaga', $ordem, $dir)?><?php if (isAdmin()): ?><?=painel_th('empresa_nome', 'Empresa', $ordem, $dir)?><?php endif; ?><?=painel_th('situacao', 'Situação', $ordem, $dir)?><?=isAdmin() ? '<th class="num">Candidaturas</th>' : painel_th('total_candidaturas', 'Candidaturas', $ordem, $dir, 'num')?><?=painel_th('visualizacoes', 'Visualizações', $ordem, $dir, 'num')?><?=painel_th('created_at', 'Publicada em', $ordem, $dir)?><th>Ações</th></tr>
    <?php foreach ($lista as $x):
        $expirada = $x['status'] === 'ativa' && !empty($x['data_expiracao']) && $x['data_expiracao'] < date('Y-m-d');
        $fechada = in_array($x['status'], ['encerrada', 'cancelada'], true); ?>
    <tr>
        <td class="pn-td-img"><?=painel_miniatura((string)($x['imagem'] ?? ''), 'cartaz', 'vagas')?></td>
        <td class="quebra"><?=e($x['titulo'])?><?=$x['destaque'] ? ' <span class="badge-vip" title="Vaga em destaque">Destaque</span>' : ''?><br><small class="meta"><?=e($x['categoria_nome'] ?? 'Sem categoria')?> · <?=e($x['cidade'] ?? '')?><?=!empty($x['uf']) ? '/'.e($x['uf']) : ''?></small></td>
        <?php if (isAdmin()): ?><td class="quebra"><?=e($x['empresa_nome'] ?? '')?></td><?php endif; ?>
        <td><?php if ($fechada): ?><?=painel_status((string)$x['status'])?>
            <?php else: ?><?=painel_chave($x['status'] === 'ativa', 'ativar', 'pausar', (int)$x['id'], 'Aberta', 'Pausada', '', (string)$x['titulo'])?><?=$expirada ? '<span class="pn-chave-nota">prazo vencido</span>' : ''?><?php endif; ?></td>
        <td class="num"><a href="<?=url('admin/pages/candidaturas.php?vaga_id='.(int)$x['id'])?>"><?=isset($x['total_candidaturas']) ? (int)$x['total_candidaturas'] : 'ver'?></a></td>
        <td class="num"><?=(int)$x['visualizacoes']?></td>
        <td class="meta"><?=!empty($x['created_at']) ? date('d/m/Y', strtotime((string)$x['created_at'])) : '—'?></td>
        <td><?=painel_botoes([
            ['href' => url('vaga.php?id='.(int)$x['id']), 'texto' => 'Ver', 'icone' => 'olho', 'estilo' => 'primario', 'nova_aba' => true],
            ['href' => painel_qs(['edit' => (int)$x['id']]).'#form-vaga', 'texto' => 'Editar', 'icone' => 'editar'],
            $fechada
                ? ['acao' => 'ativar', 'id' => (int)$x['id'], 'texto' => 'Reabrir a vaga', 'icone' => 'play', 'estilo' => 'sucesso', 'so_icone' => true]
                : ['acao' => 'encerrar', 'id' => (int)$x['id'], 'texto' => 'Encerrar a vaga', 'icone' => 'encerrar', 'estilo' => 'alerta', 'so_icone' => true, 'confirmar' => 'Encerrar esta vaga? Ela sai da busca e para de receber candidaturas.'],
            $x['status'] === 'cancelada' ? null
                : ['acao' => 'cancelar', 'id' => (int)$x['id'], 'texto' => 'Cancelar vaga', 'icone' => 'fechar', 'estilo' => 'perigo', 'confirmar' => 'Cancelar esta vaga? Ela sai do ar, e as candidaturas dela continuam guardadas. Clique em OK para confirmar.'],
        ], (string)$x['titulo'])?></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?=painel_paginacao($pagina, $paginas)?>
<?php if (!$lista): ?><div class="empty"><?=$filtroStatus !== '' || $busca !== '' || $filtroEmpresa ? 'Nenhuma vaga com esses filtros.' : 'Nenhuma vaga cadastrada ainda. Use a extração acima para publicar a primeira em segundos.'?></div><?php endif; ?>
</div>
