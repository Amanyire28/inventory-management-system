const fs=require('fs');const acorn=require('acorn');
const text=fs.readFileSync('public/js/admin.js','utf8');
const pos=1000;
try{ acorn.parse(text.slice(0,pos),{ecmaVersion:2024}); console.log('parsed ok'); }
catch(e){ console.error('error message:', e.message); if(e.loc) console.error('loc', e.loc); const head=text.slice(Math.max(0,pos-200), pos+50); console.error('context:\n', head); }
