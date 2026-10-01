<?php
declare(strict_types=1);

/**
 * Acesso à tabela `calibracao_extracao` (termos do Calibrador das máquinas de extração)
 * e aos nomes conhecidos (empresas e instituições já cadastradas), usados pelo Calibrador.
 */
final class CalibracaoDAO {
    /** Mensagem do último erro de salvar, para exibir ao usuário. */
    public string $erro = '';

    public function listar(): array {
        $this->garantirTabela();
        return Database::getConexao()->query("SELECT * FROM calibracao_extracao ORDER BY contexto, termo")->fetchAll();
    }

    /** Termos ativos (o que o Calibrador usa na extração). */
    public function ativos(): array {
        $this->garantirTabela();
        return Database::getConexao()->query("SELECT contexto, termo, destino FROM calibracao_extracao WHERE ativo=1")->fetchAll();
    }

    public function buscar(int $id): ?array {
        $this->garantirTabela();
        $s = Database::getConexao()->prepare("SELECT * FROM calibracao_extracao WHERE id=?");
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    /** @param array{contexto:string,termo:string,destino:string,ativo:int} $d */
    public function salvar(array $d, int $id = 0, ?int $usuarioId = null): int|false {
        $this->erro = '';
        $this->garantirTabela();
        $chave = Competencias::normalizar($d['termo']);
        if ($chave === '') { $this->erro = 'O termo precisa ter pelo menos uma letra ou número.'; return false; }
        $db = Database::getConexao();
        $dup = $db->prepare("SELECT id, destino FROM calibracao_extracao WHERE contexto=? AND termo_chave=? AND id<>?");
        $dup->execute([$d['contexto'], $chave, $id]);
        if ($outro = $dup->fetch()) {
            $this->erro = 'Esse termo já está calibrado nesse contexto (vai para "'.Calibrador::rotuloDestino($d['contexto'], (string)$outro['destino']).'"). Edite o que já existe.';
            return false;
        }
        if ($id) {
            $db->prepare("UPDATE calibracao_extracao SET contexto=?, termo=?, termo_chave=?, destino=?, ativo=? WHERE id=?")
               ->execute([$d['contexto'], $d['termo'], $chave, $d['destino'], (int)$d['ativo'], $id]);
            return $id;
        }
        $db->prepare("INSERT INTO calibracao_extracao(contexto, termo, termo_chave, destino, ativo, usuario_id) VALUES (?,?,?,?,?,?)")
           ->execute([$d['contexto'], $d['termo'], $chave, $d['destino'], (int)$d['ativo'], $usuarioId]);
        return (int)$db->lastInsertId();
    }

    public function alterarAtivo(int $id, bool $ativo): bool {
        $this->garantirTabela();
        $s = Database::getConexao()->prepare("UPDATE calibracao_extracao SET ativo=? WHERE id=?");
        $s->execute([(int)$ativo, $id]);
        return $s->rowCount() > 0 || $this->buscar($id) !== null;
    }

    public function excluir(int $id): bool {
        $this->garantirTabela();
        $s = Database::getConexao()->prepare("DELETE FROM calibracao_extracao WHERE id=?");
        $s->execute([$id]);
        return $s->rowCount() > 0;
    }

    /**
     * Nomes já cadastrados no sistema — a parte do calibrador que se atualiza sozinha.
     * 'empresa' = anunciante das vagas + nome fantasia das empresas; 'instituicao' = instituição dos cursos.
     * @return list<string>
     */
    public function nomesConhecidos(string $tipo): array {
        $sql = match ($tipo) {
            'empresa' => "SELECT anunciante AS nome FROM vagas WHERE anunciante IS NOT NULL AND anunciante<>''
                          UNION SELECT p.nome_fantasia FROM perfis p JOIN usuarios u ON u.id=p.usuario_id
                          WHERE u.tipo='empresa' AND p.nome_fantasia IS NOT NULL AND p.nome_fantasia<>''",
            'instituicao' => "SELECT DISTINCT instituicao AS nome FROM cursos WHERE instituicao IS NOT NULL AND instituicao<>''",
            default => '',
        };
        if ($sql === '') return [];
        return array_map('strval', Database::getConexao()->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Banco importado de uma versão anterior (sem a tabela): cria na primeira vez que precisar,
     * igual ao database/schema.sql.
     */
    private function garantirTabela(): void {
        static $pronta = false;
        if ($pronta) return;
        Database::getConexao()->exec("CREATE TABLE IF NOT EXISTS calibracao_extracao (
            id INT AUTO_INCREMENT PRIMARY KEY,
            contexto VARCHAR(30) NOT NULL,
            termo VARCHAR(120) NOT NULL,
            termo_chave VARCHAR(120) NOT NULL,
            destino VARCHAR(100) NOT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            usuario_id INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_calibracao(contexto, termo_chave),
            FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");
        $pronta = true;
    }
}
