<?php
/**
 * Banco de talentos (rota admin/pages/talentos.php): completo no plano Premium, parcial no básico.
 * Recebe de EmpresaController::talentos(): $talentos, $isPremium, $termo e $cidade.
 * No básico, a busca não considera o nome e o "Ver portfólio" abre só a prévia (PerfilController::portfolio).
 */
$acoesCab = $isPremium
    ? '<span class="badge-pro">Empresa Premium · acesso completo</span>'
    : '<a href="'.e(url('planos.php')).'" class="btn btn-gold btn-sm">Desbloquear acesso completo</a>';
?>
<div class="pn">
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?=painel_cabecalho('Banco de Talentos do DF', 'Encontre candidatos para as suas vagas pela busca por cargo, habilidades e experiências.', $acoesCab)?>

<?php if (!$isPremium): ?>
    <section class="pn-oferta" aria-labelledby="oferta-titulo">
        <div>
            <span class="badge-pro">Recurso exclusivo Empresa Premium</span>
            <h2 id="oferta-titulo">Pesquise e contrate os melhores talentos diretamente</h2>
            <p>Com o <strong>Plano Empresa Premium</strong>, sua empresa vê perfis completos, dados de contato e baixa os currículos dos candidatos do Distrito Federal.</p>
        </div>
        <a href="<?=url('planos.php')?>" class="btn">Assinar Empresa Premium (R$ 49,90/mês)</a>
    </section>
<?php endif; ?>

<form class="filtros" method="get" style="grid-template-columns:2fr 1fr 1fr auto">
    <input name="q" placeholder="Cargo, habilidade ou competência (ex.: Excel, Vendas, TI)" value="<?=e($termo)?>" aria-label="Buscar por cargo, habilidade ou competência">
    <input name="cidade" placeholder="Cidade (ex.: Taguatinga)" value="<?=e($cidade)?>" aria-label="Cidade">
    <select name="ordem" aria-label="Ordenar por"><?php foreach ($ordensTal as $k => $r): ?><option value="<?=$k?>" <?=$ordem === $k ? 'selected' : ''?>>Ordem: <?=e($r)?></option><?php endforeach; ?></select>
    <button class="btn">Pesquisar</button>
</form>
<?php if (!$isPremium): ?>
    <p class="small muted">No plano básico, a busca considera cargo, habilidades, experiências e cursos (não o nome do candidato).</p>
<?php endif; ?>
<div class="pn-contagem"><h2>Talentos</h2><span><?=gf_num($totalTalentos)?> <?=gf_plural($totalTalentos, 'candidato com perfil público', 'candidatos com perfil público')?><?=$paginas > 1 ? ' · página '.$pagina.' de '.$paginas : ''?></span></div>

<?php if (!$talentos): ?>
    <div class="empty">Nenhum talento encontrado com os filtros informados.</div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($talentos as $t): $nomeExibido = $isPremium ? $t['nome'] : mb_substr((string)$t['nome'], 0, 4).'*** (Candidato)'; ?>
            <article class="pn-talento<?=$t['is_vip'] ? ' vip' : ''?>">
                <div class="pn-talento-topo">
                    <div>
                        <?php if ($t['is_vip']): ?><span class="badge-vip">Candidato VIP</span> <?php endif; ?>
                        <span class="tag"><?=e($t['nivel_experiencia'] ? rotulo((string)$t['nivel_experiencia']) : 'Candidato')?></span>
                    </div>
                    <span class="meta"><?=e($t['cidade'] ?: 'Brasília')?>/<?=e($t['uf'] ?: 'DF')?></span>
                </div>
                <h3><?=e($nomeExibido)?></h3>
                <p class="pn-talento-cargo"><?=e($t['titulo_profissional'] ?: 'Profissional em busca de oportunidades')?></p>
                <p class="pn-talento-bio"><?=e(mb_strimwidth((string)($t['bio'] ?: $t['objetivo']), 0, 110, '…'))?></p>
                <?php if (!empty($t['habilidades'])): ?><p class="pn-talento-hab"><b>Habilidades:</b> <?=e(mb_strimwidth((string)$t['habilidades'], 0, 80, '…'))?></p><?php endif; ?>
                <a class="btn btn-sm btn-outline" target="_blank" rel="noopener" href="<?=url('view/perfil/portfolio.php?id='.(int)$t['id'])?>"><?=$isPremium ? 'Ver portfólio' : 'Ver prévia do portfólio'?><span class="sr-only"> de <?=e($nomeExibido)?> (abre em nova aba)</span></a>
                <div class="pn-talento-rodape">
                    <?php if ($isPremium): ?>
                        <small class="muted"><?=e($t['telefone'] ?: $t['email'])?></small>
                        <?php if ($t['curriculo_id']): ?>
                            <a class="btn btn-sm btn-green" href="<?=url('download.php?id='.(int)$t['curriculo_id'])?>"><?=icone('formulario', 14)?> Ver currículo</a>
                        <?php else: ?>
                            <span class="meta">Sem currículo em anexo</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="meta pn-borrado" aria-hidden="true">(61) 99999-9999</span><span class="sr-only">Contato disponível no plano Premium.</span>
                        <a href="<?=url('planos.php')?>" class="btn btn-sm btn-outline">Liberar contato</a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?=painel_paginacao($pagina, $paginas)?>
</div>
