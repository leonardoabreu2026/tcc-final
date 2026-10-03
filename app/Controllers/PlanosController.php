<?php
declare(strict_types=1);

/**
 * Planos e assinaturas (planos.php): Candidato VIP (R$ 9,90) e Empresa Premium (R$ 49,90).
 * A cobrança é DEMONSTRATIVA: a assinatura é gravada no banco após a confirmação,
 * sem pagamento real. As regras de cada plano ficam em AssinaturaDAO.
 */
final class PlanosController extends Controller {
    /** planos.php — mostra os planos (GET) e processa assinar/cancelar (POST, campo "acao"). */
    public function index(): void {
        $dao = new AssinaturaDAO();
        $usuarioLogado = usuarioLogado();
        $usuarioId = $usuarioLogado ? (int)$_SESSION['usuario_id'] : 0;
        $assinaturaAtiva = $usuarioLogado ? $dao->buscarAtivaPorUsuario($usuarioId) : null;
        $historicoAssinaturas = $usuarioLogado ? $dao->listarPorUsuario($usuarioId) : [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validar_csrf();
            if (!$usuarioLogado) { flash('erro', 'Faça login para assinar um plano.'); redirect('login.php'); }
            $acao = post_str('acao');

            if ($acao === 'assinar_candidato') {
                if (!isCandidato()) {
                    flash('erro', 'Esta assinatura é exclusiva para contas de candidatos.');
                    redirect('planos.php');
                }
                if ($dao->isCandidatoVip($usuarioId)) { flash('info', 'Seu plano VIP já está ativo.'); redirect('planos.php'); }
                $ok = $dao->assinar($usuarioId, 'assinante', AssinaturaDAO::PRECOS['assinante'], 30);
                if ($ok) {
                    flash('ok', '🎉 Parabéns! Sua assinatura VIP de Candidato foi ativada com sucesso por 30 dias. Aproveite as vantagens!');
                } else {
                    flash('erro', 'Não foi possível processar a assinatura. Tente novamente.');
                }
                redirect('planos.php');
            }

            if ($acao === 'assinar_empresa') {
                if (!isEmpresa()) {
                    flash('erro', 'Esta assinatura é exclusiva para contas de empresas.');
                    redirect('planos.php');
                }
                if ($dao->isEmpresaPremium($usuarioId)) { flash('info', 'O plano Empresa Premium já está ativo.'); redirect('planos.php'); }
                $ok = $dao->assinar($usuarioId, 'empresa', AssinaturaDAO::PRECOS['empresa'], 30);
                if ($ok) {
                    flash('ok', '🎉 Parabéns! O plano Empresa Premium foi ativado com sucesso por 30 dias. Vagas ilimitadas e banco de talentos liberados!');
                } else {
                    flash('erro', 'Não foi possível processar a assinatura. Tente novamente.');
                }
                redirect('planos.php');
            }

            if ($acao === 'cancelar') {
                if (!$assinaturaAtiva) {
                    flash('info', 'Não existe uma assinatura ativa para cancelar.');
                    redirect('planos.php');
                }
                $ok = $dao->cancelar($usuarioId);
                if ($ok) {
                    flash('info', $assinaturaAtiva['plano'] === 'empresa'
                        ? 'Sua assinatura foi cancelada. A empresa voltou ao Plano Básico: o destaque das vagas foi removido e novas publicações respeitam o limite de '.AssinaturaDAO::LIMITE_VAGAS_GRATIS.' vagas ativas.'
                        : 'Sua assinatura foi cancelada. Seu plano voltou ao padrão gratuito (até '.AssinaturaDAO::LIMITE_CANDIDATURAS_GRATIS.' candidaturas ativas).');
                } else {
                    flash('erro', 'Não foi possível cancelar a assinatura.');
                }
                redirect('planos.php');
            }

            flash('erro', 'Ação inválida.');
            redirect('planos.php');
        }

        $title = 'Planos e Assinaturas';
        $this->view('planos/index', get_defined_vars());
    }
}
