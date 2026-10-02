import {backgroundRecord,backgroundPayload,mergeBackgrounds} from './background-sync-core.js';
import {equal} from './sync-core.js';

// Pure transaction planner. Recalculate from the latest state every time the
// store retries a write so a received Blob cannot replace an in-flight edit.
export function backgroundAcknowledgement(state,{userId,before,target,acknowledged,blob=null,rules={},retainSentOriginal=false,keepLocal=false}) {
    const owner=String(userId),id=acknowledged.id,cloud=backgroundRecord(acknowledged,true);
    if(!/^[1-9]\d*$/.test(owner)||!Number.isSafeInteger(acknowledged.version)||acknowledged.version<1||target?.id!==id||before&&before.id!==id)throw new Error('INVALID_BACKGROUND');
    const list=Array.isArray(state.backgrounds)?state.backgrounds:[],current=list.find(row=>row.id===id);
    if(current?.cloudOwner!==undefined&&String(current.cloudOwner)!==owner)throw new Error('BACKGROUND_OWNER_CHANGED');
    // A local-only record created during a remote import must stay local.
    const latest=current?backgroundRecord(current):null;
    if(current&&!before&&!latest.cloudSync)throw new Error('BACKGROUND_LOCAL_COLLISION');
    const sentFile=target.source[0].revision?.startsWith('local:')===true;
    const mergeCloud=sentFile?{...cloud,source:target.source}:cloud;
    const merged=keepLocal?latest:mergeBackgrounds(before?{[id]:before}:{},latest?{[id]:latest}:{},{[id]:mergeCloud}).data[id];
    // A record removed while the network request was running stays removed.
    const files=[];
    let row=null;
    if(merged) {
        row={...current,...backgroundPayload(merged),cloudOwner:owner,localOnly:!merged.cloudSync,version:acknowledged.version};
        const source=merged.source[0],acceptFile=!keepLocal&&equal(source,mergeCloud.source[0]);
        if(source.sourceType==='upload') {
            const cached=current?.fileId===id&&current?.fileRevision===cloud.source[0].revision&&current?.syncedFileVersion===current?.fileVersion&&current?.fileSize===acknowledged.fileSize;
            if(acceptFile) {
                if(blob!==null&&(!(blob instanceof Blob)||blob.size!==acknowledged.fileSize))throw new Error('BACKGROUND_FILE_SIZE_INVALID');
                const original=!blob&&(merged.deleted||retainSentOriginal)&&sentFile&&current?.fileId===id&&equal(latest.source,target.source);
                if(!blob&&!cached&&!merged.deleted&&!original)throw new Error('BACKGROUND_FILE_MISSING');
                const version=sentFile?(current?.fileVersion||1):(cached?current.fileVersion:(current?.fileVersion||0)+1);
                Object.assign(row,{fileRevision:cloud.source[0].revision,fileSize:acknowledged.fileSize,syncedFileVersion:version,fileVersion:version,url:acknowledged.url});
                if(original){row.fileId=id;row.fileSize=current.fileSize;}
                else if(blob||cached){row.fileId=id;if(blob)files.push({id,blob});}
                else {delete row.fileId;if(current?.fileId)files.push({id:current.fileId,blob:null});}
            }else if(sentFile) {
                // The local file changed after upload started. Keep its Blob,
                // while remembering which older file the server acknowledged.
                row.fileRevision=cloud.source[0].revision;
                row.syncedFileVersion=Number(target.source[0].revision.split(':').at(-1));
            }
        }else {
            delete row.fileId;delete row.fileVersion;delete row.syncedFileVersion;delete row.fileRevision;row.fileSize=0;
            if(current?.fileId)files.push({id:current.fileId,blob:null});
        }
    }
    const previous=state.backgroundCheckpoint&&String(state.backgroundCheckpoint.userId)===owner?state.backgroundCheckpoint:{};
    const ownership=state.backgroundOwnership&&String(state.backgroundOwnership.userId)===owner?state.backgroundOwnership.ids:[];
    return {values:{backgrounds:row?[...list.filter(item=>item.id!==id),row]:list.filter(item=>item.id!==id),
        backgroundCheckpoint:{...previous,userId:owner,document:{...previous.document,[id]:cloud},rules:{...previous.rules,...rules}},
        backgroundOwnership:{userId:owner,ids:[...new Set([...(ownership||[]),id])]}}
        ,files};
}
