import {safeUrl} from './search-core.js';
import {inspectBackgroundFile} from './background-file-core.js';
import {normalizeBackground} from './background-core.js';
import {recordSettings} from './settings-history.js';
const formats={'image/jpeg':'jpg','image/png':'png','image/gif':'gif','image/webp':'webp','image/avif':'avif','video/mp4':'mp4','video/webm':'webm'};
export function offlineMediaTarget(item) {
    let candidate=item?.url;
    if(typeof candidate==='string'&&/^\/(?!\/)/.test(candidate)&&!candidate.includes('\\')){
        try{candidate=new URL(candidate,globalThis.location?.href).href;}catch{candidate=null;}
    }
    const url=safeUrl(candidate);
    if(!url||item.sourceType!=='url'||!['image','video'].includes(item.type)||item.deleted)throw new Error('BACKGROUND_OFFLINE_INVALID');
    const parsed=new URL(url);
    return {url,origin:parsed.origin,permission:parsed.protocol+'//'+parsed.hostname+'/*',limit:(item.type==='image'?25:500)*1024*1024,type:item.type};
}
export async function retrieveOfflineMedia(item,{fetcher=globalThis.fetch,access=async()=>()=>{},signal=AbortSignal.timeout(660000)}={}) {
    const target=offlineMediaTarget(item);
    const release=await access(target.permission); // Invoked directly from the user's click, before any other await.
    if(typeof release!=='function')throw new Error('BACKGROUND_OFFLINE_PERMISSION');
    let response,reader;
    try{
        response=await fetcher(target.url,{credentials:'omit',redirect:'error',cache:'no-store',signal});
        if(response.status!==200||!response.body)throw new Error('BACKGROUND_OFFLINE_UNAVAILABLE');
        const mime=response.headers.get('Content-Type')?.split(';')[0].trim().toLowerCase();
        if(!Object.hasOwn(formats,mime)||mime.startsWith('image/')!==(target.type==='image'))throw new Error('BACKGROUND_MIME_MISMATCH');
        const length=response.headers.get('Content-Length');
        if(length!==null&&(!/^\d+$/.test(length)||Number(length)>target.limit))throw new Error('BACKGROUND_TOO_LARGE');
        reader=response.body.getReader();const chunks=[];let size=0;
        for(;;){const chunk=await reader.read();if(chunk.done)break;size+=chunk.value.byteLength;if(size>target.limit)throw new Error('BACKGROUND_TOO_LARGE');chunks.push(chunk.value);}
        if(length!==null&&!response.headers.get('Content-Encoding')&&Number(length)!==size)throw new Error('BACKGROUND_FILE_SIZE_INVALID');
        const file=new File(chunks,'offline-background.'+formats[mime],{type:mime});
        const metadata=await inspectBackgroundFile(file);
        if(metadata.type!==target.type)throw new Error('BACKGROUND_MIME_MISMATCH');
        return {file,metadata};
    }catch(error){if(reader)await reader.cancel().catch(()=>{});else await response?.body?.cancel().catch(()=>{});throw error;}
    finally{reader?.releaseLock();await release();}
}
export function backgroundAccount(state) {return String(state.syncOwnership?.userId??state.backgroundCheckpoint?.userId??state.syncCheckpoint?.userId??'local');}
export function offlineCopyGuard(state) {
    return Object.fromEntries(['backgrounds','settings','settingsHistory','syncOwnership','backgroundCheckpoint','syncCheckpoint'].map(key=>[key,state[key]??null]));
}
export function offlineCopyValues(state,intent,id,metadata,suffix,time=new Date().toISOString()) {
    const source=(state.backgrounds||[]).find(row=>row.id===intent.id);
    if(backgroundAccount(state)!==intent.account||!source||source.deleted||source.sourceType!=='url'||offlineMediaTarget(source).url!==intent.url||source.type!==intent.type)throw new Error('BACKGROUND_CHANGED');
    if((state.backgrounds||[]).some(row=>row.id===id))throw new Error('BACKGROUND_CHANGED');
    const value={...source,...metadata,id,fileId:id,fileVersion:1,url:'',sourceType:'upload',localOnly:true,cloudSync:false,deleted:false,
        name:[...source.name+' '+suffix].slice(0,100).join(''),createdAt:time,updatedAt:time};
    for(const field of ['version','fileRevision','syncedFileVersion'])delete value[field];
    const copy=normalizeBackground(value);if(!copy)throw new Error('BACKGROUND_OFFLINE_INVALID');
    const result={backgrounds:[...state.backgrounds,copy]};
    // An intervening selection must not be overridden by a delayed download.
    if((state.settings?.backgroundSelected??'')===intent.selection&&(state.settings?.backgroundMode??'theme')===intent.mode){
        const patch={backgroundMode:'library',backgroundSelected:id};result.settings={...state.settings,...patch};
        result.settingsHistory=recordSettings(state.settingsHistory,state.settings||{},patch,'background',time);
    }
    return result;
}
