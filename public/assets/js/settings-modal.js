import {t,node} from './i18n.js';
import {get,setMany} from './store.js';
import {resetSettings,travelSettings} from './settings-history.js';
import {categories,categoryKeys,categoryOf} from './settings-schema.js';

export function initializeSettingsModal() {
    const dialog=document.getElementById('search-settings');
    const body=node('div',undefined,{class:'settings-body'}),nav=node('nav',undefined,{'aria-label':t('settings_categories'),class:'settings-sidebar'});
    const content=node('div',undefined,{class:'settings-content'}),panels=new Map(),tabs=new Map();
    let category='general';
    const confirmation=node('dialog',undefined,{'aria-label':t('settings_confirm_title'),class:'settings-confirm'});
    const question=node('p'),confirmButton=node('button',t('settings_confirm'),{type:'button'}),cancelButton=node('button',t('settings_cancel'),{type:'button',class:'secondary'});
    confirmation.append(question,confirmButton,cancelButton);document.body.append(confirmation);
    let answer=null;
    function finish(value){confirmation.close();const resolve=answer;answer=null;resolve?.(value);}
    confirmButton.addEventListener('click',()=>finish(true));cancelButton.addEventListener('click',()=>finish(false));
    confirmation.addEventListener('cancel',event=>{event.preventDefault();finish(false);});
    const ask=message=>new Promise(resolve=>{if(answer){resolve(false);return;}answer=resolve;question.textContent=message;confirmation.showModal();cancelButton.focus();});
    const labels={initialMode:t('initial_mode'),webDefault:t('web_default'),aiDefault:t('ai_default'),aiOrder:t('ai_order'),urlPolicy:t('url_policy'),suggestOnFocus:t('suggest_on_focus'),historyArea:t('history_area'),historyLimit:t('history_limit'),historyDays:t('history_days'),saveHistory:t('save_history'),externalSuggest:t('external_suggest_setting')};
    for(const name of categories) {
        const button=node('button',t('category_'+name),{type:'button',class:'secondary'});
        const panel=node('section',undefined,{'aria-label':t('category_'+name),id:'settings-'+name});panel.hidden=true;
        panel.append(node('h3',t('category_'+name)));panels.set(name,panel);tabs.set(name,button);nav.append(button);content.append(panel);
        button.addEventListener('click',()=>select(name));
    }
    panels.get('search').append(document.getElementById('search-preferences'),document.getElementById('provider-settings'));
    const sync=dialog.querySelector('.sync-panel');if(sync)panels.get('sync').append(sync);
    const favoriteOptions=document.querySelector('#favorites-section details');
    if(favoriteOptions) {
        for(const element of [...favoriteOptions.children])if(element.tagName!=='SUMMARY')panels.get('favorites').append(element);
        favoriteOptions.remove();
    }
    const toolbar=node('div',undefined,{class:'settings-actions'});
    const undo=node('button',t('settings_undo'),{type:'button',class:'secondary'}),redo=node('button',t('settings_redo'),{type:'button',class:'secondary'});
    const reset=node('button',t('settings_reset_category'),{type:'button',class:'secondary'}),status=node('p','',{role:'status'});
    const history=node('ol',undefined,{class:'settings-history'});
    panels.get('general').append(node('h4',t('settings_history')),history);
    toolbar.append(undo,redo,reset,status);body.append(nav,content);dialog.append(toolbar,body);
    function select(name) {
        category=name;
        for(const [key,panel] of panels){panel.hidden=key!==name;tabs.get(key).setAttribute('aria-pressed',String(key===name));}
        reset.disabled=categoryKeys[name].length===0;
    }
    function renderHistory() {
        const saved=get('settingsHistory',{entries:[],cursor:0});
        undo.disabled=saved.cursor===0;redo.disabled=saved.cursor>=saved.entries.length;
        history.replaceChildren();
        for(const item of [...saved.entries].reverse()) {
            const name=t('category_'+(categories.includes(item.label)?item.label:categoryOf(item.label)));
            const changes=item.changes.map(change=>`${labels[change.key] || t('category_'+categoryOf(change.key))}: ${change.previous.present?JSON.stringify(change.previous.value):t('settings_default')} → ${change.next.present?JSON.stringify(change.next.value):t('settings_default')}`).join('; ');
            history.append(node('li',`${new Date(item.time).toLocaleString()} · ${name} · ${changes}`));
        }
    }
    async function travel(direction) {
        try {await setMany(state=>{const next=travelSettings(state.settingsHistory,state.settings || {},direction);return {settings:next.settings,settingsHistory:next.history};});dialog.dispatchEvent(new CustomEvent('refresh-settings'));status.textContent=t('settings_saved');}
        catch {status.textContent=t('storage_unavailable');}
    }
    undo.addEventListener('click',()=>travel('undo'));redo.addEventListener('click',()=>travel('redo'));
    reset.addEventListener('click',async()=>{
        if(!await ask(t('settings_reset_confirm')))return;
        const selected=category;
        try {await setMany(state=>{const next=resetSettings(state.settingsHistory,state.settings || {},categoryKeys[selected],selected);return {settings:next.settings,settingsHistory:next.history};});dialog.dispatchEvent(new CustomEvent('refresh-settings'));status.textContent=t('settings_saved');}
        catch {status.textContent=t('storage_unavailable');}
    });
    dialog.addEventListener('settings-render',()=>{
        for(const element of dialog.querySelectorAll('[data-setting]')){
            labels[element.dataset.setting]=element.firstChild.textContent;
            const panel=panels.get(categoryOf(element.dataset.setting));
            panel.insertBefore(element,panel.querySelector('#provider-settings'));
        }
        select(category);renderHistory();
    });
    window.addEventListener('data-change',event=>{if(event.detail==='settingsHistory')renderHistory();});
    const form=document.getElementById('provider-form');
    const pending=()=>[...form.elements].some(input=>['name','url','prefix','icon'].includes(input.name) && input.value!=='');
    async function closeImportant(){if(await ask(t('settings_discard_confirm'))){form.reset();dialog.close();}}
    dialog.addEventListener('cancel',event=>{if(pending()){event.preventDefault();void closeImportant();}});
    dialog.addEventListener('click',event=>{
        const rect=dialog.getBoundingClientRect();
        const outside=event.target===dialog && (event.clientX<rect.left || event.clientX>rect.right || event.clientY<rect.top || event.clientY>rect.bottom);
        if((outside || event.target.closest('[data-close]')) && pending()){event.preventDefault();event.stopImmediatePropagation();void closeImportant();}
    },true);
    dialog.addEventListener('close',()=>{form.reset();dialog.style.removeProperty('width');dialog.style.removeProperty('height');});
    const link=document.querySelector('.site-header a[href="/#settings"]');
    link?.addEventListener('click',event=>{event.preventDefault();document.getElementById('search-settings-open').click();});
    if(location.hash==='#settings')document.getElementById('search-settings-open').click();
    select(category);renderHistory();
}
