<?php
declare(strict_types=1);

/*
 * Arquivos enviados pelos usuários e imagens do projeto.
 *
 * COMO OS UPLOADS SÃO GUARDADOS
 *  - O arquivo fica em storage/uploads/ (fora de public/, inacessível pelo navegador).
 *  - No banco é gravado o caminho "lógico" assets/uploads/<nome> — o mesmo endereço
 *    que o navegador usa. Quem entrega o arquivo é o sistema:
 *      imagens (fotos, logos, cartazes) → ArquivoController::imagem (público);
 *      PDFs da biblioteca (biblioteca_*) → ArquivoController::imagem (público: e-books da plataforma);
 *      currículos                       → download.php, com checagem de permissão.
 *  - caminho_upload() converte o caminho do banco no caminho real do disco.
 */

/**
 * Caminho real (no disco) de um arquivo enviado, a partir do valor gravado no banco
 * ("assets/uploads/foto_1_abc.png"). Retorna null se o valor não for um upload válido.
 */
function caminho_upload(string $rel): ?string {
    if (!str_starts_with($rel, 'assets/uploads/')) return null;
    $nome = substr($rel, strlen('assets/uploads/'));
    // Só nomes simples: nada de subpastas, "..", ou barras invertidas.
    if ($nome === '' || str_contains($nome, '/') || str_contains($nome, '\\') || str_contains($nome, '..')) return null;
    return UPLOAD_DIR.$nome;
}

/**
 * Salva uma imagem enviada (JPG/PNG/WEBP, até 3 MB por padrão) em storage/uploads.
 * O tipo é conferido pelo CONTEÚDO do arquivo (finfo + getimagesize), não pela extensão,
 * e o nome é aleatório (o nome original nunca é usado).
 * @return string|null|false caminho gravável no banco; null se nenhum arquivo foi enviado; false se inválido.
 */
function salvar_imagem_enviada(string $campo, string $prefixo, int $maxBytes = 3 * 1024 * 1024): string|null|false {
    $f = $_FILES[$campo] ?? null;
    if (!is_array($f) || is_array($f['error'] ?? null) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > $maxBytes || !is_uploaded_file($f['tmp_name'])) return false;
    $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
    if (!isset($tipos[$mime]) || @getimagesize($f['tmp_name']) === false) return false;
    $nome = preg_replace('/[^a-z0-9_]/i', '', $prefixo).'_'.date('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$tipos[$mime];
    return move_uploaded_file($f['tmp_name'], UPLOAD_DIR.$nome) ? 'assets/uploads/'.$nome : false;
}

/** Tamanho máximo de um PDF da biblioteca (e-book guardado na plataforma). */
const MAX_PDF_BIBLIOTECA = 25 * 1024 * 1024;
/** Tamanho máximo de um PDF baixado da web para a biblioteca (cartilhas ilustradas chegam a 100 MB — o eduCAPES tem uma de 108 MB). */
const MAX_PDF_REMOTO = 150 * 1024 * 1024;

/** O link é um PDF da nossa biblioteca ("assets/uploads/biblioteca_....pdf")? */
function eh_pdf_biblioteca(string $url): bool {
    return (bool)preg_match('#^assets/uploads/biblioteca_[A-Za-z0-9_]+\.pdf$#', $url);
}

/**
 * Salva o PDF de um e-book na biblioteca da plataforma (storage/uploads/biblioteca_*.pdf).
 * Tipo conferido pelo CONTEÚDO (application/pdf e assinatura %PDF-), nome aleatório.
 * @return string|null|false caminho gravável no banco; null se nada foi enviado; false se inválido.
 */
function salvar_pdf_biblioteca(string $campo): string|null|false {
    $f = $_FILES[$campo] ?? null;
    if (!is_array($f) || is_array($f['error'] ?? null) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > MAX_PDF_BIBLIOTECA || !is_uploaded_file($f['tmp_name'])) return false;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
    if ($mime !== 'application/pdf' || (string)file_get_contents($f['tmp_name'], false, null, 0, 5) !== '%PDF-') return false;
    $nome = 'biblioteca_'.date('YmdHis').'_'.bin2hex(random_bytes(5)).'.pdf';
    return move_uploaded_file($f['tmp_name'], UPLOAD_DIR.$nome) ? 'assets/uploads/'.$nome : false;
}

/**
 * Apaga um arquivo enviado se nenhum registro do banco ainda o usa
 * (vagas, cursos, fotos/logos de perfil, currículos). Ignora o que não for upload.
 */
function apagar_upload_sem_uso(string $rel): void {
    $path = caminho_upload($rel);
    if ($path === null) return;
    try {
        $s = Database::getConexao()->prepare("SELECT (SELECT COUNT(*) FROM vagas WHERE imagem=?) + (SELECT COUNT(*) FROM cursos WHERE imagem=? OR url=?)
                                                  + (SELECT COUNT(*) FROM perfis WHERE foto=?) + (SELECT COUNT(*) FROM curriculos WHERE arquivo_pdf=?)");
        $s->execute([$rel, $rel, $rel, $rel, $rel]);
        if ((int)$s->fetchColumn() > 0) return;
    } catch (Throwable) {
        return;
    }
    if (is_file($path)) @unlink($path);
}

/** Nome de arquivo seguro para o cabeçalho de download (só letras, números, ponto, _ e -). */
function safe_filename(string $name): string { return preg_replace('/[^A-Za-z0-9._-]/','_',basename($name)) ?: 'arquivo'; }

/** Imagens de uma pasta de public/ (para sugerir no cadastro de vagas/cursos e montar o carrossel). */
function imagens_da_pasta(string $pasta): array {
    $out = [];
    foreach (glob(PUBLIC_DIR.'/'.$pasta.'/*') ?: [] as $f) {
        if (preg_match('/\.(png|jpe?g|webp)$/i', $f)) $out[] = $pasta.'/'.basename($f);
    }
    return $out;
}

/**
 * Caminho de imagem digitado no painel: aceita só arquivos de imagem do próprio
 * projeto (assets/img ou assets/uploads), sem "..". Retorna '' se inválido.
 */
function caminho_imagem_valido(string $caminho): string {
    $caminho = str_replace('\\', '/', trim($caminho));
    if ($caminho === '' || str_contains($caminho, '..')) return '';
    return preg_match('#^assets/(img|uploads)/[A-Za-z0-9_\-/.]+\.(png|jpe?g|webp|gif|svg)$#i', $caminho) ? $caminho : '';
}

/**
 * Limpeza automática de storage/uploads: apaga os arquivos que nenhum registro usa (vagas, cursos, fotos e
 * logos de perfil, currículos) e que têm mais de $horas — sobra de conta excluída, extração abandonada ou teste.
 * Os recentes ficam (ex.: foto do currículo esperando o candidato escolher). Devolve quantos apagou.
 */
function limpar_uploads_orfaos(int $horas = 24): int {
    $db = Database::getConexao();
    $usados = [];
    foreach (['SELECT imagem FROM vagas', 'SELECT imagem FROM cursos', 'SELECT url FROM cursos', 'SELECT foto FROM perfis', 'SELECT arquivo_pdf FROM curriculos'] as $sql) {
        foreach ($db->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $v) if ($v) $usados[basename((string)$v)] = true;
    }
    $n = 0; $limite = time() - $horas * 3600;
    foreach (glob(UPLOAD_DIR.'*') ?: [] as $f) {
        $b = basename($f);
        if ($b[0] === '.' || !is_file($f) || isset($usados[$b]) || filemtime($f) > $limite) continue;
        if (@unlink($f)) $n++;
    }
    return $n;
}

/**
 * Tarefas automáticas do sistema, no máximo 1x por dia (disparadas ao abrir a visão geral do administrador):
 * limpeza de arquivos órfãos. Nunca interrompe a página.
 */
function manutencao_diaria(?int $usuarioId = null): void {
    $marca = LOG_DIR.'manutencao_diaria.txt';
    if (!(is_file($marca) && time() - (int)@file_get_contents($marca) < 86400)) {
        @file_put_contents($marca, (string)time(), LOCK_EX);
        try { limpar_uploads_orfaos(); } catch (Throwable $e) { error_log('[manutencao_diaria] '.$e->getMessage()); }
    }
}
