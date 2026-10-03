import {syncedDataRemoval} from './account-data.js';

// Use the same atomic store operations from the product and storage tests.
export function createPaletteStorage(store) {
    const matches=(state,intent)=>state.paletteLogoutPending?.userId===intent.userId&&state.paletteLogoutPending?.clear===intent.clear;
    return {
        clearHistory:()=>store.setMany({history:[]}),
        deleteFavorite:id=>store.setMany(state=>({favorites:(state.favorites||[]).filter(row=>row.id!==id)})),
        pending:()=>store.get('paletteLogoutPending',null),
        clearOnLogout:()=>store.setting('clearSyncedOnLogout',true),
        prepare:intent=>store.setMany({paletteLogoutPending:intent}),
        complete:intent=>store.setMany(
            state=>matches(state,intent)?{...(intent.clear?syncedDataRemoval(state,intent.userId).values:{}),paletteLogoutPending:null}:{},
            state=>matches(state,intent)&&intent.clear?syncedDataRemoval(state,intent.userId).files:[],
            state=>matches(state,intent)?{paletteLogoutPending:state.paletteLogoutPending}:null,
        ),
    };
}
