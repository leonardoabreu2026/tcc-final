const puppeteer=require('puppeteer-core'); const fs=require('fs');
const [,, dir, escala] = process.argv;
(async()=>{ const b=await puppeteer.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:'new',args:['--no-first-run']}); const p=await b.newPage();
 for (const f of fs.readdirSync(dir).filter(f=>f.endsWith('.svg') && (process.argv[4]? f.startsWith(process.argv[4]) : true))) {
  const svg=fs.readFileSync(dir+'/'+f,'utf8'); const m=svg.match(/width="(\d+)" height="(\d+)"/); const w=+m[1], h=+m[2];
  await p.setViewport({width:w,height:h,deviceScaleFactor:+escala||1}); await p.setContent('<html><body style="margin:0">'+svg+'</body></html>');
  const el=await p.$('svg'); await el.screenshot({path:dir+'/'+f.replace('.svg','.png')}); console.log(f,w,h);
 } await b.close(); })().catch(e=>{console.error(e);process.exit(1)});
