import assert from 'node:assert/strict';
import {apiTarget,apiFetch,accountUrl,isOnline,storageNamespace} from '../public/assets/js/api-transport.js';
import {writeSync,syncUser} from '../public/assets/js/sync-api.js';
import {createBackground,downloadBackground} from '../public/assets/js/background-api.js';
import {requireFeatures} from '../public/assets/js/site-policy.js';
import {WeatherContext} from '../public/assets/js/weather-context.js';

const context={extension:true,serverOrigin:'https://server.example'};
assert.equal(apiTarget('/api/user'),'/api/user');
assert.equal(apiTarget('/api/user',context),'https://server.example/api/user');
assert.equal(apiTarget('/api/favorites/metadata?url=https%3A%2F%2Fother.example',context),'https://server.example/api/favorites/metadata?url=https%3A%2F%2Fother.example');
for(const path of ['https://other.example/api/user','//other.example/api/user','/api/../admin','/api/backgrounds/../../user','/api/backgrounds/%2e%2e/file','/api/user#x','/api/user\n','/api//user','/api\\user'])assert.throws(()=>apiTarget(path,context),/API_PATH_INVALID/);
for(const path of ['/api/admin/users','/auth/login','/api/arbitrary','/api/backgrounds/id/download'])assert.throws(()=>apiTarget(path,context),/API_PATH_INVALID/);
for(const serverOrigin of ['http://server.example','https://user:password@server.example','https://server.example/path','https://server.example/?key=secret','https://server.example/#x','file:///tmp','javascript:alert(1)','https://server.example/../'])assert.throws(()=>apiTarget('/api/user',{extension:true,serverOrigin}),/SERVER_ORIGIN_INVALID/);
for(const serverOrigin of ['http://localhost:8115','http://127.0.0.1:8115','http://[::1]:8115'])assert.equal(apiTarget('/api/user',{extension:true,serverOrigin}),serverOrigin+'/api/user');

const original=Object.fromEntries(['navigator','location','document','fetch'].map(key=>[key,Object.getOwnPropertyDescriptor(globalThis,key)]));
let online=true,extension=false,calls=[],serverOrigin=context.serverOrigin;
Object.defineProperty(globalThis,'navigator',{configurable:true,value:{get onLine(){return online;}}});
Object.defineProperty(globalThis,'location',{configurable:true,value:{get protocol(){return extension?'chrome-extension:':'https:';}}});
Object.defineProperty(globalThis,'document',{configurable:true,value:{getElementById:()=>({textContent:JSON.stringify({platform:{kind:'extension',serverOrigin}})})}});
const json=(data,status=200)=>new Response(JSON.stringify({success:status<400,data}),{status});
try{
    globalThis.fetch=async(url,options)=>{calls.push({url,options});return json({});};
    await apiFetch('/api/user',{credentials:'omit',redirect:'follow',cache:'force-cache'});
    assert.equal(calls[0].url,'/api/user');assert.equal(calls[0].options.credentials,'same-origin');assert.equal(calls[0].options.redirect,'error');assert.equal(calls[0].options.cache,'no-store');
    extension=true;await apiFetch('/api/user',{credentials:'omit',redirect:'follow'});
    assert.equal(calls.at(-1).url,'https://server.example/api/user');assert.equal(calls.at(-1).options.credentials,'include');assert.equal(calls.at(-1).options.redirect,'error');assert.ok(calls.at(-1).options.signal instanceof AbortSignal);
    assert.equal(accountUrl(),'https://server.example/account');
    const namespace=await storageNamespace();assert.match(namespace.database,/^extension-[a-f0-9]{64}$/);assert.deepEqual(await storageNamespace(),namespace);
    serverOrigin='https://other.example';assert.notEqual((await storageNamespace()).database,namespace.database);assert.notEqual((await storageNamespace()).legacy,namespace.legacy);serverOrigin=context.serverOrigin;
    const before=calls.length;
    await assert.rejects(apiFetch('/api/admin/users'),/API_PATH_INVALID/);assert.equal(calls.length,before);
    online=false;assert.equal(isOnline(),false);
    await assert.rejects(apiFetch('/api/user'),/OFFLINE/);await assert.rejects(writeSync(2,{settings:{theme:'dark'}},'7'),/OFFLINE/);await assert.rejects(syncUser(),/OFFLINE/);
    assert.equal(calls.length,before,'offline identity does not become a fabricated signed-out response and no write is attempted');
    online=true;
    globalThis.fetch=async(url,options)=>{
        calls.push({url,options});assert.equal(options.credentials,'include');assert.equal(options.redirect,'error');
        if(url.endsWith('/api/csrf'))return json({csrf_token:'generated-test-csrf'});
        if(url.endsWith('/api/user'))return json({user:{id:'7'}});
        if(url.endsWith('/api/site-policy'))return json({flags:{cloud_sync:true}});
        if(url.endsWith('/api/sync'))return json({version:3,document:JSON.parse(options.body).document});
        if(url.endsWith('/api/backgrounds/url'))return json({item:{id:'sky'}},201);
        if(url.endsWith('/api/backgrounds/sky/file'))return new Response(new Uint8Array([137,80,78,71]),{headers:{'Content-Type':'image/png','Content-Length':'4'}});
        if(url.endsWith('/api/weather'))return json({weather:'rain',temperature:12,observedAt:1700000000,expiresAt:1700000900});
        throw new Error('unexpected API target');
    };
    assert.equal((await syncUser()).id,'7');await requireFeatures(['cloud_sync']);
    const document={settings:{theme:'forest'},favorites:{}};
    assert.equal((await writeSync(2,document,'7')).status,200);
    const sync=calls.findLast(call=>call.url.endsWith('/api/sync'));
    assert.equal(sync.options.headers['X-CSRF-Token'],'generated-test-csrf');assert.deepEqual(JSON.parse(sync.options.body),{version:2,document,user_id:'7'});
    await createBackground({id:'sky',type:'solid'},'7');
    assert.equal(calls.at(-1).options.headers['X-CSRF-Token'],'generated-test-csrf');assert.equal(JSON.parse(calls.at(-1).options.body).user_id,'7');
    const blob=await downloadBackground({id:'sky',type:'image',sourceType:'upload',fileSize:4,url:'https://other.example/private'},'7');
    assert.equal(blob.size,4);assert.equal(calls.at(-1).url,'https://server.example/api/backgrounds/sky/file');assert.equal(calls.at(-1).options.headers['X-Background-Owner'],'7');
    const weather=new WeatherContext({clock:()=>1700000000000});weather.read({latitude:35,longitude:139});await weather.pending;
    assert.equal(calls.at(-1).url,'https://server.example/api/weather');assert.equal(calls.at(-1).options.headers['X-CSRF-Token'],'generated-test-csrf');
    globalThis.fetch=async()=>json({},401);assert.equal(await syncUser(),null);
    globalThis.fetch=async()=>json({},503);await assert.rejects(syncUser(),/sync_auth_unavailable/);
    extension=false;assert.equal(accountUrl(),'/account');assert.deepEqual(await storageNamespace(),{database:'search-startpage',legacy:'search-startpage-v1'});
    console.log('API transport destination, credentials, offline suppression, reconnect, CSRF, identity, background and weather checks passed.');
}finally{
    for(const [key,descriptor] of Object.entries(original)){if(descriptor)Object.defineProperty(globalThis,key,descriptor);else delete globalThis[key];}
}
