import assert from 'node:assert/strict';
import {BackgroundSyncSession} from '../public/assets/js/background-sync-session.js';
import {backgroundAcknowledgement} from '../public/assets/js/background-sync-ack.js';
import {backgroundRecord} from '../public/assets/js/background-sync-core.js';
const server=new Map(),serverFiles=new Map();let serial=0,writes=0,conflictNext=false;
function device(rows=[]) {
    let state={backgrounds:structuredClone(rows)};const files=new Map(),statuses=[];
    const io={user:async()=>({id:'1'}),current:async()=>true,local:()=>state.backgrounds,checkpoint:()=>state.backgroundCheckpoint,
        read:async()=>structuredClone([...server.values()]),initial:async()=> 'local',conflicts:async conflicts=>({choices:Object.fromEntries(conflicts.map(row=>[row.id,'local'])),rules:{}}),
        begin:async(owner,choice)=>{state.backgroundCheckpoint={userId:owner,document:{},pendingInitial:choice};},
        finish:async()=>{delete state.backgroundCheckpoint.pendingInitial;},
        drop:async id=>{state.backgrounds=state.backgrounds.filter(row=>row.id!==id);files.delete(id);},
        file:async id=>files.get(id)||null,download:async item=>serverFiles.get(item.id),
        accept:async options=>{const plan=backgroundAcknowledgement(state,options);state={...state,...plan.values};for(const operation of plan.files){if(operation.blob===null)files.delete(operation.id);else files.set(operation.id,operation.blob);}},
        create:async(item,owner,file=null,version=null)=>{
            if(conflictNext){conflictNext=false;return {status:409};}
            const existing=server.get(item.id);if(existing&&(version===null||version!==existing.version))return {status:409};
            const next={...item,version:(existing?.version||0)+1};writes++;
            if(file){const compressed=new Blob(['x'],{type:'image/png'});serverFiles.set(item.id,compressed);Object.assign(next,{sourceType:'upload',fileRevision:(++serial).toString(16).padStart(64,'0'),fileSize:compressed.size,url:'/api/backgrounds/'+item.id+'/file'});}
            server.set(item.id,next);return {status:existing?200:201,data:{item:structuredClone(next)}};
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
console.log('Background session: two devices, compression, opt-out, CAS retry, auth switch, Later and interrupted download resume passed.');
