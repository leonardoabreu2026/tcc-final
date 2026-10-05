# Fontes oficiais de cursos e e-books

Este documento mostra de onde vêm os links dos cursos, e-books e vídeos que cadastramos na plataforma. Cada conteúdo novo
é pesquisado no site oficial da instituição e entra como uma ficha, que o administrador cola na caixa Extrair do painel,
em Cursos e e-books.

## Do catálogo de fontes ao cadastro

O catálogo de fontes oficiais fica em `app/Services/Extracao/FontesCursos.php`, na constante `FONTES`. Para cada fonte
guardamos onde procurar, o domínio usado na busca com `site:`, os formatos que ela oferece e uma dica. O método
`FontesCursos::cobertura()` conta o que já existe por área, por formato e por fonte e aponta as áreas com menos conteúdo,
por onde vale começar a pesquisa.

Quando a ficha é colada, o `ExtracaoCurso::fichas()` limpa a formatação que vem junto do texto copiado (negrito, títulos,
links em markdown e marcas de citação como `[1]`), separa as fichas e extrai cada campo. O `FontesCursos::nomeOficial()` padroniza o
nome da instituição pelo domínio do link, tanto ao importar quanto ao salvar. Por fim, o `ImagemRemota` abre cada link da
ficha para descobrir se é imagem, PDF ou página: a imagem é conferida e baixada, o PDF de um e-book vira a capa (tirada da
primeira página) e vai para a biblioteca, e a página pode oferecer o PDF e a imagem de divulgação. Quando não há imagem, o
conteúdo entra com a imagem padrão da plataforma.

## Fontes oficiais do catálogo

| Fonte | Formatos | Onde é forte | Ponto de partida |
|---|---|---|---|
| Fundação Bradesco, Escola Virtual | curso | Informática e Excel, Administração, Finanças | https://www.ev.org.br/cursos |
| Escola Virtual.Gov (Enap) | curso | Tecnologia e IA, Gestão, Dados, Carreira | https://www.escolavirtual.gov.br/catalogo |
| SEBRAE | curso, e-book | Empreendedorismo, Marketing, Finanças | https://sebrae.com.br/sites/PortalSebrae/cursosonline |
| Google Grow | curso, vídeo | Marketing digital, Dados, IA | https://grow.google/intl/pt-br/ |
| Microsoft Learn | curso | Tecnologia e IA, Excel, Dados | https://learn.microsoft.com/pt-br/training/browse/ |
| FGV Online | curso | Negócios, Finanças e ESG, Gestão | https://educacao-executiva.fgv.br/cursos/gratuitos |
| IFB, Instituto Federal de Brasília | curso | cursos presenciais gratuitos no DF (FIC) | https://www.ifb.edu.br |
| SENAI | curso | Tecnologia, Indústria | https://www.portaldaindustria.com.br/senai/ |
| SENAC | curso | Administração, Atendimento, Comércio | https://www.ead.senac.br/ |
| Banco Central do Brasil | e-book, curso | Finanças | https://www.bcb.gov.br/cidadaniafinanceira |
| CERT.br e NIC.br | e-book | Segurança na internet | https://cartilha.cert.br/ |
| Febraban, Meu Bolso em Dia | e-book, curso | Finanças pessoais | https://meubolsoemdia.com.br/ |
| Ministério do Trabalho e Emprego | e-book | Direitos trabalhistas | https://www.gov.br/trabalho-e-emprego/pt-br |
| eduCAPES (repositório) | e-book, vídeo | Todas | https://educapes.capes.gov.br/ |
| CVM, Portal do Investidor | e-book, curso | Finanças | https://www.gov.br/investidor/pt-br |
| Cisco Networking Academy | curso | Redes e cibersegurança | https://www.netacad.com/pt |
| Fundação Estudar | curso, e-book | Carreira | https://www.estudar.org.br/ |

Para adicionar uma fonte, basta incluir uma entrada em `FontesCursos::FONTES` com `nome`, `dominios` (o primeiro é usado
no `site:`), `catalogo`, `formatos`, `areas` e `dica`. Em repositórios que publicam material de outros autores, como o
eduCAPES, marcamos `'repositorio' => true`, e assim o nome do autor que veio na ficha é mantido.

## O que vale numa ficha

Só entram links do site oficial, da página do próprio curso ou e-book (para e-book, o PDF oficial também vale), e cada
link é aberto e conferido antes de entrar na ficha. Nada pode estar encerrado ou com inscrições fechadas, a imagem tem de
ser a oficial (a capa do e-book ou a imagem de divulgação do curso, nunca um logotipo genérico) e nenhum link já
cadastrado se repete. As fichas usam os rótulos exatos e são separadas por `---`. Mesmo assim, a plataforma abre cada
link de novo na prévia e avisa quando algum não abre como deveria.

## Como os nomes são organizados

O nome da instituição é padronizado automaticamente pelo link oficial, ao importar e ao salvar. A parceria fica entre
parênteses, como em "(conteúdo Microsoft)", e, nos repositórios como o eduCAPES, o autor informado na ficha é mantido.
