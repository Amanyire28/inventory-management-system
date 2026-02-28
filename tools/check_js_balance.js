const fs = require('fs');
const path = process.argv[2];
if (!path) { console.error('Usage: node check_js_balance.js <file>'); process.exit(1); }
const text = fs.readFileSync(path, 'utf8');
let stack = [];
const pairs = { '{': '}', '(': ')', '[': ']', '`': '`', '"': '"', "'": "'" };
const opens = new Set(['{','(','[','`','"',"'"]);
const closes = new Set(['}',')',']','`','"',"'"]);
let inSingle = false, inDouble=false, inBack=false;
for (let i=0;i<text.length;i++){
  const ch = text[i];
  const prev = text[i-1];
  // handle escapes in quotes/backticks
  if ((inSingle || inDouble || inBack) && ch === '\\') { i++; continue; }
  if (!inSingle && !inDouble && !inBack && ch === "'") { stack.push({ch, i}); inSingle=true; continue; }
  if (inSingle && ch === "'" ) { stack.pop(); inSingle=false; continue; }
  if (!inSingle && !inDouble && !inBack && ch === '"') { stack.push({ch,i}); inDouble=true; continue; }
  if (inDouble && ch === '"') { stack.pop(); inDouble=false; continue; }
  if (!inSingle && !inDouble && !inBack && ch === '`') { stack.push({ch,i}); inBack=true; continue; }
  // inside backtick templates we must still track ${...} expressions
  if (inBack) {
    if (ch === '$' && text[i+1] === '{') { stack.push({ch:'${', i}); i++; continue; }
    if (ch === '`') { // closing backtick
      // if there is an unclosed ${ inside template, do not pop backtick
      const top = stack[stack.length-1];
      if (!top || top.ch === '`') {
        if (top && top.ch === '`') stack.pop();
        inBack = false;
        continue;
      } else {
        // there are still ${...} unclosed
        // allow backtick close but keep markers to report
        stack.push({ch:'`', i});
        inBack = false;
        continue;
      }
    }
    // other content inside backtick - continue scanning for ${ or `
    continue;
  }
  if (ch === '{' || ch === '(' || ch === '[') { stack.push({ch,i}); }
  if (ch === '}' || ch === ')' || ch === ']') {
    if (stack.length === 0) { console.log('Unmatched closing', ch, 'at', i); process.exit(0); }
    const top = stack[stack.length-1].ch;
    const expected = ({'{':'}','(':')','[':']'})[top];
    if (expected === ch) { stack.pop(); } else { console.log('Mismatched at', i, 'found', ch, 'expected', expected); process.exit(0); }
  }
}
if (stack.length===0) { console.log('All balanced'); process.exit(0); }
console.log('Unclosed tokens (top first):');
stack.reverse().forEach(s => {
  const head = text.slice(0, s.i);
  const line = head.split('\n').length;
  const col = s.i - head.lastIndexOf('\n');
  console.log(`${s.ch} at index ${s.i} -> line ${line}, col ${col}`);
});
process.exit(0);
