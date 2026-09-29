import {localUsage, removeSyncedData} from './account-data.js';
const key = 'search-startpage-v1';
let state = {};
try { state = JSON.parse(localStorage.getItem(key) || '{}'); } catch {}
if (!state || typeof state !== 'object' || Array.isArray(state)) state = {};
const logout = document.getElementById('logout-data');
if (logout && state.settings?.clearSyncedOnLogout !== false) {
    try {
        localStorage.setItem(key, JSON.stringify(removeSyncedData(state, logout.dataset.userId)));
        logout.hidden = true;
    } catch { logout.hidden = false; }
}
const usage = localUsage(state);
const bytes = document.getElementById('account-local-bytes');
if (bytes) bytes.textContent = `${usage.bytes.toLocaleString()} B`;
const background = document.getElementById('account-background-bytes');
if (background) background.textContent = `${usage.backgroundBytes.toLocaleString()} B`;
const lastSync = document.getElementById('account-last-sync');
if (lastSync && usage.lastSync && Number.isFinite(Date.parse(usage.lastSync))) lastSync.textContent = new Date(usage.lastSync).toLocaleString();
const clear = document.getElementById('clear-synced-on-logout');
if (clear) {
    clear.checked = state.settings?.clearSyncedOnLogout !== false;
    clear.addEventListener('change', () => {
        try {
            const latest = JSON.parse(localStorage.getItem(key) || '{}');
            latest.settings = {...latest.settings, clearSyncedOnLogout: clear.checked};
            localStorage.setItem(key, JSON.stringify(latest));
        } catch { clear.checked = !clear.checked; }
    });
}
