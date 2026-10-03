/*
 * Leitor de cartaz DA PLATAFORMA: o OCR roda no navegador de quem envia o cartaz (Tesseract.js em WebAssembly),
 * com todos os arquivos servidos pelo próprio site (assets/js/vendor/tesseract) — nada para instalar no servidor
 * nem no computador de ninguém.
 *
 * Faz as MESMAS 4 leituras do leitor do servidor (OcrImagem): imagem em cinza + gama, ampliada para ~2200 px,
 * e o negativo dela, cada uma em modo "página" (psm 3) e "texto solto" (psm 11). As leituras (TSV: palavra,
 * posição, altura e confiança) vão junto com o cartaz, e o servidor monta o resultado com os mesmos ajustes.
 * Se o navegador não conseguir ler, o cartaz é enviado sem as leituras e o servidor lê (se tiver o Tesseract).
 *
 * Carregador: AMARELO enquanto carrega o leitor ou lê o cartaz; AZUL quando está pronto.
 */
(() => {
  const form = document.querySelector('form[data-leitor-cartaz]');
  if (!form) return;
  const input = form.querySelector('input[type=file]');
  const base = form.dataset.leitorCartaz.replace(/\/?$/, '/');
  const caixa = form.closest('.extrator') || form.parentElement;
  const barra = form.querySelector('[data-carregador]');
  const LARGURA = 2200, MAX_PIXELS = 40e6;
  // O navegador precisa de WebAssembly e Worker; sem eles, o envio segue normal (o servidor lê).
  if (!input || !barra || typeof WebAssembly !== 'object' || typeof Worker !== 'function') return;
  form.dataset.leitor = '1'; // o envio automático do app.js passa a ser feito aqui

  const mostrar = (estado, texto, pct) => {
    barra.hidden = false;
    barra.classList.toggle('carregando', estado === 'carregando');
    barra.classList.toggle('pronto', estado === 'pronto');
    barra.classList.toggle('indeterminado', estado === 'carregando' && pct == null);
    barra.querySelector('.carregador-barra').style.width = (estado === 'pronto' ? 100 : Math.max(4, Math.min(100, pct || 0))) + '%';
    barra.querySelector('.carregador-texto').textContent = texto + (estado === 'carregando' && pct != null ? ' ' + Math.round(pct) + '%' : '');
  };
  const travar = sim => caixa.querySelectorAll('button, input[type=file], textarea').forEach(b => { b.disabled = sim; });

  // ---- carregamento do leitor (uma vez por página) ----
  const nWorkers = (navigator.hardwareConcurrency || 2) >= 4 && (navigator.deviceMemory || 8) >= 4 ? 2 : 1;
  let pronto = null;               // Promise<worker[]>
  const progresso = new Array(nWorkers).fill(0);
  let aoCarregar = null;           // callback de progresso enquanto carrega

  const script = src => new Promise((ok, erro) => {
    if (window.Tesseract) return ok();
    const s = document.createElement('script'); s.src = src; s.onload = ok; s.onerror = () => erro(new Error('script'));
    document.head.appendChild(s);
  });

  const carregar = () => {
    if (pronto) return pronto;
    mostrar('carregando', 'Carregando o leitor de cartaz…', 0);
    pronto = (async () => {
      await script(base + 'tesseract.min.js');
      const etapas = { 'loading tesseract core': 0, 'initializing tesseract': 0.35, 'loading language traineddata': 0.4, 'initializing api': 0.85 };
      const lista = await Promise.all(Array.from({ length: nWorkers }, (_, i) => window.Tesseract.createWorker('por', 1, {
        workerPath: base + 'worker.min.js', corePath: base + 'core', langPath: base + 'lang', workerBlobURL: false,
        logger: m => {
          if (m.status === 'recognizing text') { if (leitura) leitura(i, m.progress); return; }
          if (!(m.status in etapas)) return;
          const inicio = etapas[m.status], fim = m.status === 'initializing api' ? 1 : m.status === 'loading tesseract core' ? 0.35 : m.status === 'initializing tesseract' ? 0.4 : 0.85;
          progresso[i] = Math.max(progresso[i], inicio + (fim - inicio) * (m.progress || 0));
          if (aoCarregar) aoCarregar(progresso.reduce((a, b) => a + b, 0) / nWorkers * 100);
        },
      })));
      // Cada worker fica fixo num modo de leitura (com 1 worker, o modo é trocado a cada leitura).
      await lista[0].setParameters({ tessedit_pageseg_mode: '3' });
      if (lista[1]) await lista[1].setParameters({ tessedit_pageseg_mode: '11' });
      return lista;
    })();
    aoCarregar = p => mostrar('carregando', 'Carregando o leitor de cartaz…', p);
    pronto.then(() => { aoCarregar = null; if (!lendo) mostrar('pronto', 'Leitor de cartaz pronto — escolha a imagem do cartaz.'); })
      .catch(() => { aoCarregar = null; pronto = null; barra.hidden = true; });
    return pronto;
  };

  // ---- preparação da imagem (igual ao OcrImagem::preparar do servidor) ----
  const canvas = (w, h) => { const c = document.createElement('canvas'); c.width = w; c.height = h; return c; };
  const paraBlob = c => new Promise((ok, erro) => c.toBlob(b => (b ? ok(b) : erro(new Error('png'))), 'image/png'));
  const preparar = async arquivo => {
    const bmp = await createImageBitmap(arquivo);
    const w = bmp.width, h = bmp.height;
    if (w < 20 || h < 20 || w * h > MAX_PIXELS) throw new Error('tamanho');
    // Cinza e gama na imagem original (menor): letra amarela/laranja sobre fundo claro não some no cinza.
    const c1 = canvas(w, h), x1 = c1.getContext('2d', { willReadFrequently: true });
    x1.fillStyle = '#fff'; x1.fillRect(0, 0, w, h); x1.drawImage(bmp, 0, 0);
    if (bmp.close) bmp.close();
    const gama = new Uint8ClampedArray(256);
    for (let v = 0; v < 256; v++) gama[v] = Math.round(Math.pow(v / 255, 2.2) * 255);
    const d1 = x1.getImageData(0, 0, w, h), p1 = d1.data;
    for (let i = 0; i < p1.length; i += 4) { const g = gama[(0.299 * p1[i] + 0.587 * p1[i + 1] + 0.114 * p1[i + 2]) | 0]; p1[i] = p1[i + 1] = p1[i + 2] = g; p1[i + 3] = 255; }
    x1.putImageData(d1, 0, 0);
    // Ampliação para ~2200 px de largura (cartaz de rede social chega com ~1080 px e letra pequena), com teto.
    let f = Math.max(1, Math.min(3, LARGURA / w)); f = Math.min(f, Math.sqrt(MAX_PIXELS / (w * h)));
    const nw = Math.round(w * f), nh = Math.round(h * f);
    const c2 = canvas(nw, nh), x2 = c2.getContext('2d', { willReadFrequently: true });
    x2.imageSmoothingEnabled = true; x2.imageSmoothingQuality = 'high'; x2.drawImage(c1, 0, 0, nw, nh);
    const normal = await paraBlob(c2);
    // Negativo com mais contraste: letra clara sobre faixa escura vira letra escura sobre fundo claro.
    const neg = new Uint8ClampedArray(256);
    for (let v = 0; v < 256; v++) neg[v] = Math.round((((255 - v) / 255 - 0.5) * 1.96 + 0.5) * 255);
    const d2 = x2.getImageData(0, 0, nw, nh), p2 = d2.data;
    for (let i = 0; i < p2.length; i += 4) { p2[i] = neg[p2[i]]; p2[i + 1] = neg[p2[i + 1]]; p2[i + 2] = neg[p2[i + 2]]; }
    x2.putImageData(d2, 0, 0);
    return [normal, await paraBlob(c2)];
  };

  // ---- leitura: 4 passadas (normal/negativo × psm 3/11) ----
  const CABECALHO = 'level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext\n';
  let leitura = null, lendo = false;
  const ler = async (workers, imagens) => {
    const partes = [0, 0, 0, 0], atual = [0, 1];
    leitura = (i, p) => { partes[atual[i]] = p; mostrar('carregando', 'Lendo o cartaz…', partes.reduce((a, b) => a + b, 0) / 4 * 100); };
    const passada = async (wi, img, psm, slot) => {
      atual[wi] = slot;
      if (workers.length === 1) await workers[0].setParameters({ tessedit_pageseg_mode: String(psm) });
      const { data } = await workers[wi].recognize(img, {}, { text: false, tsv: true });
      partes[slot] = 1;
      return CABECALHO + (data.tsv || '');
    };
    const [normal, negativo] = imagens;
    let tsvs;
    if (workers.length > 1) {
      const [a, b] = await Promise.all([passada(0, normal, 3, 0), passada(1, normal, 11, 1)]);
      const [c, d] = await Promise.all([passada(0, negativo, 3, 2), passada(1, negativo, 11, 3)]);
      tsvs = [a, b, c, d];
    } else {
      tsvs = [];
      for (const [img, psm, slot] of [[normal, 3, 0], [normal, 11, 1], [negativo, 3, 2], [negativo, 11, 3]]) tsvs.push(await passada(0, img, psm, slot));
    }
    leitura = null;
    return tsvs;
  };

  const enviar = tsvs => {
    form.querySelectorAll('input[name="ocr_tsv[]"]').forEach(i => i.remove());
    (tsvs || []).forEach(t => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'ocr_tsv[]'; i.value = t; form.appendChild(i); });
    // Os campos travados não seriam enviados: libera só o arquivo, no instante do envio.
    input.disabled = false;
    form.submit();
  };

  input.addEventListener('change', async () => {
    const arq = input.files && input.files[0];
    if (!arq) return;
    const prev = form.querySelector('[data-previa]');
    if (prev && arq.type.startsWith('image/')) { prev.src = URL.createObjectURL(arq); prev.hidden = false; }
    lendo = true; travar(true);
    const inicio = performance.now();
    try {
      const workers = await carregar();
      mostrar('carregando', 'Preparando a imagem…', 2);
      const tsvs = await ler(workers, await preparar(arq));
      mostrar('pronto', 'Cartaz lido em ' + Math.max(1, Math.round((performance.now() - inicio) / 1000)) + ' s — montando o relatório…');
      enviar(tsvs);
    } catch (e) {
      // Leitor do navegador falhou (memória, imagem estranha): o servidor tenta ler.
      mostrar('carregando', 'Enviando o cartaz para leitura no servidor…');
      enviar(null);
    }
  });

  // Volta pelo botão "voltar" do navegador (página guardada em cache): destrava a tela.
  window.addEventListener('pageshow', ev => { if (ev.persisted) { lendo = false; travar(false); if (pronto) mostrar('pronto', 'Leitor de cartaz pronto — escolha a imagem do cartaz.'); } });

  // Carrega o leitor quando a caixa de extração está aberta (ou quando é aberta): ao escolher o cartaz, já está pronto.
  const det = form.closest('details');
  const quandoLivre = window.requestIdleCallback || (fn => setTimeout(fn, 300));
  if (!det || det.open) quandoLivre(() => carregar());
  if (det) det.addEventListener('toggle', () => { if (det.open) carregar(); });
  input.addEventListener('focus', () => carregar());
})();
