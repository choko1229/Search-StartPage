import {get, setMany, setting} from './store.js';
import {boundedInteger} from './search-preferences.js';
export const historyLimit = () => boundedInteger(setting('historyLimit',300),300,1,10000);
export function history() {
    const rows = get('history', []);
    const retention = boundedInteger(setting('historyDays',90),90,1,3650)*86400000;
    return (Array.isArray(rows) ? rows : []).filter(row => row && typeof row.query === 'string' && Number.isFinite(row.at) && row.at > Date.now() - retention).slice(0, historyLimit());
}
function retained(rows,settings={}) {
    const cutoff=Date.now()-boundedInteger(settings.historyDays,90,1,3650)*86400000;
    return (Array.isArray(rows)?rows:[]).filter(row=>row&&typeof row.query==='string'&&Number.isFinite(row.at)&&row.at>cutoff)
        .sort((a,b)=>b.at-a.at).slice(0,boundedInteger(settings.historyLimit,300,1,10000));
}
async function change(update) {
    for(let attempt=0;;attempt++){
        try{return await setMany(state=>({history:retained(update(retained(state.history,state.settings),state.settings??{}),state.settings)}),[],state=>({history:state.history??null}));}
        catch(error){if(error.message!=='storage_conflict'||attempt>=7)throw error;}
    }
}
export async function record(query, provider, mode) {
    if (!setting('saveHistory', true)) return;
    const row={id:crypto.randomUUID(),query,provider,mode,at:Date.now()};
    // Commit against the latest history before navigation. Storage failure still permits search.
    try{await change((rows,settings)=>settings.saveHistory===false?rows:[row,...rows]);}catch{}
}
export function removeHistory(id) { return change(rows=>rows.filter(row=>row.id!==id)); }
export function clearHistory() { return change(()=>[]); }
// Physically remove expired rows as well as omitting them from the UI.
void change(rows=>rows).catch(()=>{});
