<?php
declare(strict_types=1);

/*
 * ============================================================
 * JORNADAS — o sistema usado como uma pessoa usa (pelo navegador)
 * ============================================================
 * Uso (na pasta do projeto, com Apache e MySQL ligados no XAMPP):
 *   C:\xampp\php\php.exe tests\jornadas.php
 *   C:\xampp\php\php.exe tests\jornadas.php http://localhost/outra%20pasta/   (endereço diferente)
 *
 * Cria uma conta de candidato TEMPORÁRIA e percorre, por HTTP (cookies e token CSRF de verdade):
 *  1. cadastro, saída e login (senha errada recusada, com a tolerância a Caps Lock do login);
 *  2. currículo DOCX enviado → perfil preenchido pela máquina de extração;
 *  3. candidatura a uma vaga da empresa de teste → a empresa vê a candidatura;
 *  4. empresa: extração pelo texto do anúncio, pelo cartaz (leituras reais do navegador) e "Extrair" vazio barrado;
 *  5. administrador: ficha de curso extraída para o formulário;
 *  6. "Esqueci minha senha": o log não guarda o e-mail inteiro;
 *  7. LGPD: o candidato exclui a própria conta (senha errada recusada; depois a conta, os arquivos e as
 *     tentativas de login somem e o login deixa de funcionar).
 * Nada é salvo além disso: a conta temporária é apagada no fim (mesmo se algum passo falhar), o cartaz lido é
 * descartado e a linha que o teste escreve no log de senhas é retirada. Termina com código 1 se algo falhar.
 */

require __DIR__.'/../app/Core/bootstrap.php';

$falhas = 0;
function confere(string $descricao, bool $ok, string $detalhe = ''): void {
    global $falhas;
    if (!$ok) $falhas++;
    echo ($ok ? '  OK    ' : '  FALHA ').$descricao.($ok || $detalhe === '' ? '' : " → $detalhe").PHP_EOL;
}

/** Um "navegador": guarda os cookies da sessão, segue os redirecionamentos e pega o token CSRF da página. */
final class Navegador {
    private CurlHandle $ch;
    public string $html = '';
    public int $status = 0;

    public function __construct(private string $base) {
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [CURLOPT_COOKIEFILE => '', CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 90, CURLOPT_USERAGENT => 'Conecta Vagas - teste de jornadas']);
    }

    public function abrir(string $caminho): string {
        curl_setopt_array($this->ch, [CURLOPT_URL => $this->base.$caminho, CURLOPT_HTTPGET => true]);
        return $this->executar();
    }

    /** Abre a página do formulário (para pegar o token) e envia os campos. Arquivos: CURLFile. */
    public function enviar(string $caminho, array $campos, ?string $paginaDoFormulario = null): string {
        $this->abrir($paginaDoFormulario ?? $caminho);
        $campos['csrf'] = preg_match('/name="csrf" value="([^"]+)"/', $this->html, $m) ? $m[1] : '';
        curl_setopt_array($this->ch, [CURLOPT_URL => $this->base.$caminho, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $campos]);
        return $this->executar();
    }

    /** Mensagem da caixa de aviso (flash) da página atual. */
    public function aviso(): string {
        return preg_match('/<div class="alert [a-z]+"[^>]*data-flash>(.*?)<\/div>/s', $this->html, $m) ? html_entity_decode(strip_tags($m[1]), ENT_QUOTES) : '';
    }

    /** Valor de um campo do formulário da página atual. */
    public function campo(string $nome): string {
        return preg_match('/name="'.preg_quote($nome, '/').'"[^>]*value="([^"]*)"/', $this->html, $m) ? html_entity_decode($m[1], ENT_QUOTES) : '';
    }

    private function executar(): string {
        $this->html = (string)curl_exec($this->ch);
        $this->status = (int)curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE);
        return $this->html;
    }
}

/** Currículo DOCX mínimo (o mesmo formato do Word), montado na hora. */
function docx_de_teste(string $caminho, array $linhas): void {
    $paragrafos = implode('', array_map(fn($l) => '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($l, ENT_XML1).'</w:t></w:r></w:p>', $linhas));
    $z = new ZipArchive();
    $z->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $z->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
        .$paragrafos.'</w:body></w:document>');
    $z->close();
}

// ------------------------------------------------------------
$base = $argv[1] ?? null;
if ($base === null) {
    // Padrão: a pasta do projeto dentro do htdocs (o mesmo cálculo do tests/smoke.php).
    $rel = preg_split('#[\\\\/]htdocs[\\\\/]#i', ROOT_DIR)[1] ?? '';
    $base = 'http://localhost/'.implode('/', array_map('rawurlencode', preg_split('#[\\\\/]#', $rel) ?: [])).'/';
}
$base = rtrim($base, '/').'/';
echo PHP_EOL."Jornadas em $base".PHP_EOL;
if (!function_exists('curl_init') || !class_exists('ZipArchive')) { confere('extensões curl e zip do PHP ligadas', false); exit(1); }
if ((new Navegador($base))->abrir('') === '') { confere('Apache respondendo', false, 'ligue o Apache e o MySQL no XAMPP'); exit(1); }

$dao = new UsuarioDAO();
$email = 'jornada.teste.'.date('YmdHis').'.'.bin2hex(random_bytes(2)).'@exemplo.com';
$senha = 'Jornada@2026';
$nome = 'Maria Jornada Teste';
$tmp = [];
$logSenhas = LOG_DIR.'redefinicoes_senha.log';
$tamanhoLog = is_file($logSenhas) ? (int)filesize($logSenhas) : 0;

try {
    // --------------------------------------------------------
    echo PHP_EOL.'1. Cadastro, saída e login'.PHP_EOL;
    $cand = new Navegador($base);
    $cand->enviar('cadastro.php', ['tipo' => 'candidato', 'nome' => $nome, 'email' => $email, 'senha' => $senha, 'telefone' => '', 'aceite_lgpd' => '1']);
    $u = $dao->buscarPorEmail($email);
    confere('cadastro cria a conta e já entra no perfil', $u !== null && str_contains($cand->html, 'Máquina de extração do currículo'), 'status '.$cand->status.' '.$cand->aviso());
    $cand->enviar('view/usuario/logout.php', [], 'view/perfil/index.php');
    confere('sair encerra a sessão', !str_contains($cand->abrir('view/perfil/index.php'), 'Máquina de extração do currículo'));
    $cand->enviar('login.php', ['email' => $email, 'senha' => 'SenhaErrada@1']);
    confere('login com senha errada é recusado', !str_contains($cand->abrir('view/perfil/index.php'), 'Máquina de extração do currículo'));
    $cand->enviar('login.php', ['email' => $email, 'senha' => mb_strtolower($senha)]);   // 1ª letra trocada (celular)
    confere('login certo entra (tolera a 1ª letra trocada)', str_contains($cand->abrir('view/perfil/index.php'), 'Máquina de extração do currículo'));

    // --------------------------------------------------------
    echo PHP_EOL.'2. Currículo → perfil preenchido'.PHP_EOL;
    $docx = $tmp[] = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cvdf_jornada_'.bin2hex(random_bytes(4)).'.docx';
    docx_de_teste($docx, [$nome, $email.' | (61) 99999-0000', 'Brasília - DF', 'RESUMO', 'Atendente com experiência em vendas e atendimento ao cliente.',
        'EXPERIÊNCIA', 'Atendente — Loja Exemplo (2021 - 2023)', 'FORMAÇÃO', 'Ensino Médio Completo', 'HABILIDADES', 'Excel, Atendimento ao cliente, Vendas']);
    $cand->enviar('view/perfil/curriculo_upload.php', ['titulo' => 'Currículo da jornada', 'curriculo' => new CURLFile($docx, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'curriculo.docx')], 'view/perfil/index.php');
    $perfil = (new PerfilDAO())->buscarPorUsuarioId((int)$u['id']);
    $cvs = $perfil ? (new CurriculoDAO())->listarPorPerfil((int)$perfil['id']) : [];
    $cvArquivo = $cvs ? caminho_upload((string)$cvs[0]['arquivo_pdf']) : null;
    confere('currículo DOCX salvo (arquivo no servidor)', $cvs && $cvArquivo !== null && is_file($cvArquivo), $cand->aviso());
    confere('a extração preencheu o perfil (habilidades/competências)', $perfil !== null && stripos((string)$perfil['habilidades'].(string)$perfil['competencias'], 'excel') !== false,
        json_encode(['habilidades' => $perfil['habilidades'] ?? null, 'competencias' => $perfil['competencias'] ?? null], JSON_UNESCAPED_UNICODE));

    // --------------------------------------------------------
    echo PHP_EOL.'3. Candidatura'.PHP_EOL;
    $vaga = Database::getConexao()->query("SELECT v.id FROM vagas v JOIN perfis p ON p.id=v.perfil_empresa_id JOIN usuarios u ON u.id=p.usuario_id
        WHERE u.email='empresa@conectavagas.com' AND v.status='ativa' AND (v.data_expiracao IS NULL OR v.data_expiracao>=CURDATE()) ORDER BY v.id LIMIT 1")->fetchColumn();
    $emp = new Navegador($base);
    $emp->enviar('login.php', ['email' => 'empresa@conectavagas.com', 'senha' => 'Empresa@123']);
    $empresaEntrou = str_contains($emp->abrir('admin/pages/vagas.php'), 'Máquina de extração');
    confere('conta de teste da empresa entra', $empresaEntrou, 'rode C:\xampp\php\php.exe database\resetar_senhas.php');
    if ($vaga && $cvs) {
        $cand->enviar('candidatar.php', ['vaga_id' => (string)$vaga, 'curriculo_id' => (string)$cvs[0]['id'], 'carta_apresentacao' => 'Teste automático.'], 'candidatar.php?vaga_id='.$vaga);
        confere('candidatura enviada', (new CandidaturaDAO())->buscarDoCandidato((int)$perfil['id'], (int)$vaga) !== null, $cand->aviso());
        confere('a empresa vê a candidatura na lista dela', $empresaEntrou && str_contains($emp->abrir('admin/pages/candidaturas.php'), $nome));
    } else {
        confere('há vaga aberta da empresa de teste e currículo para candidatar', false, 'importe database/seed.sql');
    }

    // --------------------------------------------------------
    echo PHP_EOL.'4. Empresa: extração de vagas'.PHP_EOL;
    $emp->enviar('admin/pages/vagas.php', ['acao' => 'extrair', 'id' => '0', 'texto_anuncio' => "VAGA: Vendedor Interno - Taguatinga\nSalário: R$ 3.000 a R$ 5.500 + comissões\nBenefícios: VT + VR"]);
    confere('texto do anúncio → formulário preenchido (nada salvo)', $emp->campo('titulo') === 'Vendedor Interno' && str_contains($emp->html, 'Relatório da extração da vaga'), $emp->campo('titulo'));
    $emp->enviar('admin/pages/vagas.php', ['acao' => 'extrair', 'id' => '0', 'texto_anuncio' => '   ']);
    confere('"Extrair" com a caixa vazia é barrado (sem relatório em branco)', !str_contains($emp->html, 'Relatório da extração da vaga') && str_contains($emp->aviso(), 'Cole o texto'), $emp->aviso());
    $png = $tmp[] = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cvdf_jornada_'.bin2hex(random_bytes(4)).'.png';
    $img = imagecreatetruecolor(400, 500); imagefill($img, 0, 0, imagecolorallocate($img, 18, 87, 201)); imagepng($img, $png); imagedestroy($img);
    $leituras = json_decode((string)file_get_contents(__DIR__.'/amostras/ocr_navegador_smile.json'), true);
    $campos = ['acao' => 'ler_cartaz', 'id' => '0', 'cartaz' => new CURLFile($png, 'image/png', 'cartaz.png')];
    foreach ($leituras as $i => $tsv) $campos["ocr_tsv[$i]"] = $tsv;
    $emp->enviar('admin/pages/vagas.php', $campos);
    confere('cartaz com as leituras do navegador → "Estágio Social Media" da Smile & Face', $emp->campo('titulo') === 'Estágio Social Media' && $emp->campo('anunciante') === 'Smile & Face',
        $emp->campo('titulo').' / '.$emp->campo('anunciante'));
    $cartaz = $emp->campo('imagem_atual');
    if ($cartaz !== '') apagar_upload_sem_uso($cartaz);   // cartaz lido e não salvo: sai do servidor

    // --------------------------------------------------------
    echo PHP_EOL.'5. Administrador: ficha de curso'.PHP_EOL;
    $adm = new Navegador($base);
    $adm->enviar('login.php', ['email' => 'admin@conectavagas.com', 'senha' => 'Admin@123']);
    $adm->enviar('admin/pages/cursos.php', ['acao' => 'extrair', 'id' => '0',
        'texto_anuncio' => "Título: Curso da Jornada de Teste\nTipo: Curso\nInstituição: Escola de Teste\nModalidade: EAD\nGratuito: Sim\nLink: https://exemplo.invalid/curso\nImagem: Não encontrada"]);
    confere('ficha colada → formulário preenchido (nada salvo)', $adm->campo('titulo') === 'Curso da Jornada de Teste', $adm->campo('titulo') ?: 'status '.$adm->status);

    // --------------------------------------------------------
    echo PHP_EOL.'6. Esqueci minha senha'.PHP_EOL;
    (new Navegador($base))->enviar('esqueci_senha.php', ['email' => $email]);
    clearstatcache();
    $novo = is_file($logSenhas) ? (string)file_get_contents($logSenhas, false, null, $tamanhoLog) : '';
    // Acesso pelo próprio computador sem APP_DEBUG = modo de demonstração: o link vai para o log, com o e-mail mascarado.
    // Com APP_DEBUG=0 (como em produção), nada pode ir para o log.
    $producao = getenv('APP_DEBUG') !== false && !filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
    confere($producao ? 'produção: o link não vai para o log' : 'demonstração: o link vai para o log com o e-mail mascarado (LGPD)',
        $producao ? $novo === '' : str_contains($novo, mascarar_email($email)) && str_contains($novo, 'redefinir_senha.php?token=') && !str_contains($novo, $email),
        trim($novo) ?: '(nada gravado)');

    // --------------------------------------------------------
    echo PHP_EOL.'7. LGPD: o candidato exclui a própria conta'.PHP_EOL;
    $cand->enviar('view/perfil/conta_excluir.php', ['senha' => 'SenhaErrada@1', 'confirmo' => '1'], 'view/perfil/index.php');
    confere('senha errada: a conta NÃO é excluída', $dao->buscarPorEmail($email) !== null && str_contains($cand->aviso(), 'Senha incorreta'), $cand->aviso());
    $cand->enviar('view/perfil/conta_excluir.php', ['senha' => $senha], 'view/perfil/index.php');
    confere('sem marcar a confirmação: a conta NÃO é excluída', $dao->buscarPorEmail($email) !== null, $cand->aviso());
    $cand->enviar('view/perfil/conta_excluir.php', ['senha' => $senha, 'confirmo' => '1'], 'view/perfil/index.php');
    $tentativas = Database::getConexao()->prepare('SELECT COUNT(*) FROM tentativas_login WHERE email=?');
    $tentativas->execute([$email]);
    confere('conta excluída: some do banco, com o arquivo do currículo e as tentativas de login', $dao->buscarPorEmail($email) === null
        && ($cvArquivo === null || !is_file($cvArquivo)) && (int)$tentativas->fetchColumn() === 0 && str_contains($cand->aviso(), 'excluídos'), $cand->aviso());
    $cand->enviar('login.php', ['email' => $email, 'senha' => $senha]);
    confere('depois de excluída, a conta não entra mais', !str_contains($cand->abrir('view/perfil/index.php'), 'Máquina de extração do currículo'));
} catch (Throwable $e) {
    confere('jornadas sem erro inesperado', false, $e->getMessage().' em '.basename($e->getFile()).':'.$e->getLine());
} finally {
    // Limpeza: nada do teste fica para trás, mesmo se algum passo falhou no meio.
    $sobrou = $dao->buscarPorEmail($email);
    if ($sobrou) { $dao->excluir((int)$sobrou['id']); }
    // A tentativa de entrar com a conta já excluída (último passo) também fica registrada: sai junto.
    Database::getConexao()->prepare('DELETE FROM tentativas_login WHERE email=?')->execute([$email]);
    foreach ($tmp as $f) if (is_file($f)) @unlink($f);
    if (is_file($logSenhas) && (int)filesize($logSenhas) > $tamanhoLog && ($h = fopen($logSenhas, 'r+'))) { ftruncate($h, $tamanhoLog); fclose($h); }
}

echo PHP_EOL.($falhas ? "RESULTADO: {$falhas} falha(s)." : 'RESULTADO: tudo certo.').PHP_EOL;
exit($falhas ? 1 : 0);
