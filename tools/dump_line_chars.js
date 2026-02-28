const fs=require('fs');
const lines=fs.readFileSync('public/js/admin.js','utf8').split('\n');
const lineNum=32;
const line=lines[lineNum-1];
console.log('Line',lineNum, 'len', line.length);
for(let i=0;i<line.length;i++){
  const ch=line[i];
  console.log(i, ch, ch.charCodeAt(0));
}
console.log('CONTENT:\n', line);
