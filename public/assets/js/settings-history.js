const equal=(a,b)=>JSON.stringify(a)===JSON.stringify(b);
const value=(settings,key)=>({present:Object.hasOwn(settings,key),value:structuredClone(settings[key]??null)});
export function recordSettings(history,settings,patch,label,time=new Date().toISOString()) {
    const keys=Object.keys(patch).filter(key=>!Object.hasOwn(settings,key) || !equal(settings[key],patch[key]));
    if(!keys.length)return history || {entries:[],cursor:0};
    const entries=(history?.entries || []).slice(0,history?.cursor ?? 0);
    entries.push({time,label,changes:keys.map(key=>({key,previous:value(settings,key),next:{present:true,value:structuredClone(patch[key])}}))});
    const kept=entries.slice(-20);return {entries:kept,cursor:kept.length};
}
export function resetSettings(history,settings,keys,label,time=new Date().toISOString()) {
    const changes=keys.filter(key=>Object.hasOwn(settings,key)).map(key=>({key,previous:value(settings,key),next:{present:false,value:null}}));
    if(!changes.length)return {settings,history:history || {entries:[],cursor:0}};
    const entries=[...(history?.entries || []).slice(0,history?.cursor ?? 0),{time,label,changes}].slice(-20);
    const next={...settings};for(const {key} of changes)delete next[key];
    return {settings:next,history:{entries,cursor:entries.length}};
}
export function travelSettings(history,settings,direction) {
    const entries=history?.entries || [],cursor=history?.cursor || 0;
    const index=direction==='undo'?cursor-1:cursor;
    if(index<0 || index>=entries.length)return {settings,history};
    const next={...settings};
    for(const change of entries[index].changes) {
        const target=direction==='undo'?change.previous:change.next;
        if(target.present)next[change.key]=structuredClone(target.value);else delete next[change.key];
    }
    return {settings:next,history:{entries,cursor:cursor+(direction==='undo'?-1:1)}};
}
