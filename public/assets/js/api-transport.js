// All authenticated requests stay on the selected installation's API origin.
// Extension pages use host permission; they do not expose a general URL proxy.
export function isOnline() {return globalThis.navigator?.onLine!==false;}
export function isExtension() {return globalThis.location?.protocol==='chrome-extension:';}
export function apiTarget(path,{extension=false,serverOrigin=null}={}) {
    if(typeof path!=='string'||!path.startsWith('/api/')||path.includes('\\')||path.includes('#')||/[\u0000-\u0020\u007f]/.test(path))throw new Error('API_PATH_INVALID');
    const pathname=path.split('?')[0];
    if(pathname.includes('%')||pathname.slice(1).split('/').some(part=>part==='.'||part==='..'||part===''))throw new Error('API_PATH_INVALID');
    if(!extension)return path;
    if(typeof serverOrigin!=='string'||!/^https?:\/\/(?:[A-Za-z0-9.-]+|\[::1\])(?::\d{1,5})?\/?$/.test(serverOrigin))throw new Error('SERVER_ORIGIN_INVALID');
    let origin;try{origin=new URL(serverOrigin);}catch{throw new Error('SERVER_ORIGIN_INVALID');}
    if(origin.username||origin.password||origin.pathname!=='/'||origin.search||origin.hash
        ||!(origin.protocol==='https:'||(origin.protocol==='http:'&&['localhost','127.0.0.1','[::1]'].includes(origin.hostname))))throw new Error('SERVER_ORIGIN_INVALID');
    if(!/^\/api\/(?:csrf|user|sync|site-policy|provider-presets|auth\/logout|statistics\/event|search\/suggest|favorites\/metadata|weather|backgrounds(?:\/(?:url|upload|receipts\/[a-f0-9]{64}|[A-Za-z0-9_-]{1,80}(?:\/(?:upload|file))?))?)$/.test(pathname))throw new Error('API_PATH_INVALID');
    return origin.origin+path;
}
function platform() {
    if(globalThis.location?.protocol!=='chrome-extension:')return {extension:false};
    let boot;try{boot=JSON.parse(document.getElementById('search-bootstrap').textContent);}catch{throw new Error('SERVER_ORIGIN_INVALID');}
    if(boot.platform?.kind!=='extension')throw new Error('SERVER_ORIGIN_INVALID');
    return {extension:true,serverOrigin:boot.platform.serverOrigin};
}
export async function apiFetch(path,options={}) {
    const context=platform(),target=apiTarget(path,context);
    if(!isOnline())throw new Error('OFFLINE');
    return globalThis.fetch(target,{
        signal:AbortSignal.timeout(15000),...options,
        credentials:context.extension?'include':'same-origin',cache:'no-store',redirect:'error',
    });
}
export function accountUrl() {
    const context=platform();
    return context.extension?new URL('/account',apiTarget('/api/user',context)).href:'/account';
}
export async function storageNamespace() {
    const context=platform();
    if(!context.extension)return {database:'search-startpage',legacy:'search-startpage-v1'};
    const origin=new URL(apiTarget('/api/user',context)).origin;
    const digest=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(origin));
    const key=[...new Uint8Array(digest)].map(byte=>byte.toString(16).padStart(2,'0')).join('');
    // Rebuilding an extension for another installation must not reuse its account checkpoint or private files.
    return {database:'extension-'+key,legacy:'search-extension-v1-'+key};
}
