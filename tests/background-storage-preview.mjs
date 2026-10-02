import {openStateDatabase} from '/assets/js/store-database.js';
import {backgroundAcknowledgement} from '/assets/js/background-sync-ack.js';
import {backgroundRecord} from '/assets/js/background-sync-core.js';
import {syncedDataRemoval} from '/assets/js/account-data.js';
const database=await openStateDatabase({},'background-storage-verification');
const result=document.getElementById('result'),loaded=document.getElementById('loaded'),preview=document.getElementById('preview');let url;
const bytes=Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aNC8AAAAASUVORK5CYII='),value=>value.charCodeAt(0));
const picture=()=>new Blob([bytes],{type:'image/png'});
async function refresh(){
    const state=await database.read(),owned=await database.file('owned'),local=await database.file('local');
    loaded.textContent=`背景${state.backgrounds?.length||0}件 / 同期所有ファイル${owned?.size||0}bytes / 端末専用ファイル${local?.size||0}bytes / 同期記録${state.backgroundCheckpoint?.document?.owned?'あり':'なし'}`;
    if(url)URL.revokeObjectURL(url);if(local){url=URL.createObjectURL(local);preview.src=url;}else preview.removeAttribute('src');
}
document.getElementById('prepare').addEventListener('click',async()=>{
    try{
        const row={id:'owned',name:'同期背景',type:'image',sourceType:'upload',fileId:'owned',fileVersion:1,fileSize:bytes.length,cloudSync:true};
        await database.write({backgrounds:[row,{...row,id:'local',fileId:'local',name:'端末専用',cloudSync:false,localOnly:true}]},[{id:'owned',blob:picture()},{id:'local',blob:picture()}]);
        const ack={...row,fileRevision:'a'.repeat(64),url:'/api/backgrounds/owned/file',version:1};
        const plan=backgroundAcknowledgement(await database.read(),{userId:'1',before:backgroundRecord(row),target:backgroundRecord(row),acknowledged:ack,blob:picture()});
        await database.write(plan.values,plan.files);await refresh();result.textContent='成功：背景ファイルと同期記録を保存';
    }catch(error){result.textContent='失敗：'+error.name;}
});
document.getElementById('failure').addEventListener('click',async()=>{
    const before=JSON.stringify(await database.read()),file=await database.file('owned');
    try{await database.write({backgrounds:[],backgroundCheckpoint:{version:999}},[{id:'owned',blob:()=>{}}]);result.textContent='失敗：不正データを受け入れた';}
    catch{await refresh();const unchanged=JSON.stringify(await database.read())===before&&(await database.file('owned'))?.size===file?.size;result.textContent=unchanged?'成功：保存失敗でも背景・同期記録・ファイルは不変':'失敗：一部だけ変更された';}
});
document.getElementById('cleanup').addEventListener('click',async()=>{
    try{const plan=syncedDataRemoval(await database.read(),'1');await database.write(plan.values,plan.files);await refresh();result.textContent='成功：同期所有ファイルだけ除去、端末専用ファイルを保持';}catch(error){result.textContent='失敗：'+error.name;}
});
await refresh();
