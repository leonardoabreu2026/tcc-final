<?php
/**
 * Portfólio profissional (rota view/perfil/portfolio.php[?id=]) montado com os dados do perfil;
 * para o dono, também a máquina de match com as vagas.
 * Recebe de PerfilController::portfolio(): $p, $ehDono, $verContato, $previa, $nomeExibido, $resumo, $experiencias,
 * $formacao, $habilidades, $cursos, $idiomas, $extras, $links, $ehPcd, $local, $iniciais, $titulo, $relatorio,
 * $matches, $exibirMatches, $cursosAtivos, $isVip, $limiteGratis e $cands (candidaturas do dono).
 * Prévia ($previa, sem Premium): nome mascarado, título, local, habilidades e o resumo curto, como no Banco de Talentos.
 * O portfólio é uma folha A4 (.pf-folha): o que aparece na tela é o que sai no "Salvar em PDF" (impressão em A4).
 */
?>
<div class="pf">
  <div class="pf-acoes">
    <?php if ($ehDono): ?>
      <button class="btn btn-sm btn-outline" type="button" aria-controls="pf-candidaturas" aria-expanded="false"
        onclick="const s = document.getElementById('pf-candidaturas'); s.hidden = !s.hidden; this.setAttribute('aria-expanded', String(!s.hidden)); if (!s.hidden) s.scrollIntoView({behavior: 'smooth', block: 'nearest'});"><?=icone('vagas', 16)?>Visualizar candidaturas (<?=count($cands)?>)</button>
      <a class="btn btn-sm btn-outline" href="<?=url('view/perfil/index.php')?>"><?=icone('editar', 16)?>Editar dados</a>
    <?php endif; ?>
    <button class="btn btn-sm" type="button" onclick="window.print()"><?=icone('download', 16)?>Salvar em PDF</button>
  </div>

  <?php if ($ehDono): ?>
  <!-- Candidaturas do dono: abre pelo botão "Visualizar candidaturas"; fica fora da folha (não sai no PDF). -->
  <section class="pf-cands" id="pf-candidaturas" hidden>
    <h2><?=icone('vagas', 18)?>Minhas candidaturas</h2>
    <?php if (!$cands): ?>
      <p class="muted">Você ainda não se candidatou a nenhuma vaga. <a href="<?=url('vagas.php')?>">Ver vagas abertas</a></p>
    <?php else: ?>
      <ul class="pf-cands-lista">
        <?php foreach ($cands as $c): ?>
          <li>
            <div class="pf-cands-topo">
              <div>
                <a class="pf-cands-vaga" href="<?=url('vaga.php?id='.(int)$c['vaga_id'])?>"><?=e($c['titulo'])?></a>
                <div class="pf-cands-meta"><?=e($c['empresa_nome'] ?? 'Empresa')?> · <?=e(trim(($c['cidade'] ?? '').'/'.($c['uf'] ?? ''), '/'))?> · enviada em <?=date('d/m/Y', strtotime((string)$c['data_candidatura']))?></div>
              </div>
              <span class="tag pf-cands-status <?=e($c['status'])?>"><?=e(rotulo($c['status']))?></span>
            </div>
            <?php if (!empty($c['observacao_empresa'])): ?><p class="pf-cands-retorno"><b>Retorno da empresa:</b> <?=e($c['observacao_empresa'])?></p><?php endif; ?>
            <?php if (($c['vaga_status'] ?? '') === 'cancelada'): ?><p class="pf-cands-retorno">A empresa cancelou esta vaga. A sua candidatura continua guardada aqui.</p><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="pf-cands-rodape"><a href="<?=url('view/perfil/index.php#candidaturas')?>">Cancelar ou acompanhar no Meu perfil</a></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($relatorio) require __DIR__.'/../partials/relatorio_extracao.php'; ?>

  <div class="pf-mesa">
  <article class="pf-folha" aria-label="Portfólio de <?=e($nomeExibido)?>">
  <header class="pf-banner">
    <?php if (!$previa && !empty($p['foto'])): ?>
      <img class="pf-foto" src="<?=e(url($p['foto']))?>" alt="Foto de <?=e($nomeExibido)?>">
    <?php else: ?>
      <div class="pf-foto pf-foto-vazia" aria-hidden="true"><?=e($iniciais)?></div>
    <?php endif; ?>
    <div>
      <h1><?=e($nomeExibido)?><?=$previa ? ' (Candidato)' : ''?></h1>
      <?php if ($titulo !== ''): ?><p><?=e($titulo)?></p><?php endif; ?>
      <span class="pf-selo">Portfólio</span>
    </div>
  </header>

  <div class="pf-grade">
    <aside class="pf-col pf-lateral">
      <div class="pf-card">
        <h2><span class="pf-ic"><?=icone('usuario', 15)?></span>Contato</h2>
        <ul class="pf-contato">
          <?php if ($verContato): ?>
            <li><span class="pf-mini"><?=icone('email', 12)?></span><?=e($p['email'])?></li>
            <?php if (!empty($p['telefone'])): ?><li><span class="pf-mini"><?=icone('telefone', 12)?></span><?=e($p['telefone'])?></li><?php endif; ?>
          <?php else: ?>
            <li class="muted small"><span class="pf-mini"><?=icone('email', 12)?></span>Contato visível para empresas com candidatura recebida ou plano Premium.</li>
          <?php endif; ?>
          <?php if ($local !== ''): ?><li><span class="pf-mini"><?=icone('local', 12)?></span><?=e($local)?></li><?php endif; ?>
          <?php if ($verContato): foreach ($links as $lk): ?>
            <li class="pf-link"><span class="pf-mini" title="<?=e($lk['tipo'])?>"><?=e(mb_substr($lk['tipo'], 0, 2))?></span><a href="<?=e($lk['href'])?>" target="_blank" rel="noopener nofollow"><?=e($lk['texto'])?></a></li>
          <?php endforeach; endif; ?>
          <?php if ($verContato && !empty($p['pretensao_salarial'])): ?><li class="small"><span class="pf-mini">R$</span>Pretensão: <?=e($p['pretensao_salarial'])?></li><?php endif; ?>
        </ul>
      </div>

      <div class="pf-card">
        <h2><span class="pf-ic"><?=icone('engrenagem', 15)?></span>Habilidades</h2>
        <?php if ($habilidades): ?>
          <ul class="pf-lista"><?php foreach ($habilidades as $h): ?><li><?=e($h)?></li><?php endforeach; ?></ul>
        <?php else: ?><p class="pf-vazio">Nenhuma habilidade informada.</p><?php endif; ?>
      </div>

      <?php if ($idiomas && !$previa): ?>
      <div class="pf-card">
        <h2 class="pequeno"><span class="pf-ic"><?=icone('idiomas', 18)?></span>Idiomas</h2>
        <ul class="pf-lista"><?php foreach ($idiomas as $i): ?><li><?=e($i)?></li><?php endforeach; ?></ul>
      </div>
      <?php endif; ?>

      <?php if (!$previa && trim((string)($p['objetivo'] ?? '')) !== ''): ?>
      <div class="pf-card">
        <h2 class="pequeno"><span class="pf-ic"><?=icone('alvo', 18)?></span>Objetivo Profissional</h2>
        <p class="pf-texto"><?=e($p['objetivo'])?></p>
      </div>
      <?php endif; ?>
    </aside>

    <div class="pf-col">
      <div class="pf-card">
        <h2><span class="pf-ic"><?=icone('usuario', 15)?></span>Resumo Profissional</h2>
        <?php if ($resumo !== ''): ?>
          <p class="pf-texto"><?=e($resumo)?></p>
        <?php else: ?><p class="pf-vazio">Resumo ainda não informado.</p><?php endif; ?>
      </div>

      <?php if ($previa): ?>
      <!-- Prévia: o restante (foto, contato, experiências, formação e cursos) é do plano Premium. -->
      <div class="notice small pf-match-vip">
        <div><b>🔒 Prévia do portfólio.</b> Nome completo, foto, contato, experiências e formação ficam visíveis para empresas Premium.</div>
        <a href="<?=url('planos.php')?>" class="cv-btn cv-btn-dourado">Assine o Premium para ver completo</a>
      </div>
      <?php else: ?>
      <div class="pf-card">
        <h2><span class="pf-ic"><?=icone('maleta', 15)?></span>Experiência Profissional</h2>
        <?php if ($experiencias): ?>
          <ul class="pf-timeline">
            <?php foreach ($experiencias as $x): ?>
              <li>
                <div class="pf-exp-topo">
                  <span class="pf-empresa"><?=e($x['empresa'] !== '' ? $x['empresa'] : $x['cargo'])?></span>
                  <span class="pf-periodo"><?=e($x['periodo'])?></span>
                </div>
                <?php if ($x['empresa'] !== '' && $x['cargo'] !== ''): ?><div class="pf-cargo"><?=e($x['cargo'])?></div><?php endif; ?>
                <?php if ($x['descricao']): ?><p class="pf-desc"><?=e(implode(' ', $x['descricao']))?></p><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php elseif (trim((string)($p['experiencias'] ?? '')) !== ''): ?>
          <p class="pf-texto"><?=e($p['experiencias'])?></p>
        <?php else: ?><p class="pf-vazio">Nenhuma experiência informada.</p><?php endif; ?>
      </div>

      <div class="pf-card">
        <h2><span class="pf-ic"><?=icone('formatura', 15)?></span>Formação Acadêmica e Técnica</h2>
        <?php if ($formacao): ?>
          <div class="pf-form">
            <?php foreach ($formacao as $f): ?>
              <div>
                <b><?=e($f['curso'])?></b>
                <?php if ($f['instituicao'] !== ''): ?><span><?=e($f['instituicao'])?></span><?php endif; ?>
                <?php if ($f['situacao'] !== ''): ?><small><?=e($f['situacao'])?></small><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?><p class="pf-vazio">Nenhuma formação informada.</p><?php endif; ?>
      </div>

      <?php if ($cursos || $extras): ?>
      <div class="pf-card">
        <?php if ($cursos): ?>
          <h2><span class="pf-ic"><?=icone('livro', 15)?></span>Cursos Complementares</h2>
          <ul class="pf-lista pf-cursos"><?php foreach ($cursos as $c): ?><li><?=e($c)?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if ($extras): ?>
          <div class="pf-extra<?=$ehPcd ? '' : ' sem-pcd'?>">
            <?=icone($ehPcd ? 'acessibilidade' : 'livro', 22)?>
            <div><?php foreach ($extras as $x): ?><p><?=e($x)?></p><?php endforeach; ?></div>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  </article>
  </div>

  <?php if ($ehDono): ?>
  <!-- ===================== Máquina de match (só o dono vê) ===================== -->
  <section class="pf-card pf-match" id="match">
    <div class="pf-match-topo">
      <h2><span class="pf-ic"><?=icone('alvo')?></span>Máquina de match</h2>
      <form method="post" action="<?=url('view/perfil/recalcular_match.php')?>"><?=csrf_campo()?><button class="cv-btn cv-btn-linha"><?=icone('raio', 15)?>Recalcular match</button></form>
    </div>
    <p class="pf-match-sub">Seu portfólio comparado com cada vaga aberta: competências (50) + cargo/histórico (20) + localização (15) + nível (15).</p>
    <?php if (!$matches): ?>
      <p class="pf-vazio">Nenhuma vaga aberta para comparar no momento.</p>
    <?php else: ?>
      <div class="pf-match-lista">
      <?php foreach ($exibirMatches as $m): $d = $m['detalhes']; $falt = $d['competencias']['faltantes'] ?? []; $recom = Competencias::cursosPara($falt, $cursosAtivos, 2); ?>
        <article class="pf-match-item">
          <div class="pf-match-cab">
            <div>
              <a class="pf-match-tit" href="<?=url('vaga.php?id='.(int)$m['vaga_id'])?>"><?=e($m['titulo'])?></a>
              <div class="pf-match-meta"><?=e($m['empresa_nome'] ?? 'Empresa')?> · <?=e($m['cidade'])?>/<?=e($m['uf'])?> · <?=e(salario_texto($m['salario_minimo'], $m['salario_maximo']))?></div>
            </div>
            <span class="score-badge <?=e($m['nivel'])?>"><?=number_format((float)$m['pontuacao'], 0)?>%</span>
          </div>
          <div class="progress"><span style="width:<?=min(100, (float)$m['pontuacao'])?>%"></span></div>
          <?php if ($d): ?>
          <details><summary>Por que essa nota?</summary>
            <div class="match-box">
              <?php if (!empty($d['competencias']['atendidas'])): ?><div class="small">Você atende:</div><div class="chips"><?php foreach ($d['competencias']['atendidas'] as $c): ?><span class="chip ok">✓ <?=e($c)?></span><?php endforeach; ?></div><?php endif; ?>
              <?php if ($falt): ?><div class="small">A vaga pede e não encontramos no seu portfólio:</div><div class="chips"><?php foreach ($falt as $c): ?><span class="chip falta"><?=e($c)?></span><?php endforeach; ?></div><?php endif; ?>
              <div class="match-linha"><span>Competências</span><b><?=e((string)$d['competencias']['pontos'])?> / 50</b></div>
              <div class="match-linha"><span>Cargo — <?=e($d['cargo']['texto'] ?? '')?></span><b><?=e((string)$d['cargo']['pontos'])?> / 20</b></div>
              <div class="match-linha"><span>Localização — <?=e($d['local']['texto'] ?? '')?></span><b><?=e((string)$d['local']['pontos'])?> / 15</b></div>
              <div class="match-linha"><span>Nível — <?=e($d['nivel']['texto'] ?? '')?></span><b><?=e((string)$d['nivel']['pontos'])?> / 15</b></div>
              <?php foreach ($d['observacoes'] ?? [] as $o): ?><div class="small"><?=!empty($o['ok']) ? '✅' : '⚠️'?> <?=e($o['texto'] ?? '')?></div><?php endforeach; ?>
              <?php if ($recom): ?>
                <div class="small" style="margin-top:8px"><b>Cursos para aumentar seu match:</b></div>
                <?php foreach ($recom as $c): ?><div class="small">📚 <a href="<?=url('curso.php?id='.(int)$c['id'])?>"><?=e($c['titulo'])?></a> — <?=e($c['instituicao'])?> <span class="muted">(cobre: <?=e(implode(', ', $c['cobre']))?>)</span></div><?php endforeach; ?>
              <?php endif; ?>
            </div>
          </details>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
      </div>
      <?php if (!$isVip && count($matches) > $limiteGratis): ?>
        <div class="notice small pf-match-vip">
          <div><b>🔒 Mais <?=count($matches) - $limiteGratis?> vaga(s) compatíveis ocultas.</b> Assinantes VIP veem 100% do match e aparecem no topo para as empresas.</div>
          <a href="<?=url('planos.php')?>" class="cv-btn cv-btn-dourado">Liberar todas</a>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>
