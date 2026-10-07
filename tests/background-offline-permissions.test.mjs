import assert from 'node:assert/strict';
const origin='https://images.example/*',item={id:'photo',name:'Photo',type:'image',sourceType:'url',url:'https://images.example/photo.png'};
const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aNC8AAAAASUVORK5CYII=','base64');
let online=true,granted=false,held=false,required=[],requests=0,fetches=0,removed=[];
globalThis.location={protocol:'chrome-extension:'};
Object.defineProperty(globalThis,'navigator',{configurable:true,value:{get onLine(){return online;}}});
globalThis.chrome={runtime:{getManifest:()=>({host_permissions:required})},permissions:{
    contains:async input=>{assert.deepEqual(input,{origins:[origin]});return held;},
    request:async input=>{assert.deepEqual(input,{origins:[origin]});requests++;return granted;},
    remove:async input=>{removed.push(input);return true;},
}};
globalThis.fetch=async(url,options)=>{fetches++;assert.equal(url,item.url);assert.equal(options.credentials,'omit');return new Response(png,{headers:{'Content-Type':'image/png'}});};
const {saveOfflineMedia,canSaveOfflineMedia}=await import('../public/assets/js/background-offline.js');
await assert.rejects(saveOfflineMedia(item),/OFFLINE_PERMISSION/);assert.equal(fetches,0);assert.equal(removed.length,0);
granted=true;assert.equal((await saveOfflineMedia(item)).file.size,png.length);assert.deepEqual(removed,[{origins:[origin]}]);
held=true;await saveOfflineMedia(item);assert.equal(removed.length,1,'pre-existing permission is retained');
held=false;required=[origin];await saveOfflineMedia(item);assert.equal(removed.length,1,'withheld required permission is not removed after user grants it');
online=false;const before=requests;await assert.rejects(saveOfflineMedia(item),/OFFLINE/);assert.equal(requests,before,'offline does not open permission requests');
online=true;globalThis.location={protocol:'https:',origin:'https://server.example'};
assert.equal(canSaveOfflineMedia(item),false);await assert.rejects(saveOfflineMedia(item),/EXTENSION_REQUIRED/);assert.equal(requests,before,'Web CSP is not bypassed by requesting an external resource');
assert.equal(canSaveOfflineMedia({...item,url:'https://server.example/photo.png'}),true);
console.log('Exact-origin permission request, denial before network, temporary release, existing/required retention and offline suppression passed.');
