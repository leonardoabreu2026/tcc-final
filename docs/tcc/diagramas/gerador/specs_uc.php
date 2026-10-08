<?php
// Especificação dos diagramas de caso de uso (posições em colunas e linhas; ver ucdraw.php).
// casos: id => [rótulo, coluna, linha]   atores: nome => ['x' => .., 'y' => .. (opcional)]
// ligacoes: [ator, caso]   relacoes: [de, para, 'include'|'extend']  (extend: do caso que estende para o caso base)

$visao = function (string $ator, array $casos, bool $direita = false): array {
    $c = []; $lig = [];
    foreach (array_values($casos) as $i => [$id, $rot]) { $c[$id] = [$rot, 0, $i]; $lig[] = [$ator, $id]; }
    return ['nome' => '', 'sistema' => 'Visão do '.$ator, 'colunas' => [$direita ? 360 : 200], 'direita' => $direita ? [0] : [], 'topo' => 70, 'passo' => 48,
            'casos' => $c, 'atores' => [$ator => ['x' => $direita ? 470 : 60]], 'ligacoes' => $lig];
};

return [
'uc01_geral' => ['nome' => 'Caso de Uso Geral (visão de cada personagem)', 'colunasGrade' => 2, 'quadros' => [
    $visao('Candidato', [['UC01', 'UC01 Realizar login'], ['UC02', 'UC02 Cadastrar conta'], ['UC03', 'UC03 Recuperar senha'],
        ['UC04', 'UC04 Consultar vagas'], ['UC05', 'UC05 Consultar cursos e e-books'], ['UC06', 'UC06 Manter perfil do candidato'],
        ['UC07', 'UC07 Enviar currículo'], ['UC08', 'UC08 Visualizar portfólio e match'], ['UC09', 'UC09 Manter candidatura'],
        ['UC14', 'UC14 Manter assinatura (planos)'], ['UC19', 'UC19 Excluir a própria conta']]),
    $visao('Empresa', [['UC01', 'UC01 Realizar login'], ['UC02', 'UC02 Cadastrar conta'], ['UC03', 'UC03 Recuperar senha'],
        ['UC10', 'UC10 Manter perfil da empresa'], ['UC11', 'UC11 Manter vaga'], ['UC12', 'UC12 Gerenciar candidaturas recebidas'],
        ['UC13', 'UC13 Consultar banco de talentos'], ['UC14', 'UC14 Manter assinatura (planos)'], ['UC18', 'UC18 Visualizar painel'],
        ['UC21', 'UC21 Extrair com os padrões automáticos']]),
    $visao('Administrador', [['UC01', 'UC01 Realizar login'], ['UC03', 'UC03 Recuperar senha'], ['UC11', 'UC11 Manter vaga'],
        ['UC12', 'UC12 Gerenciar candidaturas'], ['UC15', 'UC15 Manter usuário'], ['UC16', 'UC16 Manter categoria'],
        ['UC17', 'UC17 Manter curso/e-book'], ['UC18', 'UC18 Visualizar painel'], ['UC20', 'UC20 Gerenciar assinaturas'],
        ['UC21', 'UC21 Extrair com os padrões automáticos']]),
    $visao('Sistema', [['UC07', 'UC07 Enviar currículo (ler o arquivo)'], ['UC08', 'UC08 Visualizar portfólio e match (calcular)'],
        ['UC17', 'UC17 Manter curso/e-book (abrir os links)'], ['UC18', 'UC18 Visualizar painel (limpeza diária)'],
        ['UC21', 'UC21 Extrair com os padrões automáticos']], true),
]],

'uc02_login' => ['nome' => 'Realizar login', 'colunas' => [195, 455], 'topo' => 100, 'passo' => 70,
    'casos' => ['L' => ['Realizar login', 0, 0.5], 'S' => ["Encerrar sessão\n(sair)", 0, 2], 'B' => ["Pausar o login após\ntentativas repetidas", 1, 0.5]],
    'atores' => ['Candidato' => ['x' => 60, 'y' => 100], 'Empresa' => ['x' => 60, 'y' => 205], 'Administrador' => ['x' => 60, 'y' => 310], 'Sistema' => ['x' => 760]],
    'ligacoes' => [['Candidato', 'L'], ['Empresa', 'L'], ['Administrador', 'L'], ['Candidato', 'S'], ['Empresa', 'S'], ['Administrador', 'S'], ['Sistema', 'B']],
    'relacoes' => [['B', 'L', 'extend']]],

'uc03_acesso_conta' => ['nome' => 'Acesso à conta', 'colunas' => [215, 485], 'topo' => 100, 'passo' => 70,
    'casos' => ['CC' => ["Cadastrar conta\n(candidato ou empresa)", 0, 0], 'T' => ['Aceitar termo LGPD', 1, 0],
                'S' => ["Solicitar redefinição\nde senha", 0, 1.6], 'R' => ["Redefinir senha (link com\ntoken de uso único)", 0, 2.8]],
    'atores' => ['Candidato' => ['x' => 60, 'y' => 115], 'Empresa' => ['x' => 60, 'y' => 230], 'Administrador' => ['x' => 60, 'y' => 330]],
    'ligacoes' => [['Candidato', 'CC'], ['Empresa', 'CC'], ['Candidato', 'S'], ['Empresa', 'S'], ['Administrador', 'S'], ['Candidato', 'R'], ['Empresa', 'R'], ['Administrador', 'R']],
    'relacoes' => [['CC', 'T', 'include']]],

'uc04_consultas' => ['nome' => 'Consultas públicas', 'colunas' => [165, 395, 625], 'topo' => 100, 'passo' => 70,
    'casos' => ['CV' => ["Consultar vagas\n(busca e filtros)", 0, 0], 'DV' => ["Visualizar detalhe\nda vaga", 1, 0], 'NM' => ['Exibir nota de match', 2, 0],
                'CC' => ["Consultar cursos\ne e-books", 0, 1.6], 'DC' => ["Visualizar detalhe\ndo curso", 1, 1.6], 'BP' => ["Baixar o PDF da\nbiblioteca", 2, 1.6]],
    'atores' => ['Candidato' => ['x' => 60], 'Sistema' => ['x' => 930, 'y' => 100]],
    'ligacoes' => [['Candidato', 'CV'], ['Candidato', 'CC'], ['Sistema', 'NM']],
    'relacoes' => [['DV', 'CV', 'extend'], ['NM', 'DV', 'extend'], ['DC', 'CC', 'extend'], ['BP', 'DC', 'extend']]],

'uc05_perfil_candidato' => ['nome' => 'Perfil do candidato', 'colunas' => [185, 455], 'topo' => 100, 'passo' => 62,
    'casos' => ['EC' => ['Enviar currículo', 0, 0], 'AR' => ["Aplicar dados\ndo relatório", 0, 1], 'XC' => ['Cancelar currículo', 0, 2],
                'AP' => ["Alterar perfil\n(cadastro)", 0, 3], 'VP' => ['Visualizar portfólio', 0, 4.4], 'XA' => ["Excluir a própria\nconta (LGPD)", 0, 5.6],
                'EX' => ["Extrair dados e foto\ndo currículo", 1, 0], 'RM' => ['Recalcular match', 1, 3.7], 'CS' => ['Confirmar com a senha', 1, 5.6]],
    'atores' => ['Candidato' => ['x' => 60], 'Sistema' => ['x' => 770, 'y' => 230]],
    'ligacoes' => [['Candidato', 'EC'], ['Candidato', 'AR'], ['Candidato', 'XC'], ['Candidato', 'AP'], ['Candidato', 'VP'], ['Candidato', 'XA'], ['Sistema', 'EX'], ['Sistema', 'RM']],
    'relacoes' => [['EC', 'EX', 'include'], ['AP', 'RM', 'include'], ['RM', 'VP', 'extend'], ['XA', 'CS', 'include']]],

'uc06_candidatura' => ['nome' => 'Candidatura', 'colunas' => [185, 455], 'topo' => 100, 'passo' => 66,
    'casos' => ['CA' => ['Candidatar-se à vaga', 0, 0], 'CC' => ['Cancelar candidatura', 0, 1.2], 'AS' => ["Acompanhar status\ne retorno", 0, 2.4],
                'VL' => ["Verificar limite\ndo plano", 1, 0]],
    'atores' => ['Candidato' => ['x' => 60]],
    'ligacoes' => [['Candidato', 'CA'], ['Candidato', 'CC'], ['Candidato', 'AS']],
    'relacoes' => [['CA', 'VL', 'include']]],

'uc07_manter_vaga' => ['nome' => 'Manter vaga', 'colunas' => [175, 425, 695], 'topo' => 100, 'passo' => 66,
    'casos' => ['CV' => ['Cadastrar vaga', 0, 1], 'AV' => ['Alterar vaga', 0, 2.6], 'EV' => ['Cancelar vaga', 0, 3.6],
                'XT' => ["Extrair vaga do texto\ndo anúncio", 1, 0], 'LC' => ["Ler o cartaz no navegador\n(OCR, com carregador)", 1, 1.4], 'RM' => ["Recalcular match\nda vaga", 1, 2.6],
                'CB' => ["Aplicar os padrões\nautomáticos", 2, 0]],
    'atores' => ['Empresa' => ['x' => 60, 'y' => 150], 'Administrador' => ['x' => 60, 'y' => 300], 'Sistema' => ['x' => 1010, 'y' => 230]],
    'ligacoes' => [['Empresa', 'CV'], ['Empresa', 'AV'], ['Empresa', 'EV'], ['Administrador', 'CV'], ['Administrador', 'AV'], ['Administrador', 'EV'],
                   ['Sistema', 'LC'], ['Sistema', 'RM'], ['Sistema', 'CB']],
    'relacoes' => [['XT', 'CV', 'extend'], ['LC', 'CV', 'extend'], ['CV', 'RM', 'include'], ['AV', 'RM', 'include'], ['XT', 'CB', 'include']]],

'uc08_area_empresa' => ['nome' => 'Área da empresa', 'colunas' => [225], 'topo' => 100, 'passo' => 58,
    'casos' => ['MP' => ["Manter perfil\nda empresa", 0, 0], 'BT' => ["Consultar banco\nde talentos", 0, 1], 'ST' => ["Atualizar status e\nretorno da candidatura", 0, 2],
                'BC' => ['Baixar currículo', 0, 3], 'EX' => ['Cancelar candidatura', 0, 4]],
    'atores' => ['Empresa' => ['x' => 60, 'y' => 160], 'Administrador' => ['x' => 600, 'y' => 270]],
    'ligacoes' => [['Empresa', 'MP'], ['Empresa', 'BT'], ['Empresa', 'ST'], ['Empresa', 'BC'], ['Administrador', 'ST'], ['Administrador', 'BC'], ['Administrador', 'EX']]],

'uc09_planos' => ['nome' => 'Planos', 'colunas' => [215], 'topo' => 100, 'passo' => 70,
    'casos' => ['AP' => ["Assinar plano\n(VIP ou Premium)", 0, 0], 'CA' => ['Cancelar assinatura', 0, 1]],
    'atores' => ['Candidato' => ['x' => 60], 'Empresa' => ['x' => 580]],
    'ligacoes' => [['Candidato', 'AP'], ['Candidato', 'CA'], ['Empresa', 'AP'], ['Empresa', 'CA']]],

'uc10_manter_usuario' => ['nome' => 'Manter usuário', 'colunas' => [215], 'topo' => 100, 'passo' => 56,
    'casos' => ['C' => ['Cadastrar usuário', 0, 0], 'U' => ['Alterar usuário', 0, 1], 'T' => ["Ativar ou desativar\nusuário", 0, 2], 'E' => ['Cancelar conta', 0, 3]],
    'atores' => ['Administrador' => ['x' => 60]],
    'ligacoes' => [['Administrador', 'C'], ['Administrador', 'U'], ['Administrador', 'T'], ['Administrador', 'E']]],

'uc11_manter_categoria' => ['nome' => 'Manter categoria', 'colunas' => [215], 'topo' => 100, 'passo' => 56,
    'casos' => ['C' => ['Cadastrar categoria', 0, 0], 'U' => ['Alterar categoria', 0, 1], 'T' => ["Ativar ou desativar\ncategoria", 0, 2], 'E' => ['Cancelar categoria', 0, 3]],
    'atores' => ['Administrador' => ['x' => 60]],
    'ligacoes' => [['Administrador', 'C'], ['Administrador', 'U'], ['Administrador', 'T'], ['Administrador', 'E']]],

'uc12_manter_curso' => ['nome' => 'Manter curso/e-book', 'colunas' => [175, 425, 685, 935], 'topo' => 100, 'passo' => 62,
    'casos' => ['C' => ['Cadastrar curso/e-book', 0, 1], 'U' => ['Alterar curso/e-book', 0, 3], 'P' => ['Publicar ou ocultar', 0, 4], 'E' => ['Cancelar curso/e-book', 0, 5],
                'T' => ["Trazer os PDFs para\na biblioteca", 0, 6],
                'X' => ["Extrair do texto ou da\nficha da pesquisa", 1, 0], 'L' => ["Importar fichas em lote\n(prévia)", 1, 1.2], 'G' => ["Guardar o PDF na\nbiblioteca", 1, 2.4],
                'CB' => ["Aplicar os padrões\nautomáticos", 2, 0], 'AL' => ["Abrir os links da ficha\n(imagem, PDF ou página)", 2, 1.2],
                'CP' => ["Tirar a capa da\n1ª página do PDF", 3, 2.4]],
    'atores' => ['Administrador' => ['x' => 60], 'Sistema' => ['x' => 1230, 'y' => 140]],
    'ligacoes' => [['Administrador', 'C'], ['Administrador', 'U'], ['Administrador', 'P'], ['Administrador', 'E'], ['Administrador', 'T'],
                   ['Sistema', 'CB'], ['Sistema', 'AL'], ['Sistema', 'CP']],
    'relacoes' => [['X', 'C', 'extend'], ['L', 'C', 'extend'], ['G', 'C', 'extend'], ['X', 'CB', 'include'], ['X', 'AL', 'include'], ['CP', 'AL', 'extend']]],

'uc13_painel' => ['nome' => 'Visualizar painel', 'colunas' => [215, 495], 'topo' => 100, 'passo' => 70,
    'casos' => ['P' => ["Visualizar painel com\nindicadores e gráficos", 0, 0.5], 'LA' => ["Limpar arquivos sem uso\n(uma vez por dia)", 1, 0.5]],
    'atores' => ['Empresa' => ['x' => 60, 'y' => 95], 'Administrador' => ['x' => 60, 'y' => 205], 'Sistema' => ['x' => 820]],
    'ligacoes' => [['Empresa', 'P'], ['Administrador', 'P'], ['Sistema', 'LA']],
    'relacoes' => [['LA', 'P', 'extend']]],

'uc14_assinaturas_admin' => ['nome' => 'Gerenciar assinaturas', 'colunas' => [225], 'topo' => 100, 'passo' => 56,
    'casos' => ['L' => ["Consultar assinaturas\n(filtros e ordenação)", 0, 0], 'C' => ['Conceder assinatura', 0, 1], 'U' => ["Alterar assinatura\n(valor, datas e situação)", 0, 2],
                'X' => ['Cancelar assinatura', 0, 3]],
    'atores' => ['Administrador' => ['x' => 60]],
    'ligacoes' => [['Administrador', 'L'], ['Administrador', 'C'], ['Administrador', 'U'], ['Administrador', 'X']]],

'uc15_padroes_automaticos' => ['nome' => 'Padrões automáticos das máquinas de extração', 'colunas' => [185, 455, 735], 'topo' => 100, 'passo' => 64,
    'casos' => ['XV' => ["Extrair vaga do anúncio\nou do cartaz", 0, 1], 'XC' => ["Extrair curso/e-book\nda ficha", 0, 2.4], 'RS' => ["Revisar e salvar (o cadastro\nentra nos padrões)", 0, 3.7],
                'VR' => ["Ver os padrões automáticos\nusados no relatório", 1, 0], 'AP' => ["Aplicar os padrões (só onde\na regra não tem pista)", 1, 1.7],
                'MP' => ["Montar os padrões dos cadastros\n(2 ou mais, 90% no mesmo destino)", 2, 1.1], 'NC' => ["Reconhecer empresas e\ninstituições já cadastradas", 2, 2.4]],
    'atores' => ['Empresa' => ['x' => 60, 'y' => 170], 'Administrador' => ['x' => 60, 'y' => 320], 'Sistema' => ['x' => 1080, 'y' => 205]],
    'ligacoes' => [['Empresa', 'XV'], ['Empresa', 'RS'], ['Administrador', 'XV'], ['Administrador', 'XC'], ['Administrador', 'RS'], ['Sistema', 'MP'], ['Sistema', 'NC']],
    'relacoes' => [['VR', 'XV', 'extend'], ['XV', 'AP', 'include'], ['XC', 'AP', 'include'], ['AP', 'MP', 'include'], ['AP', 'NC', 'include']]],
];
