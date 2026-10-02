import assert from 'node:assert/strict';
import {mergeSync} from '../public/assets/js/sync-core.js';
import {backgroundAcknowledgement} from '../public/assets/js/background-sync-ack.js';
import {backgroundRecord} from '../public/assets/js/background-sync-core.js';
import {syncedDataRemoval} from '../public/assets/js/account-data.js';
globalThis.window=new EventTarget();
const records=new Map();let abortNext=false;
const legacy={favorites:[{id:'legacy',name:'Migrated'}],settings:{theme:'legacy'}};
globalThis.localStorage={getItem:()=>JSON.stringify(legacy),setItem:()=>{throw new Error('LocalStorage should not be written after migration');}};
globalThis.BroadcastChannel=class {postMessage(){}close(){}};
const fileRecords=new Map(),stores=new Map([['state',records]]);
const db={objectStoreNames:{contains:name=>stores.has(name)},createObjectStore(name){stores.set(name,name==='background-files'?fileRecords:new Map());},close(){},transaction(names,mode){
    const staged=new Map(),aborted=mode==='readwrite'&&abortNext;if(mode==='readwrite')abortNext=false;
    const tx={error:aborted?new Error('quota'):null,objectStore:name=>({
        put:(value,key)=>{if(!staged.has(name))staged.set(name,new Map());staged.get(name).set(key,{value:structuredClone(value)});},
        delete:key=>{if(!staged.has(name))staged.set(name,new Map());staged.get(name).set(key,{deleted:true});},
        get:key=>{const request={};queueMicrotask(()=>{request.result=stores.get(name).get(key);request.onsuccess?.();});return request;},
        openCursor:()=>{
            const request={},entries=[...stores.get(name)],step=i=>queueMicrotask(()=>{
                request.result=i<entries.length?{key:entries[i][0],value:structuredClone(entries[i][1]),continue:()=>step(i+1)}:null;
                request.onsuccess();
            });step(0);return request;
        },
    })};
    setTimeout(()=>{if(aborted)tx.onabort();else{for(const [name,changes] of staged)for(const [key,change] of changes){if(change.deleted)stores.get(name).delete(key);else stores.get(name).set(key,change.value);}tx.oncomplete();}},0);
    return tx;
}};
globalThis.indexedDB={open:()=>{const request={};queueMicrotask(()=>{request.result=db;request.onupgradeneeded();request.onsuccess();});return request;}};
const store=await import('../public/assets/js/store.js?indexeddb-test');
assert.equal(store.get('favorites')[0].name,'Migrated');assert.equal(records.get('storageInitialized'),true);
const history=Array.from({length:300},(_,i)=>({id:String(i),query:'あ'.repeat(12000)}));
await store.setMany({history,syncCheckpoint:{version:7,document:{history}},favorites:[{id:'f',name:'Saved'}]});
assert.equal(records.get('history').length,300);assert.equal(records.get('syncCheckpoint').document.history[0].query.length,12000);
abortNext=true;
await assert.rejects(store.setMany({history:[],favorites:[],syncCheckpoint:{version:8}}),/quota/);
assert.equal(store.get('syncCheckpoint').version,7);assert.equal(records.get('syncCheckpoint').version,7);
assert.equal(store.get('history').length,300);assert.equal(records.get('favorites')[0].name,'Saved');
const saving=store.setMany({favorites:[{id:'f',name:'Cloud'}],syncCheckpoint:{version:8}});
await Promise.resolve();store.set('favorites',[{id:'f',name:'During write'}]);
await saving;await store.flush();
assert.equal(store.get('favorites')[0].name,'During write');assert.equal(records.get('favorites')[0].name,'During write');assert.equal(records.get('syncCheckpoint').version,8);
abortNext=true;store.set('settings',{theme:'Offline edit'});
await new Promise(resolve=>setTimeout(resolve,10));
assert.equal(store.setting('theme'),'Offline edit');assert.equal(records.get('settings').theme,'legacy');
await store.flush();assert.equal(records.get('settings').theme,'Offline edit');
const reloaded=await import('../public/assets/js/store.js?indexeddb-reload');
assert.equal(reloaded.get('syncCheckpoint').version,8);assert.equal(reloaded.get('history').length,300);assert.equal(reloaded.setting('theme'),'Offline edit');
const previous=store.get('settings');
const merging=store.setMany(state=>({settings:mergeSync({settings:previous},{settings:state.settings},{settings:{...previous,theme:'Cloud theme'}}).data.settings}));
await Promise.resolve();store.set('settings',{...previous,fontSize:22});
await merging;await store.flush();
assert.equal(store.setting('theme'),'Cloud theme');assert.equal(store.setting('fontSize'),22);
assert.deepEqual(records.get('settings'),{theme:'Cloud theme',fontSize:22});
const blob=new Blob(['local background'],{type:'image/png'});
await store.setMany({backgrounds:[{id:'media',fileId:'media'}]},[{id:'media',blob}]);
assert.equal(await (await store.backgroundFile('media')).text(),'local background');
assert.equal(records.get('backgrounds')[0].fileId,'media');
abortNext=true;
await assert.rejects(store.setMany({backgrounds:[{id:'failed'}]},[{id:'media',blob:new Blob(['replacement'])}]),/quota/);
assert.equal(store.get('backgrounds')[0].id,'media');
assert.equal(await (await store.backgroundFile('media')).text(),'local background');
const mediaReloaded=await import('../public/assets/js/store.js?background-file-reload');
assert.equal(await (await mediaReloaded.backgroundFile('media')).text(),'local background');
assert.equal(await mediaReloaded.backgroundFile('missing'),null);
await store.setMany({backgrounds:[]},[{id:'media',blob:null}]);
assert.equal(await store.backgroundFile('media'),null);
assert.deepEqual(records.get('backgrounds'),[]);
let selectedFiles=[];
const functional=store.setMany(state=>({backgrounds:[{id:'atomic',fileVersion:state.settings.fontSize}],backgroundCheckpoint:{version:1}}),state=>{
    selectedFiles.push(state.settings.fontSize);return [{id:'atomic',blob:new Blob([String(state.settings.fontSize)])}];
});
await Promise.resolve();store.set('settings',{...store.get('settings'),fontSize:24});await functional;await store.flush();
assert.equal(store.get('backgrounds')[0].fileVersion,24);assert.equal(await (await store.backgroundFile('atomic')).text(),'24');assert.ok(selectedFiles.includes(24));
let observedAtomic=false;window.addEventListener('data-change',event=>{if(event.detail==='backgrounds'&&store.get('backgrounds')[0]?.id==='paired')observedAtomic=store.get('backgroundCheckpoint').version===2;});
await store.setMany({backgrounds:[{id:'paired'}],backgroundCheckpoint:{version:2}},[]);assert.equal(observedAtomic,true);
abortNext=true;await assert.rejects(store.setMany(()=>({backgrounds:[],backgroundCheckpoint:{version:3}}),()=>[{id:'atomic',blob:null}]),/quota/);
assert.equal(store.get('backgroundCheckpoint').version,2);assert.equal(await (await store.backgroundFile('atomic')).text(),'24');
const localMedia={id:'transfer',name:'Upload',type:'image',sourceType:'upload',fileId:'transfer',fileVersion:1,fileSize:3,cloudSync:true};
await store.setMany({backgrounds:[localMedia]},[{id:'transfer',blob:new Blob(['old'],{type:'image/png'})}]);
const reply={...localMedia,fileRevision:'a'.repeat(64),url:'/api/backgrounds/transfer/file',fileSize:3,version:1};
const ackOptions={userId:'1',before:backgroundRecord(localMedia),target:backgroundRecord(localMedia),acknowledged:reply,blob:new Blob(['ack'],{type:'image/png'})};
const commitAck=()=>store.setMany(state=>backgroundAcknowledgement(state,ackOptions).values,state=>backgroundAcknowledgement(state,ackOptions).files);
abortNext=true;await assert.rejects(commitAck(),/quota/);assert.equal(store.get('backgroundCheckpoint').version,2);assert.equal(await (await store.backgroundFile('transfer')).text(),'old');
await commitAck();assert.equal(store.get('backgroundCheckpoint').document.transfer.source[0].revision,reply.fileRevision);assert.equal(await (await store.backgroundFile('transfer')).text(),'ack');assert.deepEqual(store.get('backgroundOwnership').ids,['transfer']);
await store.setMany({backgrounds:[{id:'remove',fileId:'remove',cloudSync:true,cloudOwner:'1'},{id:'retain',fileId:'retain',cloudSync:false}],backgroundOwnership:{userId:'1',ids:['remove','retain']},backgroundCheckpoint:{userId:'1',document:{}}},[{id:'remove',blob:new Blob(['owned'])},{id:'retain',blob:new Blob(['local'])}]);
const removeOwned=()=>store.setMany(state=>syncedDataRemoval(state,'1').values,state=>syncedDataRemoval(state,'1').files);
abortNext=true;await assert.rejects(removeOwned(),/quota/);assert.equal(await (await store.backgroundFile('remove')).text(),'owned');assert.equal(store.get('backgrounds').length,2);
await removeOwned();assert.equal(await store.backgroundFile('remove'),null);assert.equal(await (await store.backgroundFile('retain')).text(),'local');assert.deepEqual(store.get('backgrounds').map(row=>row.id),['retain']);assert.equal(store.get('backgroundOwnership',null),null);
console.log('47 IndexedDB storage assertions passed.');
