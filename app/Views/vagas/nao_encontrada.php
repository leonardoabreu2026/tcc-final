<?php
/**
 * Vaga inexistente ou fora do ar (rota vaga.php?id= com HTTP 404).
 * Vaga pausada, encerrada ou cancelada só aparece para a empresa dona e para o administrador.
 */
?>
<?=cv_faixa('Vaga não encontrada', 'A vaga não está mais recebendo candidaturas.', ['Vagas' => url('vagas.php'), 'Não encontrada' => ''])?>
<div class="cv-wrap cv-secao">
  <div class="an-vazio">
    <span class="an-vazio-ic"><?=icone('vagas', 34)?></span>
    <h2>Esta vaga não está disponível</h2>
    <p>Ela pode ter sido preenchida, pausada ou cancelada pela empresa. Veja as outras oportunidades abertas.</p>
    <p class="an-vazio-acoes"><a class="cv-btn cv-btn-azul" href="<?=e(url('vagas.php'))?>">Ver vagas abertas</a></p>
  </div>
</div>
