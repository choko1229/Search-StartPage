import {createRegionSettings} from '/assets/js/region-editor.js';
import {t,node} from '/assets/js/i18n.js';
// Test-only notification delay reproduces a stale tab using real IndexedDB.
const pause=node('input',undefined,{type:'checkbox'}),pauseLabel=node('label','他タブの通知を一時停止');
pauseLabel.prepend(pause);document.getElementById('settings-appearance').before(pauseLabel);
const NativeChannel=globalThis.BroadcastChannel;
if(NativeChannel)globalThis.BroadcastChannel=class extends NativeChannel{
    set onmessage(listener){super.onmessage=event=>{if(!pause.checked)listener?.(event);};}
};
const {get,set,setSetting,saveSettings,flush}=await import('/assets/js/store.js');
let warnings=0;
window.addEventListener('storage-unavailable',()=>warnings++);
const saved=document.getElementById('saved'),result=document.getElementById('result');
function refresh(){saved.textContent=`保存済み地域：${JSON.stringify(get('settings',{}).themeRegion??null)} / 文字サイズ：${get('settings',{}).fontSize??'未設定'} / 保存警告：${warnings}`;}
const font=node('input',undefined,{type:'number',min:'12',max:'48',value:'18','aria-label':'生成文字サイズ'});
const fontSave=node('button','文字サイズを保存',{type:'button'});
document.getElementById('settings-appearance').before(font,fontSave);
fontSave.addEventListener('click',async()=>{setSetting('fontSize',Number(font.value));try{await flush();result.textContent='文字サイズを保存しました。';}catch{result.textContent='文字サイズの保存に失敗しました。';}refresh();});
const editor=createRegionSettings(document.getElementById('settings-appearance'),{
    t,node,readRegion:()=>get('settings',{}).themeRegion??null,
    saveRegion:region=>saveSettings({themeRegion:region},'appearance'),
    locateRegion:()=>Promise.reject(new Error('region_unsupported')),
});
window.addEventListener('data-change',()=>{editor.read();refresh();});
document.getElementById('stress').addEventListener('click',async event=>{
    event.target.disabled=true;const before=warnings;let changes=0,commits=0;
    const timer=setInterval(()=>set('verificationActivity',{sequence:++changes}),0);
    try {
        for(let i=0;i<25;i++){
            await saveSettings({themeRegion:{latitude:0,longitude:i}},'appearance');commits++;
        }
        clearInterval(timer);await flush();
        const region=get('settings',{}).themeRegion;
        result.textContent=commits===25&&changes>0&&warnings===before&&region.latitude===0&&region.longitude===24
            ? `PASS：地域保存${commits}回 / 無関係な更新${changes}回 / 新規保存警告0回`
            : `FAIL：地域保存${commits}回 / 無関係な更新${changes}回 / 新規保存警告${warnings-before}回`;
    }catch(error){result.textContent=`FAIL：${error.message}`;}
    finally{clearInterval(timer);event.target.disabled=false;refresh();}
});
refresh();
