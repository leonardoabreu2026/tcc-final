<?php
declare(strict_types=1);

/*
 * Carregamento automático das classes.
 *
 * Quando o código usa uma classe pela primeira vez (ex.: new VagaDAO()), o PHP chama
 * esta função, que procura o arquivo "VagaDAO.php" nas pastas abaixo e o inclui.
 * Por isso os arquivos não precisam de require_once uns dos outros.
 *
 * Regra: o nome do arquivo é igual ao nome da classe.
 */
spl_autoload_register(function (string $classe): void {
    static $pastas = ['Controllers', 'Models', 'DTO', 'Services', 'Services/Extracao', 'Core'];
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $classe)) return;
    foreach ($pastas as $pasta) {
        $arquivo = APP_DIR.'/'.$pasta.'/'.$classe.'.php';
        if (is_file($arquivo)) {
            require $arquivo;
            return;
        }
    }
});
