const fs=require('fs');
const text=fs.readFileSync('public/js/admin.js','utf8');
const slice=text.slice(0,400);
for(let i=0;i<slice.length;i++){
  const ch=slice[i];
  const code=ch.charCodeAt(0);
  process.stdout.write(`${i}:${code} `);
  if((i+1)%20===0) process.stdout.write('\n');
}
console.log('\n---\n');
console.log(slice);
