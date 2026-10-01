<?php
declare(strict_types=1);

/*
 * Gera docs/PROMPTS_PESQUISA.md com o PROMPT PADRÃO (e o avulso), usando as áreas de curso cadastradas.
 * Uso (depois de criar ou renomear áreas de curso):  C:\xampp\php\php.exe docs\gerar_prompts.php
 * O prompt não aparece no painel: fica neste documento. O código dele está em
 * app/Services/Extracao/PromptsPesquisa.php.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Somente pelo terminal.'); }
require __DIR__.'/../app/Core/bootstrap.php';

try {
    $areas = array_column(array_filter((new CategoriaDAO())->listar('curso'), fn($c) => (int)$c['ativo']), 'nome');
} catch (Throwable) {
    $areas = ExtracaoCurso::AREAS;   // banco desligado: usa as áreas do seed
}

$modelo = "Título:\nTipo:\nInstituição:\nModalidade:\nCidade:\nNível:\nCarga horária:\nGratuito:\nPreço:\nÁrea:\nLink: https://...\nPDF: https://... (e-book gratuito: link direto do arquivo)\nImagem: https://...\nDescrição:";
$md = "# Prompt padrão — pesquisa de cursos e e-books para o cadastro\n\n";
$md .= "Peça a uma IA de pesquisa (Perplexity, ChatGPT, Gemini, Copilot, Claude) que pesquise **cada link ou título** e devolva uma **ficha** com os mesmos campos do cadastro. Depois é só colar a resposta na caixa **Extrair** do painel (**Painel → Cursos e e-books**):\n\n";
$md .= "- **uma ficha** → preenche o formulário: revise e clique em **Salvar conteúdo**;\n- **várias fichas** (separadas por `---`) → prévia: confira e clique em **Cadastrar marcados**;\n";
$md .= "- **a plataforma abre cada link da ficha** (Imagem, PDF e Link) e descobre o que ele é: **imagem** → usa; **PDF** → tira a **capa da 1ª página** e guarda o PDF na biblioteca; **página** → procura nela o PDF e a imagem de divulgação (logotipo não vale); link quebrado ou genérico → **aviso** na prévia;\n";
$md .= "- **imagem**: o link direto da ficha é conferido na prévia e baixado ao salvar; **sem imagem** — ou se o link não baixar —, o conteúdo entra com a **imagem padrão** e aparece na lista como *trocar imagem*. A imagem **nunca impede o cadastro** (passo a passo em *Depois da pesquisa*, abaixo);\n";
$md .= "- **PDF na nossa biblioteca**: com o campo **PDF:** na ficha (ou o link do e-book apontando para o PDF), o cadastro baixa o PDF para a biblioteca da plataforma e o botão vira **Baixar**; também dá para enviar o arquivo à mão. Conteúdo que fica na web mostra **Acessar**. Os e-books antigos que ainda abrem no site de origem vêm todos de uma vez pelo botão **Trazer os PDFs para a biblioteca** (painel → Cursos e e-books).\n\n";
$md .= "> Gerado em ".date('d/m/Y')." com as áreas cadastradas. Criou ou renomeou áreas? Rode `C:\\xampp\\php\\php.exe docs\\gerar_prompts.php`.\n\n";
$md .= "## Modelo da ficha\n\n```text\n{$modelo}\n```\n\n";
$md .= "## Depois da pesquisa: subir a imagem e finalizar o cadastro\n\n"
     ."1. **Extrair** — copie as fichas da IA, abra **Painel → Cursos e e-books**, cole na caixa **Extrair** e clique em **Extrair**.\n"
     ."2. **Conferir a imagem e os avisos na prévia** — uma ficha preenche o formulário com a miniatura da imagem; várias fichas abrem a prévia em lote, com a miniatura de cada uma. A plataforma já abriu cada link: e-book sem imagem ganha a capa da 1ª página do PDF (*Capa tirada da 1ª página do PDF*); link que não abre como imagem é trocado pela imagem de divulgação da página (*Imagem achada na página do conteúdo*); link de PDF que abre uma página, ou link fora do ar, aparece como aviso — corrija o endereço antes de salvar.\n"
     ."3. **Salvar** — **Salvar conteúdo** (uma) ou **Cadastrar marcados** (várias). A imagem do link é baixada, reduzida para até 800 px de largura e guardada na plataforma (não depende mais do site de origem). Se o link não baixar, o conteúdo é salvo do mesmo jeito, com a imagem da instituição ou a imagem padrão e um aviso.\n"
     ."4. **Finalizar (trocar a imagem padrão)** — na lista, filtre **Situação → Com imagem padrão** (ou clique em *trocar imagem* na linha do conteúdo), abra **Editar** e use **uma** destas formas, na ordem de preferência:\n"
     ."   - **enviar o arquivo** (JPG, PNG ou WEBP até 3 MB) — a mais garantida: salve a imagem da página oficial no computador e envie;\n"
     ."   - **colar o link direto** da imagem (o endereço que termina em .jpg, .png ou .webp);\n"
     ."   - **escolher um caminho** de imagem que já está no site (lista *Imagem (caminho)*, ex.: `assets/img/ebooks/...`).\n"
     ."   Clique em **Salvar conteúdo**. O número em *Com imagem padrão* diminui; repita até zerar.\n"
     ."5. **Conferir no site** — abra *Cursos* ou *E-books* e veja o card com a imagem nova.\n\n"
     ."> Na ficha, o campo **Imagem:** também aceita um caminho do próprio site (`assets/img/...`) — útil quando a imagem já foi salva no projeto.\n\n";
$md .= "## Onde colar o prompt em cada IA\n\n| IA | Como usar |\n|---|---|\n";
foreach (PromptsPesquisa::IAS as $c) $md .= '| '.$c['nome'].' | '.$c['onde']." |\n";
$md .= "\nDepois de configurado, cada mensagem é só a lista de **links e/ou títulos**, um por linha.\n\n";
$md .= "## Prompt padrão (configure uma vez)\n\n```text\n".PromptsPesquisa::mestre('chatgpt', $areas)."\n```\n\n";
$md .= "No **Microsoft Copilot** (que não guarda instruções), cole o prompt como primeira mensagem e acrescente no fim: *Agora responda apenas: Pronto.*\n\n";
$md .= "## Prompt avulso (uma conversa só, sem configurar nada)\n\nTroque os itens pelos seus links ou títulos:\n\n```text\n"
     .PromptsPesquisa::avulso(['https://cartilha.cert.br/', 'Como Elaborar um Currículo (SEBRAE)'], $areas)."\n```\n";
file_put_contents(__DIR__.'/PROMPTS_PESQUISA.md', str_replace("\r\n", "\n", $md));
echo 'docs/PROMPTS_PESQUISA.md atualizado ('.count($areas).' áreas).'.PHP_EOL;
