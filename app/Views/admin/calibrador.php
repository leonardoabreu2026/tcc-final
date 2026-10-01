<?php
/**
 * Calibrador das máquinas de extração (rota admin/pages/calibrador.php) — só administrador.
 * Recebe de CalibradorController::painel(): $termos (página atual), $todos, $totalTermos, $pagina, $paginas, $edit,
 * $filtroContexto, $busca, $ordem, $dir, $areas, $maquinas, $teste, $nomesEmpresas e $nomesInstituicoes.
 */
$destinoAtual = $edit ? $edit['contexto'].'|'.$edit['destino'] : '';
$opcoesDestino = [
    'Vaga — a linha do anúncio vai para o campo' => array_combine(array_map(fn($k) => 'vaga_linha|'.$k, array_keys(Calibrador::CAMPOS_VAGA)), Calibrador::CAMPOS_VAGA),
    'Vaga — área (categoria)' => array_combine(array_map(fn($n) => 'vaga_categoria|'.$n, $areas['vaga']), $areas['vaga']) ?: [],
    'Curso / e-book — área (categoria)' => array_combine(array_map(fn($n) => 'curso_categoria|'.$n, $areas['curso']), $areas['curso']) ?: [],
    'Currículo — a linha solta vai para a seção' => array_combine(array_map(fn($k) => 'curriculo_linha|'.$k, array_keys(Calibrador::SECOES_CURRICULO)), Calibrador::SECOES_CURRICULO),
];
$porContexto = array_count_values(array_column($todos, 'contexto'));
$rotuloCampo = ['linha' => 'Linha', 'categoria' => 'Área', 'anunciante' => 'Empresa', 'instituicao' => 'Instituição'];
$abasContexto = ['vaga_linha' => 'Campos da vaga', 'vaga_categoria' => 'Área da vaga', 'curso_categoria' => 'Área do curso', 'curriculo_linha' => 'Seções do currículo'];
// Rótulo de um destino num ajuste do teste: linha = campo da vaga ou seção do currículo; o resto já vem com o nome.
$rotuloAjuste = fn(array $a, string $valor) => Calibrador::rotuloDestino($a['campo'] === 'linha' ? (($teste['maquina'] ?? '') === 'curriculo' ? 'curriculo_linha' : 'vaga_linha') : '', $valor);
?>
<div class="pn">
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?=painel_cabecalho('Calibrador', 'As máquinas de extração (cartaz, anúncio, curso e currículo) funcionam por regras. Aqui você ajusta essas regras sem mexer no código: cadastre um termo e diga para onde ele vai.')?>

<ol class="cal-passos" aria-label="Como o calibrador funciona">
    <li><b>1. A regra lê</b>A máquina separa as linhas do texto e dá um palpite pelas palavras-chave do código.</li>
    <li><b>2. O termo calibrado decide</b>Se a linha tiver um termo cadastrado aqui, ele decide o campo ou a área. Se dois termos servirem, vale o mais longo.</li>
    <li><b>3. Os nomes se atualizam sozinhos</b>Empresas e instituições já cadastradas no sistema são reconhecidas automaticamente.</li>
    <li><b>4. Você revisa</b>Nada é salvo sem revisão. O relatório da extração mostra o que o calibrador ajustou.</li>
</ol>

<div class="form">
    <h2 class="pn-form-titulo" id="form-calibrador"><?=$edit ? 'Editar termo' : 'Novo termo'?></h2>
    <form method="post">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?=(int)($edit['id'] ?? 0)?>">
        <div class="form-grid">
            <div><label for="cal-termo">Termo (palavra ou expressão)</label><input id="cal-termo" name="termo" required maxlength="120" placeholder="Ex.: uniforme, churrasqueiro, ensino médio" value="<?=e($edit['termo'] ?? '')?>">
                <p class="small muted">Não importa maiúscula nem acento. Vale a palavra inteira: "vale" não pega "valeu".</p></div>
            <div><label for="cal-destino">Para onde vai</label>
                <select id="cal-destino" name="destino" required>
                    <option value="">Escolha…</option>
                    <?php foreach ($opcoesDestino as $grupo => $ops): if (!$ops) continue; ?>
                    <optgroup label="<?=e($grupo)?>"><?php foreach ($ops as $valor => $rot): ?><option value="<?=e($valor)?>" <?=$destinoAtual === $valor ? 'selected' : ''?>><?=e($rot)?></option><?php endforeach; ?></optgroup>
                    <?php endforeach; ?>
                </select></div>
            <div><label for="cal-ativo">Ativo</label><select id="cal-ativo" name="ativo"><option value="1">Sim</option><option value="0" <?=$edit && !(int)$edit['ativo'] ? 'selected' : ''?>>Não (guardado, sem uso)</option></select></div>
        </div>
        <div class="form-actions"><button class="btn">Salvar termo</button><?php if ($edit): ?><a class="btn btn-outline" href="<?=url('admin/pages/calibrador.php')?>">Cancelar</a><?php endif; ?></div>
    </form>
</div>

<section class="pn-cartao cal-teste" id="teste-calibrador" aria-labelledby="cal-teste-titulo">
    <div class="pn-cartao-cab"><div><h2 id="cal-teste-titulo">Testar as máquinas</h2><p class="pn-cartao-sub">Cole um texto e veja o que a extração faz com as regras e os termos de agora. Nada é salvo.</p></div></div>
    <form method="post" action="#teste-calibrador" class="cal-teste-form">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="testar">
        <div><label for="cal-maquina">Máquina</label><select id="cal-maquina" name="maquina"><?php foreach ($maquinas as $k => $rot): ?><option value="<?=$k?>" <?=($teste['maquina'] ?? 'vaga') === $k ? 'selected' : ''?>><?=e($rot)?></option><?php endforeach; ?></select></div>
        <div><label for="cal-texto">Texto</label><textarea id="cal-texto" name="texto_teste" required maxlength="8000" placeholder="Ex.: Auxiliar de cozinha — Águas Claras&#10;Salário R$ 1.900 + VT&#10;Uniforme fornecido"><?=e($teste['texto'] ?? '')?></textarea></div>
        <div><button class="btn">Testar</button></div>
    </form>
    <?php if ($teste): ?>
    <div class="cal-resultado" role="status">
        <dl class="cal-campos">
            <?php foreach ($teste['campos'] as [$rot, $valor]): ?><dt><?=e($rot)?></dt><dd><?=$valor !== '' ? e($valor) : '<span class="muted">—</span>'?></dd><?php endforeach; ?>
        </dl>
        <h3>Ajustes do calibrador</h3>
        <?php if ($teste['ajustes']): ?>
        <ul class="cal-ajustes">
            <?php foreach ($teste['ajustes'] as $a): ?>
            <li><span class="cal-campo"><?=e($rotuloCampo[$a['campo']] ?? $a['campo'])?></span> “<?=e(mb_strimwidth((string)$a['texto'], 0, 90, '…'))?>”
                → <b><?=e($rotuloAjuste($a, (string)$a['para']))?></b>
                <span class="muted">(<?=$a['regra'] !== '' ? 'a regra dizia '.e($rotuloAjuste($a, (string)$a['regra'])).'; ' : ''?>termo: <?=e((string)$a['termo'])?>)</span></li>
            <?php endforeach; ?>
        </ul>
        <?php if ($teste['maquina'] === 'curriculo'): ?><p class="small muted">No currículo, o termo vale para as linhas soltas do começo (antes do primeiro título de seção). Linhas que já estão numa seção ficam onde estão.</p><?php endif; ?>
        <?php else: ?>
        <p class="muted">Nenhum termo calibrado mudou este texto: tudo veio das regras.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>

<div class="pn-contagem" id="lista-calibrador"><h2>Termos calibrados</h2><span><?=gf_num($totalTermos)?> <?=gf_plural($totalTermos, 'termo', 'termos')?><?=$paginas > 1 ? ' · página '.$pagina.' de '.$paginas : ''?><?=$filtroContexto !== '' || $busca !== '' ? ' · <a href="'.e(url('admin/pages/calibrador.php')).'#lista-calibrador">limpar filtros</a>' : ''?></span></div>
<?=painel_subabas('contexto', $filtroContexto, ['' => ['Todos', count($todos)]] + array_map(fn($k) => [$abasContexto[$k], $porContexto[$k] ?? 0], array_combine(array_keys($abasContexto), array_keys($abasContexto))), 'Onde o termo age')?>
<form class="filtros" method="get" action="#lista-calibrador" style="grid-template-columns:2fr auto">
    <?php if ($filtroContexto !== ''): ?><input type="hidden" name="contexto" value="<?=e($filtroContexto)?>"><?php endif; ?>
    <?php if (get_str('ordem') !== ''): ?><input type="hidden" name="ordem" value="<?=e($ordem)?>"><input type="hidden" name="dir" value="<?=e($dir)?>"><?php endif; ?>
    <input name="q" placeholder="Buscar pelo termo ou pelo destino" value="<?=e($busca)?>" aria-label="Buscar pelo termo ou pelo destino">
    <button class="btn">Filtrar</button>
</form>
<?php if ($termos): ?>
<div class="table-wrap"><table class="table">
    <tr><?=painel_th('termo', 'Termo', $ordem, $dir)?><?=painel_th('contexto', 'Onde age', $ordem, $dir)?><?=painel_th('destino', 'Vai para', $ordem, $dir)?><?=painel_th('ativo', 'Situação', $ordem, $dir)?><th>Ações</th></tr>
    <?php foreach ($termos as $t): ?>
    <tr>
        <td><b><?=e($t['termo'])?></b></td>
        <td><?=e(Calibrador::CONTEXTOS[$t['contexto']] ?? $t['contexto'])?></td>
        <td><?=e(Calibrador::rotuloDestino((string)$t['contexto'], (string)$t['destino']))?></td>
        <td><?=painel_chave((bool)$t['ativo'], 'ativar', 'desativar', (int)$t['id'], 'Ativo', 'Inativo', '', (string)$t['termo'])?></td>
        <td><?=painel_botoes([
            ['href' => painel_qs(['edit' => (int)$t['id']]).'#form-calibrador', 'texto' => 'Editar', 'icone' => 'editar'],
            ['acao' => 'excluir', 'id' => (int)$t['id'], 'texto' => 'Excluir', 'icone' => 'lixeira', 'estilo' => 'perigo', 'confirmar' => 'Excluir o termo "'.$t['termo'].'"? A extração volta a seguir só a regra nesse caso.'],
        ], (string)$t['termo'])?></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?=painel_paginacao($pagina, $paginas)?>
<?php else: ?>
<div class="empty"><?=$filtroContexto !== '' || $busca !== '' ? 'Nenhum termo com esses filtros.' : 'Nenhum termo calibrado ainda: a extração está usando só as regras do código.'?></div>
<?php endif; ?>

<section class="pn-cartao cal-nomes" aria-labelledby="cal-nomes-titulo">
    <div class="pn-cartao-cab"><div><h2 id="cal-nomes-titulo">Nomes conhecidos (atualizados sozinhos)</h2><p class="pn-cartao-sub">Quando nenhuma regra acha a empresa ou a instituição no texto, a extração procura estes nomes. Toda vaga ou curso salvo entra aqui na hora: não precisa fazer nada.</p></div></div>
    <div class="cal-nomes-grade">
        <?php foreach (['Empresas' => $nomesEmpresas, 'Instituições de ensino' => $nomesInstituicoes] as $rot => $lista): ?>
        <div>
            <h3><?=e($rot)?> <span class="cal-cont"><?=count($lista)?></span></h3>
            <?php if ($lista): ?>
                <?php $ordenados = $lista; natcasesort($ordenados); foreach (array_slice($ordenados, 0, 80) as $nome): ?><span class="cal-chip"><?=e($nome)?></span><?php endforeach; ?>
                <?php if (count($lista) > 80): ?><span class="muted small">e mais <?=count($lista) - 80?></span><?php endif; ?>
            <?php else: ?><p class="muted">Nenhum ainda.</p><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>
</div>
