<?php
declare(strict_types=1);

/*
 * ============================================================
 * FRONT CONTROLLER — porta de entrada de TODAS as páginas
 * ============================================================
 * O .htaccess da raiz desvia cada requisição para cá (ex.: /vagas.php → public/index.php)
 * sem mudar o endereço no navegador. Arquivos que existem em public/ (CSS, JS, imagens)
 * são entregues direto pelo Apache e nem passam por aqui.
 *
 * Passo a passo:
 *  1. prepara a aplicação (configuração, núcleo, carregamento automático das classes);
 *  2. entrega as imagens enviadas pelos usuários (sem abrir sessão);
 *  3. abre a sessão, confere a conta logada e envia cabeçalhos de segurança;
 *  4. procura o endereço na tabela de rotas e executa a ação do controller.
 */

// 1. Aplicação
require dirname(__DIR__).'/app/Core/bootstrap.php';

$caminho = Router::caminhoPedido();

// 2. Fotos, logos e cartazes enviados ficam em storage/uploads (fora de public/).
if (stripos($caminho, 'assets/uploads/') === 0) {
    (new ArquivoController())->imagem($caminho);
    exit;
}

// 3. Sessão e segurança
iniciar_sessao();
revalidar_sessao();
header('X-Content-Type-Options: nosniff');          // o navegador não "adivinha" o tipo do arquivo
header('X-Frame-Options: SAMEORIGIN');              // o site não pode ser embutido em outro (clickjacking)
header('Referrer-Policy: strict-origin-when-cross-origin');
header_remove('X-Powered-By');                      // não anuncia a versão do PHP
// Política de conteúdo mínima (não bloqueia imagens nem scripts do próprio site): formulários só enviam
// para o próprio site, nada de <base> injetado, plugins nem embutir o site em outro.
header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
if (HTTPS_ATIVO) header('Strict-Transport-Security: max-age=31536000');

// 4. Rotas: endereço → [Controller, ação]. Os endereços são os mesmos da versão anterior.
$rotas = new Router();

// Área pública
$rotas->rota('index.php',       [HomeController::class, 'index'], '');    // '' = raiz do site
$rotas->rota('contrato.php',    [HomeController::class, 'contrato']);
$rotas->rota('vagas.php',       [VagaController::class, 'lista']);
$rotas->rota('vaga.php',        [VagaController::class, 'detalhe']);
$rotas->rota('cursos.php',      [CursoController::class, 'lista']);
$rotas->rota('curso.php',       [CursoController::class, 'detalhe']);
$rotas->rota('planos.php',      [PlanosController::class, 'index']);

// Conta
$rotas->rota('login.php',               [AuthController::class, 'login']);
$rotas->rota('cadastro.php',            [AuthController::class, 'cadastro']);
$rotas->rota('view/usuario/logout.php', [AuthController::class, 'logout']);
$rotas->rota('esqueci_senha.php',       [PasswordController::class, 'esqueci']);
$rotas->rota('redefinir_senha.php',     [PasswordController::class, 'redefinir']);

// Candidato
$rotas->rota('view/perfil/index.php',                [PerfilController::class, 'index'], 'view/perfil');
$rotas->rota('view/perfil/salvar.php',               [PerfilController::class, 'salvar']);
$rotas->rota('view/perfil/portfolio.php',            [PerfilController::class, 'portfolio']);
$rotas->rota('view/perfil/recalcular_match.php',     [PerfilController::class, 'recalcularMatch']);
$rotas->rota('view/perfil/conta_excluir.php',        [PerfilController::class, 'excluirConta']);
$rotas->rota('view/perfil/curriculo_upload.php',     [CurriculoController::class, 'upload']);
$rotas->rota('view/perfil/aplicar_extracao.php',     [CurriculoController::class, 'aplicarExtracao']);
$rotas->rota('view/perfil/curriculo_excluir.php',    [CurriculoController::class, 'excluir']);
$rotas->rota('candidatar.php',                       [CandidaturaController::class, 'candidatar']);
$rotas->rota('view/perfil/candidatura_cancelar.php', [CandidaturaController::class, 'cancelar']);
$rotas->rota('download.php',                         [ArquivoController::class, 'download']);

// Painel (administrador e empresa)
$rotas->rota('admin/index.php',               [AdminController::class, 'painel'], 'admin');
$rotas->rota('admin/pages/usuarios.php',      [AdminController::class, 'usuarios']);
$rotas->rota('admin/pages/categorias.php',    [AdminController::class, 'categorias']);
$rotas->rota('admin/pages/cursos.php',        [AdminController::class, 'cursos']);
$rotas->rota('admin/pages/assinaturas.php',   [AdminController::class, 'assinaturas']);
$rotas->rota('admin/pages/calibrador.php',    [CalibradorController::class, 'painel']);
$rotas->rota('admin/pages/vagas.php',         [EmpresaController::class, 'vagas']);
$rotas->rota('admin/pages/candidaturas.php',  [EmpresaController::class, 'candidaturas']);
$rotas->rota('admin/pages/talentos.php',      [EmpresaController::class, 'talentos']);
$rotas->rota('admin/pages/empresa_perfil.php', [EmpresaController::class, 'perfil']);

$rotas->despachar($caminho);
