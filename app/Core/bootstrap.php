<?php
declare(strict_types=1);

/*
 * Inicialização da aplicação — usada por public/index.php e por tests/smoke.php.
 *
 *  1. carrega as configurações (config/config.php);
 *  2. ajusta o PHP (exibição de erros e fuso horário);
 *  3. carrega o núcleo (funções de apoio) e registra o carregamento automático das classes;
 *  4. descobre o endereço base do site (BASE_URL e SITE_URL, usados por url()).
 *
 * Sessão, rotas e cabeçalhos HTTP ficam em public/index.php (só existem na web).
 */

// 1. Configurações
require_once dirname(__DIR__, 2).'/config/config.php';

// 2. PHP
error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('display_startup_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set(FUSO_HORARIO);

// 3. Núcleo: arquivos de funções usados em todo o sistema...
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/Session.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/Csrf.php';
require_once __DIR__.'/Upload.php';
require_once __DIR__.'/View.php';
require_once __DIR__.'/Database.php';          // também define DatabaseException
// ...funções de apresentação que controllers e telas usam (ícones, cartões, datas)...
require_once APP_DIR.'/Views/partials/icones.php';
require_once APP_DIR.'/Views/partials/componentes.php';
// ...e as classes (Controllers, Models, DTO, Services, Router) são carregadas sob demanda.
require_once __DIR__.'/Autoloader.php';

if (PHP_SAPI !== 'cli') set_exception_handler('tratar_excecao');
if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);

// 4. Endereço base do site.
// Calculado a partir da pasta do projeto dentro do DocumentRoot: funciona em qualquer
// pasta do htdocs. No Windows/XAMPP o caminho pode vir com letra de unidade em caixa
// diferente (C: x c:) e com espaços no nome da pasta, por isso a comparação ignora
// caixa e cada segmento é codificado para uso em URL.
(function (): void {
    $docRoot = rtrim(str_replace('\\', '/', (string)(realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ($_SERVER['DOCUMENT_ROOT'] ?? ''))), '/');
    $dirRoot = rtrim(str_replace('\\', '/', (string)(realpath(ROOT_DIR) ?: ROOT_DIR)), '/');
    if ($docRoot !== '' && stripos($dirRoot, $docRoot) === 0) {
        $basePath = substr($dirRoot, strlen($docRoot));
    } else {
        // Fallback (ex.: Alias do Apache): usa o caminho do script atual.
        // Remove do SCRIPT_NAME a parte do caminho que fica dentro do projeto.
        $scriptFile = str_replace('\\', '/', (string)(realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: ''));
        $relScript = substr($scriptFile, strlen($dirRoot));
        $scriptName = rawurldecode((string)($_SERVER['SCRIPT_NAME'] ?? '/'));
        $basePath = $relScript !== '' && str_ends_with(strtolower($scriptName), strtolower($relScript))
            ? substr($scriptName, 0, -strlen($relScript))
            : dirname($scriptName);
    }
    $segmentos = array_filter(explode('/', $basePath), 'strlen');
    define('BASE_URL', rtrim('/'.implode('/', array_map('rawurlencode', $segmentos)), '/').'/');

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443;
    define('HTTPS_ATIVO', $https);
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // HTTP_HOST vem do cliente: aceita só caracteres válidos de host[:porta].
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$|^\[[0-9A-Fa-f:.]+\](:\d{1,5})?$/', $host)) $host = 'localhost';
    define('SITE_URL', ($https ? 'https://' : 'http://').$host.BASE_URL);
})();
