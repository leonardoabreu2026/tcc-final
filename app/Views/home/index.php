<?php
/**
 * Página inicial (rota index.php): banner com busca, vagas, assinaturas, cursos e os painéis relâmpago
 * (quem somos, objetivo, missão, valores e planos) que aparecem de tempos em tempos no canto da tela.
 * Recebe de HomeController::index(): $dbErro, $slides, $creditos, $vagasCapa/$vagasFila/$totalVagas (vitrine rotativa),
 * $conteudosCapa/$conteudosFila/$totalConteudos (por formato, mesma vitrine rotativa das vagas),
 * $mapaMatch (% de match por vaga), $minhas (candidaturas do candidato) e $plano (assinatura ativa).
 */
?>
<?php if ($dbErro): ?>
  <div class="cv-wrap cv-flash"><div class="alert erro"><b>Não foi possível carregar os dados.</b> Verifique se o MySQL está ligado no XAMPP e se o banco <code><?=e(DB_NAME)?></code> foi importado (database/schema.sql e database/seed.sql).<br><small><?=e($dbErro)?></small></div></div>
<?php endif; ?>

<section class="cv-hero" data-carrossel aria-roledescription="carrossel" aria-label="Fotos de Brasília">
  <div class="cv-hero-slides" aria-hidden="true">
    <?php foreach ($slides as $n => $img): $cr = $creditos[basename($img)] ?? null; ?>
      <div class="cv-slide<?=$n === 0 ? ' ativo' : ''?>"
           <?=$n === 0 ? 'style="background-image:url(\''.e(url($img)).'\')"' : 'data-bg="'.e(url($img)).'"'?>
           data-credito="<?=e($cr ? 'Foto: '.$cr[0].' · '.$cr[1] : '')?>" data-fonte="<?=e($cr[2] ?? '')?>"></div>
    <?php endforeach; ?>
  </div>
  <div class="cv-wrap cv-hero-in">
    <span class="cv-hero-selo"><?=icone('local', 15)?>Distrito Federal · vagas atualizadas toda semana</span>
    <h1>Seu próximo emprego <br>começa <em>aqui no DF</em></h1>
    <p>Vagas reais de empresas do Distrito Federal, cursos gratuitos e um match que mostra o quanto você combina com cada oportunidade.</p>
    <form class="cv-hero-busca" action="<?=url('vagas.php')?>" method="get" role="search">
      <label class="sr-only" for="hero-busca">Buscar vagas</label>
      <?=icone('busca', 20)?>
      <input id="hero-busca" name="q" type="search" placeholder="Cargo, empresa ou palavra-chave" autocomplete="off">
      <button class="cv-btn cv-btn-verde">Buscar vagas</button>
    </form>
    <ul class="cv-hero-pontos">
      <li><?=icone('check', 16)?>Match com o seu currículo</li>
      <li><?=icone('check', 16)?>Cursos e e-books gratuitos</li>
      <li><?=icone('check', 16)?>Grátis para candidatos</li>
    </ul>
  </div>
  <?php if (count($slides) > 1): ?>
    <div class="cv-hero-dots">
      <?php foreach ($slides as $n => $img): ?><button type="button" class="<?=$n === 0 ? 'ativo' : ''?>" aria-label="Mostrar foto <?=$n + 1?> de <?=count($slides)?>"></button><?php endforeach; ?>
    </div>
  <?php endif; ?>
  <a class="cv-hero-credito" href="#" target="_blank" rel="noopener" hidden></a>
</section>

<section class="cv-secao">
  <div class="cv-wrap">
    <?=cv_titulo_secao('maleta', 'Vagas de Emprego', 'Confira as oportunidades publicadas no Distrito Federal', url('vagas.php'), 'Ver todas as vagas')?>
    <?php if ($vagasCapa): ?>
      <?php if ($vagasFila): // vitrine rotativa: um cartão troca por vez, até passarem todas as vagas abertas ?>
        <p class="cv-rotativo-info"><span><?=icone('raio', 13)?> As vagas se revezam aqui: <?=(int)$totalVagas?> vagas abertas passando pela vitrine.</span>
          <button type="button" class="cv-rotativo-pausa" data-rotativo-pausa aria-pressed="false">Pausar</button></p>
      <?php endif; ?>
      <div class="cv-grade"<?=$vagasFila ? ' data-rotativo="4500" aria-live="off"' : ''?>>
        <?php foreach ($vagasCapa as $v): ?><?=cv_card_vaga($v, $mapaMatch[(int)$v['id']] ?? null, $minhas[(int)$v['id']] ?? null)?><?php endforeach; ?>
      </div>
      <?php if ($vagasFila): ?>
        <template data-rotativo-fila><?php foreach ($vagasFila as $v): ?><?=cv_card_vaga($v, $mapaMatch[(int)$v['id']] ?? null, $minhas[(int)$v['id']] ?? null)?><?php endforeach; ?></template>
      <?php endif; ?>
    <?php elseif (!$dbErro): ?>
      <div class="empty">Nenhuma vaga aberta no momento.</div>
    <?php endif; ?>
  </div>
</section>

<section class="cv-assinaturas">
  <div class="cv-wrap cv-assinaturas-in">
    <div class="cv-assinaturas-tit">
      <h2><?=icone('planos', 22)?>Assinaturas</h2>
      <p><?=$plano ? 'Seu plano: <b>'.($plano['plano'] === 'empresa' ? 'Empresa Premium' : 'Candidato VIP').'</b> até '.date('d/m/Y', strtotime($plano['data_fim'])).'.' : 'Comece grátis e evolua quando quiser.'?></p>
    </div>
    <?php // Cada cartão leva à página de planos, já na aba certa e no plano clicado. ?>
    <a class="cv-plano" href="<?=url('planos.php?aba=candidato#plano-gratuito')?>"><span>Gratuito</span><b>R$ 0</b><small>até <?=AssinaturaDAO::LIMITE_CANDIDATURAS_GRATIS?> candidaturas ativas</small><i><?=usuarioLogado() ? 'Ver o plano' : 'Começar grátis'?> →</i></a>
    <a class="cv-plano cv-plano-dest" href="<?=url('planos.php?aba=candidato#plano-vip')?>"><span>Candidato VIP</span><b>R$ 9,90<small>/mês</small></b><small>candidaturas ilimitadas e destaque</small><i>Quero ser VIP →</i></a>
    <a class="cv-plano" href="<?=url('planos.php?aba=empresa#plano-premium')?>"><span>Empresa Premium</span><b>R$ 49,90<small>/mês</small></b><small>vagas ilimitadas e banco de talentos</small><i>Contratar mais rápido →</i></a>
    <a class="cv-btn cv-btn-dourado" href="<?=url('planos.php')?>">Comparar os planos</a>
  </div>
</section>

<?php
// Cursos, e-books e vídeos em seções separadas; e-books e vídeos só aparecem quando houver algum publicado.
// Mesma vitrine rotativa das vagas; cada seção num ritmo próprio, para os cartões não trocarem todos juntos.
$secoesConteudo = [
  'curso' => ['formatura', 'Cursos Gratuitos', 'Capacitação gratuita para aumentar o seu match com as vagas', 'Ver todos os cursos', 'Os cursos se revezam aqui', 'cursos', 5200],
  'ebook' => ['ebooks', 'E-books', 'Guias e materiais para ler no seu ritmo', 'Ver todos os e-books', 'Os e-books se revezam aqui', 'e-books', 5900],
  'video' => ['play', 'Vídeos', 'Aulas e conteúdos em vídeo para aprender na prática', 'Ver todos os vídeos', 'Os vídeos se revezam aqui', 'vídeos', 6600],
];
foreach ($secoesConteudo as $t => [$ic, $tit, $sub, $ver, $reveza, $unidade, $ritmo]):
  if ($t !== 'curso' && !$conteudosCapa[$t]) continue;
  $fila = $conteudosFila[$t]; ?>
<section class="cv-secao">
  <div class="cv-wrap">
    <?=cv_titulo_secao($ic, $tit, $sub, pt_secao_formato($t)[1], $ver)?>
    <?php if ($conteudosCapa[$t]): ?>
      <?php if ($fila): ?>
        <p class="cv-rotativo-info"><span><?=icone('raio', 13)?> <?=e($reveza)?>: <?=(int)$totalConteudos[$t]?> <?=e($unidade)?> passando pela vitrine.</span>
          <button type="button" class="cv-rotativo-pausa" data-rotativo-pausa aria-pressed="false">Pausar</button></p>
      <?php endif; ?>
      <div class="cv-grade"<?=$fila ? ' data-rotativo="'.(int)$ritmo.'" aria-live="off"' : ''?>><?php foreach ($conteudosCapa[$t] as $c): ?><?=cv_card_curso($c)?><?php endforeach; ?></div>
      <?php if ($fila): ?><template data-rotativo-fila><?php foreach ($fila as $c): ?><?=cv_card_curso($c)?><?php endforeach; ?></template><?php endif; ?>
    <?php elseif (!$dbErro): ?>
      <div class="empty">Nenhum curso publicado ainda.</div>
    <?php endif; ?>
  </div>
</section>
<?php endforeach; ?>

<?php
// Painel relâmpago: um balão de ideia pequeno, no canto da tela, que troca de mensagem de tempos em tempos
// (app.js, bloco [data-relampago]). [ícone, título, texto, link]; a doação é só mais uma mensagem da roda.
require_once __DIR__.'/../partials/doacao.php';
$pixDoacao = Pix::doacao();
$relampagos = [
  ['grupo', 'Quem somos', 'Uma plataforma do DF que reúne vagas de emprego, cursos gratuitos e qualificação profissional.', null],
  ['alvo', 'Nosso objetivo', 'Centralizar as oportunidades do DF e mostrar, com o match, as vagas que combinam com você.', null],
  ['seta', 'Nossa missão', 'Conectar talentos às oportunidades, com inclusão, qualificação e crescimento de carreira.', null],
  ['check', 'Nossos valores', 'Inclusão, transparência e respeito a quem procura e a quem contrata.', null],
  ['coracao', 'Apoie com um Pix', 'Gostou do site? Uma doação de qualquer valor ajuda a mantê-lo no ar.', 'pix'],
];
?>
<aside class="cv-relampago" data-relampago aria-label="Mensagens do Conecta Vagas DF" hidden>
  <button type="button" class="cv-relampago-fechar" aria-label="Fechar as mensagens">×</button>
  <?php foreach ($relampagos as [$ic, $tit, $txt, $extra]): ?>
    <div class="cv-relampago-painel">
      <b><?=icone($ic, 15)?><?=e($tit)?></b>
      <p><?=e($txt)?></p>
      <?php if ($extra === 'pix'): ?>
        <div class="cv-relampago-pix"><?=cv_doacao_qr($pixDoacao, 76)?><a href="#apoie">Ver no rodapé</a></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <span class="cv-relampago-barra" aria-hidden="true"><i></i></span>
</aside>
