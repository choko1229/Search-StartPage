const endpoint='/api/backgrounds';
const limits={image:25*1024*1024,video:500*1024*1024};
function itemPath(id) {
    if(typeof id!=='string'||!/^[a-zA-Z0-9_-]{1,80}$/.test(id))throw new Error('INVALID_BACKGROUND');
    return endpoint+'/'+id;
}
async function json(url,options={}) {
    const response=await fetch(url,{credentials:'same-origin',cache:'no-store',redirect:'error',signal:AbortSignal.timeout(15000),...options});
    let payload;try{payload=await response.json();}catch{throw new Error('BACKGROUND_RESPONSE_INVALID');}
    if(!payload||typeof payload!=='object'||typeof payload.success!=='boolean')throw new Error('BACKGROUND_RESPONSE_INVALID');
    return {status:response.status,data:payload.data,error:payload.error};
}
async function csrf() {
    const result=await json('/api/csrf');
    if(result.status!==200||typeof result.data?.csrf_token!=='string')throw new Error('BACKGROUND_CSRF_FAILED');
    return result.data.csrf_token;
}
function identity(userId) {
    if(!/^[1-9]\d*$/.test(String(userId)))throw new Error('BACKGROUND_OWNER_INVALID');
    return String(userId);
}
export function readBackgrounds(){return json(endpoint);}
export async function createBackground(item,userId,file=null,version=null) {
    itemPath(item?.id);const owner=identity(userId);
    if(version!==null&&(!Number.isSafeInteger(version)||version<0||file===null))throw new Error('SYNC_VERSION_INVALID');
    if(file!==null&&(!(file instanceof Blob)||!['image','video'].includes(item.type)||file.size<=0||file.size>limits[item.type]))throw new Error('INVALID_UPLOAD');
    const token=await csrf();
    if(file===null)return json(endpoint+'/url',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':token},body:JSON.stringify({user_id:owner,item})});
    const extensions={'image/jpeg':'jpg','image/png':'png','image/gif':'gif','image/webp':'webp','image/avif':'avif','video/mp4':'mp4','video/webm':'webm'};
    const extension=extensions[file.type];if(!extension)throw new Error('INVALID_UPLOAD');
    const form=new FormData();form.set('user_id',owner);form.set('item',JSON.stringify(item));form.set('file',file,'background.'+extension);
    if(version!==null)form.set('version',String(version));
    return json((version===null?endpoint:itemPath(item.id))+'/upload',{method:'POST',headers:{'X-CSRF-Token':token},body:form,signal:AbortSignal.timeout(660000)});
}
export async function updateBackground(item,version,userId) {
    const path=itemPath(item?.id),owner=identity(userId);
    if(!Number.isSafeInteger(version)||version<0)throw new Error('SYNC_VERSION_INVALID');
    return json(path,{method:'PUT',headers:{'Content-Type':'application/json','X-CSRF-Token':await csrf()},body:JSON.stringify({user_id:owner,version,item})});
}
export async function downloadBackground(item) {
    const path=itemPath(item?.id);
    if(!['image','video'].includes(item.type)||item.sourceType!=='upload'||!Number.isSafeInteger(item.fileSize)||item.fileSize<=0||item.fileSize>limits[item.type])throw new Error('INVALID_BACKGROUND');
    if(item.fileRevision!==undefined&&!/^[a-f0-9]{64}$/.test(item.fileRevision))throw new Error('INVALID_BACKGROUND');
    // Always derive the private endpoint; never fetch a URL supplied in metadata.
    const response=await fetch(path+'/file',{credentials:'same-origin',cache:'no-store',redirect:'error',headers:item.fileRevision?{'If-Match':'"'+item.fileRevision+'"'}:{},signal:AbortSignal.timeout(660000)});
    async function reject(code){await response.body?.cancel().catch(()=>{});throw new Error(code);}
    if(response.status!==200)return reject('BACKGROUND_FILE_UNAVAILABLE');
    if(item.fileRevision&&response.headers.get('ETag')!=='"'+item.fileRevision+'"')return reject('BACKGROUND_FILE_REVISION_INVALID');
    const mime=response.headers.get('Content-Type')?.split(';')[0].trim().toLowerCase();
    const accepted=item.type==='image'?['image/jpeg','image/png','image/gif','image/webp','image/avif']:['video/mp4','video/webm'];
    if(!accepted.includes(mime))return reject('INVALID_UPLOAD');
    const length=response.headers.get('Content-Length');
    if(length!==null&&(!/^\d+$/.test(length)||Number(length)!==item.fileSize))return reject('BACKGROUND_FILE_SIZE_INVALID');
    if(!response.body)throw new Error('BACKGROUND_FILE_UNAVAILABLE');
    const reader=response.body.getReader(),chunks=[];let size=0;
    try {
        while(true){const result=await reader.read();if(result.done)break;size+=result.value.byteLength;if(size>item.fileSize)throw new Error('BACKGROUND_FILE_SIZE_INVALID');chunks.push(result.value);}
        if(size!==item.fileSize)throw new Error('BACKGROUND_FILE_SIZE_INVALID');
        return new Blob(chunks,{type:mime});
    } catch(error){await reader.cancel().catch(()=>{});throw error;}
    finally {reader.releaseLock();}
}
