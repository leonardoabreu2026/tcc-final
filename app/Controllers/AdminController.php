<?php
declare(strict_types=1);

/**
 * Painel administrativo: visão geral (também usada pela empresa) e os CRUDs
 * exclusivos do administrador — usuários, categorias e cursos/e-books.
 * As telas do painel que a empresa usa (vagas, candidaturas...) ficam em EmpresaController.
 */
final class AdminController extends Controller {

    /**
     * admin/index.php — dashboard (estilo Power BI): indicadores + gráficos + listas recentes.
     * Administrador: o sistema todo; empresa: só as vagas e candidaturas dela.
     * Os números já vêm agrupados do banco (GROUP BY nos DAOs), sem uma consulta por linha.
     */
    public function painel(): void {
        exigirLogin();
        if (isCandidato()) redirect('view/perfil/index.php');

        $usuarioId = (int)$_SESSION['usuario_id'];
        $vagaDao = new VagaDAO();
        $candDao = new CandidaturaDAO();
        $dias = 30; // janela dos gráficos de atividade
        $perfil = isEmpresa() ? (new PerfilDAO())->obterOuCriar($usuarioId) : null;
        $pid = $perfil ? (int)$perfil['id'] : null; // null = administrador (tudo)
        if (isEmpresa() && !$pid) $pid = -1;         // sem perfil: nenhuma vaga, em vez de ver tudo

        $resumoVagas = $vagaDao->resumoPainel($pid);
        $porStatus = $candDao->contarPorStatus($pid);
        $porDia = $candDao->contarPorDia($dias, $pid);
        $matchCandidaturas = $candDao->resumoMatch($pid);
        $recentes = $candDao->listarRecentes(8, $pid);

        // Funil de seleção: cada etapa conta quem chegou nela ou passou dela (canceladas ficam de fora).
        $recebidas = array_sum($porStatus) - $porStatus['cancelada'];
        $funil = [
            'Recebidas' => $recebidas,
            'Analisadas' => $recebidas - $porStatus['enviada'],
            'Entrevista' => $porStatus['entrevista'] + $porStatus['aprovado'],
            'Aprovadas' => $porStatus['aprovado'],
        ];

        if (isAdmin()) {
            // Manutenção automática (no máximo 1x por dia): limpa arquivos órfãos.
            manutencao_diaria($usuarioId);
            $usuariosPorTipo = (new UsuarioDAO())->contarPorTipo($dias);
            $novosUsuarios = (new UsuarioDAO())->listarRecentes(5);
            $vagasPorArea = $vagaDao->contarPorCategoria();
            $vagasPorCidade = $vagaDao->contarPorCidade();
            $matchVagas = (new MatchDAO())->resumoVagasAbertas();
            $planos = (new AssinaturaDAO())->resumoPorPlano();
            $cursos = (new CursoDAO())->listar(false);
            $cursosPublicados = count(array_filter($cursos, fn($c) => (int)$c['ativo']));
            $ebooks = count(array_filter($cursos, fn($c) => (int)$c['ativo'] && $c['tipo'] === 'ebook'));
            $vagasRecentes = array_slice($vagaDao->listar(false), 0, 6);
            $isEmpresaPremium = false;
        } else {
            $desempenho = $vagaDao->desempenhoPorEmpresa((int)$pid);
            $isEmpresaPremium = (new AssinaturaDAO())->isEmpresaPremium($usuarioId);
        }

        $title = 'Painel';
        $abaAtiva = 'painel';
        $this->view('admin/painel', get_defined_vars());
    }

    /**
     * admin/pages/usuarios.php — CRUD de usuários.
     * Travas: o administrador não exclui nem rebaixa a própria conta, e o sistema
     * sempre mantém pelo menos um administrador ativo.
     */
    public function usuarios(): void {
        exigirAdmin();
        $dao = new UsuarioDAO();
        $meuId = (int)$_SESSION['usuario_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validar_csrf();
            $acao = post_str('acao');
            $id = post_int('id');

            if ($acao === 'excluir') {
                if ($id === $meuId) { flash('erro', 'Você não pode excluir a própria conta.'); redirect('admin/pages/usuarios.php'.painel_qs()); }
                $alvo = $dao->buscarPorId($id);
                if ($alvo && $alvo['tipo'] === 'admin' && (int)$alvo['ativo'] && $dao->contarAdminsAtivos() <= 1) { flash('erro', 'É preciso manter pelo menos um administrador ativo.'); redirect('admin/pages/usuarios.php'.painel_qs()); }
                $ok = $dao->excluir($id);
                flash($ok ? 'ok' : 'erro', $ok ? 'Usuário excluído (perfil, currículos, vagas e candidaturas foram removidos junto).' : 'Não foi possível excluir o usuário.');
                redirect('admin/pages/usuarios.php'.painel_qs());
            }

            if ($acao === 'ativar' || $acao === 'desativar') {
                $erro = $dao->alterarAtivo($id, $acao === 'ativar', $meuId);
                flash($erro === '' ? 'ok' : 'erro', $erro === '' ? ($acao === 'ativar' ? 'Conta ativada: o login volta a funcionar.' : 'Conta bloqueada: o login e a sessão aberta perdem o acesso.') : $erro);
                redirect('admin/pages/usuarios.php'.painel_qs());
            }

            $d = [
                'nome' => mb_substr(post_str('nome'), 0, 255),
                'email' => normalizar_email(post_str('email')),
                'tipo' => enum_val(post_str('tipo'), UsuarioDAO::TIPOS, 'candidato'),
                'telefone' => mb_substr(post_str('telefone'), 0, 30),
                'ativo' => post_int('ativo', 1) ? 1 : 0,
                'senha' => is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '',
            ];
            $erros = [];
            if ($d['nome'] === '') $erros[] = 'Informe o nome.';
            if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || strlen($d['email']) > 255) $erros[] = 'E-mail inválido.';
            if ((!$id || $d['senha'] !== '') && strlen($d['senha']) < 6) $erros[] = 'A senha precisa ter pelo menos 6 caracteres.';
            if (strlen($d['senha']) > 72) $erros[] = 'A senha pode ter no máximo 72 caracteres.'; // limite do bcrypt
            if ($id === $meuId && ($d['tipo'] !== 'admin' || !$d['ativo'])) $erros[] = 'Você não pode remover seu próprio acesso de administrador.';
            if ($erros) { flash('erro', implode(' ', $erros)); redirect('admin/pages/usuarios.php'.painel_qs($id ? ['edit' => $id] : [])); }

            $ok = $id ? $dao->atualizar($id, $d) : (bool)$dao->cadastrar(new UsuarioDTO($d));
            flash($ok ? 'ok' : 'erro', $ok ? ($id ? 'Usuário atualizado.' : 'Usuário criado'.($d['tipo'] !== 'admin' ? ' com perfil.' : '.')) : ($dao->erro ?: 'Não foi possível salvar.'));
            redirect('admin/pages/usuarios.php'.painel_qs(!$ok && $id ? ['edit' => $id] : []));
        }

        $edit = registro_encontrado(get_str('edit') !== '' ? $dao->buscarPorId((int)get_str('edit')) : null, 'edit', 'admin/pages/usuarios.php', 'Usuário não encontrado (pode ter sido excluído).');
        $ver = registro_encontrado(get_str('ver') !== '' ? $dao->resumo((int)get_str('ver')) : null, 'ver', 'admin/pages/usuarios.php', 'Usuário não encontrado (pode ter sido excluído).');
        $filtroTipo = enum_val(get_str('tipo'), UsuarioDAO::TIPOS, '');
        $busca = get_str('q');
        $filtroSituacao = enum_val(get_str('situacao'), ['ativo', 'bloqueado'], '');
        $usuarios = array_values(array_filter($dao->listar($filtroTipo ?: null, $busca),
            fn($u) => $filtroSituacao === '' || (int)$u['ativo'] === ($filtroSituacao === 'ativo' ? 1 : 0)));
        [$ordem, $dir] = lista_ordem(['id', 'nome', 'email', 'tipo', 'ativo', 'ultimo_acesso'], 'id', 'desc');
        $totalUsuarios = count($usuarios);
        [$usuarios, $pagina, $paginas] = paginar(ordenar_linhas($usuarios, $ordem, $dir), 25);
        $title = 'Usuários';
        $abaAtiva = 'usuarios';
        $this->view('admin/usuarios', get_defined_vars());
    }

    /**
     * admin/pages/assinaturas.php — CRUD de assinaturas (Candidato VIP e Empresa Premium).
     * Criar: concede o plano da conta (candidato → VIP, empresa → Premium) por N dias. Editar: valor, datas e situação.
     * Cancelar mantém o histórico; excluir apaga. Cada conta tem no máximo uma assinatura ativa.
     */
    public function assinaturas(): void {
        exigirAdmin();
        $dao = new AssinaturaDAO();
        $usuarioDao = new UsuarioDAO();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validar_csrf();
            $id = post_int('id');
            $acao = post_str('acao');
            if ($acao === 'cancelar') {
                $ok = $dao->cancelarPorId($id);
                flash($ok ? 'ok' : 'erro', $ok ? 'Assinatura cancelada: a conta voltou ao plano gratuito (o histórico fica guardado).' : 'Só dá para cancelar uma assinatura ativa.');
                redirect('admin/pages/assinaturas.php'.painel_qs());
            }
            if ($acao === 'excluir') {
                $ok = $dao->excluir($id);
                flash($ok ? 'ok' : 'erro', $ok ? 'Assinatura excluída do histórico.' : 'Assinatura não encontrada.');
                redirect('admin/pages/assinaturas.php'.painel_qs());
            }
            if ($acao === 'conceder') {
                $u = $usuarioDao->buscarPorId(post_int('usuario_id'));
                $plano = AssinaturaDAO::PLANO_DO_TIPO[$u['tipo'] ?? ''] ?? null;
                $dias = post_int('dias', 30);
                $valor = post_str('valor') === '' ? ($plano ? AssinaturaDAO::PRECOS[$plano] : 0.0) : decimal_ou_null(post_str('valor'));
                $erro = match (true) {
                    !$u => 'Escolha a conta.',
                    !$plano => 'Só contas de candidato ou de empresa têm plano.',
                    !(int)$u['ativo'] => 'Esta conta está bloqueada: ative-a antes de conceder um plano.',
                    $dias < 1 || $dias > 3660 => 'Informe a duração entre 1 e 3660 dias.',
                    $valor === null || $valor < 0 || $valor > 99999.99 => 'Informe um valor válido (0 ou mais; vazio = preço do plano).',
                    default => '',
                };
                $anterior = $u ? $dao->buscarAtivaPorUsuario((int)$u['id']) : null;
                $ok = $erro === '' && $dao->assinar((int)$u['id'], $plano, (float)$valor, $dias);
                flash($ok ? 'ok' : 'erro', $ok ? ($plano === 'empresa' ? 'Empresa Premium' : 'Candidato VIP').' concedido a '.$u['nome'].' por '.$dias.' dias.'.($anterior ? ' A assinatura que estava ativa (#'.(int)$anterior['id'].') foi cancelada e fica no histórico.' : '') : ($erro ?: 'Não foi possível conceder o plano.'));
                redirect('admin/pages/assinaturas.php'.painel_qs());
            }
            // Editar
            $d = ['valor' => decimal_ou_null(post_str('valor')), 'data_inicio' => data_ou_null(post_str('data_inicio')), 'data_fim' => data_ou_null(post_str('data_fim')),
                  'status' => enum_val(post_str('status'), AssinaturaDAO::STATUS, '')];
            $erro = $dao->atualizar($id, $d);
            flash($erro === '' ? 'ok' : 'erro', $erro === '' ? 'Assinatura atualizada.' : $erro);
            redirect('admin/pages/assinaturas.php'.painel_qs($erro !== '' ? ['edit' => $id] : []));
        }

        $edit = registro_encontrado(get_str('edit') !== '' ? $dao->buscar((int)get_str('edit')) : null, 'edit', 'admin/pages/assinaturas.php', 'Assinatura não encontrada (pode ter sido excluída).');
        $filtroPlano = enum_val(get_str('plano'), AssinaturaDAO::PLANOS, '');
        $filtroStatus = enum_val(get_str('status'), AssinaturaDAO::STATUS, '');
        $busca = get_str('q');
        $filtroUsuario = (int)get_str('usuario_id');
        $todas = $dao->listarTodas();
        $lista = $dao->listarTodas(['plano' => $filtroPlano, 'status' => $filtroStatus, 'q' => $busca, 'usuario_id' => $filtroUsuario]);
        [$ordem, $dir] = lista_ordem(['id', 'usuario_nome', 'plano', 'valor', 'data_inicio', 'data_fim', 'status'], 'id', 'desc');
        $totalLista = count($lista);
        [$lista, $pagina, $paginas] = paginar(ordenar_linhas($lista, $ordem, $dir), 25);
        $resumo = $dao->resumoPorPlano();
        $contas = array_values(array_filter($usuarioDao->listar(), fn($u) => isset(AssinaturaDAO::PLANO_DO_TIPO[$u['tipo']]) && (int)$u['ativo']));
        usort($contas, fn($a, $b) => strcasecmp((string)$a['nome'], (string)$b['nome']));
        $title = 'Assinaturas';
        $abaAtiva = 'assinaturas';
        $this->view('admin/assinaturas', get_defined_vars());
    }

    /** admin/pages/categorias.php — CRUD de categorias (áreas de vagas e de cursos). */
    public function categorias(): void {
        exigirAdmin();
        $dao = new CategoriaDAO();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validar_csrf();
            $id = post_int('id');
            if (post_str('acao') === 'excluir') {
                $ok = $dao->excluir($id);
                flash($ok ? 'ok' : 'erro', $ok ? 'Categoria excluída. Vagas e cursos que a usavam ficaram sem categoria.' : ($dao->erro ?: 'Categoria não encontrada.'));
                redirect('admin/pages/categorias.php'.painel_qs());
            }
            if (in_array(post_str('acao'), ['ativar', 'desativar'], true)) {
                $ativar = post_str('acao') === 'ativar';
                $ok = $dao->alterarAtivo($id, $ativar);
                flash($ok ? 'ok' : 'erro', $ok ? ($ativar ? 'Categoria ativada.' : 'Categoria desativada: some dos filtros, mas os itens continuam com ela.') : 'Categoria não encontrada.');
                redirect('admin/pages/categorias.php'.painel_qs());
            }
            $d = ['nome' => mb_substr(post_str('nome'), 0, 100), 'tipo' => enum_val(post_str('tipo'), CategoriaDAO::TIPOS, 'vaga'), 'ativo' => post_int('ativo', 1) ? 1 : 0];
            if ($d['nome'] === '') { flash('erro', 'Informe o nome da categoria.'); redirect('admin/pages/categorias.php'.painel_qs()); }
            if ($id && !$dao->buscar($id)) { flash('erro', 'Categoria não encontrada (pode ter sido excluída).'); redirect('admin/pages/categorias.php'.painel_qs()); }
            $ok = $dao->salvar($d, $id);
            flash($ok ? 'ok' : 'erro', $ok ? 'Categoria salva.' : $dao->erro);
            redirect('admin/pages/categorias.php'.painel_qs(!$ok && $id ? ['edit' => $id] : []));
        }

        $edit = registro_encontrado(get_str('edit') !== '' ? $dao->buscar((int)get_str('edit')) : null, 'edit', 'admin/pages/categorias.php', 'Categoria não encontrada (pode ter sido excluída).');
        $filtroTipo = enum_val(get_str('tipo'), CategoriaDAO::TIPOS, '');
        $busca = get_str('q');
        $buscaN = Competencias::normalizar($busca);
        $todasCats = $dao->listar();
        $cats = array_values(array_filter($todasCats, fn($c) => ($filtroTipo === '' || $c['tipo'] === $filtroTipo)
            && ($buscaN === '' || str_contains(Competencias::normalizar((string)$c['nome']), $buscaN))));
        [$ordem, $dir] = lista_ordem(['nome', 'tipo', 'ativo', 'em_uso'], 'nome', 'asc');
        $totalCats = count($cats);
        [$cats, $pagina, $paginas] = paginar(ordenar_linhas($cats, $ordem, $dir), 30);
        $title = 'Categorias';
        $abaAtiva = 'categorias';
        $this->view('admin/categorias', get_defined_vars());
    }

    /**
     * admin/pages/cursos.php — CRUD de cursos, e-books e vídeos, com UMA caixa de extração:
     *  - cola-se a ficha da IA de pesquisa (ou o texto de divulgação): 1 ficha → preenche o formulário para revisar;
     *    várias fichas → prévia para cadastrar as marcadas (importação em lote);
     *  - com imagem na ficha, ela é conferida e baixada; sem imagem, o conteúdo entra com a imagem padrão da
     *    plataforma (CursoDAO::imagemPadrao) e aparece na lista como "trocar imagem", para ajustar depois;
     *  - a área e a instituição sugeridas podem vir do Calibrador (termos do administrador e instituições já cadastradas).
     */
    public function cursos(): void {
        exigirAdmin();
        $dao = new CursoDAO();
        $catDao = new CategoriaDAO();
        $cats = $catDao->listar('curso');
        $form = null; $extraido = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validar_csrf();
            $id = post_int('id');
            $acao = post_str('acao');

            if ($acao === 'excluir') {
                $ok = $dao->excluir($id);
                flash($ok ? 'ok' : 'erro', $ok ? 'Conteúdo excluído.' : 'Conteúdo não encontrado.');
                redirect('admin/pages/cursos.php'.painel_qs());
            }

            if ($acao === 'ativar' || $acao === 'desativar') {
                $ok = $dao->alterarAtivo($id, $acao === 'ativar');
                flash($ok ? 'ok' : 'erro', $ok ? ($acao === 'ativar' ? 'Conteúdo publicado: já aparece para os usuários.' : 'Conteúdo ocultado: saiu da área pública.') : 'Conteúdo não encontrado.');
                redirect('admin/pages/cursos.php'.painel_qs());
            }

            // BIBLIOTECA: traz para a plataforma o PDF de todo e-book que ainda abre no site de origem ("Acessar" → "Baixar").
            if ($acao === 'pdfs_biblioteca') {
                set_time_limit(900);
                $naWeb = array_values(array_filter($dao->listar(false), fn($c) => $c['tipo'] === 'ebook' && url_http_valida((string)$c['url'])));
                $pdfs = ImagemRemota::pdfs(array_column($naWeb, 'url'));
                $ok = 0;
                foreach ($naWeb as $c) {
                    $p = $pdfs[$c['url']] ?? '';
                    if ($p === '') continue;
                    if ($dao->guardarNaBiblioteca((int)$c['id'], $p, self::comFonte((string)$c['descricao'], (string)$c['url']))) $ok++;
                    else apagar_upload_sem_uso($p);
                }
                $falta = count($naWeb) - $ok;
                flash($ok ? 'ok' : 'erro', ($ok ? "{$ok} PDF(s) guardado(s) na biblioteca: o botão desses e-books agora é \"Baixar\"." : 'Nenhum PDF foi baixado.')
                    .($falta ? " {$falta} e-book(s) continuam com \"Acessar\" (o link não levou a um PDF público; envie o arquivo em Editar)." : ''));
                redirect('admin/pages/cursos.php'.painel_qs(['tipo' => 'ebook']).'#lista-cursos');
            }

            // Várias fichas coladas: cadastra as marcadas na prévia.
            if ($acao === 'importar_salvar') {
                set_time_limit(300);   // baixa as imagens
                $itens = (array)($_SESSION['import_cursos'] ?? []);
                $marcados = array_map('intval', array_filter((array)($_POST['itens'] ?? []), 'is_scalar'));
                $catPorNome = array_column($cats, 'id', 'nome');
                // Mesmas regras do formulário: título, link http(s), sem repetir.
                $validos = [];
                foreach ($marcados as $i) {
                    $it = $itens[$i] ?? null;
                    if ($it && $it['titulo'] !== '' && url_http_valida($it['url']) && !$dao->buscarRepetido($it['url'], $it['titulo'], $it['instituicao'])) $validos[] = $it;
                }
                // Imagem: a da ficha/página (baixada para storage/uploads); sem ela, o banner da instituição ou a imagem padrão.
                $baixadas = ImagemRemota::baixar(array_map(fn($it) => (string)($it['imagem_url'] ?? ''), $validos), 'curso');
                // E-books: o PDF (do campo "PDF:" da ficha ou do link) vai para a BIBLIOTECA, se a opção ficou marcada na prévia.
                $fontePdf = fn(array $it) => (string)(($it['pdf_url'] ?? '') ?: $it['url']);
                $pdfs = isset($_POST['pdfs_biblioteca']) ? ImagemRemota::pdfs(array_map($fontePdf, array_filter($validos, fn($it) => $it['tipo'] === 'ebook'))) : [];
                $naBiblioteca = 0;
                $ok = 0; $comPadrao = 0; $pulados = count($marcados) - count($validos);
                foreach ($validos as $it) {
                    if ($dao->buscarRepetido($it['url'], $it['titulo'], $it['instituicao'])) { $pulados++; continue; }   // repetido dentro do próprio lote
                    $tipo = enum_val($it['tipo'], CursoDAO::TIPOS, 'curso');
                    $imagem = $baixadas[$it['imagem_url'] ?? ''] ?? caminho_imagem_valido((string)$it['imagem']);
                    if ($imagem === '') { $imagem = CursoDAO::imagemPadrao($tipo); $comPadrao++; }
                    $d = [
                        'categoria_id' => $catPorNome[$it['categoria']] ?? null, 'titulo' => mb_substr($it['titulo'], 0, 255), 'descricao' => (string)$it['descricao'],
                        'tipo' => $tipo, 'modalidade' => enum_val($it['modalidade'], CursoDAO::MODALIDADES, 'ead'),
                        'nivel' => enum_val($it['nivel'], CursoDAO::NIVEIS, 'iniciante'), 'duracao' => mb_substr((string)$it['duracao'], 0, 50),
                        'gratuito' => (int)$it['gratuito'] ? 1 : 0, 'preco' => (int)$it['gratuito'] ? null : $it['preco'], 'url' => mb_substr($it['url'], 0, 500),
                        'imagem' => $imagem, 'instituicao' => mb_substr((string)$it['instituicao'], 0, 255), 'ativo' => 1,
                    ];
                    if (!$d['gratuito'] && ($d['preco'] === null || $d['preco'] <= 0)) { $d['gratuito'] = 1; $d['preco'] = null; } // pago sem preço: publica como gratuito para revisar
                    if ($tipo === 'ebook' && ($pdf = $pdfs[$fontePdf($it)] ?? '') !== '') { $d['descricao'] = self::comFonte($d['descricao'], $d['url']); $d['url'] = $pdf; }
                    if ($dao->salvar($d)) { $ok++; if (eh_pdf_biblioteca($d['url'])) $naBiblioteca++; } else $pulados++;
                }
                foreach (array_merge(array_values($baixadas), array_values($pdfs)) as $arq) apagar_upload_sem_uso($arq);   // imagem/PDF baixado de item que acabou não entrando
                unset($_SESSION['import_cursos']);
                flash($ok ? 'ok' : 'erro', $ok ? "{$ok} conteúdo(s) cadastrado(s) e publicado(s)".($naBiblioteca ? "; {$naBiblioteca} com o PDF na biblioteca (botão \"Baixar\")" : '').($comPadrao ? "; {$comPadrao} com a imagem padrão (troque depois em Editar)" : '').($pulados ? "; {$pulados} pulado(s) (sem link, sem título ou repetido)." : '.') : 'Nenhum conteúdo cadastrado. Marque as fichas que quer importar.');
                redirect('admin/pages/cursos.php');
            }
            if ($acao === 'importar_cancelar') { unset($_SESSION['import_cursos']); redirect('admin/pages/cursos.php'); }

            if ($acao === 'extrair') {
                // Caixa única de extração: ficha(s) da IA de pesquisa ou texto de divulgação. Nada é salvo aqui.
                $texto = post_str('texto_anuncio');
                $nomesCat = array_column($cats, 'nome');
                $fichasLidas = preg_match('/^\s*(?:\d+[.)]\s*)?(?:\*\*)?t[ií]tulo(?:\*\*)?\s*:/imu', $texto) ? ExtracaoCurso::fichas($texto, $nomesCat) : [];
                if (count($fichasLidas) > 1) {
                    // Várias fichas → prévia (importação em lote).
                    set_time_limit(300);   // confere as imagens na internet
                    $itens = ImagemRemota::completar(array_slice($fichasLidas, 0, 100));
                    foreach ($itens as &$it) {
                        $it['problemas'] = [];
                        if ($it['titulo'] === '') $it['problemas'][] = 'sem título';
                        if (!url_http_valida($it['url'])) $it['problemas'][] = 'sem link válido';
                        $rep = $dao->buscarRepetido($it['url'], $it['titulo'], $it['instituicao']);
                        if ($rep) $it['problemas'][] = 'já cadastrado (#'.(int)$rep['id'].')';
                        $alertas = [];
                        if ($it['imagem_url'] === '' && $it['imagem'] === '') $alertas[] = 'sem imagem: entra com a imagem padrão';
                        if (!$it['gratuito'] && !$it['preco']) $alertas[] = 'pago sem preço: entra como gratuito';
                        $it['alerta'] = implode('; ', $alertas);
                    }
                    unset($it);
                    $_SESSION['import_cursos'] = $itens;
                    flash('info', count($itens).' fichas lidas. Confira a prévia e cadastre as marcadas.');
                    redirect('admin/pages/cursos.php#extrair');
                }
                if ($fichasLidas) {
                    set_time_limit(120);   // confere a imagem da ficha (ou acha a da página do curso)
                    $extraido = ImagemRemota::completar([$fichasLidas[0]])[0];
                } else {
                    $extraido = ExtracaoCurso::doTexto($texto);
                }
                $cat = $extraido['categoria'] ? $catDao->buscarPorNome($extraido['categoria'], 'curso') : null;
                $imgLink = (string)($extraido['imagem_url'] ?? '');
                $form = $extraido + ['id' => $id, 'categoria_id' => $cat['id'] ?? null, 'ativo' => 1];
                // Imagem: a do link (baixada ao salvar); sem ela, o banner da instituição (ou a padrão, ao salvar).
                $form['imagem'] = $imgLink !== '' ? '' : ExtracaoCurso::capa($extraido['instituicao'], $extraido['url'], $extraido['tipo']);
                $form['imagem_url'] = $imgLink;
            } else {
                $d = [
                    'categoria_id' => post_int('categoria_id') ?: null,
                    'titulo' => mb_substr(post_str('titulo'), 0, 255),
                    'descricao' => post_str('descricao'),
                    'tipo' => enum_val(post_str('tipo'), CursoDAO::TIPOS, 'curso'),
                    'modalidade' => enum_val(post_str('modalidade'), CursoDAO::MODALIDADES, 'ead'),
                    'nivel' => enum_val(post_str('nivel'), CursoDAO::NIVEIS, 'iniciante'),
                    'duracao' => mb_substr(post_str('duracao'), 0, 50),
                    'gratuito' => isset($_POST['gratuito']) ? 1 : 0,
                    'preco' => decimal_ou_null(post_str('preco')),
                    'url' => mb_substr(post_str('url'), 0, 500),
                    'imagem' => caminho_imagem_valido(mb_substr(post_str('imagem'), 0, 255)),
                    'instituicao' => mb_substr(FontesCursos::nomeOficial(post_str('instituicao'), post_str('url')), 0, 255),   // nome padronizado pelo link oficial
                    'ativo' => isset($_POST['ativo']) ? 1 : 0,
                ];
                $existente = $id ? $dao->buscar($id) : null;
                $erros = [];
                if ($id && !$existente) $erros[] = 'Conteúdo não encontrado (pode ter sido excluído).';
                if ($d['titulo'] === '') $erros[] = 'Informe o título.';
                // Só http/https: impede links "javascript:" no botão do curso.
                if ($d['url'] !== '' && !url_http_valida($d['url']) && !eh_pdf_biblioteca($d['url'])) $erros[] = 'O link oficial precisa ser um endereço válido começando com http:// ou https://.';
                // PDF enviado para a BIBLIOTECA da plataforma: vira o link do conteúdo e o botão passa a ser "Baixar".
                $pdf = salvar_pdf_biblioteca('arquivo_pdf');
                if ($pdf === false) $erros[] = 'PDF inválido: envie um arquivo PDF de até '.(int)(MAX_PDF_BIBLIOTECA / 1024 / 1024).' MB.';
                elseif ($pdf !== null) $d['url'] = $pdf;
                if (post_str('imagem') !== '' && $d['imagem'] === '') $erros[] = 'Caminho de imagem inválido (use um arquivo de assets/img ou envie uma imagem).';
                if ($d['categoria_id'] && !in_array((int)$d['categoria_id'], array_map(fn($c) => (int)$c['id'], $cats), true)) $d['categoria_id'] = null; // só categorias de curso
                if (!$d['gratuito'] && ($d['preco'] === null || $d['preco'] <= 0)) $erros[] = 'Informe o preço do conteúdo pago (ou marque como gratuito).';
                if ($d['preco'] !== null && $d['preco'] > 99999999.99) $erros[] = 'Preço fora do intervalo permitido.';
                $img = salvar_imagem_enviada('imagem_arquivo', 'curso');
                if ($img === false) $erros[] = 'Imagem inválida (use JPG, PNG ou WEBP até 3 MB).';
                elseif ($img !== null) $d['imagem'] = $img;
                // PDF da WEB para a BIBLIOTECA ("Guardar o PDF na biblioteca" marcado e nenhum arquivo enviado): baixa o PDF
                // do campo "Link direto do PDF", ou do link oficial (se for a página, acha o PDF nela). Sem PDF, fica "Acessar".
                $pdfLink = mb_substr(post_str('pdf_url'), 0, 500);
                if ($pdfLink !== '' && !url_http_valida($pdfLink)) $erros[] = 'O link do PDF precisa começar com http:// ou https://.';
                $avisoPdf = ''; $doLink = ''; $urlWeb = $d['url'];
                if (!$erros && $pdf === null && isset($_POST['pdf_biblioteca']) && !eh_pdf_biblioteca($d['url'])) {
                    $fonte = $pdfLink !== '' ? $pdfLink : $d['url'];
                    if (url_http_valida($fonte)) {
                        set_time_limit(300);
                        $doLink = ImagemRemota::pdfs([$fonte])[$fonte] ?? '';
                        if ($doLink !== '') {
                            $pdf = $doLink;
                            $d['descricao'] = self::comFonte($d['descricao'], $d['url'] !== '' ? $d['url'] : $fonte);
                            $d['url'] = $doLink;
                            $avisoPdf = ' PDF guardado na biblioteca: o botão agora é "Baixar".';
                        } else {
                            if ($d['url'] === '') $d['url'] = $fonte;
                            $avisoPdf = ' Não achei um PDF público no link: ficou com o botão "Acessar" (para guardar na biblioteca, envie o arquivo).';
                        }
                    }
                }
                // Imagem por LINK (ex.: a que veio na ficha): baixada para storage/uploads só ao salvar,
                // e só quando não veio arquivo nem caminho.
                $imgLink = mb_substr(post_str('imagem_url'), 0, 500);
                if ($imgLink !== '' && !url_http_valida($imgLink)) $erros[] = 'O link da imagem precisa começar com http:// ou https://.';
                $baixada = '';
                if (!$erros && $img === null && $d['imagem'] === '' && $imgLink !== '') {
                    set_time_limit(120);
                    $baixada = ImagemRemota::baixar([$imgLink], 'curso')[$imgLink] ?? '';
                    if ($baixada === '') $erros[] = 'Não foi possível baixar a imagem do link (precisa ser uma imagem JPG, PNG ou WEBP pública). Apague o link para cadastrar com a imagem padrão, envie o arquivo ou escolha um caminho.';
                    else $d['imagem'] = $baixada;
                }
                // Sem imagem nenhuma: entra com o banner da instituição ou a imagem padrão da plataforma (troca depois).
                $usouPadrao = false;
                if (!$erros && $d['imagem'] === '') {
                    $d['imagem'] = ExtracaoCurso::capa($d['instituicao'], $d['url'], $d['tipo']) ?: CursoDAO::imagemPadrao($d['tipo']);
                    $usouPadrao = CursoDAO::ehImagemPadrao($d['imagem']);
                }
                if ($erros) {
                    if ($img) { apagar_upload_sem_uso($img); $d['imagem'] = $existente['imagem'] ?? ''; } // não deixa arquivo órfão
                    if (is_string($pdf)) { apagar_upload_sem_uso($pdf); $d['url'] = $doLink !== '' ? $urlWeb : ($existente['url'] ?? ''); }
                    if ($baixada !== '') { apagar_upload_sem_uso($baixada); $d['imagem'] = $existente['imagem'] ?? ''; }
                    flash('erro', implode(' ', $erros));
                    $form = $d + ['id' => $id, 'imagem_url' => $imgLink, 'pdf_url' => $pdfLink];
                } else {
                    $ok = $dao->salvar($d, $id);
                    if ($ok && $existente && ($existente['imagem'] ?? '') !== $d['imagem']) apagar_upload_sem_uso((string)$existente['imagem']);
                    if ($ok && $existente && ($existente['url'] ?? '') !== $d['url']) apagar_upload_sem_uso((string)$existente['url']);   // PDF antigo da biblioteca
                    if (!$ok && is_string($pdf)) apagar_upload_sem_uso($pdf);
                    flash($ok ? 'ok' : 'erro', $ok ? 'Conteúdo salvo.'.$avisoPdf.($usouPadrao ? ' Entrou com a imagem padrão da plataforma: quando tiver a imagem certa, use Editar para trocar.' : '') : 'Não foi possível salvar.');
                    redirect('admin/pages/cursos.php'.painel_qs());   // volta para a mesma aba, filtros e ordem
                }
            }
        }

        $edit = registro_encontrado(get_str('edit') !== '' ? $dao->buscar((int)get_str('edit')) : null, 'edit', 'admin/pages/cursos.php', 'Conteúdo não encontrado (pode ter sido excluído).');
        // "Novo e-book" / "Novo vídeo" (?novo=ebook): o formulário já vem no formato escolhido.
        $novoTipo = enum_val(get_str('novo') ?: get_str('tipo'), CursoDAO::TIPOS, 'curso');
        $form ??= $edit ?? ['id' => 0, 'categoria_id' => null, 'titulo' => '', 'descricao' => '', 'tipo' => $novoTipo, 'modalidade' => 'ead', 'nivel' => 'iniciante', 'duracao' => '', 'gratuito' => 1, 'preco' => null, 'url' => '', 'imagem' => '', 'instituicao' => '', 'ativo' => 1];

        // LISTA: abas por formato (Cursos · E-books · Vídeos), filtros, ordenação por coluna e paginação.
        $filtroTipo = enum_val(get_str('tipo'), CursoDAO::TIPOS, '');
        $busca = get_str('q');
        $filtroCat = (int)get_str('categoria_id');
        $filtroSituacao = enum_val(get_str('situacao'), ['publicado', 'oculto', 'imagem_padrao'], '');
        $base = array_values(array_filter($dao->listar(false, ['q' => $busca, 'categoria_id' => $filtroCat]), fn($c) => match ($filtroSituacao) {
            'publicado' => (int)$c['ativo'] === 1, 'oculto' => (int)$c['ativo'] === 0, 'imagem_padrao' => CursoDAO::ehImagemPadrao((string)$c['imagem']), default => true,
        }));
        $porTipo = array_count_values(array_column($base, 'tipo'));   // contagem das abas já com os outros filtros
        $lista = $filtroTipo === '' ? $base : array_values(array_filter($base, fn($c) => $c['tipo'] === $filtroTipo));
        [$ordem, $dir] = lista_ordem(['titulo', 'tipo', 'categoria_nome', 'instituicao', 'ativo', 'created_at', 'id'], 'created_at', 'desc');
        $totalLista = count($lista);
        [$lista, $pagina, $paginas] = paginar(ordenar_linhas($lista, $ordem, $dir), 25);
        $comFiltro = $busca !== '' || $filtroCat || $filtroSituacao !== '';
        $comImagemPadrao = count(array_filter($dao->listar(false), fn($c) => CursoDAO::ehImagemPadrao((string)$c['imagem'])));
        // E-books que ainda abrem o PDF no site de origem: o botão "Trazer os PDFs para a biblioteca" resolve todos de uma vez.
        $ebooksNaWeb = count(array_filter($dao->listar(false), fn($c) => $c['tipo'] === 'ebook' && url_http_valida((string)$c['url'])));
        $imagens = array_merge(imagens_da_pasta('assets/img/cursos'), imagens_da_pasta('assets/img/cursos/capas'), imagens_da_pasta('assets/img/ebooks'), imagens_da_pasta('assets/img/padrao'));
        $importacao = (array)($_SESSION['import_cursos'] ?? []);
        $title = 'Cursos e e-books';
        $abaAtiva = 'cursos';
        $this->view('admin/cursos', get_defined_vars());
    }

    /** Descrição + de onde veio o PDF guardado na biblioteca (crédito da fonte; não repete se já estiver lá). */
    private static function comFonte(string $descricao, string $link): string {
        if ($link === '' || !url_http_valida($link) || str_contains($descricao, $link)) return $descricao;
        return ltrim(rtrim($descricao)."\n\nFonte original: ".$link);
    }
}
