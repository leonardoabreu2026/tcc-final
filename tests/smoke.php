<?php
declare(strict_types=1);

/*
 * ============================================================
 * TESTE RÁPIDO (smoke test) — confere se o sistema está de pé
 * ============================================================
 * Uso (na pasta do projeto, com Apache e MySQL ligados no XAMPP):
 *   C:\xampp\php\php.exe tests\smoke.php
 *   C:\xampp\php\php.exe tests\smoke.php http://localhost/outra%20pasta/   (endereço diferente)
 *
 * Verifica, sem gravar nada no banco (a única mudança é +1 no contador de visualizações da vaga 1):
 *  1. se todas as classes de app/ carregam (autoloader);
 *  2. as regras principais: funções de apoio, extração de vagas/cursos e máquina de match;
 *     e o calibrador das máquinas de extração (termos e nomes conhecidos em memória);
 *  3. a conexão com o banco e as contas de teste;
 *  4. as páginas pelo navegador (HTTP) e o bloqueio das pastas internas.
 * Termina com código 1 se algo falhar.
 */

require __DIR__.'/../app/Core/bootstrap.php';

$falhas = 0;
function confere(string $descricao, bool $ok, string $detalhe = ''): void {
    global $falhas;
    if (!$ok) $falhas++;
    echo ($ok ? '  OK    ' : '  FALHA ').$descricao.($ok || $detalhe === '' ? '' : " → $detalhe").PHP_EOL;
}

// ------------------------------------------------------------
echo PHP_EOL.'1. Classes (autoloader)'.PHP_EOL;
foreach (['Controllers', 'Models', 'DTO', 'Services', 'Services/Extracao'] as $pasta) {
    $classes = array_map(fn($f) => basename($f, '.php'), glob(APP_DIR."/$pasta/*.php") ?: []);
    $faltando = array_filter($classes, fn($c) => !class_exists($c) && !trait_exists($c));
    confere("app/$pasta: ".count($classes).' classe(s)', !$faltando, implode(', ', $faltando));
}

// ------------------------------------------------------------
echo PHP_EOL.'2. Regras de negócio'.PHP_EOL;
// As regras são conferidas SEM o calibrador: os termos cadastrados no banco não podem mudar estes resultados.
Calibrador::ligar(false);
confere('decimal_ou_null("1.234,56") = 1234.56', decimal_ou_null('1.234,56') === 1234.56);
confere('salario_texto(1900, 2500)', salario_texto(1900, 2500) === 'R$ 1.900,00 a R$ 2.500,00');
confere('rotulo("em_analise") = "Em análise"', rotulo('em_analise') === 'Em análise');
confere('mascarar_email: log sem o e-mail inteiro (LGPD)', mascarar_email('Candidato@ConectaVagas.com') === 'ca*******@conectavagas.com'
    && mascarar_email('ab@x.com') === 'a*@x.com' && mascarar_email('sem-arroba') === '********', mascarar_email('Candidato@ConectaVagas.com'));
confere('UsuarioDAO::senhaConfere: conta inexistente ou senha vazia nunca confere', !(new UsuarioDAO())->senhaConfere(0, 'Admin@123') && !(new UsuarioDAO())->senhaConfere(1, ''));
confere('caminho_upload() aceita só uploads simples', caminho_upload('assets/uploads/foto_1.png') === UPLOAD_DIR.'foto_1.png'
    && caminho_upload('assets/uploads/../config/config.php') === null && caminho_upload('config/config.php') === null);
confere('caminho_imagem_valido() bloqueia ".."', caminho_imagem_valido('assets/img/vagas/vaga1.jpg') !== '' && caminho_imagem_valido('assets/img/../../x.png') === '');

$comp = Competencias::extrair('Experiência com vendas, atendimento ao cliente e Excel avançado');
confere('Competencias::extrair encontra Vendas, Atendimento e Excel', !array_diff(['Vendas', 'Atendimento ao cliente', 'Excel'], $comp), implode(', ', $comp));

$vaga = ExtracaoVaga::doTexto("VAGA: Vendedor Interno - Taguatinga\nSalário: R$ 3.000 a R$ 5.500 + comissões\nRequisitos: experiência com vendas e Excel\nBenefícios: VT + VR R$ 33,40");
confere('ExtracaoVaga: título, salário (ignora o VR) e cidade', $vaga['titulo'] === 'Vendedor Interno' && $vaga['salario_minimo'] === 3000.0
    && $vaga['salario_maximo'] === 5500.0 && $vaga['cidade'] === 'Taguatinga', json_encode([$vaga['titulo'], $vaga['salario_minimo'], $vaga['salario_maximo'], $vaga['cidade']], JSON_UNESCAPED_UNICODE));

$vaga2 = ExtracaoVaga::doTexto("GRUPO DOURADO\nAUXILIAR DE COZINHA\nÁguas Claras - 2 vagas\nHorário: 14h20 às 22h, CLT 6x1\nSalário a partir de R$ 1.900,00 + VT + alimentação no local");
confere('ExtracaoVaga: "Horário:" não engole o salário/benefícios da linha seguinte', $vaga2['beneficios'] !== '' && $vaga2['salario_minimo'] === 1900.0
    && $vaga2['anunciante'] === 'Grupo Dourado' && $vaga2['cidade'] === 'Águas Claras', json_encode([$vaga2['beneficios'], $vaga2['salario_minimo'], $vaga2['anunciante'], $vaga2['cidade']], JSON_UNESCAPED_UNICODE));
$titulos = array_map(fn($t) => ExtracaoVaga::doTexto($t)['titulo'], [
    "ESTÁGIO EM ENFERMAGEM (cód. 1308)\nLocal: Taguatinga\nBolsa-auxílio de R$ 750,00",
    "O Giraffas está contratando atendente de lanchonete para o Shopping Boulevard",
    "Temporário - Operador de Caixa\nLocal: Taguatinga Shopping",
    "Desenvolvedor PHP Júnior\nModelo híbrido - Brasília/DF",
]);
confere('ExtracaoVaga: títulos (código da vaga, "contratando X", "Temporário -", siglas)', $titulos === ['Estágio em Enfermagem', 'Atendente de lanchonete', 'Operador de Caixa', 'Desenvolvedor PHP Júnior'], json_encode($titulos, JSON_UNESCAPED_UNICODE));
$cartaz = ExtracaoVaga::doTexto("DOM CASERO\nVENDEDORA\nIdeal Primeiro emprego\nsaLÁário: R$ 2.700,00\nSALÁRIO: R$ 2.110,00\nRequisitos: cursando Administração\nBolsa de R$ 900 + VT\nVT (DF ou GO)\nPremiação por assiduidade\nPremiação por assiduidade.\nTemos outras vagas também!",
    ['destaques' => ['VENDEDORA', 'GERENTE'], 'complemento' => [], 'todas' => ['DOM CASERO', 'DOM CASERO', 'VENDEDORA']]);
confere('ExtracaoVaga (cartaz): empresa repetida, cargos grandes, faixa salarial, VT e sem linha repetida',
    $cartaz['anunciante'] === 'Dom Casero' && $cartaz['titulo'] === 'Vendedora / Gerente' && $cartaz['salario_minimo'] === 900.0 && $cartaz['salario_maximo'] === 2700.0
    && str_contains($cartaz['beneficios'], 'VT (DF ou GO)') && substr_count($cartaz['beneficios'], 'Premiação por assiduidade') === 1
    && str_contains($cartaz['beneficios'], 'Bolsa') && !str_contains($cartaz['requisitos'], 'Bolsa') && !str_contains($cartaz['descricao'], 'outras vagas'),
    json_encode([$cartaz['anunciante'], $cartaz['titulo'], $cartaz['salario_minimo'], $cartaz['salario_maximo'], $cartaz['beneficios'], $cartaz['requisitos']], JSON_UNESCAPED_UNICODE));
// Cartaz real (RE9COM, roçadeira): cargo em 4 linhas, palavra partida pelo OCR, slogan, logotipo ilegível, "R$ 48,00 ror DIA".
$ocrRe9 = array (
  'texto' => 'REQ(.ÓOM
Soluções e Serviços
TEMOS
VAGA
OPERADOR DE
MÁQUI NA
COSTAL
(ROÇADEIRA)
LOCAL DE TRABALHO:
BRASÍLIA-DF
VALE REFEIÇÃO
CESTA BÁSICA
© ENVIE SEU CURRÍCULO
PELO WHATSAPP:
SOLUÇÕES QUE GERAM VALOR. SERVIÇOS QUE FAZEM A DIFERENÇA.',
  'complemento' => 
  array (
    0 => 'MAQUINA',
    1 => 'BENEFICIOS',
    2 => 'R$ 48,00 ror DIA',
    3 => 'PLANO DE SAUDE',
    4 => 'EGUR',
    5 => 'ESPE',
    6 => 'RO LUGAR',
    7 => 'OMETIDA',
    8 => '—— SOoLUÇÕES QUE GERAM VALOR. SERVIÇOS QUE FAZEM A DIFERENÇA',
    9 => 'OPORTUNIDADE PARA',
    10 => 'QUEM FAZ A DIFERENÇA!',
    11 => 'R$ 48,00 rPor',
    12 => '61 97402-3121',
    13 => 'V OMPROMETIDA',
    14 => 'REQ(COM',
    15 => 'QUIPE',
    16 => 'RESI',
  ),
  'destaques' => 
  array (
    0 => 'REQ(.ÓOM',
    1 => 'REQ(COM',
    2 => 'VAGA',
    3 => 'MÁQUI NA',
    4 => 'MAQUINA',
    5 => 'COSTAL',
    6 => 'OPORTUNIDADE PARA',
    7 => 'OPERADOR DE',
    8 => '(ROÇADEIRA)',
    9 => 'BRASÍLIA-DF',
  ),
  'todas' => 
  array (
    0 => 'Soluções e Serviços',
    1 => 'TEMOS',
    2 => 'VAGA',
    3 => 'OPERADOR DE',
    4 => 'MÁQUI NA',
    5 => 'COSTAL',
    6 => '(ROÇADEIRA)',
    7 => 'LOCAL DE TRABALHO:',
    8 => 'BRASÍLIA-DF',
    9 => 'VALE REFEIÇÃO',
    10 => 'CESTA BÁSICA',
    11 => '© ENVIE SEU CURRÍCULO',
    12 => 'PELO WHATSAPP:',
    13 => 'SOLUÇÕES QUE GERAM VALOR. SERVIÇOS QUE FAZEM A DIFERENÇA.',
    14 => 'REQ(ÓOM',
    15 => 'Soluções e Serviços',
    16 => 'TEMOS',
    17 => 'VAGA',
    18 => 'OPERADOR DE',
    19 => 'MAQUINA',
    20 => 'COSTAL',
    21 => '(ROÇADEIRA)',
    22 => 'LOCAL DE TRABALHO',
    23 => 'BRASILIA-DF',
    24 => 'BENEFICIOS',
    25 => 'VALE REFEIÇÃO',
    26 => 'R$ 48,00 ror DIA',
    27 => 'PLANO DE SAUDE',
    28 => 'CESTA BÁSICA',
    29 => 'ENVIE SEU CURRÍCULO',
    30 => 'PELO WHATSAPP:',
    31 => 'EGUR',
    32 => 'ESPE',
    33 => 'RO LUGAR',
    34 => 'OMETIDA',
    35 => '—— SOoLUÇÕES QUE GERAM VALOR. SERVIÇOS QUE FAZEM A DIFERENÇA',
    36 => 'Soluções e Serviços',
    37 => 'TEMOS',
    38 => 'OPORTUNIDADE PARA',
    39 => 'QUEM FAZ A DIFERENÇA!',
    40 => 'OPERADOR DE',
    41 => 'MAQUINA',
    42 => 'COSTAL',
    43 => '(ROÇADEIRA)',
    44 => 'LOCAL DE TRABALHO:',
    45 => 'BRASÍLIA-DF',
    46 => 'VALE REFEIÇÃO',
    47 => 'R$ 48,00 rPor',
    48 => 'CESTA BÁSICA',
    49 => 'ENVIE SEU CURRÍCULO',
    50 => 'PELO WHATSAPP:',
    51 => '61 97402-3121',
    52 => 'V OMPROMETIDA',
    53 => 'SOLUÇÕES QUE GERAM VALOR. SERVIÇOS QUE FAZEM A DIFERENÇA.',
    54 => 'REQ(COM',
    55 => 'Soluções e Serviços',
    56 => 'TEMOS',
    57 => 'VAGA',
    58 => 'OPERADOR DE',
    59 => 'MAQUINA',
    60 => 'COSTAL',
    61 => '(ROÇADEIRA)',
    62 => 'LOCAL DE TRABALHO',
    63 => 'BRASILIA-DF',
    64 => 'BENEFICIOS',
    65 => 'VALE REFEIÇÃO',
    66 => 'R$ 48,00 ror DIA',
    67 => 'PLANO DE SAUDE',
    68 => 'CESTA BÁSICA',
    69 => 'ENVIE SEU CURRÍCULO',
    70 => 'PELO WHATSAPP:',
    71 => 'QUIPE',
    72 => 'RO LUGAR',
    73 => 'RESI',
    74 => '— SOLUÇÕES QUE GERAM VALOR. SERVIÇOS QUE FAZEM A DIFERENÇA',
  ),
);
$re9 = ExtracaoVaga::doTexto($ocrRe9["texto"], $ocrRe9);
confere("cartaz com cargo em várias linhas, slogan e logotipo ilegível (calibrado)", $re9["titulo"] === "Operador de Máquina Costal (Roçadeira)" && $re9["anunciante"] === ""
    && $re9["categoria"] === "Serviços Gerais e Limpeza" && str_contains($re9["beneficios"], "Vale Refeição: R$ 48,00 por dia") && !preg_match("/solu|diferen|lugar|req\(/iu", $re9["beneficios"].$re9["descricao"])
    && $re9["contato"] === "WhatsApp (61) 97402-3121" && $re9["cidade"] === "Brasília", json_encode([$re9["titulo"], $re9["anunciante"], $re9["categoria"], $re9["beneficios"]], JSON_UNESCAPED_UNICODE));
// Leitor de cartaz DA PLATAFORMA (Tesseract.js no navegador): leituras reais de dois cartazes, salvas em tests/amostras.
$amostra = fn(string $n) => OcrImagem::leiturasDoNavegador(json_decode((string)file_get_contents(__DIR__.'/amostras/'.$n.'.json'), true));
confere('OcrImagem::leiturasDoNavegador aceita só leituras no formato do Tesseract (tamanho e quantidade limitados)', $amostra('ocr_navegador_smile') !== null
    && OcrImagem::leiturasDoNavegador('texto') === null && OcrImagem::leiturasDoNavegador(['a', 'b', 'c', 'd', 'e']) === null && OcrImagem::leiturasDoNavegador([['x']]) === null
    && OcrImagem::leiturasDoNavegador([str_repeat('x', 1_600_000)]) === null && OcrImagem::leiturasDoNavegador(["Vaga: Vendedor\nSalário: R$ 2.000"]) === null);
$smile = ExtracaoVaga::doImagem('', $amostra('ocr_navegador_smile'));
confere('cartaz lido no navegador (Smile & Face): título em 2 linhas, marca pelo e-mail, shopping não é empresa, "R$ 700 VT/VR" não é salário',
    $smile['motor'] === 'navegador' && $smile['titulo'] === 'Estágio Social Media' && $smile['anunciante'] === 'Smile & Face' && $smile['cidade'] === 'Asa Norte'
    && $smile['salario_minimo'] === 1000.0 && $smile['salario_maximo'] === 2200.0 && str_contains($smile['contato'], 'admsmileface@gmail.com')
    && $smile['tipo_vaga'] === 'estagio' && !str_contains($smile['requisitos'], 'CASE DE SUCESSO') && !str_contains($smile['descricao'], 'Conjunto Nacional contrata'),
    json_encode([$smile['titulo'], $smile['anunciante'], $smile['salario_minimo'], $smile['salario_maximo'], $smile['contato']], JSON_UNESCAPED_UNICODE));
$mim = ExtracaoVaga::doImagem('', $amostra('ocr_navegador_mimoria'));
confere('cartaz lido no navegador (Mimória): "CONSULTORDE" grudado pelo OCR, marca em 2 linhas confirmada pelo e-mail',
    $mim['titulo'] === 'Consultor de Vendas' && $mim['anunciante'] === 'Mimória Business' && str_contains($mim['contato'], 'mimoriabusiness@gmail.com')
    && $mim['cidade'] === 'Asa Sul' && $mim['salario_minimo'] === 2000.0 && $mim['tipo_vaga'] === 'pj',
    json_encode([$mim['titulo'], $mim['anunciante'], $mim['contato'], $mim['cidade'], $mim['salario_minimo']], JSON_UNESCAPED_UNICODE));
$rel = ExtracaoVaga::relatorio($vaga, 'Vendas');
confere('ExtracaoVaga::relatorio conta lidos, padrão e faltando', $rel['lidos'] + $rel['padrao'] + $rel['faltando'] === count($rel['itens']) && $rel['lidos'] >= 5
    && in_array('nivel_experiencia', array_column(array_filter($rel['itens'], fn($i) => $i['status'] === 'padrao'), 'campo'), true), json_encode([$rel['lidos'], $rel['padrao'], $rel['faltando']]));
confere('Pix: CRC16 do exemplo oficial do Banco Central = 1D3D', Pix::crc16('00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913Fulano de Tal6008BRASILIA62070503***6304') === '1D3D');
$pix = Pix::payload('teste@conectavagas.com', 'Conecta Vagas DF', 'Brasília');
confere('Pix: código copia e cola com CRC válido e cidade sem acento', str_contains($pix, '6008BRASILIA') && substr($pix, -4) === Pix::crc16(substr($pix, 0, -4)), $pix);
confere('like() trata % e _ como texto', like('50%_off') === '%50\\%\\_off%');

$curso = ExtracaoCurso::doTexto('EXCEL AVANÇADO — Curso online e gratuito da Fundação Bradesco. Carga horária: 12 horas. https://www.ev.org.br/cursos/excel');
confere('ExtracaoCurso: instituição, duração, gratuito e link', $curso['instituicao'] === 'Fundação Bradesco – Escola Virtual' && $curso['duracao'] === '12 horas'
    && $curso['gratuito'] === 1 && $curso['url'] === 'https://www.ev.org.br/cursos/excel', json_encode([$curso['instituicao'], $curso['duracao'], $curso['url']], JSON_UNESCAPED_UNICODE));

$lote = ExtracaoCurso::fichas("Aqui estão:\n**Título:** Excel Básico\n**Tipo:** E-book\n**Modalidade:** EAD\n**Área:** Informática e Excel\n**Link:** [ev](https://www.ev.org.br/x)\n---\nTítulo: Atendimento\nModalidade: Presencial\nCidade: Taguatinga/DF\nGratuito: Não\nPreço: R$ 120,00\nLink: https://www.senac.br/y\n---\nEspero ter ajudado!",
    ['Informática e Excel', 'Administração e Atendimento']);
confere('ExtracaoCurso::fichas: lote do Perplexity (markdown, e-book, presencial com cidade, preço, capa da instituição)', count($lote) === 2
    && $lote[0]['tipo'] === 'ebook' && $lote[0]['url'] === 'https://www.ev.org.br/x'
    && $lote[1]['modalidade'] === 'presencial' && $lote[1]['gratuito'] === 0 && $lote[1]['preco'] === 120.0 && str_contains($lote[1]['descricao'], 'Taguatinga/DF'),
    json_encode(array_map(fn($c) => [$c['titulo'], $c['tipo'], $c['modalidade'], $c['gratuito'], $c['preco'], $c['url']], $lote), JSON_UNESCAPED_UNICODE));
confere('ExtracaoCurso::capa segue a instituição (banner com marca nunca vai para outra)', ExtracaoCurso::capa('Fundação Bradesco – Escola Virtual') === 'assets/img/cursos/curso1.png'
    && ExtracaoCurso::capa('Escola Virtual.Gov (Enap)') === 'assets/img/cursos/curso3.png' && ExtracaoCurso::capa('Google (Grow with Google)') === 'assets/img/cursos/curso4.png'
    && ExtracaoCurso::capa('Banco Central do Brasil') === '' && ExtracaoCurso::capa('SENAC') === '' && $lote[1]['imagem'] === ''
    && ExtracaoCurso::capa('Sebrae', '', 'ebook') === '' && $lote[0]['imagem'] === '');   // banner anuncia "cursos": e-book fica com a capa do formato
confere('ExtracaoCurso::promptPesquisa usa as categorias do sistema', str_contains(ExtracaoCurso::promptPesquisa(['Área X']), 'Área: uma destas: Área X'));
confere('ExtracaoCurso: link com parênteses não é cortado (".../Cartilha%20(2)%20(1).pdf")',
    ExtracaoCurso::primeiroLink('Link: https://x.gov.br/Cartilha%20(2)%20(1).pdf') === 'https://x.gov.br/Cartilha%20(2)%20(1).pdf'
    && ExtracaoCurso::primeiroLink('(veja https://site.com/a).') === 'https://site.com/a');
$comImagem = ExtracaoCurso::fichas("Título: Guia X\nTipo: E-book\nLink: https://x.gov.br/guia.pdf\nImagem: https://x.gov.br/capa.jpg\n---\nTítulo: Curso Y\nLink: https://x.gov.br/y\nImagem: Não encontrada\n---");
confere('Padrão da ficha tem Imagem: o prompt pede e a máquina lê', str_contains(ExtracaoCurso::promptPesquisa([]), 'Imagem:')
    && ($comImagem[0]['imagem_url'] ?? '') === 'https://x.gov.br/capa.jpg' && ($comImagem[1]['imagem_url'] ?? null) === '');
// BIBLIOTECA: a ficha traz o link direto do PDF (campo "PDF:") e, na página do e-book, o PDF é achado sozinho.
$comPdf = ExtracaoCurso::fichas("Título: Guia Z\nTipo: E-book\nLink: https://z.gov.br/guia\nPDF: https://z.gov.br/arquivos/guia-z.pdf\n---\nTítulo: Guia W\nTipo: E-book\nLink: https://w.gov.br/w.pdf\nPDF: Não encontrado\n---");
$paginaDspace = '<html><head><meta name="citation_pdf_url" content="http://educapes.capes.gov.br/bitstream/capes/1/2/Cartilha%20A5.pdf"></head><body><a href="/termos.pdf">Termos</a></body></html>';
$paginaLinks = '<p><a href="/docs/edital.pdf">Edital</a> <a href="arquivos/ebook-final.pdf?v=2">Baixar o e-book</a></p>';
confere('Biblioteca: campo "PDF:" da ficha, prompt pede o PDF, PDF achado na página (metatag do repositório ou link "Baixar")',
    ($comPdf[0]['pdf_url'] ?? '') === 'https://z.gov.br/arquivos/guia-z.pdf' && ($comPdf[1]['pdf_url'] ?? null) === '' && ($comPdf[1]['url'] ?? '') === 'https://w.gov.br/w.pdf'
    && str_contains(ExtracaoCurso::promptPesquisa([]), 'PDF:') && str_contains(PromptsPesquisa::formatoFicha([]), 'PDF:')
    && ImagemRemota::pdfDaPagina($paginaDspace, 'https://educapes.capes.gov.br/handle/capes/1') === 'http://educapes.capes.gov.br/bitstream/capes/1/2/Cartilha%20A5.pdf'
    && ImagemRemota::pdfDaPagina($paginaLinks, 'https://site.org/livros/pagina') === 'https://site.org/livros/arquivos/ebook-final.pdf?v=2'
    && ImagemRemota::pdfDaPagina('<p>sem pdf</p>', 'https://site.org/') === '' && !(new CursoDAO())->guardarNaBiblioteca(1, 'https://fora.com/x.pdf', 'x')
    && ImagemRemota::pdfs(['http://127.0.0.1/x.pdf', 'file:///C:/Windows/win.ini']) === [],
    json_encode([$comPdf[0]['pdf_url'] ?? null, $comPdf[1]['pdf_url'] ?? null, ImagemRemota::pdfDaPagina($paginaLinks, 'https://site.org/livros/pagina')], JSON_UNESCAPED_UNICODE));
// Pesquisa guiada de cursos (FontesCursos): fonte pelo link, nome padronizado, lacunas e prompt direcionado.
confere('FontesCursos: reconhece a fonte oficial pelo link (subdomínio e gov.br/caminho) e padroniza o nome',
    FontesCursos::fonteDoLink('https://www.ev.org.br/cursos/x') === 'bradesco' && FontesCursos::fonteDoLink('https://sp.senai.br/c') === 'senai'
    && FontesCursos::fonteDoLink('https://www.gov.br/investidor/pt-br/a.pdf') === 'cvm' && FontesCursos::fonteDoLink('https://www.gov.br/outra') === ''
    && FontesCursos::fonteDoLink('https://senai.brasil.com') === '' && FontesCursos::nomeOficial('Fundação Bradesco - Escola Virtual', 'https://www.ev.org.br/c') === 'Fundação Bradesco – Escola Virtual'
    && FontesCursos::nomeOficial('Instituto X', 'https://x.org/c') === 'Instituto X' && ($lote[0]['instituicao'] ?? '') === 'Fundação Bradesco – Escola Virtual');
$cob = FontesCursos::cobertura([['tipo' => 'curso', 'categoria_nome' => 'A', 'url' => 'https://www.ev.org.br/1'], ['tipo' => 'ebook', 'categoria_nome' => 'A', 'url' => 'https://cartilha.cert.br/f.pdf'], ['tipo' => 'curso', 'categoria_nome' => 'B', 'url' => '']], ['A', 'B', 'C']);
$pr = FontesCursos::prompt(['formato' => 'ebook', 'quantidade' => 12], ['A', 'B', 'C'], $cob['lacunas'], ['https://www.ev.org.br/1']);
confere('FontesCursos: cobertura por área/fonte, lacunas e prompt (formato, lacunas, fontes, não repetir)', $cob['areas']['A']['total'] === 2 && $cob['fontes']['bradesco'] === 1
    && $cob['lacunas'] === ['C', 'B'] && str_contains($pr, 'liste 12 e-books') && str_contains($pr, 'MENOS conteúdo na plataforma: C, B')
    && str_contains($pr, 'site:cartilha.cert.br') && !str_contains($pr, 'site:learn.microsoft.com') && str_contains($pr, "NÃO repita")
    && str_contains($pr, 'https://www.ev.org.br/1') && substr_count($pr, 'CERT.br / NIC.br —') === 1 && str_contains($pr, 'Área: uma destas: A | B | C'));
$pf = FontesCursos::prompt(['fonte' => 'sebrae', 'area' => 'B', 'quantidade' => 99], ['A', 'B']);
confere('FontesCursos: prompt só numa fonte e numa área (quantidade limitada a 40)', str_contains($pf, 'SOMENTE nesta fonte') && str_contains($pf, 'SEBRAE —')
    && !str_contains($pf, 'Microsoft Learn') && str_contains($pf, 'Todos da área "B"') && str_contains($pf, 'liste 40 '));
// Prompt mestre (PromptsPesquisa): cada IA tem o seu, com os rótulos que a máquina lê; a ficha volta com TODOS os campos.
$okMestre = true;
foreach (array_keys(PromptsPesquisa::IAS) as $ia) {
    $pm = PromptsPesquisa::mestre($ia, ['Área X']);
    $okMestre = $okMestre && str_contains($pm, 'FORMATO DE CADA FICHA') && str_contains($pm, 'Área: uma destas: Área X') && str_contains($pm, 'NÃO ENCONTRADO');
}
$fichaIa = "**Título:** Guia Y\n**Tipo:** E-book\n**Instituição:** Banco Central\n**Modalidade:** EAD\n**Cidade:** Online\n**Nível:** Intermediário\n**Carga horária:** 3 horas\n"
    ."**Gratuito:** Não\n**Preço:** R$ 19,90\n**Área:** Área X\n**Link:** https://www.bcb.gov.br/guia.pdf [1]\n**Imagem:** https://www.bcb.gov.br/capa.png\n**Descrição:** Aprenda X. Com certificado.\n---\nNÃO ENCONTRADO: Curso Z — fora do ar";
$ida = ExtracaoCurso::fichas($fichaIa, ['Área X'])[0] ?? [];
confere('prompt mestre das 5 IAs e ida-e-volta da ficha (todos os campos do formulário)', $okMestre && count(ExtracaoCurso::fichas($fichaIa, ['Área X'])) === 1
    && ($ida['titulo'] ?? '') === 'Guia Y' && ($ida['tipo'] ?? '') === 'ebook' && ($ida['modalidade'] ?? '') === 'ead' && ($ida['nivel'] ?? '') === 'intermediario'
    && ($ida['duracao'] ?? '') === '3 horas' && ($ida['gratuito'] ?? 1) === 0 && ($ida['preco'] ?? null) === 19.9 && ($ida['categoria'] ?? '') === 'Área X'
    && ($ida['url'] ?? '') === 'https://www.bcb.gov.br/guia.pdf' && ($ida['imagem_url'] ?? '') === 'https://www.bcb.gov.br/capa.png'
    && ($ida['instituicao'] ?? '') === 'Banco Central do Brasil' && str_contains((string)($ida['descricao'] ?? ''), 'certificado'),
    json_encode(array_intersect_key($ida, array_flip(['titulo', 'tipo', 'nivel', 'duracao', 'gratuito', 'preco', 'categoria', 'url', 'imagem_url', 'instituicao'])), JSON_UNESCAPED_UNICODE));
$pa = PromptsPesquisa::avulso(["1. https://cartilha.cert.br/", "- Guia Z (SEBRAE)", '', 'https://cartilha.cert.br/'], ['Área X'], 'ebook');
confere('prompt avulso: links e títulos identificados, numeração e repetidos limpos, até 20', str_contains($pa, '1. https://cartilha.cert.br/ — LINK')
    && str_contains($pa, '2. Guia Z (SEBRAE) — TÍTULO') && !str_contains($pa, '3. https') && str_contains($pa, 'E-BOOKS')
    && count(PromptsPesquisa::entradas(array_map(fn($n) => "Curso $n", range(1, 30)))) === 20);
// Foto do currículo (padrão de foto): imagens sintéticas — um "retrato" (tons de pele, muitas cores) e um "logotipo".
if (function_exists('imagecreatetruecolor')) {
    $retrato = imagecreatetruecolor(120, 160);
    for ($y = 0; $y < 160; $y++) for ($x = 0; $x < 120; $x++) {
        $pele = ($x - 60) ** 2 / 900 + ($y - 60) ** 2 / 1600 < 1;   // "rosto" oval no meio
        imagesetpixel($retrato, $x, $y, $pele ? imagecolorallocate($retrato, 200 + ($x % 20), 150 + ($y % 25), 120 + (($x + $y) % 20)) : imagecolorallocate($retrato, 40 + $x % 60, 60 + $y % 70, 120 + ($x * $y) % 90));
    }
    ob_start(); imagepng($retrato); $pngRetrato = (string)ob_get_clean();
    $logo = imagecreatetruecolor(120, 120); imagefill($logo, 0, 0, imagecolorallocate($logo, 255, 255, 255));
    imagefilledrectangle($logo, 30, 30, 90, 90, imagecolorallocate($logo, 18, 87, 201));
    ob_start(); imagepng($logo); $pngLogo = (string)ob_get_clean();
    // PDF com a mesma foto gravada como FlateDecode (RGB cru) — o formato que o leitor antigo não enxergava.
    $rgb = '';
    for ($y = 0; $y < 160; $y++) for ($x = 0; $x < 120; $x++) { $c = imagecolorat($retrato, $x, $y); $rgb .= chr(($c >> 16) & 255).chr(($c >> 8) & 255).chr($c & 255); }
    $z = (string)gzcompress($rgb);
    $pdfFoto = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n"
        ."4 0 obj<</Type/XObject/Subtype/Image/Width 120/Height 160/ColorSpace/DeviceRGB/BitsPerComponent 8/Filter/FlateDecode/Length ".strlen($z).">>stream\n{$z}\nendstream\nendobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    $tmpPdf = sys_get_temp_dir().DIRECTORY_SEPARATOR.'smoke_foto_'.bin2hex(random_bytes(3)).'.pdf';
    file_put_contents($tmpPdf, $pdfFoto);
    $fotoPdf = LeitorDocumento::extrairFoto($tmpPdf);
    @unlink($tmpPdf);
    imagedestroy($retrato); imagedestroy($logo);
    confere('foto do currículo: retrato vence logotipo e a foto compactada do PDF (FlateDecode) é encontrada',
        LeitorDocumento::pontuacaoFoto($pngRetrato, 120, 160) > 1.0 && LeitorDocumento::pontuacaoFoto($pngLogo, 120, 120) === 0.0
        && $fotoPdf !== null && $fotoPdf['largura'] === 120 && $fotoPdf['altura'] === 160 && $fotoPdf['ext'] === 'jpg',
        json_encode(['retrato' => LeitorDocumento::pontuacaoFoto($pngRetrato, 120, 160), 'logo' => LeitorDocumento::pontuacaoFoto($pngLogo, 120, 120), 'pdf' => $fotoPdf ? $fotoPdf['largura'].'x'.$fotoPdf['altura'] : null]));
}
// Imagem padrão (cadastro sem imagem) e biblioteca (PDF da plataforma → "Baixar"; link da web → "Acessar").
confere('imagem padrão de cada formato existe e é reconhecida como "trocar imagem"', is_file(PUBLIC_DIR.'/'.CursoDAO::imagemPadrao('curso')) && is_file(PUBLIC_DIR.'/'.CursoDAO::imagemPadrao('ebook'))
    && is_file(PUBLIC_DIR.'/'.CursoDAO::imagemPadrao('video')) && CursoDAO::ehImagemPadrao('assets/img/padrao/ebook.jpg') && CursoDAO::ehImagemPadrao('') && !CursoDAO::ehImagemPadrao('assets/img/cursos/curso1.png'));
$acLocal = pt_acesso_conteudo(['url' => 'assets/uploads/biblioteca_20260101_abc123.pdf', 'titulo' => 'Guia Ágil', 'tipo' => 'ebook']);
$acWeb = pt_acesso_conteudo(['url' => 'https://www.gov.br/x.pdf', 'titulo' => 'X', 'tipo' => 'ebook']);
confere('botões: PDF da biblioteca = Baixar (download); link da web = Acessar (nova aba); currículo nunca é biblioteca',
    ($acLocal['curto'] ?? '') === 'Baixar' && str_contains($acLocal['atributos'] ?? '', 'download="guia-ágil.pdf"') && ($acWeb['curto'] ?? '') === 'Acessar'
    && str_contains($acWeb['atributos'] ?? '', 'target="_blank"') && pt_acesso_conteudo(['url' => '', 'tipo' => 'curso']) === null
    && !eh_pdf_biblioteca('assets/uploads/cv_4_2026.pdf') && !eh_pdf_biblioteca('assets/uploads/biblioteca_../x.pdf') && pt_acesso_conteudo(['url' => 'javascript:alert(1)']) === null);
// Ordenação e paginação das tabelas do painel.
$linhas = [['id' => 1, 'n' => 'Ética'], ['id' => 2, 'n' => 'abacaxi'], ['id' => 3, 'n' => null], ['id' => 4, 'n' => 'Curso 10'], ['id' => 5, 'n' => 'Curso 9']];
confere('ordenar_linhas: sem diferenciar maiúsculas/acentos, números naturais, vazios no fim', array_column(ordenar_linhas($linhas, 'n', 'asc'), 'id') === [2, 5, 4, 1, 3]
    && array_column(ordenar_linhas($linhas, 'n', 'desc'), 'id') === [1, 4, 5, 2, 3]
    && array_column(ordenar_linhas([['id' => 1, 'v' => '10'], ['id' => 2, 'v' => '9'], ['id' => 3, 'v' => '9']], 'v', 'desc'), 'id') === [1, 3, 2]);   // empate: segue a direção pelo id (como "created_at DESC, id DESC" no banco)
$_GET = ['pagina' => '99', 'ordem' => 'x; DROP', 'dir' => 'baixo', 'edit' => '5', 'tipo' => 'ebook'];
[$pg, $numPg, $totPg] = paginar(range(1, 30), 25);
confere('paginar, lista_ordem e painel_qs: página no limite, campo fora da lista cai no padrão, edit não volta para a lista',
    $pg === [26, 27, 28, 29, 30] && $numPg === 2 && $totPg === 2 && lista_ordem(['nome'], 'nome', 'desc') === ['nome', 'desc']
    && (parse_str(substr(painel_qs(['ordem' => 'nome']), 1), $qsPainel) ?? true) && $qsPainel == ['ordem' => 'nome', 'dir' => 'baixo', 'tipo' => 'ebook', 'pagina' => '99']
    && painel_qs(['pagina' => 1, 'ordem' => '', 'dir' => '']) === '?tipo=ebook');
$_GET = [];
confere('ImagemRemota: acha a imagem da página (og:image, "Imagem do curso", galeria de loja) e pula a provisória',
    ImagemRemota::daPagina('<meta content="/img/c.png" property="og:image">', 'https://s.gov.br/cursos/1') === 'https://s.gov.br/img/c.png'
    && ImagemRemota::daPagina('<img src="https://cdn.x.br/imagem_curso_1.jpg" alt="Imagem do curso: X">', 'https://x.br/') === 'https://cdn.x.br/imagem_curso_1.jpg'
    && ImagemRemota::candidatas('<meta property="og:image" content="https://l.br/media/catalog/product/placeholder/a.png"><script>{"full":"https:\/\/l.br\/media\/catalog\/product\/l\/m\/lms_img_9.png"}</script>', 'https://l.br/p') === ['https://l.br/media/catalog/product/l/m/lms_img_9.png']
    && ImagemRemota::daPagina('<meta property="og:image" content="javascript:alert(1)">', 'https://x.br/') === '');
confere('ImagemRemota: só servidor público (nada de localhost ou rede interna)', !ImagemRemota::linkPublico('http://127.0.0.1/a.jpg') && !ImagemRemota::linkPublico('http://localhost/a.jpg')
    && !ImagemRemota::linkPublico('http://192.168.0.10/a.jpg') && !ImagemRemota::linkPublico('http://[::1]/a.jpg') && !ImagemRemota::linkPublico('ftp://x.com/a.jpg'));
confere('Competencias: "construção de um ambiente", "proteção de dados" e "cobrança por consumo" não viram competência',
    Competencias::extrair('a construção de um ambiente positivo, proteção de dados e cobrança por consumo') === []
    && Competencias::extrair('Operador de cobrança') === ['Telemarketing'] && in_array('Construção civil', Competencias::extrair('Eletricista de obra'), true));
confere('Competencias::doCurso: a área só entra quando título e descrição não dizem nada',
    !in_array('UX e design', Competencias::doCurso(['titulo' => 'Power BI', 'descricao' => 'painéis e indicadores', 'categoria_nome' => 'Marketing, Dados e UX']), true)
    && Competencias::doCurso(['titulo' => 'Módulo 1', 'descricao' => '', 'categoria_nome' => 'Informática e Excel']) === ['Excel', 'Informática']);

$candidato = ['perfil' => ['cidade' => 'Taguatinga', 'uf' => 'DF', 'nivel_experiencia' => 'junior'], 'competencias' => ['Vendas', 'Excel'],
              'tokens_titulo' => ['vendedor' => true], 'tokens_historico' => [], 'mudanca' => false, 'viagens' => false, 'cnh' => '', 'pcd' => false];
$r = (new MatchService())->calcular($candidato, ['titulo' => 'Vendedor', 'requisitos' => 'vendas e Excel', 'cidade' => 'Taguatinga', 'uf' => 'DF', 'nivel_experiencia' => 'junior', 'remoto' => 'presencial']);
confere('MatchService: candidato ideal = 100 (excelente)', $r['pontuacao'] === 100.0 && $r['nivel'] === 'excelente', $r['pontuacao'].' / '.$r['nivel']);
confere('classe_match() usa os mesmos cortes do match', classe_match(75) === 'excelente' && classe_match(55) === 'alto' && classe_match(35) === 'medio' && classe_match(10) === 'baixo');

$exp = Portfolio::experiencias("ATACADÃO DIA A DIA\nAuxiliar Administrativo\n2021 – 2024");
confere('Portfolio::experiencias separa empresa, cargo e período', ($exp[0]['empresa'] ?? '') === 'ATACADÃO DIA A DIA' && ($exp[0]['cargo'] ?? '') === 'Auxiliar Administrativo', json_encode($exp[0] ?? null, JSON_UNESCAPED_UNICODE));

confere('pt_secao_formato: cada formato tem a sua página (cursos, e-books, vídeos)', pt_secao_formato('curso')[1] === url('cursos.php')
    && pt_secao_formato('ebook')[1] === url('cursos.php?tipo=ebook') && pt_secao_formato('video')[1] === url('cursos.php?tipo=video')
    && pt_secao_formato('xyz')[0] === 'Cursos');

// ------------------------------------------------------------
echo PHP_EOL.'2b. Calibrador das máquinas de extração (termos em memória, sem banco)'.PHP_EOL;
$anuncio = "ATENDENTE\nUniforme fornecido pela empresa\nVenha trabalhar na Padaria Pão Quente";
Calibrador::ligar(true);
Calibrador::usarTermos([
    ['contexto' => 'vaga_linha', 'termo' => 'Uniforme', 'destino' => 'beneficios'],
    ['contexto' => 'vaga_linha', 'termo' => 'Vale', 'destino' => 'requisitos'],
    ['contexto' => 'vaga_linha', 'termo' => 'Vale-refeição', 'destino' => 'beneficios'],
    ['contexto' => 'vaga_categoria', 'termo' => 'Churrasqueiro', 'destino' => 'Alimentação'],
    ['contexto' => 'curriculo_linha', 'termo' => 'Ensino médio', 'destino' => 'formacao'],
    ['contexto' => 'contexto_que_nao_existe', 'termo' => 'Uniforme', 'destino' => 'descricao'],
]);
Calibrador::usarNomes('empresa', ['Padaria Pão Quente', 'Loja', 'Empresa Teste']);
$comCal = ExtracaoVaga::doTexto($anuncio);
Calibrador::ligar(false);
$soRegra = ExtracaoVaga::doTexto($anuncio);
confere('Termo calibrado tira "Uniforme" da descrição e põe em benefícios (e registra o termo)',
    str_contains($comCal['beneficios'], 'Uniforme') && !str_contains($comCal['descricao'], 'Uniforme') && str_contains($soRegra['descricao'], 'Uniforme')
    && in_array('Uniforme', array_column($comCal['calibrador'], 'termo'), true), json_encode([$comCal['beneficios'], $comCal['descricao'], $comCal['calibrador']], JSON_UNESCAPED_UNICODE));
confere('Nome conhecido: empresa já cadastrada é reconhecida quando nenhuma regra acha', $comCal['anunciante'] === 'Padaria Pão Quente' && $soRegra['anunciante'] === '',
    $comCal['anunciante'].' / '.$soRegra['anunciante']);
Calibrador::ligar(true);
confere('Vence o termo mais longo ("vale-refeição" ganha de "vale")', Calibrador::decidir('vaga_linha', 'Vale refeição de R$ 30', 'descricao')['classe'] === 'beneficios'
    && Calibrador::decidir('vaga_linha', 'Vale muito a pena', 'descricao')['classe'] === 'requisitos');
confere('Termo vale como palavra inteira ("vale" não pega "valeu")', Calibrador::decidir('vaga_linha', 'Valeu pela atenção', 'descricao')['origem'] === 'regra');
confere('Sem acento e sem maiúscula: "CHURRASQUEIRO" casa com o termo "Churrasqueiro"',
    Calibrador::decidir('vaga_categoria', 'CHURRASQUEIRO COM EXPERIÊNCIA', 'Vendas') === ['classe' => 'Alimentação', 'origem' => 'calibrador', 'termo' => 'Churrasqueiro']);
confere('Termo que concorda com a regra fica registrado como regra; contexto inválido é ignorado',
    Calibrador::decidir('vaga_linha', 'Uniforme', 'beneficios')['origem'] === 'regra' && Calibrador::termoQueCasa('contexto_que_nao_existe', 'Uniforme') === null);
confere('Nome genérico ("Loja", "Empresa Teste", "Salário") não vira nome conhecido; nome de verdade entra',
    Calibrador::nomes('empresa') === ['Padaria Pão Quente'] && Calibrador::nomeGenerico('Salário') && !Calibrador::nomeGenerico('Grupo Dourado'));
$cvTexto = "Maria Souza\nAtendente\nEnsino médio completo na Escola Classe 10\nBrasileira, solteira, 25 anos\nEXPERIÊNCIA\nVendedora - Loja X";
$cvCal = ExtracaoCurriculo::extrairCampos($cvTexto);
Calibrador::ligar(false);
$cvRegra = ExtracaoCurriculo::extrairCampos($cvTexto);
confere('Currículo: linha solta com termo calibrado ("Ensino médio") vai para Formação',
    str_contains(Competencias::normalizar($cvCal['formacao']), 'ensino medio') && !str_contains(Competencias::normalizar($cvRegra['formacao']), 'ensino medio'),
    json_encode([$cvCal['formacao'], $cvRegra['formacao']], JSON_UNESCAPED_UNICODE));
confere('Currículo: dado pessoal do cabeçalho ("Brasileira, solteira, 25 anos") nunca vai para Experiências',
    !str_contains(Competencias::normalizar($cvCal['experiencias']), 'solteira'), $cvCal['experiencias']);
Calibrador::limpar();


$_SERVER['REQUEST_URI'] = BASE_URL.'vaga.php?id=3';
confere('Router::caminhoPedido() → "vaga.php"', Router::caminhoPedido() === 'vaga.php', Router::caminhoPedido());

// ------------------------------------------------------------
echo PHP_EOL.'3. Banco de dados ('.DB_NAME.')'.PHP_EOL;
try {
    $db = Database::getConexao();
    confere('conexão PDO', true);
    $tabelas = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $esperadas = ['assinaturas','calibracao_extracao','candidaturas','categorias','curriculos','cursos','matches','perfis','redefinicoes_senha','tentativas_login','usuarios','vagas'];
    confere('12 tabelas do database/schema.sql', !array_diff($esperadas, $tabelas), implode(', ', array_diff($esperadas, $tabelas)));
    // O calibrador cria a tabela dele se o banco for de uma versão anterior; os termos ativos e os nomes conhecidos carregam.
    $termosAtivos = (new CalibracaoDAO())->ativos();
    confere('calibrador: termos ativos e nomes conhecidos carregam do banco', is_array($termosAtivos) && is_array((new CalibracaoDAO())->nomesConhecidos('empresa')),
        count($termosAtivos).' termo(s)');
    $contas = $db->query("SELECT email FROM usuarios WHERE email IN ('admin@conectavagas.com','empresa@conectavagas.com','candidato@conectavagas.com')")->fetchAll(PDO::FETCH_COLUMN);
    confere('contas de teste do database/seed.sql', count($contas) === 3, count($contas).' de 3 encontradas');
    $senhasReadme = ['admin@conectavagas.com' => 'Admin@123', 'empresa@conectavagas.com' => 'Empresa@123', 'candidato@conectavagas.com' => 'Candidato@123'];
    $naoEntram = [];
    foreach ($senhasReadme as $em => $sn) { $u = (new UsuarioDAO())->buscarPorEmail($em); if (!$u || !(int)$u['ativo'] || !password_verify($sn, (string)$u['senha'])) $naoEntram[] = $em; }
    $hAdmin = (string)((new UsuarioDAO())->buscarPorEmail('admin@conectavagas.com')['senha'] ?? '');
    $aceita = fn(string $digitada) => (bool)array_filter(UsuarioDAO::variantesSenha($digitada), fn($v) => password_verify($v, $hAdmin));
    confere('login tolera 1ª letra trocada, Caps Lock e espaços, mas não outra senha', $aceita('admin@123') && $aceita('aDMIN@123') && $aceita(' Admin@123 ')
        && !$aceita('admin@1234') && !$aceita('ADMIN@123x') && count(UsuarioDAO::variantesSenha('Abc')) <= 4);
    confere('contas de teste entram com as senhas do README', !$naoEntram, implode(', ', $naoEntram).' → rode: C:\xampp\php\php.exe database\resetar_senhas.php');
    confere('vagas abertas listadas pelo VagaDAO', count((new VagaDAO())->listar(true)) > 0);
    $cruzadas = (int)$db->query("SELECT COUNT(*) FROM vagas v JOIN categorias c ON c.id=v.categoria_id WHERE c.tipo<>'vaga'")->fetchColumn()
              + (int)$db->query("SELECT COUNT(*) FROM cursos cu JOIN categorias c ON c.id=cu.categoria_id WHERE c.tipo<>'curso'")->fetchColumn();
    confere('nenhuma vaga em categoria de curso (nem curso em categoria de vaga)', $cruzadas === 0, "$cruzadas item(ns) na categoria do tipo errado");
    $semFormato = (int)$db->query("SELECT COUNT(*) FROM cursos WHERE tipo NOT IN ('".implode("','", CursoDAO::TIPOS)."')")->fetchColumn();
    confere('todo conteúdo tem formato válido (curso, e-book ou vídeo)', $semFormato === 0, "$semFormato conteúdo(s) sem formato");
    // Padrão da plataforma: tudo o que está publicado aparece com a sua imagem (e o arquivo existe).
    $temArquivo = fn(string $img) => $img !== '' && (($up = caminho_upload($img)) !== null ? is_file($up) : (preg_match('#^https?://#', $img) === 1 || is_file(PUBLIC_DIR.'/'.$img)));
    $semImagem = array_map(fn($c) => '#'.$c['id'], array_filter((new CursoDAO())->listar(true), fn($c) => !$temArquivo((string)$c['imagem'])));
    confere('todo curso e e-book publicado tem imagem (e o arquivo existe)', !$semImagem, implode(', ', $semImagem));
    $vagasSemImagem = array_map(fn($v) => '#'.$v['id'], array_filter((new VagaDAO())->listar(true), fn($v) => !$temArquivo((string)$v['imagem'])));
    confere('toda vaga aberta tem imagem (e o arquivo existe)', !$vagasSemImagem, implode(', ', $vagasSemImagem));
} catch (Throwable $e) {
    confere('conexão com o banco', false, $e->getMessage());
}

// ------------------------------------------------------------
$base = $argv[1] ?? null;
if ($base === null) {
    // Padrão: a pasta do projeto dentro do htdocs (ex.: http://localhost/TCC%20v2/TCC_GUSTAVO/).
    $rel = preg_split('#[\\\\/]htdocs[\\\\/]#i', ROOT_DIR)[1] ?? '';
    $base = 'http://localhost/'.implode('/', array_map('rawurlencode', preg_split('#[\\\\/]#', $rel) ?: [])).'/';
}
$base = rtrim($base, '/').'/';
echo PHP_EOL."4. Páginas (HTTP) em $base".PHP_EOL;
$html = '';
$status = function (string $caminho) use ($base, &$html): int {
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
    $html = (string)@file_get_contents($base.$caminho, false, $ctx);
    return (int)(preg_match('#^HTTP/\S+ (\d{3})#', $http_response_header[0] ?? '', $m) ? $m[1] : 0);
};
if ($status('') === 0) {
    confere('Apache respondendo', false, 'ligue o Apache no XAMPP ou informe o endereço: php tests\smoke.php http://localhost/sua%20pasta/');
} else {
    foreach (['' => 200, 'vagas.php' => 200, 'vaga.php?id=1' => 200, 'cursos.php' => 200, 'cursos.php?tipo=ebook' => 200, 'cursos.php?tipo=video' => 200, 'curso.php?id=1' => 200,
              'cursos.php?pagina=99' => 200, 'cursos.php?tipo=xyz' => 200,
              'planos.php' => 200, 'login.php' => 200, 'cadastro.php' => 200, 'esqueci_senha.php' => 200, 'contrato.php' => 200,
              'assets/css/app.css' => 200, 'vaga.php?id=999999' => 404, 'nao-existe.php' => 404,
              // Leitor de cartaz da plataforma: tudo servido pelo próprio site (nada instalado no servidor).
              'assets/js/leitor-cartaz.js' => 200, 'assets/js/vendor/tesseract/tesseract.min.js' => 200, 'assets/js/vendor/tesseract/worker.min.js' => 200,
              'assets/js/vendor/tesseract/core/tesseract-core-simd-lstm.wasm.js' => 200, 'assets/js/vendor/tesseract/lang/por.traineddata.gz' => 200,
              'view/perfil/index.php' => 302, 'admin/index.php' => 302, 'admin/pages/calibrador.php' => 302,
              'admin/pages/assinaturas.php' => 302, 'download.php?id=1' => 302] as $caminho => $esperado) {
        $s = $status($caminho);
        confere(sprintf('%-24s → %d', $caminho === '' ? '/' : $caminho, $esperado), $s === $esperado, "recebeu $s");
    }
    // Cada página de conteúdo só mostra o seu formato (o chapéu do cartão diz "Curso", "E-book" ou "Vídeo").
    foreach (['cursos.php' => 'Curso', 'cursos.php?tipo=ebook' => 'E-book', 'cursos.php?tipo=video' => 'Vídeo'] as $caminho => $formato) {
        $status($caminho);
        preg_match_all('#class="cv-chapeu"><span>([^<·]+?)(?: ·|</span>)#u', $html, $m);
        $outros = array_diff(array_unique(array_map('trim', $m[1])), [$formato]);
        confere(sprintf('%-24s só com %s', $caminho, $formato), !$outros && str_contains($html, 'class="ativo" aria-current="page"'), 'também aparece: '.implode(', ', $outros));
    }
    // E-book aparece com a capa inteira (cartão em pé); o menu não tem item "Vídeos" (os vídeos ficam junto dos e-books)
    // e a aba de vídeos só aparece quando houver algum.
    $status('cursos.php?tipo=ebook');
    confere('e-books com a capa em pé, sem cartão sem imagem', str_contains($html, 'cv-card-ebook') && str_contains($html, 'an-cartaz') && !str_contains($html, 'cv-img-vazia'));
    preg_match('#<nav id="menu-principal".*?</nav>#s', $html, $menu);
    confere('menu sem item "Vídeos" e sem aba de vídeos vazia', ($menu[0] ?? '') !== '' && !str_contains($menu[0], 'Vídeos') && !preg_match('#Vídeos <small>\(0\)#u', $html));
    // Página inicial: vagas, cursos e e-books na mesma vitrine rotativa (5 na tela + fila), cada uma no seu ritmo.
    $status('');
    preg_match_all('#data-rotativo="(\d+)"#', $html, $rit);
    $filas = substr_count($html, '<template data-rotativo-fila>');
    confere('início: vitrine rotativa nas vagas, nos cursos e nos e-books (ritmos diferentes)', count($rit[1]) >= 3 && $filas === count($rit[1])
        && count(array_unique($rit[1])) === count($rit[1]) && str_contains($html, 'Os cursos se revezam aqui') && str_contains($html, 'Os e-books se revezam aqui'),
        count($rit[1]).' vitrine(s): '.implode(', ', $rit[1]));
    // Blindagem HTTP: cabeçalhos de segurança, versão do PHP escondida e nenhum .php solto em public/ executa.
    $cab = array_change_key_case((array)@get_headers($base, true), CASE_LOWER);
    confere('cabeçalhos de segurança (CSP, nosniff, frame, permissions) e sem X-Powered-By', !isset($cab['x-powered-by'])
        && str_contains((string)($cab['content-security-policy'] ?? ''), "form-action 'self'") && ($cab['x-content-type-options'] ?? '') === 'nosniff'
        && ($cab['x-frame-options'] ?? '') === 'SAMEORIGIN' && isset($cab['permissions-policy']));
    $plantado = PUBLIC_DIR.'/assets/_smoke_'.bin2hex(random_bytes(3)).'.php';
    if (@file_put_contents($plantado, '<?php echo "EXECUTOU";') !== false) {
        $sPlant = $status('assets/'.basename($plantado));
        @unlink($plantado);
        confere('.php colocado em public/assets não executa (403)', $sPlant === 403 && !str_contains($html, 'EXECUTOU'), "recebeu $sPlant");
    }
    foreach (['config/config.php', 'config/cacert.pem', 'app/Core/Database.php', 'database/schema.sql', 'storage/.gitkeep', 'tests/smoke.php'] as $interno) {
        $s = $status($interno);
        confere(sprintf('%-24s bloqueado', $interno), in_array($s, [403, 404], true), "recebeu $s");
    }
}

echo PHP_EOL.($falhas ? "RESULTADO: {$falhas} falha(s)." : 'RESULTADO: tudo certo.').PHP_EOL;
exit($falhas ? 1 : 0);
