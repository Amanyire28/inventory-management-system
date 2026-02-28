const fs = require('fs');
const text = fs.readFileSync('public/js/admin.js','utf8');
let inSingle=false,inDouble=false,inBack=false,escaped=false;
let stack=[];
for(let i=0;i<text.length;i++){
  const ch=text[i];
  if(escaped){escaped=false; continue;}
  if(ch==='\\') { escaped=true; continue; }
  if(!inSingle && !inDouble && ch==='`') { inBack=!inBack; continue; }
  if(!inDouble && !inBack && ch==="'") { inSingle=!inSingle; continue; }
  if(!inSingle && !inBack && ch==='"') { inDouble=!inDouble; continue; }
  if(inBack && ch==='$' && text[i+1]==='{'){ stack.push({type:'${',i}); i++; continue; }
  if(inBack) continue; // skip other chars inside backticks
  if(inSingle || inDouble) continue;
  if(ch==='{'){ stack.push({type:'{',i}); }
  if(ch==='}'){
    if(stack.length===0){ console.log('Unmatched } at', i); process.exit(1); }
    const top=stack[stack.length-1];
    if(top.type==='{') stack.pop();
    else if(top.type==='${') stack.pop();
    else { console.log('Mismatched } at', i, 'top', top); process.exit(1); }
  }
}
if(stack.length>0){ console.log('Unclosed tokens:'); stack.forEach(s=>console.log(s.type,'at',s.i)); process.exit(2); }
console.log('Braces balanced'); process.exit(0);
