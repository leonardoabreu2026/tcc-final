<?php
declare(strict_types=1);

/**
 * Acesso à tabela `candidaturas` (candidato → vaga).
 * Status: enviada → em_analise → entrevista → aprovado/rejeitado; "cancelada" só o candidato define.
 * A chave única (candidato, vaga) impede duas candidaturas à mesma vaga: a cancelada é reaproveitada.
 */
final class CandidaturaDAO {
    public const STATUS = ['enviada','em_analise','entrevista','aprovado','rejeitado','cancelada'];
    /** Status que a empresa pode definir ("cancelada" é exclusivo do candidato). */
    public const STATUS_EMPRESA = ['enviada','em_analise','entrevista','aprovado','rejeitado'];
    /**
     * Candidatura ativa (ainda em andamento): é o que conta no limite do plano gratuito.
     * A candidatura numa vaga que a empresa cancelou fica guardada, mas não ocupa o limite.
     */
    public const ATIVAS = "status IN ('enviada','em_analise','entrevista') AND vaga_id NOT IN (SELECT id FROM vagas WHERE status = 'cancelada')";

    /** Colunas comuns às listagens da empresa e do administrador (inclui o match do candidato com a vaga). */
    private const SELECT_EMPRESA = "SELECT c.*, v.titulo, v.cidade, v.uf, v.perfil_empresa_id,
                 COALESCE(NULLIF(v.anunciante,''), NULLIF(pe.nome_fantasia,''), ue.nome) AS empresa_nome,
                 u.nome AS candidato_nome, u.nome, u.email, u.telefone,
                 p.titulo_profissional, p.nivel_experiencia, p.cidade AS candidato_cidade, p.habilidades, p.id AS candidato_perfil_id,
                 m.pontuacao AS match_pontuacao, m.nivel AS match_nivel, m.detalhes AS match_detalhes,
                 cv.titulo AS curriculo_titulo, cv.arquivo_tipo AS curriculo_tipo,
                 CASE WHEN EXISTS (SELECT 1 FROM assinaturas a WHERE a.usuario_id=u.id AND a.plano='assinante' AND a.status='ativa' AND a.data_fim>=CURDATE()) THEN 1 ELSE 0 END AS is_vip
          FROM candidaturas c
          JOIN vagas v ON v.id=c.vaga_id
          JOIN perfis pe ON pe.id=v.perfil_empresa_id
          JOIN usuarios ue ON ue.id=pe.usuario_id
          JOIN perfis p ON p.id=c.perfil_candidato_id
          JOIN usuarios u ON u.id=p.usuario_id
          LEFT JOIN matches m ON m.perfil_candidato_id=c.perfil_candidato_id AND m.vaga_id=c.vaga_id
          LEFT JOIN curriculos cv ON cv.id=c.curriculo_id";

    /**
     * Envia (ou reenvia, se estava cancelada) respeitando o limite de candidaturas ativas.
     * A linha do perfil é travada (FOR UPDATE) durante a contagem e a gravação, então dois
     * envios simultâneos não conseguem ultrapassar o limite do plano gratuito.
     * @param ?int $limite null = sem limite (VIP)
     * @return string 'ok' | 'limite' | 'erro'
     */
    public function enviarComLimite(int $pid, int $vid, int $curriculoId, string $carta, ?int $limite): string {
        $db = Database::getConexao();
        try {
            $db->beginTransaction();
            $db->prepare("SELECT id FROM perfis WHERE id=? FOR UPDATE")->execute([$pid]);
            if ($limite !== null) {
                $c = $db->prepare("SELECT COUNT(*) FROM candidaturas WHERE perfil_candidato_id=? AND ".self::ATIVAS);
                $c->execute([$pid]);
                if ((int)$c->fetchColumn() >= $limite) { $db->rollBack(); return 'limite'; }
            }
            $ex = $db->prepare("SELECT id, status FROM candidaturas WHERE perfil_candidato_id=? AND vaga_id=? FOR UPDATE");
            $ex->execute([$pid, $vid]);
            $existente = $ex->fetch();
            if ($existente && $existente['status'] !== 'cancelada') { $db->rollBack(); return 'erro'; }
            if ($existente) {
                $db->prepare("UPDATE candidaturas SET status='enviada', curriculo_id=?, carta_apresentacao=?, observacao_empresa=NULL, data_candidatura=NOW() WHERE id=?")
                   ->execute([$curriculoId, $carta ?: null, $existente['id']]);
            } else {
                $db->prepare("INSERT INTO candidaturas(perfil_candidato_id,vaga_id,curriculo_id,carta_apresentacao) VALUES(?,?,?,?)")
                   ->execute([$pid, $vid, $curriculoId, $carta ?: null]);
            }
            $db->commit();
            return 'ok';
        } catch (Throwable) {
            if ($db->inTransaction()) $db->rollBack();
            return 'erro';
        }
    }

    public function buscarDoCandidato(int $pid, int $vid): ?array {
        $s = Database::getConexao()->prepare("SELECT * FROM candidaturas WHERE perfil_candidato_id=? AND vaga_id=? LIMIT 1");
        $s->execute([$pid, $vid]);
        return $s->fetch() ?: null;
    }

    /** A empresa recebeu alguma candidatura deste candidato? (libera o contato no portfólio, mesmo sem plano) */
    public function empresaRecebeuDoCandidato(int $perfilCandidatoId, int $perfilEmpresaId): bool {
        $s = Database::getConexao()->prepare("SELECT 1 FROM candidaturas c JOIN vagas v ON v.id=c.vaga_id WHERE c.perfil_candidato_id=? AND v.perfil_empresa_id=? AND c.status<>'cancelada' LIMIT 1");
        $s->execute([$perfilCandidatoId, $perfilEmpresaId]);
        return (bool)$s->fetchColumn();
    }

    public function listarPorCandidato(int $pid): array {
        $sql = "SELECT c.*, v.titulo, v.cidade, v.uf, v.status AS vaga_status, COALESCE(NULLIF(v.anunciante,''), NULLIF(p.nome_fantasia,''), u.nome) AS empresa_nome
                FROM candidaturas c
                JOIN vagas v ON v.id=c.vaga_id
                JOIN perfis p ON p.id=v.perfil_empresa_id
                JOIN usuarios u ON u.id=p.usuario_id
                WHERE c.perfil_candidato_id=? ORDER BY c.data_candidatura DESC";
        $s = Database::getConexao()->prepare($sql);
        $s->execute([$pid]);
        return $s->fetchAll();
    }

    /** Candidaturas recebidas pela empresa. VIP primeiro, depois maior match. */
    public function listarPorEmpresa(int $perfilEmpresaId, int $vagaId = 0, string $status = ''): array {
        return $this->listar('v.perfil_empresa_id=?', [$perfilEmpresaId], $vagaId, $status);
    }

    /** Todas as candidaturas (administrador). */
    public function listarTodas(int $vagaId = 0, string $status = ''): array {
        return $this->listar('1=1', [], $vagaId, $status);
    }

    private function listar(string $where, array $p, int $vagaId, string $status): array {
        if ($vagaId) { $where .= ' AND c.vaga_id=?'; $p[] = $vagaId; }
        if (in_array($status, self::STATUS, true)) { $where .= ' AND c.status=?'; $p[] = $status; }
        $s = Database::getConexao()->prepare(self::SELECT_EMPRESA." WHERE $where ORDER BY is_vip DESC, c.status='cancelada', m.pontuacao DESC, c.data_candidatura DESC");
        $s->execute($p);
        $rows = $s->fetchAll();
        foreach ($rows as &$r) $r['match_detalhes'] = json_decode((string)($r['match_detalhes'] ?? ''), true) ?: [];
        return $rows;
    }

    public function atualizarStatus(int $id, string $status, string $obs = ''): bool {
        if (!in_array($status, self::STATUS, true)) return false;
        $s = Database::getConexao()->prepare("UPDATE candidaturas SET status=?,observacao_empresa=? WHERE id=?");
        $s->execute([$status, $obs ?: null, $id]);
        return $s->rowCount() > 0 || $this->existe($id);
    }

    /**
     * A empresa só altera candidaturas das próprias vagas e que o candidato não cancelou.
     */
    public function atualizarStatusPorEmpresa(int $id, int $perfilEmpresaId, string $status, string $obs = ''): bool {
        if (!in_array($status, self::STATUS_EMPRESA, true)) return false;
        $s = Database::getConexao()->prepare("UPDATE candidaturas c JOIN vagas v ON v.id=c.vaga_id SET c.status=?,c.observacao_empresa=? WHERE c.id=? AND v.perfil_empresa_id=? AND c.status<>'cancelada'");
        $s->execute([$status, $obs ?: null, $id, $perfilEmpresaId]);
        if ($s->rowCount() > 0) return true;
        // Nada mudou: confirma que a candidatura é mesmo desta empresa (e não está cancelada).
        $c = Database::getConexao()->prepare("SELECT 1 FROM candidaturas c JOIN vagas v ON v.id=c.vaga_id WHERE c.id=? AND v.perfil_empresa_id=? AND c.status<>'cancelada'");
        $c->execute([$id, $perfilEmpresaId]);
        return (bool)$c->fetchColumn();
    }

    /** O candidato só cancela candidaturas ainda não analisadas. */
    public function cancelarDoCandidato(int $id, int $pid): bool {
        $s = Database::getConexao()->prepare("UPDATE candidaturas SET status='cancelada' WHERE id=? AND perfil_candidato_id=? AND status IN('enviada','em_analise')");
        $s->execute([$id, $pid]);
        return $s->rowCount() > 0;
    }

    /** "Cancelar candidatura" do administrador: muda só o status; a candidatura e o retorno da empresa ficam guardados. */
    public function cancelar(int $id): bool {
        $s = Database::getConexao()->prepare("UPDATE candidaturas SET status='cancelada' WHERE id=? AND status<>'cancelada'");
        $s->execute([$id]);
        return $s->rowCount() > 0;
    }

    private function existe(int $id): bool {
        $s = Database::getConexao()->prepare("SELECT 1 FROM candidaturas WHERE id=?");
        $s->execute([$id]);
        return (bool)$s->fetchColumn();
    }

    // ------------------------------------------------------------
    // Painéis (dashboards): totais agrupados no SQL. $perfilEmpresaId null = todas (administrador).
    // ------------------------------------------------------------

    /** Quantidade por status, com todos os status (os sem candidatura vêm zerados). @return array<string,int> */
    public function contarPorStatus(?int $perfilEmpresaId = null): array {
        $sql = "SELECT c.status, COUNT(*) AS total FROM candidaturas c"
             .($perfilEmpresaId ? " JOIN vagas v ON v.id=c.vaga_id WHERE v.perfil_empresa_id=?" : '')." GROUP BY c.status";
        $s = Database::getConexao()->prepare($sql);
        $s->execute($perfilEmpresaId ? [$perfilEmpresaId] : []);
        $out = array_fill_keys(self::STATUS, 0);
        foreach ($s->fetchAll() as $r) $out[$r['status']] = (int)$r['total'];
        return $out;
    }

    /** Candidaturas recebidas por dia (sem as canceladas) nos últimos $dias dias; dias sem envio vêm zerados. @return array<string,int> 'Y-m-d' => total */
    public function contarPorDia(int $dias = 30, ?int $perfilEmpresaId = null): array {
        $dias = max(1, $dias);
        $sql = "SELECT DATE(c.data_candidatura) AS dia, COUNT(*) AS total FROM candidaturas c"
             .($perfilEmpresaId ? " JOIN vagas v ON v.id=c.vaga_id" : '')
             ." WHERE c.status<>'cancelada' AND c.data_candidatura >= CURDATE() - INTERVAL ? DAY"
             .($perfilEmpresaId ? " AND v.perfil_empresa_id=?" : '')." GROUP BY dia";
        $s = Database::getConexao()->prepare($sql);
        $s->execute($perfilEmpresaId ? [$dias - 1, $perfilEmpresaId] : [$dias - 1]);
        $out = [];
        for ($i = $dias - 1; $i >= 0; $i--) $out[date('Y-m-d', strtotime("-$i days"))] = 0;
        foreach ($s->fetchAll() as $r) if (isset($out[$r['dia']])) $out[$r['dia']] = (int)$r['total'];
        return $out;
    }

    /** Match de quem se candidatou (sem as canceladas): total, quantas têm match, média e quantidade por faixa. */
    public function resumoMatch(?int $perfilEmpresaId = null): array {
        $sql = "SELECT COUNT(*) AS total, COUNT(m.id) AS com_match, AVG(m.pontuacao) AS media,
                       SUM(m.nivel='baixo') AS baixo, SUM(m.nivel='medio') AS medio, SUM(m.nivel='alto') AS alto, SUM(m.nivel='excelente') AS excelente
                FROM candidaturas c JOIN vagas v ON v.id=c.vaga_id
                LEFT JOIN matches m ON m.perfil_candidato_id=c.perfil_candidato_id AND m.vaga_id=c.vaga_id
                WHERE c.status<>'cancelada'".($perfilEmpresaId ? " AND v.perfil_empresa_id=?" : '');
        $s = Database::getConexao()->prepare($sql);
        $s->execute($perfilEmpresaId ? [$perfilEmpresaId] : []);
        $r = $s->fetch() ?: [];
        return ['total' => (int)($r['total'] ?? 0), 'com_match' => (int)($r['com_match'] ?? 0), 'media' => isset($r['media']) ? (float)$r['media'] : null,
                'niveis' => ['baixo' => (int)($r['baixo'] ?? 0), 'medio' => (int)($r['medio'] ?? 0), 'alto' => (int)($r['alto'] ?? 0), 'excelente' => (int)($r['excelente'] ?? 0)]];
    }

    /** As candidaturas mais recentes (pela data de envio), com o match — mesmas colunas das listagens. */
    public function listarRecentes(int $limite = 8, ?int $perfilEmpresaId = null): array {
        $s = Database::getConexao()->prepare(self::SELECT_EMPRESA.($perfilEmpresaId ? " WHERE v.perfil_empresa_id=?" : '')
            ." ORDER BY c.data_candidatura DESC, c.id DESC LIMIT ".max(1, $limite));
        $s->execute($perfilEmpresaId ? [$perfilEmpresaId] : []);
        return $s->fetchAll();
    }
}
