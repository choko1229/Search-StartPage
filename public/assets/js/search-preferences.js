import {detectUrl, safeUrl} from './search-core.js';
export const defaultKeys = {webKey:'Shift+Enter',aiKey:'Alt+Enter',historyKey:'Control+Shift+H'};
export function keySignature(value) {
    const parts=String(value).toLowerCase().split('+');
    const key=parts.pop();
    return [...new Set(parts)].sort().concat(key).join('+');
}
export function validShortcut(value) {
    const modifiers=String(value).toLowerCase().split('+').slice(0,-1);
    return /^(?:(?:Control|Alt|Shift)\+){1,3}(?:Enter|[a-z0-9])$/i.test(value)
        && new Set(modifiers).size===modifiers.length
        && !['control+k','alt+1','alt+2','alt+3','alt+4','alt+5','alt+6','alt+7','alt+8','alt+9'].includes(keySignature(value));
}
export function matchesShortcut(event,value) {
    if(event.metaKey || event.isComposing) return false;
    return keySignature([event.ctrlKey?'Control':'',event.altKey?'Alt':'',event.shiftKey?'Shift':'',event.key].filter(Boolean).join('+'))===keySignature(value);
}
export function directUrl(query,policy) {
    if(policy==='auto') return detectUrl(query);
    if(policy==='full' && /^https?:\/\//i.test(query.trim())) return safeUrl(query.trim());
    return null;
}
export function boundedInteger(value,fallback,min,max) {
    const number=Number(value);
    return Number.isInteger(number) && number>=min && number<=max ? number : fallback;
}
export function orderProviders(items,order,stats={}) {
    if(order==='usage') return [...items].sort((a,b)=>(stats[b.id]?.count||0)-(stats[a.id]?.count||0));
    if(order==='recent') return [...items].sort((a,b)=>(stats[b.id]?.last||0)-(stats[a.id]?.last||0));
    return items;
}
