import {isExtension,isOnline} from './api-transport.js';
import {retrieveOfflineMedia,offlineMediaTarget} from './background-offline-core.js';
async function acquireOrigin(origin) {
    if(!isExtension())return ()=>{}; // Web pages use the remote server's normal CORS policy.
    if(!globalThis.chrome?.permissions)throw new Error('BACKGROUND_OFFLINE_PERMISSION');
    const required=chrome.runtime.getManifest().host_permissions?.includes(origin)===true;
    const prior=chrome.permissions.contains({origins:[origin]});
    const requested=chrome.permissions.request({origins:[origin]});
    const [held,granted]=await Promise.all([prior,requested]);
    if(!granted)throw new Error('BACKGROUND_OFFLINE_PERMISSION');
    return async()=>{if(!held&&!required)await chrome.permissions.remove({origins:[origin]});};
}
export function saveOfflineMedia(item) {
    if(!isOnline())return Promise.reject(new Error('OFFLINE'));
    if(!canSaveOfflineMedia(item))return Promise.reject(new Error('BACKGROUND_OFFLINE_EXTENSION_REQUIRED'));
    return retrieveOfflineMedia(item,{access:acquireOrigin});
}
export function canSaveOfflineMedia(item) {
    try{const target=offlineMediaTarget(item);return isExtension()||target.origin===location.origin;}catch{return false;}
}
