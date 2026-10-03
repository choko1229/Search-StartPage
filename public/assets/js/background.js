import {get,setting,saveSettings,setMany,backgroundFile} from './store.js';
import {t,node} from './i18n.js';
import {normalizeBackground,selectBackground,backgroundPresets} from './background-core.js';
import {color,bounded} from './appearance-core.js';
import {syncUser} from './sync-api.js';
import {recordSettings} from './settings-history.js';
import {inspectBackgroundFile} from './background-file-core.js';
import {initializeBackgroundSync} from './background-sync.js';
import {backgroundRuleEditor} from './background-rule-editor.js';
import {WeatherContext,needsWeather} from './weather-context.js';
import {BackgroundPlayback} from './background-playback.js';
import {libraryBackgrounds} from './background-library-core.js';
import {recordStatistic} from './statistics.js';

export function initializeBackground() {
    const panel=document.getElementById('settings-background'),settingsDialog=document.getElementById('search-settings');
    const layer=node('div',undefined,{class:'background-layer','aria-hidden':'true'}),media=node('div',undefined,{class:'background-media'}),overlay=node('div',undefined,{class:'background-overlay'});layer.append(media,overlay);document.body.prepend(layer);
    const library=node('div',undefined,{class:'background-library'});panel.append(node('h4',t('background_library')),library);
    const librarySort=node('select',undefined,{'aria-label':t('background_library_sort')});
    for(const [value,label] of [['saved','background_sort_saved'],['name','background_sort_name'],['favorite','background_sort_favorite']])librarySort.append(node('option',t(label),{value}));
    const sortLabel=node('label',t('background_library_sort'),{class:'preference'});sortLabel.append(librarySort);library.before(sortLabel);
    librarySort.addEventListener('change',()=>saveSettings({backgroundLibrarySort:librarySort.value},'background').catch(()=>{error.textContent=t('storage_unavailable');}));
    let libraryGeneration=0;const thumbnailUrls=new Set();
    function clearThumbnails(){libraryGeneration++;for(const url of thumbnailUrls)URL.revokeObjectURL(url);thumbnailUrls.clear();}
    window.addEventListener('pagehide',clearThumbnails);
    window.addEventListener('pageshow',event=>{if(event.persisted)renderLibrary();});
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
    field('sourceType','background_source','select','url',{choices:[['url','background_source_url'],['upload','background_source_upload']]});
    field('file','background_local_file','file','',{accept:'.jpg,.jpeg,.png,.gif,.webp,.avif,.mp4,.webm'});
    field('url','background_url','text','',{maxlength:'2048'});
    field('color','search_background','color','#101723');field('colorEnd','background_end_color','color','#304fc3');
    for(const [key,label,value,min,max,step] of [['angle','background_angle',135,0,360,1],['blur','search_blur',0,0,40,1],['brightness','background_brightness',1,0,2,.05],['overlay','background_overlay',0,0,1,.05],['scale','background_scale',1,1,2,.05],['speed','background_speed',1,.25,4,.25]])field(key,label,'number',value,{min:String(min),max:String(max),step:String(step)});
    field('overlayColor','background_overlay_color','color','#000000');
    field('position','display_position','select','center',{choices:[['center','position_center'],['top','header_top'],['bottom','header_bottom'],['left','align_left'],['right','align_right']]});
    field('fit','background_fit','select','cover',{choices:[['cover','fit_cover'],['contain','fit_contain'],['fill','fit_fill']]});
    for(const [key,label,value] of [['fixed','background_fixed',true],['autoplay','background_autoplay',true],['loop','background_loop',true],['mute','background_mute',true],['paused','background_pause',false],['cloudSync','background_cloud_sync',false]]){const input=field(key,label,'checkbox','');input.checked=value;}
    field('fallback','background_mobile_fallback','text','',{maxlength:'2048'});
    const rules=backgroundRuleEditor(form,()=>{form.dataset.pending='true';});
    const defaults=Object.fromEntries(Object.entries(fields).map(([key,input])=>[key,input.type==='checkbox'?input.checked:input.value]));
    const add=node('button',t('background_add'),{type:'submit'}),clear=node('button',t('cancel'),{type:'button',class:'secondary'});form.append(add,clear);
    const switching=node('select',undefined,{'aria-label':t('background_switch')});for(const [value,label] of [['manual','background_manual'],['random','background_random'],['rules','background_rules']])switching.append(node('option',t(label),{value}));
    const interval=node('input',undefined,{type:'number',min:'10',max:'86400',step:'1','aria-label':t('background_interval')});
    for(const [label,input] of [['background_switch',switching],['background_interval',interval]]){const wrapper=node('label',t(label),{class:'preference'});wrapper.append(input);panel.append(wrapper);}
    switching.addEventListener('change',()=>saveSettings({backgroundSwitch:switching.value},'background').catch(()=>{error.textContent=t('storage_unavailable');}));
    interval.addEventListener('change',()=>{const seconds=Number(interval.value);if(Number.isInteger(seconds)&&seconds>=10&&seconds<=86400)saveSettings({backgroundInterval:seconds},'background').catch(()=>{error.textContent=t('storage_unavailable');});});
    function resetForm(){editingId=null;for(const [key,value] of Object.entries(defaults)){if(fields[key].type==='checkbox')fields[key].checked=value;else fields[key].value=value;}rules.set(null);form.dataset.pending='false';error.textContent='';}
    clear.addEventListener('click',resetForm);settingsDialog.addEventListener('settings-discard',resetForm);
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(saving)return;const values=Object.fromEntries(new FormData(form));for(const key of ['fixed','autoplay','loop','mute','paused','cloudSync'])values[key]=fields[key].checked;
        delete values.file;
        saving=true;add.disabled=true;
        try{
            values.rule=rules.value();
            const id=editingId || crypto.randomUUID(),previous=storedBackgrounds().find(item=>item?.id===id),files=[];
            if(values.sourceType==='upload') {
                const file=fields.file.files?.[0];
                if(file){const metadata=await inspectBackgroundFile(file);Object.assign(values,metadata,{fileId:id,url:'',fileVersion:(previous?.fileVersion||0)+1});files.push({id,blob:file.slice(0,file.size,metadata.mime)});}
                else if(previous?.fileId===id)Object.assign(values,{fileId:id,url:'',type:previous.type,fileSize:previous.fileSize,fileVersion:previous.fileVersion});
                else throw new Error('INVALID_UPLOAD');
            }else if(previous?.fileId)files.push({id:previous.fileId,blob:null});
            const combined={...previous,...values,id,localOnly:!values.cloudSync,rule:values.rule??null};
            if(values.sourceType!=='upload'){for(const key of ['fileId','fileVersion','fileRevision','syncedFileVersion'])delete combined[key];combined.fileSize=0;}
            const row=normalizeBackground(combined);if(!row)throw new Error('background_invalid');
            await setMany(state=>{const patch={backgroundMode:'library',backgroundSelected:row.id};return {settings:{...state.settings,...patch},settingsHistory:recordSettings(state.settingsHistory,state.settings || {},patch,'background'),backgrounds:[...(Array.isArray(state.backgrounds)?state.backgrounds:[]).filter(item=>item?.id!==row.id),row]};},files);resetForm();void recordStatistic('feature',{feature:'background'});
        }catch(failure){error.textContent=t(['INVALID_UPLOAD','INVALID_UPLOAD_NAME','UNSUPPORTED_BACKGROUND_FORMAT','BACKGROUND_TOO_LARGE','BACKGROUND_MIME_MISMATCH','BACKGROUND_RULE_INVALID','background_invalid'].includes(failure.message)?failure.message:'storage_unavailable');}finally{saving=false;add.disabled=false;}
    });
    function renderLibrary(){
        clearThumbnails();const generation=libraryGeneration;
        librarySort.value=['saved','name','favorite'].includes(setting('backgroundLibrarySort','saved'))?setting('backgroundLibrarySort','saved'):'saved';
        library.replaceChildren();for(const item of libraryBackgrounds([...backgroundPresets,...storedBackgrounds()],librarySort.value)){
            const row=normalizeBackground(item);if(!row)continue;
            const wrapper=node('div',undefined,{class:'background-entry'});
            const thumbnail=node('div',undefined,{class:'background-thumbnail','aria-hidden':'true'});thumbnail.style.background=row.type==='gradient'?`linear-gradient(${row.angle}deg,${row.color},${row.colorEnd})`:row.color;wrapper.append(thumbnail);
            const attachThumbnail=url=>{if(generation!==libraryGeneration||!wrapper.isConnected)return;const preview=node(row.type==='video'?'video':'img',undefined,{src:url,...row.type==='video'?{preload:'metadata',playsinline:''}:{alt:'',loading:'lazy'}});preview.style.objectFit=row.fit;preview.style.objectPosition=row.position;if(row.type==='video')preview.muted=true;preview.addEventListener('error',()=>preview.remove());thumbnail.append(preview);};
            if(['image','video'].includes(row.type)){
                if(row.fileId)backgroundFile(row.fileId).then(blob=>{if(generation!==libraryGeneration||!wrapper.isConnected||!(blob instanceof Blob))return;const url=URL.createObjectURL(blob);thumbnailUrls.add(url);attachThumbnail(url);}).catch(()=>{});
                else queueMicrotask(()=>attachThumbnail(row.url));
            }
            const button=node('button',row.id.startsWith('preset-')?t(row.id):row.name,{type:'button',class:'secondary','aria-pressed':String(setting('backgroundSelected','')===row.id)});
            button.disabled=row.deleted===true;button.addEventListener('click',()=>saveSettings({backgroundMode:'library',backgroundSelected:row.id},'background').catch(()=>{error.textContent=t('storage_unavailable');}));wrapper.append(button);
            wrapper.append(node('small',`${t(row.cloudSync?'background_sync_on':'background_local_only')}${row.fileId?' · '+(row.fileSize/1048576).toFixed(2)+' MiB':''}`));
            if(!row.id.startsWith('preset-')){
                const favorite=node('button',t('background_favorite'),{type:'button',class:'secondary','aria-label':`${t('background_favorite')}: ${row.name}`,'aria-pressed':String(row.favorite)});favorite.addEventListener('click',()=>setMany(state=>({backgrounds:(state.backgrounds || []).map(item=>item.id===row.id?{...item,favorite:item.favorite!==true}:item)})).catch(()=>{error.textContent=t('storage_unavailable');}));wrapper.append(favorite);
                if(row.deleted!==true){const edit=node('button',t('edit_favorite'),{type:'button',class:'secondary','aria-label':`${t('background_edit')}: ${row.name}`});edit.textContent=t('background_edit');edit.addEventListener('click',()=>{editingId=row.id;for(const [key,input] of Object.entries(fields)){const value=row[key];if(input.type==='checkbox')input.checked=value??defaults[key];else input.value=input.type==='file'?'':String(value??defaults[key]);}try{rules.set(row.rule??null);}catch{error.textContent=t('BACKGROUND_RULE_INVALID');return;}form.dataset.pending='true';fields.name.focus();});wrapper.append(edit);}
                const archive=node('button',t(row.deleted?'background_restore':'background_archive'),{type:'button',class:'secondary','aria-label':`${t(row.deleted?'background_restore':'background_archive')}: ${row.name}`});archive.addEventListener('click',()=>setMany(state=>({backgrounds:(state.backgrounds || []).map(item=>item.id===row.id?{...item,deleted:row.deleted!==true}:item)})).catch(()=>{error.textContent=t('storage_unavailable');}));wrapper.append(archive);
            }
            library.append(wrapper);
        }
        switching.value=setting('backgroundSwitch','manual');if(document.activeElement!==interval)interval.value=String(setting('backgroundInterval',300));
    }
    let loggedIn=false,current=null,video=null,lastSwitch=0,signature='',objectUrl=null,mediaGeneration=0;
    const playbackButton=node('button',t('background_play_video'),{type:'button',class:'secondary background-playback-control',hidden:''});
    const playbackStatus=node('p','',{role:'status',class:'background-playback-control'});
    document.getElementById('favorites-section').before(playbackButton,playbackStatus);
    const playback=new BackgroundPlayback(state=>{playbackButton.hidden=!state.available;playbackButton.textContent=t(state.playing||state.pending?'background_pause_video':'background_play_video');playbackButton.setAttribute('aria-pressed',String(state.playing||state.pending));if(!state.available)playbackStatus.textContent='';},()=>{error.textContent=t('background_play_blocked');playbackStatus.textContent=t('background_play_blocked');});
    playbackButton.addEventListener('click',()=>{playbackStatus.textContent='';playback.toggle();});
    const weatherStatus=node('p','',{role:'status','aria-live':'polite'});panel.append(weatherStatus);
    const weather=new WeatherContext({changed:()=>apply(true),status:key=>{weatherStatus.textContent=key?t(key):'';}});
    function clearMedia(){mediaGeneration++;playback.clear();video=null;media.replaceChildren();if(objectUrl){URL.revokeObjectURL(objectUrl);objectUrl=null;}}
    let authCheck=null,lastAuthCheck=0;
    function refreshAuth(){
        if(authCheck)return;lastAuthCheck=Date.now();
        authCheck=syncUser().then(user=>{const next=Boolean(user);if(next!==loggedIn){loggedIn=next;apply(true);}}).catch(()=>{}).finally(()=>{authCheck=null;});
    }
    refreshAuth();
    window.addEventListener('search-auth-change',event=>{
        const next=event.detail?.authenticated===true;
        if(next!==loggedIn){loggedIn=next;apply(true);}
    });
    async function apply(force=false){
        const now=Date.now(),settings=get('settings',{});layer.hidden=settings.backgroundMode!=='library';if(layer.hidden){clearMedia();current=null;signature='';return;}
        if(!document.hidden&&now-lastAuthCheck>=60000)refreshAuth();
        if(!force && current && settings.backgroundSwitch==='random' && now-lastSwitch<bounded(settings.backgroundInterval,300,10,86400)*1000)return;
        const rows=[...backgroundPresets,...storedBackgrounds()];
        const width=innerWidth,context={now:new Date(),loggedIn,width,height:innerHeight,device:width<=600?'mobile':width<=1024?'tablet':'desktop',...weather.read(settings.themeRegion,settings.backgroundSwitch==='rules' && needsWeather(rows))};
        const selected=selectBackground(rows,settings,context);if(!selected)return;
        current=selected;lastSwitch=now;const next=JSON.stringify([selected,width<=600]);if(signature===next){playback.sync(document.hidden);return;}signature=next;
        clearMedia();const generation=mediaGeneration;media.style.background='';
        media.style.filter=`blur(${selected.blur}px) brightness(${selected.brightness})`;media.style.transform=`scale(${selected.scale})`;layer.style.position=selected.fixed?'fixed':'absolute';
        const rgb=[1,3,5].map(offset=>parseInt(color(selected.overlayColor,'#000000').slice(offset,offset+2),16)).join(',');overlay.style.background=`rgba(${rgb},${selected.overlay})`;
        let type=selected.type,url=selected.url;if(type==='video'&&width<=600&&selected.fallback){type='image';url=selected.fallback;}
        if(selected.fileId && !(selected.type==='video'&&width<=600&&selected.fallback)){
            try{const blob=await backgroundFile(selected.fileId);if(generation!==mediaGeneration)return;if(!(blob instanceof Blob))throw new Error('background_file_missing');objectUrl=URL.createObjectURL(blob);url=objectUrl;}
            catch{if(generation===mediaGeneration){error.textContent=t('background_load_failed');media.style.background=selected.color;signature='';}return;}
        }
        if(type==='solid')media.style.background=selected.color;
        if(type==='gradient')media.style.background=`linear-gradient(${selected.angle}deg,${selected.color},${selected.colorEnd})`;
        if(type==='image'||type==='video'){
            const element=node(type==='image'?'img':'video',undefined,{src:url,...type==='image'?{alt:''}:{playsinline:'',preload:'metadata'}});element.style.objectFit=selected.fit;element.style.objectPosition=selected.position;
            element.addEventListener('error',()=>{error.textContent=t('background_load_failed');media.style.background=selected.color;element.remove();if(type==='video')playback.clear();});media.append(element);
            if(type==='video'){video=element;element.muted=selected.mute;element.loop=selected.loop;element.playbackRate=selected.speed;playback.attach(element,selected,document.hidden);}
        }
    }
    window.addEventListener('data-change',event=>{if(['settings','backgrounds'].includes(event.detail)){renderLibrary();apply(true);}});window.addEventListener('resize',()=>apply(true));document.addEventListener('visibilitychange',()=>{if(!document.hidden&&setting('backgroundMode','theme')==='library')refreshAuth();apply(true);});
    setInterval(()=>{if(!document.hidden)apply();},10000);renderLibrary();apply(true);initializeBackgroundSync(panel);
}
