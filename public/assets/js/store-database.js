// One record per top-level collection avoids overwriting unrelated tab edits.
export async function openStateDatabase(legacy) {
    if(!globalThis.indexedDB)return null;
    let db,channel;
    try {
        db=await new Promise((resolve,reject)=>{
            const request=indexedDB.open('search-startpage',1);
            request.onupgradeneeded=()=>request.result.createObjectStore('state');
            request.onsuccess=()=>resolve(request.result);request.onerror=()=>reject(request.error);
            request.onblocked=()=>reject(new Error('storage_blocked'));
        });
        const read=()=>new Promise((resolve,reject)=>{
            const tx=db.transaction('state','readonly'),result={};
            const request=tx.objectStore('state').openCursor();
            request.onsuccess=()=>{const cursor=request.result;if(cursor){result[cursor.key]=cursor.value;cursor.continue();}};
            tx.oncomplete=()=>resolve(result);tx.onerror=()=>reject(tx.error);tx.onabort=()=>reject(tx.error);
        });
        channel=globalThis.BroadcastChannel?new BroadcastChannel('search-startpage-data'):null;
        const write=values=>new Promise((resolve,reject)=>{
            const tx=db.transaction('state','readwrite');
            tx.oncomplete=()=>{channel?.postMessage(true);resolve();};
            tx.onerror=()=>reject(tx.error);tx.onabort=()=>reject(tx.error || new Error('storage_aborted'));
            for(const [key,value] of Object.entries(values))tx.objectStore('state').put(value,key);
        });
        // Check and migrate under the same write transaction so two first-open
        // tabs cannot replace each other's already migrated state.
        await new Promise((resolve,reject)=>{
            const tx=db.transaction('state','readwrite'),store=tx.objectStore('state');
            const marker=store.get('storageInitialized');
            marker.onsuccess=()=>{if(marker.result!==true)for(const [key,value] of Object.entries({...legacy,storageInitialized:true}))store.put(value,key);};
            tx.oncomplete=resolve;tx.onerror=()=>reject(tx.error);tx.onabort=()=>reject(tx.error);
        });
        const initial=await read();
        // Initialized databases never re-import an obsolete legacy copy.
        db.onversionchange=()=>{db.close();window.dispatchEvent(new CustomEvent('storage-unavailable'));};
        return {initial,read,write,listen:listener=>{if(channel)channel.onmessage=listener;}};
    }catch(error) {db?.close();channel?.close();throw error;}
}
