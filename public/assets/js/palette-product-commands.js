import {get,setting,saveSettings} from './store.js';
import * as store from './store.js';
import {createPaletteStorage} from './palette-storage.js';
import {t} from './i18n.js';
import {favorites,folders,openFavorite} from './favorites-store.js';
import {focusFavorites} from './favorites.js';
import {editFavorite} from './favorite-editor.js';
import {providers} from './providers.js';
import {history} from './history.js';
import {normalizeBackground,backgroundPresets} from './background-core.js';
import {categories} from './settings-schema.js';
import {logoutPaletteAccount,recoverPaletteAccount} from './palette-logout.js';

export function registerProductCommands(registry,actions) {
    const storage=createPaletteStorage(store);
    void recoverPaletteAccount().catch(()=>window.dispatchEvent(new CustomEvent('storage-unavailable')));
    const register=(id,title,category,effect,run,keywords=[],confirmationKey=id)=>registry.register({id,title,category,effect,run,keywords,confirmationKey});
    register('settings:open',t('palette_open_settings'),'commands','navigate',()=>()=>actions.openSettings());
    register('history:open',t('palette_open_history'),'commands','navigate',()=>actions.openHistory);
    register('favorite:add',t('add_favorite'),'commands','navigate',()=>()=>editFavorite());
    register('history:clear',t('clear_history'),'commands','state',storage.clearHistory);
    register('account:login',t('discord_login'),'commands','navigate',()=>()=>location.assign('/account'));
    register('account:logout',t('logout'),'commands','state',async()=>{
        await logoutPaletteAccount();
        return ()=>location.assign('/account');
    });
    for(const theme of ['solar','light','dark','os','forest','rose'])register('theme:'+theme,t('palette_theme')+': '+t('theme_'+theme),'commands','state',()=>saveSettings({theme},'appearance'),[],'theme-change');
    register('background:random',t('palette_random_background'),'commands','state',()=>{
        const candidates=[...backgroundPresets,...get('backgrounds',[])].map(normalizeBackground).filter(row=>row&&!row.deleted);
        const row=candidates[Math.floor(Math.random()*candidates.length)];if(!row)throw new Error('background_invalid');
        return saveSettings({backgroundMode:'library',backgroundSelected:row.id,backgroundSwitch:'manual'},'background');
    },[],'background-change');
    for(const category of categories)register('settings:'+category,t('category_'+category),'settings','navigate',()=>()=>actions.openSettings(category));
    let signature='',dynamic=[];
    return ()=>{
        const data={favorites:favorites(),folders:folders(),web:providers('web'),ai:providers('ai'),history:history(),backgrounds:[...backgroundPresets,...get('backgrounds',[])],customThemes:setting('customThemes',{})};
        const next=JSON.stringify(data);if(next===signature)return;signature=next;for(const dispose of dynamic)dispose();dynamic=[];
        const add=(...args)=>dynamic.push(register(...args));
        for(const item of data.favorites){
            add('favorite:'+item.id,item.name,'favorites','navigate',()=>()=>{const latest=favorites().find(row=>row.id===item.id);if(latest)void openFavorite(latest);},(item.tags||[]).slice(0,20));
            add('favorite-delete:'+item.id,t('delete')+': '+item.name,'commands','state',()=>storage.deleteFavorite(item.id),[],'favorite-delete');
        }
        for(const item of data.folders)add('folder:'+item.id,item.name,'folders','navigate',()=>()=>focusFavorites({folder:item.id}));
        const tags=[...new Set(data.favorites.flatMap(item=>item.tags||[]))];for(const tag of tags)add('tag:'+Array.from(tag).map(value=>value.codePointAt(0).toString(16).padStart(6,'0')).join(''),tag,'tags','navigate',()=>()=>focusFavorites({tag}));
        for(const mode of ['web','ai'])for(const item of data[mode])add('provider:'+mode+':'+item.id,item.name,mode==='web'?'search':'ai','state',async()=>{await saveSettings({[mode==='web'?'webDefault':'aiDefault']:item.id},mode==='web'?'search':'ai');actions.chooseProvider(mode,item.id);},item.prefix?[item.prefix]:[],mode==='web'?'search-engine-change':'ai-change');
        for(const item of data.history)if(item.query.trim())add('history:'+item.id,item.query.slice(0,512),'history','search',()=>()=>actions.search(item.query,item.mode,item.provider));
        for(const item of data.backgrounds){const row=normalizeBackground(item);if(row&&!row.deleted)add('background:'+row.id,t('palette_change_background')+': '+(row.id.startsWith('preset-')?t(row.id):row.name),'commands','state',()=>saveSettings({backgroundMode:'library',backgroundSelected:row.id,backgroundSwitch:'manual'},'background'),[],'background-change');}
        for(const [id,theme] of Object.entries(data.customThemes))if(theme&&typeof theme.name==='string'&&theme.name.trim())add('custom-theme:'+id,t('palette_theme')+': '+theme.name,'commands','state',()=>saveSettings({theme:'custom',customThemeId:id},'appearance'),[],'theme-change');
    };
}
