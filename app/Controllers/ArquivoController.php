<?php
declare(strict_types=1);

/**
 * Entrega dos arquivos enviados pelos usuários, que ficam em storage/uploads
 * (fora de public/, então o navegador nunca os acessa diretamente).
 *  - imagem():   fotos, logos e cartazes — públicos (aparecem nas vagas e no portfólio);
 *  - download(): currículos — só para quem tem permissão.
 */
final class ArquivoController extends Controller {
    /** Tipos de imagem que podem ser exibidos (SVG fica de fora: pode conter script). */
    private const IMAGENS = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * assets/uploads/<arquivo> — chamado direto por public/index.php, antes de abrir a sessão.
     * O tipo é conferido pelo conteúdo do arquivo: currículos (PDF/DOC) nunca saem por aqui.
     */
    public function imagem(string $caminho): void {
        $path = caminho_upload($caminho);
        $mime = $path !== null && is_file($path) ? ((new finfo(FILEINFO_MIME_TYPE))->file($path) ?: '') : '';
        // PDF só da BIBLIOTECA (e-books da plataforma, nome biblioteca_*): currículo nunca sai por aqui.
        $pdfBiblioteca = $mime === 'application/pdf' && eh_pdf_biblioteca($caminho);
        if (!in_array($mime, self::IMAGENS, true) && !$pdfBiblioteca) {
            pagina_erro(404, 'Arquivo não encontrado', '<p>O arquivo solicitado não existe ou foi removido.</p>');
        }
        header('Content-Type: '.$mime);
        if ($pdfBiblioteca) header('Content-Disposition: inline; filename="'.safe_filename(basename($path)).'"');
        header('X-Content-Type-Options: nosniff');
        // O nome do arquivo muda a cada envio, então o navegador pode guardar a imagem em cache.
        header('Cache-Control: public, max-age=604800');
        header('Content-Length: '.filesize($path));
        // E-book grande em internet lenta: a entrega não é cortada pelo limite de tempo do PHP (readfile não usa memória).
        if ($pdfBiblioteca) set_time_limit(0);
        readfile($path);
    }

    /**
     * download.php?id= — abre o currículo. Quem pode:
     *  - o próprio candidato;
     *  - o administrador;
     *  - a empresa que recebeu uma candidatura com esse currículo;
     *  - empresa Premium (Banco de Talentos), se o perfil do candidato for público e o currículo não foi cancelado.
     */
    public function download(): void {
        exigirLogin();
        $dao = new CurriculoDAO();
        $id = (int)get_str('id');
        $cv = $id > 0 ? $dao->buscar($id) : null;
        if (!$cv) pagina_erro(404, 'Currículo não encontrado', '<p>O arquivo solicitado não existe ou foi removido pelo candidato.</p>');

        $usuarioId = (int)$_SESSION['usuario_id'];
        $perfilDao = new PerfilDAO();
        $meu = $perfilDao->buscarPorUsuarioId($usuarioId);
        $dono = $perfilDao->buscarPorId((int)$cv['perfil_id']);
        $ehDono = $meu && (int)$meu['id'] === (int)$cv['perfil_id'];
        $permitido = isAdmin() || $ehDono;
        if (!$permitido && isEmpresa() && $meu) {
            $permitido = $dao->enviadoParaEmpresa((int)$cv['id'], (int)$meu['id'])
                || ((new AssinaturaDAO())->isEmpresaPremium($usuarioId) && (int)$cv['ativo'] === 1 && $dono && (int)$dono['publico'] === 1 && (int)$dono['ativo'] === 1);
        }
        if (!$permitido) pagina_erro(403, 'Acesso restrito', '<p>O currículo só pode ser aberto pelo próprio candidato, pelas empresas que receberam a candidatura ou por empresas com plano Premium (perfis públicos).</p>');

        // O caminho vem do banco, mas ainda assim só servimos arquivos que estejam DENTRO de storage/uploads.
        $path = caminho_upload((string)$cv['arquivo_pdf']);
        $real = $path !== null ? realpath($path) : false;
        $pasta = realpath(UPLOAD_DIR);
        if ($real === false || $pasta === false || !str_starts_with($real, $pasta.DIRECTORY_SEPARATOR) || !is_file($real)) {
            pagina_erro(404, 'Arquivo indisponível', '<p>O arquivo deste currículo não está mais disponível no servidor.</p>');
        }

        if (!$ehDono) $dao->registrarDownload((int)$cv['id']);
        $tipos = ['pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'doc' => 'application/msword'];
        $ext = strtolower((string)$cv['arquivo_tipo']);
        $mime = $tipos[$ext] ?? 'application/octet-stream';
        header('Content-Type: '.$mime);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        // PDF abre no navegador; Word e desconhecidos sempre baixam (nunca são interpretados como página).
        header('Content-Disposition: '.($ext === 'pdf' ? 'inline' : 'attachment').'; filename="'.safe_filename(($cv['titulo'] ?: 'curriculo').'.'.(isset($tipos[$ext]) ? $ext : 'bin')).'"');
        header('Content-Length: '.filesize($real));
        readfile($real);
        exit;
    }
}
