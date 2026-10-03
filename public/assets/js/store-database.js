// One record per top-level collection avoids overwriting unrelated tab edits.
export async function openStateDatabase(legacy,namespace='search-startpage') {
    if(typeof namespace!=='string'||!/^[a-z][a-z0-9-]{0,79}$/.test(namespace))throw new Error('invalid_storage_namespace');
    if(!globalThis.indexedDB)return null;
    let db,channel;
    try {
        db=await new Promise((resolve,reject)=>{
            const request=indexedDB.open(namespace,2);
            request.onupgradeneeded=()=>{
                if(!request.result.objectStoreNames.contains('state'))request.result.createObjectStore('state');
                if(!request.result.objectStoreNames.contains('background-files'))request.result.createObjectStore('background-files');
            };
            request.onsuccess=()=>resolve(request.result);request.onerror=()=>reject(request.error);
            request.onblocked=()=>reject(new Error('storage_blocked'));
        });
        const read=()=>new Promise((resolve,reject)=>{
            const tx=db.transaction('state','readonly'),result={};
            const request=tx.objectStore('state').openCursor();
            request.onsuccess=()=>{const cursor=request.result;if(cursor){result[cursor.key]=cursor.value;cursor.continue();}};
            tx.oncomplete=()=>resolve(result);tx.onerror=()=>reject(tx.error);tx.onabort=()=>reject(tx.error);
        });
        channel=globalThis.BroadcastChannel?new BroadcastChannel(namespace+'-data'):null;
        const write=(values,files=[],expected=null)=>new Promise((resolve,reject)=>{
            const tx=db.transaction(files.length?['state','background-files']:'state','readwrite');
            tx.oncomplete=()=>{channel?.postMessage(true);resolve();};
            tx.onerror=()=>reject(tx.error);tx.onabort=()=>reject(tx.error || new Error('storage_aborted'));
            function apply(){try {
                for(const [key,value] of Object.entries(values))tx.objectStore('state').put(value,key);
                for(const {id,blob} of files){const store=tx.objectStore('background-files');if(blob===null)store.delete(id);else store.put(blob,id);}
            }catch(error){try{tx.abort();}catch{}reject(error);}}
            const conditions=Object.entries(expected||{});let remaining=conditions.length;
            if(!remaining)apply();
            else for(const [key,value] of conditions){
                const request=tx.objectStore('state').get(key);
                request.onsuccess=()=>{
                    if(JSON.stringify(request.result??null)!==JSON.stringify(value??null)){try{tx.abort();}catch{}reject(new Error('storage_conflict'));return;}
                    if(--remaining===0)apply();
                };
            }
        });
        const file=id=>new Promise((resolve,reject)=>{
            const tx=db.transaction('background-files','readonly'),request=tx.objectStore('background-files').get(id);
            tx.oncomplete=()=>resolve(request.result??null);tx.onerror=()=>reject(tx.error);tx.onabort=()=>reject(tx.error);
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
        return {initial,read,write,file,listen:listener=>{if(channel)channel.onmessage=listener;}};
    }catch(error) {db?.close();channel?.close();throw error;}
}
