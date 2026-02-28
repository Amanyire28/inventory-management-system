const fs=require('fs');
const t=fs.readFileSync('public/js/admin.js','utf8');
console.log('backticks', (t.match(/`/g)||[]).length);
console.log('single', (t.match(/'/g)||[]).length);
console.log('double', (t.match(/"/g)||[]).length);
