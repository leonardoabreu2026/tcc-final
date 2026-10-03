<?php
declare(strict_types=1);

/**
 * Acesso à tabela `assinaturas` e às REGRAS DOS PLANOS (conferidas no servidor):
 *  - Candidato gratuito: até 3 candidaturas ativas; VIP ('assinante'): ilimitado e no topo.
 *  - Empresa básica: até 2 vagas abertas; Premium ('empresa'): ilimitado, destaque e banco de talentos.
 * Cobrança demonstrativa: a assinatura é gravada após a confirmação em planos.php.
 * Ao ser criado, o DAO marca como expiradas as assinaturas vencidas (uma vez por requisição).
 */
final class AssinaturaDAO {
    public const PLANOS = ['assinante', 'empresa'];
    public const STATUS = ['ativa', 'cancelada', 'expirada'];
    /** Preço mensal de cada plano (demonstrativo) — usado em planos.php e no painel de assinaturas. */
    public const PRECOS = ['assinante' => 9.90, 'empresa' => 49.90];
    /** Plano de cada tipo de conta. */
    public const PLANO_DO_TIPO = ['candidato' => 'assinante', 'empresa' => 'empresa'];

    /** Já conferiu as assinaturas vencidas nesta requisição? (vários DAOs são criados por página) */
    private static bool $expiracaoConferida = false;

    public function __construct() {
        $this->expirarAssinaturas();
    }

    /**
     * Marca automaticamente assinaturas vencidas. Roda só na primeira instância da requisição.
     */
    private function expirarAssinaturas(): void {
        if (self::$expiracaoConferida) return;
        self::$expiracaoConferida = true;
        try {
            $db = Database::getConexao();
            // Empresas cujo Premium vai expirar agora: perdem o destaque das vagas.
            $venc = $db->query("SELECT DISTINCT usuario_id FROM assinaturas
                                WHERE status = 'ativa' AND plano = 'empresa' AND data_fim < CURDATE()")->fetchAll(PDO::FETCH_COLUMN);
            $db->exec("UPDATE assinaturas
                       SET status = 'expirada'
                       WHERE status = 'ativa'
                         AND data_fim IS NOT NULL
                         AND data_fim < CURDATE()");
            foreach ($venc as $uid) $this->removerDestaqueSemPremium((int)$uid);
        } catch (Throwable) {
            // A consulta de assinatura também verifica a data, evitando liberar acesso vencido.
        }
    }

    /**
     * Busca a assinatura ativa e ainda válida do usuário.
     */
    public function buscarAtivaPorUsuario(int $usuarioId): ?array {
        try {
            $sql = "SELECT *
                    FROM assinaturas
                    WHERE usuario_id = ?
                      AND status = 'ativa'
                      AND data_fim >= CURDATE()
                    ORDER BY id DESC
                    LIMIT 1";
            $s = Database::getConexao()->prepare($sql);
            $s->execute([$usuarioId]);
            $ass = $s->fetch();
            return $ass ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Verifica se o usuário tem o plano VIP de candidato.
     */
    public function isCandidatoVip(int $usuarioId): bool {
        $ass = $this->buscarAtivaPorUsuario($usuarioId);
        return $ass !== null && $ass['plano'] === 'assinante';
    }

    /**
     * Verifica se a empresa tem o plano Premium.
     */
    public function isEmpresaPremium(int $usuarioId): bool {
        $ass = $this->buscarAtivaPorUsuario($usuarioId);
        return $ass !== null && $ass['plano'] === 'empresa';
    }

    /**
     * Cria uma nova assinatura.
     *
     * Nesta versão a cobrança é local/demonstrativa: a assinatura é registrada
     * no banco após a confirmação do usuário em planos.php.
     */
    public function assinar(int $usuarioId, string $plano, float $valor, int $dias = 30): bool {
        $planosPermitidos = ['assinante', 'empresa'];
        if (!in_array($plano, $planosPermitidos, true) || $dias < 1 || $valor < 0) {
            return false;
        }

        try {
            $db = Database::getConexao();
            $db->beginTransaction();

            $usuario = $db->prepare("SELECT tipo FROM usuarios WHERE id = ? AND ativo = 1 LIMIT 1");
            $usuario->execute([$usuarioId]);
            $tipoUsuario = $usuario->fetchColumn();

            if ($tipoUsuario === false) {
                $db->rollBack();
                return false;
            }

            if (($plano === 'assinante' && !in_array($tipoUsuario, ['candidato', 'admin'], true)) ||
                ($plano === 'empresa' && !in_array($tipoUsuario, ['empresa', 'admin'], true))) {
                $db->rollBack();
                return false;
            }

            // Não deixa duas assinaturas pagas ativas para a mesma conta.
            $cancelar = $db->prepare("UPDATE assinaturas
                                      SET status = 'cancelada'
                                      WHERE usuario_id = ?
                                        AND status = 'ativa'");
            $cancelar->execute([$usuarioId]);

            $ins = $db->prepare("INSERT INTO assinaturas
                (usuario_id, plano, valor, data_inicio, data_fim, status)
                VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'ativa')");
            $ins->execute([$usuarioId, $plano, number_format($valor, 2, '.', ''), $dias]);

            $db->commit();
            return true;
        } catch (Throwable) {
            if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    }

    /**
     * Cancela a assinatura ativa do usuário sem apagar o histórico.
     */
    public function cancelar(int $usuarioId): bool {
        try {
            $sql = "UPDATE assinaturas
                    SET status = 'cancelada'
                    WHERE usuario_id = ?
                      AND status = 'ativa'";
            $s = Database::getConexao()->prepare($sql);
            $s->execute([$usuarioId]);
            $ok = $s->rowCount() > 0;
            if ($ok) $this->removerDestaqueSemPremium($usuarioId);
            return $ok;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Destaque é recurso do Premium: sem assinatura vigente, as vagas da empresa
     * deixam de ficar no topo. (As vagas continuam ativas; só novas publicações
     * passam a respeitar o limite do plano básico.)
     */
    public function removerDestaqueSemPremium(int $usuarioId): void {
        try {
            if ($this->isEmpresaPremium($usuarioId)) return;
            Database::getConexao()->prepare("UPDATE vagas v JOIN perfis p ON p.id = v.perfil_empresa_id
                                            SET v.destaque = 0 WHERE p.usuario_id = ? AND v.destaque = 1")->execute([$usuarioId]);
        } catch (Throwable) {}
    }

    /**
     * Histórico de assinaturas da conta, do mais recente para o mais antigo.
     */
    public function listarPorUsuario(int $usuarioId): array {
        try {
            $s = Database::getConexao()->prepare(
                "SELECT * FROM assinaturas WHERE usuario_id = ? ORDER BY id DESC"
            );
            $s->execute([$usuarioId]);
            return $s->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Conta candidaturas ativas de um candidato.
     */
    public function contarCandidaturasAtivas(int $perfilCandidatoId): int {
        try {
            $sql = "SELECT COUNT(*) FROM candidaturas
                    WHERE perfil_candidato_id = ?
                      AND status IN ('enviada','em_analise','entrevista')";
            $s = Database::getConexao()->prepare($sql);
            $s->execute([$perfilCandidatoId]);
            return (int)$s->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Conta vagas ativas de uma empresa.
     */
    public function contarVagasAtivas(int $perfilEmpresaId): int {
        try {
            $sql = "SELECT COUNT(*) FROM vagas
                    WHERE perfil_empresa_id = ?
                      AND status = 'ativa'
                      AND (data_expiracao IS NULL OR data_expiracao >= CURDATE())";
            $s = Database::getConexao()->prepare($sql);
            $s->execute([$perfilEmpresaId]);
            return (int)$s->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    /** Limites do plano gratuito: candidaturas ativas do candidato e vagas ativas da empresa. */
    public const LIMITE_CANDIDATURAS_GRATIS = 3;
    public const LIMITE_VAGAS_GRATIS = 2;

    /**
     * Valida se candidato pode se candidatar.
     * Plano gratuito: até 3 candidaturas ativas; Candidato VIP: ilimitado.
     */
    public function podeCandidatar(int $usuarioId, int $perfilCandidatoId): array {
        if ($this->isCandidatoVip($usuarioId)) {
            return ['permitido' => true, 'motivo' => 'Candidato VIP (Ilimitado)'];
        }
        return $this->dentroDoLimite($this->contarCandidaturasAtivas($perfilCandidatoId), self::LIMITE_CANDIDATURAS_GRATIS,
            'Você atingiu o limite de %d candidaturas ativas do Plano Gratuito. Torne-se Candidato VIP para enviar candidaturas ilimitadas!');
    }

    /**
     * Valida se empresa pode cadastrar vaga.
     * Plano básico: até 2 vagas ativas; Premium: ilimitado.
     */
    public function podePublicarVaga(int $usuarioId, int $perfilEmpresaId): array {
        if ($this->isEmpresaPremium($usuarioId)) {
            return ['permitido' => true, 'motivo' => 'Empresa Premium (Ilimitado)'];
        }
        return $this->dentroDoLimite($this->contarVagasAtivas($perfilEmpresaId), self::LIMITE_VAGAS_GRATIS,
            'Sua empresa atingiu o limite de %d vagas ativas do Plano Básico Gratuito. Assine o Plano Empresa Premium para publicar vagas ilimitadas!');
    }

    /** Resposta comum dos dois limites: permitido enquanto houver vaga no limite; senão, o motivo para a tela de planos. */
    private function dentroDoLimite(int $ativas, int $limite, string $motivoCheio): array {
        $permitido = $ativas < $limite;
        return [
            'permitido' => $permitido,
            'motivo' => $permitido ? 'Dentro do limite gratuito' : sprintf($motivoCheio, $limite),
            'ativas' => $ativas,
            'limite' => $limite
        ];
    }

    /**
     * Painel do administrador: assinaturas por plano — vigentes (ativas e no prazo), canceladas,
     * expiradas e a receita mensal das vigentes (valores demonstrativos, sem cobrança real).
     * @return array<string,array{vigentes:int,canceladas:int,expiradas:int,receita:float}> 'assinante' e 'empresa'
     */
    public function resumoPorPlano(): array {
        $out = [];
        foreach (['assinante', 'empresa'] as $p) $out[$p] = ['vigentes' => 0, 'canceladas' => 0, 'expiradas' => 0, 'receita' => 0.0];
        $sql = "SELECT plano, SUM(status='ativa' AND data_fim>=CURDATE()) AS vigentes, SUM(status='cancelada') AS canceladas,
                       SUM(status='expirada' OR (status='ativa' AND data_fim<CURDATE())) AS expiradas,
                       SUM(CASE WHEN status='ativa' AND data_fim>=CURDATE() THEN valor ELSE 0 END) AS receita
                FROM assinaturas GROUP BY plano";
        foreach (Database::getConexao()->query($sql)->fetchAll() as $r) {
            if (!isset($out[$r['plano']])) continue;
            $out[$r['plano']] = ['vigentes' => (int)$r['vigentes'], 'canceladas' => (int)$r['canceladas'], 'expiradas' => (int)$r['expiradas'], 'receita' => (float)$r['receita']];
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // PAINEL DO ADMINISTRADOR (admin/pages/assinaturas.php): ver, editar, cancelar e excluir
    // ------------------------------------------------------------------

    /** Todas as assinaturas com a conta (nome, e-mail, tipo) e se está vigente (ativa e no prazo). Filtros: plano, status, q. */
    public function listarTodas(array $f = []): array {
        $sql = "SELECT a.*, u.nome AS usuario_nome, u.email AS usuario_email, u.tipo AS usuario_tipo,
                       (a.status = 'ativa' AND a.data_fim >= CURDATE()) AS vigente
                FROM assinaturas a JOIN usuarios u ON u.id = a.usuario_id WHERE 1=1";
        $p = [];
        if (in_array($f['plano'] ?? '', self::PLANOS, true)) { $sql .= " AND a.plano = ?"; $p[] = $f['plano']; }
        if (in_array($f['status'] ?? '', self::STATUS, true)) { $sql .= " AND a.status = ?"; $p[] = $f['status']; }
        if (($f['q'] ?? '') !== '') { $sql .= " AND (u.nome LIKE ? OR u.email LIKE ?)"; $p[] = like($f['q']); $p[] = like($f['q']); }
        if (!empty($f['usuario_id'])) { $sql .= " AND a.usuario_id = ?"; $p[] = (int)$f['usuario_id']; }
        $s = Database::getConexao()->prepare($sql." ORDER BY a.id DESC");
        $s->execute($p);
        return $s->fetchAll();
    }

    public function buscar(int $id): ?array {
        $s = Database::getConexao()->prepare("SELECT a.*, u.nome AS usuario_nome, u.email AS usuario_email, u.tipo AS usuario_tipo
                                              FROM assinaturas a JOIN usuarios u ON u.id = a.usuario_id WHERE a.id = ?");
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    /**
     * Edita valor, datas e situação. Regras: fim não antes do início; para ficar ATIVA o fim precisa ser hoje
     * ou depois; e cada conta tem no máximo uma assinatura ativa (as outras ativas da conta são canceladas).
     * Se a empresa perde o Premium, o destaque das vagas sai. Devolve '' (ok) ou a mensagem de erro.
     */
    public function atualizar(int $id, array $d): string {
        $a = $this->buscar($id);
        if (!$a) return 'Assinatura não encontrada (pode ter sido excluída).';
        if (!in_array($d['status'], self::STATUS, true)) return 'Situação inválida.';
        if ($d['valor'] === null || $d['valor'] < 0 || $d['valor'] > 99999.99) return 'Informe um valor válido (0 ou mais).';
        if (!$d['data_inicio'] || !$d['data_fim']) return 'Informe as datas de início e fim.';
        if ($d['data_fim'] < $d['data_inicio']) return 'A data de fim não pode ser antes do início.';
        if ($d['status'] === 'ativa' && $d['data_fim'] < date('Y-m-d')) return 'Para ficar ativa, o fim precisa ser hoje ou depois (ou marque como expirada).';
        $db = Database::getConexao();
        $db->beginTransaction();
        try {
            if ($d['status'] === 'ativa') $db->prepare("UPDATE assinaturas SET status='cancelada' WHERE usuario_id=? AND status='ativa' AND id<>?")->execute([(int)$a['usuario_id'], $id]);
            $db->prepare("UPDATE assinaturas SET valor=?, data_inicio=?, data_fim=?, status=? WHERE id=?")
               ->execute([number_format((float)$d['valor'], 2, '.', ''), $d['data_inicio'], $d['data_fim'], $d['status'], $id]);
            $db->commit();
        } catch (Throwable) {
            if ($db->inTransaction()) $db->rollBack();
            return 'Não foi possível salvar a assinatura.';
        }
        $this->removerDestaqueSemPremium((int)$a['usuario_id']);
        return '';
    }

    /** Cancela UMA assinatura (pelo id), mantendo o histórico. */
    public function cancelarPorId(int $id): bool {
        $a = $this->buscar($id);
        if (!$a || $a['status'] !== 'ativa') return false;
        Database::getConexao()->prepare("UPDATE assinaturas SET status='cancelada' WHERE id=?")->execute([$id]);
        $this->removerDestaqueSemPremium((int)$a['usuario_id']);
        return true;
    }

    /** Exclui do histórico (use cancelar para manter o registro). */
    public function excluir(int $id): bool {
        $a = $this->buscar($id);
        if (!$a) return false;
        Database::getConexao()->prepare("DELETE FROM assinaturas WHERE id=?")->execute([$id]);
        $this->removerDestaqueSemPremium((int)$a['usuario_id']);
        return true;
    }
}
