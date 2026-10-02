import assert from 'node:assert/strict';
import {mergeSync} from '../public/assets/js/sync-core.js';
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
console.log('30 IndexedDB storage assertions passed.');
