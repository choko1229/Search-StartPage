import {get,setMany,setting,backgroundFile,flush} from './store.js';
import {BackgroundSyncSession} from './background-sync-session.js';
import {backgroundAcknowledgement} from './background-sync-ack.js';
import {backgroundRecord} from './background-sync-core.js';
import {readBackgrounds,createBackground,updateBackground,downloadBackground} from './background-api.js';
import {syncUser} from './sync-api.js';
import {syncDialog} from './sync-dialogs.js';
import {syncInterval} from './sync-session.js';
import {equal} from './sync-core.js';
import {t,node} from './i18n.js';
export function initializeBackgroundSync(panel) {
    const status=node('p',t('sync_ready'),{role:'status','aria-live':'polite'}),button=node('button',t('sync_now'),{type:'button',class:'secondary'});
    panel.append(button,status);let timer,applying=false,lastActivity=Date.now(),rerun=false;
    const checkpoint=owner=>{const value=get('backgroundCheckpoint',null);return String(value?.userId)===String(owner)?value:null;};
    const session=new BackgroundSyncSession({
        user:syncUser,current:async owner=>setting('syncEnabled',true)&&String((await syncUser())?.id)===String(owner),
        local:()=>get('backgrounds',[]),checkpoint,
        read:async()=>{const result=await readBackgrounds();if(result.status!==200||!Array.isArray(result.data?.items))throw new Error('background_sync_failed');return result.data.items;},
        initial:()=>syncDialog('initial',[],t('background_cloud_sync')),conflicts:items=>syncDialog('conflicts',items,t('background_cloud_sync')),
        begin:async(owner,choice)=>setMany({backgroundCheckpoint:{userId:owner,document:{},pendingInitial:choice}}),
        drop:async(id,before)=>setMany(state=>{
            const row=(state.backgrounds||[]).find(item=>item.id===id);if(!row||!equal(backgroundRecord(row),before))throw new Error('background_changed');
            // Initial Cloud choice keeps displaced local items recoverable.
            return {backgrounds:state.backgrounds.map(item=>item.id===id?{...item,deleted:true,cloudSync:false,localOnly:true}:item)};
        }),
        file:backgroundFile,create:createBackground,update:updateBackground,download:downloadBackground,
        accept:async options=>{applying=true;try{await setMany(state=>backgroundAcknowledgement(state,options).values,state=>backgroundAcknowledgement(state,options).files);}finally{applying=false;}},
        finish:async owner=>setMany(state=>{
            if(String(state.backgroundCheckpoint?.userId)!==owner)throw new Error('BACKGROUND_OWNER_CHANGED');
            const next={...state.backgroundCheckpoint};delete next.pendingInitial;return {backgroundCheckpoint:next};
        }),status:value=>{status.textContent=t('sync_'+value);},
    });
    function schedule(delay){clearTimeout(timer);timer=setTimeout(run,delay);}
    async function run(){
        if(session.busy){rerun=true;return;}if(session.paused)return;
        if(!setting('syncEnabled',true)){status.textContent=t('sync_disabled');return;}
        button.disabled=true;status.textContent=t('sync_working');
        try{await flush();await session.run();}catch{status.textContent=t('sync_failed');}
        finally{button.disabled=false;const delay=rerun?250:syncInterval(Date.now()-lastActivity);rerun=false;if(!session.paused)schedule(delay);}
    }
    button.addEventListener('click',()=>{session.paused=false;schedule(0);});
    window.addEventListener('data-change',event=>{if(!applying&&['backgrounds','settings'].includes(event.detail)){lastActivity=Date.now();schedule(250);}});
    window.addEventListener('online',()=>{if(!session.paused)schedule(0);});
    document.addEventListener('visibilitychange',()=>{if(!document.hidden&&!session.paused)schedule(0);});
    schedule(0);return session;
}
