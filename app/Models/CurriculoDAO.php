<?php
declare(strict_types=1);

/**
 * Acesso à tabela `curriculos`: arquivos enviados pelo candidato (PDF/DOCX/DOC)
 * e o texto extraído deles (usado pela máquina de match).
 * "Cancelar currículo" não apaga: o arquivo sai da lista do candidato (ativo = 0) e deixa de ser usado
 * em novas candidaturas e no match, mas as empresas que já o receberam continuam podendo abri-lo.
 */
final class CurriculoDAO {
    public function listarPorPerfil(int $pid): array {
        $s = Database::getConexao()->prepare("SELECT * FROM curriculos WHERE perfil_id=? AND ativo=1 ORDER BY created_at DESC,id DESC");
        $s->execute([$pid]);
        return $s->fetchAll();
    }

    public function buscar(int $id): ?array {
        $s = Database::getConexao()->prepare("SELECT * FROM curriculos WHERE id=?");
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    /** Currículo ativo do candidato (os cancelados não servem para novas candidaturas). */
    public function buscarDoPerfil(int $id, int $pid): ?array {
        $s = Database::getConexao()->prepare("SELECT * FROM curriculos WHERE id=? AND perfil_id=? AND ativo=1");
        $s->execute([$id, $pid]);
        return $s->fetch() ?: null;
    }

    /** Texto extraído do currículo mais recente (usado pela máquina de match). */
    public function ultimoTexto(int $pid): string {
        $s = Database::getConexao()->prepare("SELECT curriculo_texto FROM curriculos WHERE perfil_id=? AND ativo=1 AND curriculo_texto IS NOT NULL AND curriculo_texto<>'' ORDER BY created_at DESC,id DESC LIMIT 1");
        $s->execute([$pid]);
        return (string)($s->fetchColumn() ?: '');
    }

    /**
     * Texto do currículo mais recente de CADA perfil, numa consulta só (mesmo critério de ultimoTexto).
     * Usado quando o match de uma vaga é recalculado com todos os candidatos.
     * @return array<int,string> perfil_id => texto
     */
    public function ultimosTextos(): array {
        $sql = "SELECT c.perfil_id, c.curriculo_texto FROM curriculos c
                WHERE c.id=(SELECT c2.id FROM curriculos c2
                            WHERE c2.perfil_id=c.perfil_id AND c2.ativo=1 AND c2.curriculo_texto IS NOT NULL AND c2.curriculo_texto<>''
                            ORDER BY c2.created_at DESC,c2.id DESC LIMIT 1)";
        $out = [];
        foreach (Database::getConexao()->query($sql)->fetchAll() as $r) $out[(int)$r['perfil_id']] = (string)$r['curriculo_texto'];
        return $out;
    }

    public function salvar(array $d): int {
        $db = Database::getConexao();
        $s = $db->prepare("INSERT INTO curriculos(perfil_id,titulo,arquivo_pdf,arquivo_tipo,curriculo_texto,template) VALUES(?,?,?,?,?,?)");
        $s->execute([$d['perfil_id'], mb_substr((string)$d['titulo'], 0, 255), $d['arquivo_pdf'], $d['arquivo_tipo'], $d['curriculo_texto'] ?: null, $d['template'] ?? 'moderno']);
        return (int)$db->lastInsertId();
    }

    /** Cancela o currículo do candidato. O registro e o arquivo continuam guardados. */
    public function cancelar(int $id, int $pid): bool {
        $s = Database::getConexao()->prepare("UPDATE curriculos SET ativo=0 WHERE id=? AND perfil_id=? AND ativo=1");
        $s->execute([$id, $pid]);
        return $s->rowCount() > 0;
    }

    /** A empresa pode abrir o currículo que foi enviado em candidatura para uma vaga dela. */
    public function enviadoParaEmpresa(int $curriculoId, int $perfilEmpresaId): bool {
        $s = Database::getConexao()->prepare("SELECT 1 FROM candidaturas c JOIN vagas v ON v.id=c.vaga_id WHERE c.curriculo_id=? AND v.perfil_empresa_id=? AND c.status<>'cancelada' LIMIT 1");
        $s->execute([$curriculoId, $perfilEmpresaId]);
        return (bool)$s->fetchColumn();
    }

    public function registrarDownload(int $id): void {
        try { Database::getConexao()->prepare('UPDATE curriculos SET downloads=downloads+1 WHERE id=?')->execute([$id]); } catch (Throwable) {}
    }
}
