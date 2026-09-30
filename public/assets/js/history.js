import {get, set, setting} from './store.js';
import {boundedInteger} from './search-preferences.js';
export const historyLimit = () => boundedInteger(setting('historyLimit',300),300,1,10000);
export function history() {
    const rows = get('history', []);
    const retention = boundedInteger(setting('historyDays',90),90,1,3650)*86400000;
    return (Array.isArray(rows) ? rows : []).filter(row => row && typeof row.query === 'string' && Number.isFinite(row.at) && row.at > Date.now() - retention).slice(0, historyLimit());
}
export function record(query, provider, mode) {
    if (!setting('saveHistory', true)) return;
    set('history', [{id: crypto.randomUUID(), query, provider, mode, at: Date.now()}, ...history()].slice(0, historyLimit()));
}
export function removeHistory(id) { set('history', history().filter(row => row.id !== id)); }
export function clearHistory() { set('history', []); }
// Physically remove expired rows as well as omitting them from the UI.
set('history', history());
