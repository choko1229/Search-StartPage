import {get, set, setting,flush} from './store.js';
import {safeUrl} from './search-core.js';
import {normalizeFavorite, shortcutKey, favoriteShortcuts} from './favorites-core.js';
export const favorites = () => {const rows = get('favorites', []); return (Array.isArray(rows) ? rows : []).filter(item => item && typeof item.name === 'string' && safeUrl(item.url));};
export const folders = () => {const rows = get('favorite-folders', []); return Array.isArray(rows) ? rows : [];};
export function saveFavorite(value, id) {
    const items = favorites();
    const favorite = normalizeFavorite(value, items.find(item => item.id === id));
    if (favorite.folderId && !folders().some(folder => folder.id === favorite.folderId)) throw new Error('invalid_folder');
    if (favorite.shortcut) {
        const key = shortcutKey(favorite.shortcut);
        if (['control+k', 'alt+enter', 'shift+enter'].includes(key)
            || (favoriteShortcuts(items).has(key) && favoriteShortcuts(items).get(key).id !== id)) throw new Error('shortcut_conflict');
    }
    set('favorites', id ? items.map(item => item.id === id ? favorite : item) : [...items, favorite]);
    return favorite;
}
export function patchFavorite(id, values) {set('favorites', favorites().map(item => item.id === id ? {...item, ...values, updatedAt: Date.now()} : item));}
export function deleteFavorite(id) {set('favorites', favorites().filter(item => item.id !== id));}
export function duplicateFavorite(id, suffix) {
    const original = favorites().find(item => item.id === id);
    if (original) saveFavorite({...original, name: (original.name + suffix).slice(0,100), shortcut:'',tags:original.tags.join(',')});
}
export async function openFavorite(item) {
    if (setting('favoriteStats', true)) patchFavorite(item.id, {usageCount:(item.usageCount || 0)+1,lastAccess:Date.now()});
    try {await flush();}catch {window.dispatchEvent(new CustomEvent('storage-unavailable'));}
    window.location.assign(item.url);
}
export function saveFolder(name, id) {
    name = name.trim(); if (!name || name.length > 100) throw new Error('invalid_folder');
    const items = folders();
    if (items.some(item => item.id !== id && item.name.toLocaleLowerCase() === name.toLocaleLowerCase())) throw new Error('folder_conflict');
    const folder = {id: id || crypto.randomUUID(),name,sortOrder:items.find(item => item.id === id)?.sortOrder ?? items.length};
    set('favorite-folders', id ? items.map(item => item.id === id ? folder : item) : [...items,folder]);
}
export function deleteFolder(id) {
    set('favorites', favorites().map(item => item.folderId === id ? {...item,folderId:null} : item));
    set('favorite-folders', folders().filter(item => item.id !== id));
}
export function reorder(entity, source, destination) {
    const items = entity === 'favorites' ? favorites() : folders();
    items.sort((a,b)=>(a.sortOrder || 0)-(b.sortOrder || 0));
    const moving = items.find(item => item.id === source);
    if (!moving || source === destination) return;
    const remaining = items.filter(item => item.id !== source);
    const target = remaining.findIndex(item => item.id === destination);
    if (target < 0) return;
    remaining.splice(target,0,moving);
    set(entity,remaining.map((item,index)=>({...item,sortOrder:index})));
}
