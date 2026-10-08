<?php
/**
 * Curso inexistente ou oculto (rota curso.php?id= com HTTP 404).
 * Conteúdo inativo só aparece para o administrador. Recebe $dbErro (falha ao consultar o banco).
 */
?>
<?=cv_faixa('Conteúdo não encontrado', 'O curso pode ter sido cancelado ou ainda não foi publicado.', ['Cursos' => url('cursos.php'), 'Não encontrado' => ''])?>
<div class="cv-wrap cv-secao"><div class="empty"><?=$dbErro ? e($dbErro).'<br>' : ''?><a class="cv-btn cv-btn-azul" href="<?=e(url('cursos.php'))?>">Ver todos os cursos</a></div></div>