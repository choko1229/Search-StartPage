import assert from 'node:assert/strict';
import {BackgroundSyncSession} from '../public/assets/js/background-sync-session.js';
import {backgroundAcknowledgement} from '../public/assets/js/background-sync-ack.js';
import {backgroundRecord} from '../public/assets/js/background-sync-core.js';
import {prepareUpload,clearUpload} from '../public/assets/js/background-upload-intent.js';
const server=new Map(),serverFiles=new Map(),receipts=new Map();let serial=0,writes=0,conflictNext=false;
function device(rows=[]) {
    let state={backgrounds:structuredClone(rows)};const files=new Map(),statuses=[];
    const io={user:async()=>({id:'1'}),current:async()=>true,local:()=>state.backgrounds,checkpoint:()=>state.backgroundCheckpoint,
        read:async()=>structuredClone([...server.values()]),initial:async()=> 'local',conflicts:async conflicts=>({choices:Object.fromEntries(conflicts.map(row=>[row.id,'local'])),rules:{}}),
        begin:async(owner,choice)=>{state.backgroundCheckpoint={userId:owner,document:{},pendingInitial:choice};},
        finish:async()=>{delete state.backgroundCheckpoint.pendingInitial;},
        drop:async id=>{state.backgrounds=state.backgrounds.filter(row=>row.id!==id);files.delete(id);},
        file:async id=>files.get(id)||null,download:async item=>serverFiles.get(item.id),
        pending:owner=>state.backgroundUploadIntents?.[owner]||null,
        receipt:async requestId=>receipts.has(requestId)?structuredClone(receipts.get(requestId)):{status:404},
        prepare:async(intent,file)=>{const plan=prepareUpload(state,intent,file);state={...state,...plan.values};for(const operation of plan.files)files.set(operation.id,operation.blob);},
        abandon:async intent=>{const plan=clearUpload(state,intent);state={...state,...plan.values};for(const operation of plan.files)files.delete(operation.id);},
        accept:async options=>{const plan=backgroundAcknowledgement(state,options);state={...state,...plan.values};for(const operation of plan.files){if(operation.blob===null)files.delete(operation.id);else files.set(operation.id,operation.blob);}},
        create:async(item,owner,file=null,version=null,requestId=null)=>{
            if(requestId&&receipts.has(requestId))return structuredClone(receipts.get(requestId));
            if(conflictNext){conflictNext=false;return {status:409};}
            const existing=server.get(item.id);if(existing&&(version===null||version!==existing.version))return {status:409};
            const next={...item,version:(existing?.version||0)+1};writes++;
            if(file){const compressed=new Blob(['x'],{type:'image/png'});serverFiles.set(item.id,compressed);Object.assign(next,{sourceType:'upload',fileRevision:(++serial).toString(16).padStart(64,'0'),fileSize:compressed.size,url:'/api/backgrounds/'+item.id+'/file'});}
            server.set(item.id,next);const response={status:existing?200:201,data:{item:structuredClone(next)}};if(requestId)receipts.set(requestId,response);return response;
        },
        update:async(item,version,owner)=>{
            if(conflictNext){conflictNext=false;return {status:409};}
            const existing=server.get(item.id);if(!existing||existing.version!==version)return {status:409};
            const next={...existing,...item,version:version+1};if(item.sourceType==='upload')next.url='/api/backgrounds/'+item.id+'/file';server.set(item.id,next);writes++;return {status:200,data:{item:structuredClone(next)}};
        },status:value=>statuses.push(value)};
    return {io,files,statuses,get state(){return state;},session:new BackgroundSyncSession(io)};
}
const url={id:'url',name:'URL',type:'image',url:'https://example.test/image.png',sourceType:'url',cloudSync:true};
const a=device([url]);assert.equal(await a.session.run(),true);assert.equal(server.get('url').version,1);assert.equal(a.state.backgroundCheckpoint.document.url.name,'URL');
const b=device();assert.equal(await b.session.run(),true);assert.equal(b.state.backgrounds[0].name,'URL');
a.state.backgrounds[0].name='A edit';assert.equal(await a.session.run(),true);b.state.backgrounds[0].blur=12;assert.equal(await b.session.run(),true);assert.equal(server.get('url').name,'A edit');assert.equal(server.get('url').blur,12);
const upload={id:'file',name:'File',type:'image',sourceType:'upload',fileId:'file',fileVersion:1,fileSize:8,cloudSync:true};
const c=device([upload]);c.files.set('file',new Blob(['original'],{type:'image/png'}));assert.equal(await c.session.run(),true);assert.equal(await c.files.get('file').text(),'x');assert.equal(c.state.backgrounds.find(row=>row.id==='file').fileSize,1);
const d=device();assert.equal(await d.session.run(),true);assert.equal(await d.files.get('file').text(),'x');
const fileRow=c.state.backgrounds.find(row=>row.id==='file');fileRow.fileVersion++;fileRow.fileSize=9;fileRow.cloudSync=false;c.files.set('file',new Blob(['off-local'],{type:'image/png'}));
const previousWrites=writes;assert.equal(await c.session.run(),true);assert.equal(writes,previousWrites+1);assert.equal(server.get('file').cloudSync,false);assert.equal(await c.files.get('file').text(),'off-local');assert.equal(c.state.backgrounds.find(row=>row.id==='file').fileSize,9);
const e=device();assert.equal(await e.session.run(),true);assert.equal(e.state.backgrounds.some(row=>row.id==='file'),false);
const f=device([{...url,id:'retry',name:'Retry'}]);conflictNext=true;assert.equal(await f.session.run(),true);assert.equal(server.get('retry').name,'Retry');
const g=device([{...url,id:'later'}]);g.io.initial=async()=> 'later';assert.equal(await g.session.run(),false);assert.equal(g.session.paused,true);assert.equal(server.has('later'),false);
const h=device([{...url,id:'signedout'}]);h.io.user=async()=>null;assert.equal(await h.session.run(),false);assert.equal(h.statuses.at(-1),'signed_out');
const i=device([{...url,id:'switch'}]);i.io.current=async()=>false;assert.equal(await i.session.run(),false);assert.equal(server.has('switch'),false);
// A transfer may succeed before the compressed file can be fetched. Its ACK
// must survive and retry the download without uploading the original again.
const j=device([{...upload,id:'resume',fileId:'resume'}]);j.files.set('resume',new Blob(['original'],{type:'image/png'}));j.io.download=async()=>{throw new Error('offline');};
await assert.rejects(j.session.run(),/offline/);const acknowledgedVersion=server.get('resume').version;assert.ok(j.state.backgroundCheckpoint.document.resume);
j.io.download=async item=>serverFiles.get(item.id);assert.equal(await j.session.run(),true);assert.equal(server.get('resume').version,acknowledgedVersion);assert.equal(await j.files.get('resume').text(),'x');
// A failed ACK leaves the original immutable intent available after reload.
const k=device([{...upload,id:'ack-failure',fileId:'ack-failure'}]);k.files.set('ack-failure',new Blob(['original'],{type:'image/png'}));
const acceptK=k.io.accept;k.io.accept=async()=>{throw new Error('quota');};
await assert.rejects(k.session.run(),/quota/);const pendingK=k.io.pending('1');
assert.ok(pendingK);assert.equal(await k.files.get(pendingK.fileKey).text(),'original');
k.io.accept=acceptK;k.session=new BackgroundSyncSession(k.io);assert.equal(await k.session.run(),true);assert.equal(server.get('ack-failure').version,1);assert.equal(k.io.pending('1'),null);assert.equal(k.files.has(pendingK.fileKey),false);
const l=device([{...upload,id:'response-lost',fileId:'response-lost'}]);l.files.set('response-lost',new Blob(['original'],{type:'image/png'}));
const createL=l.io.create;let lost=true;l.io.create=async(...args)=>{const result=await createL(...args);if(lost){lost=false;throw new Error('connection_lost');}return result;};
await assert.rejects(l.session.run(),/connection_lost/);l.session=new BackgroundSyncSession(l.io);assert.equal(await l.session.run(),true);assert.equal(server.get('response-lost').version,1);
const m=device([{...upload,id:'edited-pending',fileId:'edited-pending'}]);m.files.set('edited-pending',new Blob(['original'],{type:'image/png'}));const acceptM=m.io.accept;m.io.accept=async()=>{throw new Error('quota');};
await assert.rejects(m.session.run(),/quota/);m.state.backgrounds[0].fileVersion=2;m.state.backgrounds[0].fileSize=11;m.state.backgrounds[0].name='New file';m.files.set('edited-pending',new Blob(['replacement'],{type:'image/png'}));
m.io.accept=async options=>{await acceptM(options);if(options.intent?.target.source[0].revision.endsWith(':1')){assert.equal(await m.files.get('edited-pending').text(),'replacement');assert.equal(m.state.backgrounds.find(row=>row.id==='edited-pending').fileVersion,2);}};
m.session=new BackgroundSyncSession(m.io);assert.equal(await m.session.run(),true);assert.equal(server.get('edited-pending').version,2);assert.equal(server.get('edited-pending').name,'New file');
const n=device([{...upload,id:'prepare-failure',fileId:'prepare-failure'}]);n.files.set('prepare-failure',new Blob(['original'],{type:'image/png'}));n.io.prepare=async()=>{throw new Error('quota');};const writesBeforePrepare=writes;
await assert.rejects(n.session.run(),/quota/);assert.equal(writes,writesBeforePrepare);assert.equal(server.has('prepare-failure'),false);
const o=device([{...upload,id:'cancelled-pending',fileId:'cancelled-pending'}]);o.files.set('cancelled-pending',new Blob(['original'],{type:'image/png'}));const createO=o.io.create;
o.io.create=async()=>{throw new Error('before_upload_offline');};await assert.rejects(o.session.run(),/before_upload_offline/);const cancelled=o.io.pending('1');
o.state.backgrounds[0].cloudSync=false;o.io.create=createO;o.session=new BackgroundSyncSession(o.io);assert.equal(await o.session.run(),true);assert.equal(server.has('cancelled-pending'),false);assert.equal(o.io.pending('1'),null);assert.equal(o.files.has(cancelled.fileKey),false);assert.equal(await o.files.get('cancelled-pending').text(),'original');
const p=device([{...upload,id:'account-pending',fileId:'account-pending'}]);p.files.set('account-pending',new Blob(['original'],{type:'image/png'}));p.io.create=async()=>{throw new Error('offline');};await assert.rejects(p.session.run(),/offline/);
const pendingP=p.io.pending('1');p.io.current=async()=>false;p.session=new BackgroundSyncSession(p.io);assert.equal(await p.session.run(),false);assert.equal(p.io.pending('1').requestId,pendingP.requestId);
const q=device([{...upload,id:'later-cloud-edit',fileId:'later-cloud-edit'}]);q.files.set('later-cloud-edit',new Blob(['original'],{type:'image/png'}));const acceptQ=q.io.accept;q.io.accept=async()=>{throw new Error('quota');};await assert.rejects(q.session.run(),/quota/);
await q.io.update({id:'later-cloud-edit',name:'Later cloud edit'},1,'1');q.io.accept=acceptQ;q.session=new BackgroundSyncSession(q.io);assert.equal(await q.session.run(),true);assert.equal(server.get('later-cloud-edit').version,2);assert.equal(q.state.backgrounds.find(row=>row.id==='later-cloud-edit').name,'Later cloud edit');
console.log('Background session: two devices, compression, opt-out, CAS retry, auth switch, Later and interrupted download resume passed.');
console.log('Upload intents: ACK failure/reload, lost response, later file edit, and prepare failure without upload passed.');
console.log('Upload recovery: receipt-only ACK, unsent opt-out cancellation, account isolation and subsequent cloud edit preservation passed.');
