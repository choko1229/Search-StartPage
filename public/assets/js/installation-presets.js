import {get,setMany} from './store.js';
import {presets} from './i18n.js';
import {clientPresets} from './provider-presets.js';
import {apiFetch,isExtension,isOnline} from './api-transport.js';
let pending=null,lastRead=0;
function apply(rows) {presets.web=rows.web;presets.ai=rows.ai;}
export async function refreshInstallationPresets(force=false) {
    if(!isExtension())return;
    if(!isOnline())throw new Error('OFFLINE');
    if(pending)return pending;
    if(!force&&lastRead&&Date.now()-lastRead<60000)return;
    pending=(async()=>{
        const response=await apiFetch('/api/provider-presets');
        if(response.status!==200)throw new Error('PROVIDER_PRESETS_UNAVAILABLE');
        const payload=await response.json(),rows=clientPresets(payload.data?.presets);
        await setMany({installationPresets:rows});apply(rows);lastRead=Date.now();
        window.dispatchEvent(new CustomEvent('data-change',{detail:'settings'}));
    })();
    try{await pending;}finally{pending=null;}
}
if(isExtension()){
    try{apply(clientPresets(get('installationPresets',null)));}catch{}
    if(isOnline())void refreshInstallationPresets().catch(()=>{});
    window.addEventListener('online',()=>void refreshInstallationPresets(true).catch(()=>{}));
}
