import assert from 'node:assert/strict';
import {mergeSync} from '../public/assets/js/sync-core.js';
import {backgroundAcknowledgement} from '../public/assets/js/background-sync-ack.js';
import {backgroundRecord} from '../public/assets/js/background-sync-core.js';
import {syncedDataRemoval} from '../public/assets/js/account-data.js';
import {uploadIntent,prepareUpload,clearUpload,uploadGuard} from '../public/assets/js/background-upload-intent.js';
globalThis.window=new EventTarget();
const records=new Map();let abortNext=false;
const legacy={favorites:[{id:'legacy',name:'Migrated'}],settings:{theme:'legacy'}};
globalThis.localStorage={getItem:()=>JSON.stringify(legacy),setItem:()=>{throw new Error('LocalStorage should not be written after migration');}};
globalThis.BroadcastChannel=class {postMessage(){}close(){}};
const fileRecords=new Map(),stores=new Map([['state',records]]);
const db={objectStoreNames:{contains:name=>stores.has(name)},createObjectStore(name){stores.set(name,name==='background-files'?fileRecords:new Map());},close(){},transaction(names,mode){
    const staged=new Map();let aborted=mode==='readwrite'&&abortNext;if(mode==='readwrite')abortNext=false;
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
    }),abort:()=>{aborted=true;tx.error??=new Error('aborted');}};
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
await assert.rejects(store.setMany({backgrounds:[],backgroundCheckpoint:{version:99}},[{id:'retain',blob:()=>{}}]),/clone/i);
await new Promise(resolve=>setTimeout(resolve,5));assert.deepEqual(records.get('backgrounds').map(row=>row.id),['retain']);assert.equal(await (await store.backgroundFile('retain')).text(),'local');
await store.setMany({backgrounds:[localMedia],backgroundUploadIntents:{}},[{id:'transfer',blob:new Blob(['old'],{type:'image/png'})}]);
const intent=uploadIntent({userId:'1',before:backgroundRecord(localMedia),target:backgroundRecord(localMedia)},new Blob(['old'],{type:'image/png'}));
const prepare=()=>store.setMany(state=>prepareUpload(state,intent,new Blob(['old'],{type:'image/png'})).values,state=>prepareUpload(state,intent,new Blob(['old'],{type:'image/png'})).files,uploadGuard);
abortNext=true;await assert.rejects(prepare(),/quota/);assert.equal(store.get('backgroundUploadIntents')['1'],undefined);assert.equal(await store.backgroundFile(intent.fileKey),null);
await prepare();assert.equal(await (await store.backgroundFile(intent.fileKey)).text(),'old');
const intentReload=await import('../public/assets/js/store.js?intent-reload');assert.equal(intentReload.get('backgroundUploadIntents')['1'].requestId,intent.requestId);
const intentAck={...ackOptions,blob:null,retainSentOriginal:true,intent};
abortNext=true;await assert.rejects(store.setMany(state=>backgroundAcknowledgement(state,intentAck).values,state=>backgroundAcknowledgement(state,intentAck).files,uploadGuard),/quota/);
assert.equal(await (await store.backgroundFile(intent.fileKey)).text(),'old');assert.equal(store.get('backgroundUploadIntents')['1'].requestId,intent.requestId);
const guardedAck=store.setMany(state=>backgroundAcknowledgement(state,intentAck).values,state=>backgroundAcknowledgement(state,intentAck).files,uploadGuard);
await Promise.resolve();store.set('settings',{...store.get('settings'),fontSize:26});await guardedAck;await store.flush();assert.equal(store.setting('fontSize'),26);
assert.equal(await store.backgroundFile(intent.fileKey),null);assert.equal(store.get('backgroundUploadIntents')['1'],undefined);
await assert.rejects(store.setMany({backgroundUploadIntents:{},backgrounds:[]},[{id:'transfer',blob:null}],{backgroundUploadIntents:{stale:true}}),/storage_conflict/);
assert.equal(await (await store.backgroundFile('transfer')).text(),'old');assert.equal(records.get('backgrounds').length,1);
console.log('IndexedDB: original 49 assertions plus upload prepare/ACK rollback, reload and conditional write conflict passed.');

const {createPaletteStorage}=await import('../public/assets/js/palette-storage.js');
const palette=createPaletteStorage(store);
await store.setMany({history:[{id:'palette-history',query:'Generated history'}],favorites:[{id:'remove'},{id:'keep'}]});
abortNext=true;await assert.rejects(palette.clearHistory(),/quota/);
assert.equal(store.get('history')[0].id,'palette-history');assert.equal(records.get('history')[0].id,'palette-history');
await palette.clearHistory();assert.deepEqual(store.get('history'),[]);assert.deepEqual(records.get('history'),[]);
abortNext=true;await assert.rejects(palette.deleteFavorite('remove'),/quota/);
assert.deepEqual(store.get('favorites').map(row=>row.id),['remove','keep']);
await palette.deleteFavorite('remove');assert.deepEqual(records.get('favorites').map(row=>row.id),['keep']);
console.log('Palette storage: history/favorite actions retain data on transaction abort and commit on retry.');
const logoutIntent={userId:'1',clear:true};
await store.setMany({paletteLogoutPending:null,settings:{clearSyncedOnLogout:true},favorites:[{id:'owned'},{id:'private'}],
    syncOwnership:{userId:'1',collections:{favorites:['owned']},settings:[]},
    backgrounds:[{id:'owned',fileId:'owned',cloudSync:true,cloudOwner:'1'},{id:'off',fileId:'off',cloudSync:false}],
    backgroundOwnership:{userId:'1',ids:['owned','off']}},[{id:'owned',blob:new Blob(['cloud'])},{id:'off',blob:new Blob(['local'])}]);
abortNext=true;await assert.rejects(palette.prepare(logoutIntent),/quota/);assert.equal(palette.pending(),null);
await palette.prepare(logoutIntent);assert.deepEqual(palette.pending(),logoutIntent);
abortNext=true;await assert.rejects(palette.complete(logoutIntent),/quota/);
assert.deepEqual(palette.pending(),logoutIntent);assert.equal(await (await store.backgroundFile('owned')).text(),'cloud');
assert.deepEqual(store.get('favorites').map(row=>row.id),['owned','private']);
await palette.complete(logoutIntent);
assert.equal(palette.pending(),null);assert.deepEqual(store.get('favorites').map(row=>row.id),['private']);
assert.equal(await store.backgroundFile('owned'),null);assert.equal(await (await store.backgroundFile('off')).text(),'local');
const reloadedPaletteStore=await import('../public/assets/js/store.js?palette-reloaded');
assert.equal(reloadedPaletteStore.get('paletteLogoutPending',null),null);
assert.deepEqual(reloadedPaletteStore.get('favorites').map(row=>row.id),['private']);
console.log('Palette logout storage: failed prepare/cleanup roll back intent, owned data and Blob together; retry persists cleanup.');
const replacement={userId:'2',clear:false};await palette.prepare(replacement);
await palette.complete(logoutIntent);assert.deepEqual(palette.pending(),replacement);assert.equal(await (await store.backgroundFile('off')).text(),'local');
await palette.complete(replacement);assert.equal(palette.pending(),null);assert.equal(store.get('favorites')[0].id,'private');
assert.equal(await (await store.backgroundFile('off')).text(),'local');
console.log('Palette logout storage: stale intent is ignored and clear OFF retains data and files.');
await palette.prepare(logoutIntent);
await store.setMany({backgrounds:[{id:'racing',fileId:'racing',cloudSync:true,cloudOwner:'1'}],backgroundOwnership:{userId:'1',ids:['racing']}},[{id:'racing',blob:new Blob(['retained'])}]);
// Another tab commits a new intent before this tab receives its notification.
records.set('paletteLogoutPending',structuredClone(replacement));
await assert.rejects(palette.complete(logoutIntent),/storage_conflict/);
assert.deepEqual(palette.pending(),replacement);assert.equal(await (await store.backgroundFile('racing')).text(),'retained');
assert.equal(records.get('backgrounds')[0].id,'racing');
console.log('Palette logout storage: conditional commit rejects another tab replacing the intent before cleanup.');
