<?php
/**
 * Cabeçalho do layout: <head>, menu principal e a mensagem "flash".
 * Incluído por View::render() antes de cada tela. Usa, se a ação definir:
 * $title, $layoutLargo (faixas com a largura toda), $descricaoPagina, $ogImagem e $ptMenuTipo (curso/ebook/video).
 */
$flash = getFlash();

// Item ativo do menu, a partir da rota atual (ex.: "/admin/pages/vagas.php").
$scriptAtual = '/'.Router::atual();
$emPasta = fn(string $p) => str_contains($scriptAtual, '/'.$p.'/');
$ehPagina = fn(string ...$nomes) => in_array(basename($scriptAtual), $nomes, true) && !$emPasta('admin') && !$emPasta('view');
// Formato da página de conteúdo (cursos.php?tipo= ou curso.php, que informa $ptMenuTipo): cursos acendem "Cursos";
// e-books e vídeos (que ficam junto dos e-books, sem item próprio no menu) acendem "E-books".
$tipoMenu = $ehPagina('cursos.php') ? (string)($_GET['tipo'] ?? '') : ($ehPagina('curso.php') ? (string)($ptMenuTipo ?? '') : null);
$menu = [
    'vagas'   => $ehPagina('vagas.php', 'vaga.php', 'candidatar.php'),
    'cursos'  => $tipoMenu !== null && !in_array($tipoMenu, ['ebook', 'video'], true),
    'ebooks'  => in_array($tipoMenu, ['ebook', 'video'], true),
    'cadastro'=> $ehPagina('cadastro.php'),
    'login'   => $ehPagina('login.php', 'esqueci_senha.php', 'redefinir_senha.php'),
    'perfil'  => ($emPasta('view/perfil') && basename($scriptAtual) !== 'portfolio.php') || $emPasta('admin'),
    'portfolio' => $emPasta('view/perfil') && basename($scriptAtual) === 'portfolio.php',
];
// Candidato: a aba "Portfólio" só aparece depois que o cadastro do perfil é validado (completo).
$portfolioLiberado = false;
if (usuarioLogado() && isCandidato()) {
    try {
        $portfolioLiberado = Portfolio::validarCadastro((new PerfilDAO())->buscarPorUsuarioId((int)$_SESSION['usuario_id']))['completo'];
    } catch (Throwable) { $portfolioLiberado = false; }
}
$at = fn(string $k) => $menu[$k] ? ' class="ativo" aria-current="page"' : '';
// Páginas públicas usam a largura toda (faixas com fundo); as demais ficam no contêiner padrão.
$layoutLargo = !empty($layoutLargo);
$ptDescricao = $descricaoPagina ?? 'Vagas de emprego, cursos e e-books gratuitos no Distrito Federal — Conecta Vagas DF.';
?>
<!doctype html>
<html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title ?? SITE_NAME)?> | <?=e(SITE_NAME)?></title>
<meta name="description" content="<?=e(pt_resumo($ptDescricao, 180))?>">
<meta property="og:site_name" content="<?=e(SITE_NAME)?>">
<meta property="og:title" content="<?=e($title ?? SITE_NAME)?>">
<meta property="og:description" content="<?=e(pt_resumo($ptDescricao, 180))?>">
<?php if (!empty($ogImagem)): ?><meta property="og:image" content="<?=e(url((string)$ogImagem))?>"><?php endif; ?>
<meta name="theme-color" content="#0b3a8f">
<link rel="stylesheet" href="<?=url('assets/css/app.css')?>?v=16">
<link rel="stylesheet" href="<?=url('assets/css/site.css')?>?v=17">
<link rel="stylesheet" href="<?=url('assets/css/portfolio.css')?>?v=4">
<link rel="stylesheet" href="<?=url('assets/css/anuncios.css')?>?v=1">
<link rel="stylesheet" href="<?=url('assets/css/painel.css')?>?v=13">
</head><body class="cv">
<a class="cv-pular" href="#conteudo">Pular para o conteúdo</a>
<header class="cv-topo">
 <div class="cv-wrap cv-topo-in">
  <a class="cv-marca" href="<?=url('index.php')?>" aria-label="Conecta Vagas DF — página inicial">
    <span class="cv-marca-ic"><?=icone('logo', 30)?></span>
    <span class="cv-marca-txt"><b>Conecta Vagas <span class="cv-marca-df">DF</span></b><small>Conectando talentos às oportunidades</small></span>
  </a>
  <button class="cv-menu-btn nav-toggle" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="menu-principal"
          onclick="document.body.classList.toggle('menu-aberto');this.setAttribute('aria-expanded',document.body.classList.contains('menu-aberto'))">☰</button>
  <nav id="menu-principal" class="cv-menu" aria-label="Menu principal">
   <a href="<?=url('vagas.php')?>"<?=$at('vagas')?>><?=icone('vagas', 18)?>Vagas</a>
   <a href="<?=url('cursos.php')?>"<?=$at('cursos')?>><?=icone('cursos', 18)?>Cursos</a>
   <a href="<?=url('cursos.php?tipo=ebook')?>"<?=$at('ebooks')?>><?=icone('ebooks', 18)?>E-books</a>
   <?php if (usuarioLogado()): ?>
    <a class="cv-menu-perfil<?=$menu['perfil'] ? ' ativo' : ''?>" href="<?=url(isCandidato() ? 'view/perfil/index.php' : 'admin/index.php')?>" title="<?=e($_SESSION['usuario_nome'] ?? '')?>">
      <?=icone(isCandidato() ? 'perfil' : 'painel', 18)?><?=isCandidato() ? 'Meu perfil' : 'Painel'?></a>
    <?php if ($portfolioLiberado): ?>
      <a class="cv-menu-portfolio<?=$menu['portfolio'] ? ' ativo' : ''?>" href="<?=url('view/perfil/portfolio.php')?>"<?=$menu['portfolio'] ? ' aria-current="page"' : ''?>><?=icone('jornal', 18)?>Portfólio</a>
    <?php endif; ?>
    <a href="<?=logout_url()?>"><?=icone('sair', 18)?>Sair</a>
   <?php else: ?>
    <a href="<?=url('cadastro.php')?>"<?=$at('cadastro')?>><?=icone('formulario', 18)?>Cadastro</a>
    <a class="cv-menu-perfil<?=$menu['login'] ? ' ativo' : ''?>" href="<?=url('login.php')?>"><?=icone('usuario', 18)?>Login</a>
   <?php endif; ?>
  </nav>
 </div>
</header>
<main id="conteudo" class="<?=$layoutLargo ? 'cv-main' : 'container'?>">
<?php if ($flash): ?><div class="<?=$layoutLargo ? 'cv-wrap cv-flash' : ''?>"><div class="alert <?=e($flash['type'])?>" role="status" data-flash><?=e($flash['message'])?></div></div><?php endif; ?>
