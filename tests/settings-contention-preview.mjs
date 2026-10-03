import {get,set,saveSettings,flush} from '/assets/js/store.js';
import {createRegionSettings} from '/assets/js/region-editor.js';
import {t,node} from '/assets/js/i18n.js';
let warnings=0;
window.addEventListener('storage-unavailable',()=>warnings++);
const saved=document.getElementById('saved'),result=document.getElementById('result');
function refresh(){saved.textContent=`保存済み地域：${JSON.stringify(get('settings',{}).themeRegion??null)} / 保存警告：${warnings}`;}
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
