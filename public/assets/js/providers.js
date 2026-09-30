import {get, set, setting, setSetting} from './store.js';
import {presets} from './i18n.js';
import {validProvider} from './search-core.js';
import {orderProviders} from './search-preferences.js';
export function providers(mode, enabledOnly = true) {
    const saved = get(`providers-${mode}`, null);
    const items = (Array.isArray(saved) ? saved : presets[mode]).filter(validProvider);
    const filtered=items.filter(item => !enabledOnly || item.enabled !== false);
    return mode==='ai' && enabledOnly ? orderProviders(filtered,setting('aiOrder','fixed'),get('providerUsage',{})) : filtered;
}
export function saveProviders(mode, items) { set(`providers-${mode}`, items); }
export function selected(mode) {
    const items = providers(mode);
    const preference = setting(`${mode}Default`, mode === 'web' ? 'google' : 'chatgpt');
    const id = preference === 'last' ? setting(`${mode}Last`, '') : preference;
    return items.find(item => item.id === id) ?? items[0];
}
export function remember(mode, id) { setSetting(`${mode}Last`, id); }
export function recordProvider(id) {
    const stats=get('providerUsage',{});
    set('providerUsage',{...stats,[id]:{count:(stats[id]?.count||0)+1,last:Date.now()}});
}
