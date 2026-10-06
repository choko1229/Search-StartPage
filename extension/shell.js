const localeKey='search-extension-locale';
const pages={ja:'newtab.html',en:'newtab-en.html'};
let preferred;
try {preferred=localStorage.getItem(localeKey);}catch {}
if(!Object.hasOwn(pages,preferred))preferred=navigator.language.toLowerCase().startsWith('ja')?'ja':'en';
const current=document.documentElement.lang;
if(preferred!==current)location.replace(pages[preferred]+location.hash);
const select=document.getElementById('extension-locale');
select.value=current;
select.addEventListener('change',()=>{
    if(!Object.hasOwn(pages,select.value))return;
    try {localStorage.setItem(localeKey,select.value);}catch {}
    location.replace(pages[select.value]+location.hash);
});
