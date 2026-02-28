const fs=require('fs');
const acorn=require('acorn');
const text=fs.readFileSync('public/js/admin.js','utf8');
const lines=text.split('\n');
function check(n){
  const prefix=lines.slice(0,n).join('\n');
  try{ acorn.parse(prefix, {ecmaVersion:2024}); return {ok:true}; }
  catch(e){ return {ok:false,err:e}; }
}
let lo=1,hi=lines.length,ans=hi;
while(lo<=hi){
  const mid=Math.floor((lo+hi)/2);
  const res=check(mid);
  if(!res.ok){ ans=mid; hi=mid-1; } else lo=mid+1;
}
console.log('first failing line approx:', ans);
const res=check(ans);
if(!res.ok){ console.error(res.err.message); if(res.err.loc) console.error('loc',res.err.loc); }
const start=Math.max(1,ans-10), end=Math.min(lines.length, ans+10);
for(let i=start;i<=end;i++) console.log(i, lines[i-1]);
