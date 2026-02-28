const fs=require('fs');
const text=fs.readFileSync('public/js/admin.js','utf8');
const tail=text.slice(-200);
console.log('tail length', tail.length);
for(let i=0;i<tail.length;i++){
  const ch=tail[i];
  process.stdout.write(`${i}:${ch.charCodeAt(0)} `);
  if((i+1)%20===0) process.stdout.write('\n');
}
console.log('\n---TAIL---\n', JSON.stringify(tail));
