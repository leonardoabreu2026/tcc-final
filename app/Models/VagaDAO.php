<?php
declare(strict_types=1);

/**
 * Acesso à tabela `vagas` (a "M" do MVC).
 * As listagens já trazem o nome da categoria, o nome da empresa e se ela é Premium.
 * Vaga "aberta" = status 'ativa' e data de expiração ainda não passou.
 */
final class VagaDAO {
    public const TIPOS = ['clt','pj','estagio','temporario'];
    public const NIVEIS = ['estagiario','junior','pleno','senior'];
    public const MODELOS = ['presencial','remoto','hibrido'];
    public const STATUS = ['ativa','pausada','encerrada'];

    /** empresa_nome: o anunciante (vaga publicada pela curadoria, ex.: lida de um cartaz) ou a empresa dona da vaga. */
    private const SELECT = "SELECT v.*, c.nome AS categoria_nome, COALESCE(NULLIF(v.anunciante,''), NULLIF(p.nome_fantasia,''), u.nome) AS empresa_nome,
        COALESCE(NULLIF(p.nome_fantasia,''), u.nome) AS publicado_por, p.usuario_id AS empresa_usuario_id, COALESCE(u.ativo,0) AS empresa_ativa,
        (COALESCE(v.anunciante,'') = '' AND EXISTS(SELECT 1 FROM assinaturas a WHERE a.usuario_id = p.usuario_id AND a.plano = 'empresa' AND a.status = 'ativa' AND a.data_fim >= CURDATE())) AS empresa_premium
        FROM vagas v
        LEFT JOIN categorias c ON c.id = v.categoria_id
        LEFT JOIN perfis p ON p.id = v.perfil_empresa_id
        LEFT JOIN usuarios u ON u.id = p.usuario_id";
    /** Vaga aberta (ativa e no prazo), com a tabela vagas apelidada de "v". Também conta no limite do plano básico. */
    public const ATIVA = "v.status = 'ativa' AND (v.data_expiracao IS NULL OR v.data_expiracao >= CURDATE())";

    public function salvar(array $d, int $id = 0): int|false {
        try {
            return $this->gravar(Database::getConexao(), $d, $id);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Salva respeitando o limite de vagas abertas do plano básico.
     * A linha do perfil da empresa é travada (FOR UPDATE) durante a contagem e a gravação, então duas
     * publicações simultâneas não conseguem ultrapassar o limite (como em CandidaturaDAO::enviarComLimite).
     * @param ?int $limite null = sem limite (admin, Premium ou vaga que já estava aberta)
     * @return int|string id da vaga | 'limite' | 'erro'
     */
    public function salvarComLimite(array $d, int $id, ?int $limite): int|string {
        if ($limite === null) return $this->salvar($d, $id) ?: 'erro';
        $db = Database::getConexao();
        try {
            $db->beginTransaction();
            $db->prepare("SELECT id FROM perfis WHERE id=? FOR UPDATE")->execute([(int)$d['perfil_empresa_id']]);
            $c = $db->prepare("SELECT COUNT(*) FROM vagas v WHERE v.perfil_empresa_id=? AND v.id<>? AND ".self::ATIVA);
            $c->execute([(int)$d['perfil_empresa_id'], $id]);
            if ((int)$c->fetchColumn() >= $limite) { $db->rollBack(); return 'limite'; }
            $novoId = $this->gravar($db, $d, $id);
            $db->commit();
            return $novoId;
        } catch (Throwable) {
            if ($db->inTransaction()) $db->rollBack();
            return 'erro';
        }
    }

    /** INSERT (id 0) ou UPDATE da vaga; devolve o id. Erros sobem para quem chamou. */
    private function gravar(PDO $db, array $d, int $id): int {
        $campos = [
            $d['categoria_id'] ?: null, $d['titulo'], ($d['anunciante'] ?? '') ?: null, $d['descricao'] ?: null, $d['requisitos'] ?: null,
            $d['beneficios'] ?: null, ($d['contato'] ?? '') ?: null,
            $d['tipo_vaga'], $d['nivel_experiencia'], $d['remoto'], $d['cidade'] ?: null, $d['uf'] ?: null,
            $d['salario_minimo'], $d['salario_maximo'], $d['imagem'] ?: null, $d['status'], !empty($d['destaque']) ? 1 : 0, $d['data_expiracao'] ?: null,
        ];
        if ($id) {
            $sql = "UPDATE vagas SET categoria_id=?,titulo=?,anunciante=?,descricao=?,requisitos=?,beneficios=?,contato=?,tipo_vaga=?,nivel_experiencia=?,remoto=?,cidade=?,uf=?,
                    salario_minimo=?,salario_maximo=?,imagem=?,status=?,destaque=?,data_expiracao=?,perfil_empresa_id=? WHERE id=?";
            $db->prepare($sql)->execute([...$campos, $d['perfil_empresa_id'], $id]);
            return $id;
        }
        $sql = "INSERT INTO vagas(categoria_id,titulo,anunciante,descricao,requisitos,beneficios,contato,tipo_vaga,nivel_experiencia,remoto,cidade,uf,
                salario_minimo,salario_maximo,imagem,status,destaque,data_expiracao,perfil_empresa_id,data_publicacao)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURDATE())";
        $db->prepare($sql)->execute([...$campos, $d['perfil_empresa_id']]);
        return (int)$db->lastInsertId();
    }

    /**
     * Lista vagas. Filtros opcionais: q, cidade, categoria_id, nivel, remoto, tipo.
     */
    public function listar(bool $ativas = true, array $f = []): array {
        $where = []; $p = [];
        // Área pública: só vagas abertas de empresas com a conta ativa (empresa bloqueada some da busca).
        if ($ativas) { $where[] = self::ATIVA; $where[] = "u.ativo = 1"; }
        if (($f['q'] ?? '') !== '') {
            $where[] = "(v.titulo LIKE ? OR v.descricao LIKE ? OR v.requisitos LIKE ? OR c.nome LIKE ? OR p.nome_fantasia LIKE ? OR v.anunciante LIKE ?)";
            array_push($p, ...array_fill(0, 6, like($f['q'])));
        }
        if (($f['cidade'] ?? '') !== '') { $where[] = "v.cidade LIKE ?"; $p[] = like($f['cidade']); }
        if (!empty($f['categoria_id'])) { $where[] = "v.categoria_id = ?"; $p[] = (int)$f['categoria_id']; }
        if (in_array($f['nivel'] ?? '', self::NIVEIS, true)) { $where[] = "v.nivel_experiencia = ?"; $p[] = $f['nivel']; }
        if (in_array($f['remoto'] ?? '', self::MODELOS, true)) { $where[] = "v.remoto = ?"; $p[] = $f['remoto']; }
        if (in_array($f['tipo'] ?? '', self::TIPOS, true)) { $where[] = "v.tipo_vaga = ?"; $p[] = $f['tipo']; }
        $sql = self::SELECT.($where ? ' WHERE '.implode(' AND ', $where) : '').' ORDER BY v.destaque DESC, v.created_at DESC, v.id DESC';
        $s = Database::getConexao()->prepare($sql);
        $s->execute($p);
        return $s->fetchAll();
    }

    public function buscar(int $id): ?array {
        $s = Database::getConexao()->prepare(self::SELECT." WHERE v.id = ?");
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    /**
     * Vaga aberta igual (mesmo título, anunciante e cidade — sem diferenciar acentos nem maiúsculas,
     * pela collation do banco). Evita publicar duas vezes o mesmo anúncio.
     */
    public function buscarParecida(string $titulo, string $anunciante, string $cidade, int $excetoId = 0): ?array {
        $s = Database::getConexao()->prepare(self::SELECT." WHERE ".self::ATIVA." AND v.titulo = ? AND COALESCE(v.anunciante,'') = ? AND COALESCE(v.cidade,'') = ? AND v.id <> ? LIMIT 1");
        $s->execute([trim($titulo), trim($anunciante), trim($cidade), $excetoId]);
        return $s->fetch() ?: null;
    }

    /** Aberta = ativa, no prazo e de empresa com a conta ativa (quando a linha traz empresa_ativa). */
    public function estaAberta(array $v): bool {
        return $v['status'] === 'ativa' && (empty($v['data_expiracao']) || $v['data_expiracao'] >= date('Y-m-d'))
            && (int)($v['empresa_ativa'] ?? 1) === 1;
    }

    public function listarPorEmpresa(int $pid): array {
        // Acrescenta o total de candidaturas na lista de colunas.
        $sql = str_replace('p.usuario_id AS empresa_usuario_id', "p.usuario_id AS empresa_usuario_id, (SELECT COUNT(*) FROM candidaturas ca WHERE ca.vaga_id=v.id AND ca.status<>'cancelada') AS total_candidaturas", self::SELECT)
             ." WHERE v.perfil_empresa_id = ? ORDER BY v.destaque DESC, v.created_at DESC";
        $s = Database::getConexao()->prepare($sql);
        $s->execute([$pid]);
        return $s->fetchAll();
    }

    /** Exclui a vaga; candidaturas e matches dela saem em cascata (FK). A imagem enviada também é apagada. */
    public function excluir(int $id): bool {
        try {
            $db = Database::getConexao();
            $img = $db->prepare("SELECT imagem FROM vagas WHERE id = ?");
            $img->execute([$id]);
            $imagem = (string)($img->fetchColumn() ?: '');
            $s = $db->prepare("DELETE FROM vagas WHERE id = ?");
            $s->execute([$id]);
            if ($s->rowCount() < 1) return false;
            apagar_upload_sem_uso($imagem);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Ativar / pausar / encerrar com um clique (lista do painel). Ao ATIVAR com $limite (plano básico),
     * conta e grava na mesma transação, como salvarComLimite().
     * @return string 'ok' | 'limite' | 'erro'
     */
    public function alterarStatus(int $id, string $status, ?int $limite = null): string {
        if (!in_array($status, self::STATUS, true)) return 'erro';
        $db = Database::getConexao();
        try {
            $db->beginTransaction();
            $v = $db->prepare("SELECT perfil_empresa_id FROM vagas WHERE id=?");
            $v->execute([$id]);
            $pid = $v->fetchColumn();
            if ($pid === false) { $db->rollBack(); return 'erro'; }
            if ($status === 'ativa' && $limite !== null) {
                $db->prepare("SELECT id FROM perfis WHERE id=? FOR UPDATE")->execute([(int)$pid]);
                $c = $db->prepare("SELECT COUNT(*) FROM vagas v WHERE v.perfil_empresa_id=? AND v.id<>? AND ".self::ATIVA);
                $c->execute([(int)$pid, $id]);
                if ((int)$c->fetchColumn() >= $limite) { $db->rollBack(); return 'limite'; }
            }
            $db->prepare("UPDATE vagas SET status=? WHERE id=?")->execute([$status, $id]);
            $db->commit();
            return 'ok';
        } catch (Throwable) {
            if ($db->inTransaction()) $db->rollBack();
            return 'erro';
        }
    }

    public function incrementarVisualizacao(int $id): void {
        try { Database::getConexao()->prepare("UPDATE vagas SET visualizacoes = visualizacoes + 1 WHERE id = ?")->execute([$id]); } catch (Throwable) {}
    }

    // ------------------------------------------------------------
    // Painéis (dashboards): totais agrupados no próprio SQL, sem uma consulta por linha.
    // $perfilEmpresaId null = todas as vagas (administrador); com id = só as da empresa.
    // ------------------------------------------------------------

    /** Totais das vagas: cadastradas, abertas, pausadas, encerradas, vencidas (ativa com prazo passado), destaque e visualizações. */
    public function resumoPainel(?int $perfilEmpresaId = null): array {
        $sql = "SELECT COUNT(*) AS total, SUM(".self::ATIVA.") AS abertas, SUM(v.status = 'pausada') AS pausadas,
                       SUM(v.status = 'encerrada') AS encerradas, SUM(v.status = 'ativa' AND v.data_expiracao < CURDATE()) AS vencidas,
                       SUM(v.destaque = 1) AS destaque, SUM(v.visualizacoes) AS visualizacoes
                FROM vagas v".($perfilEmpresaId ? " WHERE v.perfil_empresa_id = ?" : '');
        $s = Database::getConexao()->prepare($sql);
        $s->execute($perfilEmpresaId ? [$perfilEmpresaId] : []);
        return array_map(fn($n) => (int)$n, $s->fetch() ?: []);
    }

    /** Vagas abertas por área (categoria), da maior para a menor. @return list<array{id:?int,rotulo:string,valor:int}> */
    public function contarPorCategoria(?int $perfilEmpresaId = null): array {
        $sql = "SELECT v.categoria_id AS id, COALESCE(c.nome, 'Sem categoria') AS rotulo, COUNT(*) AS valor
                FROM vagas v LEFT JOIN categorias c ON c.id = v.categoria_id
                WHERE ".self::ATIVA.($perfilEmpresaId ? " AND v.perfil_empresa_id = ?" : '')."
                GROUP BY v.categoria_id, c.nome ORDER BY valor DESC, rotulo";
        $s = Database::getConexao()->prepare($sql);
        $s->execute($perfilEmpresaId ? [$perfilEmpresaId] : []);
        return $s->fetchAll();
    }

    /** Vagas abertas por cidade/região administrativa, da maior para a menor. @return list<array{cidade:string,uf:string,valor:int}> */
    public function contarPorCidade(?int $perfilEmpresaId = null): array {
        $sql = "SELECT COALESCE(NULLIF(TRIM(v.cidade), ''), 'Não informada') AS nome_cidade, COALESCE(v.uf, '') AS sigla_uf, COUNT(*) AS valor
                FROM vagas v
                WHERE ".self::ATIVA.($perfilEmpresaId ? " AND v.perfil_empresa_id = ?" : '')."
                GROUP BY nome_cidade, sigla_uf ORDER BY valor DESC, nome_cidade";
        $s = Database::getConexao()->prepare($sql);
        $s->execute($perfilEmpresaId ? [$perfilEmpresaId] : []);
        return array_map(fn($r) => ['cidade' => (string)$r['nome_cidade'], 'uf' => (string)$r['sigla_uf'], 'valor' => (int)$r['valor']], $s->fetchAll());
    }

    /**
     * Desempenho de cada vaga da empresa: candidaturas (sem as canceladas), aguardando análise,
     * visualizações e match médio de quem se candidatou. As candidaturas são somadas numa subconsulta.
     */
    public function desempenhoPorEmpresa(int $perfilEmpresaId): array {
        $sql = "SELECT v.id, v.titulo, v.status, v.data_expiracao, v.destaque, v.visualizacoes, v.cidade, v.uf,
                       COALESCE(a.candidaturas, 0) AS candidaturas, COALESCE(a.aguardando, 0) AS aguardando, a.match_medio
                FROM vagas v
                LEFT JOIN (SELECT ca.vaga_id, COUNT(*) AS candidaturas, SUM(ca.status = 'enviada') AS aguardando, AVG(m.pontuacao) AS match_medio
                           FROM candidaturas ca
                           LEFT JOIN matches m ON m.vaga_id = ca.vaga_id AND m.perfil_candidato_id = ca.perfil_candidato_id
                           WHERE ca.status <> 'cancelada'
                           GROUP BY ca.vaga_id) a ON a.vaga_id = v.id
                WHERE v.perfil_empresa_id = ?
                ORDER BY candidaturas DESC, v.visualizacoes DESC, v.id DESC";
        $s = Database::getConexao()->prepare($sql);
        $s->execute([$perfilEmpresaId]);
        return $s->fetchAll();
    }
}
