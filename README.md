# TCC Final — Conecta Vagas DF

Plataforma de vagas de emprego e capacitação profissional do Distrito Federal —
Trabalho de Conclusão de Curso (TCC). No site, a marca continua **Conecta Vagas DF**.

**PHP 8.0 ou mais novo, puro (padrão MVC)** · **MySQL/MariaDB (PDO)** · **XAMPP** · sem frameworks e sem dependências externas.
Testado no PHP 8.0.30 do XAMPP; o código não usa recursos do 8.1 em diante. Para publicar na internet, use PHP 8.2
ou mais novo (o 8.0 não recebe mais correções de segurança).

**De onde veio**: este projeto herdou o **conecta-vagas-df-tcc** (pasta `C:\xampp\htdocs\conecta vagas df tcc`, banco
`conecta_vagas_df_v2`), congelado na tag **`v1.0-blindada`**. O TCC Final segue a partir dele com pasta, banco
(`tcc_final`) e endereço próprios; a versão antiga não é alterada.

## O que o sistema faz

| Perfil | Recursos |
|---|---|
| Visitante | Vagas e cursos/e-books com busca e filtros; página de cada vaga e curso; planos. |
| Candidato | Envia o currículo (PDF/DOCX/DOC) e a **máquina de extração** preenche o perfil; com o cadastro completo, ganha um **portfólio** automático e a **máquina de match** (nota de 0 a 100, explicada, com cada vaga); candidata-se e acompanha o retorno das empresas. |
| Empresa | Publica vagas (envia o **cartaz** ou cola o anúncio e a **extração de vagas** preenche o formulário — o leitor de cartaz roda no navegador, sem instalar nada), recebe candidaturas ordenadas pelo match e consulta o banco de talentos. |
| Administrador | Gerencia usuários, categorias, cursos/e-books (com **extração de cursos**), vagas, candidaturas e assinaturas, e ajusta as máquinas de extração pelo **Calibrador**. |

Planos demonstrativos (sem cobrança real): **Candidato VIP** e **Empresa Premium**.

## Novidades do TCC Final (01/10/2026)

- **Calibrador das máquinas de extração** (Painel → **Calibrador**): o administrador ajusta as regras da extração
  sem mexer no código — ver a seção [Calibrador](#calibrador-ajustar-a-extração-sem-programar) e o
  **[docs/CALIBRADOR.md](docs/CALIBRADOR.md)**.
- **Testar as máquinas**: cola-se um anúncio, uma ficha de curso ou um currículo e vê-se o que a extração faz, com o
  que o calibrador ajustou. Nada é salvo.
- **Relatório da extração da vaga** com o bloco **Ajustes do calibrador** (o que a regra dizia, para onde foi e qual
  termo decidiu).
- **Banco próprio `tcc_final`**, com **12 tabelas** (entra `calibracao_extracao`) e 12 termos de exemplo no `seed.sql`.
- **Acessibilidade**: rótulos ligados aos campos nos perfis do candidato e da empresa.
- **Documentação**: diagramas UML e modelos de dados refeitos, e a monografia em
  [docs/tcc/TCC_Final_Conecta_Vagas_DF.pdf](docs/tcc/TCC_Final_Conecta_Vagas_DF.pdf).

### Herdado da versão de 27/09/2026

- **Leitor de cartaz sem instalação**: o OCR roda no navegador (Tesseract.js servido pelo próprio site). Ninguém
  instala nada, nem no servidor nem no computador; o Tesseract do servidor virou reserva opcional.
- **Carregador**: amarelo enquanto carrega ou lê (com %), azul quando está pronto, em toda máquina de extração;
  também impede o clique duplo. O "Extrair" com a caixa vazia não gera relatório em branco.
- **Cartaz mais bem lido**: shopping não vira empresa, marca em linhas separadas é confirmada pelo e-mail do
  cartaz ("Smile & Face"), palavra grudada pelo OCR é separada, "R$ 700 VT/VR" não conta como salário e até
  3 cargos em letra grande entram no título.
- **Biblioteca de e-books**: da pesquisa direto para o botão **Baixar**. A ficha tem o campo `PDF:`; ao salvar,
  o sistema baixa o PDF (ou acha o PDF na página do e-book) e credita a fonte original. O botão
  **Trazer os PDFs para a biblioteca** traz os antigos de uma vez.
- **LGPD**: o candidato exclui a própria conta e todos os dados dele (Meu perfil → Seus dados); o log de troca de
  senha só existe no modo de demonstração e com o e-mail mascarado.
- **Testes de jornada** (`tests/jornadas.php`): uma conta temporária usa o sistema pelo navegador — cadastro,
  login, currículo, candidatura, extração, troca de senha e exclusão da conta — e é apagada no fim.

Varredura final da versão herdada (27/09/2026): 1.701 páginas rastreadas nos 4 perfis sem nenhum problema; bateria de
segurança (XSS, SQL injection, CSRF, sessão, uploads, permissões) aprovada; nenhum erro de PHP no servidor; páginas sem
transbordar no celular. Ressalvas conhecidas, para depois da banca: ajustes de acessibilidade (pulos de título) e ícone
da aba (favicon).

## Dia da apresentação (roteiro rápido)

1. Notebook **na tomada** (na bateria o processador desacelera e o leitor de cartaz leva o dobro do tempo).
2. XAMPP: **Start** no Apache e no MySQL.
3. Clique duplo em `tests\verificar.bat` e espere **TUDO CERTO**.
4. Abra uma vez **Painel → Vagas** (o leitor de cartaz fica carregado e fica azul).
5. Para mostrar o Calibrador: siga o roteiro de 5 minutos em [docs/CALIBRADOR.md](docs/CALIBRADOR.md#10-roteiro-para-a-apresentação-5-minutos)
   (cadastrar termo → Testar as máquinas → ver no relatório da extração).
6. Ao terminar: **Stop** no MySQL antes de fechar o XAMPP ou desligar o computador.

## Instalação (XAMPP no Windows)

1. Copie a pasta do projeto para `C:\xampp\htdocs\` — nesta máquina: **`C:\xampp\htdocs\tcc-final`**
   (qualquer nome funciona, inclusive com espaços).
2. No **XAMPP Control Panel**, inicie **Apache** e **MySQL**.
   O `mod_rewrite` do Apache (já ativo no XAMPP) é necessário.
3. Importe o banco — primeiro a estrutura, depois os dados de demonstração:
   - phpMyAdmin → Importar → `database/schema.sql` e depois `database/seed.sql`; **ou**
   - no **Prompt de Comando (cmd)**, dentro da pasta do projeto (no PowerShell o `<` não funciona):
     ```
     C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < database\schema.sql
     C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < database\seed.sql
     ```
   O `schema.sql` apaga e recria **só** o banco `tcc_final` (o `conecta_vagas_df_v2` da versão herdada não é tocado).
4. Se o MySQL tiver senha, ajuste `DB_PASS` em `config/config.php`.
5. Acesse `http://localhost/<pasta do projeto>/` — nesta máquina: **`http://localhost/tcc-final/`**
   (se a pasta tiver espaços, eles viram `%20` no endereço).
6. Confira se está tudo certo: `C:\xampp\php\php.exe tests\smoke.php`
7. **Biblioteca de e-books** (precisa de internet, uma vez só): entre como administrador → **Cursos e e-books** →
   **Trazer os PDFs para a biblioteca**. Os PDFs dos e-books são baixados para `storage/uploads/` (arquivos
   enviados não vão para o Git) e o botão deles passa de "Acessar" para **"Baixar"**. Sem esse passo, tudo
   funciona igual, só que os e-books abrem no site de origem.

**Nada mais para instalar.** O leitor de cartaz das vagas (OCR) vem com o site e roda no navegador; os PDFs e
os currículos são lidos em PHP puro. Tesseract e Poppler no servidor são opcionais (só reserva).

### Contas de teste

| Perfil | E-mail | Senha |
|---|---|---|
| Administrador | admin@conectavagas.com | Admin@123 |
| Empresa | empresa@conectavagas.com | Empresa@123 |
| Candidato | candidato@conectavagas.com | Candidato@123 |

Troque as senhas antes de publicar o sistema.

### Não consegue entrar?

- **Confira o que foi digitado** com o botão do olho, ao lado do campo de senha. O login tolera os erros mais
  comuns: primeira letra trocada (`admin@123` entra como `Admin@123`), Caps Lock ligado (`aDMIN@123`) e
  espaços no começo ou no fim.
- **No ambiente local** (acesso pelo próprio computador) a mensagem diz o motivo exato: e-mail sem conta,
  senha incorreta ou conta desativada. Acessando de outra máquina ou com `APP_DEBUG=0`, a mensagem é única
  (não revela quais e-mails têm conta).
- **Login em pausa**: depois de **8 senhas erradas** para o mesmo e-mail (a tela avisa quando faltam 3), o
  login daquele e-mail pausa por **5 minutos** e libera sozinho. Os limites ficam em `config/config.php`
  (`LOGIN_MAX_TENTATIVAS`, `LOGIN_JANELA_MINUTOS`).
- **"Sua sessão foi encerrada porque a senha da conta foi alterada"**: a senha daquela conta mudou (pelo
  "Esqueci minha senha", pelo administrador ou pelo comando abaixo). Basta entrar de novo com a senha nova.
- **Reconectar as senhas de teste** (volta as 3 contas às senhas da tabela acima, reativa as contas e tira
  qualquer pausa do login):
  ```
  C:\xampp\php\php.exe database\resetar_senhas.php
  ```
- **"Esqueci minha senha"**: no modo de demonstração (acesso pelo próprio computador, sem e-mail configurado), o
  link de redefinição aparece na tela e fica em `storage/logs/redefinicoes_senha.log`, com o e-mail mascarado
  (`ca*******@conectavagas.com`). Com `APP_DEBUG=0` (produção), o link não vai para o log: seria enviado por e-mail.

## Estrutura de pastas

```
tcc-final/
├── .htaccess              manda todas as requisições para public/ (o endereço no navegador não muda)
├── index.php              reserva: sem mod_rewrite, redireciona para public/
├── README.md              este arquivo
├── app/                   CÓDIGO DA APLICAÇÃO (inacessível pelo navegador)
│   ├── Controllers/       recebem a requisição, conferem permissões e escolhem a tela (C do MVC)
│   ├── Core/              núcleo: inicialização, rotas, sessão, login, CSRF, uploads, banco, telas
│   ├── DTO/               objetos que levam os dados do formulário até o banco
│   ├── Models/            acesso ao banco, uma classe por tabela (M do MVC)
│   ├── Services/          regras de negócio: match, competências, portfólio e extração
│   │   └── Extracao/      leitura de PDF/DOCX/DOC, extração de currículo, vaga e curso e o Calibrador
│   └── Views/             telas em HTML + PHP (V do MVC): layouts, partes reutilizáveis e páginas
├── config/config.php      configurações (banco tcc_final, depuração, limites, pastas)
├── database/
│   ├── schema.sql         estrutura do banco (12 tabelas, chaves, índices)
│   ├── seed.sql           dados de demonstração (contas, vagas, cursos, termos do Calibrador)
│   └── resetar_senhas.php volta as senhas das contas de teste e libera o login (só pelo terminal)
├── docs/ARQUITETURA.md    como o sistema funciona por dentro (leia para a apresentação)
├── docs/CALIBRADOR.md     o Calibrador das máquinas de extração: contextos, precedência e roteiro de demonstração
├── docs/PESQUISA_CURSOS.md  pesquisa guiada: de onde vêm os links dos novos cursos e e-books
├── docs/PROMPTS_PESQUISA.md prompt padrão para as IAs de pesquisa (gerado por docs/gerar_prompts.php)
├── docs/tcc/              monografia em PDF (fonte HTML em monografia/) e diagramas (PlantUML .puml + .svg + .png)
├── .githooks/pre-commit   blindagem: antes de cada commit confere a sintaxe e roda o teste rápido
├── public/                ÚNICA pasta servida pelo Apache
│   ├── index.php          front controller: porta de entrada de todas as páginas + tabela de rotas
│   └── assets/            CSS, JavaScript e imagens (carrossel, cartazes das vagas, capas dos cursos)
│       └── js/vendor/tesseract/  leitor de cartaz da plataforma (Tesseract.js + português), servido pelo próprio site
├── storage/               arquivos gerados pelo sistema (inacessível pelo navegador)
│   ├── uploads/           currículos, fotos, logos e cartazes enviados
│   ├── logs/              registros internos (ex.: links de redefinição de senha, só no modo de demonstração)
│   └── backups/           cópias do banco e dos uploads (fora do git), com COMO_RESTAURAR.txt
└── tests/
    ├── smoke.php          teste rápido: classes, regras, calibrador, banco e páginas
    ├── jornadas.php       o sistema usado como uma pessoa usa: cadastro, login, currículo, candidatura,
    │                      extração de vagas e cursos, troca de senha e exclusão da conta (conta temporária)
    ├── amostras/          leituras reais de cartazes feitas pelo leitor do navegador (usadas nos testes)
    ├── lint.php           confere a sintaxe de todos os arquivos PHP
    └── verificar.bat      clique duplo: sintaxe + teste rápido + jornadas, com o resultado na tela
```

## Como uma página é montada

Exemplo: o navegador pede `/vagas.php`.

1. O `.htaccess` da raiz desvia o pedido para `public/index.php` (o **front controller**).
2. `app/Core/bootstrap.php` carrega as configurações, o núcleo e o carregamento automático das classes.
3. A sessão é aberta e a conta logada é conferida no banco.
4. O **roteador** (`app/Core/Router.php`) procura `vagas.php` na tabela de rotas e chama `VagaController::lista()`.
5. O controller lê os filtros, busca as vagas no **model** `VagaDAO` e o match no `MatchDAO`.
6. A **view** `app/Views/vagas/lista.php` é exibida entre o cabeçalho e o rodapé do layout.

Os endereços são os mesmos das versões anteriores (`vaga.php?id=3`, `view/perfil/index.php`,
`admin/pages/vagas.php`...): links e favoritos antigos continuam funcionando.

Detalhes — camadas, tabela completa de rotas, máquinas de extração e de match, calibrador, regras dos planos,
segurança e o mapa "onde estava → onde está": **[docs/ARQUITETURA.md](docs/ARQUITETURA.md)**.

## Calibrador: ajustar a extração sem programar

As máquinas de extração (cartaz/anúncio de vaga, ficha de curso e currículo) funcionam por **regras** escritas no
código. O **Calibrador** (Painel → **Calibrador**, só administrador) ajusta essas regras sem mexer no código:

- **Termos calibrados** (o administrador cadastra): "quando o texto tiver o termo X, mande para Y", em 4 contextos —
  linha do anúncio → campo da vaga (Descrição, Requisitos ou Benefícios), área da vaga, área do curso e linha solta do
  currículo → seção. Ex.: `Uniforme` → Benefícios; `Churrasqueiro` → Alimentação; `Ensino médio` → Formação.
- **Regras de decisão**: o termo vale mais que a regra do código; entre dois termos que casam, **vence o mais longo**
  (o mais específico); casa por **palavra inteira**, sem acento e sem maiúscula; linha que já está debaixo de um título
  de seção não muda de lugar; termo desativado fica guardado sem uso.
- **Nomes conhecidos** (automático): empresas (anunciante das vagas e nome fantasia das empresas) e instituições (dos
  cursos) já cadastradas são reconhecidas sozinhas quando nenhuma regra acha o nome. Nada para treinar.
- **Testar as máquinas**: cola um texto e vê a extração e os ajustes — nada é salvo. No uso real, o relatório da
  extração da vaga mostra o bloco **Ajustes do calibrador**.

É tudo determinístico (regras + dicionário): o mesmo texto com os mesmos termos dá sempre o mesmo resultado, e cada
ajuste mostra o termo que decidiu. Tabela `calibracao_extracao`; detalhes e roteiro de demonstração em
**[docs/CALIBRADOR.md](docs/CALIBRADOR.md)**.

## Cadastrar cursos e e-books (com ajuda de outra IA)

Em **Painel → Cursos e e-books** há uma caixa só, **Extrair**:

1. peça a uma IA de pesquisa (Perplexity, ChatGPT, Gemini…) que pesquise os links ou títulos e responda no
   **modelo de ficha** (Título, Tipo, Instituição, Modalidade, Cidade, Nível, Carga horária, Gratuito, Preço, Área,
   Link, Imagem, Descrição). O prompt padrão está em **[docs/PROMPTS_PESQUISA.md](docs/PROMPTS_PESQUISA.md)**;
2. cole a resposta em **Extrair**: **uma ficha** (ou um texto de divulgação) preenche o formulário para revisar e
   salvar; **várias fichas** (separadas por `---`) abrem uma prévia para cadastrar de uma vez;
3. **com imagem** na ficha, ela é conferida e baixada; **sem imagem**, o conteúdo entra com a **imagem padrão** da
   plataforma e a lista mostra *trocar imagem* (filtro "Com imagem padrão") — edite quando tiver a imagem certa;
4. **Baixar × Acessar**: envie o PDF do e-book no cadastro e ele fica na **biblioteca** da plataforma (botão
   **Baixar**, baixa direto); conteúdo que fica em outro site mostra **Acessar** (abre em nova aba).

A área do curso e a instituição sugeridas também passam pelo Calibrador (termos de `curso_categoria` e instituições
já cadastradas). Fontes oficiais para pesquisar: [docs/PESQUISA_CURSOS.md](docs/PESQUISA_CURSOS.md).

## Manutenção automática

Ao abrir a **visão geral** do painel, no máximo uma vez por dia, o sistema **limpa arquivos órfãos** de
`storage/uploads` (sem registro que os use e com mais de 24 h).

**Foto do currículo**: ao enviar o currículo (PDF ou DOCX), a foto é encontrada pelo padrão de foto de currículo
(tons de pele, fotografia, proporção de retrato — ignora logotipos, ícones e página escaneada) e vira a foto do
perfil; se o perfil já tiver foto, a do currículo aparece no relatório para o candidato escolher trocar.

## Blindagem do código (antes e depois de mexer)

- **Clique duplo em `tests\verificar.bat`**: confere a sintaxe de todos os PHP, roda o teste rápido (inclusive os
  testes do Calibrador) e as jornadas (`tests/jornadas.php`: uma conta temporária faz cadastro, login, currículo,
  candidatura e exclusão da conta pelo navegador, e é apagada no fim — nada fica no banco). No fim aparece
  **TUDO CERTO** ou o que quebrou (com arquivo e linha). Faça isso depois de cada alteração.
- **Commit protegido**: o gancho `.githooks/pre-commit` roda a mesma verificação antes de cada commit (pelo
  terminal ou pelo VS Code). Se algo quebrou, o commit é **barrado** e o motivo aparece; os relatórios completos
  ficam em `.git/smoke_ultimo.txt` e `.git/jornadas_ultimo.txt`. Precisa do Apache e do MySQL ligados. Emergência: `git commit --no-verify`.
- O gancho já está ligado nesta máquina. Em uma cópia nova do projeto, ligue com:
  ```
  git config core.hooksPath .githooks
  ```
- Quebrou e não sabe onde? Todo commit passou pela verificação: veja `git log --oneline` e volte ao último que estava
  bom com `git checkout <commit>` (o banco volta pelo backup, ver "Backup e restauração"). A tag `v1.0-blindada` é a
  versão herdada, de antes do TCC Final.

## Segurança (resumo)

- Senhas com `password_hash` (bcrypt); pausa automática do login contra força-bruta (ver acima).
- Token CSRF em todo formulário; SQL sempre com parâmetros (`?`); todo texto na tela passa por `e()`.
- Cada ação confere a permissão: candidato só mexe no que é dele, empresa só nas próprias vagas e
  candidaturas, e o currículo só abre para o dono, para a empresa que o recebeu ou para empresa Premium.
  O Calibrador é só do administrador.
- Só `public/` é servida e, dentro dela, só o `index.php` executa PHP. Configuração, banco, backups,
  documentos, `.git` e arquivos ocultos respondem 403.
- Cabeçalhos: `Content-Security-Policy` (formulários só para o próprio site), `X-Frame-Options`,
  `X-Content-Type-Options`, `Referrer-Policy` e `Permissions-Policy`; a versão do PHP não é anunciada.
- **LGPD**: consentimento no cadastro; o candidato exclui a própria conta em **Meu perfil → Seus dados → Excluir
  minha conta** (confirma com a senha) — saem o perfil, os currículos e arquivos, a foto, as candidaturas, o match
  e as tentativas de login. Os logs não guardam e-mail inteiro. Os termos do Calibrador não têm dado pessoal: se a
  conta do administrador que os cadastrou sair, eles ficam sem autor.
- Detalhes em [docs/ARQUITETURA.md](docs/ARQUITETURA.md) (seção de segurança).

## Backup e restauração

- Ponto de partida do código: tag `v1.0-blindada` (versão herdada); depois dela, cada commit do TCC Final passou pela
  verificação.
- Banco e arquivos enviados: `storage/backups/<data>/` tem o `.sql` do banco, o `uploads.zip` e o passo a
  passo (`COMO_RESTAURAR.txt`). Para voltar o banco, dentro da pasta do backup:
  ```
  C:\xampp\mysql\bin\mysql.exe -u root < banco_tcc_final.sql
  ```
- Fazer um backup novo:
  ```
  C:\xampp\mysql\bin\mysqldump.exe -u root --single-transaction --databases tcc_final > storage\backups\banco_tcc_final.sql
  ```

## Documentação do TCC

- Monografia: [docs/tcc/TCC_Final_Conecta_Vagas_DF.pdf](docs/tcc/TCC_Final_Conecta_Vagas_DF.pdf). O fonte fica em
  `docs/tcc/monografia/TCC_Final_Conecta_Vagas_DF.html` (+ `estilo.css`): para gerar o PDF de novo, abra no Edge →
  Ctrl+P → Salvar como PDF, sem "Cabeçalhos e rodapés" e com "Gráficos de plano de fundo".
- Diagramas (casos de uso, classes, sequência, arquitetura, modelo lógico) em `docs/tcc/diagramas/`: o fonte é o
  `.puml` (PlantUML, com o estilo comum em `estilo.iuml`); o `.svg` e o `.png` saem dele com
  `java -jar plantuml.jar -tsvg arquivo.puml` e `-tpng`. O modelo conceitual (desenhado a lápis) só tem `.svg`/`.png`.

## Depuração e produção

- Sem a variável de ambiente `APP_DEBUG`, o `DEBUG` fica ligado só no terminal e para quem acessa pelo
  próprio computador (`127.0.0.1`/`::1`): erros mostram o detalhe técnico. Acessos de outras máquinas
  veem só mensagens amigáveis; o detalhe vai para o log do Apache/PHP.
- `APP_DEBUG=0` (ou `1`) sempre prevalece — use `APP_DEBUG=0` ao publicar, principalmente atrás de um proxy
  no mesmo servidor (todo acesso chegaria como `127.0.0.1`).
