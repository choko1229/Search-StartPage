const key = 'search-startpage-v1';
let state = {};
try { state = JSON.parse(localStorage.getItem(key) || '{}'); } catch { /* Local storage may be disabled. */ }
if (!state || typeof state !== 'object' || Array.isArray(state)) state = {};
export function get(name, fallback) { return state[name] ?? fallback; }
export function set(name, value) {
    state[name] = value;
    try { localStorage.setItem(key, JSON.stringify(state)); }
    catch { window.dispatchEvent(new CustomEvent('storage-unavailable')); }
    window.dispatchEvent(new CustomEvent('data-change', {detail: name}));
}
export function setting(name, fallback) { return get('settings', {})[name] ?? fallback; }
export function setSetting(name, value) { set('settings', {...get('settings', {}), [name]: value}); }
export function snapshot() { return structuredClone(state); }
export function setMany(values) {
    const next = {...state,...values};
    try { localStorage.setItem(key, JSON.stringify(next)); }
    catch(error) {window.dispatchEvent(new CustomEvent('storage-unavailable'));throw error;}
    state = next;
    for (const name of Object.keys(values)) window.dispatchEvent(new CustomEvent('data-change',{detail:name}));
}
window.addEventListener('storage', event => {
    if(event.key!==key && event.key!==null) return;
    let next;
    try {next=JSON.parse(localStorage.getItem(key) || '{}');} catch {return;}
    if(!next || typeof next!=='object' || Array.isArray(next))return;
    const changed=Object.keys({...state,...next}).filter(name=>JSON.stringify(state[name])!==JSON.stringify(next[name]));
    state=next;
    for(const name of changed)window.dispatchEvent(new CustomEvent('data-change',{detail:name}));
});
