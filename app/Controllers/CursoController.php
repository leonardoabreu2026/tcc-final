<?php
declare(strict_types=1);

/**
 * Cursos, e-books e vídeos (área pública): lista (cursos.php) e página do conteúdo (curso.php).
 * O cadastro/edição fica no painel do administrador: AdminController::cursos().
 */
final class CursoController extends Controller {
    /**
     * cursos.php — uma página por formato, sem misturar: cursos (padrão), e-books (?tipo=ebook)
     * e vídeos (?tipo=video). Faixa de título, abas por formato, filtros, "Recomendados para você"
     * (candidato, só na aba de cursos) e grade de cartões paginada.
     */
    public function lista(): void {
        $tipo = enum_val(get_str('tipo'), CursoDAO::TIPOS, 'curso');
        $filtros = ['q' => get_str('q'), 'categoria_id' => (int)get_str('categoria_id'), 'gratuito' => get_str('gratuito')];
        // Ordem da vitrine: mais recentes (padrão, como vem do banco), título A–Z ou instituição A–Z.
        $ordens = ['' => 'Mais recentes', 'titulo' => 'Título (A–Z)', 'instituicao' => 'Instituição (A–Z)'];
        $ordem = enum_val(get_str('ordem'), array_keys($ordens), '');
        $cursos = []; $categorias = []; $porTipo = array_fill_keys(CursoDAO::TIPOS, 0); $porArea = []; $dbErro = null;
        try {
            // Busca e preço vêm do banco; formato e área são separados aqui, para contar as abas e os atalhos de área.
            $base = (new CursoDAO())->listar(true, ['q' => $filtros['q'], 'gratuito' => $filtros['gratuito']]);
            $naArea = fn($c) => !$filtros['categoria_id'] || (int)$c['categoria_id'] === $filtros['categoria_id'];
            foreach ($base as $c) {
                if ($naArea($c) && isset($porTipo[$c['tipo']])) $porTipo[$c['tipo']]++;             // contagem das abas
                if ($c['tipo'] === $tipo && $c['categoria_id']) $porArea[(int)$c['categoria_id']] = ($porArea[(int)$c['categoria_id']] ?? 0) + 1;
            }
            $cursos = array_values(array_filter($base, fn($c) => $c['tipo'] === $tipo && $naArea($c)));
            if ($ordem !== '') $cursos = ordenar_linhas($cursos, $ordem, 'asc');
            $categorias = (new CategoriaDAO())->listar('curso', true);
        } catch (Throwable $e) { $dbErro = mensagem_erro_banco($e); }
        // Atalhos de área: só as áreas que têm conteúdo neste formato, da maior para a menor.
        $atalhosArea = array_values(array_filter($categorias, fn($c) => isset($porArea[(int)$c['id']])));
        usort($atalhosArea, fn($a, $b) => [$porArea[(int)$b['id']], $a['nome']] <=> [$porArea[(int)$a['id']], $b['nome']]);

        // Candidato: cursos que cobrem as competências que mais faltam nas vagas com melhor match.
        $recomendados = []; $faltantesTop = [];
        if ($tipo === 'curso' && usuarioLogado() && isCandidato() && !$dbErro) {
            $p = (new PerfilDAO())->buscarPorUsuarioId((int)$_SESSION['usuario_id']);
            if ($p) {
                $contagem = [];
                foreach (array_slice((new MatchDAO())->listarPorCandidato((int)$p['id']), 0, 5) as $m) {
                    foreach ($m['detalhes']['competencias']['faltantes'] ?? [] as $c) $contagem[$c] = ($contagem[$c] ?? 0) + 1;
                }
                arsort($contagem);
                $faltantesTop = array_slice(array_keys($contagem), 0, 6);
                $soCursos = array_filter((new CursoDAO())->listar(true), fn($c) => $c['tipo'] === 'curso');
                $recomendados = Competencias::cursosPara($faltantesTop, array_values($soCursos), 3);
            }
        }

        $cabecalhos = [
            'curso' => ['Cursos Gratuitos', 'Cursos gratuitos e reconhecidos para fortalecer seu currículo e aumentar seu match com as vagas.'],
            'ebook' => ['E-books', 'Guias e materiais para ler no seu ritmo e se preparar para o mercado de trabalho.'],
            'video' => ['Vídeos', 'Aulas e conteúdos em vídeo para aprender na prática.'],
        ];
        [$tituloPag, $subPag] = $cabecalhos[$tipo];
        $nomeFormato = mb_strtolower(pt_secao_formato($tipo)[0]);   // "cursos", "e-books", "vídeos"
        // Link de cada aba/página: "cursos.php" (cursos), "?tipo=ebook", "?tipo=video" — mantendo os filtros.
        $qsFiltros = array_filter(['q' => $filtros['q'], 'categoria_id' => $filtros['categoria_id'] ?: '', 'gratuito' => $filtros['gratuito'], 'ordem' => $ordem]);
        $linkLista = fn(string $t, array $extra = []) => url('cursos.php'.(($q = http_build_query(array_filter($extra + $qsFiltros + ($t !== 'curso' ? ['tipo' => $t] : [])))) !== '' ? '?'.$q : ''));
        $abaUrl = fn(string $t) => $linkLista($t);
        $limparUrl = pt_secao_formato($tipo)[1];

        // Paginação: 20 cartões por página (mesmo padrão da lista de vagas).
        $porPagina = 20;
        $encontrados = count($cursos);
        $paginas = max(1, (int)ceil($encontrados / $porPagina));
        $pagina = min(max(1, (int)get_str('pagina')), $paginas);
        $cursos = array_slice($cursos, ($pagina - 1) * $porPagina, $porPagina);
        $qs = fn(array $extra) => $linkLista($tipo, ($extra['pagina'] ?? 0) > 1 ? $extra : []);   // página 1 = endereço limpo

        $title = $tipo === 'curso' ? 'Cursos gratuitos' : $tituloPag;
        $layoutLargo = true;
        $descricaoPagina = $tituloPag.' — '.$subPag;
        $this->view('cursos/lista', get_defined_vars());
    }

    /**
     * curso.php?id= — capa + resumo, descrição, competências que o conteúdo desenvolve;
     * na lateral, a ficha e o botão de acesso. Abaixo, as vagas que pedem essas
     * competências e outros cursos.
     */
    public function detalhe(): void {
        $id = (int)get_str('id');
        $curso = null; $dbErro = null;
        try { $curso = (new CursoDAO())->buscar($id); } catch (Throwable $e) { $dbErro = mensagem_erro_banco($e); }
        // Conteúdo inativo só aparece para o administrador.
        if (!$curso || (empty($curso['ativo']) && !isAdmin())) {
            http_response_code(404);
            $title = 'Conteúdo não encontrado';
            $layoutLargo = true;
            $this->view('cursos/nao_encontrado', get_defined_vars());
            return;
        }

        $competencias = Competencias::doCurso($curso);
        $vagasAtivas = []; $outros = [];
        try {
            $vagasAtivas = (new VagaDAO())->listar(true);
            $outros = array_values(array_filter((new CursoDAO())->listar(true), fn($c) => (int)$c['id'] !== $id));
        } catch (Throwable) {}
        $vagasQuePedem = Competencias::vagasParaCurso($competencias, $vagasAtivas, 4);

        // Candidato: match das vagas e o que o curso ensina que ainda falta para ele.
        $mapaMatch = []; $faltaParaMim = [];
        if (usuarioLogado() && isCandidato()) {
            try {
                $p = (new PerfilDAO())->buscarPorUsuarioId((int)$_SESSION['usuario_id']);
                if ($p) {
                    $matchDao = new MatchDAO();
                    $mapaMatch = $matchDao->mapaPorCandidato((int)$p['id']);
                    foreach (array_slice($matchDao->listarPorCandidato((int)$p['id']), 0, 5) as $m) {
                        foreach ($m['detalhes']['competencias']['faltantes'] ?? [] as $c) $faltaParaMim[$c] = true;
                    }
                }
            } catch (Throwable) {}
        }
        // Outros conteúdos: só do mesmo formato (curso com curso, e-book com e-book, vídeo com vídeo).
        $outros = array_slice(array_values(array_filter($outros, fn($c) => $c['tipo'] === $curso['tipo'])), 0, 4);

        $ptMenuTipo = (string)$curso['tipo'];   // acende o item do menu do formato (Cursos, E-books ou Vídeos)
        $formato = pt_formato((string)$curso['tipo']);
        [$secaoNome, $secaoUrl, $secaoIcone] = pt_secao_formato((string)$curso['tipo']);
        $instituicao = $curso['instituicao'] ?: 'Instituição parceira';
        $acesso = pt_acesso_conteudo($curso);   // "Baixar" (PDF da biblioteca) ou "Acessar" (link da web)
        $link = url('curso.php?id='.$id);
        $abaTipo = [$secaoNome => $secaoUrl];

        $title = $curso['titulo'];
        $layoutLargo = true;
        $descricaoPagina = pt_linha_fina_curso($curso);
        $ogImagem = $curso['imagem'] ?: null;
        $this->view('cursos/detalhe', get_defined_vars());
    }
}
