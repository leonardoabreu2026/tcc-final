<?php
declare(strict_types=1);

/**
 * Painel "Calibrador" (rota admin/pages/calibrador.php) — só administrador.
 *
 * Ajusta as máquinas de extração sem mexer no código (ver app/Services/Extracao/Calibrador.php):
 *  - CRUD dos termos calibrados: "quando o texto tiver X, mande para Y" (campo da vaga, área da vaga,
 *    área do curso ou seção do currículo); ativar/desativar sem apagar;
 *  - "Testar as máquinas": cola um anúncio, uma ficha de curso ou um currículo e vê o resultado da
 *    extração, com o que o calibrador ajustou — nada é salvo;
 *  - nomes conhecidos: empresas e instituições já cadastradas (a parte que se atualiza sozinha).
 */
final class CalibradorController extends Controller {
    private const MAQUINAS = ['vaga' => 'Vaga (texto do anúncio)', 'curso' => 'Curso ou e-book (texto de divulgação)', 'curriculo' => 'Currículo (texto)'];

    public function painel(): void {
        exigirAdmin();
        $dao = new CalibracaoDAO();
        $catDao = new CategoriaDAO();
        $areas = [
            'vaga' => array_column($catDao->listar('vaga', true), 'nome'),
            'curso' => array_column($catDao->listar('curso', true), 'nome'),
        ];
        $teste = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validar_csrf();
            $acao = post_str('acao');
            $id = post_int('id');

            if ($acao === 'excluir') {
                $ok = $dao->excluir($id);
                flash($ok ? 'ok' : 'erro', $ok ? 'Termo excluído: a extração volta a seguir só a regra nesse caso.' : 'Termo não encontrado.');
                redirect('admin/pages/calibrador.php'.painel_qs());
            }
            if (in_array($acao, ['ativar', 'desativar'], true)) {
                $ativar = $acao === 'ativar';
                $ok = $dao->alterarAtivo($id, $ativar);
                flash($ok ? 'ok' : 'erro', $ok ? ($ativar ? 'Termo ativado: já vale na próxima extração.' : 'Termo desativado: fica guardado, mas a extração não usa.') : 'Termo não encontrado.');
                redirect('admin/pages/calibrador.php'.painel_qs());
            }
            if ($acao === 'testar') {
                $teste = $this->testar(post_str('maquina'), mb_substr(post_str('texto_teste'), 0, 8000));
            } else {
                [$contexto, $destino] = array_pad(explode('|', post_str('destino'), 2), 2, '');
                $d = ['contexto' => $contexto, 'termo' => trim(mb_substr(post_str('termo'), 0, 120)), 'destino' => $destino, 'ativo' => post_int('ativo', 1) ? 1 : 0];
                $voltar = 'admin/pages/calibrador.php'.painel_qs($id ? ['edit' => $id] : []).'#form-calibrador';
                if ($d['termo'] === '') { flash('erro', 'Informe o termo (palavra ou expressão).'); redirect($voltar); }
                if (!$this->destinoValido($contexto, $destino, $areas)) { flash('erro', 'Escolha para onde o termo vai.'); redirect($voltar); }
                if ($id && !$dao->buscar($id)) { flash('erro', 'Termo não encontrado (pode ter sido excluído).'); redirect('admin/pages/calibrador.php'); }
                $ok = $dao->salvar($d, $id, (int)$_SESSION['usuario_id']);
                flash($ok ? 'ok' : 'erro', $ok ? 'Termo salvo: já vale na próxima extração.' : $dao->erro);
                redirect($ok ? 'admin/pages/calibrador.php'.painel_qs(['edit' => null]).'#lista-calibrador' : $voltar);
            }
        }

        $edit = registro_encontrado(get_str('edit') !== '' ? $dao->buscar((int)get_str('edit')) : null, 'edit', 'admin/pages/calibrador.php', 'Termo não encontrado (pode ter sido excluído).');
        $filtroContexto = enum_val(get_str('contexto'), array_keys(Calibrador::CONTEXTOS), '');
        $busca = get_str('q');
        $buscaN = Competencias::normalizar($busca);
        $todos = $dao->listar();
        $termos = array_values(array_filter($todos, fn($t) => ($filtroContexto === '' || $t['contexto'] === $filtroContexto)
            && ($buscaN === '' || str_contains((string)$t['termo_chave'], $buscaN) || str_contains(Competencias::normalizar((string)$t['destino']), $buscaN))));
        [$ordem, $dir] = lista_ordem(['termo', 'contexto', 'destino', 'ativo'], 'termo', 'asc');
        $totalTermos = count($termos);
        [$termos, $pagina, $paginas] = paginar(ordenar_linhas($termos, $ordem, $dir), 30);
        $nomesEmpresas = Calibrador::nomes('empresa');
        $nomesInstituicoes = Calibrador::nomes('instituicao');
        $maquinas = self::MAQUINAS;
        $title = 'Calibrador';
        $abaAtiva = 'calibrador';
        $this->view('admin/calibrador', get_defined_vars());
    }

    /** O destino existe para o contexto? (campo da vaga, seção do currículo ou área ativa do tipo certo) */
    private function destinoValido(string $contexto, string $destino, array $areas): bool {
        return match ($contexto) {
            'vaga_linha' => isset(Calibrador::CAMPOS_VAGA[$destino]),
            'curriculo_linha' => isset(Calibrador::SECOES_CURRICULO[$destino]),
            'vaga_categoria' => in_array($destino, $areas['vaga'], true),
            'curso_categoria' => in_array($destino, $areas['curso'], true),
            default => false,
        };
    }

    /**
     * Roda a máquina escolhida no texto colado, sem salvar nada.
     * @return array{maquina:string,texto:string,campos:list<array{0:string,1:string}>,ajustes:list<array>}|null
     */
    private function testar(string $maquina, string $texto): ?array {
        $maquina = enum_val($maquina, array_keys(self::MAQUINAS), 'vaga');
        if (trim($texto) === '') { flash('erro', 'Cole um texto para testar.'); return null; }
        $resumo = fn(string $t) => mb_strimwidth(str_replace("\n", ' · ', trim($t)), 0, 260, '…');
        $campos = []; $ajustes = [];
        try {
            if ($maquina === 'vaga') {
                $r = ExtracaoVaga::doTexto($texto);
                foreach (['titulo' => 'Cargo (título)', 'anunciante' => 'Empresa', 'categoria' => 'Área (categoria)', 'descricao' => 'Descrição',
                          'requisitos' => 'Requisitos', 'beneficios' => 'Benefícios', 'contato' => 'Contato'] as $k => $rot) $campos[] = [$rot, $resumo((string)$r[$k])];
                $ajustes = $r['calibrador'];
            } elseif ($maquina === 'curso') {
                $r = ExtracaoCurso::doTexto($texto);
                foreach (['titulo' => 'Título', 'instituicao' => 'Instituição', 'categoria' => 'Área (categoria)', 'tipo' => 'Tipo', 'descricao' => 'Descrição'] as $k => $rot)
                    $campos[] = [$rot, $resumo((string)$r[$k])];
                $ajustes = $r['calibrador'];
            } else {
                $r = ExtracaoCurriculo::extrairCampos($texto);
                foreach (['nome' => 'Nome', 'titulo_profissional' => 'Título profissional', 'objetivo' => 'Objetivo', 'experiencias' => 'Experiências',
                          'formacao' => 'Formação', 'cursos' => 'Cursos', 'habilidades' => 'Habilidades', 'idiomas' => 'Idiomas'] as $k => $rot) $campos[] = [$rot, $resumo((string)$r[$k])];
                // No currículo o termo só age nas linhas soltas do cabeçalho: mostra quais linhas têm termo.
                foreach (ExtracaoCurriculo::linhasDoTexto($texto) as $l) {
                    $t = Calibrador::termoQueCasa('curriculo_linha', $l);
                    if ($t) $ajustes[] = ['campo' => 'linha', 'texto' => $l, 'regra' => '', 'para' => $t['destino'], 'termo' => $t['termo']];
                }
            }
        } catch (Throwable $e) {
            error_log('[Calibrador] Teste falhou: '.$e->getMessage());
            flash('erro', 'Não foi possível testar esse texto.');
            return null;
        }
        return ['maquina' => $maquina, 'texto' => $texto, 'campos' => $campos, 'ajustes' => $ajustes];
    }
}
