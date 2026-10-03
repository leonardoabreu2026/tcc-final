<?php
/**
 * Planos e assinaturas (rota planos.php): Candidato VIP e Empresa Premium, status e histórico.
 * Recebe de PlanosController::index(): $dao (AssinaturaDAO), $usuarioLogado, $usuarioId,
 * $assinaturaAtiva e $historicoAssinaturas.
 * As abas "Para candidatos / Para empresas" funcionam só com CSS (botões de opção escondidos).
 */
// Aba inicial: a pedida no endereço (?aba=empresa, vinda dos cartões da página inicial) ou a do tipo de conta.
$abaPedida = get_str('aba');
$abaEmpresa = $abaPedida === 'empresa' || ($abaPedida !== 'candidato' && $usuarioLogado && isEmpresa());
$linkEntrar = url('login.php?voltar=planos');
$ehVip      = $usuarioLogado && isCandidato() && $dao->isCandidatoVip($usuarioId);
$ehPremium  = $usuarioLogado && isEmpresa() && $dao->isEmpresaPremium($usuarioId);

/** Lista de recursos do plano: [texto, incluso?]. */
$recursos = static function (array $itens): string {
    $h = '<ul class="pl-itens">';
    foreach ($itens as [$texto, $incluso]) {
        $h .= $incluso
            ? '<li>' . icone('check', 16) . '<span>' . e($texto) . '</span></li>'
            : '<li class="off"><i aria-hidden="true">×</i><span>' . e($texto) . '</span><span class="sr-only"> (não incluso)</span></li>';
    }
    return $h . '</ul>';
};
?>

<section class="pl">
    <header class="pl-topo">
        <div>
            <span class="tag">Planos</span>
            <h1>Escolha o plano ideal</h1>
            <p class="muted">Vantagens para quem busca emprego e para empresas que querem contratar mais rápido no DF.</p>
        </div>
        <?php if ($usuarioLogado): ?>
            <div class="pl-status">
                <span class="meta">Seu plano</span>
                <?php if ($assinaturaAtiva && $assinaturaAtiva['plano'] === 'assinante'): ?>
                    <span class="badge-vip">Candidato VIP</span>
                    <span class="meta">até <?=date('d/m/Y', strtotime($assinaturaAtiva['data_fim']))?></span>
                <?php elseif ($assinaturaAtiva && $assinaturaAtiva['plano'] === 'empresa'): ?>
                    <span class="badge-pro">Empresa Premium</span>
                    <span class="meta">até <?=date('d/m/Y', strtotime($assinaturaAtiva['data_fim']))?></span>
                <?php else: ?>
                    <span class="tag pl-tag-gratis">Gratuito</span>
                <?php endif; ?>
                <?php if ($assinaturaAtiva): ?>
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                        <input type="hidden" name="acao" value="cancelar">
                        <button class="pl-cancelar" data-confirm="Deseja realmente cancelar sua assinatura ativa?">Cancelar</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </header>

    <div class="pl-quadro">
        <input type="radio" name="pl-aba" id="pl-aba-cand" class="sr-only" <?=$abaEmpresa ? '' : 'checked'?>>
        <input type="radio" name="pl-aba" id="pl-aba-emp" class="sr-only" <?=$abaEmpresa ? 'checked' : ''?>>
        <div class="pl-abas">
            <label for="pl-aba-cand"><?=icone('perfil', 18)?> Para candidatos</label>
            <label for="pl-aba-emp"><?=icone('maleta', 18)?> Para empresas</label>
        </div>

        <!-- Candidatos -->
        <div class="pl-grade pl-grade-cand">
            <article class="pl-card" id="plano-gratuito">
                <h2>Candidato Gratuito</h2>
                <p class="pl-desc">Para começar a busca por vagas no DF.</p>
                <div class="pl-preco">R$ 0 <small>para sempre</small></div>
                <?=$recursos([
                    ['Perfil profissional e envio de currículo (PDF/DOCX)', true],
                    ['Até '.AssinaturaDAO::LIMITE_CANDIDATURAS_GRATIS.' candidaturas ativas ao mesmo tempo', true],
                    ['As 3 vagas mais compatíveis com você', true],
                    ['Acompanhamento do retorno da empresa', true],
                    ['Candidaturas ilimitadas', false],
                    ['Prioridade para os recrutadores', false],
                ])?>
                <div class="pl-acao">
                    <?php if ($usuarioLogado && isCandidato() && !$ehVip): ?>
                        <a href="<?=url('vagas.php')?>" class="btn btn-outline">Seu plano atual · ver vagas</a>
                    <?php elseif ($ehVip): ?>
                        <span class="meta">Tudo isso já está incluso no seu VIP.</span>
                    <?php elseif (!$usuarioLogado): ?>
                        <a href="<?=url('cadastro.php')?>" class="btn btn-outline">Cadastrar grátis</a>
                    <?php else: ?>
                        <span class="meta">Disponível para contas de candidato</span>
                    <?php endif; ?>
                </div>
            </article>

            <article class="pl-card pl-destaque" id="plano-vip">
                <span class="pl-selo">Mais escolhido</span>
                <h2>Candidato VIP</h2>
                <p class="pl-desc">Mais visibilidade e mais chances de ser contratado.</p>
                <div class="pl-preco">R$ 9,90 <small>/ mês</small></div>
                <?=$recursos([
                    ['Candidaturas ilimitadas', true],
                    ['Selo de Candidato VIP no perfil', true],
                    ['Prioridade na lista de candidatos das empresas', true],
                    ['Match completo: todas as vagas compatíveis', true],
                    ['Sem fidelidade: cancele quando quiser', true],
                ])?>
                <div class="pl-acao">
                    <?php if ($usuarioLogado && isCandidato()): ?>
                        <?php if ($ehVip): ?>
                            <button class="btn btn-green" disabled>Plano VIP ativo</button>
                        <?php else: ?>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                                <input type="hidden" name="acao" value="assinar_candidato">
                                <button class="btn">Ativar VIP</button>
                            </form>
                        <?php endif; ?>
                    <?php elseif (!$usuarioLogado): ?>
                        <a href="<?=$linkEntrar?>" class="btn">Entrar para assinar</a>
                        <a href="<?=url('cadastro.php')?>" class="pl-link">Ainda não tem conta? Cadastre-se</a>
                    <?php else: ?>
                        <span class="meta">Disponível para contas de candidato</span>
                    <?php endif; ?>
                </div>
            </article>
        </div>

        <!-- Empresas -->
        <div class="pl-grade pl-grade-emp">
            <article class="pl-card" id="plano-empresa">
                <h2>Empresa Básica</h2>
                <p class="pl-desc">Para divulgar vagas de vez em quando.</p>
                <div class="pl-preco">R$ 0 <small>para sempre</small></div>
                <?=$recursos([
                    ['Perfil da empresa', true],
                    ['Até '.AssinaturaDAO::LIMITE_VAGAS_GRATIS.' vagas ativas ao mesmo tempo', true],
                    ['Recebimento e gestão de candidaturas', true],
                    ['Vagas ilimitadas', false],
                    ['Vagas em destaque', false],
                    ['Banco de Talentos completo', false],
                ])?>
                <div class="pl-acao">
                    <?php if ($usuarioLogado && isEmpresa() && !$ehPremium): ?>
                        <a href="<?=url('admin/pages/vagas.php')?>" class="btn btn-outline">Seu plano atual · publicar vaga</a>
                    <?php elseif ($ehPremium): ?>
                        <span class="meta">Tudo isso já está incluso no seu Premium.</span>
                    <?php elseif (!$usuarioLogado): ?>
                        <a href="<?=url('cadastro.php')?>" class="btn btn-outline">Cadastrar empresa</a>
                    <?php else: ?>
                        <span class="meta">Disponível para contas de empresa</span>
                    <?php endif; ?>
                </div>
            </article>

            <article class="pl-card pl-destaque" id="plano-premium">
                <span class="pl-selo">Recrutamento Pro</span>
                <h2>Empresa Premium</h2>
                <p class="pl-desc">Para quem contrata sempre e precisa de agilidade.</p>
                <div class="pl-preco">R$ 49,90 <small>/ mês</small></div>
                <?=$recursos([
                    ['Vagas ativas ilimitadas', true],
                    ['Vagas em destaque na busca e na página inicial', true],
                    ['Banco de Talentos com busca e download de currículos', true],
                    ['Selo de Empresa Premium', true],
                    ['Cancele quando quiser, sem taxas', true],
                ])?>
                <div class="pl-acao">
                    <?php if ($usuarioLogado && isEmpresa()): ?>
                        <?php if ($ehPremium): ?>
                            <button class="btn btn-green" disabled>Premium ativo</button>
                        <?php else: ?>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                                <input type="hidden" name="acao" value="assinar_empresa">
                                <button class="btn">Assinar Premium</button>
                            </form>
                        <?php endif; ?>
                    <?php elseif (!$usuarioLogado): ?>
                        <a href="<?=$linkEntrar?>" class="btn">Entrar para assinar</a>
                        <a href="<?=url('cadastro.php')?>" class="pl-link">Ainda não tem conta? Cadastre a empresa</a>
                    <?php else: ?>
                        <span class="meta">Disponível para contas de empresa</span>
                    <?php endif; ?>
                </div>
            </article>
        </div>

        <p class="pl-nota meta">Planos demonstrativos do TCC: nenhuma cobrança é feita.</p>
    </div>

    <?php if ($usuarioLogado && $historicoAssinaturas): ?>
        <h2 class="pl-hist-titulo">Histórico da assinatura</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Plano</th><th>Valor</th><th>Início</th><th>Fim</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($historicoAssinaturas as $historico): ?>
                    <tr>
                        <td><?=e($historico['plano'] === 'assinante' ? 'Candidato VIP' : 'Empresa Premium')?></td>
                        <td>R$ <?=number_format((float)$historico['valor'], 2, ',', '.')?></td>
                        <td><?=!empty($historico['data_inicio']) ? date('d/m/Y', strtotime($historico['data_inicio'])) : '-'?></td>
                        <td><?=!empty($historico['data_fim']) ? date('d/m/Y', strtotime($historico['data_fim'])) : '-'?></td>
                        <td>
                            <?php
                            $statusLabel = match ($historico['status']) {
                                'ativa' => 'Ativa',
                                'cancelada' => 'Cancelada',
                                'expirada' => 'Expirada',
                                default => ucfirst((string)$historico['status'])
                            };
                            ?>
                            <span class="tag"><?=e($statusLabel)?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
