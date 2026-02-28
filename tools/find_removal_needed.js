const fs=require('fs');const acorn=require('acorn');
const text=fs.readFileSync('public/js/admin.js','utf8');
let lo=0, hi=text.length, ans=-1;
while(lo<=hi){
  const mid=Math.floor((lo+hi)/2);
  const candidate=text.slice(0, text.length-mid);
  try{ acorn.parse(candidate,{ecmaVersion:2024}); ans=mid; hi=mid-1; }
  catch(e){ lo=mid+1; }
}
if(ans===-1) console.log('no removal makes it parse'); else console.log('minimum chars to remove from end to parse:', ans);
const safe=text.slice(0, text.length-ans);
const lines=safe.split('\n');
console.log('last lines of parsable prefix:');
for(let i=Math.max(0, lines.length-10); i<lines.length;i++) console.log(i+1, lines[i]);
