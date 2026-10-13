// Gera uc/<nome>.png (2x) para cada diagrama de diagramas.js e avisa sobreposições. Uso: node uc/render.js [nomes...]
const fs = require('fs'), os = require('os'), path = require('path'), vm = require('vm'), { execFileSync } = require('child_process');
const DIR = __dirname.replace(/\\/g, '/');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const DIAGRAMAS = vm.runInNewContext(fs.readFileSync(path.join(DIR, 'diagramas.js'), 'utf8') + '\nDIAGRAMAS');
const nomes = process.argv.slice(2).length ? process.argv.slice(2) : Object.keys(DIAGRAMAS);
const base = ['--headless=new', '--disable-gpu', '--hide-scrollbars', `--user-data-dir=${path.join(os.tmpdir(), 'chrome_casos_de_uso')}`];
for (const n of nomes) {
  const d = DIAGRAMAS[n], url = `file:///${DIR}/desenho.html?d=${n}`;
  const dom = execFileSync(CHROME, [...base, '--dump-dom', url], { maxBuffer: 1e8 }).toString();
  const titulo = (dom.match(/<title>([^<]*)<\/title>/) || [])[1];
  execFileSync(CHROME, [...base, '--force-device-scale-factor=2', `--window-size=${d.w},${d.h}`, `--screenshot=${DIR}/${n}.png`, url], { stdio: 'ignore' });
  console.log(`${n}: ${d.w}x${d.h} ${titulo}`);
}
