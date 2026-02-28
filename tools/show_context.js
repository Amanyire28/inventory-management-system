const fs = require('fs');
const args = process.argv.slice(2).map(Number);
if (args.length===0) { console.error('Usage: node show_context.js <index> [index2 ...]'); process.exit(1); }
const text = fs.readFileSync('public/js/admin.js','utf8');
for (const idx of args) {
  const start = Math.max(0, idx-120);
  const end = Math.min(text.length, idx+120);
  const head = text.slice(0, start);
  const line = head.split('\n').length;
  const col = start - head.lastIndexOf('\n');
  console.log('\n--- context for index', idx, '-> approx line', line, 'col', col, '---\n');
  console.log(text.slice(start, end));
}
