<?php
/**
 * Meu perfil = CADASTRO do candidato (rota view/perfil/index.php): máquina de extração do currículo,
 * formulário do perfil, plano e candidaturas.
 * Recebe de PerfilController::index(): $perfil, $validacao, $completude, $isVip, $assinaturaAtiva,
 * $candidaturasAtivas, $cvs, $cands e $relatorio (+ $p, usado pelo relatório da extração).
 */
?>
<div class="profile-head">
    <?php if (!empty($perfil['foto'])): ?>
        <img class="avatar" src="<?=e(url($perfil['foto']))?>" alt="Foto de perfil">
    <?php else: ?>
        <div class="avatar pf-foto-vazia" style="display:grid;place-items:center;font-size:30px;font-weight:800;color:#0b3f82;background:#dbeafe"><?=e(mb_strtoupper(mb_substr((string)$perfil['nome'], 0, 1)))?></div>
    <?php endif; ?>
    <div style="flex:1;">
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <span class="tag">Perfil profissional</span>
            <?php if ($isVip): ?><span class="badge-vip">⭐ Candidato VIP</span>
            <?php else: ?><span class="tag" style="background:#e2e8f0; color:#475569;">Plano Gratuito</span><?php endif; ?>
        </div>
        <h1 style="margin: 8px 0 4px;"><?=e($perfil['nome'])?></h1>
        <p style="margin:0;"><?=e($perfil['titulo_profissional'] ?: 'Complete seu título profissional')?> · <?=e($perfil['cidade'] ?: 'Brasília')?>/<?=e($perfil['uf'] ?: 'DF')?></p>
        <div class="progress" style="margin-top:10px;max-width:360px" title="Cadastro <?=$completude?>% completo"><span style="width:<?=$completude?>%"></span></div>
        <small style="color:#dbe7ff">Cadastro <?=$completude?>% completo</small>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if ($validacao['completo']): ?><a href="<?=url('view/perfil/portfolio.php')?>" class="btn btn-sm btn-gold"><?=icone('jornal', 16)?>Abrir meu portfólio</a><?php endif; ?>
        <a href="<?=url('planos.php')?>" class="btn btn-sm btn-outline"><?=$isVip ? '⭐ Meu plano' : '⭐ Seja VIP'?></a>
    </div>
</div>
<?php if (isset($_GET['novo'])): ?><div class="alert ok">Conta criada! Envie seu currículo abaixo: o sistema lê o arquivo e preenche o seu cadastro. Com o cadastro completo, a aba <b>Portfólio</b> é liberada no topo.</div><?php endif; ?>

<?php if ($validacao['completo']): ?>
  <div class="cv-validacao ok">
    <div><b>✓ Cadastro validado!</b> A aba <b>Portfólio</b> foi liberada no topo da página. Lá estão o seu portfólio montado com estes dados e a <b>máquina de match</b> com as vagas.
      <?php if ($validacao['recomendados']): ?><br><small>Para deixar o portfólio ainda melhor, preencha também: <?=e(implode(', ', $validacao['recomendados']))?>.</small><?php endif; ?></div>
    <a class="cv-btn cv-btn-verde" href="<?=url('view/perfil/portfolio.php')?>"><?=icone('jornal', 16)?>Abrir meu portfólio</a>
  </div>
<?php else: ?>
  <div class="cv-validacao">
    <div><b>Complete o cadastro para liberar o seu Portfólio.</b> Envie o currículo (o sistema preenche sozinho) ou preencha o formulário. Falta:</div>
    <ul>
      <?php foreach (Portfolio::OBRIGATORIOS as $campo => $rot): $falta = isset($validacao['faltando'][$campo]); ?>
        <li class="<?=$falta ? 'falta' : 'ok'?>"><?=$falta ? '✗' : '✓'?> <?=e($rot)?></li>
      <?php endforeach; ?>
    </ul>
    <a class="cv-btn cv-btn-azul" href="#cadastro"><?=icone('editar', 16)?>Completar cadastro</a>
  </div>
<?php endif; ?>

<?php if ($relatorio) require __DIR__.'/../partials/relatorio_extracao.php'; ?>

<div class="profile-layout">
<div>
    <div class="panel">
        <div class="section-head" style="margin-top:0"><h3 style="margin:0">📄 Máquina de extração do currículo</h3></div>
        <div class="cv-machine">
            <p>Envie PDF, DOCX ou DOC (até 10 MB). O sistema lê o arquivo (inclusive modelos em duas colunas do Canva/Word), separa nome, contato, LinkedIn/GitHub, resumo, experiências, formação, cursos, habilidades, idiomas, CNH, disponibilidade, pretensão salarial, PCD e a foto, e <b>preenche o seu cadastro</b> abaixo, mostrando um <b>relatório</b> do que foi encontrado. Com o cadastro validado, a aba <b>Portfólio</b> é liberada — lá ficam o portfólio montado e a máquina de match.</p>
            <form method="post" action="<?=url('view/perfil/curriculo_upload.php')?>" enctype="multipart/form-data" data-carregando="Lendo o currículo e preenchendo o perfil…">
                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                <div class="form-grid">
                    <div><label for="pf-cv-arquivo">Arquivo</label><input id="pf-cv-arquivo" type="file" name="curriculo" accept=".pdf,.docx,.doc" required></div>
                    <div><label for="pf-cv-titulo">Título do currículo</label><input id="pf-cv-titulo" name="titulo" value="Currículo profissional" maxlength="255"></div>
                </div>
                <div class="check"><input type="checkbox" name="substituir" value="1" id="substituir"><label for="substituir">Substituir os dados do perfil pelos do currículo (sem marcar, só os campos vazios são preenchidos).</label></div>
                <div class="form-actions"><?php if (!empty($_SESSION['relatorio_extracao']) && !$relatorio): ?><a class="btn btn-sm btn-outline" href="<?=url('view/perfil/index.php?relatorio=1')?>">Ver último relatório da extração</a><?php endif; ?><button class="btn">Enviar e preencher o cadastro</button></div>
            </form>
        </div>
        <?php if ($cvs): foreach ($cvs as $cv): ?>
            <div class="list-item">
                <div style="flex:1">
                    <b><?=e($cv['titulo'] ?: 'Currículo')?></b>
                    <span class="meta"> · <?=e(strtoupper($cv['arquivo_tipo']))?> · <?=date('d/m/Y H:i', strtotime($cv['created_at']))?> · <?=number_format((int)($cv['downloads'] ?? 0), 0, '', '.')?> abertura(s)</span>
                    <?php if (trim((string)($cv['curriculo_texto'] ?? '')) !== ''): ?>
                        <details style="margin-top:8px;"><summary style="cursor:pointer; color:#475569;">Ver texto extraído</summary><div class="notice small" style="margin-top:8px; white-space:pre-wrap; max-height:220px; overflow:auto;"><?=e((string)$cv['curriculo_texto'])?></div></details>
                    <?php else: ?><div class="meta">Texto não extraído (arquivo escaneado/imagem).</div><?php endif; ?>
                </div>
                <div class="actions">
                    <a class="btn btn-sm btn-outline" href="<?=url('download.php?id='.(int)$cv['id'])?>" target="_blank">Abrir</a>
                    <form method="post" action="<?=url('view/perfil/curriculo_excluir.php')?>">
                        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$cv['id']?>">
                        <button class="btn btn-sm btn-danger" data-confirm="Remover este currículo?">Remover currículo</button>
                    </form>
                </div>
            </div>
        <?php endforeach; else: ?><div class="empty">Nenhum currículo enviado ainda.</div><?php endif; ?>
    </div>

    <form class="panel" id="cadastro" style="margin-top:18px" method="post" action="<?=url('view/perfil/salvar.php')?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <div class="section-head" style="margin-top:0"><h3 style="margin:0">Cadastro do perfil</h3><small class="muted">Campos com * são obrigatórios para liberar o Portfólio</small></div>
        <div class="form-grid">
            <div><label for="pf-nome">Nome</label><input id="pf-nome" value="<?=e($perfil['nome'])?>" disabled></div>
            <div><label for="pf-email">E-mail</label><input id="pf-email" value="<?=e($perfil['email'])?>" disabled></div>
            <div><label for="pf-titulo">Título profissional *</label><input id="pf-titulo" name="titulo_profissional" maxlength="255" value="<?=e($perfil['titulo_profissional'])?>" placeholder="Ex.: Profissional em Administração | TI | RH"></div>
            <div><label for="pf-nivel">Nível de experiência</label><select id="pf-nivel" name="nivel_experiencia"><?php foreach (PerfilDAO::NIVEIS as $n): ?><option value="<?=$n?>" <?=$perfil['nivel_experiencia'] === $n ? 'selected' : ''?>><?=e(rotulo($n))?></option><?php endforeach; ?></select></div>
            <div><label for="pf-tel">Telefone *</label><input id="pf-tel" name="telefone" maxlength="30" value="<?=e($perfil['telefone'])?>" placeholder="(61) 99999-9999"></div>
            <div><label for="pf-nasc">Data de nascimento</label><input id="pf-nasc" type="date" name="data_nascimento" value="<?=e($perfil['data_nascimento'])?>"></div>
            <div><label for="pf-cidade">Cidade / região *</label><input id="pf-cidade" name="cidade" maxlength="100" value="<?=e($perfil['cidade'])?>" placeholder="Ex.: Ceilândia"></div>
            <div><label for="pf-uf">UF</label><input id="pf-uf" name="uf" maxlength="2" value="<?=e($perfil['uf'] ?: 'DF')?>"></div>
            <div class="full"><label for="pf-bio">Resumo profissional *</label><textarea id="pf-bio" name="bio" rows="4"><?=e($perfil['bio'])?></textarea></div>
            <div class="full"><label for="pf-objetivo">Objetivo profissional *</label><textarea id="pf-objetivo" name="objetivo" rows="3"><?=e($perfil['objetivo'])?></textarea></div>
            <div class="full"><label for="pf-exp">Experiências <small class="muted">— uma por bloco: Empresa, cargo e período (ex.: 2021 – 2023 ou 2025 – Atual)</small></label><textarea id="pf-exp" name="experiencias" rows="7" placeholder="ATACADÃO DIA A DIA&#10;Auxiliar Administrativo&#10;2025 – Atual"><?=e($perfil['experiencias'])?></textarea></div>
            <div class="full"><label for="pf-formacao">Formação acadêmica e técnica * <small class="muted">— Curso – Instituição (situação)</small></label><textarea id="pf-formacao" name="formacao" rows="3" placeholder="Análise e Desenvolvimento de Sistemas – Faculdade Anhanguera (cursando)"><?=e($perfil['formacao'])?></textarea></div>
            <div><label for="pf-habilidades">Habilidades * <small class="muted">(separe por vírgula)</small></label><textarea id="pf-habilidades" name="habilidades" rows="4"><?=e($perfil['habilidades'])?></textarea></div>
            <div><label for="pf-competencias">Competências comportamentais</label><textarea id="pf-competencias" name="competencias" rows="4"><?=e($perfil['competencias'])?></textarea></div>
            <div><label for="pf-cursos-compl">Cursos complementares <small class="muted">(um por linha)</small></label><textarea id="pf-cursos-compl" name="cursos_complementares" rows="4"><?=e($perfil['cursos_complementares'])?></textarea></div>
            <div><label for="pf-idiomas">Idiomas</label><textarea id="pf-idiomas" name="idiomas" rows="4"><?=e($perfil['idiomas'])?></textarea></div>
            <div class="full"><label for="pf-info-add">Informações adicionais <small class="muted">(PCD, CNH, disponibilidade para viagens...)</small></label><textarea id="pf-info-add" name="informacoes_adicionais" rows="2"><?=e($perfil['informacoes_adicionais'])?></textarea></div>
            <div><label for="pf-disp">Disponibilidade <small class="muted">(horário, viagens, mudança)</small></label><input id="pf-disp" name="disponibilidade" maxlength="100" value="<?=e($perfil['disponibilidade'])?>" placeholder="Ex.: 12h às 18h30; disponível para viagens"></div>
            <div><label for="pf-cnh">CNH <small class="muted">(categoria)</small></label><input id="pf-cnh" name="cnh" maxlength="5" value="<?=e($perfil['cnh'] ?? '')?>" placeholder="Ex.: B, AB, D"></div>
            <div><label for="pf-pretensao">Pretensão salarial</label><input id="pf-pretensao" name="pretensao_salarial" maxlength="60" value="<?=e($perfil['pretensao_salarial'] ?? '')?>" placeholder="Ex.: R$ 2.500,00 ou a combinar"></div>
            <div class="full"><label for="pf-links">Links <small class="muted">(LinkedIn, GitHub, portfólio — um por linha)</small></label><textarea id="pf-links" name="links" rows="2" placeholder="https://linkedin.com/in/seu-perfil"><?=e($perfil['links'] ?? '')?></textarea></div>
            <div><label for="pf-foto">Nova foto <small class="muted">(JPG/PNG/WEBP, até 3 MB)</small></label><input id="pf-foto" type="file" name="foto" accept="image/jpeg,image/png,image/webp"></div>
        </div>
        <div class="check"><input type="checkbox" name="publico" value="1" id="publico" <?=$perfil['publico'] ? 'checked' : ''?>><label for="publico">Deixar meu portfólio visível para empresas no Banco de Talentos.</label></div>
        <div class="form-actions"><button class="btn">Salvar e validar cadastro</button></div>
    </form>

</div>

<aside>
    <div class="panel">
        <h3>⭐ Meu plano</h3>
        <?php if ($isVip): ?>
            <span class="badge-vip">⭐ Candidato VIP ativo</span>
            <p style="font-size:13px; color:#475569; margin:6px 0;">Válido até <b><?=date('d/m/Y', strtotime($assinaturaAtiva['data_fim'] ?? 'now'))?></b><br>✓ Candidaturas ilimitadas<br>✓ Destaque no topo para empresas<br>✓ Match completo</p>
            <a href="<?=url('planos.php')?>" class="btn btn-sm btn-outline" style="width:100%;">Gerenciar plano</a>
        <?php else: ?>
            <span class="tag" style="background:#e2e8f0; color:#475569;">Plano Gratuito</span>
            <p style="font-size:13px; color:#475569; margin:6px 0;">Candidaturas ativas: <b><?=$candidaturasAtivas?> / 3</b></p>
            <a href="<?=url('planos.php')?>" class="btn btn-sm btn-gold" style="width:100%;">Virar VIP (R$ 9,90/mês)</a>
        <?php endif; ?>
        <hr style="border:0; border-top:1px solid var(--line); margin:14px 0;">
        <h3>📌 Resumo</h3>
        <p><b>Currículos:</b> <?=count($cvs)?></p>
        <p><b>Candidaturas:</b> <?=count($cands)?></p>
        <p><b>Cadastro:</b> <?=$validacao['completo'] ? 'validado ✓' : 'incompleto ('.$completude.'%)'?></p>
        <p><b>Portfólio:</b> <?=$validacao['completo'] ? ($perfil['publico'] ? 'liberado e visível para empresas' : 'liberado (privado)') : 'bloqueado até completar o cadastro'?></p>
    </div>

    <div class="panel" id="candidaturas" style="margin-top:18px">
        <h3>📨 Minhas candidaturas</h3>
        <?php if (!$cands): ?>
            <p class="muted">Você ainda não enviou candidaturas. <a href="<?=url('vagas.php')?>">Ver vagas</a></p>
        <?php else: foreach ($cands as $c): ?>
            <div class="list-item" style="display:block">
                <div style="display:flex; justify-content:space-between; align-items:start; gap:8px">
                    <div>
                        <b><a href="<?=url('vaga.php?id='.(int)$c['vaga_id'])?>" style="text-decoration:none; color:inherit;"><?=e($c['titulo'])?></a></b>
                        <div class="meta"><?=e($c['empresa_nome'] ?? 'Empresa')?> · <?=date('d/m/Y', strtotime($c['data_candidatura']))?></div>
                    </div>
                    <span class="tag"><?=e(rotulo($c['status']))?></span>
                </div>
                <?php if (!empty($c['observacao_empresa'])): ?>
                    <div class="notice small" style="margin-top:8px; padding:8px 12px;"><b>Retorno da empresa:</b><br><?=e($c['observacao_empresa'])?></div>
                <?php endif; ?>
                <?php if (in_array($c['status'], ['enviada', 'em_analise'], true)): ?>
                    <form method="post" action="<?=url('view/perfil/candidatura_cancelar.php')?>" style="margin-top:6px">
                        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$c['id']?>">
                        <button class="btn btn-sm btn-danger" style="padding:4px 10px; font-size:12px;" data-confirm="Cancelar esta candidatura?">Cancelar candidatura</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="panel" id="excluir-conta" style="margin-top:18px">
        <h3>🔒 Seus dados (LGPD)</h3>
        <p class="meta">Você pode apagar a sua conta quando quiser. Saem juntos o perfil, os currículos e arquivos, a foto, as candidaturas e o match. Não dá para desfazer.</p>
        <details>
            <summary style="cursor:pointer; font-weight:700; color:#991b1b;">Excluir minha conta</summary>
            <form method="post" action="<?=url('view/perfil/conta_excluir.php')?>" style="margin-top:10px">
                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                <label for="ex-senha">Digite sua senha para confirmar</label>
                <input id="ex-senha" type="password" name="senha" required autocomplete="current-password">
                <div class="check" style="margin-top:10px"><input type="checkbox" name="confirmo" value="1" id="ex-confirmo" required><label for="ex-confirmo" style="font-weight:400">Entendo que a minha conta e os meus dados serão apagados para sempre.</label></div>
                <div class="form-actions" style="margin-top:12px"><button class="btn btn-sm btn-danger">Excluir minha conta</button></div>
            </form>
        </details>
    </div>
</aside>
</div>
