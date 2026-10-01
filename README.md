# Conecta Vagas DF — TCC Final

O Conecta Vagas DF é uma plataforma web de vagas de emprego e de capacitação profissional voltada para o Distrito Federal. Nós desenvolvemos o sistema como Trabalho de Conclusão de Curso, pensando principalmente em quem está procurando o primeiro emprego ou tentando voltar ao mercado de trabalho.

A ideia surgiu de uma situação que vemos todos os dias: as vagas do DF ficam espalhadas em cartazes de grupos de WhatsApp, em postagens do Instagram e em murais de lojas, e os cursos gratuitos que poderiam ajudar essas pessoas ficam escondidos em vários sites diferentes. No Conecta Vagas DF, juntamos as duas coisas num lugar só. O candidato encontra a vaga, vê o quanto o perfil dele combina com ela e, se faltar alguma competência, encontra um curso gratuito para melhorar o currículo.

Este repositório guarda a versão final do trabalho, que chamamos de **TCC Final**. Ela foi construída a partir da nossa versão anterior (o repositório conecta-vagas-df-tcc), que ficou congelada na tag `v1.0-blindada`. No site, o nome continua sendo Conecta Vagas DF.

## Sumário

1. [Tecnologias que usamos](#tecnologias-que-usamos)
2. [Os personagens do sistema](#os-personagens-do-sistema)
3. [O sistema peça por peça](#o-sistema-peça-por-peça)
4. [Como o código está organizado](#como-o-código-está-organizado)
5. [Banco de dados](#banco-de-dados)
6. [Segurança e LGPD](#segurança-e-lgpd)
7. [Como instalar e rodar](#como-instalar-e-rodar)
8. [Contas de teste](#contas-de-teste)
9. [Testes](#testes)
10. [Roteiro para a apresentação](#roteiro-para-a-apresentação)
11. [Documentação do trabalho](#documentação-do-trabalho)
12. [Problemas comuns](#problemas-comuns)
13. [Backup, depuração e publicação](#backup-depuração-e-publicação)

## Tecnologias que usamos

O sistema foi escrito em **PHP 8** puro, seguindo o padrão **MVC** (Model, View e Controller), com banco de dados **MySQL/MariaDB** acessado pela extensão PDO. As telas são feitas em HTML, CSS e JavaScript, sem nenhum framework. Para rodar no computador usamos o **XAMPP**, que já traz o Apache, o PHP e o MySQL.

Escolhemos não usar frameworks (como Laravel) de propósito. Como é um trabalho de conclusão de curso, queríamos mostrar que entendemos o que acontece por baixo: como uma requisição chega ao servidor, como a rota é escolhida, como os dados vão do formulário até o banco e voltam para a tela. Todas essas partes foram escritas por nós e ficam na pasta `app/Core`.

O projeto não depende de nada externo para funcionar. O leitor de cartazes (OCR) vem junto com o site e roda no próprio navegador, e os arquivos PDF e DOCX são lidos com código PHP feito por nós. O sistema foi testado no PHP 8.0.30 que vem no XAMPP. Para publicar na internet, recomendamos o PHP 8.2 ou mais novo, porque o 8.0 não recebe mais correções de segurança.

## Os personagens do sistema

Para organizar o projeto, pensamos em quatro personagens: o **Candidato**, a **Empresa**, o **Administrador** e o **Sistema**. Os três primeiros são pessoas que usam a plataforma. O quarto é o próprio sistema, que faz uma parte do trabalho sozinho, sem ninguém clicar em nada. Todos os nossos diagramas e toda a documentação usam esses quatro personagens.

### Candidato

O candidato é a pessoa que procura emprego. Mesmo antes de criar uma conta, ele já pode navegar pelo site: ver as vagas, filtrar por cidade, área, tipo de contratação e nível, abrir a página de cada vaga e consultar os cursos e e-books gratuitos. Quem está só olhando o site, sem conta, é para nós um candidato que ainda não se cadastrou.

Depois do cadastro, o candidato monta o perfil profissional. Ele pode digitar tudo à mão ou simplesmente enviar o currículo em PDF, DOCX ou DOC. Nesse caso o sistema lê o arquivo e preenche o perfil sozinho: nome, contato, resumo, objetivo, experiências, formação, cursos, habilidades, idiomas e até a foto, quando o currículo tem uma. No fim aparece um relatório mostrando o que foi encontrado, o que foi aplicado e o que ainda falta, e o candidato revisa antes de salvar.

Com o perfil completo, o candidato ganha um **portfólio** montado automaticamente, com as experiências numa linha do tempo e a formação organizada. Ele também passa a ver o **match**, uma nota de 0 a 100 que mostra o quanto o perfil dele combina com cada vaga, com a explicação da nota. A partir daí ele se candidata às vagas que quiser, pode mandar uma mensagem para a empresa junto com a candidatura e acompanha o andamento: enviada, em análise, entrevista, aprovado ou rejeitado. Se desistir, ele mesmo cancela a candidatura.

No plano gratuito, o candidato pode ter até 3 candidaturas em andamento ao mesmo tempo. O plano **Candidato VIP** libera candidaturas ilimitadas, coloca um selo no perfil e dá prioridade na lista que a empresa recebe. Por fim, o candidato pode excluir a própria conta a qualquer momento, e com ela saem todos os dados dele, como pede a LGPD.

### Empresa

A empresa é quem oferece as vagas. Ela tem um perfil com nome fantasia, CNPJ, setor, site, telefone, logotipo, cidade e uma descrição da empresa.

Para publicar uma vaga, a empresa tem três caminhos. Pode preencher o formulário normalmente, pode colar o texto do anúncio (aquele que circula no WhatsApp ou no Instagram) ou pode simplesmente enviar a foto do **cartaz** da vaga. Nos dois últimos casos, o sistema lê o conteúdo e preenche o formulário: cargo, empresa, salário, local, tipo de contratação, nível, descrição, requisitos, benefícios e contato. A empresa revisa tudo e só então publica. Antes de publicar, o sistema também avisa se já existe uma vaga aberta igual.

Quando os candidatos se inscrevem, a empresa vê as candidaturas já ordenadas pelo match, do perfil que mais combina para o que menos combina. Ela abre o currículo de cada um, muda o status da candidatura (em análise, entrevista, aprovado ou rejeitado) e o candidato acompanha essa mudança do lado dele. A empresa também pode consultar o **banco de talentos**, que reúne os perfis públicos dos candidatos.

No plano gratuito, a empresa pode ter até 2 vagas ativas ao mesmo tempo. O plano **Empresa Premium** libera vagas ilimitadas, coloca as vagas em destaque na busca e na página inicial, dá acesso completo ao banco de talentos (com busca e download de currículos) e mostra um selo de empresa Premium.

### Administrador

O administrador cuida da plataforma inteira. No painel dele, a primeira tela é uma visão geral com números e gráficos: candidaturas por dia, usuários novos, vagas por área, situação das candidaturas e assinaturas. A partir dali ele administra:

- os **usuários**, podendo criar, editar, ativar, desativar e excluir contas;
- as **categorias**, que são as áreas usadas para agrupar vagas e cursos;
- as **vagas** de todas as empresas, com os mesmos recursos de leitura de cartaz e de anúncio que a empresa tem;
- as **candidaturas** de toda a plataforma;
- os **cursos, e-books e vídeos** do catálogo de capacitação;
- as **assinaturas** dos planos.

O cadastro de cursos e e-books merece uma explicação à parte (está mais abaixo, em [Cursos, e-books e a biblioteca](#cursos-e-books-e-a-biblioteca)), porque ali o administrador cola uma ficha com os dados do curso e o sistema abre cada link da ficha para buscar a imagem, a capa e o PDF.

### Sistema

O quarto personagem é o próprio sistema. Ele faz várias tarefas sozinho, sempre em resposta a alguma coisa que um dos outros personagens fez:

- **lê os documentos**: o currículo do candidato, o cartaz e o anúncio da empresa e a ficha de curso do administrador;
- **calcula o match** entre candidatos e vagas sempre que um currículo é enviado, um perfil é salvo ou uma vaga é criada ou alterada;
- **monta os padrões automáticos** que ajudam a leitura de vagas e cursos, a partir do que já está cadastrado;
- **abre os links** das fichas de cursos, tira a capa da primeira página do PDF dos e-books e guarda os PDFs na biblioteca;
- **protege as contas**, pausando o login depois de muitas senhas erradas;
- **faz a limpeza**: uma vez por dia apaga arquivos enviados que não pertencem a mais ninguém.

## O sistema peça por peça

### Página inicial e navegação

A página inicial abre com um carrossel de fotos de Brasília e uma busca de vagas logo no topo. Abaixo vêm as vagas, os cursos e a apresentação dos planos, e um painel rápido mostra avisos que vão se alternando. O menu leva às vagas, aos cursos, aos e-books e aos planos. No rodapé e num dos avisos do painel rápido existe um convite discreto de **doação por Pix**, para quem quiser ajudar a manter o site no ar. O código Pix é montado pelo próprio sistema no padrão do Banco Central e o QR Code é desenhado no navegador, sem chamar nenhum serviço de fora.

### Cadastro, login e recuperação de senha

O cadastro pede nome, e-mail, senha, telefone e o tipo de conta (candidato ou empresa), além do aceite dos termos de uso e da política de privacidade. As senhas nunca são guardadas como texto: o banco guarda só o resumo criptográfico (hash) gerado pelo `password_hash`.

No login, decidimos ajudar quem erra por distração. O sistema aceita a senha mesmo com a primeira letra trocada de maiúscula para minúscula, com o Caps Lock ligado ou com espaço sobrando no começo ou no fim. Por outro lado, depois de 8 senhas erradas seguidas para o mesmo e-mail, o login daquele e-mail fica pausado por 5 minutos, o que impede alguém de ficar tentando senhas sem parar.

Quem esquece a senha pede um link de redefinição. Como o projeto roda na nossa máquina e não tem um servidor de e-mail configurado, no modo de demonstração o link aparece na tela e fica registrado em `storage/logs/redefinicoes_senha.log`, com o e-mail mascarado. O banco guarda só o resumo do código do link, nunca o código em si, e o link vale uma única vez.

### Vagas e candidaturas

A lista de vagas tem busca por palavra, filtros por cidade, área, tipo de contratação, nível e modelo de trabalho (presencial, remoto ou híbrido), e pode ser ordenada. Cada vaga tem uma página própria com o cartaz, a descrição, os requisitos, os benefícios e o contato do anúncio. Para o candidato logado, a página mostra também o match com aquela vaga.

Ao se candidatar, o candidato escolhe qual currículo enviar e pode escrever uma mensagem para a empresa. A candidatura passa pelos status *enviada*, *em análise*, *entrevista* e *aprovado* ou *rejeitado*, que são definidos pela empresa. O status *cancelada* só o próprio candidato pode escolher.

### Currículo e perfil do candidato

O envio do currículo aceita PDF, DOCX e DOC de até 10 MB. Para ler esses arquivos escrevemos leitores próprios em PHP. O de PDF entende currículos em duas colunas, comuns nos modelos do Canva e do Word, e lê cada coluna inteira antes de passar para a outra, para o texto não sair embaralhado.

Depois de ler o texto, a extração separa o currículo em partes. Ela reconhece os títulos das seções mesmo quando vêm sem acento, em letras maiúsculas, numerados, com ícones ou com as letras espaçadas ("E X P E R I Ê N C I A"). Com isso, ela preenche cada campo do perfil e padroniza as experiências e a formação. Os campos que o candidato já tinha preenchido são mantidos, a não ser que ele marque a opção de substituir. A foto também é procurada dentro do arquivo: o sistema escolhe a imagem com cara de foto de rosto e descarta logotipos, ícones e páginas escaneadas.

### Portfólio

O portfólio é a página profissional do candidato, montada a partir do perfil. As experiências viram uma linha do tempo, a formação é organizada por curso e instituição, e as habilidades e cursos aparecem em listas. Só para o dono, o portfólio também mostra as vagas compatíveis com a explicação de cada nota e recomenda cursos do catálogo que ajudam a completar o perfil. O candidato pode compartilhar o link do portfólio. Visitantes e candidatos veem o portfólio completo. Já a empresa sem o plano Premium vê uma prévia.

### Match entre candidato e vaga

O match é uma nota de 0 a 100 que dividimos em quatro partes:

| Parte | Peso | O que compara |
|---|---|---|
| Competências | 50 | competências pedidas pela vaga que o candidato tem |
| Cargo | 20 | título da vaga com o título profissional e o histórico do candidato |
| Localização | 15 | mesma cidade, mesma UF ou vaga remota |
| Nível | 15 | nível de experiência do candidato com o nível pedido |

A nota vem sempre com a explicação de cada parte, para o candidato entender por que combina mais com uma vaga do que com outra, e para a empresa entender a ordem da lista de candidatos. O match é recalculado sozinho quando o candidato envia ou exclui um currículo, quando salva o perfil e quando a empresa cria ou altera uma vaga. Também existe um botão para recalcular na hora.

### Leitura de cartazes e anúncios de vagas

Esta é uma das partes de que mais nos orgulhamos. A empresa envia a foto do cartaz e o texto é lido no próprio navegador, com o Tesseract.js (um leitor de texto em imagens) servido pelo nosso site. Por isso ninguém precisa instalar nada, nem no computador nem no servidor. Enquanto lê, o carregador fica amarelo e mostra a porcentagem. Quando termina, fica azul.

Com o texto em mãos, a extração de vagas aplica as regras que escrevemos a partir de dezenas de cartazes reais do DF. Ela encontra o cargo (inclusive quando vem em letra grande e quebrado em várias linhas), a empresa anunciante, o salário (sem confundir o valor do vale-transporte com o salário), a cidade, o tipo de contratação, o nível, o modelo de trabalho, o contato (WhatsApp, telefone ou e-mail) e a quantidade de vagas. As linhas restantes são separadas em descrição, requisitos e benefícios. No final aparece um relatório campo por campo, dizendo o que foi lido do anúncio, o que ficou com o valor padrão e o que não foi encontrado.

### Padrões automáticos das máquinas de extração

As regras da extração resolvem a maior parte dos casos, mas sempre aparece uma linha que não tem nenhuma palavra-chave conhecida, uma área que a regra não reconhece ou uma empresa cujo nome a regra não acha no texto. Para esses casos criamos os **padrões automáticos** (classe `PadroesExtracao`).

Funciona assim: na hora da leitura, o sistema olha as vagas e os cursos que já estão cadastrados e já foram revisados por uma pessoa, e conta em que campo cada palavra e cada par de palavras costuma aparecer. Antes de contar, o texto é normalizado caractere por caractere: tiramos os acentos, passamos tudo para minúsculas, removemos a pontuação e trocamos os números por `#`. Uma expressão só vira padrão quando aparece em pelo menos 2 cadastros diferentes e em pelo menos 90% deles no mesmo lugar. Por exemplo, "café da manhã" aparece nos benefícios de várias vagas cadastradas. Então, quando chega um anúncio novo com a linha "Café da manhã e lanche da tarde", o sistema coloca essa linha em Benefícios, mesmo que a regra não tenha uma palavra-chave para ela. Do mesmo jeito, "auxiliar de cozinha" no título indica a área de Alimentação, e "excel" no título de um curso indica a área de Informática e Excel.

Algumas decisões que tomamos sobre os padrões:

- a regra continua mandando, e o padrão só decide quando a regra não tem pista;
- não existe tabela nem tela de ajuste, porque os padrões são montados na hora a partir dos cadastros. Assim, cada vaga ou curso salvo já melhora a próxima leitura, sem ninguém precisar fazer nada;
- o resultado é sempre o mesmo para o mesmo texto e os mesmos cadastros, e cada decisão aparece no relatório da extração no bloco "Padrões automáticos usados", com o padrão que valeu;
- o currículo não entra nos padrões, porque tem dados pessoais dos candidatos. A leitura de currículos segue só as regras.

Os padrões também guardam os **nomes conhecidos**: as empresas (anunciante das vagas e nome fantasia das empresas cadastradas) e as instituições dos cursos. Quando a regra não acha o nome da empresa num cartaz novo, o sistema procura no texto algum desses nomes. Palavras genéricas como "Loja" ou "Empresa" nunca entram nessa lista. Mais detalhes em [docs/PADROES_AUTOMATICOS.md](docs/PADROES_AUTOMATICOS.md).

### Cursos, e-books e a biblioteca

O catálogo de capacitação reúne cursos, e-books e vídeos gratuitos de fontes oficiais, como Fundação Bradesco, Escola Virtual de Governo (Enap), SEBRAE, SENAC, FGV, Banco Central, Ministério do Trabalho e Microsoft Learn. A versão de demonstração vem com 64 conteúdos (43 cursos e 21 e-books), cada um com a sua imagem.

Para cadastrar um conteúdo novo, o administrador cola na caixa **Extrair** uma **ficha** com os campos Título, Tipo, Instituição, Modalidade, Cidade, Nível, Carga horária, Gratuito, Preço, Área, Link, PDF, Imagem e Descrição. A ficha pode ser preenchida à mão ou com ajuda de uma ferramenta de pesquisa (ChatGPT, Gemini, Perplexity e outras); o texto que usamos para pedir essa pesquisa está em [docs/PROMPTS_PESQUISA.md](docs/PROMPTS_PESQUISA.md). Uma ficha só preenche o formulário para revisar. Várias fichas, separadas por `---`, abrem uma prévia para cadastrar todas de uma vez.

Na prévia, o sistema **abre cada link da ficha** (o da imagem, o do PDF e o do conteúdo) e descobre o que cada um é:

- se é uma **imagem**, ela é usada como imagem do conteúdo;
- se é um **PDF**, o sistema desenha a primeira página do arquivo e usa como capa do e-book. Para isso usamos o leitor de PDF que já vem no Windows, chamado pelo PowerShell. Se ele não estiver disponível, o sistema pega a maior imagem da primeira página. Ao salvar, o PDF vai para a nossa **biblioteca**;
- se é uma **página** da internet, o sistema procura nela o PDF do e-book e a imagem de divulgação. Logotipos e ícones são descartados;
- se o link está fora do ar, ou se diz ser um PDF mas abre uma página, aparece um **aviso** na prévia para o administrador conferir o endereço antes de salvar.

A imagem nunca impede o cadastro. Se a ficha não tem imagem, ou se o link não baixa, o conteúdo entra com a imagem da instituição ou com a imagem padrão da plataforma e aparece na lista no filtro "Com imagem padrão". Para terminar o cadastro, o administrador abre o conteúdo em Editar e troca a imagem de uma destas formas: enviando o arquivo, colando o link direto da imagem ou escolhendo uma imagem que já está no site.

Os e-books cujo PDF está na biblioteca mostram o botão **Baixar**, e o arquivo vem direto do nosso site, com o crédito da fonte original. Os conteúdos que ficam em outro site mostram o botão **Acessar**, que abre a página de origem em outra aba.

### Planos e assinaturas

Existem dois planos pagos, os dois por 30 dias e sem fidelidade: o **Candidato VIP**, por R$ 9,90, e o **Empresa Premium**, por R$ 49,90. Como é um trabalho acadêmico, a assinatura é demonstrativa e não existe cobrança de verdade. A tela mostra o plano atual, o histórico e as vantagens de cada um, e o administrador acompanha tudo em Assinaturas.

### Tarefas automáticas

Quando o administrador abre a visão geral do painel, o sistema confere se já fez a manutenção do dia. Se não fez, apaga de `storage/uploads` os arquivos que nenhum registro usa e que têm mais de 24 horas. São sobras de contas excluídas, de cartazes lidos e não usados ou de capas tiradas de fichas que acabaram não sendo salvas.

## Como o código está organizado

Seguimos o padrão MVC. O **Model** cuida do banco de dados (uma classe por tabela, na pasta `Models`), a **View** é a tela em HTML com PHP (pasta `Views`) e o **Controller** recebe o pedido, confere se a pessoa tem permissão e decide o que mostrar (pasta `Controllers`). As regras de negócio maiores, como o match e as máquinas de extração, ficam na pasta `Services`.

Todo pedido passa por uma única porta de entrada, o arquivo `public/index.php`. Por exemplo, quando o navegador pede `/vagas.php`:

1. o `.htaccess` da raiz manda o pedido para `public/index.php`;
2. o `app/Core/bootstrap.php` carrega as configurações e prepara o carregamento automático das classes;
3. a sessão é aberta e a conta logada é conferida no banco;
4. o roteador (`app/Core/Router.php`) procura `vagas.php` na tabela de rotas e chama `VagaController::lista()`;
5. o controller lê os filtros, busca as vagas no `VagaDAO` e o match no `MatchDAO`;
6. a tela `app/Views/vagas/lista.php` é mostrada entre o cabeçalho e o rodapé.

Os endereços das páginas são os mesmos desde as primeiras versões do projeto (`vaga.php?id=3`, `view/perfil/index.php`, `admin/pages/vagas.php` e assim por diante), então links antigos continuam funcionando.

```
tcc-final/
├── .htaccess              manda todos os pedidos para public/
├── index.php              reserva para servidor sem mod_rewrite
├── app/                   código da aplicação (o navegador não acessa)
│   ├── Controllers/       recebem o pedido, conferem a permissão e escolhem a tela
│   ├── Core/              núcleo: rotas, sessão, login, CSRF, uploads, banco e telas
│   ├── DTO/               objetos que levam os dados do formulário até o banco
│   ├── Models/            acesso ao banco, uma classe por tabela
│   ├── Services/          match, competências, portfólio, Pix e as máquinas de extração
│   │   └── Extracao/      leitores de PDF/DOCX/DOC, extração de currículo, vaga e curso,
│   │                      padrões automáticos, abertura de links e capa do PDF
│   └── Views/             telas: layouts, partes reutilizáveis e páginas
├── config/config.php      configurações (banco, depuração, limites, pastas)
├── database/
│   ├── schema.sql         estrutura do banco (11 tabelas)
│   ├── seed.sql           dados de demonstração
│   └── resetar_senhas.php devolve as senhas das contas de teste
├── docs/                  documentação, monografia e diagramas
├── public/                única pasta servida pelo Apache
│   ├── index.php          porta de entrada e tabela de rotas
│   └── assets/            CSS, JavaScript, imagens e o leitor de cartaz (Tesseract.js)
├── storage/               arquivos gerados (uploads, logs e backups), fora do alcance do navegador
└── tests/                 testes automáticos (lint, smoke e jornadas)
```

O funcionamento interno, com a tabela completa de rotas e o detalhe de cada camada, está em [docs/ARQUITETURA.md](docs/ARQUITETURA.md).

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

As chaves estrangeiras usam `ON DELETE CASCADE`. Quando um usuário é excluído, saem junto o perfil, os currículos, as vagas, as candidaturas e os matches dele. Os padrões automáticos da extração não têm tabela, porque são montados na hora a partir das vagas e dos cursos.

## Segurança e LGPD

Levamos a segurança a sério desde o começo:

- as senhas são guardadas com `password_hash` (bcrypt), e o login pausa sozinho depois de muitas tentativas erradas;
- todo formulário tem um código de proteção contra envio forjado (token CSRF), toda consulta ao banco usa parâmetros (nunca texto colado direto no SQL) e todo texto mostrado na tela passa pela função `e()`, que impede a injeção de código;
- cada ação confere a permissão: o candidato só mexe no que é dele, a empresa só nas próprias vagas e candidaturas, e o currículo só abre para o dono, para a empresa que o recebeu ou para empresa Premium;
- os links das fichas de cursos só são abertos se forem endereços http ou https de servidores públicos, nunca da rede interna, com limite de tamanho e de tempo;
- só a pasta `public/` é servida pelo Apache, e lá dentro só o `index.php` executa PHP. Configurações, banco, backups e documentos respondem com acesso negado;
- o site envia cabeçalhos de segurança (`Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` e `Permissions-Policy`).

Sobre a **LGPD**: no cadastro a pessoa aceita os termos e a política de privacidade. O candidato pode excluir a própria conta em Meu perfil, Seus dados, Excluir minha conta, confirmando com a senha. Saem o perfil, os currículos e os arquivos, a foto, as candidaturas, o match e as tentativas de login. Os registros internos nunca guardam o e-mail inteiro, e os padrões automáticos nunca usam dados do currículo ou do perfil do candidato.

## Como instalar e rodar

1. Copie a pasta do projeto para `C:\xampp\htdocs\`. Na nossa máquina ela fica em `C:\xampp\htdocs\tcc-final`.
2. Abra o XAMPP Control Panel e clique em **Start** no Apache e no MySQL. O `mod_rewrite` do Apache, que já vem ativo no XAMPP, é necessário.
3. Importe o banco, primeiro a estrutura e depois os dados de demonstração. Pelo phpMyAdmin, use Importar com `database/schema.sql` e depois com `database/seed.sql`. Ou, pelo Prompt de Comando (cmd), dentro da pasta do projeto (no PowerShell o `<` não funciona):
   ```
   C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < database\schema.sql
   C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < database\seed.sql
   ```
   O `schema.sql` apaga e cria de novo apenas o banco `tcc_final`. O banco da versão anterior (`conecta_vagas_df_v2`) não é tocado.
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

Criamos três testes automáticos, que ficam na pasta `tests`:

- o `lint.php` confere a sintaxe de todos os arquivos PHP;
- o `smoke.php` é o teste rápido. Faz 112 verificações das regras de negócio, das máquinas de extração (com exemplos reais de cartazes, anúncios e currículos), dos padrões automáticos, da capa do PDF, do banco e das páginas;
- o `jornadas.php` usa o sistema como uma pessoa usaria, pelo navegador. Cria uma conta temporária, faz cadastro, login, envio de currículo, candidatura, leitura de vaga e de ficha de curso, troca de senha e exclusão da conta. São 18 verificações, e no fim a conta é apagada.

O jeito mais fácil de rodar tudo é o clique duplo em `tests\verificar.bat`. No fim aparece TUDO CERTO, ou o que quebrou, com o arquivo e a linha. O teste de jornadas precisa da extensão zip do PHP, que vem desligada no XAMPP. O `verificar.bat` liga essa extensão só durante o teste, sem mexer no `php.ini`. O site em si funciona sem ela.

Também deixamos o Git protegido: antes de cada commit, o arquivo `.githooks/pre-commit` roda os mesmos testes, e se algo quebrar o commit não é feito. Numa cópia nova do projeto, esse gancho é ligado com:
```
git config core.hooksPath .githooks
```

## Roteiro para a apresentação

1. Deixar o notebook na tomada. Na bateria, o processador fica mais lento e a leitura do cartaz demora o dobro.
2. No XAMPP, dar Start no Apache e no MySQL.
3. Clique duplo em `tests\verificar.bat` e esperar o TUDO CERTO.
4. Abrir uma vez o painel de vagas, para o leitor de cartaz já ficar carregado.
5. Mostrar o **candidato**: enviar um currículo, ver o perfil preenchido, o portfólio e o match com as vagas, e se candidatar.
6. Mostrar a **empresa**: enviar a foto de um cartaz, mostrar o formulário preenchido e o relatório da extração, com o bloco "Padrões automáticos usados". Depois, abrir as candidaturas ordenadas pelo match e mudar um status.
7. Mostrar o **administrador**: a visão geral com os gráficos e o cadastro de um e-book por ficha, com a capa tirada da primeira página do PDF.
8. Explicar o papel do **sistema** em cada um desses passos.
9. Ao terminar, dar Stop no MySQL antes de fechar o XAMPP.

## Documentação do trabalho

- [docs/ARQUITETURA.md](docs/ARQUITETURA.md): como o sistema funciona por dentro, camada por camada.
- [docs/PADROES_AUTOMATICOS.md](docs/PADROES_AUTOMATICOS.md): os padrões automáticos das máquinas de extração.
- [docs/PROMPTS_PESQUISA.md](docs/PROMPTS_PESQUISA.md) e [docs/PESQUISA_CURSOS.md](docs/PESQUISA_CURSOS.md): como pesquisamos os cursos e e-books do catálogo.
- [docs/tcc/TCC_Final_Conecta_Vagas_DF.pdf](docs/tcc/TCC_Final_Conecta_Vagas_DF.pdf): a monografia. O arquivo de origem fica em `docs/tcc/monografia/`. Para gerar o PDF de novo, é só abrir o HTML no Edge e imprimir como PDF, sem cabeçalhos e rodapés e com gráficos de plano de fundo.
- `docs/tcc/diagramas/`: os diagramas de casos de uso, classes, sequência, arquitetura e os modelos conceitual e lógico, em `.png` e `.svg`. Os de UML também têm o arquivo `.puml` de origem.

## Problemas comuns

**Não consigo entrar.** Use o botão do olho, ao lado do campo de senha, para conferir o que foi digitado. Pelo próprio computador, a mensagem de erro diz o motivo exato: e-mail sem conta, senha errada ou conta desativada. De outra máquina, a mensagem é sempre a mesma, para não revelar quais e-mails têm conta. Se o login pausou depois de muitas tentativas, ele libera sozinho em 5 minutos. Para devolver as senhas das três contas de teste, reativar as contas e tirar qualquer pausa, rode:
```
C:\xampp\php\php.exe database\resetar_senhas.php
```

**Apareceu "Sua sessão foi encerrada porque a senha da conta foi alterada".** A senha daquela conta mudou em outro lugar. É só entrar de novo com a senha nova.

**O cartaz demora para ser lido.** Na primeira vez o navegador baixa o leitor de cartaz, que depois fica guardado. Com o notebook na bateria a leitura também fica mais lenta.

## Backup, depuração e publicação

Os backups do banco ficam em `storage/backups/<data>/`, junto com um arquivo `COMO_RESTAURAR.txt`. Para fazer um backup novo:
```
C:\xampp\mysql\bin\mysqldump.exe -u root --single-transaction --databases tcc_final > storage\backups\banco_tcc_final.sql
```

Para voltar o código a um ponto conhecido, use as tags do Git: `v1.0-blindada` é a versão anterior ao TCC Final, e `tcc-final-v1.0` é a primeira versão do TCC Final.

Quando o site é acessado pelo próprio computador, os erros mostram o detalhe técnico, o que ajuda no desenvolvimento. Quem acessa de outra máquina vê só mensagens amigáveis. Para publicar, defina a variável de ambiente `APP_DEBUG=0`, que desliga o detalhe técnico para todos, inclusive quando o site fica atrás de um proxy.
