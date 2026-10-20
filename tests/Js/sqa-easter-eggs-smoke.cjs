const {readFileSync} = require('fs');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const source = readFileSync('public/js/sqa-easter-eggs.js', 'utf8');
const scripts=[];
function fixture(path, flags) {
  let keyListener=null, clicked=0, last=[];
  const grenade={hidden:true};
  const zeus={hidden:true};
  const konami={hidden:true,querySelector:()=>({addEventListener(){},focus(){}}),addEventListener(){}};
  const eggRoot={dataset:{grenade: flags.g?'1':'0', nvg: flags.n?'1':'0', zeus:'0',konami:'0'},querySelector(q){return {'.sqa-egg-grenade':grenade,'.sqa-egg-zeus':zeus,'.sqa-egg-konami':konami}[q];}};
  const title={childElementCount:0, textContent:path==='/'?'Comunidad de simulación militar':'Eventos',replaceChildren(){this.parts=[];},appendChild(n){this.parts.push(n);}};
  const document={documentElement:{classList:{contains:()=>false,toggle(){},remove(){}}}, querySelector:q=>q==='[data-sqa-eggs]'?eggRoot:(q==='.hero-kicker'&&path==='/')||(q==='.events-hero h1'&&path==='/eventos')?title:null,
   querySelectorAll:()=>title.parts?.filter(x=>x.attrs?.['data-sqa-nvg-trigger']!==undefined)||[],createTextNode:x=>({textContent:x}),createElement:()=>({attrs:{},setAttribute(k,v){this.attrs[k]=v},addEventListener(){}}),addEventListener:(name,fn)=>{if(name==='keydown')keyListener=fn}};
  const sessionStore=new Map();
  vm.runInNewContext(source,{document,window:{},location:{pathname:path},sessionStorage:{getItem:k=>sessionStore.get(k),setItem:(k,v)=>sessionStore.set(k,v),removeItem:k=>sessionStore.delete(k)},setTimeout(fn){last.push(fn);return last.length},clearTimeout(){},Date,Audio:function(){},console}, {timeout:1000});
  function press(key,target={closest:()=>null}) {keyListener({key, target, defaultPrevented:false, repeat:false, altKey:false,ctrlKey:false,metaKey:false});}
  return {press,grenade,title};
}
for (const allowed of ['/', '/eventos','/area/foro','/actividades','/usuarios','/comunidad/organigrama','/metopas','/tesoreria']) {
 const x=fixture(allowed,{g:true});x.press('g');assert.equal(x.grenade.hidden,false,allowed);
}
for (const forbidden of ['/eventos/67','/usuarios/Pepe','/admin','/area/foro/categoria']) {
 const x=fixture(forbidden,{g:false});x.press('g');assert.equal(x.grenade.hidden,true,forbidden);
}
const x=fixture('/',{g:false,n:true});x.press('n');assert.equal(x.grenade.hidden,true);assert.equal(x.title.parts.filter(p=>p.attrs&&p.attrs['data-sqa-nvg-trigger']!==undefined).length,2);
const y=fixture('/eventos',{n:true});assert.equal(y.title.parts.filter(p=>p.attrs&&p.attrs['data-sqa-nvg-trigger']!==undefined).length,1);
console.log('Easter egg JavaScript smoke: OK');
