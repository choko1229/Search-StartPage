const locales=new Set(['ja','en']);
export function preferredLocale(saved,browserLanguage,explicit=null) {
    if(locales.has(explicit))return explicit;
    if(locales.has(saved))return saved;
    return typeof browserLanguage==='string'&&browserLanguage.toLowerCase().startsWith('ja')?'ja':'en';
}
export function localePage(locale,hash='') {
    if(!locales.has(locale))throw new Error('LOCALE_INVALID');
    return (locale==='ja'?'newtab.html':'newtab-en.html')+'?locale='+locale+(typeof hash==='string'&&hash.startsWith('#')?hash:'');
}
