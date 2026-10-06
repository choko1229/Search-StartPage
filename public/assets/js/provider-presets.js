import {validProvider} from './search-core.js';
export function clientPresets(value) {
    if(!value||typeof value!=='object'||Array.isArray(value)||Object.keys(value).some(key=>!['web','ai'].includes(key)))throw new Error('PROVIDER_PRESETS_INVALID');
    const result={},ids=new Set(),prefixes=new Set();
    for(const mode of ['web','ai']){
        if(!Array.isArray(value[mode])||value[mode].length<1||value[mode].length>50)throw new Error('PROVIDER_PRESETS_INVALID');
        result[mode]=value[mode].map(row=>{
            if(!validProvider(row)||typeof row.id!=='string'||!/^[A-Za-z0-9_-]{1,80}$/.test(row.id)
                ||typeof row.enabled!=='boolean'||typeof row.copy!=='boolean'||typeof row.icon!=='string'||[...row.icon].length>4
                ||!Number.isSafeInteger(row.sortOrder)||row.sortOrder<0||row.sortOrder>9999||ids.has(row.id)||prefixes.has(row.prefix.toLowerCase()))throw new Error('PROVIDER_PRESETS_INVALID');
            ids.add(row.id);prefixes.add(row.prefix.toLowerCase());
            return {id:row.id,name:row.name,url:row.url,prefix:row.prefix,icon:row.icon,enabled:row.enabled,copy:row.copy,sortOrder:row.sortOrder};
        });
        if(!result[mode].some(row=>row.enabled))throw new Error('PROVIDER_PRESETS_INVALID');
    }
    return result;
}
export function appendPreset(items, preset, allProviders) {
    if(!validProvider(preset)||allProviders.some(row=>row.id===preset.id||row.prefix.toLowerCase()===preset.prefix.toLowerCase()))throw new Error('invalid_provider');
    return [...items,{...structuredClone(preset),enabled:true,sortOrder:items.length}];
}
