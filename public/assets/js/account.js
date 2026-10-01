import {localUsage, removeSyncedData} from './account-data.js';
import {snapshot,setMany,saveSettings} from './store.js';
let state=snapshot();
const logout = document.getElementById('logout-data');
if (logout && state.settings?.clearSyncedOnLogout !== false) {
    try {
        await setMany(latest=>{
            const clean=removeSyncedData(latest,logout.dataset.userId);
            return {...Object.fromEntries(Object.keys(latest).map(name=>[name,null])),...clean};
        });
        state=snapshot();
        logout.hidden = true;
    } catch { logout.hidden = false; }
}
const usage = localUsage(state);
const bytes = document.getElementById('account-local-bytes');
if (bytes) bytes.textContent = `${usage.bytes.toLocaleString()} B`;
const background = document.getElementById('account-background-bytes');
if (background) background.textContent = `${usage.backgroundBytes.toLocaleString()} B`;
const lastSync = document.getElementById('account-last-sync');
const syncStatus = document.getElementById('account-sync-status');
const ownsSync = syncStatus && String(state.syncOwnership?.userId) === syncStatus.dataset.userId;
if (lastSync && ownsSync && usage.lastSync && Number.isFinite(Date.parse(usage.lastSync))) lastSync.textContent = new Date(usage.lastSync).toLocaleString();
if(syncStatus)syncStatus.textContent=state.settings?.syncEnabled===false ? syncStatus.dataset.disabled
    : ownsSync && state.syncStatus?.state==='synced' ? syncStatus.dataset.synced
    : ownsSync && state.syncStatus?.state==='failed' ? syncStatus.dataset.failed : syncStatus.dataset.ready;
const clear = document.getElementById('clear-synced-on-logout');
if (clear) {
    clear.checked = state.settings?.clearSyncedOnLogout !== false;
    clear.addEventListener('change', async() => {
        try {
            const checked=clear.checked;
            await saveSettings({clearSyncedOnLogout:checked},'clearSyncedOnLogout');
        } catch { clear.checked = !clear.checked; }
    });
}
