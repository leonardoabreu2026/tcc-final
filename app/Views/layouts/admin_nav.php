<?php
/**
 * Abas do painel (admin e empresa). A ação do controller define $abaAtiva (aba destacada).
 * Também carrega partials/graficos.php: gráficos, indicadores e selos usados pelas telas do painel.
 */
require_once __DIR__.'/../partials/graficos.php';
$abas = [];
$abas['painel'] = ['admin/index.php', 'Visão geral', 'painel'];
if (isAdmin()) {
    $abas['usuarios'] = ['admin/pages/usuarios.php', 'Usuários', 'grupo'];
    $abas['categorias'] = ['admin/pages/categorias.php', 'Categorias', 'jornal'];
    $abas['cursos'] = ['admin/pages/cursos.php', 'Cursos e e-books', 'cursos'];
    $abas['assinaturas'] = ['admin/pages/assinaturas.php', 'Assinaturas', 'planos'];
}
$abas['vagas'] = ['admin/pages/vagas.php', 'Vagas', 'maleta'];
$abas['candidaturas'] = ['admin/pages/candidaturas.php', 'Candidaturas', 'formulario'];
$abas['talentos'] = ['admin/pages/talentos.php', 'Banco de talentos', 'busca'];
if (isEmpresa()) $abas['empresa'] = ['admin/pages/empresa_perfil.php', 'Perfil da empresa', 'perfil'];
if (isEmpresa()) $abas['planos'] = ['planos.php', 'Planos', 'planos']; // o administrador não assina planos
?>
<nav class="pn-abas" aria-label="Seções do painel">
  <?php foreach ($abas as $k => [$link, $rotulo, $ic]): $ativa = ($abaAtiva ?? '') === $k; ?>
    <a href="<?=url($link)?>"<?=$ativa ? ' class="ativo" aria-current="page"' : ''?>><?=icone($ic, 17)?><?=e($rotulo)?></a>
  <?php endforeach; ?>
</nav>
