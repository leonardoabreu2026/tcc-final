<?php
declare(strict_types=1);

/**
 * Roteador: decide qual controller atende cada endereço.
 *
 * Todas as requisições chegam em public/index.php (o "front controller"); o .htaccess
 * da raiz faz esse desvio sem mudar o endereço que aparece no navegador.
 * O roteador pega o caminho pedido (ex.: "admin/pages/vagas.php"), procura na tabela
 * de rotas e chama o método do controller correspondente.
 *
 * Os caminhos das rotas são endereços simples e estáveis (vagas.php, view/perfil/index.php...):
 * favoritos, links compartilhados e os links de redefinição de senha continuam funcionando.
 */
final class Router {
    /** @var array<string,array{0:class-string,1:string}> caminho => [Controller, método] */
    private array $rotas = [];
    /** @var array<string,string> caminho alternativo => caminho da rota */
    private array $apelidos = [];
    /** Rota em execução (usada pelo menu para marcar o item ativo). */
    private static string $atual = '';

    /**
     * Registra uma rota. Aceita GET e POST: a própria ação trata os dois
     * (mostra o formulário no GET e processa o envio no POST).
     * @param string[] $apelidos outros caminhos que levam à mesma rota (ex.: pasta sem "index.php")
     */
    public function rota(string $caminho, array $acao, string ...$apelidos): void {
        $this->rotas[strtolower($caminho)] = $acao;
        foreach ($apelidos as $a) $this->apelidos[strtolower($a)] = strtolower($caminho);
    }

    /** Executa a ação da rota pedida; caminho desconhecido mostra a página 404. */
    public function despachar(string $caminho): void {
        $chave = strtolower($caminho);
        $chave = $this->apelidos[$chave] ?? $chave;
        if (!isset($this->rotas[$chave])) {
            pagina_erro(404, 'Página não encontrada', '<p>O endereço acessado não existe ou foi removido.</p>');
        }
        self::$atual = $chave;
        [$classe, $metodo] = $this->rotas[$chave];
        (new $classe())->$metodo();
    }

    /** Caminho da rota em execução, ex.: "vagas.php" ou "view/perfil/portfolio.php". */
    public static function atual(): string { return self::$atual; }

    /**
     * Caminho pedido pelo navegador, relativo à pasta do projeto e sem a query string.
     * Ex.: /tcc-final/vaga.php?id=3 → "vaga.php".
     */
    public static function caminhoPedido(): string {
        $uri = rawurldecode((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/'));
        $base = rawurldecode(BASE_URL);
        // No Windows o Apache aceita a pasta com maiúsculas/minúsculas diferentes.
        $uri = stripos($uri, $base) === 0 ? substr($uri, strlen($base)) : ltrim($uri, '/');
        $uri = trim($uri, '/');
        // Quem abrir .../public/ diretamente cai nas mesmas rotas.
        if (strcasecmp($uri, 'public') === 0 || stripos($uri, 'public/') === 0) $uri = ltrim(substr($uri, 6), '/');
        return $uri;
    }
}
