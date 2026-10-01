import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {AsyncLocalStorage} from 'node:async_hooks';
import {SyncSession} from '../public/assets/js/sync-session.js';
import {syncDocument,syncValues} from '../public/assets/js/sync-data.js';
import {syncUser,request,writeSync} from '../public/assets/js/sync-api.js';
const fixture=JSON.parse(await readFile(process.argv[2],'utf8'));
const origin=process.argv[3];
if(!['http://127.0.0.1:8080','http://127.0.0.1:8081'].includes(origin))throw new Error('Isolated test origin required');
const context=new AsyncLocalStorage();const originalFetch=globalThis.fetch;
globalThis.fetch=async(path,options={})=>{
    const device=context.getStore();if(!device)throw new Error('No test device context');
    if(device.offline)throw new Error('simulated_offline');
    const headers={...options.headers,Cookie:Object.entries(device.cookies).map(([k,v])=>`${k}=${v}`).join('; ')};
    const response=await originalFetch(new URL(path,origin),{...options,headers});
    for(const value of response.headers.getSetCookie()) {const match=/^([^=]+)=([^;]*)/.exec(value);if(match)device.cookies[match[1]]=match[2];}
    if(path==='/api/sync' && response.status===409)device.retries++;
    return response;
};
const presets={web:[],ai:[]};
function device(cookies,state,choice='cloud') {
    const value={cookies:{...cookies},state,choice,cloudHistory:{},offline:false,retries:0,conflicts:0};
    value.session=new SyncSession({
        user:syncUser,current:async id=>String((await syncUser())?.id)===String(id),
        checkpoint:id=>String(value.state.syncCheckpoint?.userId)===String(id)?value.state.syncCheckpoint:null,
        preferences:()=>({historyEnabled:value.state.settings?.syncHistory===true}),
        local:checkpoint=>syncDocument(value.state,presets,value.cloudHistory,checkpoint),
        read:async()=>{const result=await request('/api/sync');assert.equal(result.status,200);value.cloudHistory=result.data.document.history || {};return result.data;},
        write:async(v,d,id)=>{const result=await writeSync(v,d,id);if(result.status===409)value.cloudHistory=result.data.document.history || {};if(![200,409].includes(result.status))console.log('Sync HTTP failure status: '+result.status);return result;},
        initial:async()=>value.choice,
        conflicts:async rows=>{value.conflicts+=rows.length;const choices=Object.fromEntries(rows.map(row=>[row.id,'cloud']));return{choices,rules:choices};},
        accept:(document,checkpoint)=>{value.state={...value.state,...syncValues(value.state,document,checkpoint,new Date().toISOString(),presets)};},
        status:status=>{value.status=status;},
    });
    value.run=()=>context.run(value,()=>value.session.run());return value;
}
const now=Date.now();
const a=device(fixture[0].devices[0],{settings:{theme:'light',fontSize:16,syncHistory:true,backgroundSettings:{selected:'preset',opacity:0.8}},favorites:[{id:'favorite',name:'Favorite',url:'https://example.test',folderId:'folder',tags:['Tag']}],'favorite-folders':[{id:'folder',name:'Work'}],backgrounds:[{id:'local-file',localOnly:true}],history:[{id:'a-history',query:'A query',provider:'g',mode:'web',at:now}]},'local');
const b=device(fixture[0].devices[1],{settings:{theme:'other',syncHistory:false},history:[{id:'b-history',query:'B private',provider:'g',mode:'web',at:now}]});
await a.run();await b.run();
assert.equal(b.state.settings.theme,'light');assert.equal(b.state.favorites[0].id,'favorite');
assert.equal(b.state.settings.backgroundSettings.opacity,0.8);assert.equal(b.state['favorite-folders'][0].id,'folder');assert.deepEqual(b.state.favorites[0].tags,['Tag']);
assert.equal(b.state.backgrounds,undefined);assert.equal(b.state.syncCheckpoint.document.backgrounds,undefined);
assert.equal(b.state.history[0].query,'B private');assert.equal(b.state.syncOwnership.collections.history,undefined);
a.state.settings.theme='dark';b.state.settings.fontSize=18;
const concurrent=await Promise.allSettled([a.run(),b.run()]);for(const result of concurrent)assert.equal(result.status,'fulfilled');await a.run();await b.run();
assert.equal(a.state.settings.fontSize,18);assert.equal(b.state.settings.theme,'dark');
assert.equal(a.conflicts+b.conflicts,0);
a.state.favorites[0].usageCount=3;await a.run();await b.run();assert.equal(b.state.favorites[0].usageCount,3);
a.state.settings.theme='alpha';b.state.settings.theme='beta';await a.run();await b.run();
assert.equal(b.state.settings.theme,'alpha');assert.equal(b.conflicts,1);
assert.equal(b.state.settings.syncRules['["settings","theme"]'],'cloud');
await a.run();assert.equal(a.state.settings.syncRules['["settings","theme"]'],'cloud');
b.session=new SyncSession(b.session.io);
a.state.settings.theme='alpha2';b.state.settings.theme='beta2';await a.run();await b.run();
assert.equal(b.state.settings.theme,'alpha2');assert.equal(b.conflicts,1);
const before=a.state.syncCheckpoint.version;a.offline=true;a.state.favorites[0].name='Offline edit';
await assert.rejects(a.run(),/simulated_offline/);assert.equal(a.state.syncCheckpoint.version,before);assert.equal(a.state.favorites[0].name,'Offline edit');
a.state=JSON.parse(JSON.stringify(a.state));a.session=new SyncSession(a.session.io);
a.offline=false;await a.run();await b.run();assert.equal(b.state.favorites[0].name,'Offline edit');
// Turning history on must retain cloud records as well as the private local rows.
b.state.settings.syncHistory=true;await b.run();await a.run();
assert.deepEqual(a.state.history.map(row=>row.id).sort(),['a-history','b-history']);
b.state.history=b.state.history.filter(row=>row.id!=='a-history');await b.run();await a.run();
assert.deepEqual(a.state.history.map(row=>row.id),['b-history']);
const other=device(fixture[1].devices[0],{settings:{theme:'other account'}},'local');await other.run();
assert.equal(other.state.settings.theme,'other account');await a.run();assert.equal(a.state.settings.theme,'alpha2');
const switched=device(fixture[1].devices[1],structuredClone(b.state),'later');await switched.run();
assert.equal(switched.status,'later');assert.equal(switched.state.syncCheckpoint.userId,fixture[0].userId);
console.log('Live sync HTTP checks passed; retry count: '+(a.retries+b.retries));
// Exercise the default history count at the search field's maximum length.
// Astral Unicode is the largest UTF-8 input at the allowed character count.
await context.run(a,async()=>{
    const query='😀'.repeat(12000);
    const history=Object.fromEntries(Array.from({length:300},(_,i)=>[`capacity-${i}`,{id:`capacity-${i}`,query,provider:'g',mode:'ai',at:now+i}]));
    const document={settings:{historyLimit:300,historyDays:90,theme:'before'},history};
    const current=(await request('/api/sync')).data;
    const saved=await writeSync(current.version,document,fixture[0].userId);
    assert.equal(saved.status,200,'maximum-length default history writes over real HTTP');
    assert.equal(Object.keys(saved.data.document.history).length,300);
    assert.equal(saved.data.document.history['capacity-299'].query,query);
    const cloud=await request('/api/sync');assert.equal(cloud.status,200);
    assert.equal(cloud.data.document.history['capacity-0'].query,query);
    const local={...document,settings:{...document.settings,theme:'after'}};
    const csrf=(await request('/api/csrf')).data.csrf_token;
    const resolved=await request('/api/sync/resolve-conflict',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({version:cloud.data.version,previous:document,local})});
    assert.equal(resolved.status,200,'two large documents resolve within bounded memory');
    assert.equal(resolved.data.document.settings.theme,'after');
    assert.equal(Object.keys(resolved.data.document.history).length,300);
    const stale=await writeSync(current.version,document,fixture[0].userId);
    assert.equal(stale.status,409);assert.equal(stale.data.document.settings.theme,'after');
    const isolated=await context.run(other,()=>request('/api/sync'));
    assert.equal(isolated.data.document.settings.theme,'other account');
    const invalidRoot=await request('/api/sync',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:'[]'});
    assert.equal(invalidRoot.status,400,'JSON arrays remain invalid request roots');
    const ordinary=await request('/api/settings',{method:'PUT',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({version:resolved.data.version,settings:{padding:'x'.repeat(1048576)}})});
    assert.equal(ordinary.status,413,'ordinary APIs retain the smaller request bound');
    const tooLarge={...document,history:Object.fromEntries(Array.from({length:350},(_,i)=>[`large-${i}`,{id:`large-${i}`,query,provider:'g',mode:'ai',at:now+i}]))};
    const rejected=await writeSync(resolved.data.version,tooLarge,fixture[0].userId);
    assert.equal(rejected.status,422,'documents exceeding MEDIUMTEXT capacity are rejected');
    assert.equal((await request('/api/sync')).data.version,resolved.data.version,'rejection leaves the saved version intact');
    console.log('Large Unicode history HTTP checks passed; document bytes: '+Buffer.byteLength(JSON.stringify(document)));
});
