const fs=require('fs');
const text=fs.readFileSync('public/js/admin.js','utf8');
let inBack=false; let positions=[];
for(let i=0;i<text.length;i++){
  const ch=text[i];
  const prev=text[i-1];
  if(ch==='`' && prev!=='\\'){
    inBack=!inBack;
    positions.push({pos:i,open:inBack});
  }
}
console.log('backtick toggles:', positions.length);
if(positions.length>0){
  const last=positions[positions.length-1];
  console.log('last backtick at', last.pos, 'open state after toggle:', last.open);
  const head=text.slice(0,last.pos+1);
  console.log('approx line', head.split('\n').length);
}
if(inBack) console.log('There is an unclosed backtick'); else console.log('Backticks balanced');
