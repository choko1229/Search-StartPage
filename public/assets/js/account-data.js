const collections = ['favorites', 'favorite-folders', 'history', 'providers-web', 'providers-ai', 'backgrounds'];

// Sync records ownership only after a successful server acknowledgement.
// Unowned records (including local-only backgrounds) are never removed here.
export function removeSyncedData(state, userId) {
    const ownership = state.syncOwnership;
    const generalOwned=ownership&&String(ownership.userId)===String(userId);
    const backgroundsOwned=state.backgroundOwnership&&String(state.backgroundOwnership.userId)===String(userId);
    const pendingOwned=state.backgroundUploadIntents?.[String(userId)];
    if(!generalOwned&&!backgroundsOwned&&!pendingOwned)return state;
    const next = {...state};
    if(pendingOwned){next.backgroundUploadIntents={...state.backgroundUploadIntents};delete next.backgroundUploadIntents[String(userId)];}
    for (const key of collections) {
        const ids = generalOwned?ownership.collections?.[key]:null;
        if (!Array.isArray(ids) || !Array.isArray(state[key])) continue;
        const owned = new Set(ids);
        next[key] = state[key].filter(item => !owned.has(item.id) || (key === 'backgrounds' && keepBackground(item,userId)));
        // An absent provider override uses public installation defaults after logout.
        if ((key === 'providers-web' || key === 'providers-ai') && next[key].length === 0) delete next[key];
    }
    if(backgroundsOwned) {
        const ids=new Set(Array.isArray(state.backgroundOwnership.ids)?state.backgroundOwnership.ids:[]);
        next.backgrounds=(next.backgrounds||[]).filter(item=>!ids.has(item.id)||keepBackground(item,userId));
        delete next.backgroundOwnership;
        if(String(state.backgroundCheckpoint?.userId)===String(userId))delete next.backgroundCheckpoint;
    }
    if(generalOwned){
        next.settings = {...state.settings};
        for (const key of Array.isArray(ownership.settings) ? ownership.settings : []) delete next.settings[key];
        delete next.syncOwnership;delete next.syncStatus;delete next.syncCheckpoint;
    }
    return next;
}
function keepBackground(item,userId) {
    return item.cloudSync===false||item.localOnly===true||(item.cloudOwner!==undefined&&String(item.cloudOwner)!==String(userId));
}
export function syncedDataRemoval(state,userId) {
    const clean=removeSyncedData(state,userId);
    const retained=new Set((clean.backgrounds||[]).map(item=>item.fileId));
    const files=(state.backgrounds||[]).filter(item=>item.fileId===item.id&&typeof item.id==='string'&&/^[a-zA-Z0-9_-]{1,80}$/.test(item.id)&&!retained.has(item.fileId)).map(item=>({id:item.fileId,blob:null}));
    const pending=state.backgroundUploadIntents?.[String(userId)];
    if(pending&&/^_bg_upload_[a-f0-9]{64}$/.test(pending.fileKey))files.push({id:pending.fileKey,blob:null});
    return {values:{...Object.fromEntries(Object.keys(state).map(name=>[name,null])),...clean},files};
}

export function localUsage(state) {
    const pendingBytes=Object.values(state.backgroundUploadIntents||{}).reduce((sum,item)=>sum+(Number.isSafeInteger(item?.fileSize)&&item.fileSize>0?item.fileSize:0),0);
    const backgroundBytes=pendingBytes+(Array.isArray(state.backgrounds)?state.backgrounds:[]).reduce((sum,item)=>{
        const size=item.fileId===item.id?item.fileSize:item.size;return sum+(Number.isSafeInteger(size)&&size>0?size:0);
    },0);
    const metadataBytes=new TextEncoder().encode(JSON.stringify(state)).byteLength;
    return {
        bytes:metadataBytes+backgroundBytes,metadataBytes,
        lastSync: state.syncStatus?.lastSync || null,
        backgroundBytes,
    };
}
