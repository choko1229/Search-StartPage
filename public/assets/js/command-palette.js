import {CommandRegistry,commandUsageAfter} from './command-registry.js';
import {CommandExecutor} from './command-executor.js';
import {registerProductCommands} from './palette-product-commands.js';
import {get,setting,setMany} from './store.js';
import {t,node} from './i18n.js';

export const paletteCommands=new CommandRegistry();
export function initializeCommandPalette(actions) {
    const dialog=node('dialog',undefined,{'aria-label':t('command_palette'),class:'command-palette'});
    const input=node('input',undefined,{type:'search','aria-label':t('palette_query'),role:'combobox','aria-autocomplete':'list','aria-controls':'palette-results','aria-expanded':'true',autocomplete:'off'});
    const results=node('div',undefined,{id:'palette-results',role:'listbox','aria-label':t('palette_results')});
    const status=node('p','',{role:'status'}),close=node('button',t('close'),{type:'button',class:'secondary'});
    dialog.append(node('h2',t('command_palette')),input,results,status,close);document.body.append(dialog);
    const button=node('button',t('command_palette')+' · Ctrl+K',{type:'button',class:'secondary'});document.getElementById('search-settings-open').before(button);
    const confirmation=node('dialog',undefined,{'aria-label':t('palette_confirm'),class:'command-confirmation'});
    const question=node('p'),remember=node('input',undefined,{type:'checkbox'}),rememberLabel=node('label',t('palette_no_ask'));
    rememberLabel.prepend(remember);const accept=node('button',t('palette_execute'),{type:'button'}),cancel=node('button',t('cancel'),{type:'button',class:'secondary'});
    confirmation.append(question,rememberLabel,accept,cancel);document.body.append(confirmation);
    let answer=null,previousFocus=null,rows=[],index=0,running=false;
    function finish(confirmed){confirmation.close();const resolve=answer;answer=null;resolve?.({confirmed,remember:confirmed&&remember.checked});}
    accept.addEventListener('click',()=>finish(true));cancel.addEventListener('click',()=>finish(false));confirmation.addEventListener('cancel',event=>{event.preventDefault();finish(false);});
    const executor=new CommandExecutor(paletteCommands,{preferences:()=>setting('commandConfirmations',{}),confirm:command=>new Promise(resolve=>{answer=resolve;question.textContent=t('palette_confirm')+': '+command.title;remember.checked=false;confirmation.showModal();cancel.focus();}),remember:(key,value)=>setMany(state=>({settings:{...state.settings,commandConfirmations:{...(state.settings?.commandConfirmations||{}),[key]:value}}}))});
    const rebuild=registerProductCommands(paletteCommands,actions);
    function highlight(){
        for(const [position,element] of [...results.querySelectorAll('[role=option]')].entries())element.setAttribute('aria-selected',String(position===index));
        if(rows[index]){input.setAttribute('aria-activedescendant','palette-option-'+index);document.getElementById('palette-option-'+index)?.scrollIntoView({block:'nearest'});}else input.removeAttribute('aria-activedescendant');
    }
    function render(){
        results.replaceChildren();rows=[];index=0;const options={usage:get('commandUsage',{})};
        const groups=input.value.trim()? [{id:'results',items:paletteCommands.search(input.value,options)}]:paletteCommands.initial(options);
        if(!input.value.trim()&&groups.every(group=>!group.items.length))groups.unshift({id:'commands',items:paletteCommands.search('',options).filter(row=>row.category==='commands')});
        for(const group of groups){if(!group.items.length)continue;results.append(node('h3',t('palette_group_'+group.id),{role:'presentation'}));for(const command of group.items){const position=rows.length;rows.push(command);const result=node('button',command.title,{type:'button',role:'option',id:'palette-option-'+position,'aria-selected':'false'});result.append(node('small',t('palette_category_'+command.category)));result.addEventListener('click',()=>void run(command));results.append(result);}}
        status.textContent=rows.length?'':t('palette_empty');highlight();
    }
    function shut(){dialog.close();previousFocus?.focus();}
    async function run(command){
        if(running)return;running=true;status.textContent='';
        try {
            const outcome=await executor.execute(command.id);if(!outcome.executed){input.focus();return;}
            try{await setMany(state=>({commandUsage:commandUsageAfter(state.commandUsage,command.id)}));}catch{window.dispatchEvent(new CustomEvent('storage-unavailable'));}
            shut();if(typeof outcome.value==='function')await outcome.value();
        }catch{status.textContent=t('palette_failed');input.focus();}finally{running=false;}
    }
    function open(){if(document.querySelector('dialog[open]'))return;previousFocus=document.activeElement;rebuild();input.value='';render();dialog.showModal();input.focus();}
    button.addEventListener('click',open);close.addEventListener('click',()=>{if(!running)shut();});dialog.addEventListener('cancel',event=>{event.preventDefault();if(!running)shut();});
    input.addEventListener('input',()=>{if(!running)render();});input.addEventListener('keydown',event=>{if(event.isComposing||running)return;if(['ArrowDown','ArrowUp','Home','End'].includes(event.key)){event.preventDefault();if(rows.length)index=event.key==='Home'?0:event.key==='End'?rows.length-1:(index+(event.key==='ArrowDown'?1:-1)+rows.length)%rows.length;highlight();}else if(event.key==='Enter'){event.preventDefault();if(rows[index])void run(rows[index]);}});
    document.addEventListener('keydown',event=>{if(!event.isComposing&&event.ctrlKey&&!event.altKey&&!event.shiftKey&&event.key.toLowerCase()==='k'){event.preventDefault();if(dialog.open&&!running)shut();else open();}});
    window.addEventListener('data-change',()=>{if(dialog.open&&!running){rebuild();render();}});
    const preferences=node('fieldset');preferences.append(node('legend',t('palette_confirm_settings')));document.getElementById('settings-shortcuts').append(preferences);
    for(const key of ['theme-change','background-change','search-engine-change','ai-change','favorite-delete','history:clear','account:logout']){
        const label=node('label',t('palette_confirmation_'+key)),checkbox=node('input',undefined,{type:'checkbox'});checkbox.checked=setting('commandConfirmations',{})[key]!==false;label.prepend(checkbox);preferences.append(label);
        checkbox.addEventListener('change',async()=>{try{const checked=checkbox.checked;await setMany(state=>({settings:{...state.settings,commandConfirmations:{...(state.settings?.commandConfirmations||{}),[key]:checked}}}));}catch{checkbox.checked=!checkbox.checked;}});
        window.addEventListener('data-change',event=>{if(event.detail==='settings')checkbox.checked=setting('commandConfirmations',{})[key]!==false;});
    }
}
