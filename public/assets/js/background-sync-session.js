import {backgroundDocuments,backgroundRecord,backgroundPayload,mergeBackgrounds,resolveBackgrounds} from './background-sync-core.js';
import {equal} from './sync-core.js';

// Persistence, authentication, transport and dialogs are adapters shared by Web
// and Extension. Each successful item has its own durable acknowledgement.
export class BackgroundSyncSession {
    constructor(io){this.io=io;this.busy=false;this.paused=false;}
    async run() {
        if(this.busy||this.paused)return false;
        this.busy=true;
        try {
            const user=await this.io.user();if(!user){this.io.status('signed_out');return false;}
            const owner=String(user.id),current=()=>this.io.current(owner);
            if(!await current())return false;
            let cloudRows=await this.io.read(),checkpoint=this.io.checkpoint(owner),conflicts=0;
            if(!checkpoint) {
                const documents=backgroundDocuments(this.io.local(),cloudRows,null,owner);
                let choice=Object.keys(documents.local).length&&Object.keys(documents.cloud).length?await this.io.initial():Object.keys(documents.cloud).length?'cloud':'local';
                if(choice==='later'){this.paused=true;this.io.status('later');return false;}
                if(!['local','cloud'].includes(choice))throw new Error('invalid_initial_choice');
                if(!await current())return false;
                await this.io.begin(owner,choice);checkpoint=this.io.checkpoint(owner);
            }
            const budget=Math.max(20,(this.io.local().length+cloudRows.length)*4+20);
            for(let step=0;step<budget;step++) {
                checkpoint=this.io.checkpoint(owner);
                const documents=backgroundDocuments(this.io.local(),cloudRows,checkpoint,owner);
                const rules=checkpoint.rules||{};let target,selectedRules={};
                if(checkpoint.pendingInitial==='local') {
                    target={...documents.local};
                    for(const [id,row] of Object.entries(documents.cloud))if(!target[id])target[id]={...row,deleted:true};
                }else if(checkpoint.pendingInitial==='cloud') {
                    target={...documents.cloud};
                    const extra=Object.keys(documents.local).find(id=>!target[id]);
                    if(extra){if(!await current())return false;await this.io.drop(extra,documents.local[extra],owner);continue;}
                }else {
                    const merged=mergeBackgrounds(documents.previous,documents.local,documents.cloud,rules);
                    target=merged.data;
                    if(merged.conflicts.length) {
                        const answer=await this.io.conflicts(merged.conflicts);
                        if(!answer){this.paused=true;this.io.status('conflict');return false;}
                        target=resolveBackgrounds(documents.previous,documents.local,documents.cloud,answer.choices,rules);selectedRules=answer.rules||{};
                    }
                }
                let progressed=false;
                for(const id of new Set([...Object.keys(target),...Object.keys(documents.cloud)])) {
                    const remote=cloudRows.find(row=>row.id===id),cloud=documents.cloud[id],before=documents.local[id];
                    let desired=target[id]||cloud&&{...cloud,deleted:true};if(!desired)continue;
                    const localRow=this.io.local().find(row=>row.id===id);
                    const keepLocal=desired.cloudSync===false;
                    if(keepLocal){if(!remote||remote.cloudSync===false)continue;desired={...cloud,cloudSync:false};}
                    const upload=desired.source[0].sourceType==='upload';
                    const writing=!equal(desired,cloud);
                    if(!writing&&!before&&desired.deleted&&equal(documents.previous[id],cloud))continue;
                    const cached=upload&&localRow?.fileRevision===remote?.fileRevision&&localRow?.fileSize===remote?.fileSize&&localRow?.syncedFileVersion===localRow?.fileVersion&&localRow?.fileId===id?await this.io.file(id):null;
                    if(!writing&&equal(before,desired)&&equal(documents.previous[id],cloud)&&(!upload||desired.deleted||cached))continue;
                    if(!await current())return false;
                    let acknowledged=remote,blob=null,retainSentOriginal=false;
                    if(writing) {
                        const sendingFile=upload&&(desired.source[0].revision?.startsWith('local:')||!remote);
                        if(sendingFile) {
                            const file=await this.io.file(id);if(!(file instanceof Blob))throw new Error('BACKGROUND_FILE_MISSING');
                            if(!desired.source[0].revision?.startsWith('local:'))desired={...desired,source:[{...desired.source[0],revision:'local:'+id+':'+localRow.fileVersion}]};
                            const response=await this.io.create(backgroundPayload(desired),owner,file,remote?.version??null);
                            if(response.status===409){cloudRows=await this.io.read();if(++conflicts>=3)throw new Error('sync_retry_exhausted');progressed=true;break;}
                            if(![200,201].includes(response.status))throw new Error('background_sync_failed');
                            acknowledged=response.data.item;retainSentOriginal=true;
                        }else {
                            const response=remote?await this.io.update(backgroundPayload(desired),remote.version,owner):await this.io.create(backgroundPayload(desired),owner);
                            if(response.status===409){cloudRows=await this.io.read();if(++conflicts>=3)throw new Error('sync_retry_exhausted');progressed=true;break;}
                            if(![200,201].includes(response.status))throw new Error('background_sync_failed');acknowledged=response.data.item;
                        }
                    }
                    if(!acknowledged)throw new Error('BACKGROUND_RESPONSE_INVALID');
                    if(upload&&!desired.deleted&&!retainSentOriginal&&!keepLocal&&!cached)blob=await this.io.download(acknowledged);
                    if(!await current())return false;
                    await this.io.accept({userId:owner,before,target:desired,acknowledged,blob,rules:selectedRules,retainSentOriginal,keepLocal});
                    cloudRows=[...cloudRows.filter(row=>row.id!==id),acknowledged];conflicts=0;progressed=true;break;
                }
                if(!progressed){if(!await current())return false;await this.io.finish(owner);this.io.status('synced');return true;}
            }
            throw new Error('background_sync_busy');
        }finally{this.busy=false;}
    }
}
