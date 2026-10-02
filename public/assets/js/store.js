import {openStateDatabase} from './store-database.js';
import {recordSettings} from './settings-history.js';
const key = 'search-startpage-v1';
let state = {};
try { state = JSON.parse(localStorage.getItem(key) || '{}'); } catch { /* Local storage may be disabled. */ }
if (!state || typeof state !== 'object' || Array.isArray(state)) state = {};
let database=null,initialError=null;
try {database=await openStateDatabase(state);}catch(error){initialError=error;}
if(database)state=database.initial;
// Remove the obsolete copy only after the database transaction and readback
// succeeded, so logout cleanup cannot leave a second copy of synced history.
if(database)try {localStorage.removeItem(key);}catch {}
let queue=Promise.resolve(),lastError=null;
const failedWrites=new Map();
const revisions=new Map(),pending=new Map();
const notify=name=>window.dispatchEvent(new CustomEvent('data-change',{detail:name}));
function enqueue(operation) {
    const result=queue.then(operation);
    queue=result.catch(error=>{lastError=error;window.dispatchEvent(new CustomEvent('storage-unavailable'));});
    return result;
}
export async function flush() {
    await queue;
    if(initialError)throw initialError;
    if(!database && lastError)throw lastError;
    if(database && failedWrites.size) {
        const retry=Object.fromEntries(failedWrites);
        await enqueue(async()=>{await database.write(retry);failedWrites.clear();lastError=null;});
    }
    // Failed atomic writes did not modify state; the session can retry them.
    lastError=null;
}
export function get(name, fallback) { return state[name] ?? fallback; }
export function set(name, value) {
    state={...state,[name]:value};revisions.set(name,(revisions.get(name)||0)+1);
    if(database) {
        pending.set(name,(pending.get(name)||0)+1);
        enqueue(async()=>{
            const copy=structuredClone(state[name]);
            try {await database.write({[name]:copy});failedWrites.delete(name);lastError=null;}
            catch(error){failedWrites.set(name,copy);throw error;}
            finally {pending.set(name,pending.get(name)-1);}
        }).catch(()=>{});
    }else {
        try {localStorage.setItem(key,JSON.stringify(state));lastError=null;}
        catch(error) {lastError=error;window.dispatchEvent(new CustomEvent('storage-unavailable'));}
    }
    window.dispatchEvent(new CustomEvent('data-change', {detail: name}));
}
export function setting(name, fallback) { return get('settings', {})[name] ?? fallback; }
export function setSetting(name, value) {
    const settings=get('settings',{});
    if(!['lastMode','webLast','aiLast','favoritesExpanded'].includes(name))set('settingsHistory',recordSettings(get('settingsHistory',null),settings,{[name]:value},name));
    set('settings',{...settings,[name]:value});
}
export function snapshot() { return structuredClone(state); }
export function saveSettings(patch,label,extra={}) {
    return setMany(state=>({settingsHistory:recordSettings(state.settingsHistory,state.settings || {},patch,label),settings:{...state.settings,...patch},...extra}));
}
export async function backgroundFile(id) {
    if(initialError)throw initialError;
    if(!database)return null;
    return database.file(id);
}
export function setMany(values,files=[]) {
    if(initialError)throw initialError;
    if(files.length&&!database)throw new Error('background_files_require_indexeddb');
    if(database)return enqueue(async()=>{
        let patch,before;
        for(let attempt=0;;attempt++) {
            patch=typeof values==='function'?values(snapshot()):values;
            before=new Map(Object.keys(patch).map(name=>[name,revisions.get(name)||0]));
            await database.write(structuredClone(patch),files);lastError=null;
            if(typeof values!=='function' || [...before].every(([name,revision])=>(revisions.get(name)||0)===revision))break;
            if(attempt>=7)throw new Error('storage_changed_during_commit');
        }
        for(const [name,value] of Object.entries(patch))if((revisions.get(name)||0)===before.get(name)){
            state={...state,[name]:value};revisions.set(name,before.get(name)+1);notify(name);
        }
    });
    if(typeof values==='function')values=values(snapshot());
    const next = {...state,...values};
    try { localStorage.setItem(key, JSON.stringify(next)); }
    catch(error) {window.dispatchEvent(new CustomEvent('storage-unavailable'));throw error;}
    state = next;
    for (const name of Object.keys(values)) window.dispatchEvent(new CustomEvent('data-change',{detail:name}));
}
if(database)database.listen(async()=>{
    try {
        const next=await database.read();
        for(const name of Object.keys({...state,...next})){
            if(pending.get(name)>0 || failedWrites.has(name) || JSON.stringify(state[name])===JSON.stringify(next[name]))continue;
            state={...state,[name]:next[name]};revisions.set(name,(revisions.get(name)||0)+1);notify(name);
        }
    }catch {window.dispatchEvent(new CustomEvent('storage-unavailable'));}
});
window.addEventListener('storage', event => {
    if(database)return;
    if(event.key!==key && event.key!==null) return;
    let next;
    try {next=JSON.parse(localStorage.getItem(key) || '{}');} catch {return;}
    if(!next || typeof next!=='object' || Array.isArray(next))return;
    const changed=Object.keys({...state,...next}).filter(name=>JSON.stringify(state[name])!==JSON.stringify(next[name]));
    state=next;
    for(const name of changed)window.dispatchEvent(new CustomEvent('data-change',{detail:name}));
});
