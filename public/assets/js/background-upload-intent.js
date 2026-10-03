import {backgroundRecord,backgroundPayload} from './background-sync-core.js';
import {equal} from './sync-core.js';

export function uploadIntent({userId,before,target,version=null,rules={}},file) {
    const bytes=crypto.getRandomValues(new Uint8Array(32));
    const requestId=Array.from(bytes,value=>value.toString(16).padStart(2,'0')).join('');
    const intent={userId:String(userId),requestId,fileKey:'_bg_upload_'+requestId,
        before:before??null,target,payload:backgroundPayload(target),version,rules,fileSize:file.size};
    validateUploadIntent(intent,userId,file);return intent;
}
export function validateUploadIntent(intent,userId,file=null) {
    if(!intent||intent.userId!==String(userId)||!/^[1-9]\d*$/.test(intent.userId)||!/^[a-f0-9]{64}$/.test(intent.requestId)||intent.fileKey!=='_bg_upload_'+intent.requestId||
        !/^[a-zA-Z0-9_-]{1,80}$/.test(intent.target?.id)||intent.before&&intent.before.id!==intent.target.id||
        intent.target?.source?.[0]?.sourceType!=='upload'||!intent.target.source[0].revision?.startsWith('local:'+intent.target.id+':')||
        !equal(intent.payload,backgroundPayload(intent.target))||!Number.isSafeInteger(intent.fileSize)||intent.fileSize<=0||
        intent.version!==null&&(!Number.isSafeInteger(intent.version)||intent.version<0))throw new Error('BACKGROUND_UPLOAD_INTENT_INVALID');
    if(file!==null&&(!(file instanceof Blob)||file.size!==intent.fileSize))throw new Error('BACKGROUND_UPLOAD_INTENT_FILE_INVALID');
    return intent;
}
export function prepareUpload(state,intent,file) {
    validateUploadIntent(intent,intent.userId,file);
    if(state.backgroundUploadIntents?.[intent.userId])throw new Error('BACKGROUND_UPLOAD_PENDING');
    const row=(state.backgrounds||[]).find(value=>value.id===intent.target.id);
    if(!row||row.cloudOwner!==undefined&&String(row.cloudOwner)!==intent.userId||!equal(backgroundRecord(row),intent.before))throw new Error('BACKGROUND_CHANGED');
    return {values:{backgroundUploadIntents:{...state.backgroundUploadIntents,[intent.userId]:intent}},files:[{id:intent.fileKey,blob:file}]};
}
export function clearUpload(state,intent) {
    validateUploadIntent(intent,intent.userId);
    if(state.backgroundUploadIntents?.[intent.userId]?.requestId!==intent.requestId)throw new Error('BACKGROUND_UPLOAD_INTENT_CHANGED');
    const next={...state.backgroundUploadIntents};delete next[intent.userId];
    return {values:{backgroundUploadIntents:next},files:[{id:intent.fileKey,blob:null}]};
}
export const uploadGuard=state=>({backgroundUploadIntents:state.backgroundUploadIntents??null});
