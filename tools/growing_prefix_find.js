const fs=require('fs');const acorn=require('acorn');
const text=fs.readFileSync('public/js/admin.js','utf8');
let step=1000;let pos=0;let failPos=-1;
while(pos<text.length){
  pos+=step;
  if(pos>text.length) pos=text.length;
  try{ acorn.parse(text.slice(0,pos),{ecmaVersion:2024}); }
  catch(e){ failPos=pos; break; }
}
if(failPos===-1) console.log('no failure up to end'); else console.log('first failure around char index', failPos);
const start=Math.max(0, failPos-200);
const end=Math.min(text.length, failPos+200);
console.log(text.slice(start,end));
console.log('approx line', text.slice(0,failPos).split('\n').length);
