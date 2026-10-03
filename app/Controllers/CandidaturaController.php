<?php
declare(strict_types=1);

/**
 * Candidaturas do lado do candidato: enviar (candidatar.php) e cancelar.
 * O lado da empresa (mudar status, dar retorno) fica em EmpresaController::candidaturas().
 */
final class CandidaturaController extends Controller {
    /**
     * candidatar.php?vaga_id= — formulário (GET) e envio (POST) da candidatura.
     * Regra do plano gratuito: até AssinaturaDAO::LIMITE_CANDIDATURAS_GRATIS candidaturas ativas (VIP: ilimitado).
     */
    public function candidatar(): void {
        exigirLogin();
        if (!isCandidato()) negar_acesso('Apenas candidatos podem se candidatar.');

        $usuarioId = (int)$_SESSION['usuario_id'];
        $perfil = (new PerfilDAO())->obterOuCriar($usuarioId);
        $vid = (int)($_GET['vaga_id'] ?? $_POST['vaga_id'] ?? 0);
        $vagaDao = new VagaDAO();
        $vaga = $vagaDao->buscar($vid);

        if (!$perfil || !$vaga || !$vagaDao->estaAberta($vaga)) {
            flash('erro', 'Esta vaga não está mais recebendo candidaturas.');
            redirect('vagas.php');
        }

        $dao = new CandidaturaDAO();
        $existente = $dao->buscarDoCandidato((int)$perfil['id'], $vid);
        if ($existente && $existente['status'] !== 'cancelada') {
            flash('info', 'Você já se candidatou a esta vaga.');
            redirect('vaga.php?id='.$vid);
        }

        $permissao = (new AssinaturaDAO())->podeCandidatar($usuarioId, (int)$perfil['id']);
        $isVip = !isset($permissao['limite']);   // podeCandidatar() só traz 'limite' no plano gratuito
        $cvDao = new CurriculoDAO();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validar_csrf();
            if (!$permissao['permitido']) { flash('erro', $permissao['motivo']); redirect('planos.php'); }

            $curriculoId = post_int('curriculo_id');
            if (!$cvDao->buscarDoPerfil($curriculoId, (int)$perfil['id'])) {
                flash('erro', 'Selecione um currículo válido do seu perfil.');
                redirect('candidatar.php?vaga_id='.$vid);
            }
            $carta = mb_substr(post_str('carta_apresentacao'), 0, 5000);
            // Contagem do limite e gravação na mesma transação (evita passar do limite com envios simultâneos).
            $limite = $isVip ? null : (int)$permissao['limite'];
            $res = $dao->enviarComLimite((int)$perfil['id'], $vid, $curriculoId, $carta, $limite);
            if ($res === 'limite') { flash('erro', sprintf(AssinaturaDAO::AVISO_LIMITE_CANDIDATURAS, $limite)); redirect('planos.php'); }

            flash($res === 'ok' ? 'ok' : 'erro', $res === 'ok' ? 'Candidatura enviada com sucesso! Acompanhe o status no seu perfil.' : 'Não foi possível enviar a candidatura.');
            redirect('vaga.php?id='.$vid);
        }

        $cvs = $cvDao->listarPorPerfil((int)$perfil['id']);
        $title = 'Candidatar-se';
        $this->view('vagas/candidatar', get_defined_vars());
    }

    /** view/perfil/candidatura_cancelar.php (POST) — o candidato só cancela candidaturas ainda não analisadas. */
    public function cancelar(): void {
        exigirLogin();
        if (!isCandidato()) negar_acesso('Acesso negado.');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('view/perfil/index.php');
        validar_csrf();

        $p = (new PerfilDAO())->buscarPorUsuarioId((int)$_SESSION['usuario_id']);
        $ok = $p && (new CandidaturaDAO())->cancelarDoCandidato(post_int('id'), (int)$p['id']);
        flash($ok ? 'ok' : 'erro', $ok ? 'Candidatura cancelada.' : 'Não foi possível cancelar: a candidatura já está em uma etapa avançada.');
        redirect('view/perfil/index.php#candidaturas');
    }
}
