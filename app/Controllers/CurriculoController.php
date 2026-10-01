<?php
declare(strict_types=1);

/**
 * Currículo do candidato: envio com a MÁQUINA DE EXTRAÇÃO, aplicação dos dados
 * do relatório e exclusão. (A abertura do arquivo fica em ArquivoController::download.)
 */
final class CurriculoController extends Controller {

    /**
     * view/perfil/curriculo_upload.php (POST) — envio do currículo:
     *  1) valida e salva o arquivo;
     *  2) extrai o texto (PDF/DOCX/DOC) e separa TODOS os dados (contato, links, CNH, disponibilidade,
     *     pretensão, PCD, experiências, formação, cursos, habilidades, idiomas, foto embutida);
     *  3) aplica ao perfil — campos vazios são preenchidos; os já preenchidos são mantidos
     *     (ou substituídos, se o candidato marcar "substituir"); listas são mescladas;
     *  4) recalcula o match com as vagas ativas;
     *  5) abre o portfólio com o RELATÓRIO DA EXTRAÇÃO (o que foi encontrado, aplicado, mantido e o que falta).
     */
    public function upload(): void {
        exigirLogin();
        if (!isCandidato()) negar_acesso('Acesso negado.');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('view/perfil/index.php');
        // Acima do post_max_size do PHP o formulário chega vazio (sem o token): avisa o tamanho, não "sessão expirada".
        if (!$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            flash('erro', 'O arquivo é grande demais. Envie um currículo de até '.(int)(MAX_FILE_SIZE / 1024 / 1024).' MB.');
            redirect('view/perfil/index.php');
        }
        validar_csrf();

        $perfilDao = new PerfilDAO();
        $usuarioId = (int)$_SESSION['usuario_id'];
        $p = $perfilDao->obterOuCriar($usuarioId);
        $f = $_FILES['curriculo'] ?? null;
        if (is_array($f) && is_array($f['error'] ?? null)) $f = null; // campo enviado como lista (curriculo[])

        $erroUpload = [UPLOAD_ERR_INI_SIZE => 'O arquivo ultrapassa o limite do servidor.', UPLOAD_ERR_FORM_SIZE => 'O arquivo é grande demais.', UPLOAD_ERR_NO_FILE => 'Selecione um arquivo.'];
        if (!$p || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('erro', $erroUpload[$f['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Não foi possível receber o arquivo.');
            redirect('view/perfil/index.php');
        }
        if ((int)$f['size'] > MAX_FILE_SIZE) { flash('erro', 'O arquivo é grande demais. Envie um currículo de até '.(int)(MAX_FILE_SIZE / 1024 / 1024).' MB.'); redirect('view/perfil/index.php'); }

        $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf','doc','docx'], true)) { flash('erro', 'Envie o currículo em PDF, DOC ou DOCX.'); redirect('view/perfil/index.php'); }

        // Confere o conteúdo real do arquivo, não só a extensão.
        $cabeca = (string)file_get_contents($f['tmp_name'], false, null, 0, 8);
        $valido = match ($ext) {
            'pdf' => str_starts_with($cabeca, '%PDF'),
            'docx' => str_starts_with($cabeca, "PK\x03\x04"),
            'doc' => str_starts_with($cabeca, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),
        };
        if (!$valido) { flash('erro', 'O arquivo não parece ser um '.strtoupper($ext).' válido.'); redirect('view/perfil/index.php'); }

        $nomeArq = 'cv_'.$usuarioId.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $dest = UPLOAD_DIR.$nomeArq;
        if (!move_uploaded_file($f['tmp_name'], $dest)) { flash('erro', 'Não foi possível salvar o currículo no servidor.'); redirect('view/perfil/index.php'); }
        // Até o currículo ser gravado no banco o arquivo é provisório: se a requisição parar no meio
        // (exceção, falta de memória, tempo esgotado), ele é apagado no fim e não fica órfão na pasta.
        $gravado = false;
        register_shutdown_function(function () use (&$gravado, $dest): void { if (!$gravado) @unlink($dest); });

        // ---- extração
        try {
            $texto = ExtracaoCurriculo::extrair($dest);
            $metodo = ExtracaoCurriculo::metodo();
            $campos = ExtracaoCurriculo::extrairCampos($texto, LeitorDocumento::$destaques);
        } catch (Throwable) {
            @unlink($dest);
            flash('erro', 'Não foi possível ler o currículo. Tente novamente ou envie o arquivo em outro formato (PDF, DOC ou DOCX).');
            redirect('view/perfil/index.php');
        }
        if (LeitorDocumento::$grandeDemais) {
            // "Bomba de descompressão" ou arquivo fora do comum: poucos MB que viram centenas de MB ao abrir.
            @unlink($dest);
            flash('erro', 'Não foi possível ler este currículo: o conteúdo do arquivo fica grande demais ao ser aberto. Salve-o novamente (ex.: "Salvar como PDF" no Word ou no Google Docs) e envie outra vez.');
            redirect('view/perfil/index.php');
        }
        $substituir = isset($_POST['substituir']);

        // ---- herança para o perfil
        $calc = AplicacaoCurriculo::calcular($p, $campos, $substituir);
        $dados = $calc['dados']; $itens = $calc['itens'];

        // Foto embutida no arquivo (LeitorDocumento::extrairFoto, padrão de foto de currículo):
        // sem foto no perfil → vira a foto do perfil; já com foto → fica guardada para o candidato trocar, se quiser.
        $fotoAntigaPendente = (string)($_SESSION['relatorio_extracao']['pendentes']['foto'] ?? '');
        if ($fotoAntigaPendente !== '' && $fotoAntigaPendente !== (string)($p['foto'] ?? '')) apagar_upload_sem_uso($fotoAntigaPendente);   // de um relatório anterior, não usada
        $fotoArq = $texto !== '' ? LeitorDocumento::extrairFoto($dest) : null;
        $fotoItem = ['campo' => 'foto', 'rotulo' => 'Foto', 'valor' => '', 'status' => 'nao_encontrado', 'atual' => !empty($p['foto']) ? 'foto atual do perfil' : ''];
        $fotoPendente = null;
        if ($fotoArq) {
            $caminho = AplicacaoCurriculo::salvarFoto($fotoArq, $usuarioId);
            if ($caminho && empty($p['foto'])) {
                $dados['foto'] = $caminho;
                $fotoItem = array_merge($fotoItem, ['valor' => 'Foto encontrada no currículo ('.$fotoArq['largura'].'×'.$fotoArq['altura'].')', 'status' => 'aplicado', 'imagem' => $caminho]);
            } elseif ($caminho) {
                $fotoPendente = $caminho;
                $fotoItem = array_merge($fotoItem, ['valor' => 'Foto encontrada no currículo ('.$fotoArq['largura'].'×'.$fotoArq['altura'].')', 'status' => 'mantido', 'imagem' => $caminho]);
            }
        }

        if ($texto !== '' && !AplicacaoCurriculo::salvar($dados)) {
            @unlink($dest);
            if (!empty($caminho)) apagar_upload_sem_uso($caminho);
            flash('erro', 'Não foi possível atualizar o perfil com os dados do currículo.');
            redirect('view/perfil/index.php');
        }

        // ---- dados da conta: telefone (se vazio) e nome (só sugerido)
        $conta = [];
        $tel = $campos['telefone'];
        if ($tel === '') $conta[] = ['campo' => 'telefone', 'rotulo' => 'Telefone', 'valor' => '', 'status' => 'nao_encontrado', 'atual' => (string)($p['telefone'] ?? '')];
        elseif (trim((string)($p['telefone'] ?? '')) === '' || $substituir) {
            (new UsuarioDAO())->atualizarTelefone($usuarioId, mb_substr($tel, 0, 30));
            $conta[] = ['campo' => 'telefone', 'rotulo' => 'Telefone', 'valor' => $tel, 'status' => 'aplicado', 'atual' => (string)($p['telefone'] ?? '')];
        } else {
            $iguais = preg_replace('/\D/', '', $tel) === preg_replace('/\D/', '', (string)$p['telefone']);
            $conta[] = ['campo' => 'telefone', 'rotulo' => 'Telefone', 'valor' => $tel, 'status' => $iguais ? 'igual' : 'mantido', 'atual' => (string)$p['telefone']];
            if (!$iguais) $calc['pendentes']['telefone'] = $tel;
        }
        $nomeSugerido = '';
        if ($campos['nome'] === '') $conta[] = ['campo' => 'nome', 'rotulo' => 'Nome', 'valor' => '', 'status' => 'nao_encontrado', 'atual' => (string)$p['nome']];
        elseif (Competencias::normalizar($campos['nome']) === Competencias::normalizar((string)$p['nome'])) $conta[] = ['campo' => 'nome', 'rotulo' => 'Nome', 'valor' => $campos['nome'], 'status' => 'igual', 'atual' => (string)$p['nome']];
        else { $nomeSugerido = $campos['nome']; $conta[] = ['campo' => 'nome', 'rotulo' => 'Nome', 'valor' => $campos['nome'], 'status' => 'sugerido', 'atual' => (string)$p['nome']]; }
        if ($campos['email'] !== '') {
            $mesmo = strtolower($campos['email']) === strtolower((string)$p['email']);
            $conta[] = ['campo' => 'email', 'rotulo' => 'E-mail', 'valor' => $campos['email'], 'status' => $mesmo ? 'igual' : 'informativo', 'atual' => (string)$p['email']];
        } else {
            $conta[] = ['campo' => 'email', 'rotulo' => 'E-mail', 'valor' => '', 'status' => 'nao_encontrado', 'atual' => (string)$p['email']];
        }
        // Dados lidos que não viram campo: idade (sem data não dá para gravar o nascimento) e PCD (vai em informações adicionais).
        $extras = [];
        if ($campos['idade'] !== '' && $campos['data_nascimento'] === '') $extras[] = ['campo' => 'idade', 'rotulo' => 'Idade', 'valor' => $campos['idade'].' anos', 'status' => 'informativo', 'atual' => ''];
        if ($campos['pcd'] !== '') $extras[] = ['campo' => 'pcd', 'rotulo' => 'PCD', 'valor' => $campos['pcd'], 'status' => 'informativo', 'atual' => ''];

        $curriculoId = (new CurriculoDAO())->salvar([
            'perfil_id' => $p['id'],
            'titulo' => post_str('titulo') ?: 'Currículo profissional',
            'arquivo_pdf' => 'assets/uploads/'.$nomeArq, 'arquivo_tipo' => $ext, 'curriculo_texto' => $texto,
        ]);
        $gravado = true; // o registro agora aponta para o arquivo

        // ---- match
        $matchMsg = '';
        try {
            $m = (new MatchService())->recalcular((int)$p['id']);
            $matchMsg = "Match recalculado com {$m} vaga(s).";
        } catch (Throwable) {
            $matchMsg = 'O recálculo do match não pôde ser concluído agora.';
        }

        if ($texto === '') {
            unset($_SESSION['relatorio_extracao']);
            flash('info', 'Currículo salvo, mas não foi possível ler o texto do arquivo (pode ser uma imagem escaneada). Preencha o perfil manualmente para montar o portfólio. '.$matchMsg);
            redirect('view/perfil/index.php');
        }

        // ---- relatório (guardado na sessão; o portfólio mostra ao dono)
        $todos = array_merge($conta, [$fotoItem], $itens, $extras);
        $_SESSION['relatorio_extracao'] = [
            'arquivo' => mb_substr(basename((string)$f['name']), 0, 120),
            'curriculo_id' => $curriculoId,
            'metodo' => $metodo,
            'quando' => date('d/m/Y H:i'),
            'substituir' => $substituir,
            'itens' => array_map(fn($i) => ['campo' => $i['campo'], 'rotulo' => $i['rotulo'], 'valor' => AplicacaoCurriculo::resumo((string)$i['valor']), 'atual' => AplicacaoCurriculo::resumo((string)$i['atual'], 80), 'status' => $i['status'], 'imagem' => (string)($i['imagem'] ?? '')], $todos),
            'pendentes' => $calc['pendentes'] + ($fotoPendente ? ['foto' => $fotoPendente] : []),
            'nome_sugerido' => $nomeSugerido,
            'match' => $matchMsg,
        ];
        $n = count(array_filter($todos, fn($i) => in_array($i['status'], ['aplicado', 'mesclado'], true)));
        flash('ok', "Currículo lido ({$metodo}): {$n} dado(s) aplicado(s) ao perfil e o portfólio foi montado. {$matchMsg} Confira o relatório da extração abaixo.");
        redirect('view/perfil/portfolio.php?relatorio=1');
    }

    /**
     * view/perfil/aplicar_extracao.php (POST) — relatório da extração → "usar o dado do currículo":
     * aplica os valores que foram mantidos (porque o candidato já tinha outro) e, se ele pedir,
     * troca o nome da conta pelo nome encontrado no currículo.
     */
    public function aplicarExtracao(): void {
        exigirLogin();
        if (!isCandidato()) negar_acesso('Acesso negado.');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('view/perfil/portfolio.php');
        validar_csrf();

        $rel = $_SESSION['relatorio_extracao'] ?? null;
        if (!is_array($rel)) { flash('info', 'O relatório da extração expirou. Envie o currículo novamente, se quiser.'); redirect('view/perfil/index.php'); }

        $usuarioId = (int)$_SESSION['usuario_id'];
        $dao = new PerfilDAO();
        $usuarioDao = new UsuarioDAO();
        $p = $dao->obterOuCriar($usuarioId);
        $escolhidos = array_values(array_filter((array)($_POST['campos'] ?? []), 'is_string'));
        $pendentes = (array)($rel['pendentes'] ?? []);
        $aplicados = [];

        // Nome da conta (só quando o candidato confirma).
        if (in_array('nome', $escolhidos, true) && ($rel['nome_sugerido'] ?? '') !== '') {
            $nome = mb_substr(trim((string)$rel['nome_sugerido']), 0, 150);
            $usuarioDao->atualizarNome($usuarioId, $nome);
            $_SESSION['usuario_nome'] = $nome;
            $rel['nome_sugerido'] = '';
            $aplicados[] = 'nome';
        }
        if (in_array('telefone', $escolhidos, true) && isset($pendentes['telefone'])) {
            $usuarioDao->atualizarTelefone($usuarioId, mb_substr((string)$pendentes['telefone'], 0, 30));
            unset($pendentes['telefone']);
            $aplicados[] = 'telefone';
        }

        // Campos do perfil mantidos na extração.
        $dados = AplicacaoCurriculo::dtoDe($p);
        $mudouPerfil = false;
        // Foto do currículo no lugar da foto atual (o arquivo já foi guardado na leitura do currículo).
        $fotoTrocada = '';
        if (in_array('foto', $escolhidos, true) && isset($pendentes['foto']) && caminho_upload((string)$pendentes['foto']) !== null) {
            $fotoTrocada = (string)($p['foto'] ?? '');
            $dados['foto'] = (string)$pendentes['foto'];
            unset($pendentes['foto']);
            $aplicados[] = 'foto'; $mudouPerfil = true;
        }
        foreach ($escolhidos as $col) {
            if (!isset(AplicacaoCurriculo::CAMPOS[$col], $pendentes[$col])) continue;
            $dados[$col] = (string)$pendentes[$col];
            unset($pendentes[$col]);
            $aplicados[] = $col; $mudouPerfil = true;
        }
        if ($mudouPerfil && !AplicacaoCurriculo::salvar($dados)) { flash('erro', 'Não foi possível aplicar os dados escolhidos.'); redirect('view/perfil/portfolio.php?relatorio=1'); }
        if ($fotoTrocada !== '') apagar_upload_sem_uso($fotoTrocada);   // a foto antiga só sai depois que a nova foi salva

        // Atualiza o relatório: o que foi aplicado agora deixa de estar "mantido"/"sugerido".
        foreach ($rel['itens'] as &$i) if (in_array($i['campo'], $aplicados, true)) $i['status'] = 'aplicado';
        unset($i);
        $rel['pendentes'] = $pendentes;
        $_SESSION['relatorio_extracao'] = $rel;

        if (!$aplicados) { flash('info', 'Nenhum dado foi selecionado.'); redirect('view/perfil/portfolio.php?relatorio=1'); }
        $msg = count($aplicados).' dado(s) do currículo aplicados ao perfil.';
        if ($mudouPerfil) { try { $n = (new MatchService())->recalcular((int)$p['id']); $msg .= " Match recalculado com {$n} vaga(s)."; } catch (Throwable) {} }
        flash('ok', $msg);
        redirect('view/perfil/portfolio.php?relatorio=1');
    }

    /** view/perfil/curriculo_excluir.php (POST) — apaga o currículo (registro e arquivo) e recalcula o match. */
    public function excluir(): void {
        exigirLogin();
        if (!isCandidato()) negar_acesso('Acesso negado.');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('view/perfil/index.php');
        validar_csrf();

        $p = (new PerfilDAO())->buscarPorUsuarioId((int)$_SESSION['usuario_id']);
        $dao = new CurriculoDAO();
        $cv = $p ? $dao->buscarDoPerfil(post_int('id'), (int)$p['id']) : null;
        if (!$cv) { flash('erro', 'Currículo não encontrado.'); redirect('view/perfil/index.php'); }

        if ($dao->excluir((int)$cv['id'], (int)$p['id'])) {
            $path = caminho_upload((string)$cv['arquivo_pdf']);
            if ($path !== null && is_file($path)) @unlink($path);
            try { (new MatchService())->recalcular((int)$p['id']); } catch (Throwable) {}
            flash('ok', 'Currículo excluído. Candidaturas já enviadas com ele continuam registradas, sem o arquivo.');
        } else {
            flash('erro', 'Não foi possível excluir o currículo.');
        }
        redirect('view/perfil/index.php');
    }
}
