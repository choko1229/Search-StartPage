import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {AsyncLocalStorage} from 'node:async_hooks';
import {BackgroundSyncSession} from '../public/assets/js/background-sync-session.js';
import {backgroundAcknowledgement} from '../public/assets/js/background-sync-ack.js';
import {readBackgrounds,createBackground,updateBackground,downloadBackground} from '../public/assets/js/background-api.js';
import {syncUser} from '../public/assets/js/sync-api.js';
const fixture=JSON.parse(await readFile(process.argv[2],'utf8')),origin=process.argv[3];
if(!['http://127.0.0.1:8083','http://127.0.0.1:8084'].includes(origin))throw new Error('Isolated origin required');
const context=new AsyncLocalStorage(),nativeFetch=globalThis.fetch;
globalThis.fetch=async(path,options={})=>{
    const value=context.getStore();if(!value)throw new Error('Device context required');if(value.offline)throw new Error('test_offline');
    if(options.method==='POST'&&path.endsWith('/upload'))value.uploads++;
    const response=await nativeFetch(new URL(path,origin),{...options,headers:{...options.headers,Cookie:Object.entries(value.cookies).map(([key,cookie])=>key+'='+cookie).join('; ')}});
    for(const header of response.headers.getSetCookie()){const match=/^([^=]+)=([^;]*)/.exec(header);if(match)value.cookies[match[1]]=match[2];}
    if(response.status===409)value.retries++;return response;
};
function device(cookies,rows=[]){
    const value={cookies:{...cookies},state:{backgrounds:rows},files:new Map(),uploads:0,retries:0,conflicts:0,offline:false};
    const io={user:syncUser,current:async owner=>String((await syncUser())?.id)===owner,
        local:()=>value.state.backgrounds,checkpoint:owner=>String(value.state.backgroundCheckpoint?.userId)===owner?value.state.backgroundCheckpoint:null,
        read:async()=>{const response=await readBackgrounds();assert.equal(response.status,200);return response.data.items;},
        initial:async()=> 'cloud',conflicts:async rows=>{value.conflicts+=rows.length;return {choices:Object.fromEntries(rows.map(row=>[row.id,'cloud'])),rules:{}};},
        begin:async(owner,choice)=>{value.state.backgroundCheckpoint={userId:owner,pendingInitial:choice,document:{}};},
        finish:async()=>{delete value.state.backgroundCheckpoint.pendingInitial;},drop:async id=>{value.state.backgrounds=value.state.backgrounds.filter(row=>row.id!==id);},
        file:async id=>value.files.get(id)||null,download:downloadBackground,create:createBackground,
        update:async(...args)=>{const hook=value.beforeWrite;value.beforeWrite=null;if(hook)await hook();return updateBackground(...args);},
        accept:async options=>{const plan=backgroundAcknowledgement(value.state,options);value.state={...value.state,...plan.values};for(const file of plan.files){if(file.blob===null)value.files.delete(file.id);else value.files.set(file.id,file.blob);}},status:status=>{value.status=status;}};
    value.session=new BackgroundSyncSession(io);value.run=()=>context.run(value,()=>value.session.run());return value;
}
const png=new Uint8Array(Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aNC8AAAAASUVORK5CYII=','base64'));
const a=device(fixture[0].devices[0],[{id:'http-url',name:'URL',type:'image',sourceType:'url',url:'https://example.test/image.png',cloudSync:true},{id:'http-file',name:'File',type:'image',sourceType:'upload',fileId:'http-file',fileVersion:1,fileSize:png.length,cloudSync:true}]);
a.files.set('http-file',new Blob([png],{type:'image/png'}));assert.equal(await a.run(),true);assert.equal(a.uploads,1);
const b=device(fixture[0].devices[1]);assert.equal(await b.run(),true);assert.equal(b.state.backgrounds.length,2);assert.deepEqual(new Uint8Array(await b.files.get('http-file').arrayBuffer()),png);
a.state.backgrounds.find(row=>row.id==='http-url').name='Device A';assert.equal(await a.run(),true);b.state.backgrounds.find(row=>row.id==='http-url').blur=12;assert.equal(await b.run(),true);
let remote=await context.run(a,readBackgrounds);assert.equal(remote.data.items.find(row=>row.id==='http-url').name,'Device A');assert.equal(remote.data.items.find(row=>row.id==='http-url').blur,12);
b.state.backgrounds.find(row=>row.id==='http-url').name='Device B';
b.beforeWrite=async()=>context.run(a,async()=>{const item=(await readBackgrounds()).data.items.find(row=>row.id==='http-url');const response=await updateBackground({id:item.id,name:'Concurrent cloud'},item.version,fixture[0].userId);assert.equal(response.status,200);});
assert.equal(await b.run(),true);assert.ok(b.retries>0);assert.ok(b.conflicts>0);assert.equal(b.state.backgrounds.find(row=>row.id==='http-url').name,'Concurrent cloud');
b.offline=true;b.state.backgrounds.find(row=>row.id==='http-url').brightness=.5;await assert.rejects(b.run(),/test_offline/);assert.equal(b.state.backgrounds.find(row=>row.id==='http-url').brightness,.5);b.offline=false;assert.equal(await b.run(),true);
const row=b.state.backgrounds.find(row=>row.id==='http-file');row.fileVersion++;row.fileSize=png.length;row.cloudSync=false;const uploads=b.uploads;assert.equal(await b.run(),true);assert.equal(b.uploads,uploads);assert.ok(b.files.has('http-file'));
const foreign=device(fixture[1].devices[0]);const otherList=await context.run(foreign,readBackgrounds);assert.equal(otherList.data.items.length,0);
const privateItem=a.state.backgrounds.find(row=>row.id==='http-file');await assert.rejects(context.run(foreign,()=>downloadBackground(privateItem)),/FILE_UNAVAILABLE/);
console.log('PASS: real background session transport, two devices, multipart and private file bytes');
console.log('PASS: field merge, live HTTP 409 and conflict resolution, offline recovery');
console.log('PASS: file opt-out without upload and foreign-owner download denial');
