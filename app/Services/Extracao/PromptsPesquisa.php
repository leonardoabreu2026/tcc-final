<?php
declare(strict_types=1);

/**
 * PROMPT PADRÃO para as IAs de pesquisa (Perplexity, ChatGPT, Gemini, Copilot, Claude) — cadastro de cursos,
 * e-books e vídeos. Não aparece no painel: fica em docs/PROMPTS_PESQUISA.md (gerado por docs/gerar_prompts.php).
 * A resposta (fichas) é colada na caixa "Extrair" do painel (admin/pages/cursos.php).
 *
 *  - mestre($ia): instruções para configurar UMA vez na IA (Space, Projeto, Gem...). Depois, basta mandar
 *    links ou títulos, um por linha: ela responde uma FICHA por item.
 *  - avulso($entradas): prompt pronto para uma conversa só, com os links/títulos já dentro.
 *  - formatoFicha(): os rótulos da ficha — os MESMOS campos do formulário de cadastro e os mesmos que
 *    ExtracaoCurso::fichas() lê. Uma ficha colada na "Máquina de extração" preenche o formulário inteiro;
 *    várias fichas vão em "Importar vários".
 */
final class PromptsPesquisa {
    /** IAs de pesquisa: nome e como configurar o prompt mestre em cada uma. */
    public const IAS = [
        'perplexity' => ['nome' => 'Perplexity', 'onde' => 'Crie um Space (Espaços → Criar Space) e cole o prompt mestre no campo de instruções. A pesquisa na web já vem ligada.',
            'extra' => 'Não coloque números de citação ([1], [2]...) dentro das fichas.'],
        'chatgpt' => ['nome' => 'ChatGPT', 'onde' => 'Crie um Projeto (ou um GPT personalizado) e cole o prompt mestre nas instruções. Em cada conversa, deixe a pesquisa na web ligada.',
            'extra' => 'Use SEMPRE a pesquisa na web para abrir as páginas; nunca responda de memória.'],
        'gemini' => ['nome' => 'Google Gemini', 'onde' => 'Crie um Gem (Gems → Novo Gem) e cole o prompt mestre nas instruções. O Gemini pesquisa no Google sozinho.',
            'extra' => 'Pesquise no Google e abra a página oficial de cada item antes de responder.'],
        'copilot' => ['nome' => 'Microsoft Copilot', 'onde' => 'Cole o prompt mestre como PRIMEIRA mensagem de uma conversa nova; ele responde "Pronto" e aí você manda os links ou títulos.',
            'extra' => 'Pesquise na web (Bing) e abra a página oficial de cada item antes de responder.'],
        'claude' => ['nome' => 'Claude', 'onde' => 'Crie um Projeto e cole o prompt mestre nas instruções do projeto. Ligue a pesquisa na web nas conversas.',
            'extra' => 'Use a pesquisa na web para abrir as páginas oficiais; nunca responda de memória.'],
    ];

    /** Os rótulos da ficha, na ordem do formulário de cadastro. */
    public static function formatoFicha(array $areas): string {
        $listaAreas = implode(' | ', $areas ?: ExtracaoCurso::AREAS);
        return <<<TXT
Título: nome oficial do curso ou e-book
Tipo: Curso | E-book | Vídeo
Instituição: quem oferece
Modalidade: EAD | Presencial | Híbrido
Cidade: cidade/UF (só se for presencial ou híbrido; se for EAD escreva Online)
Nível: Iniciante | Intermediário | Avançado
Carga horária: ex.: 20 horas (ou Não informado)
Gratuito: Sim | Não
Preço: ex.: R\$ 49,90 (só se não for gratuito)
Área: uma destas: {$listaAreas}
Link: endereço oficial completo, começando com https://
PDF: (só e-book gratuito) endereço DIRETO do arquivo PDF oficial, terminando em .pdf (ou Não encontrado)
Imagem: endereço DIRETO do arquivo da imagem (capa do e-book ou imagem de divulgação do curso), começando com https:// e terminando em .jpg, .jpeg, .png ou .webp (ou Não encontrada)
Descrição: 1 ou 2 frases dizendo o que a pessoa aprende e se tem certificado
---
TXT;
    }

    /** Regras de pesquisa comuns aos prompts (fontes oficiais, link, imagem, não inventar). */
    private static function regras(): string {
        $fontes = '';
        foreach (FontesCursos::FONTES as $f) $fontes .= $f['nome'].' ('.$f['dominios'][0].'), ';
        $fontes = rtrim($fontes, ', ');
        return <<<TXT
REGRAS DE PESQUISA:
1. Abra a página OFICIAL de cada item (site da instituição) antes de responder. Nunca invente link, carga horária, preço ou imagem: o que não achar, escreva Não informado (na imagem: Não encontrada).
2. Link: o endereço oficial da página do próprio curso/e-book (para e-book, pode ser o PDF oficial). Nada de página inicial, resultado de busca ou site que copia conteúdo.
   PDF (e-book gratuito): o link DIRETO do arquivo no site oficial (o que baixa o PDF, terminando em .pdf) — a plataforma guarda esse PDF na biblioteca dela. Nunca de site que copia conteúdo; livro pago não tem PDF.
3. Imagem: o endereço DIRETO do arquivo da imagem oficial do item — aberto no navegador, mostra só a imagem. Termina em .jpg, .jpeg, .png ou .webp (pode ter ?parâmetros depois). No e-book, a CAPA; no curso, a imagem de divulgação da página (a mesma que aparece quando o link é compartilhado: metatag og:image). Prefira imagem com pelo menos 300 px de largura.
   Nunca: a página do curso no lugar da imagem, logotipo ou ícone da instituição, banner genérico do site, miniatura do Google/Bing, link de resultado de busca, imagem em base64 (data:), imagem gerada por IA ou de outro site. Não achou a imagem certa? Escreva Não encontrada: a plataforma procura sozinha a imagem de divulgação da página do Link e, se não achar, cadastra com a imagem padrão (o administrador troca depois).
4. Fontes oficiais preferidas: {$fontes}. Outras instituições públicas ou reconhecidas valem se o link for do site oficial delas.
5. Tudo em português. Descrição curta e objetiva, sem propaganda. Diga se tem certificado. Área: escolha a mais próxima da lista (nunca invente uma área nova).
6. Se um item estiver encerrado, fora do ar ou não for encontrado, responda no lugar da ficha: NÃO ENCONTRADO: <o que foi pedido> — <motivo>.
7. Responda SOMENTE com as fichas, sem introdução, sem conclusão, sem tabela e sem negrito. Uma ficha por item, separadas por uma linha contendo apenas ---
TXT;
    }

    /**
     * Prompt mestre de uma IA: configurado uma vez; depois cada mensagem é um ou mais links/títulos.
     * @param list<string> $areas categorias de curso ativas (o que vai em "Área")
     */
    public static function mestre(string $ia, array $areas): string {
        $cfg = self::IAS[$ia] ?? self::IAS['perplexity'];
        $formato = self::formatoFicha($areas);
        $regras = self::regras();
        $inicio = $ia === 'copilot' ? "\nAgora responda apenas: Pronto. Depois disso, cada mensagem minha será a lista de itens a pesquisar." : '';
        return <<<TXT
Você é o pesquisador de cursos e e-books da plataforma Conecta Vagas DF (empregos no Distrito Federal, Brasil). O público são pessoas procurando emprego, muitas no primeiro emprego.

COMO VAMOS TRABALHAR: em cada mensagem eu mando um ou mais itens, um por linha. Cada item é um LINK (endereço de um curso ou e-book) ou um TÍTULO (nome de um curso ou e-book, às vezes com a instituição). Para cada item:
- se for LINK: abra o link, confirme que é a página oficial do curso/e-book e preencha a ficha com o que está nela;
- se for TÍTULO: encontre a página oficial desse curso/e-book (a da instituição que oferece) e preencha a ficha com o que está nela.
Mantenha a mesma ordem da minha lista. {$cfg['extra']}

{$regras}

FORMATO DE CADA FICHA (copie os rótulos exatamente assim, um por linha):
{$formato}{$inicio}
TXT;
    }

    /**
     * Prompt avulso (uma conversa só): o prompt mestre + os links/títulos já colados.
     * @param list<string> $entradas links ou títulos (vazios e repetidos são ignorados; no máximo 20)
     */
    public static function avulso(array $entradas, array $areas, string $formato = ''): string {
        $itens = self::entradas($entradas);
        $oQue = ['curso' => 'Todos os itens são CURSOS.', 'ebook' => 'Todos os itens são E-BOOKS (guias, cartilhas, livros digitais).', 'video' => 'Todos os itens são VÍDEOS/aulas gravadas.'][$formato] ?? '';
        $lista = '';
        foreach ($itens as $n => $e) $lista .= ($n + 1).'. '.$e.' — '.(url_http_valida($e) ? 'LINK' : 'TÍTULO')."\n";
        $regras = self::regras();
        $ficha = self::formatoFicha($areas);
        $qtd = count($itens);
        return <<<TXT
Você é o pesquisador de cursos e e-books da plataforma Conecta Vagas DF (empregos no Distrito Federal, Brasil).

TAREFA: pesquise na internet os {$qtd} itens abaixo e preencha UMA ficha para cada um, na mesma ordem. Se o item for LINK, abra o link e use a página dele; se for TÍTULO, encontre a página oficial desse curso/e-book. {$oQue}

ITENS:
{$lista}
{$regras}

FORMATO DE CADA FICHA (copie os rótulos exatamente assim, um por linha):
{$ficha}
TXT;
    }

    /** Linhas de entrada limpas: sem vazias, sem repetidas, sem numeração/marcadores, no máximo 20. */
    public static function entradas(array $linhas): array {
        $out = [];
        foreach ($linhas as $l) {
            $l = trim(preg_replace('/^\s*(?:\d+[.)]|[-*•])\s*/u', '', (string)$l) ?? '');
            if ($l !== '' && !in_array($l, $out, true)) $out[] = mb_substr($l, 0, 300);
        }
        return array_slice($out, 0, 20);
    }
}
