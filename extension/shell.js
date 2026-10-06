import {preferredLocale,localePage} from './locale-core.js';
const localeKey='search-extension-locale';
let saved;
try {saved=localStorage.getItem(localeKey);}catch {}
const preferred=preferredLocale(saved,navigator.language,new URL(location.href).searchParams.get('locale'));
const current=document.documentElement.lang;
if(preferred!==current)location.replace(localePage(preferred,location.hash));
const select=document.getElementById('extension-locale');
select.value=current;
select.addEventListener('change',()=>{
    if(!['ja','en'].includes(select.value))return;
    try {localStorage.setItem(localeKey,select.value);}catch {}
    location.replace(localePage(select.value,location.hash));
});
