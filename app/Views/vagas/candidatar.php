<?php
/**
 * Formulário de candidatura (rota candidatar.php?vaga_id=): resumo da vaga (cartaz, empresa, salário) ao lado
 * e o formulário com a escolha do currículo e a carta de apresentação.
 * Recebe de CandidaturaController::candidatar(): $vaga, $vid, $permissao (limite do plano), $cvs e $isVip.
 */
$empresa = $vaga['empresa_nome'] ?: 'Empresa';
$salario = salario_texto($vaga['salario_minimo'] ?? null, $vaga['salario_maximo'] ?? null);
$linkVaga = url('vaga.php?id='.$vid);
?>
<div class="an-cand">
  <nav class="an-trilha" aria-label="Você está em">
    <a href="<?=e(url('index.php'))?>">Início</a><span aria-hidden="true">›</span>
    <a href="<?=e(url('vagas.php'))?>">Vagas</a><span aria-hidden="true">›</span>
    <a href="<?=e($linkVaga)?>"><?=e($vaga['titulo'])?></a><span aria-hidden="true">›</span>
    <span aria-current="page">Candidatura</span>
  </nav>

  <div class="an-cand-grade">
    <aside class="cv-bloco an-cand-vaga" aria-label="Vaga escolhida">
      <a class="an-cand-cartaz" href="<?=e($linkVaga)?>" tabindex="-1" aria-hidden="true"><?=cv_cartaz_vaga($vaga['imagem'] ?? '', '', false)?></a>
      <div class="an-cand-info">
        <p class="cv-chapeu"><span><?=e($vaga['categoria_nome'] ?? 'Vaga')?></span></p>
        <h2><a href="<?=e($linkVaga)?>"><?=e($vaga['titulo'])?></a></h2>
        <p class="an-card-linha"><?=icone('maleta', 15)?><span><?=e($empresa)?></span></p>
        <p class="an-card-linha"><?=icone('local', 15)?><span><?=e(pt_local($vaga))?></span></p>
        <p class="an-card-salario<?=$salario === 'A combinar' ? ' an-combinar' : ''?>"><?=icone('dinheiro', 15)?><span><?=e($salario)?></span></p>
        <ul class="an-chips" aria-label="Contratação, nível e modelo"><?php foreach (pt_chips_vaga($vaga) as $c): ?><li><?=e($c)?></li><?php endforeach; ?></ul>
        <a class="an-cand-voltar" href="<?=e($linkVaga)?>">‹ Ver o anúncio completo</a>
      </div>
    </aside>

    <section class="cv-bloco an-cand-form" aria-labelledby="an-cand-tit">
      <div class="an-cand-topo">
        <h1 id="an-cand-tit">Candidatura</h1>
        <?php if ($isVip): ?><span class="badge-vip">⭐ Candidato VIP</span><?php endif; ?>
      </div>
      <p class="an-cand-sub">Você está se candidatando a <b><?=e($vaga['titulo'])?></b> em <b><?=e($empresa)?></b>.</p>

      <?php if (!$permissao['permitido']): ?>
        <div class="alert erro">
          <b>Limite de candidaturas atingido.</b><br><?=e($permissao['motivo'])?><br><br>
          <a href="<?=url('planos.php')?>" class="btn btn-gold btn-sm">Ativar Plano VIP por R$ 9,90/mês</a>
        </div>
      <?php else: ?>
        <?php if (!$isVip): ?>
          <div class="notice small an-cand-plano">Plano Gratuito: <b><?=(int)($permissao['ativas'] ?? 0)?>/<?=(int)($permissao['limite'] ?? AssinaturaDAO::LIMITE_CANDIDATURAS_GRATIS)?></b> candidaturas ativas. <a href="<?=url('planos.php')?>">Seja VIP</a> para candidaturas ilimitadas e prioridade para a empresa.</div>
        <?php else: ?>
          <div class="alert ok small">✓ <b>VIP:</b> sua candidatura aparece no topo da lista da empresa.</div>
        <?php endif; ?>

        <?php if (!$cvs): ?>
          <div class="alert info">Envie pelo menos um currículo no seu <a href="<?=url('view/perfil/index.php')?>">perfil</a> antes de se candidatar. O sistema também monta seu portfólio e calcula o match.</div>
        <?php else: ?>
        <form method="post" class="an-cand-campos">
          <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
          <input type="hidden" name="vaga_id" value="<?=$vid?>">
          <div>
            <label for="an-curriculo">Currículo</label>
            <select id="an-curriculo" name="curriculo_id" required>
              <?php foreach ($cvs as $cv): ?>
                <option value="<?=(int)$cv['id']?>"><?=e($cv['titulo'] ?: 'Currículo #'.$cv['id'])?> · <?=e(strtoupper($cv['arquivo_tipo']))?> · <?=date('d/m/Y', strtotime($cv['created_at']))?></option>
              <?php endforeach; ?>
            </select>
            <p class="an-cand-dica">A empresa também verá o seu <a href="<?=url('view/perfil/portfolio.php')?>" target="_blank" rel="noopener">portfólio<span class="sr-only"> (abre em nova aba)</span></a> e o seu match com a vaga.</p>
          </div>
          <div>
            <label for="an-carta">Carta de apresentação <small class="muted">(opcional)</small></label>
            <textarea id="an-carta" name="carta_apresentacao" maxlength="5000" rows="7" data-contador="an-carta-cont" aria-describedby="an-carta-cont" placeholder="Conte brevemente por que você se interessa pela vaga e o que pode oferecer à empresa."></textarea>
            <p class="an-cand-dica an-cand-contador"><span id="an-carta-cont">até 5.000 caracteres</span></p>
          </div>
          <div class="an-cand-acoes">
            <a class="cv-btn an-btn-linha" href="<?=e($linkVaga)?>">Voltar</a>
            <button class="cv-btn cv-btn-verde cv-btn-g"><?=icone('check', 16)?>Enviar candidatura</button>
          </div>
        </form>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>
</div>
<script src="<?=url('assets/js/anuncios.js')?>?v=1" defer></script>
