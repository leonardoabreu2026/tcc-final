<?php
declare(strict_types=1);

/**
 * CALIBRADOR das máquinas de extração (vaga, curso e currículo).
 *
 * As máquinas de extração funcionam por REGRAS: palavras-chave e padrões escritos no código
 * (ExtracaoVaga, ExtracaoCurso, ExtracaoCurriculo). O calibrador ajusta essas regras sem mexer no
 * código, em duas partes:
 *
 *  1. TERMOS CALIBRADOS (manual — tabela calibracao_extracao, tela admin/pages/calibrador.php):
 *     "quando o texto tiver o termo X, mande para Y". Ex.: "uniforme" → Benefícios da vaga;
 *     "churrasqueiro" → área Alimentação. O termo calibrado vale mais que a regra do código,
 *     porque foi o administrador quem decidiu. Entre dois termos que casam, vence o mais longo
 *     (o mais específico: "vale refeição" ganha de "vale").
 *  2. NOMES CONHECIDOS (automático): as empresas e instituições que já estão no sistema (anunciante
 *     das vagas, nome fantasia das empresas, instituição dos cursos). Toda vaga ou curso salvo entra
 *     na lista sozinho — nada para treinar nem atualizar à mão. Só é usado quando nenhuma regra
 *     achou o nome no texto.
 *
 * Tudo é determinístico: o mesmo texto com os mesmos termos dá sempre o mesmo resultado, e cada
 * decisão do calibrador fica registrada em $r['calibrador'] (aparece no relatório da extração).
 * Se a tabela não existir ou o banco falhar, a extração segue só com as regras.
 */
final class Calibrador {
    /** Onde um termo pode atuar (contexto) e o rótulo mostrado na tela. */
    public const CONTEXTOS = [
        'vaga_linha' => 'Vaga — linha do anúncio vai para o campo',
        'vaga_categoria' => 'Vaga — área (categoria)',
        'curso_categoria' => 'Curso / e-book — área (categoria)',
        'curriculo_linha' => 'Currículo — linha solta vai para a seção',
    ];
    /** Destinos fixos de cada contexto (as áreas vêm da tabela categorias). */
    public const CAMPOS_VAGA = ['descricao' => 'Descrição / atividades', 'requisitos' => 'Requisitos', 'beneficios' => 'Benefícios'];
    public const SECOES_CURRICULO = [
        'objetivo' => 'Objetivo', 'experiencias' => 'Experiências', 'formacao' => 'Formação', 'cursos' => 'Cursos',
        'habilidades' => 'Habilidades', 'competencias' => 'Competências', 'idiomas' => 'Idiomas', 'disponibilidade' => 'Disponibilidade',
    ];

    /** Palavras que, sozinhas, não identificam ninguém ("Loja", "Empresa Teste"): não viram nome conhecido. */
    private const NOMES_GENERICOS = ['vaga','vagas','emprego','empregos','empresa','empresas','salario','beneficio','beneficios','requisito','requisitos',
        'local','horario','contato','whatsapp','curriculo','oportunidade','oportunidades','contrata','contratamos','contratando','urgente','atencao',
        'processo','seletivo','trabalho','trabalhe','conosco','equipe','time','loja','lojas','nome','teste','nao','informado','sim','brasilia','df',
        'curso','cursos','gratuito','gratuita','online','ead','instituicao','escola','anuncio','cargo','funcao','servicos','geral','gerais'];

    /** Desligado, o calibrador não interfere (os testes das regras rodam assim). */
    private static bool $ligado = true;
    /** @var array<string,list<array{chave:string,termo:string,destino:string}>>|null termos ativos por contexto, mais longos primeiro */
    private static ?array $termos = null;
    /** @var array<string,list<string>> nomes conhecidos por tipo ('empresa', 'instituicao') */
    private static array $nomes = [];

    /** Contexto existe? */
    public static function contextoValido(string $contexto): bool { return isset(self::CONTEXTOS[$contexto]); }

    /** Rótulo do destino para a tela ("beneficios" → "Benefícios"; área fica como está). */
    public static function rotuloDestino(string $contexto, string $destino): string {
        return match ($contexto) {
            'vaga_linha' => self::CAMPOS_VAGA[$destino] ?? $destino,
            'curriculo_linha' => self::SECOES_CURRICULO[$destino] ?? $destino,
            default => $destino,
        };
    }

    /**
     * Decisão final de um contexto: o palpite da regra, a não ser que um termo calibrado diga outra coisa.
     * @return array{classe:string,origem:string,termo:string} origem 'regra' ou 'calibrador'
     */
    public static function decidir(string $contexto, string $texto, string $palpiteRegra): array {
        $t = self::termoQueCasa($contexto, $texto);
        if ($t === null || $t['destino'] === $palpiteRegra) return ['classe' => $palpiteRegra, 'origem' => 'regra', 'termo' => ''];
        return ['classe' => $t['destino'], 'origem' => 'calibrador', 'termo' => $t['termo']];
    }

    /**
     * Primeiro termo calibrado (o mais longo) que aparece no texto como palavra/expressão inteira.
     * @return array{chave:string,termo:string,destino:string}|null
     */
    public static function termoQueCasa(string $contexto, string $texto): ?array {
        if (!self::$ligado || $texto === '') return null;
        $n = ' '.Competencias::normalizar($texto).' ';
        foreach (self::termos()[$contexto] ?? [] as $t) {
            if (str_contains($n, ' '.$t['chave'].' ')) return $t;
        }
        return null;
    }

    /**
     * Procura no texto uma empresa ('empresa') ou instituição ('instituicao') já cadastrada no sistema.
     * Os nomes mais longos são testados primeiro. '' = nenhum.
     */
    public static function nomeConhecido(string $tipo, string $texto): string {
        if (!self::$ligado || $texto === '') return '';
        $n = ' '.Competencias::normalizar($texto).' ';
        foreach (self::nomes($tipo) as $nome) {
            if (str_contains($n, ' '.Competencias::normalizar($nome).' ')) return $nome;
        }
        return '';
    }

    /** Nome curto demais ou feito só de palavras genéricas? */
    public static function nomeGenerico(string $nome): bool {
        $palavras = array_filter(explode(' ', Competencias::normalizar($nome)));
        if (mb_strlen(implode(' ', $palavras)) < 3) return true;
        return !array_diff($palavras, self::NOMES_GENERICOS);
    }

    /**
     * Nomes conhecidos de um tipo, já sem os genéricos e do mais longo para o mais curto.
     * @return list<string>
     */
    public static function nomes(string $tipo): array {
        if (isset(self::$nomes[$tipo])) return self::$nomes[$tipo];
        try {
            $lista = (new CalibracaoDAO())->nomesConhecidos($tipo);
        } catch (Throwable $e) {
            error_log('[Calibrador] Não foi possível carregar os nomes ('.$tipo.'): '.$e->getMessage());
            $lista = [];
        }
        return self::$nomes[$tipo] = self::organizarNomes($lista);
    }

    /** Termos ativos agrupados por contexto (carregados uma vez por requisição). */
    private static function termos(): array {
        if (self::$termos !== null) return self::$termos;
        try {
            $linhas = (new CalibracaoDAO())->ativos();
        } catch (Throwable $e) {
            error_log('[Calibrador] Não foi possível carregar os termos: '.$e->getMessage());
            $linhas = [];
        }
        return self::$termos = self::agrupar($linhas);
    }

    /** @param list<array{contexto:string,termo:string,destino:string}> $linhas */
    private static function agrupar(array $linhas): array {
        $por = [];
        foreach ($linhas as $l) {
            $chave = Competencias::normalizar((string)$l['termo']);
            if ($chave === '' || !self::contextoValido((string)$l['contexto'])) continue;
            $por[$l['contexto']][] = ['chave' => $chave, 'termo' => (string)$l['termo'], 'destino' => (string)$l['destino']];
        }
        foreach ($por as &$lista) usort($lista, fn($a, $b) => mb_strlen($b['chave']) <=> mb_strlen($a['chave']));
        return $por;
    }

    /** @return list<string> */
    private static function organizarNomes(array $lista): array {
        $vistos = []; $out = [];
        foreach ($lista as $nome) {
            $nome = trim((string)$nome);
            $k = Competencias::normalizar($nome);
            if ($k === '' || isset($vistos[$k]) || self::nomeGenerico($nome)) continue;
            $vistos[$k] = true; $out[] = $nome;
        }
        usort($out, fn($a, $b) => mb_strlen(Competencias::normalizar($b)) <=> mb_strlen(Competencias::normalizar($a)));
        return $out;
    }

    // ------------------------------------------------------------------ testes (tests/smoke.php)

    public static function ligar(bool $ligado): void { self::$ligado = $ligado; }

    /** Usa estes termos no lugar dos do banco. @param list<array{contexto:string,termo:string,destino:string}> $linhas */
    public static function usarTermos(array $linhas): void { self::$termos = self::agrupar($linhas); }

    /** Usa estes nomes no lugar dos do banco. */
    public static function usarNomes(string $tipo, array $nomes): void { self::$nomes[$tipo] = self::organizarNomes($nomes); }

    /** Esquece o que carregou: a próxima extração lê o banco de novo (depois de salvar um termo, por exemplo). */
    public static function limpar(): void { self::$termos = null; self::$nomes = []; }
}
