<?php
declare(strict_types=1);

/**
 * Acesso à tabela `categorias` (áreas usadas para agrupar vagas e cursos).
 * O campo tipo diz onde a categoria é usada: 'vaga' ou 'curso'.
 */
final class CategoriaDAO {
    public const TIPOS = ['vaga','curso'];
    /** Mensagem do último erro de salvar/excluir, para exibir ao usuário. */
    public string $erro = '';

    public function listar(?string $tipo = null, bool $apenasAtivas = false): array {
        $sql = "SELECT c.*,
                  IF(c.tipo='vaga', (SELECT COUNT(*) FROM vagas v WHERE v.categoria_id=c.id), (SELECT COUNT(*) FROM cursos cu WHERE cu.categoria_id=c.id)) AS em_uso
                FROM categorias c WHERE 1=1";
        $p = [];
        if ($tipo) { $sql .= " AND c.tipo=?"; $p[] = $tipo; }
        if ($apenasAtivas) $sql .= " AND c.ativo=1";
        $sql .= " ORDER BY c.tipo, c.nome";
        $s = Database::getConexao()->prepare($sql);
        $s->execute($p);
        return $s->fetchAll();
    }

    public function salvar(array $d, int $id = 0): int|false {
        $this->erro = '';
        try {
            $db = Database::getConexao();
            if ($id) {
                // Trocar "Vagas" ↔ "Cursos" de uma categoria em uso deixaria vagas numa categoria de curso (ou o contrário).
                $uso = $db->prepare("SELECT (SELECT COUNT(*) FROM vagas WHERE categoria_id=?) AS vagas, (SELECT COUNT(*) FROM cursos WHERE categoria_id=?) AS cursos");
                $uso->execute([$id, $id]);
                $u = $uso->fetch();
                if (($d['tipo'] === 'curso' && (int)$u['vagas'] > 0) || ($d['tipo'] === 'vaga' && (int)$u['cursos'] > 0)) {
                    $this->erro = 'Esta categoria está em uso: não é possível trocar entre "Vagas" e "Cursos". Crie uma categoria nova para o outro tipo.';
                    return false;
                }
                $db->prepare("UPDATE categorias SET nome=?,tipo=?,ativo=? WHERE id=?")->execute([$d['nome'], $d['tipo'], (int)$d['ativo'], $id]);
                return $id;
            }
            $db->prepare("INSERT INTO categorias(nome,tipo,ativo) VALUES(?,?,?)")->execute([$d['nome'], $d['tipo'], (int)$d['ativo']]);
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            $this->erro = $e->getCode() === '23000' ? 'Já existe uma categoria com esse nome para esse tipo.' : 'Erro ao salvar a categoria.';
            return false;
        }
    }

    public function buscar(int $id): ?array {
        $s = Database::getConexao()->prepare("SELECT * FROM categorias WHERE id=?");
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public function buscarPorNome(string $nome, string $tipo): ?array {
        $s = Database::getConexao()->prepare("SELECT * FROM categorias WHERE nome=? AND tipo=?");
        $s->execute([$nome, $tipo]);
        return $s->fetch() ?: null;
    }

    /** Ativar (1) ou desativar (0) com um clique. false = categoria não existe. */
    public function alterarAtivo(int $id, bool $ativo): bool {
        if (!$this->buscar($id)) return false;
        Database::getConexao()->prepare("UPDATE categorias SET ativo=? WHERE id=?")->execute([$ativo ? 1 : 0, $id]);
        return true;
    }
}
