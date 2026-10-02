import {normalizeBackground} from './background-core.js';
import {mergeSync,resolveSync,equal} from './sync-core.js';
const appearance=['color','colorEnd','angle','blur','brightness','overlay','overlayColor','position','scale','fit','fixed','autoplay','loop','mute','speed','paused','fallback'];
const forbidden=new Set(['constructor','prototype','__proto__']);
export function backgroundRecord(value,cloud=false) {
    const row=normalizeBackground(value);
    if(!row||forbidden.has(row.id))throw new Error('INVALID_BACKGROUND');
    const sourceType=row.sourceType==='upload'?'upload':'url';
    let revision=null;
    if(sourceType==='upload') {
        if(cloud||row.syncedFileVersion===row.fileVersion){
            if(typeof row.fileRevision!=='string'||!/^[a-f0-9]{64}$/.test(row.fileRevision))throw new Error('BACKGROUND_FILE_REVISION_INVALID');
            revision=row.fileRevision;
        }else {
            if(row.fileId!==row.id||!Number.isSafeInteger(row.fileVersion)||row.fileVersion<1)throw new Error('BACKGROUND_FILE_REVISION_INVALID');
            revision='local:'+row.id+':'+row.fileVersion;
        }
    }
    // Type, source URL and file revision are one choice: independently merging
    // these fields could bind video settings to an unrelated image or URL.
    return {id:row.id,name:row.name,cloudSync:enabled(row),deleted:row.deleted===true,
        source:[{type:row.type,sourceType,url:sourceType==='upload'?'':row.url||'',revision}],
        appearance:Object.fromEntries(appearance.map(key=>[key,row[key]??(key==='fallback'?'':null)])),rule:row.rule??null};
}
function rows(value){if(!Array.isArray(value))throw new Error('INVALID_BACKGROUND');return value;}
function enabled(row){return typeof row.cloudSync==='boolean'?row.cloudSync:row.localOnly===false;}
export function backgroundDocuments(localRows,cloudRows,checkpoint,userId) {
    const owner=String(userId),base=checkpoint&&String(checkpoint.userId)===owner?checkpoint.document:{};
    const local={},cloud={},protectedIds=new Set(),localIds=new Set(),cloudIds=new Set();
    for(const row of rows(localRows)) {
        const normalized=normalizeBackground(row);if(!normalized)throw new Error('INVALID_BACKGROUND');
        if(localIds.has(row.id))throw new Error('invalid_sync_id');localIds.add(row.id);
        const foreign=row.cloudOwner!==undefined&&String(row.cloudOwner)!==owner;
        if(foreign||(!enabled(row)&&!Object.hasOwn(base,row.id))){protectedIds.add(row.id);continue;}
        if(Object.hasOwn(local,row.id))throw new Error('invalid_sync_id');
        local[row.id]=!enabled(row)&&Object.hasOwn(base,row.id)?{...base[row.id],cloudSync:false}:backgroundRecord(row);
    }
    for(const row of rows(cloudRows)) {
        if(!normalizeBackground(row))throw new Error('INVALID_BACKGROUND');
        if(cloudIds.has(row.id))throw new Error('invalid_sync_id');cloudIds.add(row.id);
        if(protectedIds.has(row.id)||(!enabled(row)&&!Object.hasOwn(base,row.id)))continue;
        if(Object.hasOwn(cloud,row.id))throw new Error('invalid_sync_id');
        cloud[row.id]=backgroundRecord(row,true);
    }
    return {previous:base,local,cloud,protectedIds:[...protectedIds]};
}
function conflictDocuments(previous,local,cloud) {
    const result=[{...previous},{...local},{...cloud}];
    const ids=new Set([...Object.keys(previous),...Object.keys(local),...Object.keys(cloud)]);
    for(const id of ids) {
        const before=previous[id],left=local[id],right=cloud[id];
        if(before&&left&&right&&left.deleted!==right.deleted&&!equal(before,left)&&!equal(before,right)) {
            // Archive versus concurrent edit needs a record-level decision.
            for(let index=0;index<3;index++)result[index][id]=[result[index][id]];
        }
    }
    return result.map(backgrounds=>({backgrounds}));
}
const unwrap=result=>Object.fromEntries(Object.entries(result.backgrounds).map(([id,row])=>[id,Array.isArray(row)?row[0]:row]));
export function mergeBackgrounds(previous,local,cloud,rules={}) {
    const result=mergeSync(...conflictDocuments(previous,local,cloud),rules);
    return {data:unwrap(result.data),conflicts:result.conflicts};
}
export function resolveBackgrounds(previous,local,cloud,choices,rules={}) {
    return unwrap(resolveSync(...conflictDocuments(previous,local,cloud),choices,rules));
}
export function backgroundPayload(record) {
    const source=record.source[0];
    return {id:record.id,name:record.name,...record.appearance,type:source.type,sourceType:source.sourceType,url:source.url,
        cloudSync:record.cloudSync,deleted:record.deleted,rule:record.rule};
}
