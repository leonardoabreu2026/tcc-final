<?php
/**
 * CRUD de cursos, e-books e vídeos (rota admin/pages/cursos.php) — só administrador.
 * Recebe de AdminController::cursos(): $form, $extraido, $cats, $lista (página atual), $totalLista, $pagina, $paginas,
 * $porTipo, $filtroTipo, $filtroCat, $filtroSituacao, $busca, $ordem, $dir, $comImagemPadrao, $imagens
 * e $importacao (várias fichas lidas, aguardando confirmação).
 * Tela enxuta: UMA caixa de extração (ficha da IA ou texto de divulgação) → formulário ou prévia; depois, a lista.
 */
$novoRotulo = ['curso' => 'Novo curso', 'ebook' => 'Novo e-book', 'video' => 'Novo vídeo'];
$botoesNovo = '';
foreach ($novoRotulo as $t => $r) $botoesNovo .= '<a class="btn btn-sm'.($t === 'curso' ? '' : ' btn-outline').'" href="'.e(url('admin/pages/cursos.php').painel_qs(['novo' => $t])).'#form-curso">+ '.e($r).'</a>';
$modeloFicha = "Título:\nTipo:\nInstituição:\nModalidade:\nCidade:\nNível:\nCarga horária:\nGratuito:\nPreço:\nÁrea:\nLink: https://...\nPDF: https://... (e-book gratuito: link direto do arquivo)\nImagem: https://...\nDescrição:";
?>
<div class="pn">
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?=painel_cabecalho('Cursos e e-books', 'Cole a ficha da pesquisa (uma ou várias) ou o texto de divulgação: o sistema preenche o cadastro. Sem imagem, entra com a imagem padrão e você troca depois.', $botoesNovo)?>
<div class="form" style="max-width:none">
    <section class="cx-extrair" id="extrair" aria-labelledby="cx-extrair-tit">
        <h2 class="pn-form-titulo" id="cx-extrair-tit">Extrair</h2>
        <form method="post" data-carregando="Extraindo a ficha e conferindo o link e a imagem…">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="extrair"><input type="hidden" name="id" value="<?=(int)($form['id'] ?? 0)?>">
            <label for="c-texto" class="sr-only">Ficha da pesquisa ou texto de divulgação</label>
            <textarea id="c-texto" name="texto_anuncio" required rows="6" placeholder="<?=e("Cole aqui a ficha (uma ou várias, separadas por ---) ou o texto de divulgação:\n\n".$modeloFicha)?>"><?=e(post_str('texto_anuncio'))?></textarea>
            <div class="form-actions"><button class="btn">Extrair</button><span class="meta">1 ficha ou texto → preenche o formulário abaixo · várias fichas → prévia para cadastrar de uma vez</span></div>
        </form>
        <?php if ($extraido): ?>
            <?=carregador_html('Pronto: dados extraídos — revise abaixo e clique em "Salvar conteúdo".'.match (true) {
                ($extraido['imagem_origem'] ?? '') === 'capa_pdf' => ' Capa tirada da 1ª página do PDF do e-book.',
                ($extraido['imagem_origem'] ?? '') === 'pagina' => ' Imagem achada na página do conteúdo: confira a prévia.',
                ($form['imagem_url'] ?? '') === '' && ($form['imagem'] ?? '') === '' => ' Sem imagem na ficha: vai entrar com a imagem padrão (troque depois).',
                default => '',
            }, true)?>
            <?php if ($extraido['avisos_links'] ?? []): ?><ul class="ex-avisos"><?php foreach ($extraido['avisos_links'] as $a): ?><li><?=e(ucfirst($a))?>.</li><?php endforeach; ?></ul><?php endif; ?>
        <?php endif; ?>
        <?php if ($importacao): ?>
        <form method="post" class="imp-previa" style="margin-top:12px" data-carregando="Cadastrando os conteúdos e baixando as imagens e os PDFs…">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="importar_salvar">
            <div class="table-wrap"><table class="table">
                <tr><th><span class="sr-only">Importar</span></th><th>Imagem</th><th>Título</th><th>Tipo</th><th>Instituição</th><th>Modalidade</th><th>Área</th><th>Link</th><th>Situação</th></tr>
                <?php foreach ($importacao as $i => $it): $ruim = !empty($it['problemas']); $miniatura = ($it['imagem_url'] ?? '') ?: ($it['imagem'] ? url($it['imagem']) : ''); ?>
                <tr>
                    <td><input type="checkbox" name="itens[]" value="<?=(int)$i?>" id="imp-<?=(int)$i?>" <?=$ruim ? 'disabled' : 'checked'?> aria-label="Importar <?=e($it['titulo'])?>"></td>
                    <td><?=$miniatura !== '' ? '<img class="imp-miniatura" src="'.e($miniatura).'" alt="" loading="lazy" referrerpolicy="no-referrer">' : '<span class="meta">—</span>'?></td>
                    <td><label for="imp-<?=(int)$i?>"><?=e($it['titulo'] ?: '—')?></label><?php if ($it['duracao'] !== ''): ?><br><small class="meta"><?=e($it['duracao'])?> · <?=e(rotulo($it['nivel']))?></small><?php endif; ?></td>
                    <td><?=e(rotulo($it['tipo']))?></td>
                    <td><?=e($it['instituicao'] ?: '—')?></td>
                    <td><?=e(rotulo($it['modalidade']))?><?=$it['gratuito'] ? '' : '<br><small class="meta">pago</small>'?></td>
                    <td><?=e($it['categoria'] ?: 'Sem categoria')?></td>
                    <td class="meta"><?=$it['url'] !== '' ? '<a href="'.e($it['url']).'" target="_blank" rel="noopener">'.e(parse_url($it['url'], PHP_URL_HOST) ?: $it['url']).'<span class="sr-only"> (abre em nova aba)</span></a>' : '—'?><?=$it['tipo'] === 'ebook' && (($it['pdf_url'] ?? '') !== '' || preg_match('/\.pdf($|[?#])/i', $it['url'])) ? '<br><small>PDF → biblioteca</small>' : ''?></td>
                    <td><?=$ruim ? painel_status('bloqueado', ucfirst(implode(', ', $it['problemas']))) : ($it['alerta'] !== '' ? painel_status('pausada', ucfirst($it['alerta'])) : painel_status('ativa', 'Pronto'))?></td>
                </tr>
                <?php endforeach; ?>
            </table></div>
            <?php if (array_filter($importacao, fn($it) => $it['tipo'] === 'ebook')): ?><div class="check"><input type="checkbox" name="pdfs_biblioteca" value="1" id="imp-pdfs" checked><label for="imp-pdfs">Guardar na nossa biblioteca o PDF dos e-books (o botão vira "Baixar")</label></div><?php endif; ?>
            <div class="form-actions"><button class="btn">Cadastrar marcados</button>
                <button class="btn btn-outline" name="acao" value="importar_cancelar" formnovalidate>Descartar prévia</button></div>
        </form>
        <?php endif; ?>
    </section>

    <h2 class="pn-form-titulo" id="form-curso"><?=!empty($form['id']) ? 'Editar '.e(mb_strtolower(rotulo((string)$form['tipo']))).' #'.(int)$form['id'] : e($novoRotulo[$form['tipo']] ?? 'Novo conteúdo')?></h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?=(int)($form['id'] ?? 0)?>">
        <div class="form-grid">
            <div><label for="c-titulo">Título</label><input id="c-titulo" name="titulo" required maxlength="255" value="<?=e($form['titulo'])?>"></div>
            <div><label for="c-inst">Instituição</label><input id="c-inst" name="instituicao" maxlength="255" value="<?=e($form['instituicao'])?>"></div>
            <div><label for="c-cat">Categoria</label><select id="c-cat" name="categoria_id"><option value="">Sem categoria</option><?php foreach ($cats as $c): ?><option value="<?=(int)$c['id']?>" <?=(int)($form['categoria_id'] ?? 0) === (int)$c['id'] ? 'selected' : ''?>><?=e($c['nome'])?><?=$c['ativo'] ? '' : ' (inativa)'?></option><?php endforeach; ?></select></div>
            <div><label for="c-tipo">Formato</label><select id="c-tipo" name="tipo"><?php foreach (CursoDAO::TIPOS as $x): ?><option value="<?=$x?>" <?=$form['tipo'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label for="c-mod">Modalidade</label><select id="c-mod" name="modalidade"><?php foreach (CursoDAO::MODALIDADES as $x): ?><option value="<?=$x?>" <?=$form['modalidade'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label for="c-nivel">Nível</label><select id="c-nivel" name="nivel"><?php foreach (CursoDAO::NIVEIS as $x): ?><option value="<?=$x?>" <?=$form['nivel'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label for="c-dur">Duração / carga horária</label><input id="c-dur" name="duracao" maxlength="50" value="<?=e($form['duracao'])?>" placeholder="Ex.: 12 horas"></div>
            <div><label for="c-preco">Preço (se pago)</label><input id="c-preco" name="preco" inputmode="decimal" value="<?=e($form['preco'] !== null && $form['preco'] !== '' ? number_format((float)$form['preco'], 2, ',', '.') : '')?>" placeholder="Ex.: 49,90"></div>
            <div class="full"><label for="c-url">Link oficial (página ou PDF na web → botão "Acessar")</label><input id="c-url" name="url" type="text" inputmode="url" maxlength="500" value="<?=e($form['url'])?>" placeholder="https://"></div>
            <?php $naBiblioteca = eh_pdf_biblioteca((string)$form['url']); ?>
            <div class="full"><label for="c-pdf-url">Link direto do PDF (opcional: se ficar vazio, o PDF é procurado no link oficial)</label><input id="c-pdf-url" name="pdf_url" type="url" maxlength="500" value="<?=e((string)($form['pdf_url'] ?? ''))?>" placeholder="https://.../ebook.pdf">
                <div class="check" style="margin-top:8px"><input type="checkbox" name="pdf_biblioteca" value="1" id="pdf-bib" <?=!$naBiblioteca && $form['tipo'] === 'ebook' ? 'checked' : ''?>><label for="pdf-bib">Guardar o PDF na nossa biblioteca ao salvar: o sistema baixa o PDF (do link direto ou achado na página) e o botão vira "Baixar". Use com e-books gratuitos.</label></div></div>
            <div class="full"><label for="c-pdf">…ou envie o PDF para a nossa biblioteca (o botão vira "Baixar"; até <?=(int)(MAX_PDF_BIBLIOTECA / 1024 / 1024)?> MB)</label>
                <input id="c-pdf" type="file" name="arquivo_pdf" accept="application/pdf">
                <?php if (eh_pdf_biblioteca((string)$form['url'])): ?><small class="meta">Este conteúdo já está na biblioteca: <a href="<?=e(url($form['url']))?>" target="_blank" rel="noopener">abrir o PDF<span class="sr-only"> (abre em nova aba)</span></a>. Enviar outro substitui.</small><?php endif; ?></div>
            <div><label for="c-img">Imagem (caminho)</label><input id="c-img" name="imagem" list="imgs-curso" maxlength="255" value="<?=e($form['imagem'])?>"><datalist id="imgs-curso"><?php foreach ($imagens as $i): ?><option value="<?=e($i)?>"><?php endforeach; ?></datalist></div>
            <div><label for="c-arq">…ou envie uma imagem</label><input id="c-arq" type="file" name="imagem_arquivo" accept="image/jpeg,image/png,image/webp"></div>
            <div class="full"><label for="c-img-url">…ou cole o link da imagem (é baixada ao salvar). Sem imagem, ou se o link não baixar, entra a imagem padrão da plataforma.</label>
                <div class="pm-img-link"><?php if (url_http_valida((string)($form['imagem_url'] ?? ''))): ?><img class="imp-miniatura" src="<?=e($form['imagem_url'])?>" alt="Prévia da imagem do link" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
                <input id="c-img-url" name="imagem_url" type="url" maxlength="500" value="<?=e((string)($form['imagem_url'] ?? ''))?>" placeholder="https://.../capa.jpg"></div></div>
            <div class="full"><label for="c-desc">Descrição</label><textarea id="c-desc" name="descricao" rows="4"><?=e($form['descricao'])?></textarea></div>
        </div>
        <div class="check"><input type="checkbox" name="gratuito" value="1" id="gratuito" <?=(int)$form['gratuito'] ? 'checked' : ''?>><label for="gratuito">Gratuito</label></div>
        <div class="check"><input type="checkbox" name="ativo" value="1" id="ativo" <?=(int)$form['ativo'] ? 'checked' : ''?>><label for="ativo">Publicado (aparece para os usuários)</label></div>
        <div class="form-actions"><button class="btn">Salvar conteúdo</button><?php if (!empty($form['id']) || $extraido): ?><a class="btn btn-outline" href="<?=e(url('admin/pages/cursos.php').painel_qs())?>">Cancelar</a><?php endif; ?></div>
    </form>
</div>

<div class="pn-contagem" id="lista-cursos"><h2>Conteúdos cadastrados</h2><span><?=gf_num($totalLista)?> <?=gf_plural($totalLista, 'conteúdo', 'conteúdos')?><?=$paginas > 1 ? ' · página '.$pagina.' de '.$paginas : ''?><?=$filtroTipo !== '' || $comFiltro ? ' · <a href="'.e(url('admin/pages/cursos.php')).'#lista-cursos">limpar filtros</a>' : ''?></span></div>
<?php if ($ebooksNaWeb): ?>
<form method="post" class="pn-biblioteca" data-carregando="Baixando os PDFs dos e-books para a biblioteca… (pode levar alguns minutos)">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="pdfs_biblioteca">
    <p><b><?=gf_num($ebooksNaWeb)?> <?=gf_plural($ebooksNaWeb, 'e-book', 'e-books')?></b> ainda <?=$ebooksNaWeb === 1 ? 'abre' : 'abrem'?> o PDF no site de origem (botão "Acessar"). Traga para a nossa biblioteca e o botão vira "Baixar".</p>
    <div class="form-actions"><button class="btn btn-sm">Trazer os PDFs para a biblioteca</button></div>
</form>
<?php endif; ?>
<?=painel_subabas('tipo', $filtroTipo, ['' => ['Todos', array_sum($porTipo)], 'curso' => ['Cursos', $porTipo['curso'] ?? 0], 'ebook' => ['E-books', $porTipo['ebook'] ?? 0], 'video' => ['Vídeos', $porTipo['video'] ?? 0]], 'Formato')?>
<form class="filtros" method="get" action="#lista-cursos" style="grid-template-columns:2fr 1fr 1fr auto">
    <?php if ($filtroTipo !== ''): ?><input type="hidden" name="tipo" value="<?=e($filtroTipo)?>"><?php endif; ?>
    <?php if (get_str('ordem') !== ''): ?><input type="hidden" name="ordem" value="<?=e($ordem)?>"><input type="hidden" name="dir" value="<?=e($dir)?>"><?php endif; ?>
    <input name="q" placeholder="Buscar por título, descrição ou instituição" value="<?=e($busca)?>" aria-label="Buscar por título, descrição ou instituição">
    <select name="categoria_id" aria-label="Área"><option value="">Todas as áreas</option><?php foreach ($cats as $c): ?><option value="<?=(int)$c['id']?>" <?=$filtroCat === (int)$c['id'] ? 'selected' : ''?>><?=e($c['nome'])?></option><?php endforeach; ?></select>
    <select name="situacao" aria-label="Situação"><option value="">Publicados e ocultos</option><option value="publicado" <?=$filtroSituacao === 'publicado' ? 'selected' : ''?>>Só publicados</option><option value="oculto" <?=$filtroSituacao === 'oculto' ? 'selected' : ''?>>Só ocultos</option><option value="imagem_padrao" <?=$filtroSituacao === 'imagem_padrao' ? 'selected' : ''?>>Com imagem padrão (<?=(int)$comImagemPadrao?>)</option></select>
    <button class="btn">Filtrar</button>
</form>
<div class="table-wrap"><table class="table">
    <tr><th><span class="sr-only">Imagem</span></th><?=painel_th('titulo', 'Título', $ordem, $dir)?><?=painel_th('tipo', 'Formato', $ordem, $dir)?><?=painel_th('categoria_nome', 'Área', $ordem, $dir)?><?=painel_th('instituicao', 'Instituição', $ordem, $dir)?><?=painel_th('ativo', 'Situação', $ordem, $dir)?><?=painel_th('created_at', 'Cadastro', $ordem, $dir)?><th>Ações</th></tr>
    <?php foreach ($lista as $x): ?>
    <tr>
        <td class="pn-td-img"><?=painel_miniatura((string)$x['imagem'], $x['tipo'] === 'ebook' ? 'ebook' : '', pt_secao_formato((string)$x['tipo'])[2])?></td>
        <td class="quebra"><?=e($x['titulo'])?><br><small class="meta">#<?=(int)$x['id']?><?=$x['duracao'] ? ' · '.e($x['duracao']) : ''?> · <?=e(pt_preco($x))?></small></td>
        <td><?=e(rotulo($x['tipo']))?><?php if ($x['tipo'] === 'ebook'): ?><br><small class="meta"><?=eh_pdf_biblioteca((string)$x['url']) ? 'PDF na biblioteca' : 'link da web'?></small><?php endif; ?></td><td><?=e($x['categoria_nome'] ?? '—')?></td><td class="quebra"><?=e($x['instituicao'] ?? '')?></td>
        <td><?=painel_chave((bool)$x['ativo'], 'ativar', 'desativar', (int)$x['id'], 'Publicado', 'Oculto', '', (string)$x['titulo'])?><?php if (CursoDAO::ehImagemPadrao((string)$x['imagem'])): ?><br><a class="pn-trocar-img" href="<?=e(painel_qs(['edit' => (int)$x['id']]))?>#form-curso">trocar imagem</a><?php endif; ?></td>
        <td class="meta"><?=$x['created_at'] ? date('d/m/Y', strtotime((string)$x['created_at'])) : '—'?></td>
        <td><?=painel_botoes([
            ['href' => url('curso.php?id='.(int)$x['id']), 'texto' => 'Ver', 'icone' => 'olho', 'estilo' => 'primario', 'nova_aba' => true],
            ['href' => painel_qs(['edit' => (int)$x['id']]).'#form-curso', 'texto' => 'Editar', 'icone' => 'editar'],
            ['acao' => 'excluir', 'id' => (int)$x['id'], 'texto' => 'Excluir', 'icone' => 'lixeira', 'estilo' => 'perigo', 'confirmar' => 'Excluir este conteúdo? Esta ação não pode ser desfeita.'],
        ], (string)$x['titulo'])?></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?=painel_paginacao($pagina, $paginas)?>
<?php if (!$lista): ?><div class="empty"><?=$filtroTipo !== '' || $comFiltro ? 'Nenhum conteúdo com esses filtros.' : 'Nenhum curso ou e-book cadastrado ainda. Use a extração acima para publicar o primeiro.'?></div><?php endif; ?>
</div>
