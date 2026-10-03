import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {AsyncLocalStorage} from 'node:async_hooks';
import {BackgroundSyncSession} from '../public/assets/js/background-sync-session.js';
import {backgroundAcknowledgement} from '../public/assets/js/background-sync-ack.js';
import {readBackgrounds,readBackgroundReceipt,createBackground,updateBackground,downloadBackground} from '../public/assets/js/background-api.js';
import {syncUser} from '../public/assets/js/sync-api.js';
import {prepareUpload,clearUpload} from '../public/assets/js/background-upload-intent.js';
const fixture=JSON.parse(await readFile(process.argv[2],'utf8')),origin=process.argv[3];
if(!['http://127.0.0.1:8083','http://127.0.0.1:8084'].includes(origin))throw new Error('Isolated origin required');
const context=new AsyncLocalStorage(),nativeFetch=globalThis.fetch;
globalThis.fetch=async(path,options={})=>{
    const value=context.getStore();if(!value)throw new Error('Device context required');if(value.offline)throw new Error('test_offline');
    if(options.method==='POST'&&path.endsWith('/upload'))value.uploads++;
    const response=await nativeFetch(new URL(path,origin),{...options,headers:{...options.headers,Cookie:Object.entries(value.cookies).map(([key,cookie])=>key+'='+cookie).join('; ')}});
    for(const header of response.headers.getSetCookie()){const match=/^([^=]+)=([^;]*)/.exec(header);if(match)value.cookies[match[1]]=match[2];}
    if(value.loseUploadResponse&&options.method==='POST'&&path.endsWith('/upload')){value.loseUploadResponse=false;await response.arrayBuffer();throw new Error('lost_upload_response');}
    if(response.status===409)value.retries++;return response;
};
function device(cookies,rows=[]){
    const value={cookies:{...cookies},state:{backgrounds:rows},files:new Map(),uploads:0,retries:0,conflicts:0,offline:false};
    const io={user:syncUser,current:async owner=>String((await syncUser())?.id)===owner,
        local:()=>value.state.backgrounds,checkpoint:owner=>String(value.state.backgroundCheckpoint?.userId)===owner?value.state.backgroundCheckpoint:null,
        read:async owner=>{const response=await readBackgrounds(owner);if(response.status!==200)throw new Error('background_read_owner_denied');return response.data.items;},
        initial:async()=> 'cloud',conflicts:async rows=>{value.conflicts+=rows.length;return {choices:Object.fromEntries(rows.map(row=>[row.id,'cloud'])),rules:{}};},
        begin:async(owner,choice)=>{value.state.backgroundCheckpoint={userId:owner,pendingInitial:choice,document:{}};},
        finish:async()=>{delete value.state.backgroundCheckpoint.pendingInitial;},drop:async id=>{value.state.backgrounds=value.state.backgrounds.filter(row=>row.id!==id);},
        file:async id=>value.files.get(id)||null,download:downloadBackground,create:createBackground,
        pending:owner=>value.state.backgroundUploadIntents?.[owner]||null,
        receipt:readBackgroundReceipt,
        prepare:async(intent,file)=>{const plan=prepareUpload(value.state,intent,file);value.state={...value.state,...plan.values};for(const operation of plan.files)value.files.set(operation.id,operation.blob);},
        abandon:async intent=>{const plan=clearUpload(value.state,intent);value.state={...value.state,...plan.values};for(const operation of plan.files)value.files.delete(operation.id);},
        update:async(...args)=>{const hook=value.beforeWrite;value.beforeWrite=null;if(hook)await hook();return updateBackground(...args);},
        accept:async options=>{if(options.intent&&value.failAck)throw new Error('test_ack_quota');const plan=backgroundAcknowledgement(value.state,options);value.state={...value.state,...plan.values};for(const file of plan.files){if(file.blob===null)value.files.delete(file.id);else value.files.set(file.id,file.blob);}},status:status=>{value.status=status;}};
    value.io=io;value.session=new BackgroundSyncSession(io);value.run=()=>context.run(value,()=>value.session.run());return value;
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
const switched=device(fixture[0].devices[0]);const originalRead=switched.io.read;
switched.io.read=async owner=>{switched.cookies={...fixture[1].devices[1]};return originalRead(owner);};
await assert.rejects(switched.run(),/background_read_owner_denied/);assert.equal(switched.state.backgroundCheckpoint,undefined);assert.deepEqual(switched.state.backgrounds,[]);
const resumed=device(fixture[0].devices[0],[{id:'http-ack-failure',name:'ACK recovery',type:'image',sourceType:'upload',fileId:'http-ack-failure',fileVersion:1,fileSize:png.length,cloudSync:true}]);
resumed.io.initial=async()=> 'local';resumed.files.set('http-ack-failure',new Blob([png],{type:'image/png'}));resumed.failAck=true;
await assert.rejects(resumed.run(),/test_ack_quota/);const requestId=resumed.io.pending(String(fixture[0].userId)).requestId;
await context.run(resumed,async()=>{const item=(await readBackgrounds()).data.items.find(row=>row.id==='http-ack-failure');const reply=await updateBackground({id:item.id,name:'Cloud edit after upload'},item.version,fixture[0].userId);assert.equal(reply.status,200);});
resumed.failAck=false;resumed.session=new BackgroundSyncSession(resumed.io);assert.equal(await resumed.run(),true);
let after=await context.run(resumed,readBackgrounds);assert.equal(after.data.items.find(row=>row.id==='http-ack-failure').version,2);assert.equal(after.data.items.find(row=>row.id==='http-ack-failure').name,'Cloud edit after upload');assert.equal(resumed.io.pending(String(fixture[0].userId)),null);assert.equal(resumed.files.has('_bg_upload_'+requestId),false);
assert.equal(resumed.uploads,1);
resumed.state.backgrounds.push({id:'http-response-lost',name:'Lost response',type:'image',sourceType:'upload',fileId:'http-response-lost',fileVersion:1,fileSize:png.length,cloudSync:true});resumed.files.set('http-response-lost',new Blob([png],{type:'image/png'}));resumed.loseUploadResponse=true;
await assert.rejects(resumed.run(),/lost_upload_response/);resumed.session=new BackgroundSyncSession(resumed.io);assert.equal(await resumed.run(),true);
after=await context.run(resumed,readBackgrounds);assert.equal(after.data.items.find(row=>row.id==='http-response-lost').version,1);assert.deepEqual(new Uint8Array(await resumed.files.get('http-response-lost').arrayBuffer()),png);
assert.equal([...resumed.files.keys()].some(id=>id.startsWith('_bg_upload_')),false);
assert.equal(resumed.uploads,2);
const concurrentId=crypto.getRandomValues(new Uint8Array(32));const concurrentRequest=Array.from(concurrentId,value=>value.toString(16).padStart(2,'0')).join('');
const concurrentItem={id:'http-concurrent-upload',name:'Concurrent upload',type:'image',cloudSync:true};
const concurrentReplies=await Promise.all([a,b].map(value=>context.run(value,()=>createBackground(concurrentItem,fixture[0].userId,new Blob([png],{type:'image/png'}),null,concurrentRequest))));
assert.ok(concurrentReplies.every(reply=>reply.status===201&&reply.data.item.version===1));assert.equal(concurrentReplies[0].data.item.fileRevision,concurrentReplies[1].data.item.fileRevision);assert.ok(concurrentReplies.some(reply=>reply.data.replayed));
console.log('PASS: real background session transport, two devices, multipart and private file bytes');
console.log('PASS: field merge, live HTTP 409 and conflict resolution, offline recovery');
console.log('PASS: file opt-out without upload and foreign-owner download denial');
console.log('PASS: account switch during read rejected before checkpoint or data persistence');
console.log('PASS: real upload receipts recover ACK storage failure and lost HTTP response after session reload without incrementing version');
console.log('PASS: simultaneous upload requests share one committed file revision and receipt');
