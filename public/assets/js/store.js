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
