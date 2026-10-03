import {openStateDatabase} from '/assets/js/store-database.js';
import {createPaletteStorage} from '/assets/js/palette-storage.js';
const database=await openStateDatabase({},'palette-storage-verification');
let state=await database.read(),fail=false;
const result=document.getElementById('result'),loaded=document.getElementById('loaded');
const assert=(value,message)=>{if(!value)throw new Error(message);};
const store={get:(key,fallback)=>state[key]??fallback,setting:(key,fallback)=>state.settings?.[key]??fallback,
    setMany:async(values,files=[],conditions=null)=>{
        const current=structuredClone(state),patch=typeof values==='function'?values(current):values;
        const operations=typeof files==='function'?files(current):files;
        const expected=typeof conditions==='function'?conditions(current):conditions;
        try{await database.write({...patch,...(fail?{invalid:()=>{}}:{})},operations,expected);}
        catch(error){state=await database.read();throw error;}
        state=await database.read();
    }};
const palette=createPaletteStorage(store),intent={userId:'1',clear:true},replacement={userId:'2',clear:false};
async function refresh(){
    state=await database.read();
    loaded.textContent=`保存済み：履歴${state.history?.length||0}件 / お気に入り${state.favorites?.length||0}件 / 同期ファイル${(await database.file('owned'))?.size||0}bytes / 端末専用ファイル${(await database.file('off'))?.size||0}bytes / 保留記録${state.paletteLogoutPending?'あり':'なし'}`;
}
async function failure(operation,check){
    fail=true;let failed=false;
    try{await operation();}catch(error){assert(error.name==='DataCloneError','Unexpected failure '+error.name);failed=true;}finally{fail=false;}
    assert(failed,'Failed write unexpectedly accepted');await check();
}
document.getElementById('verify').addEventListener('click',async()=>{
    const button=document.getElementById('verify');button.disabled=true;result.textContent='検証中';
    try{
        await store.setMany({history:[{id:'generated',query:'Generated history'}],favorites:[{id:'owned'},{id:'private'}],
            settings:{clearSyncedOnLogout:true},paletteLogoutPending:null,
            syncOwnership:{userId:'1',settings:[],collections:{favorites:['owned']}},
            backgrounds:[{id:'owned',fileId:'owned',cloudOwner:'1',cloudSync:true},{id:'off',fileId:'off',cloudSync:false}],
            backgroundOwnership:{userId:'1',ids:['owned','off']}},[{id:'owned',blob:new Blob(['cloud'])},{id:'off',blob:new Blob(['local'])}]);
        await failure(palette.clearHistory,async()=>assert(state.history.length===1,'History lost on abort'));
        await palette.clearHistory();assert(state.history.length===0,'History retry failed');
        await failure(()=>palette.deleteFavorite('owned'),async()=>assert(state.favorites.length===2,'Favorite lost on abort'));
        await palette.deleteFavorite('owned');assert(state.favorites[0].id==='private','Favorite retry failed');
        await failure(()=>palette.prepare(intent),async()=>assert(palette.pending()===null,'Intent changed on abort'));
        await palette.prepare(intent);
        await failure(()=>palette.complete(intent),async()=>{
            assert(palette.pending()?.userId==='1','Pending intent lost');
            assert((await database.file('owned'))?.size===5,'Owned Blob lost on abort');
            assert(state.backgrounds.length===2,'Metadata lost on abort');
        });
        // Commit in another connection/state view without refreshing the caller.
        await database.write({paletteLogoutPending:replacement});
        let conflict=false;try{await palette.complete(intent);}catch(error){assert(error.message==='storage_conflict','Wrong conflict');conflict=true;}
        assert(conflict,'Stale intent accepted');assert((await database.file('owned'))?.size===5,'Race removed Blob');
        await palette.complete(replacement);assert(palette.pending()===null,'Clear OFF did not complete');
        assert(state.backgrounds.length===2,'Clear OFF removed data');
        await palette.prepare(intent);await palette.complete(intent);
        assert(palette.pending()===null,'Pending intent not cleared');assert(await database.file('owned')===null,'Owned Blob retained');
        assert((await database.file('off'))?.size===5,'Local Blob removed');assert(state.backgrounds[0].id==='off','Local metadata removed');
        await refresh();result.textContent='成功：履歴・お気に入りの失敗保持と再試行、保留保存の失敗、清掃失敗の原子性、別タブ競合、削除OFF、所有ファイル清掃を確認';
    }catch(error){await refresh();result.textContent='失敗：'+error.message;}finally{button.disabled=false;}
});
await refresh();
