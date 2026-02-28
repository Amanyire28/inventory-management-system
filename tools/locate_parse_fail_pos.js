const fs=require('fs');const acorn=require('acorn');
const text=fs.readFileSync('public/js/admin.js','utf8');
let lo=0, hi=text.length, lastOk=0;
while(lo<=hi){
  const mid=Math.floor((lo+hi)/2);
  try{ acorn.parse(text.slice(0,mid), {ecmaVersion:2024}); lastOk=mid; lo=mid+1; }
  catch(e){ hi=mid-1; }
}
console.log('lastOk index', lastOk);
const head=text.slice(0,lastOk);
console.log('approx line', head.split('\n').length);
console.log('context around lastOk:');
const start=Math.max(0,lastOk-200);
const end=Math.min(text.length, lastOk+200);
console.log(text.slice(start,end));
