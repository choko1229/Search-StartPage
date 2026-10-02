const collections = ['favorites', 'favorite-folders', 'history', 'providers-web', 'providers-ai', 'backgrounds'];

// Sync records ownership only after a successful server acknowledgement.
// Unowned records (including local-only backgrounds) are never removed here.
export function removeSyncedData(state, userId) {
    const ownership = state.syncOwnership;
    const generalOwned=ownership&&String(ownership.userId)===String(userId);
    const backgroundsOwned=state.backgroundOwnership&&String(state.backgroundOwnership.userId)===String(userId);
    if(!generalOwned&&!backgroundsOwned)return state;
    const next = {...state};
    for (const key of collections) {
        const ids = generalOwned?ownership.collections?.[key]:null;
        if (!Array.isArray(ids) || !Array.isArray(state[key])) continue;
        const owned = new Set(ids);
        next[key] = state[key].filter(item => !owned.has(item.id) || (key === 'backgrounds' && keepBackground(item,userId)));
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
    return {values:{...Object.fromEntries(Object.keys(state).map(name=>[name,null])),...clean},files};
}

export function localUsage(state) {
    const backgroundBytes=(Array.isArray(state.backgrounds)?state.backgrounds:[]).reduce((sum,item)=>{
        const size=item.fileId===item.id?item.fileSize:item.size;return sum+(Number.isSafeInteger(size)&&size>0?size:0);
    },0);
    const metadataBytes=new TextEncoder().encode(JSON.stringify(state)).byteLength;
    return {
        bytes:metadataBytes+backgroundBytes,metadataBytes,
        lastSync: state.syncStatus?.lastSync || null,
        backgroundBytes,
    };
}
