<?php
declare(strict_types=1);

/**
 * Área do candidato: perfil (cadastro), portfólio e máquina de match.
 *
 * Fluxo: o candidato envia o currículo (CurriculoController::upload) ou preenche o
 * formulário → quando os campos obrigatórios estão completos (Portfolio::validarCadastro),
 * a aba "Portfólio" é liberada no topo → lá ficam o portfólio montado e o match com as vagas.
 */
final class PerfilController extends Controller {

    /** view/perfil/index.php — "Meu perfil": máquina de extração do currículo + formulário do cadastro. */
    public function index(): void {
        exigirLogin();
        if (!isCandidato()) redirect('admin/index.php');

        $usuarioId = (int)$_SESSION['usuario_id'];
        $perfil = (new PerfilDAO())->obterOuCriar($usuarioId);

        $assDao = new AssinaturaDAO();
        $isVip = $assDao->isCandidatoVip($usuarioId);
        $assinaturaAtiva = $assDao->buscarAtivaPorUsuario($usuarioId);
        $candidaturasAtivas = $assDao->contarCandidaturasAtivas((int)$perfil['id']);

        $cvs = (new CurriculoDAO())->listarPorPerfil((int)$perfil['id']);
        $cands = (new CandidaturaDAO())->listarPorCandidato((int)$perfil['id']);

        // Validação do cadastro: libera a aba "Portfólio" quando os obrigatórios estão completos.
        $validacao = Portfolio::validarCadastro($perfil);
        $completude = $validacao['percentual'];
        // Relatório da última extração (logo após enviar o currículo, ou pelo link "ver último relatório").
        $relatorio = (isset($_GET['relatorio']) && is_array($_SESSION['relatorio_extracao'] ?? null)) ? $_SESSION['relatorio_extracao'] : null;
        $p = $perfil; // o parcial do relatório usa $p

        $title = 'Meu perfil';
        $this->view('perfil/index', get_defined_vars());
    }

    /** view/perfil/salvar.php (POST) — grava o formulário do perfil, a foto e o telefone; recalcula o match. */
    public function salvar(): void {
        exigirLogin();
        if (!isCandidato()) negar_acesso('Acesso negado.');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('view/perfil/index.php');
        validar_csrf();

        $dao = new PerfilDAO();
        $p = $dao->obterOuCriar((int)$_SESSION['usuario_id']);

        $campos = ['titulo_profissional','bio','cidade','uf','habilidades','experiencias','formacao','cursos_complementares','informacoes_adicionais','idiomas','competencias','disponibilidade','objetivo','links','cnh','pretensao_salarial'];
        $dados = ['id' => $p['id'], 'usuario_id' => $p['usuario_id'], 'foto' => $p['foto'] ?? null, 'publico' => isset($_POST['publico']) ? 1 : 0, 'aceite_lgpd' => 1,
                  'nivel_experiencia' => enum_val(post_str('nivel_experiencia'), PerfilDAO::NIVEIS, 'junior'),
                  'data_nascimento' => data_ou_null(post_str('data_nascimento'))];
        foreach ($campos as $c) $dados[$c] = post_str($c);
        if ($dados['uf'] !== '' && !preg_match('/^[A-Za-z]{2}$/', $dados['uf'])) { flash('erro', 'UF inválida. Use a sigla com 2 letras (ex.: DF).'); redirect('view/perfil/index.php'); }
        if ($dados['cnh'] !== '' && !preg_match('/^(A|B|C|D|E|AB|AC|AD|AE)$/i', $dados['cnh'])) { flash('erro', 'CNH inválida. Informe a categoria (ex.: B, AB, D).'); redirect('view/perfil/index.php'); }
        // Links: um por linha, só endereços web.
        $dados['links'] = implode("\n", array_filter(array_map('trim', preg_split('/[\s,;]+/u', $dados['links']) ?: []), fn($u) => (bool)preg_match('#^(https?://)?[\w.-]+\.[a-z]{2,}(/\S*)?$#i', $u)));

        // Foto (opcional): JPG/PNG/WEBP até 3 MB, depois de validar o resto do formulário.
        // A foto antiga só é apagada quando o perfil já foi salvo com a nova.
        $fotoNova = salvar_imagem_enviada('foto', 'foto_'.(int)$_SESSION['usuario_id']);
        if ($fotoNova === false) { flash('erro', 'Foto inválida: use JPG, PNG ou WEBP de até 3 MB.'); redirect('view/perfil/index.php'); }
        if ($fotoNova !== null) $dados['foto'] = $fotoNova;

        if (!$dao->salvar(new PerfilDTO($dados))) {
            if ($fotoNova) apagar_upload_sem_uso($fotoNova);
            flash('erro', 'Não foi possível salvar o perfil.');
            redirect('view/perfil/index.php');
        }
        if ($fotoNova && !empty($p['foto'])) apagar_upload_sem_uso((string)$p['foto']);
        // Telefone pertence à conta (tabela usuarios).
        $tel = mb_substr(post_str('telefone'), 0, 30);
        (new UsuarioDAO())->atualizarTelefone((int)$_SESSION['usuario_id'], $tel ?: null);

        try { (new MatchService())->recalcular((int)$p['id']); } catch (Throwable) {}
        // Validação do cadastro: completo => a aba "Portfólio" aparece no topo.
        $antes = Portfolio::validarCadastro($p)['completo'];
        $val = Portfolio::validarCadastro($dao->buscarPorUsuarioId((int)$_SESSION['usuario_id']));
        if ($val['completo']) {
            flash('ok', $antes ? 'Cadastro atualizado. Seu portfólio e o match já foram atualizados.' : 'Cadastro validado! A aba Portfólio foi liberada no topo — lá estão o seu portfólio e a máquina de match.');
        } else {
            flash('info', 'Cadastro salvo. Para liberar o Portfólio, falta: '.implode(', ', $val['faltando']).'.');
        }
        redirect('view/perfil/index.php');
    }

    /**
     * view/perfil/portfolio.php — portfólio profissional montado automaticamente com os dados
     * do perfil (preenchidos no cadastro ou extraídos do currículo).
     *  - ?id=<perfil> : portfólio de um candidato (empresas/admin/visitantes, se o perfil for público);
     *                   completo para admin, empresa Premium ou que recebeu a candidatura, prévia para os demais
     *  - sem id       : o portfólio do próprio candidato logado (com a máquina de match)
     */
    public function portfolio(): void {
        $dao = new PerfilDAO();
        $id = (int)($_GET['id'] ?? 0);
        if ($id === 0) {
            exigirLogin();
            if (!isCandidato()) { flash('info', 'Informe qual portfólio deseja ver.'); redirect('admin/index.php'); }
            $p = $dao->obterOuCriar((int)$_SESSION['usuario_id']);
        } else {
            $p = $dao->buscarPorId($id);
        }
        if (!$p || ($p['tipo'] ?? '') !== 'candidato' || !(int)$p['ativo']) negar_acesso('Portfólio não encontrado.', 404);

        $usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
        $ehDono = usuarioLogado() && (int)$p['usuario_id'] === $usuarioId;

        // O portfólio do candidato só é liberado depois que o cadastro do perfil é validado.
        $validacao = Portfolio::validarCadastro($p);
        if ($ehDono && !$validacao['completo']) {
            $comRelatorio = isset($_GET['relatorio']);
            if (!$comRelatorio) flash('info', 'Complete o cadastro para liberar o seu portfólio. Falta: '.implode(', ', $validacao['faltando']).'.');
            redirect('view/perfil/index.php'.($comRelatorio ? '?relatorio=1' : '#cadastro'));
        }

        // Máquina de match (só para o dono): vagas compatíveis, explicação da nota e cursos recomendados.
        $matches = []; $exibirMatches = []; $cursosAtivos = []; $isVip = false; $limiteGratis = 3;
        if ($ehDono) {
            try {
                $matchDao = new MatchDAO();
                $matches = $matchDao->listarPorCandidato((int)$p['id']);
                if (!$matches) { (new MatchService())->recalcular((int)$p['id']); $matches = $matchDao->listarPorCandidato((int)$p['id']); }
                $cursosAtivos = (new CursoDAO())->listar(true);
                $isVip = (new AssinaturaDAO())->isCandidatoVip($usuarioId);
            } catch (Throwable) {}
            $exibirMatches = $isVip ? $matches : array_slice($matches, 0, $limiteGratis);
        }

        // Empresa que recebeu candidatura deste candidato vê o contato, mesmo sem plano.
        $recebeuCandidatura = false;
        if (usuarioLogado() && isEmpresa()) {
            $minha = $dao->buscarPorUsuarioId($usuarioId);
            if ($minha) $recebeuCandidatura = (new CandidaturaDAO())->empresaRecebeuDoCandidato((int)$p['id'], (int)$minha['id']);
        }
        $empresaPremium = usuarioLogado() && isEmpresa() && (new AssinaturaDAO())->isEmpresaPremium($usuarioId);
        $podeVer = $ehDono || isAdmin() || $recebeuCandidatura || (int)$p['publico'] === 1;
        if (!$podeVer) negar_acesso('Este candidato deixou o perfil privado.');
        $verContato = $ehDono || isAdmin() || $recebeuCandidatura || $empresaPremium;
        // Empresa sem Premium (e sem candidatura deste candidato) vê só a prévia, igual ao Banco de Talentos do
        // plano básico: nome mascarado, sem foto, contato, experiências e formação. Visitantes e candidatos veem
        // o portfólio público completo (vitrine do candidato), mas sem os dados de contato.
        $previa = usuarioLogado() && isEmpresa() && !$verContato;
        $nomeExibido = $previa ? mb_substr((string)$p['nome'], 0, 4).'***' : (string)$p['nome'];

        $experiencias = Portfolio::experiencias((string)($p['experiencias'] ?? ''));
        $formacao = Portfolio::formacao((string)($p['formacao'] ?? ''));
        $habilidades = Portfolio::lista((string)($p['habilidades'] ?? ''), (string)($p['competencias'] ?? ''));
        $cursos = Portfolio::lista((string)($p['cursos_complementares'] ?? ''));
        $idiomas = Portfolio::lista((string)($p['idiomas'] ?? ''));
        $extras = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string)($p['informacoes_adicionais'] ?? '')) ?: [])));
        // Dados herdados da extração do currículo que também vão na faixa de informações adicionais.
        $temTexto = fn(string $rx) => (bool)preg_grep($rx, $extras);
        if (!empty($p['cnh']) && !$temTexto('/\bcnh\b|habilita/iu')) $extras[] = 'CNH categoria '.$p['cnh'];
        if (trim((string)($p['disponibilidade'] ?? '')) !== '' && !$temTexto('/disponib/iu')) $extras[] = 'Disponibilidade: '.trim((string)$p['disponibilidade']);
        $links = Portfolio::links((string)($p['links'] ?? ''));
        $ehPcd = (bool)preg_match('/pcd|defici|laudo/iu', implode(' ', $extras));
        // Relatório da última extração (só para o dono, logo depois do envio do currículo).
        $relatorio = ($ehDono && isset($_GET['relatorio']) && is_array($_SESSION['relatorio_extracao'] ?? null)) ? $_SESSION['relatorio_extracao'] : null;
        // Ex.: "Ceilândia, Brasília - DF" (região administrativa) ou "Goiânia - GO".
        $cidade = trim((string)($p['cidade'] ?? '')); $uf = trim((string)($p['uf'] ?? ''));
        if ($uf === 'DF' && $cidade !== '' && $cidade !== 'Brasília') $cidade .= ', Brasília';
        $local = implode(' - ', array_filter([$cidade, $uf]));
        $iniciais = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/u', trim((string)$p['nome'])) ?: [], 0, 2))));
        $titulo = trim((string)($p['titulo_profissional'] ?? ''));
        $resumo = trim((string)($p['bio'] ?? ''));
        if ($previa) {
            // Na prévia: só a inicial do nome e o mesmo trecho curto do resumo do cartão do Banco de Talentos.
            $iniciais = mb_strtoupper(mb_substr($nomeExibido, 0, 1));
            $resumo = mb_strimwidth(trim((string)(($p['bio'] ?? '') ?: ($p['objetivo'] ?? ''))), 0, 110, '...');
        }

        $title = 'Portfólio de '.$nomeExibido;
        $this->view('perfil/portfolio', get_defined_vars());
    }

    /** view/perfil/recalcular_match.php (POST) — botão "Recalcular match" do portfólio. */
    public function recalcularMatch(): void {
        exigirLogin();
        if (!isCandidato()) negar_acesso('Acesso negado.');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('view/perfil/index.php');
        validar_csrf();

        $p = (new PerfilDAO())->obterOuCriar((int)$_SESSION['usuario_id']);
        try {
            $n = (new MatchService())->recalcular((int)$p['id']);
            flash('ok', "Match recalculado: {$n} vaga(s) analisada(s).");
        } catch (Throwable) {
            flash('erro', 'Não foi possível recalcular o match agora.');
        }
        redirect('view/perfil/portfolio.php#match');
    }

    /**
     * view/perfil/conta_excluir.php (POST) — LGPD: o candidato apaga a PRÓPRIA conta e todos os dados dele
     * (perfil, currículos e arquivos, foto, candidaturas e matches).
     * Confirma com a senha (mesma tolerância e mesma trava contra tentativas do login) e com a caixa marcada.
     */
    public function excluirConta(): void {
        exigirLogin();
        if (!isCandidato()) negar_acesso('Acesso negado.');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('view/perfil/index.php');
        validar_csrf();

        $id = (int)$_SESSION['usuario_id'];
        $dao = new UsuarioDAO();
        $email = (string)($dao->buscarPorId($id)['email'] ?? '');
        $voltar = 'view/perfil/index.php#excluir-conta';
        if ($dao->minutosBloqueioLogin(ip_cliente(), $email) > 0) {
            flash('erro', 'Muitas senhas erradas. Aguarde alguns minutos e tente de novo.');
            redirect($voltar);
        }
        if (!isset($_POST['confirmo'])) {
            flash('erro', 'Marque a caixa de confirmação para excluir a conta.');
            redirect($voltar);
        }
        $senha = $_POST['senha'] ?? '';
        if (!is_string($senha) || !$dao->senhaConfere($id, $senha)) {
            $dao->registrarFalhaLogin(ip_cliente(), $email);
            flash('erro', 'Senha incorreta: a conta não foi excluída.');
            redirect($voltar);
        }
        if (!$dao->excluir($id)) {
            flash('erro', 'Não foi possível excluir a conta agora. Tente de novo em instantes.');
            redirect($voltar);
        }
        // Sessão da conta apagada encerrada; uma sessão nova só para mostrar a confirmação.
        encerrar_sessao();
        iniciar_sessao();
        flash('ok', 'Sua conta e todos os seus dados foram excluídos da plataforma.');
        redirect('index.php');
    }
}
