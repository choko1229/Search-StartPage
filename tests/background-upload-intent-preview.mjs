import {openStateDatabase} from '/assets/js/store-database.js';
import {backgroundAcknowledgement} from '/assets/js/background-sync-ack.js';
import {backgroundRecord} from '/assets/js/background-sync-core.js';
import {uploadIntent,prepareUpload,uploadGuard} from '/assets/js/background-upload-intent.js';
const database=await openStateDatabase({},'background-upload-verification');
const result=document.getElementById('result'),loaded=document.getElementById('loaded'),preview=document.getElementById('preview');let url;
const bytes=Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aNC8AAAAASUVORK5CYII='),value=>value.charCodeAt(0));
const picture=()=>new Blob([bytes],{type:'image/png'});
async function refresh(){
    const state=await database.read(),intent=state.backgroundUploadIntents?.['1'];
    const original=await database.file('intent-image'),pending=intent?await database.file(intent.fileKey):null;
    loaded.textContent=`背景${state.backgrounds?.length||0}件 / 原本${original?.size||0}bytes / 送信記録${intent?'あり':'なし'} / 送信原本${pending?.size||0}bytes / 確定記録${state.backgroundCheckpoint?.document?.['intent-image']?'あり':'なし'}`;
    if(url)URL.revokeObjectURL(url);if(original){url=URL.createObjectURL(original);preview.src=url;}else preview.removeAttribute('src');
}
function ackPlan(state){
    const intent=state.backgroundUploadIntents?.['1'];if(!intent)throw new Error('Missing intent');
    return backgroundAcknowledgement(state,{userId:'1',before:intent.before,target:intent.target,intent,retainSentOriginal:true,
        acknowledged:{...intent.payload,fileRevision:'b'.repeat(64),fileSize:bytes.length,url:'/api/backgrounds/intent-image/file',version:1}});
}
document.getElementById('prepare').addEventListener('click',async()=>{
    try{
        const state=await database.read();if(state.backgroundUploadIntents?.['1']){result.textContent='保持：既存の送信記録を確認';return;}
        const row={id:'intent-image',name:'送信原本',type:'image',sourceType:'upload',fileId:'intent-image',fileVersion:1,fileSize:bytes.length,cloudSync:true};
        await database.write({backgrounds:[row],backgroundCheckpoint:null,backgroundUploadIntents:{}},[{id:row.id,blob:picture()}]);
        const before=await database.read(),intent=uploadIntent({userId:'1',before:backgroundRecord(row),target:backgroundRecord(row)},picture());
        const plan=prepareUpload(before,intent,picture());await database.write(plan.values,plan.files,uploadGuard(before));await refresh();result.textContent='成功：送信記録と原本を同時に保存';
    }catch(error){result.textContent='失敗：'+error.name;}
});
document.getElementById('failure').addEventListener('click',async()=>{
    try{
        const state=await database.read(),plan=ackPlan(state),before=JSON.stringify(state);let rejected=0;
        try{await database.write(plan.values,[...plan.files,{id:'invalid',blob:()=>{}}],uploadGuard(state));}catch{rejected++;}
        try{await database.write(plan.values,plan.files,{backgroundUploadIntents:{stale:true}});}catch{rejected++;}
        const current=await database.read(),intent=current.backgroundUploadIntents?.['1'];
        const unchanged=JSON.stringify(current)===before&&(await database.file(intent.fileKey))?.size===bytes.length&&(await database.file('intent-image'))?.size===bytes.length;
        await refresh();result.textContent=rejected===2&&unchanged?'成功：保存失敗と競合でも送信記録・原本・確定記録は不変':'失敗：部分変更を検出';
    }catch(error){result.textContent='失敗：'+error.name;}
});
document.getElementById('ack').addEventListener('click',async()=>{
    try{const state=await database.read(),plan=ackPlan(state);await database.write(plan.values,plan.files,uploadGuard(state));await refresh();result.textContent='成功：確定記録を保存し、送信記録と送信用原本だけ除去';}catch(error){result.textContent='失敗：'+error.name;}
});
await refresh();
