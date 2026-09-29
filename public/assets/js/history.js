import {get, set, setting} from './store.js';
const retention = 90 * 86400000;
export function history() {
    const rows = get('history', []);
    return (Array.isArray(rows) ? rows : []).filter(row => row && typeof row.query === 'string' && Number.isFinite(row.at) && row.at > Date.now() - retention).slice(0, 300);
}
export function record(query, provider, mode) {
    if (!setting('saveHistory', true)) return;
    set('history', [{id: crypto.randomUUID(), query, provider, mode, at: Date.now()}, ...history()].slice(0, 300));
}
export function removeHistory(id) { set('history', history().filter(row => row.id !== id)); }
export function clearHistory() { set('history', []); }
// Physically remove expired rows as well as omitting them from the UI.
set('history', history());
