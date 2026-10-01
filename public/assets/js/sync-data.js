import {collectionMap} from './sync-core.js';
export const syncCollections = ['favorites','favorite-folders','history','providers-web','providers-ai'];
const localSettings = new Set(['syncEnabled','syncHistory','clearSyncedOnLogout']);
export function syncDocument(state, presets, cloudHistory = {}) {
    const settings = Object.fromEntries(Object.entries(state.settings || {}).filter(([key])=>!localSettings.has(key)));
    const document = {settings};
    for (const key of syncCollections) {
        if (key === 'history' && state.settings?.syncHistory !== true) document[key] = structuredClone(cloudHistory);
        else {
            const rows=state[key] ?? (key.startsWith('providers-') ? presets[key.slice(10)] : []);
            document[key] = collectionMap(key.startsWith('providers-') ? rows.map((row,index)=>({...row,sortOrder:index})) : rows);
        }
    }
    return document;
}
export function syncValues(state, document, checkpoint, time, presets = {web:[],ai:[]}) {
    const values = {settings:{}};
    for (const [key,value] of Object.entries(state.settings || {})) if(localSettings.has(key)) values.settings[key]=value;
    for (const [key,value] of Object.entries(document.settings || {})) if(!localSettings.has(key)) values.settings[key]=value;
    const ownership = {userId:checkpoint.userId,settings:Object.keys(checkpoint.document.settings || {}).filter(key=>!localSettings.has(key)),collections:{}};
    for (const key of syncCollections) {
        if (key === 'history' && state.settings?.syncHistory !== true) continue;
        values[key] = Object.hasOwn(document,key) ? Object.values(document[key]) : (key.startsWith('providers-') ? structuredClone(presets[key.slice(10)]) : []);
        if (key === 'history') values[key].sort((a,b)=>b.at-a.at);
        if (key.startsWith('providers-')) values[key].sort((a,b)=>(a.sortOrder || 0)-(b.sortOrder || 0));
        ownership.collections[key] = Object.keys(checkpoint.document[key] || {});
    }
    return {...values,syncCheckpoint:checkpoint,syncOwnership:ownership,syncStatus:{state:'synced',lastSync:time}};
}
