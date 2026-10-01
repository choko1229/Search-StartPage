import {get,setMany,setting,snapshot,flush} from './store.js';
import {presets,t,node} from './i18n.js';
import {SyncSession,syncInterval} from './sync-session.js';
import {syncDocument,syncValues,syncCollections} from './sync-data.js';
import {syncDialog} from './sync-dialogs.js';
import {request,syncUser as user,writeSync} from './sync-api.js';
import {mergeSync,equal} from './sync-core.js';

const panel=node('section',undefined,{'aria-label':t('sync_title'),class:'sync-panel'});
const status=node('span',t('sync_ready'),{role:'status','aria-live':'polite'});
const button=node('button',t('sync_now'),{type:'button',class:'secondary'});
function control(key,label,fallback) {
    const input=node('input',undefined,{type:'checkbox'});input.checked=setting(key,fallback);
    input.addEventListener('change',async()=>{
        const checked=input.checked;
        const values={settings:{...get('settings',{}),[key]:checked}};
        if(key==='syncHistory')values.syncHistoryMergePending=checked;
        try {await setMany(values);}catch {input.checked=setting(key,fallback);return;}
        session.paused=false;schedule(0);
    });
    const wrapper=node('label',t(label));wrapper.prepend(input);panel.append(wrapper);return input;
}
const enabled=control('syncEnabled','sync_enabled',true);
const historyEnabled=control('syncHistory','sync_history',false);
panel.append(button,status);document.getElementById('search-preferences').after(panel);
let applying=false,cloudHistory={},timer,lastActivity=Date.now(),rerun=false;
const session=new SyncSession({
    user,
    current:async id=>setting('syncEnabled',true) && String((await user())?.id)===String(id),
    checkpoint:id=>{const value=get('syncCheckpoint',null);return String(value?.userId)===String(id)?value:null;},
    preferences:()=>({historyEnabled:setting('syncHistory',false)}),
    local:checkpoint=>syncDocument(snapshot(),presets,cloudHistory,checkpoint),
    read:async()=>{
        const result=await request('/api/sync');if(result.status!==200)throw new Error('sync_read_failed');
        cloudHistory=result.data.document.history || {};return result.data;
    },
    write:async(version,document,userId)=>{
        const result=await writeSync(version,document,userId);
        if(result.status===409)cloudHistory=result.data.document.history || {};
        return result;
    },
    initial:()=>syncDialog('initial'),conflicts:items=>syncDialog('conflicts',items),
    accept:async(document,checkpoint)=>{
        const previousCheckpoint=get('syncCheckpoint',null);
        const before=syncDocument(snapshot(),presets,cloudHistory,previousCheckpoint);
        applying=true;
        try {await setMany(state=>{
            const latest=mergeSync(before,syncDocument(state,presets,cloudHistory,previousCheckpoint),document).data;
            return syncValues(state,latest,checkpoint,new Date().toISOString(),presets);
        });}
        finally {applying=false;}
        if(!equal(syncDocument(snapshot(),presets,cloudHistory,checkpoint),checkpoint.document))rerun=true;
    },
    status:value=>{
        status.textContent=t('sync_'+value);
        if(get('syncOwnership',null))Promise.resolve(setMany({syncStatus:{...get('syncStatus',{}),state:value}})).catch(()=>{status.textContent=t('sync_failed');});
    },
});
function schedule(delay) {clearTimeout(timer);timer=setTimeout(run,delay);}
async function run() {
    if(session.busy) {rerun=true;return;}
    if(session.paused) {status.textContent=t('sync_later');return;}
    if(!setting('syncEnabled',true)) {status.textContent=t('sync_disabled');return;}
    button.disabled=true;status.textContent=t('sync_working');
    try {await flush();await session.run();}
    catch {try {session.io.status('failed');} catch {status.textContent=t('sync_failed');}}
    finally {
        button.disabled=false;
        if(session.paused)status.textContent=t('sync_later');
        const delay=rerun?250:syncInterval(Date.now()-lastActivity);rerun=false;schedule(delay);
    }
}
button.addEventListener('click',()=>{session.paused=false;schedule(0);});
window.addEventListener('data-change',event=>{
    enabled.checked=setting('syncEnabled',true);historyEnabled.checked=setting('syncHistory',false);
    if(!applying && (event.detail==='settings' || syncCollections.includes(event.detail))) {lastActivity=Date.now();schedule(250);}
});
for(const event of ['keydown','pointerdown'])window.addEventListener(event,()=>{
    const wasIdle=Date.now()-lastActivity>=10000;lastActivity=Date.now();
    if(wasIdle && !session.paused && setting('syncEnabled',true))schedule(syncInterval(0));
},{passive:true});
window.addEventListener('online',()=>{if(!session.paused)schedule(0);});
document.addEventListener('visibilitychange',()=>{if(!document.hidden && !session.paused)schedule(0);});
schedule(0);
