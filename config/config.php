<?php
declare(strict_types=1);

/*
 * ============================================================
 * CONFIGURAÇÃO — Conecta Vagas DF
 * ============================================================
 * Aqui ficam só VALORES que mudam de um computador para outro
 * (banco, modo de depuração, limites). O código que usa esses
 * valores fica em app/Core/.
 *
 * Qualquer item do banco e o DEBUG também podem vir de variáveis
 * de ambiente: APP_DEBUG, DB_HOST, DB_PORT, DB_NAME, DB_USER,
 * DB_PASS e DB_SOCKET.
 */

// ------------------------------------------------------------
// Modo de depuração
// ------------------------------------------------------------
// true  = mostra os detalhes técnicos dos erros (ambiente local do TCC);
// false = mostra só mensagens amigáveis (use em produção/apresentação pública).
// Sem APP_DEBUG definida: true só no terminal (CLI) ou com acesso pelo próprio
// computador (127.0.0.1 / ::1); quem acessa de outra máquina nunca vê o detalhe.
if (!defined('DEBUG')) {
    define('DEBUG', getenv('APP_DEBUG') !== false
        ? filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN)
        : (PHP_SAPI === 'cli' || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)));
}

define('SITE_NAME', 'Conecta Vagas DF');
define('FUSO_HORARIO', 'America/Sao_Paulo');

// ------------------------------------------------------------
// Banco de dados (MySQL/MariaDB do XAMPP)
// ------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'tcc_final');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '');
// Socket só existe no Linux (XAMPP/LAMPP). No Windows a conexão é via TCP (127.0.0.1:3306).
define('DB_SOCKET', getenv('DB_SOCKET') ?: (PHP_OS_FAMILY === 'Windows' ? '' : '/opt/lampp/var/mysql/mysql.sock'));

// ------------------------------------------------------------
// Pastas do projeto
// ------------------------------------------------------------
define('ROOT_DIR', dirname(__DIR__));
define('APP_DIR', ROOT_DIR.DIRECTORY_SEPARATOR.'app');
define('PUBLIC_DIR', ROOT_DIR.DIRECTORY_SEPARATOR.'public');
// Arquivos enviados pelos usuários (currículos, fotos, logos, cartazes).
// Ficam FORA de public/: o navegador só chega a eles pelo sistema
// (fotos via ArquivoController::imagem, currículos via download.php com permissão).
define('UPLOAD_DIR', ROOT_DIR.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR);
// Registros internos (ex.: links de redefinição de senha no modo demonstrativo).
define('LOG_DIR', ROOT_DIR.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR);

// ------------------------------------------------------------
// Doação (QR Code Pix do rodapé)
// ------------------------------------------------------------
// Imagem do QR Code de doação (rodapé e painel relâmpago). Para usar o seu QR real, gerado no app do
// banco, substitua o arquivo public/assets/img/doacao/qrcode-pix.svg (ou aponte aqui para um .png/.jpg).
// O arquivo que vem com o projeto é um QR FICTÍCIO, só para marcar o lugar.
define('DOACAO_QR_IMAGEM', 'assets/img/doacao/qrcode-pix.svg');
// Opcional: com a chave Pix aqui (CPF, CNPJ, e-mail, celular +5561999999999 ou chave aleatória), o site
// gera o QR sozinho no lugar da imagem acima. Nome até 25 e cidade até 15 letras, sem acento.
define('DOACAO_PIX_CHAVE', getenv('DOACAO_PIX_CHAVE') ?: '');
define('DOACAO_NOME', getenv('DOACAO_NOME') ?: 'Conecta Vagas DF');
define('DOACAO_CIDADE', getenv('DOACAO_CIDADE') ?: 'Brasilia');

// ------------------------------------------------------------
// Limites
// ------------------------------------------------------------
define('MAX_FILE_SIZE', 10 * 1024 * 1024);  // currículo: até 10 MB

// Proteção do login contra tentativas repetidas (por IP + e-mail, por e-mail e por IP).
// Ajustado para não atrapalhar quem erra a senha algumas vezes, mas ainda barrar força-bruta.
define('LOGIN_MAX_TENTATIVAS', 8);       // erros seguidos para o mesmo e-mail, a partir do mesmo IP
define('LOGIN_MAX_TENTATIVAS_CONTA', 20); // erros para o mesmo e-mail, somando todos os IPs (ataque distribuído)
define('LOGIN_MAX_TENTATIVAS_IP', 60);   // erros de um mesmo IP, somando todos os e-mails
define('LOGIN_JANELA_MINUTOS', 5);       // janela de contagem e tempo da pausa
