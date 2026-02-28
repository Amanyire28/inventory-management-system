const fs = require('fs');
const acorn = require('acorn');
const text = fs.readFileSync('public/js/admin.js','utf8');
try{
  acorn.parse(text, {ecmaVersion:2024, sourceType:'script'});
  console.log('Parsed OK');
}catch(e){
  console.error('Acorn error:', e.message);
  if (e.loc) console.error('Line', e.loc.line, 'Column', e.loc.column);
  // show surrounding lines
  const lines = text.split('\n');
  const line = e.loc ? e.loc.line : Math.max(1, lines.length);
  const start = Math.max(1, line-6);
  const end = Math.min(lines.length, line+6);
  for(let i=start;i<=end;i++) console.error(i, lines[i-1]);
}
