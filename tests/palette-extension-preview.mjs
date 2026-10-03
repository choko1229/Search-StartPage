import {paletteCommands} from '/assets/js/command-palette.js';
import {get,setMany} from '/assets/js/store.js';
import {providers} from '/assets/js/providers.js';
const status=document.getElementById('extension-status');
const id='extension:verification';let dispose=null,count=0,fail=false;
function refresh(message){status.textContent=`${message}; successful executions ${count}; saved usage ${get('commandUsage',{})[id]?.count||0}`;}
document.getElementById('extension-register').addEventListener('click',()=>{
    if(dispose){refresh('Already registered');return;}
    dispose=paletteCommands.register({id,title:'Palette extension verification <img src=x onerror=alert(1)>',category:'commands',effect:'state',confirmationKey:'extension-verification',run:async()=>{
        if(fail){fail=false;refresh('Injected action failure');throw new Error('test_action_failure');}
        count++;refresh('Executed registered command');
    }});
    refresh('Registered');window.dispatchEvent(new CustomEvent('data-change'));
});
document.getElementById('extension-remove').addEventListener('click',()=>{
    dispose?.();dispose=null;refresh('Removed');window.dispatchEvent(new CustomEvent('data-change'));
});
document.getElementById('extension-fail').addEventListener('click',()=>{fail=true;refresh('Failure armed');});
document.getElementById('history-prepare').addEventListener('click',async()=>{
    if(location.origin!=='http://127.0.0.1:8089')throw new Error('Isolated origin required');
    const id='palette-localhost';
    await setMany(state=>({'providers-web':[
        ...providers('web',false).filter(row=>row.id!==id),
        {id,name:'Palette localhost verification',prefix:'plocal',url:location.origin+'/_test/palette-extension-preview.php?q={query}',enabled:true},
    ],settings:{...state.settings,webDefault:id,saveHistory:true,externalSuggest:false}}));
    refresh('Localhost provider prepared');
});
window.addEventListener('data-change',()=>refresh(dispose?'Registered':'Not registered'));
refresh('Not registered');
