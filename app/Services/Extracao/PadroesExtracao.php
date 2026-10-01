<?php
declare(strict_types=1);

/**
 * PADRÕES AUTOMÁTICOS das máquinas de extração — se atualizam sozinhos com o que já está cadastrado.
 *
 * As máquinas de extração (ExtracaoVaga, ExtracaoCurso) funcionam por REGRAS: palavras-chave e padrões escritos
 * no código. Onde a regra não sabe decidir, entram estes padrões, tirados na hora dos cadastros que já passaram
 * pela revisão de uma pessoa — sem tabela, sem tela de ajuste e sem treino:
 *
 *  - vaga_linha:      palavras e pares de palavras que, nas vagas salvas, ficam quase sempre no mesmo campo
 *                     (ex.: "café da manhã" em Benefícios) — decidem a linha que a regra jogaria na descrição por falta de pista;
 *  - vaga_categoria:  palavras dos títulos das vagas salvas que quase sempre são da mesma área (ex.: "churrasqueiro" → Alimentação);
 *  - curso_categoria: o mesmo com os títulos do catálogo de cursos e e-books;
 *  - nomes:           empresas (anunciante das vagas e nome fantasia das empresas) e instituições (dos cursos).
 *
 * Padrão = texto normalizado por caractere (sem acento, minúsculo, sem pontuação; número vira "#"), visto em pelo
 * menos MIN_CADASTROS cadastros diferentes e em pelo menos PUREZA deles no mesmo destino. Toda vaga ou curso salvo
 * entra na próxima extração. Tudo é determinístico e explicável: a decisão registra o padrão que valeu.
 * Currículo não entra: o perfil do candidato tem dados pessoais (LGPD) — a extração dele segue só as regras.
 * Se o banco falhar, a extração segue só com as regras.
 */
final class PadroesExtracao {
    /** Um padrão precisa aparecer em pelo menos tantos cadastros diferentes... */
    private const MIN_CADASTROS = 2;
    /** ...e cair no mesmo destino em pelo menos esta fração deles. */
    private const PUREZA = 0.9;
    /** Palavras que sozinhas não dizem nada (artigos, preposições, conectivos). */
    private const VAZIAS = ['a','o','as','os','um','uma','uns','umas','de','da','do','das','dos','e','ou','em','no','na','nos','nas','ao','aos',
        'para','pra','por','pelo','pela','com','sem','se','que','mais','ate','sua','seu','suas','seus','nossa','nosso','r','x','h','ser','ter','tem',
        'vaga','vagas','contrata','contratamos','empresa','local','dia','dias','mes','ano','anos'];
    /** Palavras que, sozinhas, não identificam ninguém ("Loja", "Empresa Teste"): não viram nome conhecido. */
    private const NOMES_GENERICOS = ['vaga','vagas','emprego','empregos','empresa','empresas','salario','beneficio','beneficios','requisito','requisitos',
        'local','horario','contato','whatsapp','curriculo','oportunidade','oportunidades','contrata','contratamos','contratando','urgente','atencao',
        'processo','seletivo','trabalho','trabalhe','conosco','equipe','time','loja','lojas','nome','teste','nao','informado','sim','brasilia','df',
        'curso','cursos','gratuito','gratuita','online','ead','instituicao','escola','anuncio','cargo','funcao','servicos','geral','gerais'];

    /** Desligado, os padrões não interferem (os testes das regras rodam assim). */
    private static bool $ligado = true;
    /** @var array<string,array<string,array{destino:string,vezes:int}>>|null padrões por contexto, montados uma vez por requisição */
    private static ?array $padroes = null;
    /** @var array<string,list<string>> nomes conhecidos por tipo ('empresa', 'instituicao') */
    private static array $nomes = [];

    /**
     * Decisão onde a regra não tem pista: o padrão mais específico que aparece no texto (par de palavras antes de
     * palavra solta; empate → o visto em mais cadastros). Sem padrão, fica o palpite da regra.
     * @return array{classe:string,origem:string,termo:string} origem 'regra' ou 'padrao'
     */
    public static function decidir(string $contexto, string $texto, string $palpiteRegra): array {
        $p = self::padraoQueCasa($contexto, $texto);
        if ($p === null || $p['destino'] === $palpiteRegra) return ['classe' => $palpiteRegra, 'origem' => 'regra', 'termo' => ''];
        return ['classe' => $p['destino'], 'origem' => 'padrao', 'termo' => $p['termo']];
    }

    /** @return array{termo:string,destino:string,vezes:int}|null */
    public static function padraoQueCasa(string $contexto, string $texto): ?array {
        if (!self::$ligado || $texto === '') return null;
        $tabela = self::padroes()[$contexto] ?? [];
        $melhor = null;
        foreach (self::termos($texto) as $t) {
            if (!isset($tabela[$t])) continue;
            $c = ['termo' => $t, 'destino' => $tabela[$t]['destino'], 'vezes' => $tabela[$t]['vezes']];
            if ($melhor === null || [substr_count($t, ' '), $c['vezes']] > [substr_count($melhor['termo'], ' '), $melhor['vezes']]) $melhor = $c;
        }
        return $melhor;
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
     * Palavras e pares de palavras vizinhas de um texto, normalizados por caractere: sem acento, minúsculos,
     * sem pontuação, número vira "#"; palavras vazias e soltas de 1–2 letras ficam de fora.
     * @return list<string>
     */
    public static function termos(string $texto): array {
        $palavras = array_values(array_filter(explode(' ', preg_replace('/\d+/', '#', Competencias::normalizar($texto)) ?? '')));
        $out = [];
        foreach ($palavras as $i => $p) {
            $vazia = in_array($p, self::VAZIAS, true) || $p === '#' || mb_strlen($p) < 3;
            if (!$vazia) $out[$p] = true;
            // Par de palavras: pula as vazias do meio ("café da manhã" → "cafe manha").
            if ($vazia) continue;
            for ($j = $i + 1; $j < count($palavras) && $j <= $i + 3; $j++) {
                $q = $palavras[$j];
                if (in_array($q, self::VAZIAS, true) || $q === '#' || mb_strlen($q) < 3) continue;
                $out[$p.' '.$q] = true;
                break;
            }
        }
        return array_keys($out);
    }

    /** Padrões dos três contextos, montados dos cadastros (uma vez por requisição). */
    public static function padroes(): array {
        if (self::$padroes !== null) return self::$padroes;
        try {
            $db = Database::getConexao();
            $vagas = $db->query("SELECT v.titulo, v.descricao, v.requisitos, v.beneficios, c.nome AS categoria
                                 FROM vagas v LEFT JOIN categorias c ON c.id=v.categoria_id")->fetchAll();
            $cursos = $db->query("SELECT cu.titulo, c.nome AS categoria FROM cursos cu LEFT JOIN categorias c ON c.id=cu.categoria_id")->fetchAll();
        } catch (Throwable $e) {
            error_log('[PadroesExtracao] Sem padrões (banco indisponível): '.$e->getMessage());
            $vagas = $cursos = [];
        }
        return self::$padroes = self::montar($vagas, $cursos);
    }

    /**
     * Monta os padrões a partir de registros (vagas: titulo, descricao, requisitos, beneficios, categoria;
     * cursos: titulo, categoria). Público para os testes montarem com dados em memória.
     */
    public static function montar(array $vagas, array $cursos): array {
        $linhas = []; $catVaga = []; $catCurso = [];
        foreach ($vagas as $k => $v) {
            foreach (['descricao', 'requisitos', 'beneficios'] as $campo) {
                foreach (preg_split('/\R/u', (string)($v[$campo] ?? '')) ?: [] as $l) {
                    foreach (self::termos($l) as $t) $linhas[$t][$campo][$k] = true;   // conta cadastros, não linhas
                }
            }
            if (($v['categoria'] ?? '') !== '') foreach (self::termos((string)$v['titulo']) as $t) $catVaga[$t][$v['categoria']][$k] = true;
        }
        foreach ($cursos as $k => $c) {
            if (($c['categoria'] ?? '') !== '') foreach (self::termos((string)$c['titulo']) as $t) $catCurso[$t][$c['categoria']][$k] = true;
        }
        return ['vaga_linha' => self::filtrar($linhas), 'vaga_categoria' => self::filtrar($catVaga), 'curso_categoria' => self::filtrar($catCurso)];
    }

    /** Fica o termo visto em MIN_CADASTROS cadastros ou mais e quase sempre no mesmo destino. */
    private static function filtrar(array $contagem): array {
        $out = [];
        foreach ($contagem as $termo => $destinos) {
            $vezes = array_map('count', $destinos);
            arsort($vezes);
            $total = array_sum($vezes);
            $destino = (string)array_key_first($vezes);
            if ($total >= self::MIN_CADASTROS && $vezes[$destino] / $total >= self::PUREZA) $out[(string)$termo] = ['destino' => $destino, 'vezes' => $vezes[$destino]];
        }
        return $out;
    }

    /**
     * Nomes conhecidos de um tipo, já sem os genéricos e do mais longo para o mais curto.
     * @return list<string>
     */
    public static function nomes(string $tipo): array {
        if (isset(self::$nomes[$tipo])) return self::$nomes[$tipo];
        $sql = match ($tipo) {
            'empresa' => "SELECT anunciante FROM vagas WHERE anunciante IS NOT NULL AND anunciante<>''
                          UNION SELECT p.nome_fantasia FROM perfis p JOIN usuarios u ON u.id=p.usuario_id
                          WHERE u.tipo='empresa' AND p.nome_fantasia IS NOT NULL AND p.nome_fantasia<>''",
            'instituicao' => "SELECT DISTINCT instituicao FROM cursos WHERE instituicao IS NOT NULL AND instituicao<>''",
            default => '',
        };
        try {
            $lista = $sql !== '' ? Database::getConexao()->query($sql)->fetchAll(PDO::FETCH_COLUMN) : [];
        } catch (Throwable $e) {
            error_log('[PadroesExtracao] Sem nomes ('.$tipo.'): '.$e->getMessage());
            $lista = [];
        }
        return self::$nomes[$tipo] = self::organizarNomes($lista);
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

    /** Usa estes padrões no lugar dos do banco (montados com PadroesExtracao::montar). */
    public static function usarPadroes(array $padroes): void { self::$padroes = $padroes; }

    /** Usa estes nomes no lugar dos do banco. */
    public static function usarNomes(string $tipo, array $nomes): void { self::$nomes[$tipo] = self::organizarNomes($nomes); }

    /** Esquece o que montou: a próxima extração lê os cadastros de novo. */
    public static function limpar(): void { self::$padroes = null; self::$nomes = []; }
}
