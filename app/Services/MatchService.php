<?php
declare(strict_types=1);

/**
 * Máquina de match candidato × vaga. Pontuação de 0 a 100, explicável:
 *   Competências  50 — competências exigidas pela vaga que o candidato possui
 *   Cargo         20 — título da vaga × título profissional / histórico do candidato
 *   Localização   15 — mesma cidade, mesma UF ou vaga remota
 *   Nível         15 — nível de experiência do candidato × nível pedido
 * O detalhamento é salvo em matches.detalhes (JSON) e exibido para candidato e empresa.
 * Dados herdados da extração do currículo também entram: cursos complementares, CNH e
 * informações adicionais nas competências; disponibilidade para mudança na localização (6/15);
 * e "observacoes" (CNH pedida, vaga PCD, viagens) — que explicam, mas não alteram a nota.
 *
 * Quando é recalculado: ao enviar/excluir currículo, salvar o perfil, criar/editar vaga
 * ou pelo botão "Recalcular match" do portfólio.
 */
final class MatchService {
    public const PESOS = ['competencias' => 50, 'cargo' => 20, 'local' => 15, 'nivel' => 15];
    /** Níveis em ordem, para medir a distância entre o nível do candidato e o da vaga. */
    private const NIVEIS = ['estagiario' => 0, 'junior' => 1, 'pleno' => 2, 'senior' => 3];
    /** Palavras ignoradas ao comparar cargos (muito comuns para indicar compatibilidade). */
    private const STOP = ['para','com','uma','como','por','das','dos','que','nas','nos','sobre','mais','vaga','vagas','empresa','profissional',
        'area','setor','geral','loja','obra','horas','junior','pleno','senior','estagiario','auxiliar','assistente','ajudante','de','da','do','e','ou','em','a','o'];

    /** Recalcula o match de um candidato com todas as vagas ativas. */
    public function recalcular(int $perfilId): int {
        $p = (new PerfilDAO())->buscarPorId($perfilId);
        if (!$p || ($p['tipo'] ?? '') !== 'candidato') return 0;
        $cand = $this->prepararCandidato($p);
        $dao = new MatchDAO();
        $dao->limparPorCandidato($perfilId);
        $n = 0;
        foreach ((new VagaDAO())->listar(true) as $v) {
            $r = $this->calcular($cand, $v);
            $dao->salvar($perfilId, (int)$v['id'], $r['pontuacao'], $r['nivel'], $r['detalhes']);
            $n++;
        }
        return $n;
    }

    /** Recalcula o match de uma vaga com todos os candidatos (após criar/editar a vaga). */
    public function recalcularVaga(int $vagaId): int {
        $dao = new MatchDAO();
        $v = (new VagaDAO())->buscar($vagaId);
        if (!$v || $v['status'] !== 'ativa' || (!empty($v['data_expiracao']) && $v['data_expiracao'] < date('Y-m-d'))) {
            $dao->limparPorVaga($vagaId);
            return 0;
        }
        // Textos dos currículos de todos os candidatos numa consulta só (e não uma por candidato).
        $textos = (new CurriculoDAO())->ultimosTextos();
        $n = 0;
        foreach ((new PerfilDAO())->listarCandidatos() as $p) {
            $r = $this->calcular($this->prepararCandidato($p, $textos[(int)$p['id']] ?? ''), $v);
            $dao->salvar((int)$p['id'], $vagaId, $r['pontuacao'], $r['nivel'], $r['detalhes']);
            $n++;
        }
        return $n;
    }

    /**
     * Dados do candidato já processados (competências e tokens), reaproveitados para todas as vagas.
     * $cv: texto do último currículo, quando já foi carregado (null = busca no banco).
     */
    private function prepararCandidato(array $p, ?string $cv = null): array {
        $cv ??= (new CurriculoDAO())->ultimoTexto((int)$p['id']);
        return [
            'perfil' => $p,
            'competencias' => Competencias::doPerfil($p, $cv),
            'tokens_titulo' => $this->tokens(($p['titulo_profissional'] ?? '').' '.($p['objetivo'] ?? '')),
            'tokens_historico' => $this->tokens(implode(' ', [$p['experiencias'] ?? '', $p['habilidades'] ?? '', $p['formacao'] ?? '', $p['cursos_complementares'] ?? '', $p['bio'] ?? '', $cv])),
            // Dados herdados da extração do currículo (disponibilidade, CNH, PCD).
            'mudanca' => (bool)preg_match('/mudan[cç]a|mudar de cidade|reloca/iu', ($p['disponibilidade'] ?? '').' '.($p['informacoes_adicionais'] ?? '')),
            'viagens' => (bool)preg_match('/viage(m|ns)|viajar/iu', ($p['disponibilidade'] ?? '').' '.($p['informacoes_adicionais'] ?? '')),
            'cnh' => strtoupper((string)($p['cnh'] ?? '')),
            'pcd' => (bool)preg_match('/^\s*pcd\b|pessoa com defici/imu', (string)($p['informacoes_adicionais'] ?? '')),
        ];
    }

    /** @return array{pontuacao:float,nivel:string,detalhes:array} */
    public function calcular(array $cand, array $v): array {
        $p = $cand['perfil'];
        $d = [];

        // 1) Competências exigidas pela vaga.
        $exigidas = Competencias::daVaga($v);
        $atendidas = array_values(array_intersect($exigidas, $cand['competencias']));
        $faltantes = array_values(array_diff($exigidas, $cand['competencias']));
        if ($exigidas) {
            $ptsComp = self::PESOS['competencias'] * count($atendidas) / count($exigidas);
        } else {
            // Vaga sem competências do dicionário: usa sobreposição de palavras da descrição/requisitos.
            $tv = $this->tokens(($v['descricao'] ?? '').' '.($v['requisitos'] ?? ''));
            $comuns = array_intersect_key($tv, $cand['tokens_historico'] + $cand['tokens_titulo']);
            $ptsComp = $tv ? self::PESOS['competencias'] * min(1, count($comuns) / max(3, count($tv) * 0.5)) : 0;
        }
        $d['competencias'] = ['pontos' => round($ptsComp, 1), 'max' => self::PESOS['competencias'], 'exigidas' => $exigidas, 'atendidas' => $atendidas, 'faltantes' => $faltantes];

        // 2) Cargo: palavras do título da vaga no título/objetivo (peso cheio) ou no histórico (60%).
        $tt = $this->tokens((string)($v['titulo'] ?? ''));
        $ptsCargo = 0.0; $motivoCargo = 'Cargo diferente do seu histórico';
        if ($tt) {
            $rt = count(array_intersect_key($tt, $cand['tokens_titulo'])) / count($tt);
            $rh = count(array_intersect_key($tt, $cand['tokens_historico'])) / count($tt);
            $ptsCargo = self::PESOS['cargo'] * max($rt, 0.6 * $rh);
            if ($rt > 0) $motivoCargo = 'Cargo compatível com seu título/objetivo';
            elseif ($rh > 0) $motivoCargo = 'Cargo aparece no seu histórico';
        }
        $d['cargo'] = ['pontos' => round($ptsCargo, 1), 'max' => self::PESOS['cargo'], 'texto' => $motivoCargo];

        // 3) Localização.
        $mesmaCidade = !empty($p['cidade']) && !empty($v['cidade']) && Competencias::normalizar((string)$p['cidade']) === Competencias::normalizar((string)$v['cidade']);
        $mesmaUf = !empty($p['uf']) && !empty($v['uf']) && strtoupper((string)$p['uf']) === strtoupper((string)$v['uf']);
        [$ptsLocal, $motivoLocal] = match (true) {
            ($v['remoto'] ?? '') === 'remoto' => [15, 'Vaga remota'],
            $mesmaCidade => [15, 'Mesma cidade'],
            $mesmaUf => [9, 'Mesmo estado ('.strtoupper((string)$v['uf']).')'],
            !empty($cand['mudanca']) => [6, 'Fora da sua região, mas você tem disponibilidade para mudança'],
            default => [0, 'Fora da sua região'],
        };
        $d['local'] = ['pontos' => $ptsLocal, 'max' => self::PESOS['local'], 'texto' => $motivoLocal];

        // 4) Nível de experiência.
        $nc = self::NIVEIS[$p['nivel_experiencia'] ?? 'junior'] ?? 1;
        $nv = self::NIVEIS[$v['nivel_experiencia'] ?? 'junior'] ?? 1;
        $dif = $nc - $nv;
        [$ptsNivel, $motivoNivel] = match (true) {
            $dif === 0 => [15, 'Nível exatamente o pedido'],
            $dif === 1 => [12, 'Você tem mais experiência que o pedido'],
            $dif >= 2 => [8, 'Você tem bem mais experiência que o pedido'],
            $dif === -1 => [7, 'A vaga pede um nível acima do seu'],
            default => [0, 'A vaga pede bem mais experiência'],
        };
        $d['nivel'] = ['pontos' => $ptsNivel, 'max' => self::PESOS['nivel'], 'texto' => $motivoNivel];

        // 5) Observações (não mudam a nota): dados do currículo que a empresa costuma pedir.
        $textoVaga = implode(' ', [$v['titulo'] ?? '', $v['descricao'] ?? '', $v['requisitos'] ?? '']);
        $obs = [];
        if (preg_match('/\bCNH\b[^.\n]{0,25}?\b(?:categoria\s*)?([A-E]{1,2})\b/u', $textoVaga, $mc)) {
            $pede = strtoupper($mc[1]);
            $tem = $cand['cnh'] ?? '';
            $obs[] = $tem !== '' && count(array_intersect(str_split($pede), str_split($tem))) > 0
                ? ['ok' => true, 'texto' => "A vaga pede CNH {$pede} e você informou CNH {$tem}"]
                : ['ok' => false, 'texto' => "A vaga pede CNH {$pede}".($tem !== '' ? " (você informou CNH {$tem})" : ' — informe sua CNH no perfil, se tiver')];
        }
        if (preg_match('/\b(pcd|pessoas? com defici[eê]ncia)\b/iu', $textoVaga) && !empty($cand['pcd'])) {
            $obs[] = ['ok' => true, 'texto' => 'Vaga aberta/afirmativa para PCD e você informou ser PCD'];
        }
        if (preg_match('/\bviage(m|ns)\b|disponibilidade para viajar/iu', $textoVaga)) {
            $obs[] = !empty($cand['viagens']) ? ['ok' => true, 'texto' => 'A vaga envolve viagens e você tem disponibilidade'] : ['ok' => false, 'texto' => 'A vaga envolve viagens'];
        }
        if ($obs) $d['observacoes'] = $obs;

        $total = round(min(100, $ptsComp + $ptsCargo + $ptsLocal + $ptsNivel), 2);
        return ['pontuacao' => $total, 'nivel' => self::classificar($total), 'detalhes' => $d];
    }

    public static function classificar(float $pontos): string {
        return $pontos >= 75 ? 'excelente' : ($pontos >= 55 ? 'alto' : ($pontos >= 35 ? 'medio' : 'baixo'));
    }

    /** @return array<string,true> palavras relevantes normalizadas e sem plural simples */
    private function tokens(string $texto): array {
        $out = [];
        foreach (explode(' ', Competencias::normalizar($texto)) as $t) {
            if (strlen($t) < 3 || in_array($t, self::STOP, true) || ctype_digit($t)) continue;
            if (strlen($t) > 4 && str_ends_with($t, 's')) $t = substr($t, 0, -1);
            // Gênero: vendedora → vendedor, domestica → domestic(o/a)
            if (strlen($t) > 5 && (str_ends_with($t, 'a') || str_ends_with($t, 'o'))) $t = substr($t, 0, -1);
            $out[$t] = true;
        }
        return $out;
    }
}
