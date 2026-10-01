<?php
declare(strict_types=1);

/**
 * Extração de vagas: transforma o texto de um anúncio (WhatsApp, Instagram, site) ou a
 * leitura de um CARTAZ (imagem, via OcrImagem) nos campos do cadastro de vaga.
 * O resultado só preenche o formulário — quem publica revisa antes de salvar.
 *
 * O que é extraído: título (cargo), empresa anunciante, local, salário (faixa), tipo de
 * contratação, nível, modelo (presencial/remoto/híbrido), descrição, requisitos, benefícios,
 * contato do anúncio (WhatsApp/telefone/e-mail), quantidade de vagas, competências e categoria.
 * Os "avisos" dizem o que a máquina não conseguiu confirmar e merece atenção na revisão.
 *
 * PADRÕES AUTOMÁTICOS: as regras abaixo são a base. Onde a regra não tem pista — linha solta sem palavra-chave,
 * área não reconhecida, empresa não achada —, decidem os padrões tirados das vagas já cadastradas (PadroesExtracao),
 * que se atualizam sozinhos a cada vaga salva. Cada decisão deles vai para $r['padroes'] e aparece no relatório da extração.
 */
final class ExtracaoVaga {
    private const SECOES = [
        'descricao' => ['descricao','descricao da vaga','atividades','atribuicoes','responsabilidades','principais atividades','sobre a vaga',
                        'o que voce vai fazer','funcoes','sua missao','detalhes da vaga','responsabilidades resumidas','atividades do dia a dia'],
        'requisitos' => ['requisitos','requisito','pre requisitos','requisitos e qualificacoes','exigencias','perfil','perfil desejado','qualificacoes',
                         'o que buscamos','o que esperamos','buscamos alguem que seja','necessario','desejavel','diferenciais','principais atitudes'],
        'beneficios' => ['beneficios','oferecemos','remuneracao','remuneracao e beneficios','salario e beneficios','salario beneficios','o que oferecemos',
                         'vantagens','nossos beneficios','composicao do ganho'],
        'local' => ['local','local de trabalho','localizacao','endereco','cidade','locais de atuacao','local da vaga','vagas para as regioes','localidade dos candidatos'],
        'horario' => ['horario','horarios','jornada','escala','horario de trabalho','carga horaria','jornada de trabalho','escala de trabalho'],
        'contato' => ['interessados','contato','enviar curriculo','envie seu curriculo','como se candidatar','candidatura','como participar','entre em contato','fale conosco'],
    ];

    /** Categoria de vaga sugerida a partir das competências encontradas (as do título valem mais). */
    private const CATEGORIAS = [
        'Tecnologia da Informação' => ['PHP','JavaScript','React','HTML e CSS','Banco de dados SQL','Python','Java','Git','Desenvolvimento de software','Suporte técnico de TI','Análise de dados'],
        'Saúde' => ['Enfermagem','Saúde e cuidados'],
        'Engenharia' => ['Elétrica','Hidráulica','Construção civil'],
        'Alimentação' => ['Cozinha e alimentação'],
        'Serviços Gerais e Limpeza' => ['Serviços domésticos e limpeza', 'Jardinagem e áreas verdes'],
        'Logística e Transporte' => ['Motorista e CNH','Logística e entregas','Estoque e almoxarifado'],
        'Atendimento ao Público' => ['Atendimento ao cliente','Operação de caixa','Telemarketing'],
        'Vendas' => ['Vendas','Negociação'],
        'Recursos Humanos' => ['Recursos Humanos'],
        'Financeiro' => ['Finanças','Contabilidade'],
        'Marketing' => ['Marketing digital','UX e design'],
        'Educação' => ['Educação'],
        'Administração' => ['Rotinas administrativas','Administração','Excel','Pacote Office'],
    ];

    /**
     * Cargo no começo do texto normalizado (palavra inteira, com plural/feminino):
     * "auxiliar de cozinha", "vendedora", "operador a de loja", "jovem aprendiz".
     */
    private const CARGO_INICIO = '/^(?:jovem\s+)?(?:auxiliar|aux|ajudante|assistente|atendente|analista|vendedor|garcom|garcons|garconete|cozinheir[ao]|chapeiro|'
        .'churrasqueiro|pizzaiolo|confeiteir[ao]|padeir[ao]|salgadeir[ao]|copeir[ao]|cumim|barista|bartender|balconista|operador|motorista|motoboy|entregador|'
        .'frentista|promotor|consultor|corretor|representante|sdr|trainee|estagio|estagios|estagiari[ao]|aprendiz|zelador|diarista|domestica|faxineir[ao]|'
        .'camareir[ao]|recepcionista|secretari[ao]|gerente|supervisor|coordenador|encarregad[ao]|lider|tecnic[ao]|enfermeir[ao]|nutricionista|farmaceutic[ao]|'
        .'estoquista|repositor|conferente|embalador|embaixador|eletricista|pedreiro|servente|mecanico|montador|soldador|pintor|porteiro|vigilante|cuidador|'
        .'professor|monitor|designer|desenvolvedor|programador|contador|advogad[ao]|costureir[ao]|marceneir[ao]|jardineir[ao]|lavador|manobrista|telefonista|'
        .'cobrador|caixa|agente|dp|rh|rocador|capinador|podador|serralheiro|carpinteiro|azulejista|gesseiro|armador|borracheiro|lanterneiro|operari[ao])(?:a|as|es|s)?\b/';

    /** Frases de chamada que não são o cargo. */
    private const GENERICOS = '/^(estamos( contratando)?|contratando|contrata(mos|-se)?|temos( uma)?( vagas?)?|vagas?( abertas?| disponive(l|is)| de emprego| para| aberta)?|'
        .'oportunidades?( de emprego| comercial)?|trabalhe conosco|faca parte( do nosso time| da nossa equipe| do time)?|junte se a nossa equipe|venha fazer parte.*|'
        .'inicio imediato|atencao|enviar curriculo|envie seu curriculo|requisitos|beneficios|salario|local|horario|contato|whatsapp|sua missao|nosso time|time|'
        .'nossa equipe|urgente|vem ai|novas vagas|processo seletivo|seu futuro.*|aqui.*|faca parte de quem.*|precisa se de|'
        // Rótulos de cartaz que não são informação (a informação vem ao lado ou embaixo deles).
        .'locais de trabalho( formato)?|local de trabalho|formato|escala|beneficios comuns|nossos beneficios|envie seu curriculo( pelo whatsapp)?|'
        .'junte se ao nosso time|(temos )?outras vagas( tambem)?|trabalho presencial|presencial|contratacao|requisitos da vaga)$/';

    /**
     * Dicionários de palavras-chave (texto já normalizado: sem acento, minúsculo): classificam qualquer linha do
     * anúncio mesmo sem título de seção — assim o cadastro herda tudo o que o cartaz diz.
     */
    private const PALAVRAS_BENEFICIO = '/r \d|\b(vt|vr|va|vale|vales|plano de saude|plano odontologico|odonto|odontologico|cesta basica|comissao|comissoes|bonus|bonificacao|'
        .'premiacao|premiacoes|premio|assiduidade|metas atingidas|day off|folga no aniversario|ajuda de custo|beneficios?|refeicao|alimentacao|seguro de vida|gympass|wellhub|'
        .'totalpass|total pass|plano de carreira|produtividade|gorjeta|gorjetas|plr|participacao nos lucros|convenio|desconto|descontos|auxilio|bolsa|salario|remuneracao|'
        .'transporte|telemedicina|academia)\b/';
    private const PALAVRAS_REQUISITO = '/\b(experiencia|experiente|primeiro emprego|sem experiencia|pouca experiencia|ideal para|cnh|curso|cursando|ensino|conhecimento|'
        .'conhecimentos|disponibilidade|residir|morar|morador|registro|coren|nr ?10|ter |possuir|saber|dominio|formacao|maior de|anos completos|idade|'
        .'comunicativ|proativ|organizad|responsavel|pontual|pontualidade|dinamic|boa comunicacao|vontade de aprender|pcd)\b/';
    private const PALAVRAS_HORARIO = '/\b(\d{1,2}\s*x\s*\d{1,2}|escala|horario|horarios|turno|turnos|segunda|terca|quarta|quinta|sexta|sabado|domingo|diurno|noturno|madrugada|'
        .'folga|folgas|jornada|carga horaria|\d{1,2}h(\d{2})?|a combinar|periodo integral|meio periodo)\b/';

    /** Frases de efeito do cartaz (slogan da empresa, chamadas): não são descrição, requisito nem benefício. */
    private const SLOGANS = '/\b(faz(?:em)? a diferenca|gera(?:m)? valor|solucoes e servicos|oportunidade para quem|quem faz a diferenca|seguranca em primeiro lugar|'
        .'em primeiro lugar|equipe comprometida|foco em resultados|respeito e valorizacao|valorizacao das pessoas|sua carreira comeca aqui|venha crescer com a gente|'
        .'o seu futuro comeca|juntos somos mais|aqui voce cresce|construa seu portfolio|case de sucesso)\b|^(oportunidade para|temos vaga|temos vagas)$/';

    /** Marcas e redes comuns nos anúncios do DF (reconhecidas pelo nome no texto). */
    private const MARCAS = ["McDonald's", 'Subway', 'Giraffas', 'Burger King', "Bob's", 'KFC', "Habib's", 'Outback', 'Coco Bambu', 'Madero', 'Mandaka',
        "Sam's Club", 'Atacadão', 'Assaí', 'Carrefour', 'Big Box', 'Super Adega', 'Dona de Casa', 'Tatico', 'Leroy Merlin', 'Renner', 'Riachuelo', 'C&A',
        'Americanas', 'Magazine Luiza', 'Casas Bahia', 'World Tennis', 'Centauro', 'O Boticário', 'Natura', 'Cacau Show', 'Kopenhagen', 'Drogasil',
        'Drogaria Rosário', 'Pague Menos', 'Vivo', 'Claro', 'Cascol', 'Ipiranga', 'Correios', 'Sama', 'WePink', 'Cinco Estrelas', 'Santa Therezinha',
        'Jovi', 'Zane Estágios', 'CIEE', 'Uaço', 'Indeniza', 'Santos Beneli', 'Casa do Vovô', 'Casa Gaúcha', 'Fênix Telecom', 'Don Romano', 'Concluinte'];

    /** Tipos de negócio que costumam vir antes/depois do nome da empresa ("Restaurante Prosa di Minas"). */
    private const NEGOCIOS = 'restaurante|panificadora|papelaria|farmacia|drogaria|hotel|grupo|casa de paes|churrascaria|pizzaria|cantina|supermercado|posto|padaria|lanchonete|corretora|agencia|confeitaria|hamburgueria';
    /** Ramo da empresa escrito sozinho abaixo do cargo ("SOCIAL MEDIA" / "ODONTOLOGIA"): fala do empregador, não do cargo. */
    private const RAMOS = 'odontologia|odontologica|odonto|clinica|consultorio|advocacia|advogados|contabilidade|imobiliaria|estetica|academia|colegio|faculdade|'
        .'hospital|laboratorio|otica|veterinaria|pet shop|petshop|construtora|seguradora|transportadora|escritorio';

    /** Pontos conhecidos → região administrativa (quando o cartaz cita só o shopping ou setor). */
    private const PONTOS = ['conjunto nacional' => ['Asa Norte', 'DF'], 'patio brasil' => ['Asa Sul', 'DF'], 'park shopping' => ['Guará', 'DF'],
        'taguatinga shopping' => ['Taguatinga', 'DF'], 'jk shopping' => ['Taguatinga', 'DF'], 'boulevard' => ['Asa Norte', 'DF'],
        'iguatemi' => ['Lago Norte', 'DF'], 'gilberto salomao' => ['Lago Sul', 'DF'], 'sia' => ['SIA', 'DF'], 'setor de industria e abastecimento' => ['SIA', 'DF'],
        'plano piloto' => ['Brasília', 'DF'], 'gama shopping' => ['Gama', 'DF'], 'nucleo bandeirante' => ['Núcleo Bandeirante', 'DF']];

    /**
     * Lê um cartaz (imagem) e extrai a vaga.
     * @param array{0:string,1:string,2:string,3:string}|null $leituras leituras feitas no navegador pelo leitor da
     *        plataforma (OcrImagem::leiturasDoNavegador); sem elas, lê com o Tesseract do servidor, se houver
     * @return array<string,mixed> campos de doTexto() + texto_ocr, confianca, ocr (false = sem leitor) e motor
     */
    public static function doImagem(string $path, ?array $leituras = null): array {
        if ($leituras !== null) { $ocr = OcrImagem::montar(...$leituras); $motor = 'navegador'; }
        elseif (OcrImagem::disponivel()) { $ocr = OcrImagem::ler($path); $motor = 'servidor'; }
        else return self::doTexto('') + ['texto_ocr' => '', 'confianca' => 0, 'ocr' => false, 'motor' => ''];
        $r = self::doTexto($ocr['texto'], $ocr);
        if ($ocr['texto'] === '') $r['avisos'][] = 'Não foi possível ler texto nesta imagem. Preencha o formulário manualmente.';
        elseif ($ocr['confianca'] < 75) $r['avisos'][] = 'A leitura da imagem teve pouca nitidez ('.$ocr['confianca'].'% de confiança): confira cada campo.';
        return $r + ['texto_ocr' => trim($ocr['texto']."\n".implode("\n", $ocr['complemento'])), 'confianca' => $ocr['confianca'], 'ocr' => true, 'motor' => $motor];
    }

    /**
     * @param array{destaques?:string[],complemento?:string[]} $ocr dados extras da leitura de imagem
     * @return array<string,mixed>
     */
    public static function doTexto(string $texto, array $ocr = []): array {
        self::$linhasDoTitulo = [];
        $texto = trim(str_replace(["\r\n", "\r"], "\n", $texto));
        $r = ['titulo'=>'','descricao'=>'','requisitos'=>'','beneficios'=>'','tipo_vaga'=>'clt','nivel_experiencia'=>'junior','remoto'=>'presencial',
              'cidade'=>'','uf'=>'','salario_minimo'=>null,'salario_maximo'=>null,'categoria'=>'','competencias'=>[],
              'anunciante'=>'','contato'=>'','quantidade'=>null,'cargos'=>[],'avisos'=>[],'padrao'=>[],'padroes'=>[]];
        // OCR parte palavras em letra grande ("MÁQUI NA"): se a junção aparece inteira em outra leitura ("MAQUINA"), junta.
        $vocabulario = self::vocabularioOcr([$texto, ...($ocr['complemento'] ?? []), ...($ocr['destaques'] ?? []), ...($ocr['todas'] ?? [])]);
        $consertar = fn(string $l) => self::juntarPartidas(self::limparLinha($l), $vocabulario);
        $destaques = array_values(array_filter(array_map($consertar, $ocr['destaques'] ?? [])));
        $complemento = array_values(array_filter(array_map($consertar, $ocr['complemento'] ?? [])));
        if ($texto === '' && !$destaques) return ['padrao' => ['tipo_vaga', 'nivel_experiencia', 'remoto']] + $r;

        $todas = array_values(array_filter(array_map($consertar, explode("\n", $texto))));
        // Slogan e restos de logotipo ("REQ(.ÓOM") não entram em campo nenhum.
        $todas = array_values(array_filter($todas, fn($l) => !self::ehSlogan($l) && !self::ehLixoOcr($l)));
        $complemento = array_values(array_filter($complemento, fn($l) => !self::ehSlogan($l) && !self::ehLixoOcr($l)));
        // HERANÇA do cartaz: o que as outras leituras acharam fora da ordem (letra clara sobre fundo escuro, textos
        // soltos) também entra na classificação por palavras-chave — mas só as linhas que dizem algo (sem ruído de ícone).
        $vistas = array_flip(array_map([Competencias::class, 'normalizar'], $todas));
        foreach ($complemento as $linhaOcr) {
            // Duas colunas do cartaz lidas na mesma linha, separadas por um ícone que o OCR vira símbolo
            // ("VT (DF ou GO) © Plano de Saúde"): cada parte é uma informação.
            foreach (preg_split('/\s*[©®ª¢¥§¶•|]\s*/u', $linhaOcr) ?: [] as $l) {
                $l = self::limparLinha($l);
                $k = Competencias::normalizar($l);
                if ($k !== '' && !isset($vistas[$k]) && self::linhaUtil($l)) { $todas[] = $l; $vistas[$k] = true; }
            }
        }
        $tudo = $texto."\n".implode("\n", $complemento);
        $n = Competencias::normalizar($tudo);

        // Contato do anúncio (antes de descartar as linhas de "envie seu currículo").
        $r['contato'] = self::contato($tudo);
        $linhas = array_values(array_filter($todas, fn($l) => !self::ehLinhaContato($l)));
        [$soltas, $secoes] = self::separar($linhas);

        // Cargo(s) e título.
        $emSecao = array_fill_keys(array_map([Competencias::class, 'normalizar'], [...($secoes['requisitos'] ?? []), ...($secoes['beneficios'] ?? [])]), true);
        $r['anunciante'] = self::anunciante($tudo, $linhas, $destaques, $ocr['todas'] ?? [], $emSecao, $texto."\n".implode("\n", $ocr['complemento'] ?? []));
        if ($r['anunciante'] === '') {
            // Nenhuma regra achou a empresa: talvez seja uma que já está cadastrada no sistema (padrões automáticos).
            $r['anunciante'] = PadroesExtracao::nomeConhecido('empresa', $tudo);
            if ($r['anunciante'] !== '') $r['padroes'][] = ['campo' => 'anunciante', 'texto' => $r['anunciante'], 'regra' => '', 'para' => $r['anunciante'], 'termo' => 'empresa já cadastrada'];
        }
        // Cargos: das linhas e também das letras grandes do cartaz (o título decorado nem sempre entra no texto corrido).
        $r['cargos'] = self::cargos([...$linhas, ...array_slice($destaques, 0, 4)]);
        $r['titulo'] = self::titulo($linhas, $destaques, $complemento, $r['cargos'], $r['anunciante']);
        // Cargos em letra grande viram o título; os demais do cartaz ("Temos outras vagas também!") vão para a descrição.
        $noTitulo = array_map([Competencias::class, 'normalizar'], preg_split('/\s*\/\s*/u', $r['titulo']) ?: []);
        $outras = array_values(array_filter($r['cargos'], fn($c) => !in_array(Competencias::normalizar($c), $noTitulo, true)));
        if (count($r['cargos']) >= 6) $r['avisos'][] = 'O cartaz lista '.count($r['cargos']).' cargos diferentes: confira se é uma vaga só ou uma lista de vagas de vários anunciantes.';
        [$r['salario_minimo'], $r['salario_maximo']] = self::salario($tudo);
        $r['quantidade'] = self::quantidade($n);

        // '' = o anúncio não diz; aí entra o valor padrão e o campo é marcado como "padrão" no relatório.
        $r['tipo_vaga'] = match (true) {
            (bool)preg_match('/\b(estagio|estagiario|estagiaria|estagiarios|bolsa auxilio)\b/', $n) => 'estagio',
            (bool)preg_match('/\b(pj|pessoa juridica|prestador de servico|mei)\b/', $n) => 'pj',
            (bool)preg_match('/\b(temporario|temporaria|freelancer|freela|por contrato|dias de contrato|contrato de \d+ dias|acao temporaria)\b/', $n) => 'temporario',
            (bool)preg_match('/\b(clt|carteira assinada|registro em carteira|efetivo)\b/', $n) => 'clt',
            default => '',
        };
        $r['nivel_experiencia'] = match (true) {
            (bool)preg_match('/\b(senior|sr)\b/', $n) => 'senior',
            (bool)preg_match('/\bpleno\b/', $n) => 'pleno',
            (bool)preg_match('/\b(estagio|estagiario|estagiaria|estagiarios|jovem aprendiz|aprendiz)\b/', $n) => 'estagiario',
            (bool)preg_match('/\b(junior|jr|primeiro emprego|sem experiencia)\b/', $n) => 'junior',
            default => '',
        };
        $r['remoto'] = match (true) {
            (bool)preg_match('/\b(hibrido|hibrida)\b/', $n) => 'hibrido',
            (bool)preg_match('/\b(remoto|remota|home office|100 online|teletrabalho)\b/', $n) => 'remoto',
            (bool)preg_match('/\b(presencial|no local)\b/', $n) => 'presencial',
            default => '',
        };
        foreach (['tipo_vaga' => 'clt', 'nivel_experiencia' => 'junior', 'remoto' => 'presencial'] as $campo => $padrao) {
            if ($r[$campo] === '') { $r[$campo] = $padrao; $r['padrao'][] = $campo; }
        }
        [$r['cidade'], $r['uf']] = self::local($tudo."\n".implode("\n", $destaques), $secoes['local'] ?? []);
        if ($r['cidade'] === '' && $r['remoto'] !== 'remoto') { $r['cidade'] = 'Brasília'; $r['uf'] = 'DF'; $r['padrao'][] = 'cidade'; }

        // Linhas fora de seção: benefícios têm R$/VT/VR; requisitos têm "experiência", "CNH", "curso"...
        $desc = $secoes['descricao'] ?? []; $req = $secoes['requisitos'] ?? []; $ben = $secoes['beneficios'] ?? [];
        $ignorar = array_map([Competencias::class, 'normalizar'], array_filter([$r['titulo'], $r['anunciante'], ...$r['cargos'], ...self::$linhasDoTitulo]));
        foreach ($soltas as $l) {
            $ln = Competencias::normalizar($l);
            $lt = Competencias::normalizar(self::limparTitulo($l));
            if ($lt === '' || in_array($ln, $ignorar, true) || in_array($lt, $ignorar, true) || preg_match(self::GENERICOS, $lt) || !self::temPalavras($l)) continue;
            if (self::ehLocalSolto($ln)) continue; // já vai para o campo cidade
            if (self::soRotulos($ln)) continue;    // "ESCALA LOCAIS DE TRABALHO FORMATO", "Temos outras vagas também!"
            // Palpite da regra pelas palavras-chave; o horário fica na descrição.
            $semPista = false;
            if (preg_match(self::PALAVRAS_BENEFICIO, $ln)) $regra = 'beneficios';
            elseif (preg_match(self::PALAVRAS_REQUISITO, $ln)) $regra = 'requisitos';
            elseif (preg_match(self::PALAVRAS_HORARIO, $ln)) $regra = 'descricao';
            elseif (self::ehCargo(self::limparTitulo($l)) && mb_strlen($l) <= 42) continue; // cargo solto: já está no título/"outras vagas"
            else { $regra = 'descricao'; $semPista = true; }
            // Sem palavra-chave na regra: decide o padrão das vagas cadastradas (se houver).
            $d = $semPista ? PadroesExtracao::decidir('vaga_linha', $l, $regra) : ['classe' => $regra, 'origem' => 'regra', 'termo' => ''];
            if ($d['origem'] === 'padrao') $r['padroes'][] = ['campo' => 'linha', 'texto' => $l, 'regra' => $regra, 'para' => $d['classe'], 'termo' => $d['termo']];
            match ($d['classe']) { 'beneficios' => $ben[] = $l, 'requisitos' => $req[] = $l, default => $desc[] = $l };
        }
        foreach (['horario' => 'Horário', 'local' => 'Local'] as $extra => $rot) {
            if (empty($secoes[$extra])) continue;
            // "Local: BRASÍLIA-DF" só repete o campo cidade: fica de fora. Endereço ("Shopping X, loja 12") entra.
            if ($extra === 'local' && self::ehLocalSolto(Competencias::normalizar(implode(' ', $secoes[$extra])))) continue;
            $desc[] = $rot.': '.implode(' ', $secoes[$extra]);
        }
        if (!isset($secoes['horario']) && preg_match('/\b(\d{1,2})\s*x\s*(\d{1,2})\b/', $n, $m) && in_array($m[1].'x'.$m[2], ['6x1','5x2','12x36','4x2','5x1','6x2'], true)
            && !preg_match('/\b'.$m[1].'\s*x\s*'.$m[2].'\b/', Competencias::normalizar(implode(' ', $desc)))) $desc[] = 'Escala '.$m[1].'x'.$m[2];
        if ($outras && $r['titulo'] !== '' && count($outras) < count($r['cargos'])) $desc[] = 'Outras vagas no anúncio: '.implode(', ', $outras).'.';
        elseif (count($r['cargos']) >= 2) $desc[] = 'Cargos: '.implode(', ', $r['cargos']).'.';
        if ($r['quantidade']) $desc[] = 'Quantidade de vagas: '.$r['quantidade'];
        // Frase de abertura montada com o que foi lido ("Grupo Dourado contrata Auxiliar de Cozinha em Águas Claras."),
        // como nas vagas da curadoria — só quando a descrição ainda não apresenta a vaga.
        $abertura = self::abertura($r);
        if ($abertura !== '' && !str_contains(Competencias::normalizar(implode(' ', array_slice($desc, 0, 1))), Competencias::normalizar($r['titulo']))) array_unshift($desc, $abertura);
        $r['descricao'] = self::juntar($desc);
        $r['requisitos'] = self::juntar($req);
        $r['beneficios'] = self::juntar(self::casarValores($ben));

        $r['competencias'] = Competencias::daVaga($r);
        $regraCategoria = self::categoria($r['competencias'], Competencias::extrair($r['titulo']));
        // Área que a regra não reconheceu: decide o padrão dos títulos das vagas cadastradas.
        $d = $regraCategoria === '' ? PadroesExtracao::decidir('vaga_categoria', $r['titulo'], '') : ['classe' => $regraCategoria, 'origem' => 'regra', 'termo' => ''];
        $r['categoria'] = $d['classe'];
        if ($d['origem'] === 'padrao') $r['padroes'][] = ['campo' => 'categoria', 'texto' => $r['titulo'], 'regra' => $regraCategoria, 'para' => $d['classe'], 'termo' => $d['termo']];

        if ($r['titulo'] === '') $r['avisos'][] = 'Não encontramos o cargo: preencha o título.';
        if ($r['salario_minimo'] === null) $r['avisos'][] = 'Salário não informado no anúncio (ficará "A combinar").';
        if ($r['anunciante'] === '' && ($ocr['destaques'] ?? null) !== null) $r['avisos'][] = 'Empresa anunciante não identificada: confira no cartaz.';
        return $r;
    }

    /**
     * Relatório campo a campo da extração (no mesmo espírito do relatório do currículo):
     * 'lido' = veio do anúncio; 'padrao' = o anúncio não diz e ficou o valor padrão; 'falta' = não encontrado.
     * @return array{itens:array<int,array{campo:string,rotulo:string,valor:string,status:string}>,lidos:int,padrao:int,faltando:int}
     */
    public static function relatorio(array $r, string $categoriaNome = ''): array {
        $salario = $r['salario_minimo'] !== null ? salario_texto($r['salario_minimo'], $r['salario_maximo']) : '';
        $campos = [
            'titulo' => ['Cargo (título)', $r['titulo']],
            'anunciante' => ['Empresa anunciante', $r['anunciante']],
            'salario' => ['Salário', $salario],
            'cidade' => ['Local', trim($r['cidade'].($r['uf'] !== '' ? '/'.$r['uf'] : ''), '/')],
            'tipo_vaga' => ['Contratação', rotulo($r['tipo_vaga'])],
            'nivel_experiencia' => ['Nível', rotulo($r['nivel_experiencia'])],
            'remoto' => ['Modelo de trabalho', rotulo($r['remoto'])],
            'categoria' => ['Área (categoria)', $categoriaNome !== '' ? $categoriaNome : $r['categoria']],
            'descricao' => ['Descrição / atividades', self::resumoCampo($r['descricao'])],
            'requisitos' => ['Requisitos', self::resumoCampo($r['requisitos'])],
            'beneficios' => ['Benefícios', self::resumoCampo($r['beneficios'])],
            'contato' => ['Contato do anúncio', $r['contato']],
            'quantidade' => ['Quantidade de vagas', $r['quantidade'] ? (string)$r['quantidade'] : ''],
            'competencias' => ['Competências (match)', implode(', ', $r['competencias'])],
        ];
        $itens = []; $cont = ['lido' => 0, 'padrao' => 0, 'falta' => 0];
        foreach ($campos as $campo => [$rot, $valor]) {
            $st = in_array($campo, $r['padrao'] ?? [], true) ? 'padrao' : ($valor !== '' ? 'lido' : 'falta');
            // Categoria sugerida que não existe no cadastro não é aplicada ao formulário.
            if ($campo === 'categoria' && $valor !== '' && $categoriaNome === '') { $st = 'falta'; $valor = $r['categoria'].' (sugerida — não cadastrada)'; }
            $cont[$st]++;
            $itens[] = ['campo' => $campo, 'rotulo' => $rot, 'valor' => (string)$valor, 'status' => $st];
        }
        return ['itens' => $itens, 'lidos' => $cont['lido'], 'padrao' => $cont['padrao'], 'faltando' => $cont['falta']];
    }

    /** Primeira linha + "(+N linhas)" para caber no relatório. */
    private static function resumoCampo(string $t): string {
        $linhas = array_values(array_filter(explode("\n", $t), fn($l) => trim($l) !== ''));
        if (!$linhas) return '';
        $p = mb_strimwidth($linhas[0], 0, 110, '…');
        return count($linhas) > 1 ? $p.' (+'.(count($linhas) - 1).' '.(count($linhas) === 2 ? 'linha' : 'linhas').')' : $p;
    }

    /** "Grupo Dourado contrata Auxiliar de Cozinha em Águas Claras." / "Vaga de Motorista em Ceilândia." */
    private static function abertura(array $r): string {
        $t = trim($r['titulo']);
        if ($t === '' || str_starts_with($t, 'Vagas abertas')) return '';
        $local = $r['remoto'] === 'remoto' ? ' (trabalho remoto)' : ($r['cidade'] !== '' && !in_array('cidade', $r['padrao'] ?? [], true) ? ' em '.$r['cidade'] : '');
        return ($r['anunciante'] !== '' ? $r['anunciante'].' contrata '.$t : 'Vaga de '.$t).$local.'.';
    }

    /** Categoria pelas competências; as competências do título contam em dobro. */
    public static function categoria(array $competencias, array $doTitulo = []): string {
        $melhor = ''; $max = 0;
        foreach (self::CATEGORIAS as $cat => $lista) {
            $q = count(array_intersect($competencias, $lista)) + 2 * count(array_intersect($doTitulo, $lista));
            if ($q > $max) { $max = $q; $melhor = $cat; }
        }
        return $melhor;
    }

    // ------------------------------------------------------------------ linhas e seções

    /** Tira emojis, marcadores e restos de ícones que o OCR lê como letras soltas nas pontas. */
    private static function limparLinha(string $l): string {
        $l = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}\x{2B50}\x{2705}\x{274C}\x{E000}-\x{F8FF}]/u', '', $l) ?? $l;
        $l = str_replace(['“', '”', '«', '»', '‘', '’'], ['', '', '', '', '', "'"], $l);
        $l = preg_replace('/(?<!\d)[ªº]|[©®¢¥§¶£]/u', ' ', $l) ?? $l; // ícones lidos como "ª", "©"… ("3º" ordinal fica)
        // Pedaços de ícone no começo: "(O) ", "Q ", "e ", "4) ", "] ", "O) " ...
        for ($i = 0; $i < 3; $i++) {
            $l = preg_replace('/^\s*(?:[\(\[]?[\p{L}\d]{1,2}[\)\]]|[\p{L}](?=\s+\p{Lu})|[^\p{L}\d\s(+$]+)\s*/u', '', $l, 1, $c) ?? $l;
            if (!$c) break;
        }
        // OCR troca o "por" de valores: "R$ 48,00 ror DIA", "R$ 48,00 rPor" → "R$ 48,00 por dia".
        $l = preg_replace_callback('/(R\$\s*[\d.,]+)\s*r?\s*[pP]?[oO0][rR]\b\.?\s*(dia|m[eê]s|hora|semana)?/iu',
            fn($m) => $m[1].' por'.(($m[2] ?? '') !== '' ? ' '.mb_strtolower($m[2]) : ''), $l) ?? $l;
        $l = trim_u($l, " \t*_•·-–—>|=:;,~\"'`´^");
        $l = preg_replace('/\s+[|=\]\[~]+(\s+|$)/u', ' ', $l) ?? $l;
        return trim(preg_replace('/\s{2,}/u', ' ', $l) ?? $l);
    }

    /**
     * Linha das leituras extras (esparsa/negativo) que vale herdar: tem palavra-chave de vaga (benefício, requisito,
     * horário, cargo) ou é texto de verdade (2+ palavras de 4+ letras, quase só letras) — descarta restos de ícone e foto.
     */
    private static function linhaUtil(string $l): bool {
        $semEspaco = preg_replace('/\s+/u', '', $l) ?? '';
        if ($semEspaco === '' || preg_match('/[£“”º]/u', $l)) return false;
        if (preg_match_all('/[\p{L}\d]/u', $semEspaco) / mb_strlen($semEspaco) < 0.8) return false;
        $n = Competencias::normalizar($l);
        if (preg_match(self::PALAVRAS_BENEFICIO, $n) || preg_match(self::PALAVRAS_REQUISITO, $n) || preg_match(self::PALAVRAS_HORARIO, $n) || self::ehCargo(self::limparTitulo($l))) {
            return self::temPalavras($l);
        }
        return preg_match_all('/\b\p{L}{4,}\b/u', $l) >= 2;
    }

    /** Linha feita só de rótulos do cartaz (sem informação própria) ou do aviso "temos outras vagas também". */
    private static function soRotulos(string $ln): bool {
        if (preg_match('/\b(temos|outras|oulras|outra)\b.{0,12}\bvagas\b|\bvagas tambem\b/', $ln)) return true;
        $resto = preg_replace('/\b(escala|locais|local|de|do|da|trabalho|formato|horario|beneficios|comuns|salario|contratacao|modelo|requisitos|cargo|vaga|vagas|'
            .'modalidade|remuneracao|atividades|atribuicoes|responsabilidades|funcoes|candidatura|inscricao|inscricoes)\b/', '', $ln) ?? $ln;
        return trim($resto) === '';
    }

    /** Ponto conhecido do DF escrito sozinho ("CONJUNTO NACIONAL", "Park Shopping"): é onde fica a vaga, não a empresa. */
    private static function ehPonto(string $ln): bool {
        $ln = trim(preg_replace('/^shopping\s+|\s+shopping$/', '', $ln) ?? $ln);
        return isset(self::PONTOS[$ln]) || isset(self::PONTOS[$ln.' shopping']);
    }

    private static function temPalavras(string $l): bool {
        if (preg_match('/^\W*(VT|VR|VA|PLR)\b/u', $l)) return true; // benefício em sigla: "VT (DF ou GO)", "VR", "PLR"
        return preg_match_all('/[\p{L}]{3,}/u', $l) >= 1 && preg_match_all('/\p{L}/u', $l) >= 4;
    }

    private static function ehLinhaContato(string $l): bool {
        $n = Competencias::normalizar($l);
        if (preg_match('/@|\b\d{4,5}[\s.-]?\d{4}\b/', $l) && !preg_match('/r\$\s*\d/i', $l)) return true;
        return (bool)preg_match('/\b(whatsapp|whats|zap|wa)\b|\b(envie|enviar|mande|mandar|entregar)\b.*\b(curriculo|cv)\b|\binteressad[oa]s?\b|\b(assunto|informe|e mail|email)\b|\bfale (conosco|com)\b|\bentre em contato\b|\blink na bio\b|\bacesse\b/', $n);
    }

    private static function ehLocalSolto(string $ln): bool {
        return (bool)preg_match('/^(local|regioes|localizacao)\b/', $ln) || (bool)preg_match('/^(asa (sul|norte)|aguas claras|taguatinga( sul| norte)?|ceilandia( norte| sul)?|guara( i+| 1| 2)?|samambaia|brasilia|gama|sia|vicente pires|cruzeiro( novo| velho)?|lago (sul|norte)|sobradinho|planaltina|recanto das emas|riacho fundo|santa maria|sao sebastiao|paranoa|itapoa|nucleo bandeirante)( df)?$/', $ln);
    }

    private static function separar(array $linhas): array {
        $soltas = []; $sec = []; $atual = null;
        foreach ($linhas as $l) {
            $partes = preg_split('/\s*:\s*/u', $l, 2) ?: [$l];
            $cab = Competencias::normalizar($partes[0]);
            $achou = null;
            if (mb_strlen($partes[0]) <= 45) {
                foreach (self::SECOES as $k => $titulos) if (in_array($cab, $titulos, true)) { $achou = $k; break; }
            }
            if ($achou) { $atual = $achou; if (trim($partes[1] ?? '') !== '') $sec[$atual][] = trim($partes[1]); continue; }
            // Horário e local são seções curtas: a linha seguinte só entra nelas se for continuação
            // ("Segunda a sábado", "Shopping X"); senão ("Salário: R$ ... + VT") volta a ser classificada.
            if (in_array($atual, ['horario', 'local'], true) && !self::continuaSecao($atual, $l)) $atual = null;
            // Linha de dinheiro/benefício logo depois dos requisitos ("Bolsa-auxílio de R$ 750 + VT") não é requisito.
            if ($atual === 'requisitos' && preg_match('/r \d|\b(salario|bolsa|vt|vr|va|vale|beneficios?|remuneracao)\b/', Competencias::normalizar($l))) $atual = null;
            if ($atual === null) $soltas[] = $l; else $sec[$atual][] = $l;
        }
        return [$soltas, $sec];
    }

    private static function continuaSecao(string $secao, string $l): bool {
        $n = Competencias::normalizar($l);
        if (preg_match('/r \d|\b(salario|beneficios?|requisitos?|vt|vr|va|vale)\b/', $n)) return false;
        return $secao === 'horario'
            ? (bool)preg_match('/\d{1,2}\s*h\b|\d{1,2}h\d{2}|\b\d{1,2}\s*x\s*\d{1,2}\b|\b(segunda|terca|quarta|quinta|sexta|sabado|domingo|escala|turno|folga|folgas|feriados?|diurno|noturno|madrugada|integral)\b/', $n)
            : self::ehLocalSolto($n) || (bool)preg_match('/\b(shopping|setor|quadra|qd|conjunto|lote|loja|bloco|rua|avenida|av|sala|df|go)\b/', $n);
    }

    private static function juntar(array $linhas): string {
        $out = []; $vistos = [];
        foreach ($linhas as $l) {
            $l = trim($l);
            $k = Competencias::normalizar($l);
            if ($k === '' || isset($vistos[$k]) || !self::temPalavras($l)) continue;
            // A mesma frase lida duas vezes pelo OCR ("Primeiro emprego" / "Tdeal Primeiro emprego",
            // "ou pouca experiência" / "ou pOUC a ex eriência"): fica só a primeira.
            $c = str_replace(' ', '', $k);
            foreach (array_keys($vistos) as $v) {
                $cv = str_replace(' ', '', (string)$v);
                similar_text($c, $cv, $pct);
                if ($pct >= 78 || (strlen($c) >= 8 && str_contains($cv, $c))) continue 2; // quase igual, ou pedaço de uma linha já guardada
            }
            $vistos[$k] = true;
            $out[] = $l;
        }
        return implode("\n", $out);
    }

    // ------------------------------------------------------------------ calibragem do OCR de cartaz

    /** Linhas do cartaz que foram juntadas no título (não voltam para a descrição). */
    private static array $linhasDoTitulo = [];

    /** Palavras (normalizadas, 4+ letras) que aparecem inteiras em alguma das leituras do OCR. */
    private static function vocabularioOcr(array $textos): array {
        $v = [];
        foreach ($textos as $t) {
            $ws = preg_split('/\s+/u', Competencias::normalizar((string)$t)) ?: [];
            foreach ($ws as $i => $w) {
                if (strlen($w) >= 4) $v[$w] = true;
                // Par "palavra + de/da/do/em" lido separado em alguma leitura: "+consultorde" → corta depois de 9 letras.
                if (strlen($w) >= 4 && in_array($ws[$i + 1] ?? '', ['de', 'da', 'do', 'das', 'dos', 'em'], true)) $v['+'.$w.$ws[$i + 1]] = strlen($w);
            }
        }
        return $v;
    }

    /** "MÁQUI NA" → "MÁQUINA" quando "maquina" aparece inteira em outra leitura (pedaço com até 2 letras). */
    private static function juntarPartidas(string $l, array $vocabulario): string {
        $p = preg_split('/\s+/u', $l) ?: [];
        // O contrário também acontece: "CONSULTORDE" numa leitura e "CONSULTOR DE" em outra → separa.
        foreach ($p as $i => $w) {
            $n = Competencias::normalizar($w);
            $k = $vocabulario['+'.$n] ?? null;
            if (is_int($k) && preg_match('/^\p{L}+$/u', $w) && mb_strlen($w) === strlen($n)) $p[$i] = mb_substr($w, 0, $k).' '.mb_substr($w, $k);
        }
        $p = preg_split('/\s+/u', implode(' ', $p)) ?: [];
        for ($i = 0; $i + 1 < count($p); $i++) {
            if (mb_strlen($p[$i]) > 2 && mb_strlen($p[$i + 1]) > 2) continue;
            $junto = Competencias::normalizar($p[$i].$p[$i + 1]);
            if (strlen($junto) >= 5 && isset($vocabulario[$junto]) && !in_array(Competencias::normalizar($p[$i + 1]), ['de', 'da', 'do', 'e', 'em'], true)) {
                array_splice($p, $i, 2, [$p[$i].$p[$i + 1]]);
                $i--;
            }
        }
        return implode(' ', $p);
    }

    /** Slogan / frase de efeito (ver SLOGANS). */
    private static function ehSlogan(string $l): bool {
        return (bool)preg_match(self::SLOGANS, Competencias::normalizar($l));
    }

    /** Resto de logotipo lido como texto: palavra curta com símbolo entre letras ("REQ(.ÓOM", "RE9(COM"). */
    private static function ehLixoOcr(string $l): bool {
        return str_word_count(Competencias::normalizar($l)) <= 2 && (bool)preg_match('/\p{L}[().,;:\[\]{}]+\p{L}/u', $l) && !preg_match('/\p{L}\.\p{L}\.|https?:|www\.|[\w@]\.(?:com|net|org|br)\b/iu', $l);
    }

    /**
     * Cargo escrito em várias linhas de letra grande: a linha do cargo + as de baixo, enquanto forem curtas, em
     * MAIÚSCULAS, sem número e sem ser rótulo ("LOCAL DE TRABALHO:"), local, benefício ou chamada. Até 8 palavras.
     */
    private static function tituloEmLinhas(array $linhas): string {
        self::$linhasDoTitulo = [];
        foreach (array_slice($linhas, 0, 12) as $i => $l) {
            $t = self::limparTitulo($l);
            if ($t === '' || !self::ehCargo($t) || $l !== mb_strtoupper($l) || str_word_count(Competencias::normalizar($t)) > 4) continue;
            $partes = [$t];
            for ($j = $i + 1; $j < min(count($linhas), $i + 5); $j++) {
                $prox = $linhas[$j];
                $pn = Competencias::normalizar($prox);
                if ($prox !== mb_strtoupper($prox) || str_contains($prox, ':') || preg_match('/\d/', $prox) || str_word_count($pn) > 3 || !preg_match('/\p{L}{3,}/u', $prox)
                    || preg_match(self::GENERICOS, $pn) || self::ehLocalSolto($pn) || self::ehPonto($pn) || self::soRotulos($pn) || self::ehSlogan($prox) || self::ehCargo(self::limparTitulo($prox))
                    || preg_match(self::PALAVRAS_BENEFICIO, $pn) || preg_match(self::PALAVRAS_REQUISITO, $pn) || preg_match(self::PALAVRAS_HORARIO, $pn)) break;
                // Ramo da empresa sozinho na linha ("ODONTOLOGIA") não continua o cargo — a não ser depois de "de/em" ("AUXILIAR DE" / "CLÍNICA").
                if (preg_match('/^(?:'.self::NEGOCIOS.'|'.self::RAMOS.')$/', $pn) && !preg_match('/\b(de|da|do|das|dos|em|e)$/', Competencias::normalizar(end($partes)))) break;
                $partes[] = $prox;
            }
            if (count($partes) < 2) continue;
            $titulo = self::limparTitulo(implode(' ', $partes));
            if (str_word_count(Competencias::normalizar($titulo)) > 8) continue;
            self::$linhasDoTitulo = array_slice($partes, 1);
            return self::maiuscula($titulo);
        }
        return '';
    }

    /**
     * Valor solto do cartaz junto do benefício dele: "VALE REFEIÇÃO" + "R$ 48,00 por dia" → "Vale refeição: R$ 48,00 por dia".
     * Só quando dá para ter certeza: valor por dia vai para o vale refeição/alimentação que ainda não tem valor.
     */
    private static function casarValores(array $ben): array {
        foreach ($ben as $i => $v) {
            if (!preg_match('/^R\$\s*[\d.,]+\s+por dia$/iu', trim($v))) continue;
            foreach ($ben as $j => $rotulo) {
                if ($j === $i || str_contains($rotulo, 'R$') || !preg_match('/\b(vale refeicao|vale alimentacao|vr|va)\b/', Competencias::normalizar($rotulo))) continue;
                $ben[$j] = self::caixa($rotulo).': '.trim($v);
                unset($ben[$i]);
                // Outras leituras do mesmo valor soltas ("R$ 48,00 por", "R$ 48,00") saem junto.
                preg_match('/R\$\s*([\d.,]+)/u', $v, $mv);
                foreach ($ben as $k => $outro) if ($k !== $j && preg_match('/^R\$\s*'.preg_quote($mv[1] ?? '', '/').'(?:\s+por)?$/iu', trim($outro))) unset($ben[$k]);
                break;
            }
        }
        return array_values($ben);
    }

    // ------------------------------------------------------------------ título e cargos

    private static function ehCargo(string $t): bool {
        $n = Competencias::normalizar($t);
        return $n !== '' && (bool)preg_match(self::CARGO_INICIO, $n) && !preg_match(self::GENERICOS, $n);
    }

    /** Tira o lixo antes do cargo ("Su? AUXILIAR…", "VAGAS DISPONÍVEIS: 62 Copeira"): até 3 palavras curtas/sujas. */
    private static function cortarAteCargo(string $t): string {
        $pal = preg_split('/\s+/u', trim($t)) ?: [];
        for ($i = 1; $i <= 3 && $i < count($pal); $i++) {
            $sujo = fn($w) => mb_strlen($w) <= 3 || preg_match('/[^\p{L}]/u', $w) || preg_match('/^(vagas?|disponiveis|abertas?|para|contrata)$/', Competencias::normalizar($w));
            if (!array_filter(array_slice($pal, 0, $i), fn($w) => !$sujo($w)) && preg_match(self::CARGO_INICIO, Competencias::normalizar(implode(' ', array_slice($pal, $i))))) {
                return implode(' ', array_slice($pal, $i));
            }
        }
        return $t;
    }

    /** Linhas curtas que são só um cargo (listas "VAGAS: > Garçom > Operador de caixa"). */
    private static function cargos(array $linhas): array {
        $out = [];
        foreach ($linhas as $l) {
            $t = self::limparTitulo($l);
            $n = Competencias::normalizar($t);
            if ($n === '' || mb_strlen($t) > 42 || str_word_count($n) > 5 || preg_match('/[,;]$/', $t) || preg_match('/\b(de|da|do|em|e|para)$/', $n)
                || preg_match('/\d{3}|r \d|\b(experiencia|conhecimento|curso|ensino|com|para|nas|sua|seu|voce|nosso|nossa)\b/', $n) || !self::ehCargo($t)) continue;
            $out[$n] = self::maiuscula(preg_replace('/\s*\(\d+\)$/', '', $t) ?? $t);
        }
        // "Estágios" = "Estágio"; "Vendedor(a)" dentro de "Vendedor(a) Conjunto Nacional": fica o mais curto.
        $chaves = array_keys($out);
        foreach ($chaves as $a) foreach ($chaves as $b) {
            if ($a === $b || !isset($out[$a], $out[$b])) continue;
            if (rtrim($a, 's') === rtrim($b, 's') || str_starts_with($b, $a.' ')) unset($out[$b]);
        }
        return array_values($out);
    }

    private static function titulo(array $linhas, array $destaques, array $complemento, array $cargos, string $anunciante = ''): string {
        // 1) Rótulo explícito: "Vaga: X", "Cargo: X" (ou "CARGO:" com o cargo na linha de baixo).
        foreach ($linhas as $i => $l) {
            if (preg_match('/^(?:vaga|cargo|fun[cç][aã]o|oportunidade|posi[cç][aã]o)\s*(?:de|para)?\s*:\s*(.{3,80})$/iu', $l, $m)) return self::maiuscula(self::limparTitulo($m[1]));
            if (preg_match('/^(?:cargo|vaga|fun[cç][aã]o)\s*:?$/iu', $l) && isset($linhas[$i + 1]) && self::ehCargo($linhas[$i + 1])) {
                // O cargo pode seguir em mais linhas ("VAGA" / "OPERADOR DE" / "MÁQUINA" / "COSTAL").
                $emLinhas = self::tituloEmLinhas(array_slice($linhas, $i + 1));
                return $emLinhas !== '' ? $emLinhas : self::maiuscula(self::limparTitulo($linhas[$i + 1]));
            }
        }
        // 1b) Frase "O Giraffas está contratando atendente de lanchonete para o Shopping…".
        if (count($cargos) < 2) {
            foreach (array_slice($linhas, 0, 6) as $l) {
                if (preg_match('/\b(?:contratando|contrata|precisa(?:-se)? de|procura|seleciona)\s+(?:um|uma|uns|umas)?\s*([\p{L}\s()\/]{4,50}?)(?=\s+(?:para|no|na|em|com|das|nas|nos)\b|\s*[—–,.!:-]|\s*$)/iu', $l, $m)) {
                    $t = self::limparTitulo($m[1]);
                    if (self::ehCargo($t) && str_word_count(Competencias::normalizar($t)) <= 6) return self::maiuscula($t);
                }
            }
        }
        // 1c) Cartaz com o cargo em várias linhas de letra grande: "OPERADOR DE" / "MÁQUINA" / "COSTAL" / "(ROÇADEIRA)".
        if ($destaques && ($emLinhas = self::tituloEmLinhas($linhas)) !== '') return $emLinhas;
        // 2) Vários cargos no cartaz: os escritos com as MAIORES letras são os da vaga ("GERENTE e VENDEDORA");
        //    os pequenos costumam ser "temos outras vagas também". Até 3 no título.
        if (count($cargos) >= 2 && $destaques) {
            // As leituras repetem o mesmo cargo com restos de ícone na frente ("as GERENTE", "Aa GERENTE"): limpa e olha os 8 maiores.
            $grandes = array_map(fn($d) => Competencias::normalizar(self::limparTitulo(preg_replace('/^(?:\S{1,2}\s+)+(?=\p{Lu}{3})/u', '', $d) ?? $d)), array_slice($destaques, 0, 8));
            $principais = array_values(array_filter($cargos, fn($c) => in_array(Competencias::normalizar($c), $grandes, true)));
            if ($principais) return implode(' / ', array_slice($principais, 0, 3));
        }
        // Lista de cargos no cartaz: até 3 no título; lista grande vira "Vagas abertas — Empresa".
        if (count($cargos) > 5) return $anunciante !== '' ? 'Vagas abertas — '.$anunciante : implode(' / ', array_slice($cargos, 0, 3)).' e outras';
        if (count($cargos) >= 2) return implode(' / ', array_slice($cargos, 0, 3));

        // 3) O maior texto do cartaz que tenha um cargo (juntando "AUXILIAR DE" + "COZINHA").
        $candidatos = [];
        foreach ($destaques as $k => $d) $candidatos[] = [$d, 30 - $k * 2];
        foreach (array_slice($linhas, 0, 18) as $k => $l) $candidatos[] = [$l, 18 - $k];
        foreach (array_slice($complemento, 0, 25) as $k => $l) $candidatos[] = [$l, 8 - $k * 0.3];
        $melhor = ''; $pontos = -INF;
        foreach ($candidatos as [$c, $p]) {
            $t = self::completarTitulo(self::limparTitulo($c), $linhas, $destaques);
            if ($t === '' || mb_strlen($t) > 60 || !self::ehCargo($t) || preg_match('/r\$|\d{4}|@|whats|http/iu', $t)) continue;
            $palavras = str_word_count(Competencias::normalizar($t));
            if ($palavras > 7) continue;
            $p += $palavras >= 2 && $palavras <= 5 ? 4 : 0;
            if ($p > $pontos) { $pontos = $p; $melhor = $t; }
        }
        if ($melhor !== '') return self::maiuscula($melhor);
        if (count($cargos) === 1) return $cargos[0];
        // 4) Cartaz sem cargo legível: "Vagas abertas — Empresa" (ou vazio, para a revisão preencher).
        if ($anunciante !== '') return 'Vagas abertas — '.$anunciante;
        if ($destaques) return '';
        // 5) Anúncio de texto: a primeira linha com cara de título.
        foreach (array_slice($linhas, 0, 4) as $l) {
            if (preg_match('/^(local|hor[aá]rio|sal[aá]rio|requisitos?|benef[ií]cios|contato|endere[cç]o|escala|jornada)\s*:/iu', $l)) continue; // linha de campo, não título
            $t = self::limparTitulo($l);
            if (preg_match_all('/\p{L}/u', $t) >= 5 && mb_strlen($t) <= 80 && !preg_match(self::GENERICOS, Competencias::normalizar($t)) && !preg_match('/r\$|\d{4}|@|whats/iu', $t)) return self::maiuscula($t);
        }
        return '';
    }

    private static function maiuscula(string $t): string { return mb_strtoupper(mb_substr($t, 0, 1)).mb_substr($t, 1); }

    /** "Auxiliar de" + "Cozinha", "Consultor" + "Interno": completa com a linha vizinha (depois; nos destaques, também antes). */
    private static function completarTitulo(string $t, array $linhas, array $destaques): string {
        $n = Competencias::normalizar($t);
        $incompleto = preg_match('/\b(de|da|do|em|e)$/', $n) || (str_word_count($n) === 1 && preg_match('/^(consultor|consultora|auxiliar|assistente|promotor|operador|operadora|analista|tecnico|atendente)$/', $n));
        if (!$incompleto) return $t;
        $serve = function (string $l): ?string {
            $prox = self::limparTitulo($l);
            $pn = Competencias::normalizar($prox);
            // 1 a 3 palavras, sem números, sem frase em minúsculas, sem ser local ou chamada.
            if ($pn === '' || str_word_count($pn) > 3 || preg_match('/\d|\//', $prox) || preg_match(self::GENERICOS, $pn) || self::ehLocalSolto($pn)) return null;
            if (preg_match('/(^|\s)(?!(?:de|da|do|e|em)\b)\p{Ll}/u', $prox)) return null;
            return $prox;
        };
        foreach ([[$linhas, [1]], [$destaques, [1, -1]]] as [$fonte, $passos]) {
            foreach ($fonte as $i => $l) {
                if (Competencias::normalizar(self::limparTitulo($l)) !== $n) continue;
                foreach ($passos as $d) if (isset($fonte[$i + $d]) && ($prox = $serve($fonte[$i + $d])) !== null) return self::limparTitulo($t.' '.$prox);
            }
        }
        return preg_match('/\b(de|da|do|em|e)$/', $n) ? '' : $t;
    }


    private static function limparTitulo(string $t): string {
        $t = self::limparLinha($t);
        $t = preg_replace('/^(?:vaga(?:s)?(?: de emprego)?(?: abertas?| dispon[ií]veis)?(?: para| de)?|contrata(?:-se|mos)?|estamos contratando|oportunidade(?: de emprego)?(?: para)?|precisa-se de|urgente|temos vagas?(?: para)?|contratamos)\s*[:!\-–—]?\s*/iu', '', trim($t)) ?? $t;
        $t = preg_replace('/^(?:tempor[aá]ri[oa]s?|clt|pj|efetivo|freelancer?)\s*[-–—:|]\s*/iu', '', $t) ?? $t;   // "Temporário - Operador de Caixa"
        $t = preg_replace('/\s*\(?\bc[oó]d(?:igo)?\.?\s*:?\s*\d+\)?/iu', '', $t) ?? $t;                         // "(cód. 1308)"
        $t = self::cortarAteCargo($t);
        $t = preg_replace('/\s*[-–|]\s*(?:'.implode('|', ['Brasília','DF','Taguatinga','Ceilândia','Guará','Águas Claras','Samambaia','Asa Norte','Asa Sul','Gama']).')\b.*$/iu', '', $t) ?? $t;
        $t = preg_replace('/^\d\s+(?=\p{Lu})/u', '', $t) ?? $t;                 // número de ícone antes do cargo
        $t = preg_replace('/\s*\(\d+\)$|\s*\([\p{L}\d]{0,2}$/u', '', $t) ?? $t;  // "(3)" ou "(a" sem fechar, no fim
        if (substr_count($t, '(') < substr_count($t, ')')) $t = preg_replace('/\)\s*$/u', '', $t) ?? $t; // ")" sem par
        $t = trim_u($t, " !:-–—.,;|/");
        return self::caixa($t);
    }

    /** Palavras em CAIXA ALTA viram "Auxiliar de Cozinha" (preposições minúsculas, siglas mantidas). */
    private static function caixa(string $t): string {
        if ($t === '') return $t;
        $siglas = ['RH', 'DP', 'SDR', 'PAP', 'TI', 'CLT', 'PJ', 'SIA', 'DF', 'GO', 'SESC', 'SAC', 'CNH', 'EAD', 'UX', 'UI', 'MEI', 'II', 'III',
                   'PHP', 'SQL', 'HTML', 'CSS', 'JS', 'SAP', 'ERP', 'CRM', 'PCD', 'NR10', 'BI', 'QA', 'DBA', 'SUS', 'CRECI', 'COREN', 'CRM', 'EPI'];
        $out = [];
        foreach (preg_split('/(\s+|\/|-)/u', $t, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $i => $p) {
            if (trim($p) === '' || in_array($p, ['/', '-'], true)) { $out[] = $p; continue; }
            if ($i > 0 && in_array(mb_strtolower($p), ['de', 'da', 'do', 'das', 'dos', 'di', 'e', 'em'], true)) { $out[] = mb_strtolower($p); continue; }
            if ($p !== mb_strtoupper($p) || !preg_match('/\p{L}{2}/u', $p)) { $out[] = $p; continue; } // já em caixa mista: mantém
            if (in_array($p, $siglas, true)) { $out[] = $p; continue; }
            $p = mb_strtolower($p);
            if ($i > 0 && in_array($p, ['de', 'da', 'do', 'das', 'dos', 'di', 'e', 'em', 'para', 'com', 'a', 'o', 'na', 'no', 'ao'], true)) { $out[] = $p; continue; }
            // Primeira LETRA em maiúscula, mesmo depois de pontuação: "(roçadeira)" → "(Roçadeira)".
            $out[] = preg_replace_callback('/^(\P{L}*)(\p{L})/u', fn($m) => $m[1].mb_strtoupper($m[2]), $p) ?? $p;
        }
        $t = implode('', $out);
        return preg_replace_callback('/\((\p{Lu}{1,2})\)/u', fn($m) => '('.mb_strtolower($m[1]).')', $t) ?? $t; // Operador(A) → Operador(a)
    }

    // ------------------------------------------------------------------ empresa, contato, salário, local

    /** @param array<string,bool> $emSecao linhas (normalizadas) de requisitos/benefícios: não são nome de empresa
     *  @param string $bruto texto do OCR sem limpeza (o "&" de "& FACE" fica) */
    private static function anunciante(string $tudo, array $linhas, array $destaques, array $repetidas = [], array $emSecao = [], string $bruto = ''): string {
        $n = ' '.Competencias::normalizar($tudo).' ';
        // 1) Marcas conhecidas.
        foreach (self::MARCAS as $m) if (str_contains($n, ' '.Competencias::normalizar($m).' ')) return $m;
        // 2) Frases: "O Giraffas está contratando", "Somos a Cinco Estrelas", "Casa do Vovô - Vicente Pires/DF está com vaga".
        $pad = [
            '/\b(?:[OA]s?\s+)([\p{Lu}][\p{L}\'’&.]*(?:\s+(?:d[aeo]s?\s+)?[\p{Lu}][\p{L}\'’&.]*){0,3})\s+(?:est[aá]|estão)\s+(?:contratando|com vagas?|aumentando|com oportunidades)/u',
            '/\bSomos\s+(?:a|o|os|as)\s+([\p{Lu}][\p{L}\'’&.]*(?:\s+(?:d[aeo]s?\s+)?[\p{Lu}][\p{L}\'’&.]*){0,3})/u',
            '/^([\p{Lu}][\p{L}\'’&.]*(?:\s+(?:d[aeo]s?\s+)?[\p{Lu}][\p{L}\'’&.]*){0,3})\s+-\s+[\p{L}\s]+\/(?:DF|GO)\s+est[aá]/mu',
        ];
        foreach ($pad as $rx) if (preg_match($rx, $tudo, $m)) { $nome = self::nomeEmpresa($m[1]); if ($nome !== '') return $nome; }
        // 2b) Logotipo confirmado pelo e-mail do cartaz: "SMILE" + "& FACE" … admsmileface@gmail.com → "Smile & Face".
        //     Linhas curtas cujas letras aparecem ENCOSTADAS no e-mail (ou uma de 2+ palavras), 6+ letras no total.
        if (preg_match_all('/\b([a-z0-9._-]{4,})@([a-z0-9-]+)\./i', self::corrigirEmails($tudo), $ms, PREG_SET_ORDER)) {
            $emails = [];
            foreach ($ms as $m) {
                $emails[] = strtolower(preg_replace('/[^a-z0-9]/i', '', $m[1]) ?? '');
                if (!preg_match('/^(gmail|hotmail|outlook|yahoo|live|icloud|bol|uol|terra)$/i', $m[2])) $emails[] = strtolower($m[2]);
            }
            $brutas = array_values(array_filter(array_map('trim', explode("\n", $bruto !== '' ? $bruto : $tudo)), fn($l) => $l !== ''));
            $curta = fn(string $l) => $l === mb_strtoupper($l) && !preg_match('/[\d@:]/', $l) && str_word_count(Competencias::normalizar($l)) <= 3
                && !preg_match('/\b(vagas?|curriculos?|contato|rh|email|envie|whatsapp|candidatura|vendas|loja|comercial|atendimento|selecao|recrutamento|trabalhe|empregos?)\b/', Competencias::normalizar($l));
            // Pedaços do logotipo achados no e-mail, pela posição: "mimoria"(0) + "business"(7) → encostados → um nome só.
            $pecas = [];
            foreach ($brutas as $l) {
                $junto = str_replace(' ', '', Competencias::normalizar($l));
                if (!$curta($l) || strlen($junto) < 4) continue;
                foreach ($emails as $e => $em) {
                    $pos = strpos($em, $junto);
                    if ($pos !== false && !isset($pecas[$e][$pos])) $pecas[$e][$pos] = [$l, strlen($junto)];
                }
            }
            foreach ($pecas as $lista) {
                ksort($lista);
                $melhor = null;
                foreach ($lista as $pos => [$l, $tam]) {
                    $partes = [$l]; $fimPeca = $pos + $tam; $total = $tam;
                    while (isset($lista[$fimPeca]) && count($partes) < 3) { $partes[] = $lista[$fimPeca][0]; $total += $lista[$fimPeca][1]; $fimPeca += $lista[$fimPeca][1]; }
                    // Um pedaço só precisa ter 2+ palavras ("DOM CASERO"); juntando pedaços, 6+ letras.
                    $ok = count($partes) > 1 ? $total >= 6 : str_word_count(Competencias::normalizar($l)) >= 2 && $total >= 6;
                    if ($ok && (!$melhor || $total > $melhor[1])) $melhor = [implode(' ', $partes), $total];
                }
                if (!$melhor) continue;
                $nome = self::nomeEmpresa($melhor[0]);
                if ($nome !== '' && !self::ehPonto(Competencias::normalizar($nome))) return $nome;
            }
        }
        // 2c) Nome repetido no cartaz (logo no topo e no rodapé: "DOM CASERO" … "DOM CASERO"): 2 a 4 palavras em
        //     maiúsculas, sem números, que não sejam cargo, rótulo ou palavra-chave de vaga, lido 2+ vezes.
        $contagem = [];
        foreach ($repetidas as $l) {
            $l = self::limparLinha($l);
            $k = Competencias::normalizar($l);
            $palavras = str_word_count($k);
            // Toda palavra do nome com 3+ letras (ou "de/da/do/e/&"): "RO LUGAR" é pedaço de "EM PRIMEIRO LUGAR", não empresa.
            $pedaco = (bool)array_filter(preg_split('/\s+/u', $k) ?: [], fn($w) => mb_strlen($w) < 3 && !in_array($w, ['de', 'da', 'do', 'e'], true));
            if ($pedaco || $palavras < 2 || $palavras > 4 || preg_match('/\d/', $l) || $l !== mb_strtoupper($l) || self::ehCargo($l) || preg_match(self::GENERICOS, $k)
                || self::ehPonto($k) || self::ehLocalSolto($k) || isset($emSecao[$k]) || self::ehSlogan($l) || str_contains($l, ",")
                || preg_match(self::PALAVRAS_BENEFICIO, $k) || preg_match(self::PALAVRAS_REQUISITO, $k) || preg_match(self::PALAVRAS_HORARIO, $k)
                || preg_match('/\b(vaga|vagas|local|locais|trabalho|curriculo|whatsapp|contato|envie|time|equipe|emprego|formato|clt|pj|brasilia|df|junior|pleno|senior|jr|sr)\b/', $k)) continue;
            $contagem[$k] = [($contagem[$k][0] ?? 0) + 1, $contagem[$k][1] ?? $l];
        }
        uasort($contagem, fn($a, $b) => $b[0] <=> $a[0]);
        foreach ($contagem as [$vezes, $original]) {
            if ($vezes < 2) break;
            $nome = self::nomeEmpresa($original);
            if ($nome !== '') return $nome;
        }
        // 3) Linha com o tipo do negócio: "Restaurante Prosa di Minas em", "Kairós Restaurante", "Grupo Viver Bem Seguros".
        foreach (array_merge($linhas, $destaques) as $l) {
            $ln = Competencias::normalizar($l);
            if (mb_strlen($l) > 60 || !preg_match('/\b('.self::NEGOCIOS.')\b/', $ln) || self::ehCargo($l) || preg_match('/^(de|da|do|em|e|ao|a|o|no|na)\b/', $ln)
                || preg_match('/\b(experiencia|vaga|vagas|fazer parte|equipe|time|refeicao|alimentacao|vale|contratando|aumentando|desconto|convenio|plano|saude|parceria|seguro)\b/', $ln)) continue;
            $nome = self::nomeEmpresa(preg_replace('/\s+(?:em|no|na|de)$/iu', '', self::limparLinha($l)) ?? $l);
            if ($nome !== '' && str_word_count(Competencias::normalizar($nome)) >= 2) return $nome;
        }
        // 4) Domínio do e-mail que aparece escrito no cartaz (rh@santosbeneli.com.br + "SANTOS BENELI").
        if (preg_match_all('/@([a-z0-9-]+)\.(?:com|net|org|adv)/i', self::corrigirEmails($tudo), $ms)) {
            foreach ($ms[1] as $dom) {
                if (preg_match('/^(gmail|hotmail|outlook|yahoo|live|icloud|bol|uol|terra)$/i', $dom)) continue;
                foreach (array_merge($destaques, $linhas) as $l) {
                    $junto = str_replace(' ', '', Competencias::normalizar($l));
                    if (strlen($junto) >= 4 && str_starts_with(strtolower($dom), $junto)) return self::nomeEmpresa($l);
                }
            }
        }
        return '';
    }

    private static function nomeEmpresa(string $s): string {
        $s = trim_u(preg_replace('/\s+/u', ' ', self::limparLinha($s)) ?? $s, " .,-–");
        // Corta onde a frase continua: "Cantón Restaurante está…" → "Cantón Restaurante".
        $s = preg_replace('/\s+(?!(?:de|da|do|das|dos|di|e)\b)\p{Ll}.*$/u', '', $s) ?? $s;
        if ($s === '' || mb_strlen($s) > 50 || str_contains($s, ':') || self::ehCargo($s) || preg_match(self::GENERICOS, Competencias::normalizar($s))) return '';
        if (preg_match('/\b(de|da|do|das|dos|e|em|para|com|a|o)$/', Competencias::normalizar($s))) return ''; // "TIPO DE" (de "TIPO DE CONTRATO:")
        return self::caixa($s);
    }

    /** OCR costuma trocar o @ por G, Q, O ou "(o": "vagasQgmail.com" → "vagas@gmail.com". */
    private static function corrigirEmails(string $t): string {
        $t = preg_replace('/\b([a-z0-9._-]{3,})(?:\(o|[GQO©@])((?:gmail|hotmail|outlook|yahoo|live|icloud)\.com(?:\.br)?)\b/i', '$1@$2', $t) ?? $t;
        return preg_replace('/\b([a-z0-9._-]{2,})\(o([a-z0-9-]+\.(?:com|net|org)(?:\.br)?)\b/i', '$1@$2', $t) ?? $t;
    }

    /** "WhatsApp (61) 98549-9498 · vagas@empresa.com". */
    private static function contato(string $t): string {
        $t = self::corrigirEmails($t);
        $partes = []; $vistos = [];
        if (preg_match_all('/(?<![\d,.])(?:\+?\s*55[\s-]*)?\(?\s*(\d{2})\s*\)?[\s.-]*(9?)[\s.-]*(\d{4})[\s.-]*(\d{4})(?![\d,])/u', $t, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $ddd = (int)$m[1];
                if ($ddd < 11 || $ddd > 99 || $ddd % 10 === 0) continue;
                $num = $m[2].$m[3].$m[4];
                $fmt = '('.$m[1].') '.(strlen($num) === 9 ? substr($num, 0, 5).'-'.substr($num, 5) : substr($num, 0, 4).'-'.substr($num, 4));
                if (isset($vistos[$fmt])) continue;
                $vistos[$fmt] = true;
                $partes[] = (strlen($num) === 9 && preg_match('/whats|wa\b|zap/i', $t) ? 'WhatsApp ' : 'Telefone ').$fmt;
            }
        }
        if (preg_match_all('/\b[a-z0-9._%+-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}\b/i', $t, $ms)) {
            foreach ($ms[0] as $e) { $e = strtolower($e); if (!isset($vistos[$e])) { $vistos[$e] = true; $partes[] = $e; } }
        }
        if (preg_match('/\b(?:assunto|informe|t[ií]tulo)\s*:\s*([^\n|]{3,60})/iu', $t, $m)) $partes[] = 'Assunto: '.self::caixa(trim($m[1], ' .'));
        return mb_substr(implode(' · ', array_slice($partes, 0, 4)), 0, 255);
    }

    /** @return array{0:?float,1:?float} */
    private static function salario(string $t): array {
        $t = preg_replace('/\bR\s?S\s?(?=\d)/u', 'R$ ', $t) ?? $t;            // OCR: "RS 1.750,00"
        $candidatos = [];
        if (preg_match_all('/(?:R\$|sal[aá]rio(?:\s+de)?:?|bolsa(?:-aux[ií]lio)?:?|remunera[cç][aã]o:?)\s*(\d{1,3}(?:\.\d{3})+(?:,\d{2})?|\d{3,6}(?:,\d{2})?)/iu', $t, $ms, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $antes = Competencias::normalizar(substr($t, max(0, $m[0][1] - 45), min(45, $m[0][1])));
                $depois = Competencias::normalizar(substr($t, $m[1][1] + strlen($m[1][0]), 25));
                // Valores que não são o salário: benefícios, adicionais, metas e "pode chegar a".
                // (a palavra-chave vale se não houver outro valor "R$ ..." entre ela e este)
                if (preg_match_all('/\b(vr|vt|va|vale|refeicao|alimentacao|comissao|comissoes|bonus|ajuda|diaria|por dia|hora|periculosidade|adicional|auxilio|reembolso|premiacao|premio|indicacao|chegar a|chegar ate|ganhar|mobilidade|transporte|plano|passagem|desconto|cerca de)\b/', $antes, $km, PREG_OFFSET_CAPTURE)) {
                    $ult = end($km[0]);
                    // "R$ 700,00 VT/VR" logo antes: a sigla é do valor anterior, não deste ("… VT/VR  ESTÁGIO: R$ 1.000").
                    $doAnterior = preg_match('/\br \d[\d ]*(?:\s*\b(?:vt|vr|va)\b)+$/', substr($antes, 0, (int)$ult[1] + strlen($ult[0])));
                    if (!$doAnterior && !preg_match('/\br \d/', substr($antes, (int)$ult[1]))) continue;
                }
                if (preg_match('/^\s*(por dia|dia|ao dia|por km|mes|mensal de vale|de vale|ou mais)\b/', $depois)) continue;
                // "R$ 700,00 VT/VR" é o valor do vale; "R$ 1.900,00 + VT" é o salário mais o vale (o "+" separa).
                if (preg_match('/^\s*(?:VT|VR|VA)\b/iu', substr($t, $m[1][1] + strlen($m[1][0]), 12))) continue;
                $v = decimal_ou_null($m[1][0]);
                if ($v === null || $v < 500 || $v > 30000) continue;
                // "sal[a-z]{1,4}rio": aceita o "salário" mal lido pelo OCR ("saLÁário" → "salaario").
                // "CLT: R$ 2.200" / "ESTÁGIO: R$ 1.000": valor com o regime logo antes também é salário declarado.
                $candidatos[] = ['v' => $v, 'forte' => (bool)preg_match('/\b(sal[a-z]{1,4}rio|remuneracao|bolsa|fixo|base|ganhos?|mensal)\b/', $antes.' '.Competencias::normalizar($m[0][0]))
                    || (bool)preg_match('/\b(clt|pj|estagio|estagiario|efetivo|temporario)\s*$/', $antes)];
            }
        }
        if (!$candidatos) return [null, null];
        $fortes = array_column(array_filter($candidatos, fn($c) => $c['forte']), 'v');
        // Com salário declarado, os outros valores só entram se forem da mesma ordem (cartaz com dois cargos e dois salários).
        $valores = $fortes ? array_filter(array_column($candidatos, 'v'), fn($v) => $v >= min($fortes) * 0.5 && $v <= max($fortes) * 2) : array_column($candidatos, 'v');
        return [min($valores), max($valores)];
    }

    private static function quantidade(string $n): ?int {
        if (preg_match('/\b(\d{1,3})\s+vagas?\b/', $n, $m) && (int)$m[1] > 0 && (int)$m[1] <= 500) return (int)$m[1];
        if (preg_match('/\bquantidade de vagas\s*(\d{1,3})\b/', $n, $m)) return (int)$m[1];
        return null;
    }

    /** @return array{0:string,1:string} */
    private static function local(string $tudo, array $linhasLocal): array {
        foreach ([implode("\n", $linhasLocal), $tudo] as $fonte) {
            if (trim($fonte) === '') continue;
            [$c, $uf] = ExtracaoCurriculo::local($fonte);
            // Descarta o que não é cidade (o padrão "Nome - UF" às vezes pega pedaços de outras linhas).
            if ($c !== '' && (str_contains($c, "\n") || self::ehCargo($c) || str_word_count(Competencias::normalizar($c)) > 4)) $c = '';
            $c = self::caixa($c);
            if ($c !== '' && $c !== 'Brasília') return [$c, $uf];
            $n = ' '.Competencias::normalizar($fonte).' ';
            foreach (self::PONTOS as $k => $v) if (str_contains($n, ' '.$k.' ')) return $v;
            if ($c !== '') return [$c, $uf];
        }
        return ['', ''];
    }
}
