const {spawnSync} = require('child_process');
const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname,'..');
const cwd = path.dirname(root);
const cache=path.join(process.env.LOCALAPPDATA,'npm-cache','_npx');
const cliPath=fs.readdirSync(cache).map(x=>path.join(cache,x,'node_modules','@playwright','cli','playwright-cli.js')).find(x=>fs.existsSync(x));
if(!cliPath)throw new Error('Run npx --package @playwright/cli playwright-cli --help first.');
function cli(session,command,code) {
  const result=spawnSync(process.execPath,[cliPath,`-s=${session}`,'--raw',command,code],{cwd,encoding:'utf8',timeout:180000,maxBuffer:10*1024*1024});
  if(result.status)throw new Error(result.stderr||result.stdout);
  return JSON.parse(result.stdout.trim());
}
const code = `async page => {
 const errors=[]; const rows=[];
 page.on('pageerror', e => errors.push(e.message));
 await page.setViewportSize({width:1440,height:1000});
 for(const [name,route] of [['home','/'],['catalog','/catalog'],['type','/catalog/type/1'],['product','/catalog/product/1'],['order','/order']]) {
  const response=await page.goto('http://127.0.0.1:8020'+route);
  await page.locator('main').waitFor();
  if(name==='order'){
   await page.setViewportSize({width:1440,height:1200});
   await page.locator('input[name="qty[1]"]').fill('2');
   await page.locator('input[name="qty[3]"]').fill('1');
   await page.locator('[data-delivery]').check();
   await page.waitForFunction(()=>document.querySelector('[data-total]').textContent.replace(/[^0-9]/g,'')==='225500');
   await page.evaluate(()=>window.scrollTo(0,250));
  }
  const details=await page.evaluate(()=>{
   const n=performance.getEntriesByType('navigation')[0];
   return {userAgent:navigator.userAgent,overflow:document.documentElement.scrollWidth>innerWidth,domMs:Math.round(n.domContentLoadedEventEnd),loadMs:Math.round(n.loadEventEnd),documentBytes:n.encodedBodySize,brokenImages:Array.from(document.images).filter(x=>!x.loading&&x.complete&&x.naturalWidth===0).length};
  });
  rows.push({name,status:response.status(),...details});
  await page.screenshot({path:'rgr-furniture/reports/screenshots/'+name+'-BROWSER.png'});
  if(name==='home'||name==='type'||name==='product'){
   const before=await page.locator('[data-slide-count]').first().textContent();
   await page.locator('[data-next]').first().click();
   const after=await page.locator('[data-slide-count]').first().textContent();
   rows[rows.length-1].slideshow=before!==after;
  }
 }
 await page.goto('http://127.0.0.1:8020/language?value=en&return=/');
 await page.screenshot({path:'rgr-furniture/reports/screenshots/english-BROWSER.png'});
 rows.push({name:'english',valid:(await page.locator('h1').textContent()).includes('Home takes')});
 await page.goto('http://127.0.0.1:8020/language?value=ru&return=/');
 await page.setViewportSize({width:390,height:844});
 for(const route of ['/','/catalog','/order']){
  await page.goto('http://127.0.0.1:8020'+route);
  rows.push({name:'mobile '+route,overflow:await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)});
 }
 await page.goto('http://127.0.0.1:8020/');
 await page.screenshot({path:'rgr-furniture/reports/screenshots/mobile-BROWSER.png'});
 await page.setViewportSize({width:1440,height:1000});
 return {rows,errors};
}`;
const reports={};
for(const [session,name] of [['rgr','edge'],['firefox-rgr','firefox']]) {
 reports[name]=cli(session,'run-code',code.replaceAll('BROWSER',name));
 console.log(name, reports[name].rows.length,'checks; errors:',reports[name].errors.length);
}
fs.writeFileSync(path.join(root,'reports','browser-tests.json'),JSON.stringify(reports,null,2));
