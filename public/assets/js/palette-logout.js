import {get,setting,setMany} from './store.js';
import {request} from './sync-api.js';
import {syncedDataRemoval} from './account-data.js';
import {logoutFromPalette,recoverPaletteLogout} from './palette-logout-core.js';
const io={request,pending:()=>get('paletteLogoutPending',null),clearOnLogout:()=>setting('clearSyncedOnLogout',true),
    prepare:intent=>setMany({paletteLogoutPending:intent}),
    complete:intent=>setMany(state=>matches(state,intent)?{...(intent.clear?syncedDataRemoval(state,intent.userId).values:{}),paletteLogoutPending:null}:{},state=>matches(state,intent)&&intent.clear?syncedDataRemoval(state,intent.userId).files:[])};
const matches=(state,intent)=>state.paletteLogoutPending?.userId===intent.userId&&state.paletteLogoutPending?.clear===intent.clear;
export const logoutPaletteAccount=()=>logoutFromPalette(io);
export const recoverPaletteAccount=()=>recoverPaletteLogout(io);
