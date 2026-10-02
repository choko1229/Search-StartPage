import {collectionMap} from './sync-core.js';
export const syncCollections = ['favorites','favorite-folders','history','providers-web','providers-ai'];
const localSettings = new Set(['syncEnabled','syncHistory','clearSyncedOnLogout','backgroundSelected','backgroundSwitch','backgroundInterval']);
const localSetting=(state,key)=>localSettings.has(key) || key==='backgroundMode' && state.settings?.backgroundMode==='library';
export function syncDocument(state, presets, cloudHistory = {}, checkpoint = state.syncCheckpoint) {
    const settings = Object.fromEntries(Object.entries(state.settings || {}).filter(([key])=>!localSetting(state,key)));
    const document = {settings};
    for (const key of syncCollections) {
        if (key === 'history' && state.settings?.syncHistory !== true) document[key] = structuredClone(cloudHistory);
        else {
            const rows=state[key] ?? (key.startsWith('providers-') ? presets[key.slice(10)] : []);
            document[key] = collectionMap(key.startsWith('providers-') ? rows.map((row,index)=>({...row,sortOrder:index})) : rows);
            if(key==='history' && checkpoint && (state.syncHistoryMergePending || checkpoint.preferences?.historyEnabled===false)) {
                document[key]={...structuredClone(cloudHistory),...document[key]};
            }
        }
    }
    return document;
}
export function syncValues(state, document, checkpoint, time, presets = {web:[],ai:[]}) {
    const values = {settings:{}};
    for (const [key,value] of Object.entries(state.settings || {})) if(localSetting(state,key)) values.settings[key]=value;
    for (const [key,value] of Object.entries(document.settings || {})) if(!localSetting(state,key)) values.settings[key]=value;
    const ownership = {userId:checkpoint.userId,settings:Object.keys(checkpoint.document.settings || {}).filter(key=>!localSetting(state,key)),collections:{}};
    for (const key of syncCollections) {
        if (key === 'history' && state.settings?.syncHistory !== true) {
            if(String(state.syncOwnership?.userId)===String(checkpoint.userId) && Array.isArray(state.syncOwnership?.collections?.history)) {
                const remaining=new Set((state.history || []).map(row=>row.id));
                ownership.collections.history=state.syncOwnership.collections.history.filter(id=>remaining.has(id));
            }
            continue;
        }
        values[key] = Object.hasOwn(document,key) ? Object.values(document[key]) : (key.startsWith('providers-') ? structuredClone(presets[key.slice(10)]) : []);
        if (key === 'history') values[key].sort((a,b)=>b.at-a.at);
        if (key.startsWith('providers-')) values[key].sort((a,b)=>(a.sortOrder || 0)-(b.sortOrder || 0));
        ownership.collections[key] = Object.keys(checkpoint.document[key] || {});
    }
    return {...values,...(state.settings?.syncHistory===true?{syncHistoryMergePending:false}:{}),syncCheckpoint:checkpoint,syncOwnership:ownership,syncStatus:{state:'synced',lastSync:time}};
}
