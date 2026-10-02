import {get,setting,saveSettings,setMany} from './store.js';
import {t,node} from './i18n.js';
import {normalizeBackground,selectBackground,backgroundPresets} from './background-core.js';
import {color,bounded} from './appearance-core.js';
import {syncUser} from './sync-api.js';
import {recordSettings} from './settings-history.js';

export function initializeBackground() {
    const panel=document.getElementById('settings-background'),settingsDialog=document.getElementById('search-settings');
    const layer=node('div',undefined,{class:'background-layer','aria-hidden':'true'}),media=node('div',undefined,{class:'background-media'}),overlay=node('div',undefined,{class:'background-overlay'});layer.append(media,overlay);document.body.prepend(layer);
    const library=node('div',undefined,{class:'background-library'});panel.append(node('h4',t('background_library')),library);
    const form=node('form',undefined,{class:'background-editor','aria-label':t('background_editor')}),error=node('p','',{role:'alert'});panel.append(form,error);
    const fields={};
    let editingId=null,saving=false;
    const storedBackgrounds=()=>{const rows=get('backgrounds',[]);return Array.isArray(rows)?rows:[];};
    function field(key,label,type,value,options={}) {
        const wrapper=node('label',t(label)),input=node(type==='select'?'select':'input',undefined,{name:key,'aria-label':t(label),...(type==='select'?{}:{type}),...options});
        if(type==='select')for(const [id,name] of options.choices || [])input.append(node('option',t(name),{value:id}));input.removeAttribute('choices');input.value=String(value);wrapper.append(input);form.append(wrapper);fields[key]=input;
        input.addEventListener('input',()=>{form.dataset.pending='true';});input.addEventListener('change',()=>{form.dataset.pending='true';});return input;
    }
    field('name','name','text','',{required:'',maxlength:'100'});
    field('type','background_type','select','gradient',{choices:[['solid','background_solid'],['gradient','background_gradient'],['image','background_image'],['video','background_video']]});
    field('url','background_url','text','',{maxlength:'2048'});
    field('color','search_background','color','#101723');field('colorEnd','background_end_color','color','#304fc3');
    for(const [key,label,value,min,max,step] of [['angle','background_angle',135,0,360,1],['blur','search_blur',0,0,40,1],['brightness','background_brightness',1,0,2,.05],['overlay','background_overlay',0,0,1,.05],['scale','background_scale',1,1,2,.05],['speed','background_speed',1,.25,4,.25]])field(key,label,'number',value,{min:String(min),max:String(max),step:String(step)});
    field('overlayColor','background_overlay_color','color','#000000');
    field('position','display_position','select','center',{choices:[['center','position_center'],['top','header_top'],['bottom','header_bottom'],['left','align_left'],['right','align_right']]});
    field('fit','background_fit','select','cover',{choices:[['cover','fit_cover'],['contain','fit_contain'],['fill','fit_fill']]});
    for(const [key,label,value] of [['fixed','background_fixed',true],['autoplay','background_autoplay',true],['loop','background_loop',true],['mute','background_mute',true],['paused','background_pause',false]]){const input=field(key,label,'checkbox','');input.checked=value;}
    field('fallback','background_mobile_fallback','text','',{maxlength:'2048'});
    const timeRule=field('timeRule','background_time_rule','checkbox','');timeRule.checked=false;
    field('timeStart','background_time_start','time','08:00');field('timeEnd','background_time_end','time','18:00');
    const defaults=Object.fromEntries(Object.entries(fields).map(([key,input])=>[key,input.type==='checkbox'?input.checked:input.value]));
    const add=node('button',t('background_add'),{type:'submit'}),clear=node('button',t('cancel'),{type:'button',class:'secondary'});form.append(add,clear);
    const switching=node('select',undefined,{'aria-label':t('background_switch')});for(const [value,label] of [['manual','background_manual'],['random','background_random'],['rules','background_rules']])switching.append(node('option',t(label),{value}));
    const interval=node('input',undefined,{type:'number',min:'10',max:'86400',step:'1','aria-label':t('background_interval')});
    for(const [label,input] of [['background_switch',switching],['background_interval',interval]]){const wrapper=node('label',t(label),{class:'preference'});wrapper.append(input);panel.append(wrapper);}
    switching.addEventListener('change',()=>saveSettings({backgroundSwitch:switching.value},'background').catch(()=>{error.textContent=t('storage_unavailable');}));
    interval.addEventListener('change',()=>{const seconds=Number(interval.value);if(Number.isInteger(seconds)&&seconds>=10&&seconds<=86400)saveSettings({backgroundInterval:seconds},'background').catch(()=>{error.textContent=t('storage_unavailable');});});
    function resetForm(){editingId=null;for(const [key,value] of Object.entries(defaults)){if(fields[key].type==='checkbox')fields[key].checked=value;else fields[key].value=value;}form.dataset.pending='false';error.textContent='';}
    clear.addEventListener('click',resetForm);settingsDialog.addEventListener('settings-discard',resetForm);
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(saving)return;const values=Object.fromEntries(new FormData(form));for(const key of ['fixed','autoplay','loop','mute','paused'])values[key]=fields[key].checked;
        if(timeRule.checked)values.rule={type:'time',start:fields.timeStart.value,end:fields.timeEnd.value};
        const row=normalizeBackground({...values,id:editingId || crypto.randomUUID(),localOnly:true});if(!row){error.textContent=t('background_invalid');return;}
        saving=true;add.disabled=true;
        try{await setMany(state=>{const patch={backgroundMode:'library',backgroundSelected:row.id};return {settings:{...state.settings,...patch},settingsHistory:recordSettings(state.settingsHistory,state.settings || {},patch,'background'),backgrounds:[...(Array.isArray(state.backgrounds)?state.backgrounds:[]).filter(item=>item.id!==row.id),row]};});resetForm();}catch{error.textContent=t('storage_unavailable');}finally{saving=false;add.disabled=false;}
    });
    function renderLibrary(){
        library.replaceChildren();for(const item of [...backgroundPresets,...storedBackgrounds()]){
            const row=normalizeBackground(item);if(!row)continue;
            const wrapper=node('div',undefined,{class:'background-entry'});
            const button=node('button',row.id.startsWith('preset-')?t(row.id):row.name,{type:'button',class:'secondary','aria-pressed':String(setting('backgroundSelected','')===row.id)});
            button.disabled=row.deleted===true;button.addEventListener('click',()=>saveSettings({backgroundMode:'library',backgroundSelected:row.id},'background').catch(()=>{error.textContent=t('storage_unavailable');}));wrapper.append(button);
            if(!row.id.startsWith('preset-')){
                if(row.deleted!==true){const edit=node('button',t('edit_favorite'),{type:'button',class:'secondary','aria-label':`${t('background_edit')}: ${row.name}`});edit.textContent=t('background_edit');edit.addEventListener('click',()=>{editingId=row.id;for(const [key,input] of Object.entries(fields)){const value=key==='timeRule'?Boolean(row.rule?.type==='time'):key==='timeStart'?row.rule?.start:key==='timeEnd'?row.rule?.end:row[key];if(input.type==='checkbox')input.checked=value??defaults[key];else input.value=String(value??defaults[key]);}form.dataset.pending='true';fields.name.focus();});wrapper.append(edit);}
                const archive=node('button',t(row.deleted?'background_restore':'background_archive'),{type:'button',class:'secondary','aria-label':`${t(row.deleted?'background_restore':'background_archive')}: ${row.name}`});archive.addEventListener('click',()=>setMany(state=>({backgrounds:(state.backgrounds || []).map(item=>item.id===row.id?{...item,deleted:row.deleted!==true}:item)})).catch(()=>{error.textContent=t('storage_unavailable');}));wrapper.append(archive);
            }
            library.append(wrapper);
        }
        switching.value=setting('backgroundSwitch','manual');if(document.activeElement!==interval)interval.value=String(setting('backgroundInterval',300));
    }
    let loggedIn=false,current=null,video=null,lastSwitch=0,signature='';
    syncUser().then(user=>{loggedIn=Boolean(user);apply(true);}).catch(()=>{});
    function apply(force=false){
        const now=Date.now(),settings=get('settings',{});layer.hidden=settings.backgroundMode!=='library';if(layer.hidden){video?.pause();current=null;signature='';return;}
        if(!force && current && settings.backgroundSwitch==='random' && now-lastSwitch<bounded(settings.backgroundInterval,300,10,86400)*1000)return;
        const width=innerWidth,context={now:new Date(),loggedIn,width,height:innerHeight,device:width<=600?'mobile':width<=1024?'tablet':'desktop'};
        const selected=selectBackground([...backgroundPresets,...storedBackgrounds()],settings,context);if(!selected)return;
        current=selected;lastSwitch=now;const next=JSON.stringify([selected,width<=600]);if(signature===next){if(video){if(document.hidden||selected.paused)video.pause();else if(selected.autoplay)video.play().catch(()=>{});}return;}signature=next;
        video?.pause();video=null;media.replaceChildren();media.style.background='';
        media.style.filter=`blur(${selected.blur}px) brightness(${selected.brightness})`;media.style.transform=`scale(${selected.scale})`;layer.style.position=selected.fixed?'fixed':'absolute';
        const rgb=[1,3,5].map(offset=>parseInt(color(selected.overlayColor,'#000000').slice(offset,offset+2),16)).join(',');overlay.style.background=`rgba(${rgb},${selected.overlay})`;
        let type=selected.type,url=selected.url;if(type==='video'&&width<=600&&selected.fallback){type='image';url=selected.fallback;}
        if(type==='solid')media.style.background=selected.color;
        if(type==='gradient')media.style.background=`linear-gradient(${selected.angle}deg,${selected.color},${selected.colorEnd})`;
        if(type==='image'||type==='video'){
            const element=node(type==='image'?'img':'video',undefined,{src:url,...type==='image'?{alt:''}:{playsinline:'',preload:'metadata'}});element.style.objectFit=selected.fit;element.style.objectPosition=selected.position;
            element.addEventListener('error',()=>{error.textContent=t('background_load_failed');media.style.background=selected.color;element.remove();});media.append(element);
            if(type==='video'){video=element;element.muted=selected.mute;element.loop=selected.loop;element.playbackRate=selected.speed;if(selected.autoplay&&!selected.paused&&!document.hidden)element.play().catch(()=>{error.textContent=t('background_play_blocked');});}
        }
    }
    window.addEventListener('data-change',event=>{if(['settings','backgrounds'].includes(event.detail)){renderLibrary();apply(true);}});window.addEventListener('resize',()=>apply(true));document.addEventListener('visibilitychange',()=>apply(true));
    setInterval(()=>{if(!document.hidden)apply();},10000);renderLibrary();apply(true);
}
