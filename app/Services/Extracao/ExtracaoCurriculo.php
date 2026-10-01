<?php
declare(strict_types=1);

/**
 * Máquina de extração do currículo: lê o arquivo e separa os dados profissionais.
 * Não inventa informação — só organiza o texto que existe no arquivo.
 *
 * Etapas:
 *  1. linhas "de dados" com rótulo (Nome:, Endereço:, CNH:, Pretensão salarial:, PCD: ...) são lidas
 *     e retiradas do fluxo, para não se misturarem às seções;
 *  2. nome (rótulo "Nome:", maior fonte do arquivo ou primeira linha com cara de nome) e o título logo abaixo;
 *  3. divisão em seções pelos títulos (com ou sem acento, maiúsculas, numeração, ícones, "E X P E R I Ê N C I A");
 *  4. cada campo é montado a partir da sua seção; experiências e formação saem em formato padronizado.
 * Só regras: o currículo tem dados pessoais do candidato (LGPD), por isso não entra nos padrões automáticos (PadroesExtracao).
 */
final class ExtracaoCurriculo {
    /** Seções reconhecidas e os títulos que as identificam (normalizados). */
    private const SECOES = [
        'resumo' => ['resumo','resumo profissional','perfil','perfil profissional','sobre mim','sobre','apresentacao','qualificacoes','qualificacoes profissionais','sumario','sumario profissional','quem sou','quem sou eu','resumo de qualificacoes','perfil pessoal'],
        'objetivo' => ['objetivo','objetivos','objetivo profissional','objetivo de carreira','cargo pretendido','area de interesse','cargo desejado','vaga pretendida','areas de interesse'],
        'experiencias' => ['experiencia','experiencias','experiencia profissional','experiencias profissionais','historico profissional','atuacao profissional','trajetoria profissional','empregos anteriores','ultimas experiencias','experiencia de trabalho','historico de trabalho','estagios','experiencia e estagios','experiencias e estagios'],
        'formacao' => ['formacao','formacao academica','formacao escolar','escolaridade','educacao','formacao educacional','graduacao','formacao profissional','formacao academica e tecnica','formacao tecnica'],
        'cursos' => ['cursos','cursos complementares','cursos extracurriculares','cursos e certificacoes','certificacoes','certificados','qualificacao profissional','capacitacoes','cursos livres','cursos e treinamentos','treinamentos','formacao complementar','cursos profissionalizantes'],
        'habilidades' => ['habilidades','habilidades tecnicas','conhecimentos','conhecimentos tecnicos','ferramentas','tecnologias','competencias tecnicas','hard skills','informatica','conhecimentos em informatica','conhecimentos de informatica','conhecimentos especificos','principais habilidades','skills','stack'],
        'competencias' => ['competencias','competencias comportamentais','soft skills','habilidades comportamentais','caracteristicas','pontos fortes','qualidades','caracteristicas pessoais'],
        'idiomas' => ['idiomas','idioma','linguas','lingua estrangeira','linguas estrangeiras'],
        'contato' => ['contato','contatos','dados pessoais','informacoes pessoais','dados de contato','informacoes de contato','dados','endereco'],
        'links' => ['links','redes sociais','portfolio','portfolios','perfis','redes','links profissionais'],
        'pcd' => ['pcd','pessoa com deficiencia','deficiencia','acessibilidade','laudo'],
        'disponibilidade' => ['disponibilidade','disponibilidade de horario'],
        'pretensao' => ['pretensao','pretensao salarial'],
        'referencias' => ['referencias','referencias profissionais','referencias pessoais'],
        'outros' => ['informacoes adicionais','informacoes complementares','outras informacoes','atividades complementares','voluntariado','trabalho voluntario','observacoes','observacao','hobbies','interesses','registro','registro profissional','conselho de classe','premios','conquistas','atividades extracurriculares','projetos','projetos pessoais','publicacoes','adicionais','diferenciais'],
    ];

    /** Início de título aceito quando a linha está em MAIÚSCULAS ou termina com ":" (ex.: "EXPERIÊNCIAS NA ÁREA"). */
    private const PREFIXOS = ['experiencia' => 'experiencias', 'formacao' => 'formacao', 'cursos' => 'cursos', 'habilidades' => 'habilidades', 'idiomas' => 'idiomas', 'competencias' => 'competencias', 'objetivo' => 'objetivo', 'resumo' => 'resumo', 'informacoes adicionais' => 'outros', 'certificac' => 'cursos'];

    /** Regiões administrativas do DF e cidades do entorno (para cidade/UF). */
    private const CIDADES_DF = ['Brasília','Asa Sul','Asa Norte','Lago Sul','Lago Norte','Sudoeste','Octogonal','Cruzeiro','Guará','Taguatinga','Ceilândia','Samambaia','Águas Claras','Vicente Pires','Riacho Fundo','Recanto das Emas','Gama','Santa Maria','Núcleo Bandeirante','Candangolândia','Park Way','Sobradinho','Planaltina','Paranoá','Itapoã','São Sebastião','Jardim Botânico','Brazlândia','Estrutural','SCIA','Varjão','Fercal','Sol Nascente','Pôr do Sol','Arniqueira','Arapoanga','Noroeste'];
    private const CIDADES_ENTORNO = ['Valparaíso de Goiás','Valparaíso','Luziânia','Águas Lindas de Goiás','Águas Lindas','Novo Gama','Cidade Ocidental','Formosa','Planaltina de Goiás','Santo Antônio do Descoberto','Cristalina','Goiânia','Anápolis'];
    private const UFS = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

    /** Rótulos de "linhas de dados" (normalizados) → campo. Dados sensíveis (estado civil, CPF, RG...) são lidos só para serem descartados. */
    private const ROTULOS = [
        'nome' => 'nome', 'nome completo' => 'nome',
        'endereco' => 'endereco', 'residencia' => 'endereco', 'reside em' => 'endereco', 'localizacao' => 'endereco', 'cidade' => 'endereco', 'bairro' => 'endereco',
        'telefone' => 'telefone', 'telefones' => 'telefone', 'celular' => 'telefone', 'fone' => 'telefone', 'tel' => 'telefone', 'whatsapp' => 'telefone',
        'e mail' => 'email', 'email' => 'email',
        'data de nascimento' => 'nascimento', 'nascimento' => 'nascimento', 'data nasc' => 'nascimento', 'dn' => 'nascimento',
        'idade' => 'idade',
        'cnh' => 'cnh', 'habilitacao' => 'cnh', 'carteira de habilitacao' => 'cnh', 'carteira de motorista' => 'cnh',
        'pretensao salarial' => 'pretensao', 'pretensao' => 'pretensao', 'salario pretendido' => 'pretensao', 'expectativa salarial' => 'pretensao',
        'disponibilidade' => 'disponibilidade', 'disponibilidade de horario' => 'disponibilidade', 'horario' => 'disponibilidade', 'turno' => 'disponibilidade',
        'pcd' => 'pcd', 'pessoa com deficiencia' => 'pcd', 'deficiencia' => 'pcd', 'laudo' => 'pcd',
        'linkedin' => 'link', 'github' => 'link', 'gitlab' => 'link', 'portfolio' => 'link', 'site' => 'link', 'behance' => 'link', 'lattes' => 'link', 'instagram' => 'link',
        'estado civil' => 'sensivel', 'cpf' => 'sensivel', 'rg' => 'sensivel', 'nacionalidade' => 'sensivel', 'naturalidade' => 'sensivel', 'filhos' => 'sensivel', 'sexo' => 'sensivel', 'genero' => 'sensivel', 'religiao' => 'sensivel',
    ];


    /** Lê o arquivo (PDF/DOCX/DOC) e devolve o texto. */
    public static function extrair(string $path): string {
        return LeitorDocumento::extrair($path);
    }

    public static function metodo(): string { return LeitorDocumento::$metodo; }

    /** Campos devolvidos (sempre presentes, vazios quando não encontrados). */
    public const CAMPOS = ['nome','email','telefone','data_nascimento','idade','titulo_profissional','bio','objetivo','experiencias','formacao','cursos','habilidades','competencias','idiomas','cidade','uf','nivel_experiencia','informacoes_adicionais','links','cnh','disponibilidade','pretensao_salarial','pcd'];

    /**
     * @param string[] $destaques linhas em fonte grande no arquivo (LeitorDocumento::$destaques) — ajudam a achar o nome
     * @return array<string,string> campos do perfil encontrados no texto
     */
    public static function extrairCampos(string $texto, array $destaques = []): array {
        $texto = LeitorDocumento::limpar($texto);
        $r = array_fill_keys(self::CAMPOS, '');
        if ($texto === '') return $r;

        // ---- 1) linhas e linhas de dados com rótulo
        $linhas = self::linhas($texto);
        $dados = []; $resto = [];
        foreach ($linhas as $l) {
            $d = self::linhaDeDados($l);
            if ($d === null) { $resto[] = $l; continue; }
            foreach ($d as [$campo, $valor]) $dados[$campo][] = $valor;
        }

        // ---- 2) nome e título
        [$nome, $idxNome, $qtdNome] = self::nome($resto, $dados['nome'] ?? [], $destaques);
        $r['nome'] = $nome;
        $titulo = '';
        if ($idxNome !== null) {
            for ($j = $idxNome + $qtdNome, $vistas = 0; $j < count($resto) && $vistas < 3; $j++, $vistas++) {
                $l = self::semMarcador($resto[$j]);
                if (self::tituloSecao($l)) break;
                if (self::ehContato($l)) continue;
                if (self::ehTitulo($l)) { $titulo = self::capitalizar($l, false); array_splice($resto, $j, 1); break; }
                break;
            }
            array_splice($resto, $idxNome, $qtdNome);
        }

        // ---- 3) seções
        [$cabecalho, $secoes] = self::separarSecoes($resto);
        foreach ($secoes['contato'] ?? [] as $l) $cabecalho[] = $l;

        // ---- 4) contato, local, datas
        $contato = implode("\n", array_merge($cabecalho, $dados['telefone'] ?? [], $dados['email'] ?? [], $dados['endereco'] ?? []));
        $semReferencias = implode("\n", array_diff($linhas, $secoes['referencias'] ?? []));
        $r['email'] = self::email($contato) ?: self::email($semReferencias);
        $r['telefone'] = self::telefone($contato) ?: self::telefone($semReferencias);
        [$r['cidade'], $r['uf']] = self::localPreferido(array_merge($dados['endereco'] ?? [], $cabecalho), $semReferencias);
        $r['data_nascimento'] = self::nascimento(implode("\n", array_map(fn($v) => 'Nascimento: '.$v, $dados['nascimento'] ?? []))."\n".$texto);
        $r['idade'] = self::idade($dados['idade'] ?? [], $cabecalho, $r['data_nascimento']);
        $r['links'] = implode("\n", self::links($texto));

        // ---- 5) texto das seções
        $txt = fn(string $k) => trim(implode("\n", $secoes[$k] ?? []));
        $r['bio'] = self::paragrafo(self::semMarcadores($txt('resumo')));
        if ($r['bio'] === '') $r['bio'] = self::bioDoCabecalho($cabecalho);
        $r['objetivo'] = self::paragrafo(self::semMarcadores($txt('objetivo')));

        $exp = $txt('experiencias');
        $itensExp = Portfolio::experiencias($exp);
        $r['experiencias'] = $itensExp ? Portfolio::serializarExperiencias($itensExp) : $exp;
        $form = $txt('formacao');
        $itensForm = Portfolio::formacao($form);
        $r['formacao'] = $itensForm ? Portfolio::serializarFormacao($itensForm) : $form;
        $r['cursos'] = implode("\n", self::itens($txt('cursos'), false));
        $r['idiomas'] = self::idiomas($txt('idiomas') !== '' ? $txt('idiomas') : self::idiomasSoltos($texto));

        // ---- 6) dados complementares
        $outros = array_merge($secoes['outros'] ?? [], $secoes['disponibilidade'] ?? [], $secoes['pretensao'] ?? []);
        $r['cnh'] = self::cnh(implode("\n", array_merge(array_map(fn($v) => 'CNH '.$v, $dados['cnh'] ?? []), $outros, $cabecalho)));
        $r['pretensao_salarial'] = self::pretensao(array_merge($dados['pretensao'] ?? [], $secoes['pretensao'] ?? [], $outros));
        $r['disponibilidade'] = self::disponibilidade(array_merge($dados['disponibilidade'] ?? [], $secoes['disponibilidade'] ?? []), $secoes['outros'] ?? []);
        $r['pcd'] = self::pcd($dados['pcd'] ?? [], $secoes['pcd'] ?? [], $linhas);
        $r['informacoes_adicionais'] = self::informacoes($r['pcd'], $secoes['outros'] ?? [], $r);

        // ---- 7) título, nível, habilidades e competências
        $r['titulo_profissional'] = $titulo !== '' ? $titulo : self::tituloAlternativo($cabecalho, $r['objetivo'], $itensExp);
        $r['nivel_experiencia'] = self::nivel($r['titulo_profissional'], $itensExp, $texto);

        $todas = Competencias::extrair($texto);
        $tecnicas = array_values(array_diff($todas, Competencias::COMPORTAMENTAIS));
        $itensHab = self::itens($txt('habilidades'), true);
        // Não repete o nome do dicionário quando o candidato já escreveu a mesma coisa (ex.: "MySQL" × "Banco de dados SQL").
        $cobertas = Competencias::extrair(implode("\n", $itensHab));
        // Competências do dicionário achadas no texto só completam a lista quando o candidato não listou (quase) nada;
        // o match lê o perfil e o currículo inteiros de qualquer forma (Competencias::doPerfil).
        $r['habilidades'] = self::juntarLista($itensHab, count($itensHab) < 3 ? array_values(array_diff($tecnicas, $cobertas)) : []);
        $itensComp = self::itens($txt('competencias'), true);
        $comport = array_values(array_diff(array_intersect($todas, Competencias::COMPORTAMENTAIS), Competencias::extrair(implode("\n", $itensComp))));
        $r['competencias'] = self::juntarLista($itensComp, count($itensComp) < 3 ? $comport : []);
        return $r;
    }

    // ------------------------------------------------------------------ linhas

    private static function linhas(string $t): array {
        $out = [];
        foreach (preg_split('/\R/u', $t) ?: [] as $l) {
            $l = trim($l);
            // Títulos espaçados letra a letra ("E X P E R I Ê N C I A") viram palavra.
            if (preg_match('/^(?:\p{L} ){3,}\p{L}$/u', $l)) $l = str_replace(' ', '', $l);
            if ($l !== '') $out[] = $l;
        }
        return $out;
    }

    private static function semMarcador(string $l): string {
        return trim(preg_replace('/^[\s•·▪■●◦○►▶➢➤✓✔☐☑\-–—*>\p{So}\p{Co}\x{FE0F}\x{200D}]+/u', '', $l) ?? $l);
    }

    private static function semMarcadores(string $t): string {
        return implode("\n", array_map([self::class, 'semMarcador'], preg_split('/\R/u', $t) ?: []));
    }

    /**
     * Linha "Rótulo: valor" de dado pessoal/contato. Uma linha pode ter vários pares
     * separados por "·" ou "|" (ex.: "LinkedIn: x · GitHub: y").
     * @return array<int,array{0:string,1:string}>|null
     */
    private static function linhaDeDados(string $linha): ?array {
        $l = self::semMarcador($linha);
        $pares = preg_split('/\s+[·|•]\s+/u', $l) ?: [$l];
        $out = [];
        foreach ($pares as $p) {
            if (!preg_match('/^([\p{L} .\-]{2,30}?)(?:\s*\/\s*[\p{L}]+)?\s*[:：]\s*(.*)$/u', $p, $m)) continue;
            $rot = Competencias::normalizar($m[1]);
            if (!isset(self::ROTULOS[$rot]) || trim($m[2]) === '') continue;
            $out[] = [self::ROTULOS[$rot], trim($m[2])];
        }
        // "PCD – descrição" sem dois-pontos.
        if (!$out && preg_match('/^(pcd|pessoa com defici[eê]ncia)\s*[-–—]\s*(.+)$/iu', $l, $m)) $out[] = ['pcd', trim($m[2])];
        if (!$out) return null;
        // A linha só é "de dados" se o primeiro par começa a linha (evita frases com dois-pontos no meio).
        return $out;
    }

    /** Se a linha é um título de seção, devolve [chave, conteúdo na mesma linha]. */
    private static function tituloSecao(string $linha): ?array {
        $linha = self::semMarcador($linha);
        if (mb_strlen($linha) > 60 && !str_contains($linha, ':')) return null;
        $partes = preg_split('/\s*:\s*/u', $linha, 2) ?: [$linha];
        $cab = Competencias::normalizar(preg_replace('/^(\d+|[IVX]+)[.)\-]\s*/u', '', $partes[0]) ?? $partes[0]);
        $resto = trim($partes[1] ?? '');
        if ($cab === '') return null;
        foreach (self::SECOES as $chave => $titulos) {
            if (in_array($cab, $titulos, true)) return [$chave, $resto];
        }
        // "EXPERIÊNCIAS NA ÁREA ADMINISTRATIVA", "Formação acadêmica e técnica:" etc.
        $maiusc = $partes[0] === mb_strtoupper($partes[0]) && preg_match('/\p{Lu}/u', $partes[0]);
        if (($maiusc || (count($partes) === 2 && $resto === '')) && mb_strlen($partes[0]) <= 45 && !preg_match('/\d/', $partes[0]) && count(explode(' ', $cab)) <= 5) {
            foreach (self::PREFIXOS as $pre => $chave) if (str_starts_with($cab, $pre)) return [$chave, $resto];
        }
        return null;
    }

    /** @return array{0:string[],1:array<string,string[]>} [linhas antes da 1ª seção, linhas de cada seção] */
    private static function separarSecoes(array $linhas): array {
        $cabecalho = []; $secoes = []; $atual = null;
        foreach ($linhas as $l) {
            $t = self::tituloSecao($l);
            if ($t) {
                $atual = $t[0];
                $secoes[$atual] ??= [];
                if ($t[1] !== '') $secoes[$atual][] = $t[1];
                continue;
            }
            if ($atual === null) $cabecalho[] = $l; else $secoes[$atual][] = $l;
        }
        return [$cabecalho, $secoes];
    }

    // ------------------------------------------------------------------ nome e título

    /**
     * @param string[] $rotulados valores de "Nome:"
     * @return array{0:string,1:?int,2:int} [nome, índice da linha do nome em $linhas, quantidade de linhas]
     */
    private static function nome(array $linhas, array $rotulados, array $destaques): array {
        foreach ($rotulados as $v) if (self::ehNome($v)) return [self::capitalizar($v), null, 0];
        // Destaques (maior fonte): podem ser o nome em 1 ou 2 linhas.
        $destaques = array_values(array_unique(array_filter(array_map('trim', $destaques))));
        $cands = [];
        if ($destaques) { $cands[] = [$destaques[0]]; if (isset($destaques[1])) $cands[] = [$destaques[0], $destaques[1]]; }
        foreach (array_reverse($cands) as $partes) {
            $nome = implode(' ', $partes);
            if (!self::ehNome($nome)) continue;
            $n = count($partes);
            for ($i = 0; $i + $n <= count($linhas); $i++) {
                $junto = implode(' ', array_map([self::class, 'semMarcador'], array_slice($linhas, $i, $n)));
                if (Competencias::normalizar($junto) === Competencias::normalizar($nome)) return [self::capitalizar($nome), $i, $n];
            }
            return [self::capitalizar($nome), null, 0];
        }
        // Primeira linha com cara de nome, antes da primeira seção.
        foreach (array_slice($linhas, 0, 10) as $i => $l) {
            $l = self::semMarcador($l);
            if (self::tituloSecao($l)) break;
            if (preg_match('/^(curr[ií]culo|curriculum|cv|resume)\b/iu', $l)) continue;
            if (!self::ehNome($l)) continue;
            // Nome em maiúsculas quebrado em duas linhas ("RAFAEL MENDES" / "OLIVEIRA").
            $prox = self::semMarcador($linhas[$i + 1] ?? '');
            if ($l === mb_strtoupper($l) && $prox !== '' && $prox === mb_strtoupper($prox) && count(explode(' ', $prox)) <= 2 && preg_match('/^[\p{L}\s]+$/u', $prox) && !self::tituloSecao($prox) && !self::ehTitulo($prox, true)) {
                return [self::capitalizar($l.' '.$prox), $i, 2];
            }
            return [self::capitalizar($l), $i, 1];
        }
        return ['', null, 0];
    }

    private static function ehNome(string $l): bool {
        $l = trim($l);
        if ($l === '' || str_contains($l, '@') || preg_match('/\d/', $l) || self::tituloSecao($l)) return false;
        if (!preg_match('/^[\p{L}\s.\'-]+$/u', $l)) return false;
        $p = preg_split('/\s+/u', $l) ?: [];
        if (count($p) < 2 || count($p) > 7) return false;
        if (preg_match('/^(curr[ií]culo|curriculum|resume)/iu', $l)) return false;
        // Não é cargo nem local.
        return !self::ehTitulo($l, true) && !self::ehLocal($l);
    }

    /** Título profissional: curto, sem dígitos/contato; com $soCargo, exige palavra típica de cargo/área. */
    private static function ehTitulo(string $l, bool $soCargo = false): bool {
        if ($soCargo) return (bool)preg_match('/\b(auxiliar|assistente|analista|t[eé]cnic[oa]|desenvolvedor[a]?|designer|gerente|profissional|estagi[aá]ri[oa]|atendente|vendedor[a]?|motorista|professor[a]?|enfermeir[oa]|engenheir[oa]|administrador[a]?|consultor[a]?|coordenador[a]?|supervisor[a]?|operador[a]?|programador[a]?|contador[a]?|recepcionista|secret[aá]ri[oa]|especialista|jovem aprendiz)\b/iu', $l);
        if (mb_strlen($l) < 3 || mb_strlen($l) > 80 || str_contains($l, '@') || preg_match('/\d{3}|https?:|www\./', $l)) return false;
        if (str_ends_with($l, '.') || self::ehLocal($l) || str_contains($l, ':')) return false;
        return (bool)preg_match('/^[\p{L}\s\/|&,.()+#-]+$/u', $l);
    }

    private static function ehContato(string $l): bool {
        return str_contains($l, '@') || (bool)preg_match('/\d{4}[\s.-]?\d{4}|https?:|www\.|linkedin|github/iu', $l) || self::ehLocal($l);
    }

    private static function tituloAlternativo(array $cabecalho, string $objetivo, array $itensExp): string {
        // "Atuar como X", "vaga de X", "cargo de X" no objetivo.
        if (preg_match('/(?:atuar como|atuando como|trabalhar como|vaga de|vaga como|cargo de|fun[cç][aã]o de|posi[cç][aã]o de|oportunidade como|oportunidade de|oportunidade na [aá]rea de|na [aá]rea de)\s+([^.,;\n]{3,60})/iu', $objetivo, $m)) {
            $t = trim(preg_replace('/\s+(ou|e)\s+.*$/iu', '', trim($m[1])) ?? $m[1]);
            $t = trim(preg_replace('/^(primeira|minha)\s+/iu', '', $t) ?? $t);
            return self::capitalizar($t, false);
        }
        if ($objetivo !== '' && mb_strlen($objetivo) <= 60 && !str_contains($objetivo, "\n") && !str_ends_with($objetivo, '.')) return self::capitalizar($objetivo, false);
        // Cargo mais recente.
        foreach ($itensExp as $x) if ($x['cargo'] !== '') return $x['cargo'];
        return '';
    }

    private static function bioDoCabecalho(array $cabecalho): string {
        $par = [];
        foreach ($cabecalho as $l) {
            $l = self::semMarcador($l);
            if (self::ehContato($l) || preg_match('/\d{2}\/\d{2}\/\d{4}/', $l)) continue;
            if (mb_strlen($l) >= 60 || ($par && !preg_match('/[.!?]$/u', end($par)))) $par[] = $l;
        }
        $t = self::paragrafo(implode("\n", $par));
        return mb_strlen($t) >= 80 ? $t : '';
    }

    // ------------------------------------------------------------------ campos

    private static function email(string $t): string {
        return preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $t, $m) ? strtolower($m[0]) : '';
    }

    /** Primeiro celular encontrado (ou, na falta, o primeiro fixo), no formato (DD) 9XXXX-XXXX. */
    private static function telefone(string $t): string {
        if (!preg_match_all('/(?<![\d\/])(?:\+?55[\s.-]?)?\(?(\d{2})\)?[\s.-]?(9[\s.]?\d{4}|[2-5]\d{3})[\s.-]?(\d{4})(?![\d\/])/', $t, $ms, PREG_SET_ORDER)) return '';
        $fixo = '';
        foreach ($ms as $m) {
            $ddd = (int)$m[1];
            if ($ddd < 11 || $ddd > 99) continue;
            $meio = preg_replace('/[\s.]/', '', $m[2]) ?? $m[2];
            $num = sprintf('(%02d) %s-%s', $ddd, $meio, $m[3]);
            if (strlen($meio) === 5) return $num;
            if ($fixo === '') $fixo = $num;
        }
        return $fixo;
    }

    private static function nascimento(string $t): string {
        if (preg_match('/(?:nascimento|nascido|nascida|data de nasc\.?|d\.?\s?n\.?)\s*(?:em)?\s*:?\s*(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})/iu', $t, $m)) {
            if (checkdate((int)$m[2], (int)$m[1], (int)$m[3]) && (int)$m[3] > 1930 && (int)$m[3] < (int)date('Y') - 13) {
                return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }
        }
        return '';
    }

    private static function idade(array $rotulados, array $cabecalho, string $nascimento): string {
        if ($nascimento !== '') return (string)(new DateTime($nascimento))->diff(new DateTime('today'))->y;
        foreach ($rotulados as $v) if (preg_match('/(\d{2})/', $v, $m)) return $m[1];
        foreach ($cabecalho as $l) if (preg_match('/(?:^|,\s*)(\d{2})\s*anos\b/iu', $l, $m) && (int)$m[1] >= 14 && (int)$m[1] <= 80) return $m[1];
        return '';
    }

    /** @return string[] URLs (com https://) de LinkedIn, GitHub, portfólio etc., sem repetição. */
    private static function links(string $t): array {
        $hosts = 'linkedin\.com|github\.com|gitlab\.com|behance\.net|dribbble\.com|instagram\.com|medium\.com|lattes\.cnpq\.br|youtube\.com|wa\.me|bitbucket\.org|figma\.com|notion\.site|vercel\.app|netlify\.app|github\.io';
        preg_match_all('#(?<![@\w.])(?:https?://[^\s<>"\'|·,;]+|www\.[^\s<>"\'|·,;]+|(?:[\w-]+\.)*(?:'.$hosts.')(?:/[^\s<>"\'|·,;]*)?)#iu', $t, $ms);
        $out = [];
        foreach ($ms[0] as $u) {
            $u = rtrim($u, '.,;:)]}');
            if (preg_match('#^mailto:#i', $u)) continue;
            $chave = LeitorDocumento::chaveLink($u);
            if ($chave === '' || !str_contains($chave, '.')) continue;
            $href = preg_match('#^https?://#i', $u) ? $u : 'https://'.$u;
            // Mesmo endereço com e sem "https://www.": fica um só.
            $dup = false;
            foreach ($out as $k => $v) {
                $ck = LeitorDocumento::chaveLink($v);
                if ($ck === $chave || str_starts_with($ck, $chave) || str_starts_with($chave, $ck)) { $dup = true; if (strlen($chave) > strlen($ck)) $out[$k] = $href; break; }
            }
            if (!$dup) $out[] = $href;
        }
        return $out;
    }

    private static function ehLocal(string $l): bool {
        $n = ' '.Competencias::normalizar($l).' ';
        foreach (array_merge(self::CIDADES_DF, self::CIDADES_ENTORNO) as $c) if (str_contains($n, ' '.Competencias::normalizar($c).' ')) return true;
        return (bool)preg_match('/\b(rua|quadra|qd|qnm|qnn|qr|conjunto|conj|casa|lote|avenida|av\.|bairro|cep|bloco|sqs|sqn|shis|setor)\b|\s[-–\/]\s?(DF|GO)\b/u', $l);
    }

    /** Local do candidato: primeiro nas linhas de endereço/cabeçalho, depois no texto todo. */
    private static function localPreferido(array $linhas, string $texto): array {
        foreach ($linhas as $l) {
            if (!self::ehLocal($l) && !preg_match('/\b('.implode('|', self::UFS).')\b/u', $l)) continue;
            $r = self::local($l);
            if ($r[0] !== '') return $r;
        }
        return self::local($texto);
    }

    /** @return array{0:string,1:string} */
    public static function local(string $t): array {
        $n = ' '.Competencias::normalizar($t).' ';
        // Prioriza RAs específicas (ex.: Ceilândia) antes de "Brasília"; e a que aparece primeiro no texto.
        $achadas = [];
        foreach (self::CIDADES_ENTORNO as $c) {
            $p = strpos($n, ' '.Competencias::normalizar($c).' ');
            if ($p !== false) $achadas[] = [$p, $c === 'Valparaíso' ? 'Valparaíso de Goiás' : ($c === 'Águas Lindas' ? 'Águas Lindas de Goiás' : $c), 'GO'];
        }
        foreach (self::CIDADES_DF as $c) {
            if ($c === 'Brasília') continue;
            $p = strpos($n, ' '.Competencias::normalizar($c).' ');
            if ($p !== false) $achadas[] = [$p, $c, 'DF'];
        }
        if ($achadas) { usort($achadas, fn($a, $b) => $a[0] <=> $b[0]); return [$achadas[0][1], $achadas[0][2]]; }
        if (str_contains($n, ' brasilia ')) return ['Brasília', 'DF'];
        if (preg_match('/([\p{Lu}][\p{L}]+(?:\s(?:d[aeo]s?\s)?[\p{Lu}][\p{L}]+){0,3})\s*[-–\/,]\s*('.implode('|', self::UFS).')\b/u', $t, $m)) {
            return [trim($m[1]), $m[2]];
        }
        return ['', ''];
    }

    /**
     * Nível pela soma dos períodos de experiência (sem contar duas vezes períodos sobrepostos).
     * Cargos operacionais/de apoio ficam no máximo em "pleno", mesmo com muitos anos.
     */
    private static function nivel(string $titulo, array $itensExp, string $texto): string {
        $nt = Competencias::normalizar($titulo);
        if (preg_match('/\b(senior|sr)\b/', $nt)) return 'senior';
        if (preg_match('/\bpleno\b/', $nt)) return 'pleno';
        if (preg_match('/\b(estagiari[oa]|estagio|jovem aprendiz|aprendiz|trainee)\b/', $nt)) return 'estagiario';

        $agora = (int)date('Y') * 12 + (int)date('n');
        $faixas = [];
        foreach ($itensExp as $x) if ($x['inicio'] > 0) $faixas[] = [$x['inicio'], min($agora, $x['fim'] ?? $agora)];
        usort($faixas, fn($a, $b) => $a[0] <=> $b[0]);
        $meses = 0; $ate = 0;
        foreach ($faixas as [$i, $f]) {
            $i = max($i, $ate);
            if ($f > $i) { $meses += $f - $i; $ate = $f; }
        }
        if (!$faixas && preg_match('/(\d{1,2})\s*anos? de experi[eê]ncia/iu', $texto, $m)) $meses = (int)$m[1] * 12;

        $recente = $itensExp[0]['cargo'] ?? '';
        $apoio = (bool)preg_match('/\b(auxiliar|assistente|atendente|ajudante|operador|operadora|repositor|repositora|recepcionista|servente|motorista|entregador|entregadora|balconista|conferente|estoquista|caixa|agente|porteiro|vigilante|zelador|zeladora|diarista|domestica|garcom|garconete|frentista)\b/', Competencias::normalizar($titulo.' '.$recente));
        if ($meses >= 96 && !$apoio) return 'senior';
        if ($meses >= 42) return 'pleno';
        if ($meses > 0) return 'junior';
        return $itensExp ? 'junior' : 'estagiario';
    }

    private static function cnh(string $t): string {
        if (preg_match('/\b(?:cnh|habilita[cç][aã]o|carteira de (?:habilita[cç][aã]o|motorista))\b[^\n]{0,20}?(?:categoria|cat\.?)?\s*[:\-–]?\s*\b([A-E]{1,2})\b(?![\p{L}])/iu', $t, $m)) {
            $c = strtoupper($m[1]);
            if (preg_match('/^(A|B|C|D|E|AB|AC|AD|AE)$/', $c)) return $c;
        }
        return '';
    }

    private static function pretensao(array $linhas): string {
        foreach ($linhas as $l) {
            if (preg_match('/R\$\s*\d[\d.]*(?:,\d{2})?(?:\s*(?:a|até|-|–)\s*R\$\s*\d[\d.]*(?:,\d{2})?)?/u', $l, $m)) return $m[0];
            if (preg_match('/\b(a combinar|negoci[aá]vel|conforme mercado)\b/iu', $l, $m)) return ucfirst(mb_strtolower($m[1]));
        }
        return '';
    }

    /** Disponibilidade: valores diretos (rótulo/seção) + frases sobre horário, viagem, mudança, remoto. */
    private static function disponibilidade(array $diretos, array $outros): string {
        $partes = [];
        foreach ($diretos as $l) { $l = self::semMarcador($l); if ($l !== '') $partes[] = $l; }
        foreach ($outros as $l) {
            $l = self::semMarcador($l);
            if ($l !== '' && preg_match('/\bdispon[ií]ve(?:l|is)|disponibilidade|mudan[cç]a de cidade|viagens|hor[aá]rio (?:integral|comercial|flex)|home office|trabalho (?:remoto|h[ií]brido)/iu', $l)) $partes[] = $l;
        }
        $t = trim(implode(' ', array_unique($partes)));
        $t = preg_replace('/^disponibilidade\s*:\s*/iu', '', $t) ?? $t;
        if ($t === '') return '';
        $t = mb_strtoupper(mb_substr($t, 0, 1)).mb_substr($t, 1);
        return mb_strlen($t) > 100 ? rtrim(mb_substr($t, 0, 99)).'…' : $t;
    }

    private static function pcd(array $rotulados, array $secao, array $linhas): string {
        foreach ($rotulados as $v) if ($v !== '') return self::limparPcd($v);
        $sec = [];
        foreach ($secao as $l) { $l = self::semMarcador($l); if ($l !== '' && !self::linhaDeDados($l)) $sec[] = $l; }
        if ($sec) return self::limparPcd(implode(' ', $sec));
        foreach ($linhas as $l) {
            if (mb_strlen($l) <= 200 && preg_match('/\b(pcd|pessoa com defici[eê]ncia|laudo m[eé]dico|defici[eê]ncia (?:auditiva|visual|f[ií]sica|intelectual|motora)|cid\s?[a-z]\d)/iu', $l)) return self::limparPcd(self::semMarcador($l));
        }
        return '';
    }

    private static function limparPcd(string $t): string {
        $t = trim(preg_replace('/^(pcd|pessoa com defici[eê]ncia)\s*[:\-–—]?\s*/iu', '', $t) ?? $t);
        return mb_strtoupper(mb_substr($t, 0, 1)).mb_substr($t, 1);
    }

    /**
     * Informações adicionais: PCD primeiro, depois a seção de outras informações — sem os dados que
     * já têm campo próprio (CNH, disponibilidade, pretensão) e sem dados sensíveis (estado civil, CPF, RG...).
     */
    private static function informacoes(string $pcd, array $outros, array $r): string {
        $out = $pcd !== '' ? ['PCD: '.$pcd] : [];
        foreach ($outros as $l) {
            $l = self::semMarcador($l);
            if ($l === '' || mb_strlen($l) > 300 || self::linhaDeDados($l)) continue;
            if (preg_match('/\b(estado civil|solteir[oa]|casad[oa]|divorciad[oa]|cpf|rg\b|nacionalidade|naturalidade)\b/iu', $l)) continue;
            if ($r['cnh'] !== '' && preg_match('/\b(cnh|habilita[cç][aã]o)\b/iu', $l)) continue;
            if ($r['disponibilidade'] !== '' && str_contains(Competencias::normalizar($r['disponibilidade']), Competencias::normalizar(mb_substr($l, 0, 40)))) continue;
            if ($r['pretensao_salarial'] !== '' && preg_match('/pretens|R\$/iu', $l)) continue;
            if ($pcd !== '' && str_contains(Competencias::normalizar($pcd), Competencias::normalizar(mb_substr($l, 0, 40)))) continue;
            $out[] = $l;
        }
        $vistos = []; $final = [];
        foreach ($out as $l) { $k = Competencias::normalizar($l); if ($k !== '' && !isset($vistos[$k])) { $vistos[$k] = 1; $final[] = $l; } }
        return implode("\n", $final);
    }

    /** "Inglês – Avançado", "Inglês: intermediário", "Inglês fluente" → "Inglês (avançado)". */
    private static function idiomas(string $t): string {
        $out = [];
        $niveis = 'b[aá]sico|intermedi[aá]rio|avan[cç]ado|fluente|nativo|t[eé]cnico|leitura|conversa[cç][aã]o|instrumental|pr[eé]-intermedi[aá]rio|iniciante|proficiente|[abc][12]';
        foreach (self::itens($t, false) as $item) {
            if (preg_match('/^([\p{L}]+(?:\s+\([^)]*\))?)\s*[-–—:(,]?\s*(?:n[ií]vel\s+)?('.$niveis.')\)?\s*$/iu', $item, $m)) {
                $out[] = self::capitalizar($m[1], false).' ('.mb_strtolower($m[2]).')';
            } else {
                $out[] = $item;
            }
        }
        return self::juntarLista($out);
    }

    private static function idiomasSoltos(string $t): string {
        $achados = [];
        if (preg_match_all('/\b(ingl[eê]s|espanhol|franc[eê]s|alem[aã]o|italiano|libras)\b\s*[-:(]?\s*(b[aá]sico|intermedi[aá]rio|avan[cç]ado|fluente|nativo|t[eé]cnico)?/iu', $t, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $k = mb_strtolower($m[1]);
                if (isset($achados[$k]) && empty($m[2])) continue;
                $achados[$k] = self::capitalizar($m[1], false).(!empty($m[2]) ? ' '.mb_strtolower($m[2]) : '');
            }
        }
        return implode("\n", $achados);
    }

    // ------------------------------------------------------------------ utilitários

    /** Junta linhas quebradas pelo layout do PDF: só mantém a quebra depois de ponto final. */
    private static function paragrafo(string $t): string {
        $t = preg_replace('/\n{2,}/u', "\n", trim($t)) ?? $t;
        return trim(preg_replace('/(?<![.!?:;])\n(?=\p{Ll}|\p{Lu}\p{Ll}|\d)/u', ' ', $t) ?? $t);
    }

    /**
     * Itens de uma lista: um por linha; com $virgulas, também separados por vírgula/";"/" • "
     * (vírgulas dentro de parênteses não separam). Primeira letra maiúscula.
     * @return string[]
     */
    private static function itens(string $bloco, bool $virgulas): array {
        if (trim($bloco) === '') return [];
        $rx = $virgulas ? '/\R|;|\s[•·|]\s|,(?![^(]*\))/u' : '/\R|;(?![^(]*\))/u';
        $out = [];
        foreach (preg_split($rx, $bloco) ?: [] as $p) {
            $p = trim_u(self::semMarcador($p), " \t.-–");
            if ($p === '' || mb_strlen($p) > 120 || self::linhaDeDados($p)) continue;
            $out[] = mb_strtoupper(mb_substr($p, 0, 1)).mb_substr($p, 1);
        }
        return $out;
    }

    /** Une listas sem repetir (comparação sem acento/caixa). */
    public static function juntarLista(array ...$listas): string {
        $vistos = []; $out = [];
        foreach ($listas as $lista) foreach ($lista as $item) {
            $item = trim((string)$item); $k = Competencias::normalizar($item);
            if ($item === '' || isset($vistos[$k])) continue;
            $vistos[$k] = true; $out[] = $item;
        }
        return implode(', ', $out);
    }

    private static function capitalizar(string $s, bool $nomeProprio = true): string {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
        // Só reescreve a caixa quando a linha está toda em maiúsculas ou minúsculas.
        if ($s !== mb_strtoupper($s) && $s !== mb_strtolower($s)) return $s;
        $s = mb_convert_case(mb_strtolower($s), MB_CASE_TITLE, 'UTF-8');
        $s = preg_replace_callback('/(?<=\s)(D[aeo]s?|E|Em|Com|Para|Na|No)\b/u', fn($m) => mb_strtolower($m[1]), $s) ?? $s;
        // Siglas conhecidas voltam para maiúsculas.
        $s = preg_replace_callback('/\b(Ti|Rh|Ux|Ui|Php|Sql|Df|Go|Pcd|Cnh|Ti\/Rh)\b/u', fn($m) => mb_strtoupper($m[1]), $s) ?? $s;
        return $nomeProprio ? $s : mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
    }
}
