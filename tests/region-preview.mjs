import {createRegionSettings} from '/assets/js/region-editor.js';
import {openStateDatabase} from '/assets/js/store-database.js';
import {t,node} from '/assets/js/i18n.js';
const database=await openStateDatabase({},'region-settings-verification');
let state=await database.read(),pending=[],failure=false;
const saved=document.getElementById('saved');
function refresh(){saved.textContent=`保存済み：${state.region?JSON.stringify(state.region):'未設定'} / 保存失敗：${failure?'有効':'無効'} / 位置要求：${pending.length}件`;
}
const editor=createRegionSettings(document.getElementById('settings-appearance'),{
    t,node,readRegion:()=>state.region??null,
    saveRegion:async region=>{await database.write({region,...failure?{invalid:()=>{}}:{}});state=await database.read();refresh();},
    locateRegion:()=>new Promise((resolve,reject)=>{pending.push({resolve,reject});refresh();}),
});
document.getElementById('success').addEventListener('click',()=>{pending.shift()?.resolve({latitude:0,longitude:0});refresh();});
document.getElementById('denied').addEventListener('click',()=>{pending.shift()?.reject(new Error('region_denied'));refresh();});
document.getElementById('failure').addEventListener('click',()=>{failure=true;refresh();});
document.getElementById('retry').addEventListener('click',()=>{failure=false;refresh();});
document.getElementById('discard').addEventListener('click',()=>editor.discard());
refresh();
