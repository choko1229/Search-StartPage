import assert from 'node:assert/strict';
import {offlineMediaTarget,retrieveOfflineMedia,offlineCopyValues,backgroundAccount,offlineCopyGuard} from '../public/assets/js/background-offline-core.js';
const source={id:'original',name:'Original',type:'image',sourceType:'url',url:'https://images.example:9443/photo.png?key=generated',cloudSync:true,localOnly:false,version:8};
const target=offlineMediaTarget(source);assert.equal(target.permission,'https://images.example/*');assert.equal(target.url,source.url);
globalThis.location={href:'https://server.example/newtab.html',origin:'https://server.example'};
assert.equal(offlineMediaTarget({...source,url:'/assets/backgrounds/photo.png'}).url,'https://server.example/assets/backgrounds/photo.png');
assert.throws(()=>offlineMediaTarget({...source,url:'//other.example/photo.png'}),/OFFLINE_INVALID/);
assert.throws(()=>offlineMediaTarget({...source,url:'/\\other.example/photo.png'}),/OFFLINE_INVALID/);
for(const url of ['javascript:alert(1)','https://user:password@images.example/a','file:///tmp/image'])assert.throws(()=>offlineMediaTarget({...source,url}),/OFFLINE_INVALID/);
const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aNC8AAAAASUVORK5CYII=','base64');
let requests=0,releases=0;
const access=async pattern=>{assert.equal(pattern,'https://images.example/*');return async()=>{releases++;};};
const fetcher=async(url,options)=>{requests++;assert.equal(url,source.url);assert.equal(options.credentials,'omit');assert.equal(options.redirect,'error');assert.equal(options.cache,'no-store');assert.equal(options.headers,undefined);return new Response(png,{headers:{'Content-Type':'image/png','Content-Length':String(png.length)}});};
const downloaded=await retrieveOfflineMedia(source,{fetcher,access});assert.equal(requests,1);assert.equal(releases,1);assert.equal(downloaded.metadata.type,'image');assert.equal(downloaded.file.size,png.length);
await assert.rejects(retrieveOfflineMedia(source,{fetcher,access:async()=>false}),/OFFLINE_PERMISSION/);assert.equal(requests,1,'permission denial sends no network request');
for(const [headers,body,pattern] of [
    [{'Content-Type':'text/html'},'<script>bad</script>',/MIME_MISMATCH/],
    [{'Content-Type':'image/png'},'<script>bad</script>',/MIME_MISMATCH/],
    [{'Content-Type':'image/png','Content-Length':String(26*1024*1024)},png,/TOO_LARGE/],
    [{'Content-Type':'image/png','Content-Length':'1'},png,/SIZE_INVALID/],
]){await assert.rejects(retrieveOfflineMedia(source,{access,fetcher:async()=>new Response(body,{headers})}),pattern);}
assert.equal(releases,5,'every acquired temporary permission is released after failure');
await assert.rejects(retrieveOfflineMedia(source,{access,fetcher:async()=>{throw new Error('network outage');}}),/network outage/);assert.equal(releases,6);
const state={settings:{backgroundMode:'library',backgroundSelected:'original',theme:'rose'},backgroundCheckpoint:{userId:'1'},backgrounds:[source]};
const before=structuredClone(state),intent={id:'original',url:source.url,type:'image',account:backgroundAccount(state),selection:'original',mode:'library'};
assert.deepEqual(Object.keys(offlineCopyGuard(state)),['backgrounds','settings','settingsHistory','syncOwnership','backgroundCheckpoint','syncCheckpoint']);
assert.equal(offlineCopyGuard(state).backgrounds,state.backgrounds);assert.equal(offlineCopyGuard(state).syncOwnership,null);
const patch=offlineCopyValues(state,intent,'device-copy',downloaded.metadata,'(device copy)','2026-10-06T00:00:00Z');
assert.deepEqual(state,before,'preparation does not modify the original or settings');
assert.equal(patch.backgrounds.length,2);assert.deepEqual(patch.backgrounds[0],source);
const copy=patch.backgrounds[1];assert.equal(copy.id,copy.fileId);assert.equal(copy.sourceType,'upload');assert.equal(copy.localOnly,true);assert.equal(copy.cloudSync,false);assert.equal(copy.url,null);assert.equal(copy.version,undefined);
assert.equal(patch.settings.backgroundSelected,'device-copy');assert.equal(patch.settings.theme,'rose');assert.equal(patch.settingsHistory.entries.length,1);
const changed=structuredClone(state);changed.settings.backgroundSelected='new-choice';assert.equal(offlineCopyValues(changed,intent,'copy-2',downloaded.metadata,'copy').settings,undefined,'intervening selection is preserved');
for(const mutate of [value=>value.backgroundCheckpoint.userId='2',value=>value.backgrounds[0].url='https://other.example/photo.png',value=>value.backgrounds[0].deleted=true]){
    const changed=structuredClone(state);mutate(changed);assert.throws(()=>offlineCopyValues(changed,intent,'copy-3',downloaded.metadata,'copy'),/BACKGROUND_CHANGED/);
}
console.log('Offline media anonymous transport, permission denial/release, byte/MIME bounds, immutable original, device-only copy, selection and account races passed.');
