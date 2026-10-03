import {get,setting,saveSettings,setMany,flush} from './store.js';
import {t,node} from './i18n.js';
import {providers} from './providers.js';
import {syncUser} from './sync-api.js';
import {defaultKeys} from './search-preferences.js';
import {onboardingSteps,onboardingPosition,onboardingPatch,onboardingBackgrounds} from './onboarding-core.js';

export async function initializeOnboarding() {
    let authenticated=false;try{authenticated=Boolean(await syncUser());}catch{}
    const steps=onboardingSteps(authenticated);let index=onboardingPosition(get('onboarding',null),steps),busy=false;
    const dialog=node('dialog',undefined,{class:'onboarding','aria-labelledby':'onboarding-title'});
    const title=node('h2',t('onboarding_title'),{id:'onboarding-title'}),counter=node('p'),description=node('p'),form=node('form'),error=node('p','',{role:'alert'});
    const actions=node('div',undefined,{class:'settings-actions'}),back=node('button',t('onboarding_back'),{type:'button',class:'secondary'}),skip=node('button',t('onboarding_skip'),{type:'button',class:'secondary'}),next=node('button',t('onboarding_next'),{type:'submit'}),later=node('button',t('onboarding_later'),{type:'button',class:'secondary'});
    actions.append(back,skip,next,later);form.append(actions);dialog.append(title,counter,description,form,error);document.body.append(dialog);
    const fields=node('div',undefined,{class:'onboarding-fields'});form.prepend(fields);
    function select(key,label,choices,fallback){const wrapper=node('label',t(label)),input=node('select',undefined,{name:key,'aria-label':t(label)});for(const [value,name] of choices)input.append(node('option',name,{value}));input.value=setting(key,fallback);if(!input.value && choices.length)input.value=choices[0][0];wrapper.append(input);fields.append(wrapper);return input;}
    function input(key,label,type,fallback,options={}){const wrapper=node('label',t(label)),control=node('input',undefined,{name:key,type,value:String(setting(key,fallback)),'aria-label':t(label),...options});wrapper.append(control);fields.append(wrapper);return control;}
    function render(){
        const step=steps[index];fields.replaceChildren();error.textContent='';counter.textContent=`${index+1} / ${steps.length} · ${t('onboarding_'+step)}`;description.textContent=t('onboarding_help_'+step);back.disabled=index===0;next.textContent=t(step==='complete'?'onboarding_finish':'onboarding_next');
        if(step==='appearance'){
            select('theme','appearance_theme',['solar','light','dark','os','forest','rose','custom'].map(value=>[value,t('theme_'+value)]),'solar');
            input('fontSize','font_size','number',16,{min:'10',max:'32',required:''});select('animationLevel','appearance_animation',['none','low','standard','rich'].map(value=>[value,t('animation_'+value)]),'rich');
        }
        if(step==='background'){
            const mode=select('backgroundMode','background_mode',[['theme',t('background_theme')],['solid',t('background_solid')],['library',t('background_library')]],'theme');
            const color=input('backgroundColor','search_background','color','#f4f6fa');
            const choices=onboardingBackgrounds(get('backgrounds',[])).map(row=>[row.id,row.id.startsWith('preset-')?t(row.id):row.name]);
            const background=select('backgroundSelected','onboarding_background_choice',choices,'preset-night');
            const update=()=>{color.parentElement.hidden=mode.value!=='solid';background.parentElement.hidden=mode.value!=='library';};mode.addEventListener('change',update);update();
        }
        if(step==='search'){select('initialMode','initial_mode',[['web',t('web_mode')],['ai',t('ai_mode')],['last',t('last_mode')]],'web');select('webDefault','web_default',providers('web').map(item=>[item.id,item.name]),'google');}
        if(step==='ai'){select('aiDefault','ai_default',providers('ai').map(item=>[item.id,item.name]),'chatgpt');select('aiOrder','ai_order',[['fixed',t('manual')],['usage',t('usage')],['recent',t('recent')]],'fixed');}
        if(step==='favorites'){select('favoriteDisplay','display',[['auto',t('auto')],['icon-name',t('icon-name')],['icon',t('icon')],['card',t('card')]],'auto');input('webKey','webKey','text',defaultKeys.webKey,{maxlength:'80'});input('aiKey','aiKey','text',defaultKeys.aiKey,{maxlength:'80'});}
        if(step==='discord')fields.append(node('a',t('header_login'),{href:'/account',class:'button'}));
        next.focus();
    }
    async function advance(skipped=false){
        if(busy)return;busy=true;for(const button of [next,skip,back,later])button.disabled=true;
        try{
            const step=steps[index],values=Object.fromEntries(new FormData(form)),patch=skipped?{}:onboardingPatch(step,values,{web:providers('web').map(item=>item.id),ai:providers('ai').map(item=>item.id),historyKey:setting('historyKey',defaultKeys.historyKey),backgrounds:onboardingBackgrounds(get('backgrounds',[])).map(row=>row.id)});
            const complete=index===steps.length-1,progress={step:complete?'complete':steps[index+1],complete};
            await saveSettings(patch,step==='favorites'?'favorites':step==='appearance'?'appearance':step==='background'?'background':step==='search'?'search':step==='ai'?'ai':'general',{onboarding:progress});
            if(complete){dialog.close();return;}index++;render();
        }catch(cause){error.textContent=cause.message==='INVALID_INPUT'?t('onboarding_invalid'):t('storage_unavailable');}
        finally{busy=false;next.disabled=skip.disabled=later.disabled=false;back.disabled=index===0;}
    }
    form.addEventListener('submit',event=>{event.preventDefault();void advance();});skip.addEventListener('click',()=>advance(true));
    back.addEventListener('click',async()=>{if(busy||index===0)return;try{await setMany({onboarding:{step:steps[index-1],complete:false}});index--;render();}catch{error.textContent=t('storage_unavailable');}});
    later.addEventListener('click',()=>dialog.close());
    dialog.addEventListener('cancel',event=>{if(busy)event.preventDefault();});
    const reopen=node('button',t('onboarding_restart'),{type:'button',class:'secondary'});document.getElementById('settings-general').prepend(reopen);
    const settings=document.getElementById('search-settings');reopen.addEventListener('click',()=>settings.dispatchEvent(new CustomEvent('open-onboarding')));
    settings.addEventListener('onboarding-approved',async()=>{index=0;render();try{await setMany({onboarding:{step:steps[0],complete:false}});}catch{error.textContent=t('storage_unavailable');}dialog.showModal();});
    const saved=get('onboarding',null);if(saved?.complete)return;
    try{await flush();}catch{error.textContent=t('storage_unavailable');}
    render();if(document.querySelector('dialog[open]'))return;dialog.showModal();
}
