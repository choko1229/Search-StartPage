import {openStateDatabase} from './store-database.js';
import {recordSettings} from './settings-history.js';
import {storageNamespace} from './api-transport.js';
const namespace=await storageNamespace();
const key = namespace.legacy;
let state = {};
try { state = JSON.parse(localStorage.getItem(key) || '{}'); } catch { /* Local storage may be disabled. */ }
if (!state || typeof state !== 'object' || Array.isArray(state)) state = {};
let database=null,initialError=null;
try {database=await openStateDatabase(state,namespace.database);}catch(error){initialError=error;}
if(database)state=database.initial;
// Remove the obsolete copy only after the database transaction and readback
// succeeded, so logout cleanup cannot leave a second copy of synced history.
if(database)try {localStorage.removeItem(key);}catch {}
let queue=Promise.resolve(),lastError=null;
const failedWrites=new Map();
const optimisticSettings=new Map(),failedSettings=new Map();
const revisions=new Map(),pending=new Map();
const notify=name=>window.dispatchEvent(new CustomEvent('data-change',{detail:name}));
function enqueue(operation) {
    const result=queue.then(operation);
    queue=result.catch(error=>{
        // Conditional conflicts are retried from current data, not storage failures.
        if(error.message==='storage_conflict')return;
        lastError=error;window.dispatchEvent(new CustomEvent('storage-unavailable'));
    });
    return result;
}
export async function flush() {
    await queue;
    if(initialError)throw initialError;
    if(!database && lastError)throw lastError;
    for(const [name,request] of failedSettings)await enqueue(()=>persistSetting(name,request));
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
    if(database){
        const request={value:structuredClone(value)};
        optimisticSettings.set(name,request);failedSettings.delete(name);
        state={...state,settings:{...get('settings',{}),[name]:value}};
        revisions.set('settings',(revisions.get('settings')||0)+1);
        pending.set('settings',(pending.get('settings')||0)+1);
        enqueue(async()=>{
            try {await persistSetting(name,request);}
            catch(error){if(optimisticSettings.get(name)===request)failedSettings.set(name,request);throw error;}
            finally{pending.set('settings',pending.get('settings')-1);}
        }).catch(()=>{});
        notify('settings');return;
    }
    const settings=get('settings',{});
    if(!['lastMode','webLast','aiLast','favoritesExpanded'].includes(name))set('settingsHistory',recordSettings(get('settingsHistory',null),settings,{[name]:value},name));
    set('settings',{...settings,[name]:value});
}
async function persistSetting(name,request){
    const tracked=!['lastMode','webLast','aiLast','favoritesExpanded'].includes(name);
    for(let attempt=0;;attempt++){
        const before=get('settings',{}),historyRevision=revisions.get('settingsHistory')||0;
        const latest=await database.read(),settings={...latest.settings,[name]:request.value};
        const values={settings},expected={settings:latest.settings??null};
        if(tracked){
            values.settingsHistory=recordSettings(latest.settingsHistory,latest.settings||{},{[name]:request.value},name);
            expected.settingsHistory=latest.settingsHistory??null;
        }
        try {await database.write(values,[],expected);}
        catch(error){if(error.message==='storage_conflict'&&attempt<7)continue;throw error;}
        lastError=null;
        if(optimisticSettings.get(name)===request)optimisticSettings.delete(name);
        if(failedSettings.get(name)===request)failedSettings.delete(name);
        // Keep newer edits made while awaiting this transaction, including queued
        // optimistic settings that have not reached IndexedDB yet.
        const current=get('settings',{}),merged={...settings};
        for(const key of Object.keys({...before,...current})){
            if(Object.hasOwn(before,key)===Object.hasOwn(current,key)&&JSON.stringify(before[key])===JSON.stringify(current[key]))continue;
            if(Object.hasOwn(current,key))merged[key]=current[key];else delete merged[key];
        }
        for(const [key,pendingRequest] of optimisticSettings)merged[key]=pendingRequest.value;
        state={...state,settings:merged};revisions.set('settings',(revisions.get('settings')||0)+1);
        const historyChanged=tracked&&(revisions.get('settingsHistory')||0)===historyRevision;
        if(historyChanged){state={...state,settingsHistory:values.settingsHistory};revisions.set('settingsHistory',historyRevision+1);}
        notify('settings');if(historyChanged)notify('settingsHistory');return;
    }
}
export function snapshot() { return structuredClone(state); }
export function saveSettings(patch,label,extra={}) {
    // This transform reads only settings and its undo history. Telemetry and sync
    // status changes must not invalidate a successful settings transaction.
    const values=state=>({settingsHistory:recordSettings(state.settingsHistory,state.settings || {},patch,label),settings:{...state.settings,...patch},...extra});
    if(!database)return setMany(values);
    const guard=state=>({settings:state.settings??null,settingsHistory:state.settingsHistory??null});
    return (async()=>{
        if(failedSettings.size)await flush();
        for(let attempt=0;;attempt++){
            try {return await setMany(values,[],guard,['settings','settingsHistory']);}
            catch(error){
                if(error.message!=='storage_conflict'||attempt>=7)throw error;
                if(failedSettings.size)await flush();
            }
        }
    })();
}
export async function backgroundFile(id) {
    if(initialError)throw initialError;
    if(!database)return null;
    return database.file(id);
}
async function refreshState() {
    const next=await database.read(),changed=[];
    for(const name of Object.keys({...state,...next})){
        if(name==='settings'&&optimisticSettings.size)continue;
        if(pending.get(name)>0||failedWrites.has(name)||JSON.stringify(state[name])===JSON.stringify(next[name]))continue;
        state={...state,[name]:next[name]};revisions.set(name,(revisions.get(name)||0)+1);changed.push(name);
    }
    for(const name of changed)notify(name);
}
// Optional dependencies must include every collection read by the transforms.
// Callers without an explicit list retain conservative whole-state rebasing.
export function setMany(values,files=[],conditions=null,dependencies=null) {
    if(initialError)throw initialError;
    if(!database&&(typeof files==='function'?files(snapshot()):files).length)throw new Error('background_files_require_indexeddb');
    if(database)return enqueue(async()=>{
        let patch,before,committedConditions=null;
        try {
        for(let attempt=0;;attempt++) {
            const inputs=dependencies===null?new Map(revisions):new Map(dependencies.map(name=>[name,revisions.get(name)||0]));
            const current=snapshot();
            patch=typeof values==='function'?values(current):values;
            const operations=typeof files==='function'?files(current):files;
            before=new Map(Object.keys(patch).map(name=>[name,revisions.get(name)||0]));
            const expected=committedConditions??(typeof conditions==='function'?conditions(current):conditions);
            await database.write(structuredClone(patch),operations,expected);lastError=null;
            if(expected)committedConditions=Object.fromEntries(Object.entries(expected).map(([name,value])=>[name,Object.hasOwn(patch,name)?patch[name]:value]));
            const dynamic=typeof values==='function'||typeof files==='function';
            const unchanged=dependencies===null
                ? revisions.size===inputs.size&&[...inputs].every(([name,revision])=>revisions.get(name)===revision)
                : [...inputs].every(([name,revision])=>(revisions.get(name)||0)===revision);
            if(!dynamic || unchanged)break;
            if(attempt>=7)throw new Error('storage_changed_during_commit');
        }
        }catch(error){if(conditions)await refreshState();throw error;}
        const changed=[];
        for(const [name,value] of Object.entries(patch))if((revisions.get(name)||0)===before.get(name)){
            if(name==='settings')for(const [key,request] of optimisticSettings){
                // Successful reset/logout/cloud replacement cancels obsolete failed
                // edits, so a later flush cannot restore explicitly removed data.
                if(!Object.hasOwn(value||{},key)||JSON.stringify(value[key])!==JSON.stringify(request.value)){
                    optimisticSettings.delete(key);failedSettings.delete(key);
                }
            }
            state={...state,[name]:value};revisions.set(name,before.get(name)+1);changed.push(name);
        }
        for(const name of changed)notify(name);
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
        await refreshState();
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
