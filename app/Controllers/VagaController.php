<?php
declare(strict_types=1);

/**
 * Vagas de emprego (área pública): lista com filtros (vagas.php) e página da vaga (vaga.php).
 * O cadastro/edição de vagas fica no painel: EmpresaController::vagas().
 */
final class VagaController extends Controller {
    /**
     * vagas.php — faixa de título, filtros, abas por área e grade de cartões
     * (mesmo cartão da página inicial), com paginação. Candidato logado vê o % de match e pode ordenar por ele.
     */
    public function lista(): void {
        $filtros = [
            'q' => get_str('q'), 'cidade' => get_str('cidade'), 'categoria_id' => (int)get_str('categoria_id'),
            'nivel' => get_str('nivel'), 'remoto' => get_str('remoto'), 'tipo' => get_str('tipo'),
        ];
        $vagas = []; $todas = []; $categorias = []; $dbErro = null;
        try {
            $dao = new VagaDAO();
            $categorias = (new CategoriaDAO())->listar('vaga', true);
            // Só áreas de vaga ativas valem como filtro (um id de categoria de curso não filtra nada).
            if ($filtros['categoria_id'] && !in_array($filtros['categoria_id'], array_map(fn($c) => (int)$c['id'], $categorias), true)) $filtros['categoria_id'] = 0;
            $todas = $dao->listar(true);
            $vagas = array_filter(array_values($filtros)) ? $dao->listar(true, $filtros) : $todas; // sem filtro: reaproveita a mesma consulta
        } catch (Throwable $e) { $dbErro = mensagem_erro_banco($e); }

        // Candidato logado: % de match e candidaturas já enviadas.
        $mapaMatch = []; $minhas = [];
        if (usuarioLogado() && isCandidato() && !$dbErro) {
            $p = (new PerfilDAO())->buscarPorUsuarioId((int)$_SESSION['usuario_id']);
            if ($p) {
                $mapaMatch = (new MatchDAO())->mapaPorCandidato((int)$p['id']);
                foreach ((new CandidaturaDAO())->listarPorCandidato((int)$p['id']) as $c) if ($c['status'] !== 'cancelada') $minhas[(int)$c['vaga_id']] = $c['status'];
            }
        }
        $ordenarMatch = get_str('ordem') === 'match' && $mapaMatch;
        if ($ordenarMatch) usort($vagas, fn($a, $b) => ($mapaMatch[(int)$b['id']] ?? 0) <=> ($mapaMatch[(int)$a['id']] ?? 0));

        // Paginação: 20 cartões por página (4 fileiras de 5 no computador).
        $porPagina = 20;
        $total = count($vagas);
        $paginas = max(1, (int)ceil($total / $porPagina));
        $pagina = min(max(1, (int)get_str('pagina')), $paginas);
        $vagas = array_slice($vagas, ($pagina - 1) * $porPagina, $porPagina);

        // Área atual e contagem de vagas por área (para as abas).
        $catAtual = null;
        foreach ($categorias as $c) if ((int)$c['id'] === $filtros['categoria_id']) $catAtual = $c;
        $contagem = [];
        foreach ($todas as $v) $contagem[(int)$v['categoria_id']] = ($contagem[(int)$v['categoria_id']] ?? 0) + 1;
        // Links que mantêm os filtros e a ordem atuais; $extra troca ou remove valores (['q' => ''] tira a busca).
        $base = $filtros + ['ordem' => $ordenarMatch ? 'match' : ''];
        $qs = fn(array $extra) => url('vagas.php'.(($q = http_build_query(array_filter($extra + $base))) ? '?'.$q : ''));
        // Filtros em uso, cada um com o link que o remove (etiquetas acima da grade).
        $filtrosAtivos = [];
        foreach (['q' => 'Busca', 'cidade' => 'Cidade', 'tipo' => 'Contratação', 'nivel' => 'Nível', 'remoto' => 'Modelo'] as $k => $nome) {
            if ($filtros[$k] === '') continue;
            $filtrosAtivos[] = ['texto' => $nome.': '.(in_array($k, ['q', 'cidade'], true) ? $filtros[$k] : rotulo($filtros[$k])), 'link' => $qs([$k => ''])];
        }
        $trilha = $catAtual ? ['Vagas' => url('vagas.php'), $catAtual['nome'] => ''] : ['Vagas' => ''];

        $title = $catAtual ? 'Vagas em '.$catAtual['nome'] : 'Vagas de emprego';
        $layoutLargo = true;
        $descricaoPagina = 'Vagas de emprego abertas no Distrito Federal'.($catAtual ? ' na área de '.$catAtual['nome'] : '').'.';
        $this->view('vagas/lista', get_defined_vars());
    }

    /**
     * vaga.php?id= — página no formato de anúncio: cartaz (amplia ao clicar), o match do candidato
     * e cursos relacionados; título, empresa, salário, contato e as seções (descrição, requisitos
     * com as competências, benefícios); caixa de candidatura fixa na lateral. Abaixo, outras vagas.
     */
    public function detalhe(): void {
        $id = (int)get_str('id');
        $dao = new VagaDAO();
        $vaga = $dao->buscar($id);
        $aberta = $vaga && $dao->estaAberta($vaga);
        // Vaga pausada, encerrada ou cancelada: só a empresa dona e o admin veem.
        $ehDona = $vaga && usuarioLogado() && (int)($vaga['empresa_usuario_id'] ?? 0) === (int)$_SESSION['usuario_id'];
        if (!$vaga || (!$aberta && !$ehDona && !isAdmin())) {
            http_response_code(404);
            $title = 'Vaga não encontrada';
            $layoutLargo = true;
            $this->view('vagas/nao_encontrada', get_defined_vars());
            return;
        }
        // Uma visualização por visitante (sessão), para o F5 não inflar o "Desempenho" da empresa.
        if ($aberta && !$ehDona && empty($_SESSION['vagas_vistas'][$id])) {
            $dao->incrementarVisualizacao($id);
            $vaga['visualizacoes'] = (int)$vaga['visualizacoes'] + 1;
            $_SESSION['vagas_vistas'][$id] = 1;
        }

        $competencias = Competencias::daVaga($vaga);
        $match = null; $candidatura = null; $recom = [];
        $cursosAtivos = [];
        try { $cursosAtivos = (new CursoDAO())->listar(true); } catch (Throwable) {}
        if (usuarioLogado() && isCandidato()) {
            $p = (new PerfilDAO())->buscarPorUsuarioId((int)$_SESSION['usuario_id']);
            if ($p) {
                $match = (new MatchDAO())->buscar((int)$p['id'], $id);
                $candidatura = (new CandidaturaDAO())->buscarDoCandidato((int)$p['id'], $id);
                if ($match) $recom = Competencias::cursosPara($match['detalhes']['competencias']['faltantes'] ?? [], $cursosAtivos, 3);
            }
        }
        // Cursos que ensinam o que a vaga pede (quando o candidato não tem recomendação própria).
        $cursosRel = $recom ?: Competencias::cursosParaVaga($competencias, $cursosAtivos, 3);

        // Outras vagas: mesma área primeiro, depois as mais recentes.
        $relacionadas = [];
        try {
            $abertas = pt_ordenar_recentes($dao->listar(true));
            $mesmaArea = array_filter($abertas, fn($v) => (int)$v['id'] !== $id && (int)$v['categoria_id'] === (int)$vaga['categoria_id']);
            $outras = array_filter($abertas, fn($v) => (int)$v['id'] !== $id && (int)$v['categoria_id'] !== (int)$vaga['categoria_id']);
            $relacionadas = array_slice([...$mesmaArea, ...$outras], 0, 5);
        } catch (Throwable) {}

        $empresa = $vaga['empresa_nome'] ?: 'Empresa';
        $quando = pt_quando($vaga);
        $atendidas = $match['detalhes']['competencias']['atendidas'] ?? [];
        $linkVaga = url('vaga.php?id='.$id);
        $textoShare = $vaga['titulo'].' — '.pt_linha_fina_vaga($vaga);
        // Anúncio já formatado para a tela: salário, listas (uma linha = um item) e contatos clicáveis.
        $salario = salario_texto($vaga['salario_minimo'], $vaga['salario_maximo']);
        $requisitos = pt_itens_vaga($vaga['requisitos']);
        $beneficios = pt_itens_vaga($vaga['beneficios']);
        $contatos = pt_contatos_vaga($vaga['contato'] ?? '', 'Olá! Vi a vaga de '.$vaga['titulo'].' no Conecta Vagas DF e gostaria de mais informações.');
        $expira = $vaga['data_expiracao'] ? date('d/m/Y', strtotime($vaga['data_expiracao'])) : '';
        $trilha = ['Vagas' => url('vagas.php')];
        if (!empty($vaga['categoria_nome'])) $trilha[$vaga['categoria_nome']] = url('vagas.php?categoria_id='.(int)$vaga['categoria_id']);
        $trilha[$vaga['titulo']] = '';

        $title = $vaga['titulo'];
        $layoutLargo = true;
        $descricaoPagina = pt_linha_fina_vaga($vaga).'. '.pt_lide_vaga($vaga);
        $ogImagem = $vaga['imagem'] ?: null;
        $this->view('vagas/detalhe', get_defined_vars());
    }
}
