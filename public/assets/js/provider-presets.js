import {validProvider} from './search-core.js';
export function appendPreset(items, preset, allProviders) {
    if(!validProvider(preset)||allProviders.some(row=>row.id===preset.id||row.prefix.toLowerCase()===preset.prefix.toLowerCase()))throw new Error('invalid_provider');
    return [...items,{...structuredClone(preset),enabled:true,sortOrder:items.length}];
}
