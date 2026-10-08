# Conecta Vagas DF

O Conecta Vagas DF é uma plataforma web de vagas de emprego e de capacitação profissional para o Distrito Federal. Nós desenvolvemos o sistema como Projeto de Conclusão de Curso do Técnico em Informática da Escola Técnica de Ceilândia (ETC), pensando principalmente em quem procura o primeiro emprego ou quer voltar ao mercado de trabalho.

A ideia surgiu de uma situação que vemos todos os dias: as vagas do DF ficam espalhadas em cartazes de grupos de WhatsApp, em postagens do Instagram e em murais de lojas, e os cursos gratuitos que poderiam ajudar essas pessoas ficam escondidos em vários sites diferentes. No Conecta Vagas DF juntamos as duas coisas num lugar só. O candidato encontra a vaga, vê o quanto o perfil dele combina com ela e, se faltar alguma competência, encontra um curso gratuito para melhorar o currículo.

## Sumário

1. [O projeto em números](#o-projeto-em-números)
2. [Tecnologias que usamos](#tecnologias-que-usamos)
3. [Os personagens do sistema](#os-personagens-do-sistema)
4. [O sistema peça por peça](#o-sistema-peça-por-peça)
5. [Como o código está organizado](#como-o-código-está-organizado)
6. [Banco de dados](#banco-de-dados)
7. [Segurança e LGPD](#segurança-e-lgpd)
8. [Como instalar e rodar](#como-instalar-e-rodar)
9. [Contas de teste](#contas-de-teste)
10. [Testes](#testes)
11. [Roteiro para a apresentação](#roteiro-para-a-apresentação)
12. [Documentação do trabalho](#documentação-do-trabalho)
13. [Problemas comuns](#problemas-comuns)
14. [Backup, versões e publicação](#backup-versões-e-publicação)

## O projeto em números

| Item | Quantidade |
|---|---|
| Tabelas no banco de dados | 11 |
| Telas (views de página) | 27, além de 3 layouts e 5 partes reutilizáveis |
| Controllers | 12 |
| Endereços (rotas) | 32 |
| Classes de acesso ao banco (DAO) | 9 |
| Serviços de negócio | 4, mais 13 peças das máquinas de extração |
| Personagens | 4 (Candidato, Empresa, Administrador e Sistema) |
| Dados de demonstração | 62 vagas com cartaz, 64 cursos e e-books (43 cursos e 21 e-books) e 20 áreas |
| Testes automáticos | 98 arquivos conferidos, 112 verificações rápidas e 21 verificações de jornada |

## Tecnologias que usamos

O sistema foi escrito em **PHP 8** puro, no padrão **MVC** (Model, View e Controller), com banco de dados **MySQL/MariaDB** acessado pela extensão PDO. As telas são feitas em HTML, CSS e JavaScript, sem framework. Para rodar no computador usamos o **XAMPP**, que já traz o Apache, o PHP e o MySQL.

Não usamos frameworks (como Laravel) de propósito. Num projeto de conclusão de curso queríamos mostrar que entendemos o que acontece por baixo: como uma requisição chega ao servidor, como a rota é escolhida e como os dados vão do formulário até o banco e voltam para a tela. Todas essas partes foram escritas por nós e ficam na pasta `app/Core`.

O projeto não depende de pacotes externos. O leitor de cartazes (Tesseract.js) e o gerador de QR Code vêm junto com o site, na pasta `public/assets/js/vendor`, e os arquivos PDF e DOCX são lidos com código PHP feito por nós. O sistema foi testado no PHP 8.0.30 que vem no XAMPP. Para publicar na internet, recomendamos o PHP 8.2 ou mais novo, porque o 8.0 não recebe mais correções de segurança.

## Os personagens do sistema

Organizamos o projeto em quatro personagens: o **Candidato**, a **Empresa**, o **Administrador** e o **Sistema**. Os três primeiros são pessoas que usam a plataforma. O quarto é o próprio sistema, que faz uma parte do trabalho sozinho, sem ninguém clicar em nada. Todos os diagramas e toda a documentação usam esses quatro personagens.

### Candidato

O candidato é a pessoa que procura emprego. Mesmo antes de criar uma conta ele já navega pelo site: vê as vagas, filtra por cidade, área, tipo de contratação e nível, abre a página de cada vaga e consulta os cursos e e-books gratuitos. Quem está só olhando o site, sem conta, é para nós um candidato que ainda não se cadastrou.

Depois do cadastro, o candidato monta o perfil profissional. Ele pode digitar tudo ou enviar o currículo em PDF, DOCX ou DOC. Nesse caso o sistema lê o arquivo e preenche o perfil: nome, contato, resumo, objetivo, experiências, formação, cursos, habilidades, idiomas e até a foto, quando o currículo tem uma. No fim aparece um relatório com o que foi encontrado, o que foi aplicado e o que ainda falta e, logo abaixo, só o formulário do perfil, para o candidato revisar e completar antes de salvar.

Com o perfil completo, o candidato ganha um **portfólio** montado automaticamente e passa a ver o **match**, uma nota de 0 a 100 que mostra o quanto o perfil combina com cada vaga, com a explicação da nota. Ele se candidata às vagas que quiser, pode mandar uma mensagem para a empresa e acompanha o andamento: enviada, em análise, entrevista, aprovado ou rejeitado. Se desistir, ele mesmo cancela a candidatura.

No plano gratuito, o candidato pode ter até 3 candidaturas em andamento ao mesmo tempo. O plano **Candidato VIP** libera candidaturas ilimitadas, coloca um selo no perfil e dá prioridade na lista que a empresa recebe. O candidato também pode excluir a própria conta a qualquer momento, e com ela saem todos os dados dele, como pede a LGPD.

### Empresa

A empresa é quem oferece as vagas. Ela tem um perfil com nome fantasia, CNPJ, setor, site, telefone, logotipo, cidade e uma descrição.

Para publicar uma vaga, a empresa tem três caminhos: preencher o formulário, colar o texto do anúncio (aquele que circula no WhatsApp ou no Instagram) ou enviar a foto do **cartaz**. Nos dois últimos casos o sistema lê o conteúdo e preenche o formulário: cargo, empresa, salário, local, tipo de contratação, nível, descrição, requisitos, benefícios e contato. A empresa revisa e só então publica. Antes de publicar, o sistema avisa se já existe uma vaga aberta parecida.

Quando os candidatos se inscrevem, a empresa vê as candidaturas ordenadas pelo match, do perfil que mais combina para o que menos combina. Ela abre o currículo, muda o status da candidatura e o candidato acompanha a mudança do lado dele. A empresa também consulta o **banco de talentos**, com os perfis públicos dos candidatos.

No plano gratuito, a empresa pode ter até 2 vagas ativas ao mesmo tempo. O plano **Empresa Premium** libera vagas ilimitadas, coloca as vagas em destaque na busca e na página inicial, dá acesso completo ao banco de talentos (com busca e download de currículos) e mostra o selo de empresa Premium.

### Administrador

O administrador cuida da plataforma inteira. A primeira tela do painel é uma visão geral com números e gráficos: candidaturas por dia, usuários novos, vagas por área, situação das candidaturas e assinaturas. A partir dali ele administra:

- os **usuários**: criar, editar, ativar, desativar e cancelar contas;
- as **categorias**, que são as áreas usadas para agrupar vagas e cursos;
- as **vagas** de todas as empresas, com a mesma leitura de cartaz e de anúncio que a empresa tem;
- as **candidaturas** de toda a plataforma;
- os **cursos, e-books e vídeos** do catálogo de capacitação;
- as **assinaturas** dos planos.

### Sistema

O quarto personagem é o próprio sistema. Ele faz várias tarefas sozinho, sempre em resposta a alguma coisa que os outros personagens fizeram:

- **lê os documentos**: o currículo do candidato, o cartaz e o anúncio da empresa e a ficha de curso do administrador;
- **calcula o match** entre candidatos e vagas quando um currículo é enviado, um perfil é salvo ou uma vaga é criada ou alterada;
- **monta os padrões automáticos** que ajudam a leitura de vagas e cursos, a partir do que já está cadastrado;
- **abre os links** das fichas de cursos, tira a capa da primeira página do PDF dos e-books e guarda os PDFs na biblioteca;
- **protege as contas**, pausando o login depois de muitas senhas erradas;
- **faz a limpeza**: uma vez por dia apaga os arquivos enviados que não pertencem a mais ninguém.

## O sistema peça por peça

### Página inicial e navegação

A página inicial abre com um carrossel de fotos de Brasília e uma busca de vagas no topo. Abaixo vêm as vagas, os cursos e a apresentação dos planos, e um painel rápido mostra avisos que vão se alternando. O menu leva às vagas, aos cursos, aos e-books e aos planos. No rodapé existe um convite discreto de **doação por Pix** para ajudar a manter o site no ar. O código Pix é montado pelo próprio sistema no padrão do Banco Central, e o QR Code é desenhado no navegador, sem chamar serviço de fora.

### Cadastro, login e recuperação de senha

O cadastro pede nome, e-mail, senha, telefone e o tipo de conta (candidato ou empresa), além do aceite dos termos de uso e da política de privacidade. As senhas nunca são guardadas como texto: o banco guarda só o resumo criptográfico (hash) gerado pelo `password_hash`.

No login, decidimos ajudar quem erra por distração. O sistema aceita a senha mesmo com a primeira letra trocada de maiúscula para minúscula, com o Caps Lock ligado ou com espaço sobrando no começo ou no fim. Por outro lado, depois de muitas senhas erradas o login fica pausado por 5 minutos: 8 erros seguidos para o mesmo e-mail no mesmo computador, 20 para o mesmo e-mail vindos de qualquer lugar ou 60 vindos do mesmo endereço IP. Os limites ficam em `config/config.php`.

Quem esquece a senha pede um link de redefinição. Como o projeto roda na nossa máquina e não tem servidor de e-mail, no modo de demonstração o link aparece na tela e fica registrado em `storage/logs/redefinicoes_senha.log`, com o e-mail mascarado. O banco guarda só o resumo do código do link, o link vale 30 minutos e só pode ser usado uma vez.

### Vagas e candidaturas

A lista de vagas tem busca por palavra, filtros por cidade, área, tipo de contratação, nível e modelo de trabalho (presencial, remoto ou híbrido), ordenação e paginação. Cada vaga tem uma página própria com o cartaz, a descrição, os requisitos, os benefícios e o contato do anúncio. Para o candidato logado, a página mostra também o match com aquela vaga e os cursos que ensinam o que falta para ele.

Ao se candidatar, o candidato escolhe qual currículo enviar e pode escrever uma mensagem para a empresa. A candidatura passa pelos status *enviada*, *em análise*, *entrevista* e *aprovado* ou *rejeitado*, definidos pela empresa. O status *cancelada* só o próprio candidato escolhe, enquanto a candidatura ainda está em *enviada* ou *em análise*.

### Currículo e perfil do candidato

O envio do currículo aceita PDF, DOCX e DOC de até 10 MB. Para ler esses arquivos escrevemos leitores próprios em PHP. O de PDF entende currículos em duas colunas, comuns nos modelos do Canva e do Word, e lê cada coluna inteira antes de passar para a outra, para o texto não sair embaralhado.

Depois de ler o texto, a extração separa o currículo em partes. Ela reconhece os títulos das seções mesmo sem acento, em maiúsculas, numerados, com ícones ou com as letras espaçadas ("E X P E R I Ê N C I A"), preenche cada campo do perfil e padroniza as experiências e a formação. Os campos que o candidato já tinha preenchido são mantidos, a não ser que ele marque a opção de substituir. A foto também é procurada dentro do arquivo: o sistema escolhe a imagem com cara de foto de rosto e descarta logotipos, ícones e páginas escaneadas.

### Portfólio

O portfólio é a página profissional do candidato, montada a partir do perfil. As experiências viram uma linha do tempo, a formação é organizada por curso e instituição, e as habilidades e cursos aparecem em listas. Ele fica numa folha do tamanho de uma A4: uma faixa com a foto de Brasília, a foto e o nome do candidato, cartões azul-claros à esquerda (contato, habilidades, idiomas e objetivo) e brancos à direita (resumo, experiência, formação e cursos). O botão **Salvar em PDF** imprime só a folha, igual ao que aparece na tela. Só para o dono, o botão **Visualizar candidaturas** abre a lista das vagas em que ele se candidatou, com o status e o retorno da empresa, e o portfólio mostra as vagas compatíveis com a explicação de cada nota e recomenda cursos do catálogo que ajudam a completar o perfil. O candidato pode compartilhar o link. Qualquer pessoa e os outros candidatos veem o portfólio completo; a empresa sem o plano Premium vê uma prévia.

### Match entre candidato e vaga

O match é uma nota de 0 a 100 dividida em quatro partes:

| Parte | Peso | O que compara |
|---|---|---|
| Competências | 50 | competências pedidas pela vaga que o candidato tem |
| Cargo | 20 | título da vaga com o título profissional e o histórico do candidato |
| Localização | 15 | mesma cidade, mesma UF ou vaga remota |
| Nível | 15 | nível de experiência do candidato com o nível pedido |

A nota vem sempre com a explicação de cada parte, para o candidato entender por que combina mais com uma vaga do que com outra e para a empresa entender a ordem da lista de candidatos. O match é recalculado quando o candidato envia ou cancela um currículo, quando salva o perfil e quando a empresa cria ou altera uma vaga. Também existe um botão para recalcular na hora.

### Leitura de cartazes e anúncios de vagas

Esta é uma das partes de que mais nos orgulhamos. A empresa envia a foto do cartaz e o texto é lido no próprio navegador, com o Tesseract.js (um leitor de texto em imagens) servido pelo nosso site. Por isso ninguém precisa instalar nada, nem no computador nem no servidor. Enquanto lê, o carregador fica amarelo e mostra a porcentagem; quando termina, fica azul.

Com o texto em mãos, a extração de vagas aplica as regras que escrevemos a partir de dezenas de cartazes reais do DF. Ela encontra o cargo (inclusive quando vem em letra grande e quebrado em várias linhas), a empresa anunciante, o salário (sem confundir o valor do vale-transporte com o salário), a cidade, o tipo de contratação, o nível, o modelo de trabalho, o contato (WhatsApp, telefone ou e-mail) e a quantidade de vagas. As linhas restantes são separadas em descrição, requisitos e benefícios. No final aparece um relatório campo por campo, dizendo o que foi lido do anúncio, o que ficou com o valor padrão e o que não foi encontrado.

### Padrões automáticos das máquinas de extração

As regras resolvem a maior parte dos casos, mas sempre aparece uma linha sem nenhuma palavra-chave conhecida, uma área que a regra não reconhece ou uma empresa cujo nome a regra não acha no texto. Para esses casos criamos os **padrões automáticos** (classe `PadroesExtracao`).

Na hora da leitura, o sistema olha as vagas e os cursos já cadastrados e revisados por uma pessoa e conta em que campo cada palavra e cada par de palavras costuma aparecer. Antes de contar, o texto é normalizado: tiramos os acentos, passamos tudo para minúsculas, removemos a pontuação e trocamos os números por `#`. Uma expressão só vira padrão quando aparece em pelo menos 2 cadastros diferentes e em pelo menos 90% deles no mesmo lugar. Por exemplo, "café da manhã" aparece nos benefícios de várias vagas; quando chega um anúncio novo com "Café da manhã e lanche da tarde", o sistema coloca essa linha em Benefícios, mesmo sem uma palavra-chave na regra. Do mesmo jeito, "auxiliar de cozinha" no título indica a área de Alimentação, e "excel" no título de um curso indica Informática e Excel.

Algumas decisões que tomamos:

- a regra continua mandando, e o padrão só decide quando a regra não tem pista;
- não existe tabela nem tela de ajuste: os padrões são montados na hora a partir dos cadastros, então cada vaga ou curso salvo já melhora a próxima leitura;
- o resultado é sempre o mesmo para o mesmo texto e os mesmos cadastros, e cada decisão aparece no relatório da extração, no bloco "Padrões automáticos usados";
- o currículo não entra nos padrões, porque tem dados pessoais. A leitura de currículos segue só as regras.

Os padrões também guardam os **nomes conhecidos**: as empresas (anunciante das vagas e nome fantasia das empresas cadastradas) e as instituições dos cursos. Quando a regra não acha o nome da empresa num cartaz novo, o sistema procura no texto algum desses nomes. Palavras genéricas como "Loja" ou "Empresa" nunca entram nessa lista. Mais detalhes em [docs/PADROES_AUTOMATICOS.md](docs/PADROES_AUTOMATICOS.md).

### Cursos, e-books e a biblioteca

O catálogo de capacitação reúne cursos, e-books e vídeos gratuitos de fontes oficiais, como Fundação Bradesco, Escola Virtual de Governo (Enap), SEBRAE, SENAC, FGV, Banco Central, Ministério do Trabalho e Microsoft Learn.

Para cadastrar um conteúdo novo, o administrador cola na caixa **Extrair** uma **ficha** com os campos Título, Tipo, Instituição, Modalidade, Cidade, Nível, Carga horária, Gratuito, Preço, Área, Link, PDF, Imagem e Descrição. Uma ficha preenche o formulário para revisar. Várias fichas, separadas por `---`, abrem uma prévia para cadastrar todas de uma vez.

Na prévia, o sistema **abre cada link da ficha** (o da imagem, o do PDF e o do conteúdo) e descobre o que cada um é:

- se é uma **imagem**, ela é usada como imagem do conteúdo;
- se é um **PDF**, o sistema desenha a primeira página e usa como capa do e-book. Para isso usamos o leitor de PDF que já vem no Windows, chamado pelo PowerShell; se ele não estiver disponível, pega a maior imagem da primeira página. Ao salvar, o PDF vai para a nossa **biblioteca**;
- se é uma **página**, o sistema procura nela o PDF do e-book e a imagem de divulgação, descartando logotipos e ícones;
- se o link está fora do ar, ou diz ser um PDF mas abre uma página, aparece um **aviso** para o administrador conferir antes de salvar.

A imagem nunca impede o cadastro. Sem imagem, ou se o link não baixa, o conteúdo entra com a imagem da instituição ou com a imagem padrão e aparece no filtro "Com imagem padrão". Para terminar, o administrador abre o conteúdo em Editar e envia o arquivo, cola o link direto da imagem ou escolhe uma imagem que já está no site.

Os e-books com PDF na biblioteca mostram o botão **Baixar**, e o arquivo vem direto do nosso site, com o crédito da fonte original. Os conteúdos que ficam em outro site mostram o botão **Acessar**, que abre a página de origem em outra aba.

### Planos e assinaturas

Existem dois planos pagos, os dois por 30 dias e sem fidelidade: o **Candidato VIP**, por R$ 9,90, e o **Empresa Premium**, por R$ 49,90. Como é um trabalho acadêmico, a assinatura é demonstrativa e não há cobrança de verdade. A tela mostra o plano atual, o histórico e as vantagens de cada um, e o administrador acompanha tudo em Assinaturas.

### Cancelar sem perder dados

Nenhum botão do painel apaga um registro. Onde antes havia "Excluir", agora há **Cancelar**, que tira o item de circulação e guarda tudo o que está ligado a ele:

- **Cancelar vaga**: a vaga sai do ar com a situação *cancelada*, e as candidaturas e o match dela continuam guardados. O candidato vê o aviso "A empresa cancelou esta vaga", e essa candidatura deixa de ocupar o limite do plano gratuito. O botão Reabrir desfaz;
- **Cancelar candidatura** (administrador): a candidatura fica com o status *cancelada* e continua no histórico;
- **Cancelar conta**, **Cancelar categoria** e **Cancelar curso** (ou e-book e vídeo): a conta é bloqueada, a categoria sai dos filtros e o conteúdo sai da área pública, e os dados continuam guardados. A chave de ativar desfaz;
- **Cancelar assinatura**: a conta volta ao plano gratuito, e a assinatura fica no histórico;
- **Cancelar currículo** (candidato): o currículo sai da lista e não é usado em novas candidaturas nem no match, mas as empresas que já o receberam continuam com ele.

A confirmação de cada botão termina com "Clique em OK para confirmar", para não confundir o Cancelar do botão com o Cancelar da janela de confirmação. O único jeito de apagar dados é o candidato excluir a própria conta, como pede a LGPD.

### Tarefas automáticas

Quando o administrador abre a visão geral do painel, o sistema confere se já fez a manutenção do dia. Se não fez, apaga de `storage/uploads` os arquivos que nenhum registro usa e que têm mais de 24 horas: sobras de contas excluídas, de cartazes lidos e não usados ou de capas tiradas de fichas que não foram salvas.

## Como o código está organizado

Seguimos o padrão MVC. O **Model** cuida do banco de dados (uma classe por tabela, na pasta `Models`), a **View** é a tela em HTML com PHP (pasta `Views`) e o **Controller** recebe o pedido, confere a permissão e decide o que mostrar (pasta `Controllers`). As regras de negócio maiores, como o match e as máquinas de extração, ficam na pasta `Services`.

Todo pedido passa por uma única porta de entrada, o arquivo `public/index.php`. Por exemplo, quando o navegador pede `/vagas.php`:

1. o `.htaccess` da raiz manda o pedido para `public/index.php`;
2. o `app/Core/bootstrap.php` carrega a configuração e prepara o carregamento automático das classes;
3. a sessão é aberta e a conta logada é conferida no banco;
4. o roteador (`app/Core/Router.php`) procura `vagas.php` na tabela de rotas e chama `VagaController::lista()`;
5. o controller lê os filtros e busca as vagas no `VagaDAO` e o match no `MatchDAO`;
6. a tela `app/Views/vagas/lista.php` é mostrada entre o cabeçalho e o rodapé.

```
tcc-final/
├── .githooks/pre-commit        roda os testes antes de cada commit
├── .htaccess                   manda todos os pedidos para public/
├── index.php                   reserva para servidor sem mod_rewrite
├── app/                        código da aplicação (o navegador não acessa)
│   ├── Core/                   núcleo: bootstrap, autoloader, rotas, sessão, login, CSRF,
│   │                           banco (PDO), uploads, telas e funções gerais
│   ├── Controllers/            12 controllers: Home, Vaga, Curso, Planos, Auth, Password,
│   │                           Perfil, Curriculo, Candidatura, Arquivo, Empresa e Admin
│   ├── Models/                 9 DAOs, um por assunto do banco (Usuario, Perfil, Vaga, Curso,
│   │                           Curriculo, Candidatura, Match, Categoria e Assinatura)
│   ├── DTO/                    UsuarioDTO e PerfilDTO: levam o formulário até o DAO
│   ├── Services/               MatchService, Competencias, Portfolio e Pix
│   │   └── Extracao/           leitores de PDF, DOCX e DOC, OCR do cartaz, extração de
│   │                           currículo, vaga e curso, padrões automáticos, fontes oficiais,
│   │                           abertura de links e capa do PDF (capa-pdf.ps1)
│   └── Views/
│       ├── layouts/            cabeçalho, rodapé e menu do painel
│       ├── partials/           componentes, gráficos, ícones, doação Pix e relatório da extração
│       ├── home/ vagas/ cursos/ planos/ auth/ perfil/   telas do site
│       ├── admin/              telas do painel (empresa e administrador)
│       └── errors/             página de erro (403, 404, 500 e 503)
├── config/
│   ├── config.php              banco, depuração, limites, pastas e chave Pix
│   └── cacert.pem              certificados para abrir links https das fichas
├── database/
│   ├── schema.sql              estrutura do banco (11 tabelas)
│   ├── seed.sql                dados de demonstração
│   └── resetar_senhas.php      devolve as senhas das contas de teste
├── docs/                       documentação (veja "Documentação do trabalho")
│   └── tcc/                    documento do TCC (.docx) e diagramas UML
├── public/                     única pasta servida pelo Apache
│   ├── index.php               porta de entrada e tabela de rotas
│   └── assets/                 css/, js/ (com o Tesseract.js e o QR Code em vendor/) e img/
├── storage/                    gerado pelo sistema, fora do alcance do navegador e do Git
│   ├── uploads/                currículos, fotos, cartazes e PDFs da biblioteca
│   ├── logs/                   registros de erro e de redefinição de senha
│   └── backups/                cópias do banco e dos arquivos
└── tests/                      lint.php, smoke.php, jornadas.php, verificar.bat e amostras/
```

Os endereços das páginas são simples e estáveis (`vaga.php?id=3`, `view/perfil/index.php`, `admin/pages/vagas.php`), e a tabela completa de rotas está em [docs/ARQUITETURA.md](docs/ARQUITETURA.md), junto com o detalhe de cada camada.

## Banco de dados

O banco se chama `tcc_final` e tem 11 tabelas:

| Tabela | Para que serve |
|---|---|
| `usuarios` | as contas de acesso (candidato, empresa ou administrador), com e-mail, senha protegida e telefone |
| `perfis` | o perfil de cada conta: dados profissionais do candidato ou dados da empresa (um perfil por usuário) |
| `categorias` | as áreas que agrupam vagas e cursos |
| `vagas` | as vagas publicadas pelas empresas |
| `cursos` | os cursos, e-books e vídeos do catálogo |
| `curriculos` | os arquivos de currículo enviados pelos candidatos |
| `candidaturas` | a inscrição de um candidato numa vaga e o status dela |
| `matches` | a nota de match de cada candidato com cada vaga e a explicação da nota |
| `assinaturas` | as assinaturas dos planos VIP e Premium |
| `tentativas_login` | as senhas erradas, usadas para pausar o login |
| `redefinicoes_senha` | os pedidos de nova senha (só o resumo do código do link) |

Os botões Cancelar não apagam nada: a vaga cancelada fica com a situação `cancelada`, a candidatura e a assinatura com o status `cancelada`, e a conta, a categoria, o curso e o currículo cancelados ficam com `ativo = 0`. As chaves estrangeiras usam `ON DELETE CASCADE`, que só entra em ação quando o candidato exclui a própria conta (LGPD): saem junto o perfil, os currículos, as candidaturas e os matches dele. Os padrões automáticos da extração não têm tabela, porque são montados na hora a partir das vagas e dos cursos.

## Segurança e LGPD

- as senhas são guardadas com `password_hash` (bcrypt), e o login pausa sozinho depois de muitas tentativas erradas;
- todo formulário tem um token contra envio forjado (CSRF), toda consulta ao banco usa parâmetros (nunca texto colado direto no SQL) e todo texto mostrado na tela passa pela função `e()`, que impede a injeção de código;
- cada ação confere a permissão: o candidato só mexe no que é dele, a empresa só nas próprias vagas e candidaturas, e o currículo só abre para o dono, para a empresa que o recebeu ou para empresa Premium;
- os links das fichas de cursos só são abertos se forem http ou https de servidores públicos, nunca da rede interna. Cada redirecionamento é conferido de novo e a conexão vai direto ao IP conferido, com limite de tamanho e de tempo;
- só a pasta `public/` é servida pelo Apache, e lá dentro só o `index.php` executa PHP. Configurações, banco, backups e documentos respondem com acesso negado;
- o site envia cabeçalhos de segurança (`Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` e `Permissions-Policy`).

Sobre a **LGPD**: no cadastro a pessoa aceita os termos e a política de privacidade. O candidato pode excluir a própria conta em Meu perfil, Seus dados, Excluir minha conta, confirmando com a senha. Saem o perfil, os currículos e os arquivos, a foto, as candidaturas, o match e as tentativas de login. Os registros internos nunca guardam o e-mail inteiro, e os padrões automáticos nunca usam dados do currículo ou do perfil do candidato.

## Como instalar e rodar

1. Copie a pasta do projeto para `C:\xampp\htdocs\`. Na nossa máquina ela fica em `C:\xampp\htdocs\tcc-final`.
2. Abra o XAMPP Control Panel e clique em **Start** no Apache e no MySQL. O `mod_rewrite` do Apache, que já vem ativo no XAMPP, é necessário.
3. Importe o banco: primeiro a estrutura e depois os dados de demonstração. Pelo phpMyAdmin, use Importar com `database/schema.sql` e depois com `database/seed.sql`. Ou, pelo Prompt de Comando (cmd), dentro da pasta do projeto (no PowerShell o `<` não funciona):
   ```
   C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < database\schema.sql
   C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < database\seed.sql
   ```
   O `schema.sql` apaga e cria de novo apenas o banco `tcc_final`.
4. Se o seu MySQL tiver senha, ajuste `DB_PASS` em `config/config.php`.
5. Acesse `http://localhost/tcc-final/` (ou `http://localhost/<nome da pasta>/`).
6. Para conferir se está tudo certo, dê um clique duplo em `tests\verificar.bat`.
7. Opcional, com internet: entre como administrador, abra Cursos e e-books e clique em **Trazer os PDFs para a biblioteca**. Os PDFs dos e-books são baixados para o nosso site e o botão deles muda de Acessar para Baixar.

## Contas de teste

| Personagem | E-mail | Senha |
|---|---|---|
| Administrador | admin@conectavagas.com | Admin@123 |
| Empresa | empresa@conectavagas.com | Empresa@123 |
| Candidato | candidato@conectavagas.com | Candidato@123 |

Antes de publicar o sistema na internet, essas senhas precisam ser trocadas.

## Testes

Criamos três testes automáticos, na pasta `tests`:

- o `lint.php` confere a sintaxe de todos os arquivos PHP;
- o `smoke.php` é o teste rápido: 112 verificações das regras de negócio, das máquinas de extração (com exemplos reais de cartazes, anúncios e currículos), dos padrões automáticos, da capa do PDF, do banco e das páginas;
- o `jornadas.php` usa o sistema como uma pessoa usaria, pelo navegador. Cria uma conta temporária e passa por cadastro, login, envio de currículo, candidatura, leitura de vaga e de ficha de curso, cancelamento (a vaga, a candidatura e o currículo cancelados continuam guardados), troca de senha e exclusão da conta. São 21 verificações, e no fim a conta é apagada e a vaga cancelada volta a ficar aberta.

O jeito mais fácil de rodar tudo é o clique duplo em `tests\verificar.bat`. No fim aparece TUDO CERTO, ou o que quebrou, com o arquivo e a linha. O teste de jornadas precisa da extensão zip do PHP, que vem desligada no XAMPP; o `verificar.bat` liga essa extensão só durante o teste, sem mexer no `php.ini`. O site em si funciona sem ela.

O Git também está protegido: antes de cada commit, o `.githooks/pre-commit` roda os mesmos testes, e se algo quebrar o commit não é feito. Numa cópia nova do projeto, esse gancho é ligado com:
```






processador fica mais lento e a leitura do cartaz demora o dobro.
2. No XAMPP, dar Start no Apache e no MySQL.
3. Clique duplo em `tests\verificar.bat` e esperar o TUDO CERTO.
4. Abrir uma vez o painel de vagas, para o leitor de cartaz já ficar carregado.
5. Mostrar o **candidato**: enviar um currículo, ver o relatório e o perfil preenchido, o portfólio na folha A4 (com o Salvar em PDF) e o match com as vagas, se candidatar e abrir o **Visualizar candidaturas**.
6. Mostrar a **empresa**: enviar a foto de um cartaz, mostrar o formulário preenchido e o relatório da extração, com o bloco "Padrões automáticos usados". Depois, abrir as candidaturas ordenadas pelo match e mudar um status.
7. Mostrar o **administrador**: a visão geral com os gráficos e o cadastro de um e-book por ficha, com a capa tirada da primeira página do PDF.
8. Explicar o papel do **sistema** em cada um desses passos.
9. Ao terminar, dar Stop no MySQL antes de fechar o XAMPP.

## Documentação do trabalho

| Documento | O que tem |
|---|---|
| [docs/tcc/TCC_Final_Conecta_Vagas_DF.docx](docs/tcc/TCC_Final_Conecta_Vagas_DF.docx) | o documento do TCC no modelo do professor (Escola Técnica de Ceilândia, PCC 2.2026): requisitos, casos de uso, diagramas, modelo de dados, telas, testes, a seção 8.11 com cada funcionalidade em detalhe e o Apêndice A com o código-fonte explicado |
| [docs/tcc/TCC_Final_Conecta_Vagas_DF.pdf](docs/tcc/TCC_Final_Conecta_Vagas_DF.pdf) | o mesmo documento em PDF (296 páginas), com sumário e listas atualizados e marcadores para navegar pelos títulos |
| [docs/tcc/TCC_Conecta_Vagas_DF_Documentacao.docx](docs/tcc/TCC_Conecta_Vagas_DF_Documentacao.docx) e [.pdf](docs/tcc/TCC_Conecta_Vagas_DF_Documentacao.pdf) | a documentação revisada no modelo do professor (68 páginas): requisitos, casos de uso com especificação, diagramas de classes, sequência e entidade-relacionamento, dicionário de dados e a leitura do cartaz por OCR com o Tesseract |
| [docs/telas/](docs/telas/README.md) | capturas de todas as telas do sistema (visitante, candidato, empresa e administrador, no computador e no celular) e o portfólio salvo em PDF |
| [docs/ARQUITETURA.md](docs/ARQUITETURA.md) | como o sistema funciona por dentro, camada por camada, com a tabela de rotas |
| [docs/PADROES_AUTOMATICOS.md](docs/PADROES_AUTOMATICOS.md) | os padrões automáticos das máquinas de extração e como demonstrá-los |
| [docs/PESQUISA_CURSOS.md](docs/PESQUISA_CURSOS.md) | as fontes oficiais do catálogo e como a pesquisa de cursos funciona |
| `docs/tcc/diagramas/` | casos de uso, classes, sequência, arquitetura e os modelos conceitual e lógico, em `.png` e `.svg` |

Os diagramas de sequência e de arquitetura têm o arquivo `.puml` de origem (PlantUML, com o estilo comum em `estilo.iuml`). Os de caso de uso, o de classes e o modelo lógico são desenhados pelos scripts de `docs/tcc/diagramas/gerador`, com linhas retas e posições controladas, no estilo do Astah.

Antes de entregar o documento do TCC, a equipe completa no Word os campos que dependem dela (nomes completos, data da defesa e banca), atualiza o sumário e as listas de figuras, tabelas e quadros (selecionar tudo com Ctrl+A e apertar F9, ou clicar com o botão direito no sumário e escolher Atualizar campo) e exporta o PDF pelo próprio Word.

## Problemas comuns

**Não consigo entrar.** Use o botão do olho, ao lado do campo de senha, para conferir o que foi digitado. Pelo próprio computador, a mensagem de erro diz o motivo exato: e-mail sem conta, senha errada ou conta desativada. De outra máquina a mensagem é sempre a mesma, para não revelar quais e-mails têm conta. Se o login pausou depois de muitas tentativas, ele libera sozinho em 5 minutos. Para devolver as senhas das três contas de teste, reativar as contas e tirar qualquer pausa, rode:
```
C:\xampp\php\php.exe database\resetar_senhas.php
```

**Apareceu "Sua sessão foi encerrada porque a senha da conta foi alterada".** A senha daquela conta mudou em outro lugar. É só entrar de novo com a senha nova.

**O cartaz demora para ser lido.** Na primeira vez o navegador baixa o leitor de cartaz, que depois fica guardado. Com o notebook na bateria a leitura também fica mais lenta.

**A página mostra "Banco de dados indisponível" ou "Serviço indisponível".** O MySQL está desligado. Dê Start no MySQL pelo XAMPP e recarregue a página.

## Backup, versões e publicação

Os backups ficam em `storage/backups/<data>/`, com o banco, os arquivos enviados e um `COMO_RESTAURAR.txt`. Para fazer um backup novo do banco:
```
C:\xampp\mysql\bin\mysqldump.exe -u root --single-transaction --databases tcc_final > storage\backups\banco_tcc_final.sql
```

As versões estáveis do código são marcadas com tags no Git (`tcc-final-v1.0`, `tcc-final-v1.1`, `tcc-final-v1.2`, `tcc-final-v1.3`, `tcc-final-v1.4` e as seguintes). Para voltar a uma delas, use `git checkout <tag>`. Cada versão final tem um backup do banco e dos arquivos enviados em `storage/backups/` (fora do Git), com o `COMO_RESTAURAR.txt`.

Quando o site é acessado pelo próprio computador, os erros mostram o detalhe técnico, o que ajuda no desenvolvimento. Quem acessa de outra máquina vê só mensagens amigáveis. Para publicar, defina a variável de ambiente `APP_DEBUG=0`, que desliga o detalhe técnico para todos, inclusive quando o site fica atrás de um proxy.
