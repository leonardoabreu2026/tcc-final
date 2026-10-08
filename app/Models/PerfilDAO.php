<?php
declare(strict_types=1);

/**
 * Acesso à tabela `perfis`: dados profissionais do candidato ou dados da empresa.
 * Cada usuário candidato/empresa tem um perfil (1:1). As consultas já juntam
 * nome, e-mail, telefone, tipo e situação da conta (tabela `usuarios`).
 */
final class PerfilDAO {
    public const NIVEIS = ['estagiario','junior','pleno','senior'];

    /**
     * Cria o perfil padrão do usuário (Brasília/DF, público, aceite LGPD agora) — ÚNICO lugar com esses valores.
     * Usa a mesma conexão do resto da requisição (Database é Singleton): chamado dentro de uma
     * transação (ex.: UsuarioDAO::cadastrar), o INSERT faz parte dela.
     * $seNaoExistir = true: não faz nada se o usuário já tiver perfil (retorna 0).
     */
    public function criar(int $uid, bool $seNaoExistir = false): int {
        $db = Database::getConexao();
        $s = $db->prepare("INSERT".($seNaoExistir ? " IGNORE" : "")." INTO perfis(usuario_id,cidade,uf,publico,aceite_lgpd,aceite_em) VALUES(?,?,?,1,1,NOW())");
        $s->execute([$uid, 'Brasília', 'DF']);
        return $s->rowCount() > 0 ? (int)$db->lastInsertId() : 0;
    }

    /** Busca o perfil do usuário e cria um vazio se ainda não existir. */
    public function obterOuCriar(int $uid): ?array {
        $p = $this->buscarPorUsuarioId($uid);
        if (!$p) { $this->criar($uid); $p = $this->buscarPorUsuarioId($uid); }
        return $p;
    }

    public function buscarPorUsuarioId(int $uid): ?array {
        $s = Database::getConexao()->prepare("SELECT p.*,u.nome,u.email,u.telefone,u.tipo,u.ativo FROM perfis p JOIN usuarios u ON u.id=p.usuario_id WHERE p.usuario_id=?");
        $s->execute([$uid]);
        return $s->fetch() ?: null;
    }

    public function buscarPorId(int $id): ?array {
        $s = Database::getConexao()->prepare("SELECT p.*,u.nome,u.email,u.telefone,u.tipo,u.ativo FROM perfis p JOIN usuarios u ON u.id=p.usuario_id WHERE p.id=?");
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public function salvar(PerfilDTO $p): bool {
        $nivel = in_array($p->getNivelExperiencia(), self::NIVEIS, true) ? $p->getNivelExperiencia() : 'junior';
        $uf = strtoupper(substr(trim((string)$p->getUf()), 0, 2));
        $data = $p->getDataNascimento();
        $data = is_string($data) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) ? $data : null;
        $sql = "UPDATE perfis SET titulo_profissional=?,bio=?,data_nascimento=?,cidade=?,uf=?,nome_fantasia=?,cnpj=?,setor=?,site=?,habilidades=?,experiencias=?,formacao=?,cursos_complementares=?,informacoes_adicionais=?,idiomas=?,competencias=?,nivel_experiencia=?,disponibilidade=?,objetivo=?,foto=?,publico=?,aceite_lgpd=?,aceite_em=IF(?=1,COALESCE(aceite_em,NOW()),aceite_em) WHERE id=?";
        // Campos da extração de currículo: só são gravados quando vierem nos dados
        // (outras telas, como o perfil da empresa, não os enviam e não devem apagá-los).
        $extras = []; $valExtras = [];
        if ($p->tem('links')) { $extras[] = 'links=?'; $valExtras[] = self::txt($p->getLinks()); }
        if ($p->tem('cnh')) { $c = strtoupper(preg_replace('/[^A-Ea-e]/', '', (string)$p->getCnh()) ?? ''); $extras[] = 'cnh=?'; $valExtras[] = $c !== '' ? mb_substr($c, 0, 5) : null; }
        if ($p->tem('pretensao_salarial')) { $extras[] = 'pretensao_salarial=?'; $valExtras[] = self::txt($p->getPretensaoSalarial(), 60); }
        if ($extras) $sql = str_replace(' WHERE id=?', ','.implode(',', $extras).' WHERE id=?', $sql);
        try {
            return Database::getConexao()->prepare($sql)->execute([
                self::txt($p->getTituloProfissional(), 255), self::txt($p->getBio()), $data, self::txt($p->getCidade(), 100) ?? 'Brasília', $uf ?: 'DF',
                self::txt($p->getNomeFantasia(), 255), self::txt($p->getCnpj(), 20), self::txt($p->getSetor(), 100), self::txt($p->getSite(), 255),
                self::txt($p->getHabilidades()), self::txt($p->getExperiencias()), self::txt($p->getFormacao()),
                self::txt($p->getCursosComplementares()), self::txt($p->getInformacoesAdicionais()), self::txt($p->getIdiomas()),
                self::txt($p->getCompetencias()), $nivel, self::txt($p->getDisponibilidade(), 100), self::txt($p->getObjetivo()), $p->getFoto() ?: null,
                (int)$p->getPublico(), (int)$p->getAceiteLgpd(), (int)$p->getAceiteLgpd(), ...$valExtras, ...[(int)$p->getId()],
            ]);
        } catch (Throwable) {
            return false;
        }
    }

    /** Texto aparado; vazio vira null; corta no tamanho da coluna. */
    private static function txt(mixed $v, int $max = 0): ?string {
        $v = trim((string)($v ?? ''));
        if ($v === '') return null;
        return $max > 0 ? mb_substr($v, 0, $max) : $v;
    }

    public function listarEmpresas(): array {
        return Database::getConexao()->query("SELECT p.*,u.nome,u.email,u.tipo FROM perfis p JOIN usuarios u ON u.id=p.usuario_id WHERE u.tipo='empresa' AND u.ativo=1 ORDER BY COALESCE(NULLIF(p.nome_fantasia,''),u.nome)")->fetchAll();
    }

    /**
     * Banco de Talentos: candidatos ativos com perfil público, com o último currículo
     * e a marca de VIP. VIP primeiro, depois os mais recentes.
     * Filtros opcionais: termo (nome, título, habilidades, competências, experiências,
     * cursos ou links) e cidade.
     */
    public function listarTalentos(string $termo = '', string $cidade = ''): array {
        $sql = "SELECT p.*, u.nome, u.email, u.telefone,
                       (SELECT id FROM curriculos c WHERE c.perfil_id = p.id AND c.ativo = 1 ORDER BY c.id DESC LIMIT 1) AS curriculo_id,
                       (SELECT COUNT(*) FROM assinaturas a
                        WHERE a.usuario_id = u.id AND a.plano = 'assinante' AND a.status = 'ativa'
                        AND a.data_fim >= CURDATE()) AS is_vip
                FROM perfis p
                JOIN usuarios u ON u.id = p.usuario_id
                WHERE u.tipo = 'candidato' AND u.ativo = 1 AND p.publico = 1";
        $params = [];
        if ($termo !== '') {
            $sql .= " AND (u.nome LIKE ? OR p.titulo_profissional LIKE ? OR p.habilidades LIKE ? OR p.competencias LIKE ? OR p.experiencias LIKE ? OR p.cursos_complementares LIKE ? OR p.links LIKE ?)";
            array_push($params, ...array_fill(0, 7, like($termo)));
        }
        if ($cidade !== '') {
            $sql .= " AND p.cidade LIKE ?";
            $params[] = like($cidade);
        }
        $sql .= " ORDER BY is_vip DESC, p.id DESC";
        $s = Database::getConexao()->prepare($sql);
        $s->execute($params);
        return $s->fetchAll();
    }

    /** Candidatos ativos (para recalcular o match de uma vaga). */
    public function listarCandidatos(): array {
        return Database::getConexao()->query("SELECT p.*,u.nome,u.email,u.telefone,u.tipo,u.ativo FROM perfis p JOIN usuarios u ON u.id=p.usuario_id WHERE u.tipo='candidato' AND u.ativo=1")->fetchAll();
    }
}
