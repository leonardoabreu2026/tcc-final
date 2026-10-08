<?php
declare(strict_types=1);

/**
 * Acesso à tabela `usuarios` (contas de login) e às tabelas de segurança da conta:
 * `tentativas_login` (bloqueio por força-bruta) e `redefinicoes_senha` (recuperação de senha).
 * Senhas: sempre password_hash()/password_verify() (bcrypt) — nunca em texto puro.
 */
final class UsuarioDAO {
    public const TIPOS = ['admin','candidato','empresa'];
    /** Validade do link de redefinição de senha, em minutos. */
    public const REDEFINICAO_MINUTOS = 30;
    /** Mensagem do último erro, para exibir ao usuário. */
    public string $erro = '';

    /**
     * Cria o usuário e, para candidato/empresa, o perfil — na mesma transação.
     */
    public function cadastrar(UsuarioDTO $u): int|false {
        $this->erro = '';
        $email = normalizar_email((string)$u->getEmail());
        if ($this->emailEmUso($email)) { $this->erro = 'Este e-mail já está cadastrado.'; return false; }
        $db = Database::getConexao();
        try {
            $db->beginTransaction();
            $s = $db->prepare("INSERT INTO usuarios(nome,email,senha,tipo,telefone,ativo) VALUES(?,?,?,?,?,?)");
            $s->execute([trim((string)$u->getNome()), $email, password_hash((string)$u->getSenha(), PASSWORD_DEFAULT), $u->getTipo(), trim((string)$u->getTelefone()) ?: null, (int)$u->getAtivo()]);
            $id = (int)$db->lastInsertId();
            if (in_array($u->getTipo(), ['candidato','empresa'], true)) {
                (new PerfilDAO())->criar($id); // mesma conexão: dentro desta transação
            }
            $db->commit();
            return $id;
        } catch (Throwable $ex) {
            if ($db->inTransaction()) $db->rollBack();
            // 23000 = chave única: dois cadastros simultâneos com o mesmo e-mail.
            $this->erro = ($ex instanceof PDOException && $ex->getCode() === '23000') ? 'Este e-mail já está cadastrado.' : 'Não foi possível cadastrar o usuário.';
            return false;
        }
    }

    public function atualizar(int $id, array $d): bool {
        $this->erro = '';
        $email = normalizar_email((string)$d['email']);
        if ($this->emailEmUso($email, $id)) { $this->erro = 'Este e-mail já pertence a outro usuário.'; return false; }
        $atual = $this->buscarPorId($id);
        if (!$atual) { $this->erro = 'Usuário não encontrado.'; return false; }
        // Nunca deixa o sistema sem nenhum administrador ativo.
        $deixaDeSerAdminAtivo = $atual['tipo'] === 'admin' && (int)$atual['ativo'] === 1 && ($d['tipo'] !== 'admin' || !(int)$d['ativo']);
        if ($deixaDeSerAdminAtivo && $this->contarAdminsAtivos() <= 1) { $this->erro = 'É preciso manter pelo menos um administrador ativo.'; return false; }
        $sql = "UPDATE usuarios SET nome=?,email=?,tipo=?,telefone=?,ativo=?";
        $p = [trim((string)$d['nome']), $email, $d['tipo'], trim((string)$d['telefone']) ?: null, (int)$d['ativo']];
        $novoHash = ($d['senha'] ?? '') !== '' ? password_hash((string)$d['senha'], PASSWORD_DEFAULT) : null;
        if ($novoHash !== null) { $sql .= ",senha=?"; $p[] = $novoHash; }
        $sql .= " WHERE id=?"; $p[] = $id;
        $db = Database::getConexao();
        try {
            $db->beginTransaction();
            $db->prepare($sql)->execute($p);
            // Candidato/empresa sempre precisam de perfil (ex.: admin mudou o tipo da conta).
            if (in_array($d['tipo'], ['candidato','empresa'], true)) {
                (new PerfilDAO())->criar($id, true); // mesma conexão: dentro desta transação
            }
            $db->commit();
            // Senha nova derruba as outras sessões da conta; se foi o próprio usuário, a atual continua.
            if ($novoHash !== null) renovar_marca_senha($id, $novoHash);
            return true;
        } catch (Throwable $ex) {
            if ($db->inTransaction()) $db->rollBack();
            $this->erro = ($ex instanceof PDOException && $ex->getCode() === '23000') ? 'Este e-mail já pertence a outro usuário.' : 'Não foi possível atualizar o usuário.';
            return false;
        }
    }

    /** Telefone da conta (fica em `usuarios`, não no perfil). null apaga. */
    public function atualizarTelefone(int $id, ?string $telefone): void {
        Database::getConexao()->prepare('UPDATE usuarios SET telefone=? WHERE id=?')->execute([$telefone, $id]);
    }

    /** Nome da conta (usado quando o candidato aceita o nome encontrado no currículo). */
    public function atualizarNome(int $id, string $nome): void {
        Database::getConexao()->prepare('UPDATE usuarios SET nome=? WHERE id=?')->execute([$nome, $id]);
    }

    public function emailEmUso(string $email, int $excetoId = 0): bool {
        $s = Database::getConexao()->prepare("SELECT 1 FROM usuarios WHERE email=? AND id<>?");
        $s->execute([normalizar_email($email), $excetoId]);
        return (bool)$s->fetchColumn();
    }

    /** Lista as contas; perfil_id serve para o link "Ver portfólio" dos candidatos. */
    public function listar(?string $tipo = null, string $q = ''): array {
        $sql = "SELECT u.*, p.id AS perfil_id FROM usuarios u LEFT JOIN perfis p ON p.usuario_id=u.id WHERE 1=1"; $p = [];
        if ($tipo && in_array($tipo, self::TIPOS, true)) { $sql .= " AND u.tipo=?"; $p[] = $tipo; }
        if ($q !== '') { $sql .= " AND (u.nome LIKE ? OR u.email LIKE ?)"; $p[] = like($q); $p[] = like($q); }
        $sql .= " ORDER BY u.created_at DESC, u.id DESC";
        $s = Database::getConexao()->prepare($sql);
        $s->execute($p);
        return $s->fetchAll();
    }

    /**
     * Ficha da conta para o "Ver" do painel: dados do usuário + perfil + números do que ela tem.
     * null = usuário não existe.
     */
    public function resumo(int $id): ?array {
        $s = Database::getConexao()->prepare(
            "SELECT u.id, u.nome, u.email, u.tipo, u.telefone, u.ativo, u.ultimo_acesso, u.created_at,
                    p.id AS perfil_id, p.nome_fantasia, p.titulo_profissional, p.cidade, p.uf, p.setor, p.publico,
                    (SELECT COUNT(*) FROM vagas v WHERE v.perfil_empresa_id = p.id) AS total_vagas,
                    (SELECT COUNT(*) FROM candidaturas c WHERE c.perfil_candidato_id = p.id) AS total_candidaturas,
                    (SELECT COUNT(*) FROM curriculos cv WHERE cv.perfil_id = p.id AND cv.ativo = 1) AS total_curriculos,
                    (SELECT a.plano FROM assinaturas a WHERE a.usuario_id = u.id AND a.status = 'ativa' AND a.data_fim >= CURDATE() ORDER BY a.id DESC LIMIT 1) AS plano_ativo
             FROM usuarios u LEFT JOIN perfis p ON p.usuario_id = u.id WHERE u.id = ?");
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    /**
     * Ativar/bloquear com um clique, com as mesmas travas do formulário: nunca o próprio
     * administrador e nunca o último administrador ativo. '' = ok; senão, a mensagem de erro.
     */
    public function alterarAtivo(int $id, bool $ativo, int $meuId): string {
        $alvo = $this->buscarPorId($id);
        if (!$alvo) return 'Usuário não encontrado.';
        if ($id === $meuId && !$ativo) return 'Você não pode bloquear a própria conta.';
        if (!$ativo && $alvo['tipo'] === 'admin' && (int)$alvo['ativo'] && $this->contarAdminsAtivos() <= 1) return 'É preciso manter pelo menos um administrador ativo.';
        Database::getConexao()->prepare("UPDATE usuarios SET ativo=? WHERE id=?")->execute([$ativo ? 1 : 0, $id]);
        return '';
    }

    public function contarAdminsAtivos(): int {
        return (int)Database::getConexao()->query("SELECT COUNT(*) FROM usuarios WHERE tipo='admin' AND ativo=1")->fetchColumn();
    }

    public function buscarPorId(int $id): ?array {
        $s = Database::getConexao()->prepare("SELECT * FROM usuarios WHERE id=?");
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public function buscarPorEmail(string $email): ?array {
        $s = Database::getConexao()->prepare("SELECT * FROM usuarios WHERE email=?");
        $s->execute([normalizar_email($email)]);
        return $s->fetch() ?: null;
    }

    /** Por que o último autenticar() falhou: 'sem_conta', 'desativada' ou 'senha' (a tela só detalha no ambiente local). */
    public string $motivoFalha = '';

    /**
     * Variações aceitas da senha digitada (tolerância a erros comuns, como fazem os grandes sites):
     * a própria senha, sem espaços nas pontas, com a 1ª letra na caixa trocada ("admin@123" → "Admin@123",
     * celular que põe maiúscula sozinho) e com o Caps Lock ligado ("aDMIN@123"). Sempre no máximo 4.
     * @return list<string>
     */
    public static function variantesSenha(string $senha): array {
        $t = trim($senha);
        $trocar = fn(string $c) => mb_strtoupper($c) === $c ? mb_strtolower($c) : mb_strtoupper($c);
        $primeira = $t === '' ? '' : $trocar(mb_substr($t, 0, 1)).mb_substr($t, 1);
        $capsLock = implode('', array_map($trocar, mb_str_split($t)));
        return array_values(array_unique(array_filter([$senha, $t, $primeira, $capsLock], fn($v) => $v !== '')));
    }

    public function autenticar(string $email, string $senha): array|false {
        $this->motivoFalha = '';
        $u = $this->buscarPorEmail($email);
        $variantes = self::variantesSenha($senha);
        if (!$u) {
            // Gasta o mesmo tempo de um login real: o tempo de resposta não revela quais e-mails existem.
            foreach ($variantes as $v) password_verify($v, '$2y$10$uiTuTWeHZjGisseAQFKgOOZrIqsMAT2p5wAY886sw.TAw7x5bae56');
            $this->motivoFalha = 'sem_conta';
            return false;
        }
        $certa = null;
        foreach ($variantes as $v) if (password_verify($v, $u['senha'])) { $certa = $v; break; }
        $confere = $certa !== null;
        if (!$confere) $this->motivoFalha = 'senha';
        elseif ((int)$u['ativo'] !== 1) $this->motivoFalha = 'desativada';
        if ((int)$u['ativo'] === 1 && $confere) {
            $db = Database::getConexao();
            $db->prepare("UPDATE usuarios SET ultimo_acesso=NOW() WHERE id=?")->execute([$u['id']]);
            // Só refaz o hash se o ALGORITMO mudou (não por diferença de custo do bcrypt): refazer muda o hash,
            // e a sessão entende hash novo como "senha alterada" — derrubaria as outras sessões abertas da conta.
            if ((password_get_info($u['senha'])['algo'] ?? null) !== PASSWORD_DEFAULT) {
                // Devolve o hash novo: é dele que a sessão tira a "marca" da senha (ver iniciar_sessao_usuario).
                $u['senha'] = password_hash($certa, PASSWORD_DEFAULT);
                $db->prepare("UPDATE usuarios SET senha=? WHERE id=?")->execute([$u['senha'], $u['id']]);
            }
            return $u;
        }
        return false;
    }

    // ------------------------------------------------------------
    // Proteção contra força-bruta no login (tabela tentativas_login)
    // ------------------------------------------------------------

    /**
     * Minutos restantes de bloqueio para este IP/e-mail (0 = liberado).
     * Bloqueia, dentro da janela (LOGIN_JANELA_MINUTOS), com:
     *  - LOGIN_MAX_TENTATIVAS erros no mesmo e-mail a partir deste IP;
     *  - LOGIN_MAX_TENTATIVAS_IP erros deste IP, somando todos os e-mails;
     *  - LOGIN_MAX_TENTATIVAS_CONTA erros no mesmo e-mail vindos de qualquer IP (ataque distribuído).
     * O bloqueio da conta acaba sozinho quando os erros saem da janela (nunca é permanente).
     * $somarIps = false ignora o limite da conta (usado em "Esqueci minha senha": o dono da conta,
     * vindo de outro IP, ainda consegue pedir o link durante um ataque).
     */
    public function minutosBloqueioLogin(string $ip, string $email, bool $somarIps = true): int {
        try {
            $db = Database::getConexao();
            $janela = max(1, (int)LOGIN_JANELA_MINUTOS);   // número inteiro: vai direto no SQL
            $s = $db->prepare("SELECT COUNT(*) n, MIN(created_at) primeira FROM tentativas_login WHERE ip=? AND email=? AND created_at > NOW() - INTERVAL $janela MINUTE");
            $s->execute([$ip, normalizar_email($email)]);
            $porEmail = $s->fetch();
            $s = $db->prepare("SELECT COUNT(*) n, MIN(created_at) primeira FROM tentativas_login WHERE ip=? AND created_at > NOW() - INTERVAL $janela MINUTE");
            $s->execute([$ip]);
            $porIp = $s->fetch();
            $s = $db->prepare("SELECT COUNT(*) n, MIN(created_at) primeira FROM tentativas_login WHERE email=? AND created_at > NOW() - INTERVAL $janela MINUTE");
            $s->execute([normalizar_email($email)]);
            $porConta = $s->fetch();
            $primeira = null;
            if ((int)$porEmail['n'] >= LOGIN_MAX_TENTATIVAS) $primeira = $porEmail['primeira'];
            elseif ((int)$porIp['n'] >= LOGIN_MAX_TENTATIVAS_IP) $primeira = $porIp['primeira'];
            elseif ($somarIps && (int)$porConta['n'] >= LOGIN_MAX_TENTATIVAS_CONTA) $primeira = $porConta['primeira'];
            if ($primeira === null) return 0;
            $libera = strtotime((string)$primeira) + $janela * 60;
            return max(1, (int)ceil(($libera - time()) / 60));
        } catch (Throwable) {
            return 0; // tabela ausente (banco antigo): não impede o login
        }
    }

    /** Quantos erros ainda cabem para este IP + e-mail antes da pausa (para avisar na tela). */
    public function tentativasRestantes(string $ip, string $email): int {
        try {
            $janela = max(1, (int)LOGIN_JANELA_MINUTOS);
            $s = Database::getConexao()->prepare("SELECT COUNT(*) FROM tentativas_login WHERE ip=? AND email=? AND created_at > NOW() - INTERVAL $janela MINUTE");
            $s->execute([$ip, normalizar_email($email)]);
            return max(0, LOGIN_MAX_TENTATIVAS - (int)$s->fetchColumn());
        } catch (Throwable) {
            return LOGIN_MAX_TENTATIVAS;
        }
    }

    public function registrarFalhaLogin(string $ip, string $email): void {
        try {
            $db = Database::getConexao();
            $db->prepare("INSERT INTO tentativas_login(ip,email) VALUES(?,?)")->execute([$ip, mb_substr(normalizar_email($email), 0, 255)]);
            // Limpeza periódica de registros antigos (mantém a tabela pequena).
            if (random_int(1, 20) === 1) $db->exec("DELETE FROM tentativas_login WHERE created_at < NOW() - INTERVAL 1 DAY");
        } catch (Throwable) {}
    }

    public function limparFalhasLogin(string $ip, string $email): void {
        try {
            Database::getConexao()->prepare("DELETE FROM tentativas_login WHERE ip=? AND email=?")->execute([$ip, normalizar_email($email)]);
        } catch (Throwable) {}
    }

    // ------------------------------------------------------------
    // Recuperação de senha (tabela redefinicoes_senha)
    // ------------------------------------------------------------

    /**
     * Gera um token de redefinição para o e-mail informado.
     * Retorna o token em texto (só existe na memória; o banco guarda o hash)
     * ou null se o e-mail não existir, a conta estiver inativa ou houver pedidos demais.
     */
    public function criarTokenRedefinicao(string $email, string $ip): ?string {
        $this->erro = '';
        $u = $this->buscarPorEmail($email);
        if (!$u || (int)$u['ativo'] !== 1) return null;
        $db = Database::getConexao();
        $s = $db->prepare("SELECT COUNT(*) FROM redefinicoes_senha WHERE usuario_id=? AND created_at > NOW() - INTERVAL 1 HOUR");
        $s->execute([$u['id']]);
        if ((int)$s->fetchColumn() >= 3) { $this->erro = 'limite'; return null; }
        $token = bin2hex(random_bytes(32));
        $db->beginTransaction();
        try {
            // Só o link mais recente vale.
            $db->prepare("UPDATE redefinicoes_senha SET usado_em=NOW() WHERE usuario_id=? AND usado_em IS NULL")->execute([$u['id']]);
            $db->prepare("INSERT INTO redefinicoes_senha(usuario_id,token_hash,expira_em,ip) VALUES(?,?,NOW() + INTERVAL ".self::REDEFINICAO_MINUTOS." MINUTE,?)")
               ->execute([$u['id'], hash('sha256', $token), $ip]);
            $db->commit();
        } catch (Throwable $ex) {
            $db->rollBack();
            throw $ex;
        }
        return $token;
    }

    /** Usuário dono de um token válido (não usado e não expirado), ou null. */
    public function buscarPorTokenRedefinicao(string $token): ?array {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $s = Database::getConexao()->prepare(
            "SELECT u.*, r.id AS redefinicao_id FROM redefinicoes_senha r JOIN usuarios u ON u.id=r.usuario_id
             WHERE r.token_hash=? AND r.usado_em IS NULL AND r.expira_em > NOW() AND u.ativo=1 LIMIT 1");
        $s->execute([hash('sha256', $token)]);
        return $s->fetch() ?: null;
    }

    /** Troca a senha usando o token (uso único). */
    public function redefinirSenha(string $token, string $novaSenha): bool {
        $u = $this->buscarPorTokenRedefinicao($token);
        if (!$u) return false;
        $db = Database::getConexao();
        $db->beginTransaction();
        try {
            // Marca como usado primeiro; se outro pedido já usou o token, rowCount = 0.
            $m = $db->prepare("UPDATE redefinicoes_senha SET usado_em=NOW() WHERE id=? AND usado_em IS NULL");
            $m->execute([$u['redefinicao_id']]);
            if ($m->rowCount() < 1) { $db->rollBack(); return false; }
            $db->prepare("UPDATE usuarios SET senha=? WHERE id=?")->execute([password_hash($novaSenha, PASSWORD_DEFAULT), $u['id']]);
            $db->prepare("UPDATE redefinicoes_senha SET usado_em=NOW() WHERE usuario_id=? AND usado_em IS NULL")->execute([$u['id']]);
            $db->prepare("DELETE FROM tentativas_login WHERE email=?")->execute([$u['email']]);
            $db->commit();
            return true;
        } catch (Throwable) {
            if ($db->inTransaction()) $db->rollBack();
            return false;
        }
    }

    /** A senha digitada é a desta conta? Mesma tolerância do login (espaços nas pontas, 1ª letra trocada, Caps Lock). */
    public function senhaConfere(int $id, string $senha): bool {
        $u = $this->buscarPorId($id);
        if (!$u || $senha === '') return false;
        foreach (self::variantesSenha($senha) as $v) if (password_verify($v, (string)$u['senha'])) return true;
        return false;
    }

    /**
     * Exclui a conta; perfil, currículos, candidaturas, vagas, matches, assinaturas e pedidos de troca de senha
     * saem em cascata (FKs). Remove também os arquivos enviados (currículos, foto/logo e imagens de vagas enviadas)
     * e as tentativas de login guardadas pelo e-mail (LGPD: nada da pessoa fica para trás).
     */
    public function excluir(int $id): bool {
        try {
            $db = Database::getConexao();
            $email = (string)($this->buscarPorId($id)['email'] ?? '');
            $s = $db->prepare("SELECT cv.arquivo_pdf FROM curriculos cv JOIN perfis p ON p.id=cv.perfil_id WHERE p.usuario_id=?
                               UNION SELECT foto FROM perfis WHERE usuario_id=? AND foto IS NOT NULL
                               UNION SELECT v.imagem FROM vagas v JOIN perfis p ON p.id=v.perfil_empresa_id WHERE p.usuario_id=? AND v.imagem LIKE 'assets/uploads/%'");
            $s->execute([$id, $id, $id]);
            $arquivos = $s->fetchAll(PDO::FETCH_COLUMN);
            $d = $db->prepare("DELETE FROM usuarios WHERE id=?");
            $d->execute([$id]);
            if ($d->rowCount() < 1) return false;
            foreach ($arquivos as $a) apagar_upload_sem_uso((string)$a);
            if ($email !== '') $db->prepare("DELETE FROM tentativas_login WHERE email=?")->execute([$email]);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Painel do administrador: usuários por tipo — total, ativos e cadastrados nos últimos $dias dias.
     * @return array<string,array{total:int,ativos:int,novos:int}> um item por tipo (os sem usuário vêm zerados)
     */
    public function contarPorTipo(int $dias = 30): array {
        $out = array_fill_keys(self::TIPOS, ['total' => 0, 'ativos' => 0, 'novos' => 0]);
        $s = Database::getConexao()->prepare("SELECT tipo, COUNT(*) AS total, SUM(ativo=1) AS ativos, SUM(created_at >= CURDATE() - INTERVAL ? DAY) AS novos
                                              FROM usuarios GROUP BY tipo");
        $s->execute([max(1, $dias) - 1]);
        foreach ($s->fetchAll() as $r) $out[$r['tipo']] = ['total' => (int)$r['total'], 'ativos' => (int)$r['ativos'], 'novos' => (int)$r['novos']];
        return $out;
    }

    /** Os últimos usuários cadastrados (sem a senha). */
    public function listarRecentes(int $limite = 5): array {
        return Database::getConexao()->query("SELECT id, nome, email, tipo, ativo, created_at FROM usuarios ORDER BY created_at DESC, id DESC LIMIT ".max(1, $limite))->fetchAll();
    }
}
