import assert from 'node:assert/strict';
import {request as httpRequest} from 'node:http';
import {apiFetch} from '../public/assets/js/api-transport.js';
import {request,syncUser,writeSync} from '../public/assets/js/sync-api.js';
import {createBackground,readBackgrounds,downloadBackground} from '../public/assets/js/background-api.js';
import {clientPresets} from '../public/assets/js/provider-presets.js';
const [physical,virtual,roundText]=process.argv.slice(2),round=Number(roundText);
if(!['http://127.0.0.1:8115','http://127.0.0.1:8116'].includes(physical)||!['http://extension-mysql.localhost:8115','http://extension-mariadb.localhost:8116'].includes(virtual)||!Number.isInteger(round)||round<1||round>3)throw new Error('Dedicated extension HTTP origin required');
const web={extension:false,online:true,cookies:new Map()},extension={extension:true,online:true,cookies:new Map()},other={extension:false,online:true,cookies:new Map()},guest={extension:false,online:true,cookies:new Map()};
let current=guest,count=0,network=0;
const check=(ok,name)=>{assert.ok(ok,name);count++;console.log('PASS: '+name);};
Object.defineProperty(globalThis,'location',{configurable:true,value:{get protocol(){return current.extension?'chrome-extension:':'http:';}}});
Object.defineProperty(globalThis,'navigator',{configurable:true,value:{get onLine(){return current.online;}}});
globalThis.document={getElementById:()=>({textContent:JSON.stringify({platform:{kind:'extension',serverOrigin:virtual}})})};
async function wire(path,options={}){
    network++;
    const headers=new Headers(options.headers);headers.set('Host',new URL(virtual).host);
    headers.set('Cookie',[...current.cookies].map(([key,value])=>key+'='+value).join('; '));
    if(current.extension)headers.set('Origin','chrome-extension://aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
    // Node's Fetch replaces the virtual Host header. Send actual HTTP to the fixed loopback socket
    // while retaining the test host and real server-issued cookie jar. This is not a browser proof.
    const encoded=new Request(physical+path,{method:options.method??'GET',headers,body:options.body});
    const body=Buffer.from(await encoded.arrayBuffer()),outgoing=Object.fromEntries(encoded.headers);
    outgoing.host=new URL(virtual).host;if(body.length)outgoing['content-length']=String(body.length);
    const destination=new URL(physical);
    const response=await new Promise((resolve,reject)=>{
        const request=httpRequest({hostname:destination.hostname,port:destination.port,path,method:encoded.method,headers:outgoing},message=>{
            const chunks=[];let size=0;
            message.on('data',chunk=>{size+=chunk.length;if(size>16*1024*1024){request.destroy(new Error('Oversized isolated response'));return;}chunks.push(chunk);});
            message.on('error',reject);message.on('end',()=>{
                const received=new Headers();for(let i=0;i<message.rawHeaders.length;i+=2)received.append(message.rawHeaders[i],message.rawHeaders[i+1]);
                resolve(new Response(Buffer.concat(chunks),{status:message.statusCode,headers:received}));
            });
        });
        request.on('error',reject);request.setTimeout(15000,()=>request.destroy(new Error('Isolated HTTP timeout')));
        options.signal?.addEventListener('abort',()=>request.destroy(new Error('Isolated HTTP aborted')),{once:true});
        request.end(body);
    });
    for(const cookie of response.headers.getSetCookie()){const match=/^([^=]+)=([^;]*)/.exec(cookie);if(match)current.cookies.set(match[1],match[2]);}
    return response;
}
globalThis.fetch=async(target,options)=>{
    if(current.extension){assert.equal(options.credentials,'include');assert.ok(target.startsWith(virtual+'/api/'));target=target.slice(virtual.length);}
    else assert.equal(options.credentials,'same-origin');
    assert.equal(options.redirect,'error');return wire(target,options);
};
let ready=false,readiness='unobserved';
for(let attempt=0;attempt<50;attempt++){
    try{const response=await wire('/api/user');readiness='HTTP '+response.status;if(response.status===401){ready=true;break;}}catch(error){readiness=error.cause?.code??error.code??'network_error';}
    await new Promise(resolve=>setTimeout(resolve,200));
}
if(!ready)throw new Error('Dedicated extension HTTP service did not become ready: '+readiness);
async function login(device,account){
    current=device;const page=await wire('/_test/login');assert.equal(page.status,200);
    const html=await page.text(),match=/name="csrf" value="([a-f0-9]{64})"/.exec(html);assert.ok(match);
    const response=await wire('/_test/login',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf:match[1],account})});assert.equal(response.status,303);
    return (await syncUser()).id;
}
check((await request('/api/user')).status===401,'guest identity remains unauthorized');
check((await request('/api/sync')).status===401,'guest cloud data denied');
const user=String(await login(web,'primary')),extensionUser=String(await login(extension,'primary')),otherUser=String(await login(other,'other'));
check(user===extensionUser&&user!==otherUser,'independent Web and extension devices bind the same generated account');
current=web;check((await wire('/api/admin/users')).status===403,'generated account has no administrator access');
current=extension;
const defaults=await request('/api/provider-presets');check(defaults.status===200&&clientPresets(defaults.data.presets).web.length>0,'real shared provider preset API is available');
const before=await request('/api/sync');assert.equal(before.status,200);
const document={settings:{theme:'forest'}};
const without=await apiFetch('/api/sync',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({version:before.data.version,document,user_id:user})});
check(without.status===403,'real sync endpoint requires CSRF');
const accepted=await writeSync(before.data.version,document,user);check(accepted.status===200&&accepted.data.document.settings.theme==='forest','extension transport writes through real CSRF and owner checks');
current=web;const received=await request('/api/sync');check(received.data.document.settings.theme==='forest'&&received.data.version===accepted.data.version,'Web reads the extension setting at the same revision');
check((await writeSync(before.data.version,document,user)).status===409,'stale write returns the real conflict response');
current=other;check((await writeSync(0,document,user)).status===403,'another account cannot claim the primary owner');
check(!(await request('/api/sync')).data.document.settings?.theme,'another account cannot read the primary setting');
current=extension;extension.online=false;const networkBefore=network;
await assert.rejects(writeSync(accepted.data.version,{settings:{theme:'rose'}},user),/OFFLINE/);
check(network===networkBefore,'offline extension sends no request or false acknowledgement');
extension.online=true;
const restored=await writeSync(accepted.data.version,{settings:{theme:'rose'}},user);check(restored.status===200,'reconnection submits the retained edit to the real server');
current=web;check((await request('/api/sync')).data.document.settings.theme==='rose','Web reads the edit after reconnection');
current=extension;
const bytes=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aNC8AAAAASUVORK5CYII=','base64');
const created=await createBackground({id:'extension-http-'+round,name:'Generated extension image',type:'image'},user,new Blob([bytes],{type:'image/png'}));
check(created.status===201&&created.data.item.sourceType==='upload','real extension multipart upload stores generated image bytes');
const downloaded=await downloadBackground(created.data.item,user);check(Buffer.from(await downloaded.arrayBuffer()).equals(bytes),'real extension background download matches the uploaded bytes');
current=web;const cloud=await readBackgrounds(user);check(cloud.data.items.some(item=>item.id==='extension-http-'+round),'Web reads the extension cloud background');
current=other;check((await readBackgrounds(user)).status===403,'private background owner binding rejects another account');
current=extension;const invalid=await createBackground({id:'invalid-'+round,name:'Invalid',url:'javascript:alert(1)'},user);check(invalid.status===422,'real background validation rejects executable URLs');
console.log(`${count} real extension HTTP transport checks passed (browser privilege/cookie behavior still requires actual Chrome).`);
