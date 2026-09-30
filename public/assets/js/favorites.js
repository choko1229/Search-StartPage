import {t, node} from './i18n.js';
import {setting, setSetting} from './store.js';
import {favorites, folders, patchFavorite, deleteFavorite, duplicateFavorite, openFavorite, saveFolder, deleteFolder, reorder} from './favorites-store.js';
import {rankFavorites, sortFavorites, shortcutKey, favoriteShortcuts} from './favorites-core.js';
import {editFavorite} from './favorite-editor.js';
import {favoriteLayout, layoutNumber} from './favorites-layout.js';
const grid = document.getElementById('favorites-grid');
const filter = document.getElementById('favorite-filter');
const order = document.getElementById('favorite-sort');
const display = document.getElementById('favorite-display');
const showHidden = document.getElementById('favorite-show-hidden');
const more = document.getElementById('favorites-more');
const tagReset = document.getElementById('favorite-tag-reset');
const tags = new Set();
const section = document.getElementById('favorites-section');
let expanded = setting('rememberFavoritesExpanded', true) && setting('favoritesExpanded', false);
function initializeLayoutSettings() {
    const container = document.getElementById('favorite-layout-settings');
    const positionLabel = node('label', t('favorite_position'));
    const position = node('select');
    for (const value of ['below', 'above', 'bottom']) position.append(node('option', t(`favorite_position_${value}`), {value}));
    position.value = setting('favoritePosition', 'below');
    position.addEventListener('change', () => {setSetting('favoritePosition', position.value); render();});
    positionLabel.append(position); container.append(positionLabel);
    for (const [key, title, fallback, min, max] of [
        ['favoriteWidth', 'favorite_width', 100, 25, 100],
        ['favoriteHeight', 'favorite_height', 0, 0, 1200],
        ['favoriteGap', 'favorite_gap', 32, 0, 300],
        ['favoriteLimit', 'favorite_limit', 0, 0, 1000],
        ['favoriteColumns', 'favorite_columns', 0, 0, 12]
    ]) {
        const label = node('label', t(title));
        const input = node('input', undefined, {type:'number', min:String(min), max:String(max), value:String(setting(key, fallback))});
        input.addEventListener('input', () => {
            if (!input.value || !input.validity.valid) return;
            setSetting(key, layoutNumber(input.value, fallback, min, max)); render();
        });
        input.addEventListener('change', () => {
            const value = layoutNumber(input.value, fallback, min, max);
            input.value = String(value); setSetting(key, value); render();
        });
        label.append(input); container.append(label);
    }
    const rememberLabel = node('label', t('favorite_remember_expanded'));
    const remember = node('input', undefined, {type:'checkbox'});
    remember.checked = setting('rememberFavoritesExpanded', true);
    remember.addEventListener('change', () => {
        setSetting('rememberFavoritesExpanded', remember.checked);
        setSetting('favoritesExpanded', remember.checked && expanded);
    });
    rememberLabel.prepend(remember); container.append(rememberLabel);
}
function applyPlacement() {
    const search = document.querySelector('.search-home');
    const historyArea = document.getElementById('history-area');
    const position = setting('favoritePosition', 'below');
    if (position === 'above' && section.nextElementSibling !== search) search.before(section);
    else if (position === 'bottom' && historyArea.nextElementSibling !== section) historyArea.after(section);
    else if (position !== 'above' && position !== 'bottom' && search.nextElementSibling !== section) search.after(section);
    section.style.width = `${layoutNumber(setting('favoriteWidth',100),100,25,100)}%`;
    const gap = `${layoutNumber(setting('favoriteGap',32),32,0,300)}px`;
    section.style.marginTop = position === 'above' ? '0' : gap;
    section.style.marginBottom = position === 'above' ? gap : '0';
}
function confirmDeletion(message, action) {
    const dialog = document.getElementById('favorite-confirm');
    document.getElementById('favorite-confirm-message').textContent = t(message);
    document.getElementById('favorite-confirm-delete').onclick = () => {dialog.close(); action();};
    dialog.showModal();
    dialog.querySelector('[data-close]').focus();
}
for (const [id, key] of [['favorite-context-setting','favoriteContextMenu'],['favorite-stats-setting','favoriteStats']]) {
    const input = document.getElementById(id);
    input.checked = setting(key, true);
    input.addEventListener('change', () => setSetting(key, input.checked));
}
const searchSetting = document.getElementById('favorite-search-setting');
searchSetting.value = setting('favoriteSearch', 'both');
filter.hidden = searchSetting.value === 'main';
searchSetting.addEventListener('change', () => {
    setSetting('favoriteSearch', searchSetting.value);
    filter.hidden = searchSetting.value === 'main';
    filter.value = '';
    render();
});
let folderId = setting('favoriteFolder','');
order.value = setting('favoriteSort','manual');
display.value = setting('favoriteDisplay','icon-name');
function drag(item, element, entity) {
    element.draggable = true;
    element.addEventListener('dragstart', event => {event.dataTransfer.setData(`text/x-${entity}`,item.id);event.dataTransfer.effectAllowed='move';});
    element.addEventListener('dragover', event => {if(event.dataTransfer.types.includes(`text/x-${entity}`)) event.preventDefault();});
    element.addEventListener('drop', event => {event.preventDefault();reorder(entity,event.dataTransfer.getData(`text/x-${entity}`),item.id);});
}
function orderedFolders() {
    return [...folders()].sort((a,b) => setting('folderSort','manual') === 'usage'
        ? favorites().filter(item=>item.folderId===b.id).reduce((sum,item)=>sum+item.usageCount,0)-favorites().filter(item=>item.folderId===a.id).reduce((sum,item)=>sum+item.usageCount,0)
        : a.sortOrder-b.sortOrder);
}
function selectFolder(id) {folderId=id;setSetting('favoriteFolder',id);render();}
function renderFolders() {
    const tabs = document.getElementById('folder-tabs'); tabs.replaceChildren();
    if (folderId && !folders().some(item=>item.id===folderId)) folderId='';
    const all = node('button',t('all'),{type:'button',role:'tab','aria-selected':String(!folderId)});
    all.addEventListener('click',()=>selectFolder(''));tabs.append(all);
    const items = orderedFolders();
    for(const [index,item] of items.entries()) {
        const button = node('button',item.name,{type:'button',role:'tab','aria-selected':String(folderId===item.id),class:index>=3?'overflow-tab':''});
        button.addEventListener('click',()=>selectFolder(item.id));
        if(setting('folderSort','manual')==='manual') drag(item,button,'favorite-folders');
        tabs.append(button);
    }
    if(items.length>3) {
        const overflow = node('select',undefined,{class:'folder-overflow','aria-label':t('more_folders')});
        overflow.append(node('option',t('more_folders'),{value:''}));
        for(const item of items.slice(3)) overflow.append(node('option',item.name,{value:item.id}));
        overflow.value = items.slice(3).some(item=>item.id===folderId)?folderId:'';
        overflow.addEventListener('change',()=>{if(overflow.value)selectFolder(overflow.value);});tabs.append(overflow);
    }
}
function menu(item) {
    const dialog=document.getElementById('favorite-menu');
    const container=document.getElementById('favorite-menu-actions');container.replaceChildren();
    const actions=[
        ['open',()=>openFavorite(item)],['edit',()=>editFavorite(item.id)],
        ['delete',()=>confirmDeletion('confirm_delete_favorite',()=>deleteFavorite(item.id))],
        ['duplicate',()=>duplicateFavorite(item.id,t('copy_suffix'))],
        ['move_folder',()=>editFavorite(item.id,'folderId')],['tags',()=>editFavorite(item.id,'tags')],
        ['shortcut',()=>editFavorite(item.id,'shortcut')],
        [item.pinned?'unpin':'pin',()=>patchFavorite(item.id,{pinned:!item.pinned})],
        [item.visible===false?'show':'hide',()=>patchFavorite(item.id,{visible:item.visible===false})],
        ['move_up',()=>{const rows=sortFavorites(favorites(),'manual');const index=rows.findIndex(row=>row.id===item.id);if(index>0)reorder('favorites',item.id,rows[index-1].id);}]
    ];
    for(const [label,action] of actions) {
        const button=node('button',t(label),{type:'button'});button.addEventListener('click',()=>{dialog.close();action();});container.append(button);
    }
    dialog.showModal();
}
function card(item, cardMode) {
    const container=node('article',undefined,{class:`favorite-item${item.visible===false?' is-hidden':''}`,role:'listitem'});
    container.style.borderColor=item.color;
    const open=node('button',undefined,{type:'button',class:'favorite-open','aria-label':item.name,title:item.url});
    const icon=node('span',undefined,{class:'favorite-icon','aria-hidden':'true'});
    if(/^https:\/\//i.test(item.icon||'')) {
        const image=node('img',undefined,{src:item.icon,alt:'',width:'32',height:'32',loading:'lazy',referrerpolicy:'no-referrer'});
        image.addEventListener('error',()=>icon.replaceChildren(document.createTextNode(item.name.slice(0,1))));icon.append(image);
    } else icon.textContent=(item.icon||item.name.slice(0,1)).slice(0,4);
    open.append(icon,node('span',`${item.pinned?'◆ ':''}${item.name}`,{class:'favorite-name'}));
    open.addEventListener('click',()=>openFavorite(item));
    const actions=node('button','⋯',{type:'button',class:'favorite-menu-button','aria-label':`${t('favorite_actions')} ${item.name}`});
    actions.addEventListener('click',()=>menu(item));container.append(actions,open);
    container.addEventListener('contextmenu',event=>{if(setting('favoriteContextMenu',true)){event.preventDefault();menu(item);}});
    if(cardMode) {
        container.append(node('p',item.description||new URL(item.url).hostname,{class:'favorite-description'}));
        const tagList=node('div',undefined,{class:'favorite-tags'});
        for(const tag of item.tags||[]) {
            const button=node('button',tag,{type:'button','aria-pressed':String(tags.has(tag))});
            button.addEventListener('click',()=>{if(tags.has(tag))tags.delete(tag);else tags.add(tag);render();});tagList.append(button);
        }
        container.append(tagList,node('p',`${t('usage_count')}: ${item.usageCount||0} · ${t('last_access')}: ${item.lastAccess?new Date(item.lastAccess).toLocaleString():t('never')}`,{class:'favorite-stats'}));
    }
    if(order.value==='manual' && !filter.value)drag(item,container,'favorites');
    return container;
}
function render() {
    applyPlacement();
    renderFolders();
    let items=favorites().filter(item=>(showHidden.checked||item.visible!==false)&&(!folderId||item.folderId===folderId)&&[...tags].every(tag=>(item.tags||[]).includes(tag)));
    items=filter.value.trim()?rankFavorites(items,filter.value,folders()):sortFavorites(items,order.value);
    const layout = favoriteLayout({width: section.clientWidth, count: items.length, display:display.value, columns:setting('favoriteColumns',0), limit:setting('favoriteLimit',0)});
    const mode = layout.mode;
    grid.dataset.display=mode;
    grid.style.gridTemplateColumns = `repeat(${layout.columns}, minmax(0, 1fr))`;
    const height = layoutNumber(setting('favoriteHeight',0),0,0,1200);
    grid.style.height = height && !expanded ? `${height}px` : 'auto';
    grid.style.overflowY = height && !expanded ? 'auto' : 'visible';
    grid.replaceChildren(...(expanded?items:items.slice(0,layout.limit)).map(item=>card(item,mode==='card')));
    if(!items.length)grid.append(node('p',t('favorites_empty')));
    more.hidden=items.length<=layout.limit;more.textContent=t(expanded?'show_less':'show_more');more.setAttribute('aria-expanded',String(expanded));
    tagReset.hidden=tags.size===0;tagReset.textContent=`${t('clear_filter')}: ${[...tags].join(', ')}`;
}
more.addEventListener('click',()=>{expanded=!expanded;if(setting('rememberFavoritesExpanded',true))setSetting('favoritesExpanded',expanded);render();});
filter.addEventListener('input',render);showHidden.addEventListener('change',render);
order.addEventListener('change',()=>{setSetting('favoriteSort',order.value);render();});
display.addEventListener('change',()=>{setSetting('favoriteDisplay',display.value);render();});
tagReset.addEventListener('click',()=>{tags.clear();render();});
function renderFolderEditor() {
    const list=document.getElementById('folder-list');list.replaceChildren();
    const form=document.getElementById('folder-form');
    for(const [index,item] of orderedFolders().entries()) {
        const row=node('div',undefined,{class:'provider-row'});row.append(node('span',item.name));
        for(const [label,action] of [
            ['edit',()=>{form.elements.id.value=item.id;form.elements.name.value=item.name;form.elements.name.focus();}],
            ['move_up',()=>{const previous=orderedFolders()[index-1];if(previous)reorder('favorite-folders',item.id,previous.id);}],
            ['delete',()=>confirmDeletion('confirm_delete_folder',()=>deleteFolder(item.id))]
        ]) {const button=node('button',t(label),{type:'button','aria-label':`${t(label)} ${item.name}`});button.addEventListener('click',action);row.append(button);}
        list.append(row);
    }
}
const folderForm=document.getElementById('folder-form');
folderForm.addEventListener('submit',event=>{event.preventDefault();try{saveFolder(folderForm.elements.name.value,folderForm.elements.id.value||null);folderForm.reset();document.getElementById('folder-error').textContent='';}catch(error){document.getElementById('folder-error').textContent=t(error.message);}});
document.getElementById('folder-sort').value=setting('folderSort','manual');
document.getElementById('folder-sort').addEventListener('change',event=>{setSetting('folderSort',event.target.value);render();renderFolderEditor();});
document.getElementById('folder-manage').addEventListener('click',()=>{renderFolderEditor();document.getElementById('folder-editor').showModal();});
window.addEventListener('data-change',event=>{if(['favorites','favorite-folders'].includes(event.detail)){render();renderFolderEditor();}});
document.addEventListener('keydown',event=>{
    if(event.isComposing||event.target.closest('input,textarea,select,[contenteditable=true]')||document.querySelector('dialog[open]'))return;
    const key=shortcutKey([event.ctrlKey?'Control':'',event.altKey?'Alt':'',event.shiftKey?'Shift':'',event.key].filter(Boolean).join('+'));
    const item=favoriteShortcuts(favorites()).get(key);
    if(item){event.preventDefault();openFavorite(item);}
});
let lastWidth = 0;
new ResizeObserver(entries => {
    const width = Math.round(entries[0].contentRect.width);
    if(width !== lastWidth) {lastWidth = width; render();}
}).observe(section);
initializeLayoutSettings();
render();
