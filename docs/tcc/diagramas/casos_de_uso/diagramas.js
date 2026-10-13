// Diagramas de casos de uso do Conecta Vagas DF (desenhados por desenho.html).
// Regras: só três atores (Usuário, Empresa e Administrador); o sistema não é ator; herança entre os atores
// (a Empresa herda do Usuário e o Administrador herda da Empresa).
// uc: { chave: [texto ("\n" quebra a linha), x, y] }   at: { chave: [nome, x, y dos braços] }
// as: associações [ator, caso]   her: generalizações [filho, pai]   dep: [de, para, '«include»'|'«extend»', dx, dy do rótulo]
const DIAGRAMAS = {
  geral: {
    titulo: 'Caso de Uso Geral', w: 920, h: 660, lim: [250, 48, 650, 596],
    at: { usu: ['Usuário', 130, 190], emp: ['Empresa', 130, 410], adm: ['Administrador', 130, 565] },
    her: [['emp', 'usu'], ['adm', 'emp']],
    uc: {
      uc01: ['UC01 Realizar login', 430, 95],
      uc02: ['UC02 Recuperar senha', 750, 95],
      uc03: ['UC03 Manter conta', 430, 168],
      uc04: ['UC04 Inserir currículo', 430, 230],
      uc05: ['UC05 Manter candidaturas', 430, 292],
      uc06: ['UC06 Acessar painel\nda empresa', 430, 380],
      uc07: ['UC07 Manter vagas', 430, 452],
      uc08: ['UC08 Acessar painel\nadministrativo', 430, 535],
      uc09: ['UC09 Manter usuários', 430, 607],
      uc10: ['UC10 Calcular match', 750, 335],
    },
    as: [['usu', 'uc01'], ['usu', 'uc03'], ['usu', 'uc04'], ['usu', 'uc05'], ['emp', 'uc06'], ['emp', 'uc07'], ['adm', 'uc08'], ['adm', 'uc09']],
    dep: [['uc02', 'uc01', '«extend»'], ['uc04', 'uc10', '«include»'], ['uc07', 'uc10', '«include»', 0, 16]],
  },

  perfil: {
    titulo: 'Perfil do Usuário', w: 920, h: 600, lim: [220, 48, 680, 536],
    at: { usu: ['Usuário', 95, 320] },
    uc: {
      conta: ['Inserir conta', 440, 95],
      alterar: ['Alterar perfil', 440, 175],
      curriculo: ['Inserir currículo', 400, 265],
      cancelar: ['Cancelar currículo', 400, 380],
      portfolio: ['Visualizar portfólio\ne match', 440, 465],
      excluir: ['Excluir a própria\nconta (LGPD)', 440, 548],
      match: ['Calcular match', 770, 175],
      extrair: ['Extrair dados e foto\ndo currículo', 770, 265],
      aplicar: ['Aplicar dados\ndo relatório', 770, 380],
      senha: ['Confirmar com a senha', 770, 548],
    },
    as: [['usu', 'conta'], ['usu', 'alterar'], ['usu', 'curriculo'], ['usu', 'cancelar'], ['usu', 'portfolio'], ['usu', 'excluir']],
    dep: [['alterar', 'match', '«include»'], ['curriculo', 'match', '«include»', -10, 2], ['curriculo', 'extrair', '«include»'],
      ['aplicar', 'curriculo', '«extend»', 0, 16], ['excluir', 'senha', '«include»']],
  },

  candidaturas: {
    titulo: 'Manter Candidaturas', w: 920, h: 470, lim: [200, 48, 530, 406],
    at: { usu: ['Usuário', 100, 205], emp: ['Empresa', 830, 250], adm: ['Administrador', 830, 395] },
    her: [['adm', 'emp']],
    uc: {
      candidatar: ['Candidatar-se à vaga', 350, 105],
      limite: ['Verificar limite\ndo plano', 600, 105],
      cancelar: ['Cancelar candidatura', 350, 220],
      acompanhar: ['Acompanhar status\ne retorno', 350, 335],
      analisar: ['Analisar candidaturas\nrecebidas', 600, 230],
      status: ['Alterar status\ne escrever retorno', 600, 340],
    },
    as: [['usu', 'candidatar'], ['usu', 'cancelar'], ['usu', 'acompanhar'], ['emp', 'analisar'], ['emp', 'status']],
    dep: [['candidatar', 'limite', '«include»']],
  },

  vagas: {
    titulo: 'Manter Vagas', w: 920, h: 520, lim: [210, 48, 690, 456],
    at: { emp: ['Empresa', 105, 230], adm: ['Administrador', 105, 425] },
    her: [['adm', 'emp']],
    uc: {
      padroes: ['Aplicar os padrões\nautomáticos', 365, 100],
      cadastrar: ['Cadastrar vaga', 365, 215],
      alterar: ['Alterar vaga', 365, 330],
      cancelar: ['Cancelar vaga', 365, 435],
      anuncio: ['Extrair vaga do texto\ndo anúncio', 700, 100],
      cartaz: ['Ler o cartaz pela foto\n(OCR no navegador)', 700, 215],
      match: ['Calcular match', 700, 330],
    },
    as: [['emp', 'cadastrar'], ['emp', 'alterar'], ['emp', 'cancelar']],
    dep: [['anuncio', 'padroes', '«include»'], ['anuncio', 'cadastrar', '«extend»', 10, 0], ['cartaz', 'cadastrar', '«extend»'],
      ['cadastrar', 'match', '«include»', 0, 2], ['alterar', 'match', '«include»']],
  },

  usuarios: {
    titulo: 'Manter Usuários', w: 920, h: 470, lim: [260, 48, 620, 406],
    at: { adm: ['Administrador', 130, 240] },
    uc: {
      cadastrar: ['Cadastrar usuário', 560, 105],
      alterar: ['Alterar usuário', 560, 195],
      ativar: ['Ativar ou desativar usuário', 560, 285],
      cancelar: ['Cancelar conta', 560, 375],
    },
    as: [['adm', 'cadastrar'], ['adm', 'alterar'], ['adm', 'ativar'], ['adm', 'cancelar']],
  },

  padroes: {
    titulo: 'Padrões Automáticos da Extração', w: 920, h: 500, lim: [210, 48, 690, 436],
    at: { emp: ['Empresa', 105, 175], adm: ['Administrador', 105, 385] },
    her: [['adm', 'emp']],
    uc: {
      vaga: ['Extrair vaga do anúncio\nou do cartaz', 375, 120],
      revisar: ['Revisar e salvar (o cadastro\nentra nos padrões)', 375, 250],
      curso: ['Extrair curso/e-book\nda ficha', 375, 400],
      ver: ['Ver os padrões usados\nno relatório', 705, 95],
      aplicar: ['Aplicar os padrões (só onde\na regra não tem pista)', 690, 215],
      montar: ['Montar os padrões\ndos cadastros', 580, 390],
      reconhecer: ['Reconhecer empresas\ne instituições', 785, 390],
    },
    as: [['emp', 'vaga'], ['emp', 'revisar'], ['adm', 'curso']],
    dep: [['ver', 'vaga', '«extend»'], ['vaga', 'aplicar', '«include»', 0, -4], ['curso', 'aplicar', '«include»', -20, 0],
      ['aplicar', 'montar', '«include»', -30, 0], ['aplicar', 'reconhecer', '«include»', 34, 0]],
  },
};
