# Arquitetura do Conecta Vagas DF

Neste documento explicamos como o sistema funciona por dentro, camada por camada. A instalação e a visão geral
estão no [README](../README.md).

Os personagens do projeto são quatro: o Candidato, a Empresa, o Administrador e o Sistema. Quem navega pelo site sem
conta é tratado como um candidato que ainda não se cadastrou. O Sistema aparece quando faz alguma coisa sozinho, como
a extração dos dados, os padrões automáticos, o cálculo do match, a capa do PDF dos e-books e a limpeza dos arquivos
sem uso.

## 1. Visão geral

Organizamos o projeto no padrão MVC (Model, View e Controller) em PHP puro, sem framework. Além das três camadas do
MVC, criamos uma camada de Services, onde ficam as regras de negócio, e um Core, onde fica a infraestrutura que todo
o resto usa.

Quando o navegador pede uma página, por exemplo `/vagas.php`, o caminho é sempre o mesmo. O arquivo `.htaccess` da
raiz manda o pedido para a pasta `public/` (se o arquivo existir de verdade, como um CSS, ele é entregue direto). Lá,
o `public/index.php` funciona como porta de entrada única, o chamado front controller. Ele carrega o
`app/Core/bootstrap.php` (configuração, núcleo e carregamento automático das classes), entrega as imagens enviadas
pelos usuários, abre a sessão, confere a conta logada, envia os cabeçalhos de segurança e pede ao roteador a ação
certa. No exemplo, o roteador chama `VagaController::lista()`. O controller busca os dados nos models (que falam com
o MySQL) e nos services (que aplicam as regras, como o match e a extração), e no fim a view monta o HTML entre o
cabeçalho e o rodapé do layout.

A tabela abaixo resume o papel de cada camada e o que ela não deve fazer.

| Camada | Pasta | O que faz | O que não faz |
|---|---|---|---|
| Controller | `app/Controllers` | Recebe a requisição, confere a permissão e o CSRF, lê o formulário, chama models e services, redireciona ou mostra a tela | SQL e HTML |
| Model | `app/Models` | SQL com PDO e consultas preparadas, uma classe (DAO) por tabela | Ler `$_POST` ou gerar HTML |
| Service | `app/Services` | Regras de negócio: match, dicionário de competências, extração, padrões automáticos, portfólio | Ler `$_POST` ou gerar HTML |
| View | `app/Views` | Exibir os dados já prontos (HTML com PHP) | Consultar o banco |
| Core | `app/Core` | Rotas, sessão, login, CSRF, uploads, conexão, telas e erros | Regras de negócio |
| DTO | `app/DTO` | Levar os dados de um formulário até o DAO | (nada além disso) |

## 2. Arquivos, camada por camada

### Núcleo (app/Core)

| Arquivo | O que faz |
|---|---|
| `bootstrap.php` | Inicializa a aplicação: carrega `config/config.php`, ajusta erros e fuso horário, carrega o núcleo, registra o autoloader e calcula `BASE_URL` e `SITE_URL`. É usado pelo site e pelos testes. |
| `Autoloader.php` | Carrega uma classe na primeira vez que ela é usada (`new VagaDAO()` abre `app/Models/VagaDAO.php`). Por isso não há `require_once` espalhados pelo código. |
| `Router.php` | Tabela de rotas, que liga cada endereço a um controller e uma ação. Também guarda a rota atual, que o menu usa para marcar o item ativo. |
| `Controller.php` | Classe base dos controllers, com o método `view('pasta/tela', get_defined_vars())`. |
| `View.php` | `View::render()` (cabeçalho, tela e rodapé), `pagina_erro()` (403, 404, 500 e 503) e o tratador global de exceções. |
| `Database.php` | Conexão PDO única com o MySQL (Singleton), com mensagens de erro em português. |
| `Session.php` | Sessão segura, mensagens rápidas (flash), login, logout e revalidação da conta a cada requisição. |
| `Auth.php` | Funções de permissão: `usuarioLogado()`, `isAdmin()`, `isEmpresa()`, `isCandidato()`, `exigirLogin()`, `exigirAdmin()` e `destinoPainel()`. |
| `Csrf.php` | Token contra CSRF: `csrf_token()`, `csrf_campo()` e `validar_csrf()`. |
| `Upload.php` | Imagens enviadas, caminho real dos arquivos (`caminho_upload`), limpeza de arquivos sem uso e a manutenção diária. |
| `helpers.php` | Funções gerais: `e()` (escapa o HTML), `url()`, `redirect()`, leitura segura de formulários, conversões e rótulos. |

### Controllers (um por área do sistema)

| Controller | Ações e endereços |
|---|---|
| `HomeController` | Página inicial (`index.php`) e termo de privacidade (`contrato.php`) |
| `VagaController` | Lista de vagas (`vagas.php`) e página da vaga (`vaga.php`) |
| `CursoController` | Listas separadas por formato: cursos (`cursos.php`), e-books (`?tipo=ebook`) e vídeos (`?tipo=video`), e a página do conteúdo (`curso.php`) |
| `CandidaturaController` | Candidatar-se (`candidatar.php`) e cancelar a candidatura |
| `PlanosController` | Planos e assinaturas (`planos.php`) |
| `AuthController` | Login, cadastro e sair |
| `PasswordController` | Esqueci a senha e redefinir a senha |
| `PerfilController` | Meu perfil, salvar o perfil, portfólio, recalcular o match e excluir a própria conta |
| `CurriculoController` | Envio do currículo (extração), aplicar os dados do relatório e excluir o currículo |
| `ArquivoController` | Imagens enviadas (`assets/uploads/...`) e download do currículo (`download.php`) |
| `AdminController` | Painel (`admin/index.php`), usuários, categorias, cursos e assinaturas |
| `EmpresaController` | Vagas (com extração), candidaturas recebidas, banco de talentos e perfil da empresa |

São 12 controllers ao todo.

### Models (acesso ao banco)

| Model | Tabelas |
|---|---|
| `UsuarioDAO` | `usuarios`, `tentativas_login` e `redefinicoes_senha` |
| `PerfilDAO` | `perfis` (do candidato e da empresa) e o banco de talentos |
| `VagaDAO` | `vagas` |
| `CursoDAO` | `cursos` |
| `CategoriaDAO` | `categorias` |
| `CurriculoDAO` | `curriculos` |
| `CandidaturaDAO` | `candidaturas` |
| `MatchDAO` | `matches` |
| `AssinaturaDAO` | `assinaturas` e as regras dos planos |

### Services (regras de negócio)

| Service | O que faz |
|---|---|
| `Competencias` | Dicionário único de competências e sinônimos, usado pela extração e pelo match. |
| `MatchService` | Calcula a nota de compatibilidade entre candidato e vaga (de 0 a 100, com explicação). |
| `Portfolio` | Valida o cadastro e organiza o portfólio (linha do tempo, formação e links). |
| `Pix` | Monta o código Pix "copia e cola" (BR Code do Banco Central, com CRC16) do QR Code de doação do rodapé. |
| `Extracao/LeitorDocumento` | Lê o texto de arquivos PDF, DOCX e DOC (com a ajuda de `PdfTexto` e `DocxTexto`). |
| `Extracao/ExtracaoCurriculo` | Separa os dados do currículo (contato, experiências, formação e o resto). |
| `Extracao/AplicacaoCurriculo` | Aplica os dados extraídos no perfil, preenchendo, mantendo ou juntando cada campo. |
| `Extracao/ExtracaoVaga` | Transforma o texto de um anúncio ou de um cartaz nos campos da vaga. |
| `Extracao/ExtracaoCurso` | Transforma o texto de divulgação ou a ficha da pesquisa nos campos do curso. |
| `Extracao/PadroesExtracao` | Padrões automáticos: onde a regra não tem pista, decide o padrão contado nas vagas e nos cursos já cadastrados. Explicamos em [PADROES_AUTOMATICOS.md](PADROES_AUTOMATICOS.md). |
| `Extracao/ImagemRemota` | Abre cada link da ficha de curso ou e-book, descobre se é imagem, PDF ou página e baixa o que serve, sempre de servidor público. |
| `Extracao/CapaPdf` | Tira a capa do e-book da primeira página do PDF, com o leitor de PDF do Windows (via PowerShell) ou, sem ele, com a maior imagem da página. |
| `Extracao/OcrImagem`, `FontesCursos` | Leitura do cartaz por OCR e fontes oficiais de cursos. |

### Views (telas)

As telas ficam em `app/Views`. A pasta `layouts/` tem o cabeçalho com o menu e as mensagens (`header.php`), o rodapé
(`footer.php`) e as abas do painel (`admin_nav.php`). A pasta `partials/` guarda as partes que se repetem: os ícones em
SVG, os cartões, faixas, datas e preços (`componentes.php`) e o relatório da extração do currículo
(`relatorio_extracao.php`). A página de erro (`errors/erro.php`) não usa o layout, para funcionar mesmo com o banco fora
do ar. O resto está dividido em uma pasta por área: `home/`, `vagas/`, `cursos/`, `planos/`, `auth/`, `perfil/` e
`admin/`. Cada tela começa com um comentário que diz qual rota a exibe e quais variáveis ela recebe do controller.

## 3. Tabela de rotas

As rotas são definidas em `public/index.php` e aceitam GET (para mostrar a tela) e POST (para enviar formulários).
São 32 rotas.

| Endereço | Controller e ação | Tela | Quem acessa |
|---|---|---|---|
| `/` ou `index.php` | `HomeController::index` | `home/index` | qualquer pessoa |
| `contrato.php` | `HomeController::contrato` | `home/contrato` | qualquer pessoa |
| `vagas.php` | `VagaController::lista` | `vagas/lista` | qualquer pessoa |
| `vaga.php?id=` | `VagaController::detalhe` | `vagas/detalhe` | qualquer pessoa (vaga fechada: só a empresa dona e o administrador) |
| `cursos.php` | `CursoController::lista` | `cursos/lista` | qualquer pessoa |
| `curso.php?id=` | `CursoController::detalhe` | `cursos/detalhe` | qualquer pessoa (conteúdo oculto: só o administrador) |
| `planos.php` | `PlanosController::index` | `planos/index` | qualquer pessoa (para assinar, precisa entrar) |
| `login.php` | `AuthController::login` | `auth/login` | quem ainda não entrou |
| `cadastro.php` | `AuthController::cadastro` | `auth/cadastro` | quem ainda não tem conta |
| `view/usuario/logout.php` | `AuthController::logout` | `auth/logout` | quem está logado |
| `esqueci_senha.php` | `PasswordController::esqueci` | `auth/esqueci_senha` | quem ainda não entrou |
| `redefinir_senha.php?token=` | `PasswordController::redefinir` | `auth/redefinir_senha` | quem tem o link |
| `view/perfil/index.php` | `PerfilController::index` | `perfil/index` | candidato |
| `view/perfil/salvar.php` | `PerfilController::salvar` | (redireciona) | candidato |
| `view/perfil/portfolio.php[?id=]` | `PerfilController::portfolio` | `perfil/portfolio` | o dono, ou outros se o perfil for público |
| `view/perfil/recalcular_match.php` | `PerfilController::recalcularMatch` | (redireciona) | candidato |
| `view/perfil/conta_excluir.php` | `PerfilController::excluirConta` | (redireciona) | candidato |
| `view/perfil/curriculo_upload.php` | `CurriculoController::upload` | (redireciona) | candidato |
| `view/perfil/aplicar_extracao.php` | `CurriculoController::aplicarExtracao` | (redireciona) | candidato |
| `view/perfil/curriculo_excluir.php` | `CurriculoController::excluir` | (redireciona) | candidato |
| `candidatar.php?vaga_id=` | `CandidaturaController::candidatar` | `vagas/candidatar` | candidato |
| `view/perfil/candidatura_cancelar.php` | `CandidaturaController::cancelar` | (redireciona) | candidato |
| `download.php?id=` | `ArquivoController::download` | (arquivo) | o dono, o administrador e a empresa autorizada |
| `assets/uploads/<arquivo>` | `ArquivoController::imagem` | (imagem) | qualquer pessoa (só imagens) |
| `admin/index.php` | `AdminController::painel` | `admin/painel` | administrador e empresa |
| `admin/pages/usuarios.php` | `AdminController::usuarios` | `admin/usuarios` | administrador |
| `admin/pages/categorias.php` | `AdminController::categorias` | `admin/categorias` | administrador |
| `admin/pages/cursos.php` | `AdminController::cursos` | `admin/cursos` | administrador |
| `admin/pages/assinaturas.php` | `AdminController::assinaturas` | `admin/assinaturas` | administrador |
| `admin/pages/vagas.php` | `EmpresaController::vagas` | `admin/vagas` | empresa (as próprias vagas) e administrador |
| `admin/pages/candidaturas.php` | `EmpresaController::candidaturas` | `admin/candidaturas` | empresa e administrador |
| `admin/pages/talentos.php` | `EmpresaController::talentos` | `admin/talentos` | empresa e administrador |
| `admin/pages/empresa_perfil.php` | `EmpresaController::perfil` | `admin/empresa_perfil` | empresa |

Mantivemos os mesmos endereços das versões anteriores para não quebrar links e favoritos. Hoje eles são apenas nomes
de rota: não existem mais arquivos como `vagas.php` ou `view/perfil/index.php` no disco.

## 4. Fluxo principal

O caminho que mais mostramos na apresentação é o do candidato. A tela Meu perfil (`PerfilController::index`) é ao mesmo
tempo o cadastro e a máquina de extração. O candidato envia o currículo, o `LeitorDocumento` lê o arquivo e a
`ExtracaoCurriculo` separa os dados. Depois a `AplicacaoCurriculo` preenche o cadastro, e o relatório da extração mostra
o que foi encontrado, o que foi aplicado, o que foi mantido e o que faltou. Com o relatório aberto, o Meu perfil não
repete a caixa da máquina de extração: logo abaixo do relatório vem só o formulário, para completar o que faltou.

Quando o telefone, o título profissional, a cidade, o resumo, o objetivo, a formação e as habilidades estão preenchidos,
o `Portfolio::validarCadastro` considera o cadastro completo e a aba Portfólio aparece no topo. O portfólio é montado com
os dados do cadastro e com a máquina de match: o `MatchService` compara o candidato com cada vaga usando o dicionário de
`Competencias`. O candidato vê a nota e os cursos que cobrem o que falta, e a empresa vê o match em cada candidatura.

O portfólio fica numa folha A4 (`.pf-folha`, 21 cm de largura e 1 cm de margem) e o botão "Salvar em PDF" imprime só
ela, com `@page folha` na mesma medida, então o PDF sai igual à tela. A faixa do topo é uma imagem só
(`portfolio-faixa.jpg`, a foto de Brasília com o degradê azul já aplicado), porque camadas transparentes se perdiam no
PDF. Para o dono, o botão "Visualizar candidaturas" abre acima da folha a lista das vagas em que ele se candidatou
(`CandidaturaDAO::listarPorCandidato`), com o status e o retorno da empresa. A lista, o relatório da extração e a máquina
de match ficam fora da folha e não saem no PDF.

A empresa faz um caminho parecido com as vagas. Ela cola o anúncio ou envia o cartaz, a `ExtracaoVaga` preenche o
formulário, a empresa revisa e publica, e o Sistema recalcula o match com todos os candidatos. Nas extrações de vaga e de
curso, as regras do código mandam, e só onde elas não têm pista entram os padrões automáticos tirados dos cadastros
(`PadroesExtracao`). O relatório da extração da vaga mostra quais padrões foram usados. O currículo segue só as regras,
por causa dos dados pessoais (LGPD).

## 5. Máquinas de extração

### Currículo

A extração do currículo começa em `CurriculoController::upload`. Para PDF, escrevemos um leitor próprio em PHP
(`PdfTexto`) que entende fontes com mapa de caracteres (ToUnicode e CMap), object streams, a posição de cada trecho e
currículos em duas colunas, como os modelos do Canva e do Word. Se o PDF não tiver mapa de caracteres, usamos o
`pdftotext`, quando ele estiver instalado. O DOCX é aberto com o `ZipArchive` ou com um leitor de ZIP interno (para quando
a extensão zip está desligada), e o XML é lido com DOM (`DocxTexto`). O DOC antigo é lido pelo `antiword`, se existir, ou
por uma leitura aproximada do arquivo.

O texto é dividido em seções pelos títulos, mesmo com variações de escrita: resumo, objetivo, experiências, formação,
cursos, habilidades, competências, idiomas e informações adicionais (PCD, CNH e outras). Também extraímos nome, e-mail,
telefone, data de nascimento, cidade e UF (com as regiões do DF e do entorno), links, CNH, pretensão salarial,
disponibilidade, a foto e o nível, que calculamos pelo tempo somado das experiências.

Na hora de aplicar no perfil, o campo vazio recebe o valor, o campo já preenchido é mantido (a não ser que o candidato
marque "Substituir") e as listas são juntadas. O nome da conta só muda se o candidato confirmar no relatório.

A foto é escolhida pelo `LeitorDocumento::extrairFoto`. Ele junta as imagens do DOCX (`word/media`) e do PDF (JPEG e
imagens compactadas remontadas em PNG pelo `PdfTexto::imagens`) e dá uma nota para cada uma: tons de pele, variedade de
cores e proporção de retrato contam a favor, e logotipo, ícone, banner e página escaneada são descartados. A vencedora é
salva como JPEG de até 800 pixels. Se o perfil não tem foto, ela é aplicada; se já tem, a foto do currículo aparece no
relatório para o candidato decidir.

### Vagas

No painel, em Vagas, a empresa pode enviar o cartaz (uma imagem, que o leitor da plataforma lê por OCR) ou colar o
anúncio que recebeu pelo WhatsApp, pelo Instagram ou por um site. O Sistema preenche o título, a empresa anunciante, o
salário (ignorando os valores de vale-transporte e vale-refeição), a cidade, o tipo, o nível, o modelo de trabalho, a
descrição, os requisitos, os benefícios, o contato, a quantidade de vagas e a área.

A leitura começa assim que a pessoa escolhe o arquivo, e o cartaz vira a imagem da vaga. O relatório da extração mostra,
campo a campo, se o valor foi lido do anúncio, se é um valor padrão (quando o anúncio não diz, como o nível "Júnior") ou
se não foi encontrado, junto com os avisos do que conferir. O texto lido no cartaz fica numa caixa que pode ser editada:
dá para corrigir o que o OCR leu errado e extrair de novo sem perder o cartaz. A descrição ganha uma frase de abertura
montada com o que foi lido, como "Grupo Dourado contrata Auxiliar de Cozinha em Águas Claras.". Também tratamos as seções
curtas ("Horário:", "Local:") para não engolirem as linhas seguintes, os códigos de vaga como "(cód. 1308)", os prefixos
como "Temporário -" e frases como "está contratando X" no título.

O leitor de cartaz não precisa de instalação. O OCR roda no navegador de quem envia o cartaz, com o Tesseract.js 7 em
WebAssembly e o modelo de português `best_int`, tudo servido pelo próprio site (`public/assets/js/leitor-cartaz.js` e
`public/assets/js/vendor/tesseract/`). Ele faz quatro leituras (a imagem em cinza com gama ajustada e ampliada para cerca
de 2200 pixels, e o negativo dela, cada uma em dois modos de página) e envia o resultado em TSV junto com o cartaz. No
servidor, `OcrImagem::leiturasDoNavegador` confere se as leituras são válidas e `OcrImagem::montar` aplica os mesmos
ajustes; leitura forjada ou inválida é ignorada e o servidor lê sozinho. O Tesseract instalado no servidor ficou
apenas como reserva, para quando o navegador não consegue ler. Enquanto o leitor carrega ou lê, um carregador amarelo
mostra a porcentagem; quando fica pronto, ele fica azul. Esse carregador aparece em todas as máquinas de extração e
também impede o clique duplo.

Fomos ajustando a leitura do cartaz com cartazes reais. Uma palavra partida pelo OCR é juntada quando aparece inteira em
outra leitura ("MÁQUI NA" vira "MÁQUINA"); um cargo escrito em várias linhas de letra grande vira um título só; slogans
e restos de logotipo não entram em campo nenhum; nome de empresa não aceita pedaço de palavra; e "R$ 48,00 por dia" vai
para o vale-refeição. Com os cartazes da Smile & Face e da Mimória (leituras reais do navegador guardadas em
`tests/amostras/`), passamos a reconhecer que um shopping não é a empresa, que o ramo sozinho ("ODONTOLOGIA") não
continua o cargo, que uma marca escrita em duas linhas é confirmada pelo e-mail do cartaz e que uma palavra grudada pelo
OCR pode ser separada quando outra leitura tem as duas partes. Esses cartazes viraram testes permanentes no
`tests/smoke.php`.

### Cursos e e-books

No painel, em Cursos e e-books, existe uma caixa só, chamada Extrair. Uma ficha (ou um texto de divulgação) preenche o
formulário, e várias fichas separadas por `---` abrem uma prévia para cadastrar de uma vez. O formato da ficha é um só,
com os rótulos que `ExtracaoCurso::fichas()` lê, e as fontes oficiais da pesquisa estão em
[PESQUISA_CURSOS.md](PESQUISA_CURSOS.md). Antes de ler a ficha, o Sistema tira as marcas de formatação e de citação que
costumam vir no texto copiado, como o negrito e o `[1]`.

Na prévia, o Sistema abre cada link da ficha (`ImagemRemota::completar`). Ele baixa só o começo de cada endereço, o
suficiente para descobrir se é uma imagem, um PDF ou uma página, e aproveita o que serve. Se o campo Imagem aponta para
uma imagem que abre de verdade, ela vira a imagem do conteúdo e é baixada ao salvar, com até 800 pixels. Se um dos links
é um PDF e o e-book não tem imagem, a capa sai da primeira página do PDF: o `CapaPdf::gerar` usa o leitor de PDF do
próprio Windows (`Windows.Data.Pdf`, o mesmo do Edge), chamado pelo PowerShell com o script
`app/Services/Extracao/capa-pdf.ps1`, que desenha a página inteira de todos os PDFs de uma vez. Sem Windows ou sem
PowerShell, usamos a maior imagem desenhada na primeira página (`PdfTexto::capa`). A capa é gravada em `storage/uploads`
e a prévia avisa que ela foi tirada do PDF. Esse PDF também vai para a biblioteca quando o conteúdo é salvo (na prévia,
baixamos no máximo 80 MB só para tirar a capa). Se o link é uma página, procuramos nela o PDF oferecido (`pdfDaPagina`) e a
imagem de divulgação (a metatag og:image), deixando de fora logotipo, favicon, sprite e avatar. Quando um link não abre,
quando um endereço terminado em .pdf devolve uma página, quando o link do PDF não abre um PDF ou quando o e-book não tem
PDF em nenhum link, a prévia mostra um aviso para conferir.

A imagem nunca impede o cadastro. Sem imagem, ou com um link que não baixa, o conteúdo entra com o banner da instituição
ou com a imagem padrão do formato (`CursoDAO::IMAGENS_PADRAO`, em `public/assets/img/padrao/`), e a lista marca "trocar
imagem" para alguém ajustar depois.

O PDF enviado no cadastro vai para a biblioteca da plataforma, em `storage/uploads/biblioteca_*.pdf` (conferido pelo
conteúdo, até 25 MB). Ele é entregue ao público pelo `ArquivoController::imagem`, que só libera PDFs com esse prefixo,
então um currículo nunca sai por ali. O botão de cada conteúdo é decidido pelo endereço (`pt_acesso_conteudo`): se o PDF
está na biblioteca, o botão é "Baixar"; se o conteúdo fica em outro site, o botão é "Acessar" e abre em outra aba. A
ficha também tem o campo PDF, com o link direto do arquivo do e-book gratuito. Ao salvar um e-book com a opção "Guardar o
PDF na nossa biblioteca" (que vem marcada para e-book), ou ao cadastrar um lote, o `ImagemRemota::pdfs` baixa o PDF do
campo PDF, do link oficial ou da página (pela metatag `citation_pdf_url` de repositórios como o eduCAPES, ou pelo link
de baixar da página). O download vai direto para o disco, seis por vez, com até 150 MB e 180 segundos cada, só de
servidor público e só se o arquivo for um PDF inteiro. A descrição ganha a linha "Fonte original" com o link, para dar o
crédito. O botão "Trazer os PDFs para a biblioteca" faz o mesmo com todos os e-books que ainda abrem na web; é o passo que
os colegas rodam depois de importar o `seed.sql`, já que a pasta `storage/uploads` não vai para o Git. A instituição é
padronizada pelo link oficial ao salvar e ao importar (`FontesCursos::nomeOficial`).

Cada formato tem a sua página (`cursos.php` para cursos, `cursos.php?tipo=ebook` para e-books e `cursos.php?tipo=video`
para vídeos), o seu item no menu, a sua seção na página inicial e, na página do conteúdo, a lista de outros do mesmo
formato (`pt_secao_formato()` em `partials/componentes.php`).

### Padrões automáticos

As regras das máquinas de vaga e de curso continuam mandando. Quando a regra não tem pista (uma linha solta do anúncio
sem palavra-chave de benefício, requisito ou horário, uma área de vaga ou de curso que ela não reconhece, ou uma empresa
ou instituição que ela não acha no texto), o Sistema usa os padrões automáticos da classe `PadroesExtracao`. Eles são
contados na hora a partir dos cadastros que já passaram pela revisão de uma pessoa: o campo em que cada linha ficou nas
vagas salvas e a área de cada título de vaga e de curso.

Um padrão é uma palavra ou um par de palavras vizinhas, normalizado por caractere (sem acento, em minúsculas, sem
pontuação e com todo número trocado por `#`). Ele só vale se aparecer em pelo menos dois cadastros diferentes e se, em
pelo menos 90% deles, estiver no mesmo destino. Quando vários padrões aparecem na mesma linha, o par de palavras vale
mais que a palavra solta. Os nomes conhecidos são as empresas (anunciante das vagas e nome fantasia das empresas) e as
instituições (dos cursos) que já estão no banco, sem os nomes genéricos como "Loja" ou "Empresa Teste".

Os padrões não têm tabela nem tela: são montados uma vez a cada requisição, então toda vaga ou curso salvo já conta na
próxima extração. O resultado é sempre o mesmo para o mesmo texto e os mesmos cadastros, e cada decisão vai para
`$r['padroes']`, que aparece no relatório da extração da vaga com o título "Padrões automáticos usados". O currículo fica
de fora, porque tem dados pessoais. Se o banco falhar, a extração segue só com as regras. Explicamos tudo com exemplos em
[PADROES_AUTOMATICOS.md](PADROES_AUTOMATICOS.md).

Em todas as máquinas, nada é gravado sem revisão: a extração só preenche o formulário (ou a prévia da importação).

## 6. Painel e telas de cadastro

As tabelas do painel (usuários, categorias, cursos, e-books e vídeos, assinaturas e vagas) têm colunas que ordenam com
um clique (`painel_th()`), filtros e paginação (`painel_paginacao()`, que usa a mesma `cv_paginacao()` das listas
públicas; ela mesma escapa cada endereço, então quem chama passa o endereço puro). Candidaturas e banco de talentos têm a opção de
ordem no filtro. Essa lógica fica em `app/Core/helpers.php` (`lista_ordem()`, `ordenar_linhas()`, `paginar()` e
`painel_qs()`), e todas as ações, como salvar, publicar e excluir, voltam para a mesma aba, com os mesmos filtros, a mesma
ordem e a mesma página. Se alguém abre "Editar" ou "Ver" de um registro que não existe mais, o painel avisa e volta para a
lista (`registro_encontrado()`).

Nas listas, cada vaga, curso e e-book aparece com a sua foto (`painel_miniatura()`), uma chave de liga e desliga para a
situação (`painel_chave()`, que é um botão num formulário POST com CSRF) e uma barra de ações numa linha só
(`painel_botoes()`, com Ver, Editar, Encerrar ou Reabrir e Excluir).

| Tela | Criar | Ver | Editar | Ativar e desativar | Excluir | Filtros |
|---|---|---|---|---|---|---|
| Vagas | formulário e extração | página pública da vaga | `?edit=` | ativar, pausar e encerrar (reativar respeita o limite do plano) | sim | situação e busca |
| Cursos e e-books | formulário e extração | página pública do curso | `?edit=` | publicar e ocultar | sim | formato e busca |
| Usuários | formulário | ficha da conta (`?ver=`) | `?edit=` | ativar e bloquear (nunca a própria conta nem o último administrador) | sim | tipo e busca |
| Categorias | formulário | vagas e cursos da categoria | `?edit=` | ativar e desativar | sim | nenhum |
| Candidaturas | pelo candidato | portfólio e currículo | status e retorno | não se aplica | administrador | vaga e status |

Toda ação que muda dados é um formulário POST com token CSRF: a chave liga/desliga (`painel_chave()`) e a barra de
botões do CRUD (`painel_botoes()`). O controller confere a permissão no servidor e volta para a mesma lista, com os
filtros, a ordenação e a página (`painel_qs()`).

A tela de Assinaturas é só do administrador. Ela concede o plano da conta (Candidato VIP para candidato e Empresa Premium
para empresa) por um número de dias, edita o valor, as datas e a situação, cancela (mantendo o histórico) e exclui. Cada
conta tem no máximo uma assinatura ativa, e se a empresa perde o Premium o destaque das vagas sai. Os preços ficam em
`AssinaturaDAO::PRECOS`, os mesmos de `planos.php`.

Na página inicial, as vitrines de vagas, cursos e e-books mostram cinco cartões e trocam um de cada vez com os outros da
fila (`[data-rotativo]` em `app.js`). Cada seção tem um ritmo diferente (4,5 s, 5,2 s e 5,9 s) para não trocarem juntas,
e a troca para com o mouse em cima, com o botão "Pausar" ou para quem configurou o navegador para ter menos movimento.

O Sistema também faz uma manutenção diária: ao abrir a visão geral do painel do administrador, no máximo uma vez por dia,
a função `manutencao_diaria()` (em `app/Core/Upload.php`) apaga os arquivos de `storage/uploads` que nenhum registro usa
e que têm mais de 24 horas (`limpar_uploads_orfaos`).

## 7. Máquina de match

A nota vai de 0 a 100 e é explicada para o candidato e para a empresa. Dividimos os pontos assim:

| Critério | Pontos | Como calculamos |
|---|---|---|
| Competências | 50 | competências que a vaga pede comparadas com as do candidato (perfil e texto do currículo) |
| Cargo | 20 | título da vaga comparado com o título e o objetivo do candidato (peso cheio) ou com o histórico (60%) |
| Localização | 15 | mesma cidade ou vaga remota vale 15; mesma UF vale 9; disponível para mudança vale 6 |
| Nível | 15 | nível igual vale 15; acima do pedido vale 12 ou 8; abaixo vale 7 ou 0 |

A partir de 75 pontos a compatibilidade é excelente, a partir de 55 é alta, a partir de 35 é média e abaixo disso é
baixa. O detalhamento fica salvo em `matches.detalhes` (JSON) e aparece para o candidato, no "Por que essa nota?", e para a
empresa, com as competências atendidas e as que faltam em cada candidatura. Algumas observações explicam a nota sem
mudá-la, como a CNH pedida pela vaga, a vaga para PCD e as viagens. Os cursos que cobrem as competências que faltam são
recomendados no portfólio, na página da vaga e em Cursos. O Sistema recalcula o match quando o candidato envia ou exclui
um currículo, salva o perfil ou clica em "Recalcular match", e quando uma vaga é criada ou editada.

## 8. Regras dos planos

As regras são conferidas no servidor, em `AssinaturaDAO`. O candidato gratuito pode ter até três candidaturas ativas
(enviada, em análise ou entrevista). Com o plano VIP ele não tem limite, aparece primeiro para as empresas e vê todas as
vagas compatíveis, enquanto o gratuito vê as três melhores. Cancelar uma candidatura libera a vaga no limite, e a
candidatura cancelada pode ser enviada de novo.

A empresa básica pode ter até duas vagas abertas, e esse limite vale ao publicar, ao reativar uma vaga pausada ou
encerrada e ao renovar uma vaga vencida. A Empresa Premium não tem limite, tem as vagas em destaque e vê o banco de
talentos completo. Quando o Premium é cancelado ou vence, o destaque sai, mas as vagas abertas continuam abertas. Todas as
assinaturas são demonstrativas, sem cobrança real.

Os números dos limites ficam num lugar só: `AssinaturaDAO::LIMITE_CANDIDATURAS_GRATIS` (3) e
`AssinaturaDAO::LIMITE_VAGAS_GRATIS` (2). As telas de planos, a página inicial, o painel e as mensagens leem esses números
daí, e a mensagem de limite atingido também é uma constante só (`AVISO_LIMITE_CANDIDATURAS` e `AVISO_LIMITE_VAGAS`),
usada tanto na conferência quanto na transação. O que conta como candidatura ativa (`CandidaturaDAO::ATIVAS`) e como vaga
aberta (`VagaDAO::ATIVA`) também é escrito uma vez só, então o contador da tela e o limite da gravação nunca discordam.

## 9. Segurança

No banco de dados, todas as consultas usam PDO com consultas preparadas, e nenhum valor digitado pelo usuário é colado
direto no SQL. As senhas são guardadas com `password_hash` e conferidas com `password_verify` (bcrypt), com limite de 72
caracteres. As chaves estrangeiras usam exclusão em cascata: quando um usuário é excluído, saem junto o perfil, os
currículos, as vagas, as candidaturas, os matches, as assinaturas e os pedidos de troca de senha, e os arquivos enviados
também são apagados. Como os padrões automáticos são montados na hora, as vagas apagadas deixam de contar neles. O
cadastro do usuário e do perfil acontece numa transação, e o e-mail é único também por uma chave UNIQUE.

A sessão usa um cookie próprio (`CVDF_SESSAO`), restrito à pasta do projeto, com HttpOnly, SameSite=Lax (e Secure quando
há HTTPS) e modo estrito, que não aceita um identificador de sessão inventado. A cada login ou cadastro geramos um novo
identificador e um novo token CSRF. A cada requisição a conta é conferida no banco: se o administrador desativar ou excluir
o usuário, ou mudar o tipo dele, a sessão aberta perde o acesso na hora.

Para o login, guardamos as tentativas na tabela `tentativas_login`. Oito senhas erradas para o mesmo e-mail a partir do
mesmo IP (ou 20 somando todos os IPs, ou 60 de um mesmo IP) em cinco minutos pausam o login por cinco minutos, e a tela
avisa quando faltam três. A mensagem e o tempo de resposta são sempre os mesmos, para não revelar quais e-mails têm conta.
O login tolera os erros mais comuns de digitação (`UsuarioDAO::variantesSenha`): espaços nas pontas, a primeira letra
trocada entre maiúscula e minúscula e o Caps Lock ligado, com no máximo quatro conferências, feitas também quando o
e-mail não existe. No computador local a tela diz o motivo exato do erro; fora dele a mensagem continua única. O hash da
senha só é refeito se o algoritmo mudar, porque um hash novo faz as outras sessões abertas entenderem que a senha foi
trocada. O script `database/resetar_senhas.php`, que só roda pelo terminal, volta as contas de teste às senhas do README e
libera o login. Para sair, aceitamos um POST com token, um link com token (`logout_url()`) ou o clique no menu do próprio
site; um link vindo de outro site mostra uma confirmação.

Todos os formulários POST têm token CSRF, e um token inválido mostra uma página amigável com o código 403. Cada ação
confere a permissão: a empresa só altera as próprias vagas e as candidaturas delas, o candidato só mexe no que é dele e o
administrador não exclui nem rebaixa a própria conta (e sempre sobra pelo menos um administrador ativo). O cadastro
público só cria candidato ou empresa, nunca administrador, e exige o aceite do termo. Todo texto exibido passa pela
função `e()` (htmlspecialchars), e os links externos só aceitam http e https.

Só a pasta `public/` é servida pelo Apache. As pastas `app/`, `config/`, `database/`, `docs/`, `storage/` e `tests/` ficam
inacessíveis: o `.htaccess` da raiz manda tudo para `public/`, e cada uma delas tem um `.htaccess` com `Require all denied`
como segunda barreira. Os arquivos enviados ficam em `storage/uploads/`. As imagens (JPG, PNG ou WEBP de até 3 MB) são
conferidas pelo conteúdo, ganham nome aleatório e só são entregues pelo `ArquivoController::imagem`. Os currículos só saem
pelo `download.php`, para o dono, o administrador, a empresa que recebeu a candidatura ou a empresa Premium, quando o perfil
é público. As respostas levam os cabeçalhos `X-Content-Type-Options`, `X-Frame-Options` e `Referrer-Policy`. Quando uma
empresa é bloqueada, as vagas dela saem da área pública e deixam de receber candidaturas, e quando uma candidatura é
cancelada a empresa perde o acesso ao contato e ao currículo daquele candidato. Os links das fichas de cursos só são
abertos em servidores públicos. O cURL não segue redirecionamentos sozinho: `ImagemRemota::transferir` segue cada um à
mão, até 5, confere o endereço novo e liga direto no IP conferido (`CURLOPT_RESOLVE`). Assim, nem um redirecionamento para
a rede interna nem um nome que troca de IP entre a conferência e o download (DNS rebinding) fazem o servidor abrir um
endereço interno.

Pensando na LGPD, o candidato pode excluir a própria conta em `PerfilController::excluirConta`. A tela pede a senha (com a
mesma tolerância e a mesma pausa do login, em `UsuarioDAO::senhaConfere`) e uma caixa de confirmação. Depois o
`UsuarioDAO::excluir` apaga a conta, com o perfil, os currículos, as candidaturas, os matches, as assinaturas e os pedidos
de troca de senha, além dos arquivos enviados e das tentativas de login do e-mail. As outras sessões abertas da conta caem
sozinhas (`revalidar_sessao`). Empresas pedem a exclusão ao administrador. O registro do link de troca de senha só existe
no modo de demonstração e guarda o e-mail mascarado (`mascarar_email`).

A recuperação de senha é demonstrativa, porque não enviamos e-mail. O link vale por 30 minutos, pode ser usado uma vez e é
gravado em `storage/logs/redefinicoes_senha.log`; no modo de demonstração, acessando pelo próprio computador, ele também
aparece na tela. O banco guarda só o hash SHA-256 do token. A resposta é a mesma exista ou não o e-mail, cada conta pode
pedir no máximo três links por hora, e trocar a senha invalida os links que estavam pendentes.

As buscas com `LIKE` tratam os caracteres `%` e `_` digitados como texto comum (`like()`), e a visualização de uma vaga
conta uma vez por pessoa. No rodapé, quando a constante `DOACAO_PIX_CHAVE` está preenchida em `config/config.php`, aparece
o QR Code Pix de doação: o `Pix::doacao()` monta o código e o `assets/js/vendor/qrcode.js` (licença MIT) desenha o QR no
navegador, sem chamar nenhum serviço externo.

## 10. Testes e blindagem do código

O `tests/lint.php` confere a sintaxe de todos os arquivos PHP. O `tests/smoke.php` é o teste rápido: confere as classes,
as regras de negócio, as máquinas de extração (inclusive os padrões automáticos e a capa do PDF), o banco e as páginas.
O `tests/jornadas.php` usa o sistema como uma pessoa usaria, pelo HTTP: uma conta temporária faz cadastro, saída e login,
envia um currículo DOCX, se candidata, testa a extração de vagas e cursos, troca a senha e exclui a conta, e tudo o que
ela criou é apagado no fim, mesmo se algum passo falhar. O `tests/verificar.bat` roda os três com um clique duplo, e o
gancho `.githooks/pre-commit` (ligado com `git config core.hooksPath .githooks`) roda a mesma verificação antes de cada
commit e barra o commit se algo quebrar. O `.gitattributes` mantém o gancho com quebra de linha LF e o `.bat` com CRLF.

## 11. Arquivos enviados

Os arquivos enviados são gravados em `storage/uploads/` com nome aleatório, por exemplo `foto_3_a1b2c3.png` ou
`cv_3_20260923_ab12.pdf`. No banco guardamos o caminho lógico `assets/uploads/<nome>`, que é o mesmo endereço usado no
navegador, e a função `caminho_upload()` (em `app/Core/Upload.php`) converte esse caminho no caminho real do disco. A
função `apagar_upload_sem_uso()` só apaga um arquivo quando nenhuma vaga, curso, perfil ou currículo o usa mais.

## 12. Banco de dados

A estrutura está em `database/schema.sql` e os dados de demonstração em `database/seed.sql`. O banco se chama `tcc_final` e
tem 11 tabelas. Um usuário tem um perfil, e o perfil do candidato tem vários currículos. O perfil da empresa publica
várias vagas. Entre candidato e vaga ficam as candidaturas e os matches. O usuário também tem as assinaturas, as
tentativas de login e os pedidos de troca de senha, e as categorias agrupam vagas e cursos.

Os padrões automáticos das máquinas de extração não têm tabela: são montados na hora a partir de `vagas`, `cursos`,
`categorias` e `perfis`, como explicamos em [PADROES_AUTOMATICOS.md](PADROES_AUTOMATICOS.md).

As categorias de vaga do seed são TI, Administração, Marketing, Vendas, RH, Financeiro, Engenharia, Saúde, Educação,
Alimentação, Serviços Gerais e Limpeza, Logística e Transporte e Atendimento ao Público. As quatro últimas são as que a
extração de vagas mais sugere. Uma categoria que já está em uso não pode trocar entre vagas e cursos.

A conexão (`app/Core/Database.php`) é aberta uma vez por requisição, trata erros como exceção, usa consultas preparadas
de verdade, espera no máximo 5 segundos, trabalha em utf8mb4 e usa o mesmo fuso horário do PHP (America/Sao_Paulo).
Quando algo falha, o erro vira uma `DatabaseException` com uma mensagem em português, dizendo se o serviço está desligado,
se o banco não existe ou se a senha foi recusada.

## 13. Quando algo dá errado

Se o banco falhar, o sistema mostra a página "Banco de dados indisponível" (código 503) com a orientação certa. Nesse caso,
vale conferir se o Apache e o MySQL estão ligados no XAMPP Control Panel, se o banco `tcc_final` existe (importando o
`database/schema.sql` e o `database/seed.sql`), se `DB_HOST`, `DB_NAME`, `DB_USER` e `DB_PASS` estão certos em
`config/config.php` (ou nas variáveis de ambiente) e se a pasta `storage/uploads` permite gravação. Se só a página inicial
abre e as outras dão "Not Found" do Apache, o `mod_rewrite` está desligado: no `httpd.conf`, a linha
`LoadModule rewrite_module` não pode estar comentada e a pasta precisa de `AllowOverride All`. Com o modo de depuração
ligado, o detalhe técnico aparece abaixo da mensagem amigável. O teste automático roda com
`C:\xampp\php\php.exe tests\smoke.php`.

## 14. Fotos do carrossel

Todas as imagens da pasta `public/assets/img/brasilia/` entram no carrossel da página inicial, em ordem de nome. São fotos
de teste, com licenças livres do Wikimedia Commons, e os créditos ficam em `HomeController::index`.

| Arquivo | Foto | Licença |
|---|---|---|
| `brasilia1.jpg` | imagem original do projeto | (própria) |
| `brasilia2.jpg` | Catedral Metropolitana, de Agência Brasília | CC BY 2.0 |
| `brasilia3.jpg` | Eixo Monumental, de Cayambe | CC BY-SA 3.0 |
| `brasilia4.jpg` | Ponte JK, de Marinelson Almeida | CC BY 2.0 |
| `brasilia5.jpg` | Esplanada à noite, de Dasfour2022 | CC BY-SA 4.0 |
